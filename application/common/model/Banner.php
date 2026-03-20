<?php

namespace app\common\model;

use think\Model;

/**
 * 轮播图模型
 */
class Banner extends Model
{

    // 表名
    protected $name = 'banner';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
