<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;

/**
 * 用户银行卡管理
 *
 * @icon fa fa-credit-card
 */
class Userbank extends Backend
{

    /**
     * Userbank模型对象
     * @var \app\admin\model\Userbank
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Userbank;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("typeList", ['bank' => '银行卡', 'usdt' => 'USDT']);
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            return parent::edit($ids);
        }
        // 查询用户账号
        $user = Db::name('user')->where('id', $row['user_id'])->field('username')->find();
        $this->view->assign('userAccount', $user ? $user['username'] : '-');
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $userIds = array_unique(array_column($list->items(), 'user_id'));
            $userMap = [];
            if ($userIds) {
                $userMap = Db::name('user')->whereIn('id', $userIds)->column('nickname,username', 'id');
            }
            foreach ($list as $row) {
                $u = isset($userMap[$row['user_id']]) ? $userMap[$row['user_id']] : [];
                $row['user_nickname'] = $u ? $u['nickname'] : '-';
                $row['user_account'] = $u ? $u['username'] : '-';
            }
            return json(["total" => $list->total(), "rows" => $list->items()]);
        }
        return $this->view->fetch();
    }
}
