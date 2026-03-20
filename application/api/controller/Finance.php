<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\FinanceOrder;
use app\common\model\FinanceProduct;
use app\common\model\Order;
use app\common\model\User;
use app\common\model\UserAccount;

/**
 * 理财（锁仓赚币）接口
 */
class Finance extends Api
{
    protected $noNeedLogin = ['products'];
    protected $noNeedRight = '*';

    /**
     * 理财产品列表
     * @ApiMethod (GET)
     */
    public function products()
    {
        $list = FinanceProduct::where('status', 1)
            ->order('sort asc, id asc')
            ->select();
        $this->success('', $list);
    }

    /**
     * 锁仓转入
     * @ApiMethod (POST)
     * @ApiParams (name="product_id", type="int", required=true, description="理财产品ID")
     * @ApiParams (name="amount", type="float", required=true, description="转入金额")
     */
    public function lock()
    {
        $productId = $this->request->post('product_id/d');
        $amount = $this->request->post('amount/f');
        if (!$productId || $amount <= 0) {
            $this->error(__('Invalid parameters'));
        }
        $product = FinanceProduct::get($productId);
        if (!$product || $product->status != 1) {
            $this->error(__('Finance product not found or closed'));
        }
        if ($amount < $product->min_amount) {
            $this->error(__('Minimum amount is %s', $product->min_amount));
        }
        // 验证开放时间
        $nowTime = date('H:i');
        $openTime = $product->open_time ?: '00:00';
        $closeTime = $product->close_time ?: '23:59';
        if ($nowTime < $openTime || $nowTime >= $closeTime) {
            $this->error(__('Not in open time (%s-%s)', $openTime, $closeTime));
        }
        // 验证余额
        $userId = $this->auth->id;
        $user = User::get($userId);
        if (!$user || $user->money < $amount) {
            $this->error(__('Insufficient balance'));
        }
        $now = time();
        $endTime = $now + $product->lock_days * 86400;
        $orderNo = Order::generateOrderNo();
        // 扣款
        $ret = UserAccount::changeBalance($userId, -$amount, 6, $orderNo, __('Finance lock deduction'));
        if (!$ret) {
            $this->error(__('Deduction failed, please retry'));
        }
        FinanceOrder::create([
            'order_no'   => $orderNo,
            'user_id'    => $userId,
            'product_id' => $productId,
            'amount'     => $amount,
            'daily_rate' => $product->daily_rate,
            'lock_days'  => $product->lock_days,
            'status'     => 0,
            'start_time' => $now,
            'end_time'   => $endTime,
        ]);
        $this->success(__('Lock successful'), [
            'order_no'  => $orderNo,
            'amount'    => $amount,
            'lock_days' => $product->lock_days,
            'end_time'  => $endTime,
        ]);
    }

    /**
     * 理财记录列表
     * @ApiMethod (GET)
     * @ApiParams (name="status", type="int", required=false, description="状态:0=锁仓中,1=已到期")
     * @ApiParams (name="page", type="int", required=false, description="页码")
     * @ApiParams (name="limit", type="int", required=false, description="每页数量")
     */
    public function orders()
    {
        $userId = $this->auth->id;
        $status = $this->request->get('status', '');
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);
        $where = ['user_id' => $userId];
        if ($status !== '') {
            $where['status'] = intval($status);
        }
        $total = FinanceOrder::where($where)->count();
        $list = FinanceOrder::with(['financeProduct'])
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        // 计算预计收益和累计收益，追加产品名称
        $now = time();
        $result = [];
        foreach ($list as $item) {
            $row = $item->toArray();
            $days = $row['lock_days'] ?: 0;
            $rate = $row['daily_rate'] ?: 0;
            $amount = $row['amount'] ?: 0;
            // 产品名称
            $product = $item->financeProduct;
            $row['product_name'] = $product ? $product->name : __('Finance product');
            // 预计总收益 = 金额 * 日收益率 * 锁仓天数
            $row['expected_profit'] = sprintf('%.2f', $amount * $rate * $days);
            // 累计收益：已过天数 * 日收益
            if ($row['status'] == 1) {
                $passedDays = $days;
            } else {
                $passedDays = max(0, floor(($now - $row['start_time']) / 86400));
                $passedDays = min($passedDays, $days);
            }
            $row['total_profit'] = sprintf('%.2f', $amount * $rate * $passedDays);
            $result[] = $row;
        }
        $this->success('', [
            'list'  => $result,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }
}
