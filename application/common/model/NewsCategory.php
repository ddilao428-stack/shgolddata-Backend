<?php

namespace app\common\model;

use think\Model;

/**
 * 新闻分类模型
 */
class NewsCategory extends Model
{

    // 表名
    protected $name = 'news_category';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = '';

    /**
     * 关联新闻
     */
    public function news()
    {
        return $this->hasMany('News', 'category_id', 'id');
    }
}
