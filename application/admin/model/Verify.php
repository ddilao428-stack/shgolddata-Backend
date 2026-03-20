<?php

namespace app\admin\model;

use think\Model;


class Verify extends Model
{

    

    

    // 表名
    protected $name = 'user_verify';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'id_type_text',
        'status_text',
        'audit_time_text'
    ];
    

    
    public function getIdTypeList()
    {
        return ['1' => __('Id_type 1'), '2' => __('Id_type 2'), '3' => __('Id_type 3'), '4' => __('Id_type 4')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getIdTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['id_type'] ?? '');
        $list = $this->getIdTypeList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getAuditTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['audit_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setAuditTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function user()
    {
        return $this->belongsTo('\\app\\common\\model\\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
