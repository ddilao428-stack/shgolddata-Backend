<?php

namespace app\common\model;

use think\Model;

/**
 * 订单模型
 */
class Order extends Model
{

    // 表名
    protected $name = 'order';
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

    /**
     * 生成唯一订单号
     * @return string
     */
    public static function generateOrderNo()
    {
        return date('YmdHis') . substr(microtime(), 2, 6) . mt_rand(1000, 9999);
    }
}
