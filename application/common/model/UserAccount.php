<?php

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * 用户资金账户模型
 */
class UserAccount extends Model
{

    // 表名
    protected $name = 'user_account';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = '';
    protected $updateTime = 'updatetime';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id');
    }

    /**
     * 变更用户余额（带事务和行锁）
     * 余额统一使用 fa_user.money，本表仅维护统计字段
     * @param int    $userId    用户ID
     * @param float  $amount    变动金额（正为入，负为出）
     * @param int    $type      流水类型
     * @param string $relatedId 关联ID
     * @param string $note      备注
     * @return bool
     */
    public static function changeBalance($userId, $amount, $type, $relatedId = '', $note = '')
    {
        Db::startTrans();
        try {
            $user = User::lock(true)->find($userId);
            if (!$user) {
                Db::rollback();
                return false;
            }
            $before = $user->money;
            $after = function_exists('bcadd') ? bcadd($user->money, $amount, 2) : $user->money + $amount;
            if ($after < 0) {
                Db::rollback();
                return false;
            }
            // 更新 fa_user.money
            $user->save(['money' => $after]);
            // 更新 fa_user_account 统计字段
            $account = self::where('user_id', $userId)->find();
            if ($account) {
                if ($type == 1) {
                    $account->total_recharge = function_exists('bcadd') ? bcadd($account->total_recharge, $amount, 2) : $account->total_recharge + $amount;
                } elseif ($type == 2) {
                    $account->total_withdraw = function_exists('bcadd') ? bcadd($account->total_withdraw, abs($amount), 2) : $account->total_withdraw + abs($amount);
                } elseif ($type == 7 && $amount > 0) {
                    $account->total_profit = function_exists('bcadd') ? bcadd($account->total_profit, $amount, 2) : $account->total_profit + $amount;
                }
                $account->save();
            }
            // 写入资金流水
            \app\common\service\MoneyService::log($userId, $type, $amount, $before, $after, $note, $relatedId);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }
}
