<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\service\MoneyService;
use think\Db;

/**
 * 充值记录管理
 *
 * @icon fa fa-circle-o
 */
class Recharge extends Backend
{

    /**
     * Recharge模型对象
     * @var \app\admin\model\Recharge
     */
    protected $model = null;

    protected $searchFields = 'id,order_no,user.username,user.nickname';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Recharge;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['user', 'admin'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                $row->user_username = $row->user ? $row->user->username : '-';
                $realName = '';
                if ($row->user) {
                    $verify = Db::name('user_verify')
                        ->where('user_id', $row->user_id)
                        ->where('status', 1)
                        ->value('real_name');
                    $realName = $verify ?: $row->user->nickname;
                }
                $row->user_realname = $realName ?: '-';
                $row->admin_username = $row->admin ? $row->admin->username : '-';
            }
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
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
            $amount = $row['amount'];
            $row->save(['status' => 1, 'admin_id' => $this->auth->id, 'pay_time' => $now]);

            $user = User::lock(true)->find($row['user_id']);
            $account = UserAccount::where('user_id', $row['user_id'])->find();
            if ($user) {
                $before = $user->money;
                $newMoney = function_exists('bcadd') ? bcadd($before, $amount, 2) : $before + $amount;
                $user->save(['money' => $newMoney]);
                if ($account) {
                    $account->save(['total_recharge' => $account['total_recharge'] + $amount]);
                }
                MoneyService::log(
                    $row['user_id'],
                    MoneyService::TYPE_RECHARGE,
                    $amount,
                    $before,
                    $newMoney,
                    '充值到账',
                    $row['order_no']
                );
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
        $this->success('审核通过，已到账');
    }

    /**
     * 审核拒绝
     */
    public function reject($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));
        if ($row['status'] != 0) $this->error('该记录不是待审核状态');

        if ($this->request->isPost()) {
            $remark = $this->request->post('row/a')['remark'] ?? '';
            $row->save(['status' => 2, 'admin_id' => $this->auth->id, 'remark' => $remark]);
            $this->success('已拒绝');
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

}
