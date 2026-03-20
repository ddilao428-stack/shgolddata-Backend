<?php

namespace app\common\model;

use think\Model;

/**
 * 用户银行卡模型
 */
class UserBank extends Model
{

    // 表名
    protected $name = 'user_bank';
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
