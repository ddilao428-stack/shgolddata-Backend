<?php

namespace app\common\model;

use think\Model;

/**
 * 产品分类模型
 */
class ProductCategory extends Model
{

    // 表名
    protected $name = 'product_category';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';

    /**
     * 关联产品
     */
    public function products()
    {
        return $this->hasMany('Product', 'category_id', 'id');
    }
}
