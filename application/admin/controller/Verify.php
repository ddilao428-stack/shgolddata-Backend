<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\User;
use think\Db;

/**
 * 实名认证管理
 *
 * @icon fa fa-circle-o
 */
class Verify extends Backend
{

    /**
     * Verify模型对象
     * @var \app\admin\model\Verify
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Verify;
        $this->view->assign("idTypeList", $this->model->getIdTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 列表
     */
    public function index()
    {
        $this->relationSearch = true;
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                $row->user_username = $row->user ? $row->user->username : '-';
            }
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                try {
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        // 同步更新用户昵称
                        if (!empty($params['real_name'])) {
                            User::where('id', $row['user_id'])->update(['nickname' => $params['real_name']]);
                        }
                        // 同步更新用户认证状态
                        if (isset($params['status'])) {
                            $verifyMap = ['0' => 0, '1' => 1, '2' => 0];
                            $isVerified = $verifyMap[$params['status']] ?? 0;
                            User::where('id', $row['user_id'])->update(['is_verified' => $isVerified]);
                        }
                    }
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 审核通过
     */
    public function approve($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));
        if ($row['status'] != 0) $this->error('该记录不是待审核状态');

        Db::startTrans();
        try {
            $now = time();
            $row->save(['status' => 1, 'admin_id' => $this->auth->id, 'audit_time' => $now]);
            User::where('id', $row['user_id'])->update(['is_verified' => 1]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
        $this->success('审核通过');
    }

    /**
     * 审核拒绝
     */
    public function reject($ids = null)
    {
        $row = $this->model->with(['user'])->where('verify.id', $ids)->find();
        if (!$row) $this->error(__('No Results were found'));
        if ($row['status'] != 0) $this->error('该记录不是待审核状态');

        if ($this->request->isPost()) {
            $reason = $this->request->post('reject_reason', '');
            Db::startTrans();
            try {
                $now = time();
                $row->save(['status' => 2, 'admin_id' => $this->auth->id, 'audit_time' => $now, 'reject_reason' => $reason]);
                User::where('id', $row['user_id'])->update(['is_verified' => 0]);
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('操作失败：' . $e->getMessage());
            }
            $this->success('已拒绝');
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

}
