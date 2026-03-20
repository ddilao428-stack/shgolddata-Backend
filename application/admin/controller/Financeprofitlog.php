<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 理财每日收益记录
 *
 * @icon fa fa-line-chart
 */
class Financeprofitlog extends Backend
{
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Financeprofitlog;
    }

    public function index()
    {
        $this->relationSearch = true;
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            $total = $this->model
                ->with(['user'])
                ->where($where)
                ->count();
            $result = ['total' => $total, 'rows' => $list];
            return json($result);
        }
        return $this->view->fetch();
    }
}
