<?php

namespace app\common\model;

use think\Model;

/**
 * 用户收藏(自选)模型
 */
class UserFavorite extends Model
{

    // 表名
    protected $name = 'user_favorite';
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
     * 关联产品
     */
    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'id');
    }
}
