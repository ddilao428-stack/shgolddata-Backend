<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\service\MoneyService;
use think\Db;

/**
 * 提现记录管理
 *
 * @icon fa fa-circle-o
 */
class Withdraw extends Backend
{

    /**
     * Withdraw模型对象
     * @var \app\admin\model\Withdraw
     */
    protected $model = null;

    protected $searchFields = 'id,order_no,user.username,user.nickname';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Withdraw;
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
                ->with(['user', 'bank', 'admin'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                // 用户账号
                $row->user_username = $row->user ? $row->user->username : '-';
                // 实名姓名：查实名认证表，有则显示，否则显示昵称
                $realName = '';
                if ($row->user) {
                    $verify = Db::name('user_verify')
                        ->where('user_id', $row->user_id)
                        ->where('status', 1)
                        ->value('real_name');
                    $realName = $verify ?: $row->user->nickname;
                }
                $row->user_realname = $realName ?: '-';
                // 提现账户：银行卡号或USDT地址
                if ($row->pay_type == 'usdt') {
                    $row->withdraw_account = $row->bank ? $row->bank->wallet_address : '-';
                } else {
                    $row->withdraw_account = $row->bank ? $row->bank->card_no : '-';
                }
                // 管理员账号
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
            $row->save(['status' => 1, 'admin_id' => $this->auth->id, 'audit_time' => $now]);

            $user = User::lock(true)->find($row['user_id']);
            $account = UserAccount::where('user_id', $row['user_id'])->find();
            if ($user) {
                $totalDeduct = $row['amount'] + $row['fee'];
                if ($account) {
                    $account->save([
                        'frozen'         => ['dec', $totalDeduct],
                        'total_withdraw' => $account['total_withdraw'] + $row['amount'],
                    ]);
                }
                // 提现申请时已记录扣款流水，审核通过只减冻结，不重复记流水
            }

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
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));
        if ($row['status'] != 0) $this->error('该记录不是待审核状态');

        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $remark = $this->request->post('row/a')['remark'] ?? '';
                $row->save(['status' => 2, 'admin_id' => $this->auth->id, 'audit_time' => time(), 'remark' => $remark]);

                $user = User::lock(true)->find($row['user_id']);
                $account = UserAccount::where('user_id', $row['user_id'])->find();
                if ($user) {
                    $totalRefund = $row['amount'] + $row['fee'];
                    $before = $user->money;
                    $newMoney = function_exists('bcadd') ? bcadd($before, $totalRefund, 2) : $before + $totalRefund;
                    $user->save(['money' => $newMoney]);
                    if ($account) {
                        $account->save(['frozen' => ['dec', $totalRefund]]);
                    }
                    // 提现拒绝，退回冻结资金
                    MoneyService::log(
                        $row['user_id'],
                        MoneyService::TYPE_WITHDRAW,
                        $totalRefund,
                        $before,
                        $newMoney,
                        '提现审核拒绝，资金退回',
                        $row['order_no']
                    );
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                $this->error('操作失败：' . $e->getMessage());
            }
            $this->success('已拒绝，金额已退回');
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

}
