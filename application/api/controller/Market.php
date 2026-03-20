<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Product;
use app\common\model\ProductCategory;
use app\common\model\ProductTradeTime;
use think\Config;

/**
 * 行情与产品接口
 */
class Market extends Api
{
    protected $noNeedLogin = ['products', 'detail', 'kline', 'snapshot', 'categories'];
    protected $noNeedRight = '*';

    /** 价格字段列表 */
    private static $priceFields = ['price', 'open_price', 'close_price', 'high_price', 'low_price', 'diff', 'buy_price', 'sell_price'];

    /**
     * 格式化价格：去掉尾部多余的0（3.560000 → 3.56）
     */
    private function formatPrice($val)
    {
        return rtrim(rtrim(strval($val), '0'), '.');
    }

    /**
     * 格式化数组中的价格字段
     */
    private function formatPriceFields(&$item)
    {
        foreach (self::$priceFields as $f) {
            if (isset($item[$f])) {
                $item[$f] = $this->formatPrice($item[$f]);
            }
        }
    }

    /**
     * 产品分类列表
     * @ApiMethod (GET)
     */
    public function categories()
    {
        $list = ProductCategory::where('status', 1)
            ->order('sort asc, id asc')
            ->field('id,name,name_en,sort')
            ->select();
        $this->success('', $list);
    }

    /**
     * 产品列表
     * @ApiMethod (GET)
     * @ApiParams (name="category_id", type="int", required=false, description="分类ID")
     */
    public function products()
    {
        $categoryId = $this->request->get('category_id/d', 0);
        $query = Product::where('status', 1);
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        $list = $query->order('sort asc, id asc')
            ->field('id,product_code,capital_key,name,name_en,category_id,currency,price_decimals,price,open_price,close_price,high_price,low_price,diff,diff_rate,buy_price,sell_price,icon,sort,is_recommend')
            ->select();
        $arr = [];
        foreach ($list as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $this->formatPriceFields($row);
            $arr[] = $row;
        }
        $this->success('', $arr);
    }

    /**
     * 产品详情
     * @ApiMethod (GET)
     * @ApiParams (name="id", type="int", required=true, description="产品ID")
     */
    public function detail()
    {
        $id = $this->request->get('id/d', 0);
        if (!$id) {
            $id = $this->request->get('product_id/d', 0);
        }
        if (!$id) {
            $this->error(__('Invalid parameters'));
        }
        $product = Product::get($id);
        if (!$product || $product->status != 1) {
            $this->error('产品不存在或已关闭');
        }
        // 获取交易时间配置
        $tradeTimes = ProductTradeTime::where('product_id', $id)
            ->order('time_order asc')
            ->field('deal_time_start,deal_time_end,time_order')
            ->select();
        $data = $product->toArray();
        $data['trade_times'] = $tradeTimes;
        // 手续费率（千分比值，如2表示千分之2，即0.2%）
        $feeRate = floatval(Config::get('site.trade_fee_rate') ?: 0);
        $data['trade_fee_rate'] = $feeRate;
        $data['trade_min_amount'] = floatval(Config::get('site.trade_min_amount') ?: 0);
        $this->formatPriceFields($data);
        $this->success('', $data);
    }

    /**
     * K线数据（实时倒推生成，不依赖数据库）
     * @ApiMethod (GET)
     * @ApiParams (name="product_id", type="int", required=true, description="产品ID")
     * @ApiParams (name="period", type="string", required=false, description="周期:1m/5m/30m/60m/1d")
     * @ApiParams (name="limit", type="int", required=false, description="数量")
     */
    public function kline()
    {
        $productId = $this->request->get('product_id/d');
        $period    = $this->request->get('period', '1m');
        $limit     = $this->request->get('limit/d', 200);
        if (!$productId) {
            $this->error(__('Invalid parameters'));
        }
        $periodMap = ['1m' => 60, '5m' => 300, '30m' => 1800, '60m' => 3600, '1d' => 86400];
        if (!isset($periodMap[$period])) {
            $this->error(__('Invalid parameters'));
        }
        $limit = min(max($limit, 1), 1000);

        $product = Product::get($productId);
        if (!$product || $product->status != 1) {
            $this->error('产品不存在');
        }

        $latestPrice = floatval($product->price);
        if ($latestPrice <= 0) {
            $this->success('', []);
            return;
        }

        $code     = $product->capital_key ?: ('p' . $productId);
        $decimals = intval($product->price_decimals) ?: 2;
        $timeSpan = $periodMap[$period];

        $arr = $this->generateBackwardKline($limit, $latestPrice, $code, $period, $timeSpan, $decimals);
        $this->success('', $arr);
    }

    /**
     * 倒推K线：从最新价格往过去生成历史数据（纯内存计算）
     */
    private function generateBackwardKline($rows, $latestPrice, $code, $interval, $timeSpan, $decimals)
    {
        // 用产品代码+周期+当前时间段做种子，同一时间段内结果一致
        $timeBase = $this->getTimeBase($interval);
        $seed     = crc32($code . $interval . $timeBase);
        mt_srand($seed);

        $baseVol = $this->getBaseVolatility($code);
        $vol     = $this->adjustVolByInterval($baseVol, $interval);

        // 从最新价格往历史倒推价格序列
        $prices       = [$latestPrice];
        $currentPrice = $latestPrice;
        for ($i = 1; $i <= $rows; $i++) {
            $change       = $this->seededNormal() * $vol;
            $prevPrice    = $currentPrice / (1 + $change);
            array_unshift($prices, $prevPrice);
            $currentPrice = $prevPrice;
        }

        // 重设种子生成K线细节
        mt_srand($seed + 1000);

        $list    = [];
        $baseVol2 = $this->getBaseVolume($interval);
        $now     = time();

        for ($i = 0; $i < $rows; $i++) {
            $open  = $prices[$i];
            $close = $prices[$i + 1];

            $spread = abs($close - $open);
            if ($spread == 0) {
                $spread = $open * $vol * 0.5;
            }

            $high = max($open, $close) + $spread * (mt_rand(0, 100) / 100);
            $low  = min($open, $close) - $spread * (mt_rand(0, 100) / 100);
            $volume = $baseVol2 + mt_rand(0, $baseVol2);

            $ts = $now - ($rows - $i) * $timeSpan;

            $list[] = [
                'open_price'  => $this->formatPrice(round($open, $decimals)),
                'close_price' => $this->formatPrice(round($close, $decimals)),
                'high_price'  => $this->formatPrice(round($high, $decimals)),
                'low_price'   => $this->formatPrice(round($low, $decimals)),
                'volume'      => $volume,
                'timestamp'   => $ts,
            ];
        }

        mt_srand();
        return $list;
    }

    /**
     * 时间基准（控制种子稳定性，粒度要比K线周期大很多）
     * 同一时间基准内，历史K线保持不变，只有最新一根随价格变化
     */
    private function getTimeBase($interval)
    {
        switch ($interval) {
            case '1m':  return date('YmdH');          // 每小时变一次种子
            case '5m':  return date('YmdH');           // 每小时变一次
            case '30m': return date('Ymd');             // 每天变一次
            case '60m': return date('Ymd');             // 每天变一次
            case '1d':  return date('YW');              // 每周变一次
            default:    return date('YmdH');
        }
    }

    /**
     * 产品基础波动率
     */
    private function getBaseVolatility($code)
    {
        $code = strtolower($code);
        if (strpos($code, 'btc') !== false || strpos($code, 'eth') !== false) return 0.01;
        if (strpos($code, 'usd') !== false || strpos($code, 'eur') !== false || strpos($code, 'fx_') !== false) return 0.002;
        if (strpos($code, 'gold') !== false || strpos($code, 'xau') !== false || strpos($code, 'xag') !== false) return 0.005;
        if (strpos($code, 'oil') !== false) return 0.008;
        return 0.006;
    }

    /**
     * 根据周期调整波动率
     */
    private function adjustVolByInterval($baseVol, $interval)
    {
        switch ($interval) {
            case '1m':  return $baseVol * 0.3;
            case '5m':  return $baseVol * 0.5;
            case '30m': return $baseVol * 1.0;
            case '60m': return $baseVol * 1.5;
            case '1d':  return $baseVol * 3.0;
            default:    return $baseVol;
        }
    }

    /**
     * 基础成交量
     */
    private function getBaseVolume($interval)
    {
        switch ($interval) {
            case '1m':  return 100;
            case '5m':  return 300;
            case '30m': return 1500;
            case '60m': return 3000;
            case '1d':  return 10000;
            default:    return 500;
        }
    }

    /**
     * 正态分布随机数
     */
    private function seededNormal()
    {
        $u = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $v = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    /**
     * 行情快照（所有开启产品的最新行情）
     * @ApiMethod (GET)
     */
    public function snapshot()
    {
        $list = Product::where('status', 1)
            ->order('sort asc, id asc')
            ->field('id,product_code,capital_key,name,name_en,category_id,price,open_price,close_price,high_price,low_price,diff,diff_rate,buy_price,sell_price,buy_volume,sell_volume,price_updatetime')
            ->select();
        $arr = [];
        foreach ($list as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $this->formatPriceFields($row);
            $arr[] = $row;
        }
        $this->success('', $arr);
    }
}
