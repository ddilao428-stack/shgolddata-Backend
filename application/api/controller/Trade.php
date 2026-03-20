<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\TradeTime;
use app\common\model\Order;
use app\common\model\Product;
use app\common\model\User;
use app\common\model\UserAccount;
use think\Config;

/**
 * 交易接口
 */
class Trade extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 时间盘下单
     * @ApiMethod (POST)
     * @ApiParams (name="product_id", type="int", required=true, description="产品ID")
     * @ApiParams (name="direction", type="int", required=true, description="方向:0=买涨,1=买跌")
     * @ApiParams (name="amount", type="float", required=true, description="交易金额")
     * @ApiParams (name="duration", type="int", required=true, description="结算时间(秒)")
     */
    public function order()
    {
        $productId = $this->request->post('product_id/d');
        $direction = $this->request->post('direction/d');
        $amount = $this->request->post('amount/f');
        $duration = $this->request->post('duration/d');
        if (!$productId || !in_array($direction, [0, 1]) || $amount <= 0 || $duration <= 0) {
            $this->error(__('Invalid parameters'));
        }
        // 验证产品
        $product = Product::get($productId);
        if (!$product || $product->status != 1) {
            $this->error(__('Product not found or closed'));
        }
        // 验证交易时间
        if (!TradeTime::isTrading($productId)) {
            $this->error(__('Not in trading hours'));
        }
        // 验证时间盘配置，匹配 duration 对应的配置
        $timeConfig = $product->time_config;
        $matchedConfig = null;
        foreach ($timeConfig as $config) {
            if (isset($config['minute']) && $config['minute'] == $duration) {
                $matchedConfig = $config;
                break;
            }
        }
        if (!$matchedConfig) {
            $this->error(__('Invalid settlement time'));
        }
        // 验证最低金额（系统全局配置）
        $globalMinAmount = floatval(Config::get('site.trade_min_amount') ?: 0);
        if ($globalMinAmount > 0 && $amount < $globalMinAmount) {
            $this->error(__('Trade amount cannot be less than %s', $globalMinAmount));
        }
        // 验证时间盘配置中的最低金额
        $minAmount = $matchedConfig['min_amount'] ?? 0;
        if ($minAmount > 0 && $amount < $minAmount) {
            $this->error(__('Trade amount cannot be less than %s', $minAmount));
        }
        // 验证余额
        $userId = $this->auth->id;
        $user = User::get($userId);
        // 计算手续费：从系统配置读取百分比，如0.2表示0.2%
        $feeRate = floatval(Config::get('site.trade_fee_rate') ?: 0) / 100;
        $fee = round($amount * $feeRate, 2);
        $totalDeduct = $amount + $fee;
        if (!$user || $user->money < $totalDeduct) {
            $this->error(__('Insufficient balance'));
        }
        // 获取当前价格作为入仓价
        $openPrice = $product->price;
        if ($openPrice <= 0) {
            $this->error(__('Product price error'));
        }
        $odds = $matchedConfig['odds'] ?? 0;
        $now = time();
        $settleTime = $now + $duration;
        $orderNo = Order::generateOrderNo();
        // 扣款（本金+手续费）
        $ret = UserAccount::changeBalance($userId, -$totalDeduct, 3, $orderNo, __('Order deduction (fee %s)', $fee));
        if (!$ret) {
            $this->error(__('Deduction failed, please retry'));
        }
        // 创建订单
        $order = Order::create([
            'order_no'     => $orderNo,
            'user_id'      => $userId,
            'product_id'   => $productId,
            'direction'    => $direction,
            'open_price'   => $openPrice,
            'close_price'  => 0,
            'trade_amount' => $amount,
            'fee'          => $fee,
            'profit'       => 0,
            'odds'         => $odds,
            'duration'     => $duration,
            'status'       => 0,
            'result'       => null,
            'open_time'    => $now,
            'close_time'   => 0,
            'settle_time'  => $settleTime,
        ]);
        $this->success(__('Order placed'), [
            'order_no'    => $orderNo,
            'open_price'  => $openPrice,
            'direction'   => $direction,
            'amount'      => $amount,
            'fee'         => $fee,
            'odds'        => $odds,
            'duration'    => $duration,
            'settle_time' => $settleTime,
        ]);
    }

    /**
     * 持仓订单列表
     * @ApiMethod (GET)
     */
    public function holding()
    {
        $userId = $this->auth->id;
        $list = Order::with(['product'])
            ->where('user_id', $userId)
            ->where('status', 0)
            ->order('open_time desc')
            ->select();
        // 计算浮动盈亏
        $result = [];
        foreach ($list as $order) {
            $item = $order->toArray();
            $item['product_name'] = $order->product ? $order->product->name : '';
            $item['product_name_en'] = $order->product ? $order->product->name_en : '';
            $currentPrice = $order->product ? $order->product->price : 0;
            if ($order->direction == 0) {
                $item['float_profit'] = $currentPrice > $order->open_price
                    ? $order->trade_amount * $order->odds / 100
                    : -$order->trade_amount;
            } else {
                $item['float_profit'] = $currentPrice < $order->open_price
                    ? $order->trade_amount * $order->odds / 100
                    : -$order->trade_amount;
            }
            $item['current_price'] = $currentPrice;
            $item['remaining_time'] = max(0, $order->settle_time - time());
            $result[] = $item;
        }
        $this->success('', $result);
    }

    /**
     * 按产品获取订单记录（持仓+已结算）
     * @ApiMethod (GET)
     * @ApiParams (name="product_id", type="int", required=true, description="产品ID")
     * @ApiParams (name="page", type="int", required=false, description="页码")
     * @ApiParams (name="limit", type="int", required=false, description="每页数量")
     */
    public function orders()
    {
        $userId = $this->auth->id;
        $productId = $this->request->get('product_id/d');
        if (!$productId) {
            $this->error(__('Invalid parameters'));
        }
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);

        $where = ['user_id' => $userId, 'product_id' => $productId];

        $total = Order::where($where)->count();
        $list = Order::with(['product'])
            ->where($where)
            ->order('open_time desc')
            ->page($page, $limit)
            ->select();

        $now = time();
        $result = [];
        foreach ($list as $order) {
            $item = $order->toArray();
            $item['product_name'] = $order->product ? $order->product->name : '';
            $item['product_name_en'] = $order->product ? $order->product->name_en : '';
            $currentPrice = $order->product ? $order->product->price : 0;
            $item['current_price'] = $currentPrice;
            if ($order->status == 0) {
                $item['remaining_time'] = max(0, $order->settle_time - $now);
                $item['total_duration'] = $order->duration;
            }
            $result[] = $item;
        }
        $this->success('', [
            'list'  => $result,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 历史订单列表
     * @ApiMethod (GET)
     * @ApiParams (name="page", type="int", required=false, description="页码")
     * @ApiParams (name="limit", type="int", required=false, description="每页数量")
     */
    public function history()
    {
        $userId = $this->auth->id;
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);
        $list = Order::with(['product'])
            ->where('user_id', $userId)
            ->where('status', 1)
            ->order('close_time desc')
            ->page($page, $limit)
            ->select();
        $total = Order::where('user_id', $userId)->where('status', 1)->count();
        $result = [];
        foreach ($list as $order) {
            $item = $order->toArray();
            $item['product_name'] = $order->product ? $order->product->name : '';
            $item['product_name_en'] = $order->product ? $order->product->name_en : '';
            $result[] = $item;
        }
        $this->success('', [
            'list'  => $result,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 订单详情
     * @ApiMethod (GET)
     * @ApiParams (name="order_no", type="string", required=true, description="订单编号")
     */
    public function detail()
    {
        $orderNo = $this->request->get('order_no');
        if (!$orderNo) {
            $this->error(__('Invalid parameters'));
        }
        $order = Order::with(['product'])
            ->where('order_no', $orderNo)
            ->where('user_id', $this->auth->id)
            ->find();
        if (!$order) {
            $this->error(__('Order not found'));
        }
        $this->success('', $order);
    }
}
