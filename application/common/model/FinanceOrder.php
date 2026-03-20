<?php

namespace app\common\model;

use think\Model;

/**
 * 理财锁仓记录模型
 */
class FinanceOrder extends Model
{

    // 表名
    protected $name = 'finance_order';
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
     * 关联理财产品
     */
    public function financeProduct()
    {
        return $this->belongsTo('FinanceProduct', 'product_id', 'id');
    }
}
