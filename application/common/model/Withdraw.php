<?php

namespace app\common\model;

use think\Model;

/**
 * 提现记录模型
 */
class Withdraw extends Model
{

    // 表名
    protected $name = 'withdraw';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id');
    }

    /**
     * 关联银行卡
     */
    public function bank()
    {
        return $this->belongsTo('UserBank', 'bank_id', 'id');
    }
}
