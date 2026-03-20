<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Order;
use app\common\model\Recharge;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\model\UserBank;
use app\common\model\Withdraw;
use think\Db;

/**
 * 资金接口
 */
class Account extends Api
{
    protected $noNeedLogin = ['aboutus'];
    protected $noNeedRight = '*';

    /**
     * 账户资金信息
     * @ApiMethod (GET)
     */
    public function info()
    {
        $userId = $this->auth->id;
        $user = User::get($userId);
        $account = UserAccount::where('user_id', $userId)->find();
        $data = [
            'balance'        => $user ? $user->money : '0.00',
            'frozen'         => $account ? $account->frozen : '0.00',
            'total_recharge' => $account ? $account->total_recharge : '0.00',
            'total_withdraw' => $account ? $account->total_withdraw : '0.00',
            'total_profit'   => $account ? $account->total_profit : '0.00',
            'total_loss'     => $account ? $account->total_loss : '0.00',
        ];
        $this->success('', $data);
    }

    /**
     * 资金流水
     * @ApiMethod (GET)
     * @ApiParams (name="type", type="int", required=false, description="类型筛选")
     * @ApiParams (name="page", type="int", required=false, description="页码")
     * @ApiParams (name="limit", type="int", required=false, description="每页数量")
     */
    public function flow()
    {
        $userId = $this->auth->id;
        $type = $this->request->get('type/d', 0);
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);

        $where = ['user_id' => $userId];
        if ($type > 0) {
            $where['type'] = $type;
        }
        $total = Db::name('user_money_log')->where($where)->count();
        $list = Db::name('user_money_log')->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        $this->success('', [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 充值配置（最小/最大金额）
     * @ApiMethod (GET)
     */
    public function rechargeconfig()
    {
        $site = config('site');
        $this->success('', [
            'recharge_min' => floatval($site['recharge_min'] ?? 100),
            'recharge_max' => floatval($site['recharge_max'] ?? 1000000),
        ]);
    }

    /**
     * 关于我们
     * @ApiMethod (GET)
     */
    public function aboutus()
    {
        $site = config('site');
        $this->success('', [
            'content' => $site['about_us'] ?? '',
        ]);
    }

    /**
     * 发起充值
     * @ApiMethod (POST)
     * @ApiParams (name="amount", type="float", required=true, description="充值金额")
     * @ApiParams (name="pay_type", type="string", required=false, description="支付方式")
     */
    public function recharge()
    {
        $amount = $this->request->post('amount/f');
        $payType = $this->request->post('pay_type', 'bank');
        if ($amount <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $site = config('site');
        $minRecharge = floatval($site['recharge_min'] ?? 100);
        $maxRecharge = floatval($site['recharge_max'] ?? 1000000);
        if ($amount < $minRecharge) {
            $this->error(__('Minimum recharge amount is %s', $minRecharge));
        }
        if ($amount > $maxRecharge) {
            $this->error(__('Maximum recharge amount is %s', $maxRecharge));
        }
        $orderNo = Order::generateOrderNo();
        Recharge::create([
            'order_no' => $orderNo,
            'user_id'  => $this->auth->id,
            'amount'   => $amount,
            'pay_type' => $payType,
            'status'   => 0,
        ]);
        $this->success(__('Recharge order created'), ['order_no' => $orderNo, 'amount' => $amount]);
    }

    /**
     * 提现配置（余额、最低金额、手续费等）
     * @ApiMethod (GET)
     */
    public function withdrawconfig()
    {
        $user = $this->auth->getUser();
        $site = config('site');
        $this->success('', [
            'balance'           => $user ? $user->money : '0.00',
            'min_withdraw'      => floatval($site['min_withdraw'] ?? 100),
            'withdraw_fee_rate' => floatval($site['withdraw_fee_rate'] ?? 1),
            'withdraw_fee_usdt' => floatval($site['withdraw_fee_usdt'] ?? 2),
        ]);
    }

    /**
     * 发起提现
     * @ApiMethod (POST)
     */
    public function withdraw()
    {
        $amount = $this->request->post('amount/f');
        $bankId = $this->request->post('bank_id/d');
        $tradePassword = $this->request->post('trade_password');
        $payType = $this->request->post('pay_type', 'bank');
        if ($amount <= 0 || !$bankId || !$tradePassword) {
            $this->error(__('Invalid parameters'));
        }
        // 最低提现金额
        $site = config('site');
        $minWithdraw = floatval($site['min_withdraw'] ?? 100);
        if ($amount < $minWithdraw) {
            $this->error(__('Minimum withdraw amount is %s', $minWithdraw));
        }
        $user = $this->auth->getUser();
        if ($user->trade_password != $this->auth->getEncryptPassword($tradePassword, $user->salt)) {
            $this->error(__('Trade password is incorrect'));
        }
        $bank = UserBank::where('id', $bankId)->where('user_id', $user->id)->find();
        if (!$bank) {
            $this->error(__('Withdraw account not found'));
        }
        // 计算手续费
        $fee = 0;
        if ($payType === 'usdt') {
            $fee = floatval($site['withdraw_fee_usdt'] ?? 2);
        } else {
            $feeRate = floatval($site['withdraw_fee_rate'] ?? 1);
            $fee = $feeRate > 0 ? round($amount * $feeRate / 100, 2) : 0;
        }
        // 实际扣款 = 提现金额 + 手续费
        $totalDeduct = $amount + $fee;
        if ($user->money < $totalDeduct) {
            $this->error(__('Insufficient balance (including fee %s)', $fee));
        }
        Db::startTrans();
        try {
            // 冻结资金：从可用余额扣除，加入冻结
            $before = $user->money;
            $newMoney = function_exists('bcsub') ? bcsub($user->money, $totalDeduct, 2) : $user->money - $totalDeduct;
            $user->save(['money' => $newMoney]);
            $account = UserAccount::where('user_id', $user->id)->find();
            if ($account) {
                $account->save(['frozen' => ['inc', $totalDeduct]]);
            }
            $orderNo = Order::generateOrderNo();
            Withdraw::create([
                'order_no' => $orderNo,
                'user_id'  => $user->id,
                'amount'   => $amount,
                'fee'      => $fee,
                'pay_type' => $payType,
                'bank_id'  => $bankId,
                'status'   => 0,
            ]);
            // 提现申请时记录扣款流水
            \app\common\service\MoneyService::log(
                $user->id,
                \app\common\service\MoneyService::TYPE_WITHDRAW,
                -$totalDeduct,
                $before,
                $newMoney,
                '提现申请冻结（含手续费' . $fee . '）',
                $orderNo
            );
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error(__('Operation failed') . '：' . $e->getMessage());
        }
        $this->success(__('Withdraw submitted'), ['order_no' => $orderNo, 'amount' => $amount, 'fee' => $fee]);
    }

    /**
     * 充值记录
     * @ApiMethod (GET)
     */
    public function rechargelist()
    {
        $userId = $this->auth->id;
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);

        $where = ['user_id' => $userId];
        $total = Recharge::where($where)->count();
        $list = Recharge::where($where)
            ->field('id,order_no,amount,pay_type,status,remark,createtime')
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        $this->success('', [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 提现记录
     * @ApiMethod (GET)
     */
    public function withdrawlist()
    {
        $userId = $this->auth->id;
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);

        $where = ['user_id' => $userId];
        $total = Withdraw::where($where)->count();
        $list = Withdraw::where($where)
            ->field('id,order_no,amount,fee,pay_type,bank_id,status,remark,createtime')
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        // 关联账户信息
        $listArr = collection($list)->toArray();
        $bankIds = array_unique(array_column($listArr, 'bank_id'));
        $banks = [];
        if ($bankIds) {
            $banks = UserBank::where('id', 'in', $bankIds)->column('type,bank_name,card_no,wallet_address,chain_type', 'id');
        }
        $result = [];
        foreach ($listArr as $row) {
            $info = isset($banks[$row['bank_id']]) ? $banks[$row['bank_id']] : [];
            if ($row['pay_type'] === 'usdt') {
                $row['wallet_address'] = $info ? $info['wallet_address'] : '';
                $row['chain_type'] = $info ? $info['chain_type'] : '';
                $row['bank_name'] = '';
                $row['card_no'] = '';
            } else {
                $row['bank_name'] = $info ? $info['bank_name'] : '';
                $row['card_no'] = $info ? $info['card_no'] : '';
                $row['wallet_address'] = '';
                $row['chain_type'] = '';
            }
            $result[] = $row;
        }
        $this->success('', [
            'list'  => $result,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 银行卡列表
     * @ApiMethod (GET)
     */
    public function banklist()
    {
        $type = $this->request->get('type', '');
        $where = ['user_id' => $this->auth->id, 'status' => 1];
        if ($type) {
            $where['type'] = $type;
        }
        $list = UserBank::where($where)
            ->order('createtime desc')
            ->select();
        $this->success('', $list);
    }

    /**
     * 添加银行卡/USDT钱包
     * @ApiMethod (POST)
     */
    public function bankadd()
    {
        $type = $this->request->post('type', 'bank');
        $userId = $this->auth->id;
        // 每种类型只能添加一个
        $exists = UserBank::where('user_id', $userId)->where('type', $type)->where('status', 1)->find();
        if ($exists) {
            $this->error($type === 'usdt' ? __('USDT address already bound') : __('Bank card already bound'));
        }
        if ($type === 'usdt') {
            $walletAddress = $this->request->post('wallet_address');
            $chainType = $this->request->post('chain_type', 'TRC20');
            if (!$walletAddress) {
                $this->error(__('Invalid parameters'));
            }
            UserBank::create([
                'user_id'        => $userId,
                'type'           => 'usdt',
                'wallet_address' => $walletAddress,
                'chain_type'     => $chainType,
                'status'         => 1,
            ]);
        } else {
            $bankName = $this->request->post('bank_name');
            $cardNo = $this->request->post('card_no');
            $holderName = $this->request->post('holder_name');
            $branch = $this->request->post('branch', '');
            if (!$bankName || !$cardNo || !$holderName) {
                $this->error(__('Invalid parameters'));
            }
            UserBank::create([
                'user_id'     => $userId,
                'type'        => 'bank',
                'bank_name'   => $bankName,
                'card_no'     => $cardNo,
                'holder_name' => $holderName,
                'branch'      => $branch,
                'status'      => 1,
            ]);
        }
        $this->success(__('Added successfully'));
    }

    /**
     * 删除银行卡
     * @ApiMethod (POST)
     * @ApiParams (name="id", type="int", required=true, description="银行卡ID")
     */
    public function bankdelete()
    {
        $id = $this->request->post('id/d');
        if (!$id) {
            $this->error(__('Invalid parameters'));
        }
        $bank = UserBank::where('id', $id)->where('user_id', $this->auth->id)->find();
        if (!$bank) {
            $this->error(__('Bank card not found'));
        }
        $bank->delete();
        $this->success(__('Deleted successfully'));
    }
}
