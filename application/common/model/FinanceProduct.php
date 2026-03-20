<?php

namespace app\common\model;

use think\Model;

/**
 * 理财产品模型
 */
class FinanceProduct extends Model
{

    // 表名
    protected $name = 'finance_product';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 关联锁仓记录
     */
    public function orders()
    {
        return $this->hasMany('FinanceOrder', 'product_id', 'id');
    }
}
