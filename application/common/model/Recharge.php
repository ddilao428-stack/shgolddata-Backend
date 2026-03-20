<?php

namespace app\common\model;

use think\Model;

/**
 * 充值记录模型
 */
class Recharge extends Model
{

    // 表名
    protected $name = 'recharge';
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
}
