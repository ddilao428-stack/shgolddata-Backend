<?php

namespace app\common\model;

use think\Model;

/**
 * 新闻资讯模型
 */
class News extends Model
{

    // 表名
    protected $name = 'news';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo('NewsCategory', 'category_id', 'id');
    }
}
