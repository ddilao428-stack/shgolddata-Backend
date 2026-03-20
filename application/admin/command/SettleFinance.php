<?php

namespace app\admin\command;

use app\common\model\FinanceOrder;
use app\common\model\UserAccount;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Log;

/**
 * 理财锁仓每日收益发放
 * 用法: php think settle_finance
 * 建议每天执行一次（如每天凌晨1点）
 */
class SettleFinance extends Command
{
    protected function configure()
    {
        $this->setName('settle_finance')->setDescription('Daily finance profit distribution');
    }

    protected function execute(Input $input, Output $output)
    {
        $today = date('Y-m-d');
        // 查找所有锁仓中的订单
        $orders = FinanceOrder::where('status', 0)
            ->where('start_time', '<=', time())
            ->limit(500)
            ->select();

        if (!$orders || count($orders) == 0) {
            $output->info('No active finance orders');
            return;
        }

        $distributed = 0;
        $settled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $result = $this->processOrder($order, $today);
                if ($result === 'distributed') {
                    $distributed++;
                } elseif ($result === 'settled') {
                    $settled++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("Finance profit order {$order->order_no} failed: " . $e->getMessage());
            }
        }

        $output->info("Daily profit: distributed={$distributed}, settled={$settled}, skipped={$skipped}, failed={$failed}");
    }

    /**
     * 处理单个订单——补发所有漏发的天数
     */
    protected function processOrder($order, $today)
    {
        // 从start_time到今天，应该发放多少天
        $startDate = date('Y-m-d', $order->start_time);
        $shouldDays = (strtotime($today) - strtotime($startDate)) / 86400 + 1;
        $shouldDays = min((int)$shouldDays, $order->lock_days);

        // 已发放天数
        $distributedDays = (int)Db::name('finance_profit_log')
            ->where('order_id', $order->id)
            ->count();

        // 没有需要补发的
        if ($distributedDays >= $shouldDays) {
            return 'skipped';
        }

        $dailyProfit = function_exists('bcmul')
            ? bcmul($order->amount, $order->daily_rate, 2)
            : round($order->amount * $order->daily_rate, 2);

        $totalAdded = '0.00';
        $lastDayIndex = $distributedDays;

        Db::startTrans();
        try {
            // 逐天补发
            for ($i = $distributedDays + 1; $i <= $shouldDays; $i++) {
                $profitDate = date('Y-m-d', strtotime($startDate . ' +' . ($i - 1) . ' day'));

                // 防重复
                $exists = Db::name('finance_profit_log')
                    ->where('order_id', $order->id)
                    ->where('profit_date', $profitDate)
                    ->find();
                if ($exists) {
                    continue;
                }

                Db::name('finance_profit_log')->insert([
                    'order_id'   => $order->id,
                    'user_id'    => $order->user_id,
                    'product_id' => $order->product_id,
                    'order_no'   => $order->order_no,
                    'amount'     => $order->amount,
                    'daily_rate' => $order->daily_rate,
                    'profit'     => $dailyProfit,
                    'day_index'  => $i,
                    'profit_date'=> $profitDate,
                    'createtime' => time(),
                ]);

                // 发放收益到用户余额
                $ret = UserAccount::changeBalance(
                    $order->user_id,
                    $dailyProfit,
                    7,
                    $order->order_no,
                    "理财第{$i}天收益"
                );
                if (!$ret) {
                    throw new \Exception("Distribute day {$i} profit failed");
                }

                $totalAdded = function_exists('bcadd')
                    ? bcadd($totalAdded, $dailyProfit, 2)
                    : $totalAdded + $dailyProfit;
                $lastDayIndex = $i;
            }

            // 累加订单总收益
            if ($totalAdded > 0) {
                $newTotalProfit = function_exists('bcadd')
                    ? bcadd($order->total_profit, $totalAdded, 2)
                    : $order->total_profit + $totalAdded;
                $order->save(['total_profit' => $newTotalProfit]);
            }

            // 最后一天到了：返还本金，标记已到期
            if ($lastDayIndex >= $order->lock_days) {
                $retPrincipal = UserAccount::changeBalance(
                    $order->user_id,
                    $order->amount,
                    8,
                    $order->order_no,
                    '理财锁仓到期返还本金'
                );
                if (!$retPrincipal) {
                    throw new \Exception('Return principal failed');
                }
                $order->save(['status' => 1]);
                Db::commit();
                return 'settled';
            }

            Db::commit();
            return 'distributed';
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
