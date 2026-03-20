<?php

namespace app\admin\model;

use think\Model;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'direction_text',
        'status_text',
        'result_text',
        'open_time_text',
        'close_time_text',
        'settle_time_text'
    ];
    

    
    public function getDirectionList()
    {
        return ['0' => __('Direction 0'), '1' => __('Direction 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getResultList()
    {
        return ['0' => __('Result 0'), '1' => __('Result 1'), '2' => __('Result 2')];
    }


    public function getDirectionTextAttr($value, $data)
    {
        $value = $value ?: ($data['direction'] ?? '');
        $list = $this->getDirectionList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getResultTextAttr($value, $data)
    {
        $value = $value ?: ($data['result'] ?? '');
        $list = $this->getResultList();
        return $list[$value] ?? '';
    }


    public function getOpenTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['open_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCloseTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['close_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getSettleTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['settle_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setOpenTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCloseTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setSettleTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function product()
    {
        return $this->belongsTo('app\common\model\Product', 'product_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
