<?php

namespace app\admin\model\finance\profit;

use think\Model;

class Log extends Model
{
    // 表名
    protected $name = 'finance_profit_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function financeorder()
    {
        return $this->belongsTo('app\admin\model\Financeorder', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
