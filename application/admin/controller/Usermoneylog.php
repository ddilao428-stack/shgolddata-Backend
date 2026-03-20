<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * 资金流水
 *
 * @icon fa fa-circle-o
 */
class Usermoneylog extends Backend
{

    /**
     * User_money_log模型对象
     * @var \app\admin\model\User_money_log
     */
    protected $model = null;

    protected $searchFields = 'id,user_id,related_id,memo';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User_money_log;
        $this->view->assign("typeList", $this->model->getTypeList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            // 批量查询用户账号
            $userIds = array_unique(array_column($list->items(), 'user_id'));
            $userMap = [];
            if ($userIds) {
                $userMap = Db::name('user')->where('id', 'in', $userIds)->column('username', 'id');
            }
            foreach ($list as $row) {
                $row->user_username = isset($userMap[$row->user_id]) ? $userMap[$row->user_id] : '-';
            }
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }

}
