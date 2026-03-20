<?php

namespace app\common\service;

use think\Db;

/**
 * 资金流水服务
 * 类型: 1=充值, 2=提现, 3=下单扣款, 4=结算返还, 5=管理员调整,
 *       6=锁仓扣款, 7=理财收益, 8=锁仓返还本金
 */
class MoneyService
{
    const TYPE_RECHARGE       = 1;
    const TYPE_WITHDRAW       = 2;
    const TYPE_ORDER          = 3;
    const TYPE_SETTLE         = 4;
    const TYPE_ADMIN          = 5;
    const TYPE_FINANCE_LOCK   = 6;  // 锁仓扣款
    const TYPE_FINANCE_PROFIT = 7;  // 理财每日收益
    const TYPE_FINANCE_RETURN = 8;  // 锁仓到期返还本金

    /**
     * 记录资金流水
     * @param int    $userId    用户ID
     * @param int    $type      类型
     * @param float  $money     变动金额（正数加，负数减）
     * @param float  $before    变动前余额
     * @param float  $after     变动后余额
     * @param string $memo      备注
     * @param string $relatedId 关联单号
     * @return int 插入的记录ID
     */
    public static function log($userId, $type, $money, $before, $after, $memo = '', $relatedId = '')
    {
        return Db::name('user_money_log')->insertGetId([
            'user_id'    => $userId,
            'type'       => $type,
            'money'      => $money,
            'before'     => $before,
            'after'      => $after,
            'memo'       => $memo,
            'related_id' => $relatedId,
            'createtime' => time(),
        ]);
    }
}
