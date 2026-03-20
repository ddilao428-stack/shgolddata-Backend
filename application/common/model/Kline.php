<?php

namespace app\common\model;

use think\Model;

/**
 * K线数据模型
 */
class Kline extends Model
{

    // 表名
    protected $name = 'kline';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    /**
     * 关联产品
     */
    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'id');
    }
}
