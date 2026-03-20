<?php

namespace workerman;

use GatewayWorker\Lib\Gateway;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;
use Workerman\MySQL\Connection as DbConnection;

/**
 * SGE 行情推送事件处理
 */
class Events
{
    /** @var DbConnection */
    protected static $db = null;

    /**
     * BusinessWorker 启动时触发
     */
    public static function onWorkerStart($businessWorker)
    {
        // Windows 下 $businessWorker->id 始终为 0（单进程）
        if ($businessWorker->id === 0) {
            self::initDb();
            // 连接外部行情数据源
            self::connectExternalSource();
            // 启动随机间隔推送（一次性，后续由 pushRandomData 自身递归注册）
            Timer::add(mt_rand(2, 5), [__CLASS__, 'pushRandomData'], [], false);
            // 定时更新加密货币价格（每1小时从 CoinGecko 获取基准价格）
            Timer::add(3600, [__CLASS__, 'updateCryptoPrices']);
            // 定时推送加密货币价格（每2秒）
            Timer::add(2, [__CLASS__, 'pushCryptoPrices']);
            echo "SGE 行情推送服务已启动\n";
        }
    }

    /**
     * 初始化数据库连接
     */
    protected static function initDb()
    {
        $envFile = dirname(dirname(__DIR__)) . '/.env';
        $config = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // 跳过注释和 section 头
                if (empty($line) || $line[0] === '#' || $line[0] === ';' || $line[0] === '[') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $val) = explode('=', $line, 2);
                    $config[strtolower(trim($key))] = trim($val);
                }
            }
        }
        // 兼容两种格式: DATABASE_HOSTNAME 或 hostname
        $host   = $config['database_hostname'] ?? $config['hostname'] ?? '127.0.0.1';
        $port   = $config['database_hostport'] ?? $config['hostport'] ?? '3306';
        $user   = $config['database_username'] ?? $config['username'] ?? 'root';
        $pass   = $config['database_password'] ?? $config['password'] ?? '';
        $dbname = $config['database_database'] ?? $config['database'] ?? '';
        self::$db = new DbConnection($host, $port, $user, $pass, $dbname);
    }

    // ==================== 外部数据源 ====================

    /**
     * 连接外部行情数据源 ws://39.107.99.235:8889
     */
    protected static function connectExternalSource()
    {
        if (!self::$db) {
            return;
        }
        $products = self::$db->select('capital_key')
            ->from('fa_product')
            ->where("data_source != 'CoinGecko' AND status = 1 AND capital_key != ''")
            ->query();
        if (empty($products)) {
            echo "无外部数据源产品\n";
            return;
        }
        $keys = [];
        foreach ($products as $p) {
            $keys[] = $p['capital_key'];
        }
        $dataId = implode(',', $keys);

        $conn = new AsyncTcpConnection('ws://39.107.99.235:8889');

        $conn->onConnect = function ($connection) use ($dataId) {
            $payload = json_encode(['event' => 'REG', 'Key' => $dataId]);
            $connection->send($payload);
            echo "已连接外部数据源，订阅: {$dataId}\n";
        };

        $conn->onMessage = function ($connection, $msg) {
            self::handleExternalMessage($msg);
        };

        $conn->onClose = function ($connection) {
            echo "外部数据源断开，1秒后重连...\n";
            $connection->reConnect(1);
        };

        $conn->onError = function ($connection, $code, $msg) {
            echo "外部数据源错误: {$code} {$msg}\n";
        };

        $conn->connect();
    }

    /**
     * 处理外部数据源消息
     */
    protected static function handleExternalMessage($msg)
    {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['body'])) {
            return;
        }
        $body = $data['body'];
        $code = $body['StockCode'] ?? '';
        if (empty($code)) {
            return;
        }
        $originalPrice = floatval($body['Price'] ?? 0);
        if ($originalPrice <= 0) {
            return;
        }
        $lastClose = floatval($body['LastClose'] ?? $originalPrice);
        if ($lastClose <= 0 || abs($lastClose - $originalPrice) > $originalPrice * 0.5) {
            $lastClose = $originalPrice;
        }

        // 添加微小随机波动
        $volatility  = self::getVolatility($code);
        $priceChange = (mt_rand(-100, 100) / 10000) * $volatility;
        $newPrice    = $originalPrice * (1 + $priceChange);

        // 计算涨跌
        $diff     = $newPrice - $lastClose;
        $diffRate = ($diff / $lastClose) * 100;

        // 限制涨跌幅 ±10%
        if ($diffRate > 10) {
            $newPrice = $lastClose * 1.1;
            $diff     = $lastClose * 0.1;
            $diffRate = 10;
        } elseif ($diffRate < -10) {
            $newPrice = $lastClose * 0.9;
            $diff     = $lastClose * -0.1;
            $diffRate = -10;
        }

        $decimals  = self::getPriceDecimals($code);
        $buyPrice  = round($newPrice * 0.9999, $decimals);
        $sellPrice = round($newPrice * 1.0001, $decimals);
        $now       = time();

        $updateData = [
            'price'            => round($newPrice, $decimals),
            'open_price'       => $body['Open'] ?? 0,
            'close_price'      => $lastClose,
            'high_price'       => max($body['High'] ?? $newPrice, $newPrice),
            'low_price'        => min($body['Low'] ?? $newPrice, $newPrice),
            'diff'             => round($diff, $decimals),
            'diff_rate'        => round($diffRate, 2),
            'buy_price'        => $buyPrice,
            'sell_price'       => $sellPrice,
            'buy_volume'       => mt_rand(50, 300),
            'sell_volume'      => mt_rand(50, 300),
            'price_updatetime' => $now,
        ];

        if (self::$db) {
            self::$db->update('fa_product')->cols($updateData)
                ->where("capital_key = '{$code}'")->query();
        }

        // 推送给订阅该产品的客户端
        $pushData = [
            'capital_key' => $code,
            'Price'       => self::formatPrice($updateData['price']),
            'Open'        => self::formatPrice($updateData['open_price']),
            'Close'       => self::formatPrice($updateData['close_price']),
            'High'        => self::formatPrice($updateData['high_price']),
            'Low'         => self::formatPrice($updateData['low_price']),
            'Diff'        => self::formatPrice($updateData['diff']),
            'DiffRate'    => $updateData['diff_rate'],
            'bp'          => self::formatPrice($buyPrice),
            'sp'          => self::formatPrice($sellPrice),
            'edit_time'   => date('Y-m-d H:i:s', $now),
            'TotalVol'    => mt_rand(1000, 10000),
        ];
        Gateway::sendToGroup($code, json_encode($pushData));
    }

    // ==================== 随机波动（非外部数据源产品） ====================

    /**
     * 推送随机波动数据（外部数据源超过30秒未更新的产品）
     */
    public static function pushRandomData()
    {
        if (!self::$db) {
            return;
        }
        $products = self::$db->select('id,capital_key,price,open_price,close_price,high_price,low_price,data_source,price_decimals')
            ->from('fa_product')
            ->where("status = 1 AND capital_key != '' AND data_source != 'CoinGecko'")
            ->query();
        if (empty($products)) {
            return;
        }
        foreach ($products as $product) {
            $code         = $product['capital_key'];
            $currentPrice = floatval($product['price']);
            if ($currentPrice <= 0) {
                continue;
            }
            // 40% 概率推送
            if (mt_rand(1, 100) > 40) {
                continue;
            }

            $volatility  = self::getVolatility($code);
            $priceChange = (mt_rand(-100, 100) / 10000) * $volatility;
            $newPrice    = $currentPrice * (1 + $priceChange);
            $lastClose   = floatval($product['close_price']);
            if ($lastClose <= 0) {
                $lastClose = $currentPrice;
            }

            $diff     = $newPrice - $lastClose;
            $diffRate = ($diff / $lastClose) * 100;

            if ($diffRate > 10) {
                $newPrice = $lastClose * 1.1;
                $diff     = $lastClose * 0.1;
                $diffRate = 10;
            } elseif ($diffRate < -10) {
                $newPrice = $lastClose * 0.9;
                $diff     = $lastClose * -0.1;
                $diffRate = -10;
            }

            $decimals  = intval($product['price_decimals']) ?: 2;
            $buyPrice  = round($newPrice * 0.9999, $decimals);
            $sellPrice = round($newPrice * 1.0001, $decimals);
            $now       = time();

            $updateData = [
                'price'            => round($newPrice, $decimals),
                'high_price'       => max($product['high_price'], $newPrice),
                'low_price'        => min($product['low_price'], $newPrice),
                'diff'             => round($diff, $decimals),
                'diff_rate'        => round($diffRate, 2),
                'buy_price'        => $buyPrice,
                'sell_price'       => $sellPrice,
                'buy_volume'       => mt_rand(50, 300),
                'sell_volume'      => mt_rand(50, 300),
                'price_updatetime' => $now,
            ];
            self::$db->update('fa_product')->cols($updateData)
                ->where("capital_key = '{$code}'")->query();

            $pushData = [
                'capital_key' => $code,
                'Price'       => self::formatPrice($updateData['price']),
                'Open'        => self::formatPrice($product['open_price']),
                'Close'       => self::formatPrice($lastClose),
                'High'        => self::formatPrice($updateData['high_price']),
                'Low'         => self::formatPrice($updateData['low_price']),
                'Diff'        => self::formatPrice($updateData['diff']),
                'DiffRate'    => $updateData['diff_rate'],
                'bp'          => self::formatPrice($buyPrice),
                'sp'          => self::formatPrice($sellPrice),
                'edit_time'   => date('Y-m-d H:i:s', $now),
                'TotalVol'    => mt_rand(1000, 10000),
            ];
            Gateway::sendToGroup($code, json_encode($pushData));
        }

        // 设置下次推送时间（2-5秒随机）
        Timer::add(mt_rand(2, 5), [__CLASS__, 'pushRandomData'], [], false);
    }

    // ==================== CoinGecko 加密货币 ====================

    protected static $cryptoApiKey = 'CG-34MbYoFrFzJytyCEQK1R3eh3';
    protected static $cryptoBaseUrl = 'https://api.coingecko.com/api/v3';

    /**
     * 从 CoinGecko 获取加密货币价格并更新数据库
     */
    public static function updateCryptoPrices()
    {
        if (!self::$db) {
            return;
        }
        $coins = self::$db->select('id,capital_key,price,price_decimals')
            ->from('fa_product')
            ->where("data_source = 'CoinGecko' AND status = 1 AND capital_key != ''")
            ->query();
        if (empty($coins)) {
            return;
        }

        $coinIds = [];
        foreach ($coins as $coin) {
            $coinIds[] = $coin['capital_key'];
        }
        $coinIdsStr = implode(',', $coinIds);

        $url = self::$cryptoBaseUrl . '/simple/price?ids=' . $coinIdsStr
             . '&vs_currencies=cny&include_24hr_change=true&include_24hr_vol=true'
             . '&x_cg_demo_api_key=' . self::$cryptoApiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || empty($response)) {
            echo "CoinGecko API 请求失败: HTTP {$httpCode}\n";
            return;
        }

        $prices = json_decode($response, true);
        if (empty($prices)) {
            return;
        }

        $now = time();
        foreach ($coins as $coin) {
            $coinId = $coin['capital_key'];
            if (!isset($prices[$coinId]['cny'])) {
                continue;
            }

            $price     = floatval($prices[$coinId]['cny']);
            $change24h = isset($prices[$coinId]['cny_24h_change']) ? floatval($prices[$coinId]['cny_24h_change']) : 0;

            // 添加小幅随机波动
            $randomChange = (mt_rand(-10, 10) / 10000);
            $price        = $price * (1 + $randomChange);

            $diff          = $price * ($change24h / 100);
            $yesterdayClose = $price - $diff;
            if ($yesterdayClose <= 0) {
                $yesterdayClose = $price;
            }

            $high24h = $price * (1 + abs($change24h) / 200);
            $low24h  = $price * (1 - abs($change24h) / 200);

            $decimals  = intval($coin['price_decimals']) ?: 2;
            $buyPrice  = round($price * 0.9999, $decimals);
            $sellPrice = round($price * 1.0001, $decimals);

            $updateData = [
                'price'            => round($price, $decimals),
                'open_price'       => round($yesterdayClose, $decimals),
                'close_price'      => round($yesterdayClose, $decimals),
                'high_price'       => round($high24h, $decimals),
                'low_price'        => round($low24h, $decimals),
                'diff'             => round($diff, $decimals),
                'diff_rate'        => round($change24h, 2),
                'buy_price'        => $buyPrice,
                'sell_price'       => $sellPrice,
                'buy_volume'       => mt_rand(100, 500),
                'sell_volume'      => mt_rand(100, 500),
                'price_updatetime' => $now,
            ];
            self::$db->update('fa_product')->cols($updateData)
                ->where("id = {$coin['id']}")->query();
        }
        echo date('H:i:s') . " CoinGecko 价格已更新\n";
    }

    /**
     * 推送加密货币实时价格（随机波动 + 写回数据库）
     */
    public static function pushCryptoPrices()
    {
        if (!self::$db) {
            return;
        }
        $cryptos = self::$db->select('id,capital_key,price,open_price,close_price,high_price,low_price,price_decimals')
            ->from('fa_product')
            ->where("data_source = 'CoinGecko' AND status = 1 AND capital_key != ''")
            ->query();
        if (empty($cryptos)) {
            return;
        }

        $now = time();
        foreach ($cryptos as $crypto) {
            $code         = $crypto['capital_key'];
            $currentPrice = floatval($crypto['price']);
            if ($currentPrice <= 0) {
                continue;
            }
            // 40% 概率推送
            if (mt_rand(1, 100) > 40) {
                continue;
            }

            $volatility  = self::getVolatility($code);
            $priceChange = (mt_rand(-100, 100) / 10000) * $volatility;
            $newPrice    = $currentPrice * (1 + $priceChange);
            $lastClose   = floatval($crypto['close_price']);
            if ($lastClose <= 0) {
                $lastClose = $currentPrice;
            }

            $diff     = $newPrice - $lastClose;
            $diffRate = ($diff / $lastClose) * 100;

            if ($diffRate > 10) {
                $newPrice = $lastClose * 1.1;
                $diff     = $lastClose * 0.1;
                $diffRate = 10;
            } elseif ($diffRate < -10) {
                $newPrice = $lastClose * 0.9;
                $diff     = $lastClose * -0.1;
                $diffRate = -10;
            }

            $decimals  = intval($crypto['price_decimals']) ?: 2;
            $buyPrice  = round($newPrice * 0.9999, $decimals);
            $sellPrice = round($newPrice * 1.0001, $decimals);

            $updateData = [
                'price'            => round($newPrice, $decimals),
                'high_price'       => max($crypto['high_price'], $newPrice),
                'low_price'        => min($crypto['low_price'], $newPrice),
                'diff'             => round($diff, $decimals),
                'diff_rate'        => round($diffRate, 2),
                'buy_price'        => $buyPrice,
                'sell_price'       => $sellPrice,
                'buy_volume'       => mt_rand(50, 300),
                'sell_volume'      => mt_rand(50, 300),
                'price_updatetime' => $now,
            ];
            self::$db->update('fa_product')->cols($updateData)
                ->where("id = {$crypto['id']}")->query();

            $pushData = [
                'capital_key' => $code,
                'Price'       => self::formatPrice($updateData['price']),
                'Open'        => self::formatPrice($crypto['open_price']),
                'Close'       => self::formatPrice($lastClose),
                'High'        => self::formatPrice($updateData['high_price']),
                'Low'         => self::formatPrice($updateData['low_price']),
                'Diff'        => self::formatPrice($updateData['diff']),
                'DiffRate'    => $updateData['diff_rate'],
                'bp'          => self::formatPrice($buyPrice),
                'sp'          => self::formatPrice($sellPrice),
                'edit_time'   => date('Y-m-d H:i:s', $now),
                'TotalVol'    => mt_rand(1000, 10000),
            ];
            Gateway::sendToGroup($code, json_encode($pushData));
        }
    }

    // ==================== 客户端事件 ====================

    /**
     * 客户端连接时触发
     */
    public static function onConnect($client_id)
    {
        Gateway::sendToClient($client_id, json_encode([
            'type'      => 'init',
            'client_id' => $client_id,
        ]));
    }

    /**
     * 客户端发来消息时触发
     */
    public static function onMessage($client_id, $message)
    {
        $data = json_decode($message, true);
        if (!$data || !isset($data['type'])) {
            return;
        }
        switch ($data['type']) {
            case 'subscribe':
                $key = $data['capital_key'] ?? ($data['group'] ?? '');
                if (!empty($key)) {
                    Gateway::joinGroup($client_id, $key);
                }
                break;
            case 'unsubscribe':
                $key = $data['capital_key'] ?? ($data['group'] ?? '');
                if (!empty($key)) {
                    Gateway::leaveGroup($client_id, $key);
                }
                break;
            case 'pong':
                break;
        }
    }

    /**
     * 客户端断开连接时触发
     */
    public static function onClose($client_id)
    {
    }

    // ==================== 工具方法 ====================

    /**
     * 格式化价格：去掉尾部多余的0（3.560000 → 3.56）
     */
    protected static function formatPrice($val)
    {
        return rtrim(rtrim(strval($val), '0'), '.');
    }

    /**
     * 获取产品波动率系数
     */
    protected static function getVolatility($code)
    {
        $code = strtolower($code);
        if (strpos($code, 'btc') !== false || strpos($code, 'eth') !== false) {
            return 5;
        }
        if (strpos($code, 'usd') !== false || strpos($code, 'eur') !== false || strpos($code, 'fx_') !== false) {
            return 0.5;
        }
        if (strpos($code, 'gold') !== false || strpos($code, 'xau') !== false || strpos($code, 'xag') !== false) {
            return 2;
        }
        return 2.5;
    }

    /**
     * 根据产品代码确定价格小数位数
     */
    protected static function getPriceDecimals($code)
    {
        $code = strtolower($code);
        if (strpos($code, 'usd') !== false || strpos($code, 'eur') !== false || strpos($code, 'fx_') !== false) {
            return 5;
        }
        return 2;
    }
}
