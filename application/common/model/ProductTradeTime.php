<?php

namespace app\common\model;

use think\Model;

/**
 * 产品交易时间模型
 */
class ProductTradeTime extends Model
{

    // 表名
    protected $name = 'product_deal_time';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 关联产品
     */
    public function product()
    {
        return $this->belongsTo('Product', 'product_id', 'id');
    }
}
