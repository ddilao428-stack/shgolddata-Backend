<?php

namespace app\common\model;

use think\Model;

/**
 * 产品模型
 */
class Product extends Model
{

    // 表名
    protected $name = 'product';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 获取时间盘配置
     */
    public function getTimeConfigAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * 设置时间盘配置
     */
    public function setTimeConfigAttr($value)
    {
        return is_array($value) ? json_encode($value) : $value;
    }

    /**
     * 关联产品分类
     */
    public function category()
    {
        return $this->belongsTo('ProductCategory', 'category_id', 'id');
    }

    /**
     * 关联交易时间
     */
    public function tradeTimes()
    {
        return $this->hasMany('ProductTradeTime', 'product_id', 'id')->order('time_order asc');
    }
}
