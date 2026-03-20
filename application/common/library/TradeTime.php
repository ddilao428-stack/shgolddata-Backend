<?php

namespace app\common\library;

use app\common\model\ProductTradeTime;

/**
 * 交易时间判断工具类
 */
class TradeTime
{
    /**
     * 判断指定产品当前是否在交易时间内
     *
     * @param int         $productId 产品ID
     * @param string|null $time      指定时间（H:i格式，如 "09:30"），默认当前时间
     * @return bool
     */
    public static function isTrading($productId, $time = null)
    {
        $tradeTimes = ProductTradeTime::where('product_id', $productId)
            ->order('time_order asc')
            ->select();
        if (!$tradeTimes || count($tradeTimes) == 0) {
            return false;
        }
        if ($time === null) {
            $time = date('H:i');
        }
        foreach ($tradeTimes as $item) {
            if (self::isInTimeRange($time, $item->deal_time_start, $item->deal_time_end)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断时间是否在指定时段内
     *
     * @param string $time      当前时间（H:i格式，如 "09:30"）
     * @param string $startTime 开始时间（H:i格式）
     * @param string $endTime   结束时间（H:i格式）
     * @return bool
     */
    public static function isInTimeRange($time, $startTime, $endTime)
    {
        $t = strtotime($time);
        $s = strtotime($startTime);
        $e = strtotime($endTime);
        return ($t >= $s && $t <= $e);
    }

    /**
     * 获取产品所有交易时段
     *
     * @param int $productId 产品ID
     * @return array
     */
    public static function getTradeTimeList($productId)
    {
        $tradeTimes = ProductTradeTime::where('product_id', $productId)
            ->order('time_order asc')
            ->field('deal_time_start,deal_time_end,time_order')
            ->select();
        $result = [];
        foreach ($tradeTimes as $item) {
            $result[] = [
                'start' => $item->deal_time_start,
                'end'   => $item->deal_time_end,
            ];
        }
        return $result;
    }
}
