<?php

namespace app\common\model;

use think\Model;

/**
 * 实名认证模型
 */
class UserVerify extends Model
{

    // 表名
    protected $name = 'user_verify';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id');
    }
}
