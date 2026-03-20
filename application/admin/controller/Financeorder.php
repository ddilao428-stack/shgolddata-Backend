<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\UserAccount;
use think\Db;

/**
 * 理财锁仓记录管理
 *
 * @icon fa fa-circle-o
 */
class Financeorder extends Backend
{

    /**
     * Financeorder模型对象
     * @var \app\admin\model\Financeorder
     */
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Financeorder;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['financeproduct', 'user'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            $total = $this->model
                ->with(['financeproduct', 'user'])
                ->where($where)
                ->count();
            $result = ['total' => $total, 'rows' => $list];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 手动结算锁仓订单（补发所有未发放收益 + 返还本金）
     */
    public function settle($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));
        if ($row['status'] != 0) $this->error('该订单不是锁仓中状态');

        Db::startTrans();
        try {
            $today = date('Y-m-d');
            // 已发放天数
            $distributedDays = Db::name('finance_profit_log')
                ->where('order_id', $row['id'])
                ->count();
            $remainDays = $row['lock_days'] - $distributedDays;

            $dailyProfit = function_exists('bcmul')
                ? bcmul($row['amount'], $row['daily_rate'], 2)
                : round($row['amount'] * $row['daily_rate'], 2);

            // 补发剩余天数的收益记录
            for ($i = 1; $i <= $remainDays; $i++) {
                $dayIndex = $distributedDays + $i;
                $profitDate = date('Y-m-d', strtotime($today . ' +' . ($i - 1) . ' day'));
                Db::name('finance_profit_log')->insert([
                    'order_id'    => $row['id'],
                    'user_id'     => $row['user_id'],
                    'product_id'  => $row['product_id'],
                    'order_no'    => $row['order_no'],
                    'amount'      => $row['amount'],
                    'daily_rate'  => $row['daily_rate'],
                    'profit'      => $dailyProfit,
                    'day_index'   => $dayIndex,
                    'profit_date' => $profitDate,
                    'createtime'  => time(),
                ]);
            }

            // 计算补发总收益
            $remainProfit = function_exists('bcmul')
                ? bcmul($dailyProfit, $remainDays, 2)
                : round($dailyProfit * $remainDays, 2);
            // 总收益 = 已发放 + 补发
            $totalProfit = function_exists('bcadd')
                ? bcadd($row['total_profit'], $remainProfit, 2)
                : $row['total_profit'] + $remainProfit;

            // 发放剩余收益
            if ($remainProfit > 0) {
                $ret = UserAccount::changeBalance(
                    $row['user_id'],
                    $remainProfit,
                    7,
                    $row['order_no'],
                    '理财手动结算补发收益'
                );
                if (!$ret) {
                    throw new \Exception('补发收益失败');
                }
            }

            // 返还本金
            $retPrincipal = UserAccount::changeBalance(
                $row['user_id'],
                $row['amount'],
                8,
                $row['order_no'],
                '理财锁仓到期返还本金'
            );
            if (!$retPrincipal) {
                throw new \Exception('返还本金失败');
            }

            $row->save([
                'total_profit' => $totalProfit,
                'status'       => 1,
            ]);

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('结算失败：' . $e->getMessage());
        }
        $this->success('结算成功');
    }

}
