<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\User;
use app\common\model\UserAccount;
use think\Db;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Order;
        $this->view->assign("directionList", $this->model->getDirectionList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("resultList", $this->model->getResultList());
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
                ->with(['user', 'product'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $row) {
                $row->user_username = $row->user ? $row->user->username : '-';
                $row->product_name = $row->product ? ($row->product->name . '/' . $row->product->name_en) : '-';
            }
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 手动结算单个订单
     */
    public function settle($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));
        if ($row['status'] != 0) $this->error('该订单不是持仓状态');

        try {
            $this->settleOneOrder($row);
        } catch (\Exception $e) {
            $this->error('结算失败：' . $e->getMessage());
        }
        $this->success('结算成功');
    }

    /**
     * 批量结算订单
     */
    public function batchsettle()
    {
        $ids = $this->request->post('ids');
        if (!$ids) $this->error('请选择要结算的订单');

        $idArr = explode(',', $ids);
        $orders = $this->model->where('id', 'in', $idArr)->where('status', 0)->select();
        if (!$orders || count($orders) == 0) {
            $this->error('没有可结算的持仓订单');
        }

        $success = 0;
        $fail = 0;
        foreach ($orders as $order) {
            try {
                $this->settleOneOrder($order);
                $success++;
            } catch (\Exception $e) {
                $fail++;
            }
        }

        if ($fail > 0) {
            $this->success("结算完成：成功 {$success} 笔，失败 {$fail} 笔");
        } else {
            $this->success("批量结算完成，共 {$success} 笔");
        }
    }

    /**
     * 结算单个订单（内部复用）
     */
    protected function settleOneOrder($order)
    {
        $product = \app\common\model\Product::get($order->product_id);
        if (!$product) {
            throw new \Exception('产品不存在');
        }

        $closePrice  = $product->price;
        $openPrice   = $order->open_price;
        $tradeAmount = $order->trade_amount;
        $odds        = $order->odds / 100;
        $direction   = $order->direction;
        $userId      = $order->user_id;

        $user = User::get($userId);
        $winControl = $user ? intval($user->win_control) : 0;

        if ($direction == 0) {
            $result = $closePrice > $openPrice ? 1 : ($closePrice == $openPrice ? 0 : -1);
        } else {
            $result = $closePrice < $openPrice ? 1 : ($closePrice == $openPrice ? 0 : -1);
        }

        if ($winControl == 1) {
            $result = 1;
            $randomRate = mt_rand(2, 8) / 10000;
            if ($direction == 0) {
                $closePrice = $openPrice + ($openPrice * $randomRate);
                if ($closePrice <= $openPrice) $closePrice = $openPrice + ($openPrice * 0.0002);
            } else {
                $closePrice = $openPrice - ($openPrice * $randomRate);
                if ($closePrice >= $openPrice) $closePrice = $openPrice - ($openPrice * 0.0002);
            }
        } elseif ($winControl == 2) {
            $result = -1;
            $randomRate = mt_rand(2, 8) / 10000;
            if ($direction == 0) {
                $closePrice = $openPrice - ($openPrice * $randomRate);
                if ($closePrice >= $openPrice) $closePrice = $openPrice - ($openPrice * 0.0002);
            } else {
                $closePrice = $openPrice + ($openPrice * $randomRate);
                if ($closePrice <= $openPrice) $closePrice = $openPrice + ($openPrice * 0.0002);
            }
        }

        if ($result == 1) {
            $profit = round($tradeAmount * $odds, 2);
            $returnAmount = $tradeAmount + $profit;
            $orderResult = 1;
            $note = '交易盈利结算';
        } elseif ($result == 0) {
            $profit = 0;
            $returnAmount = $tradeAmount;
            $orderResult = 2;
            $note = '交易平局退还';
        } else {
            $loss = round($tradeAmount * $odds, 2);
            $profit = -$loss;
            $returnAmount = $tradeAmount - $loss;
            $orderResult = 0;
            $note = '交易亏损结算';
        }

        $now = time();
        Db::startTrans();
        try {
            $order->save([
                'close_price' => round($closePrice, 6),
                'profit'      => $profit,
                'status'      => 1,
                'result'      => $orderResult,
                'close_time'  => $now,
            ]);

            if ($returnAmount > 0) {
                $ret = UserAccount::changeBalance($userId, $returnAmount, 4, $order->order_no, $note);
                if (!$ret) {
                    throw new \Exception('返还资金失败');
                }
            }

            $account = UserAccount::where('user_id', $userId)->find();
            if ($account) {
                if ($orderResult == 1 && $profit > 0) {
                    $account->total_profit = function_exists('bcadd')
                        ? bcadd($account->total_profit, $profit, 2)
                        : $account->total_profit + $profit;
                } elseif ($orderResult == 0) {
                    $account->total_loss = function_exists('bcadd')
                        ? bcadd($account->total_loss, abs($profit), 2)
                        : $account->total_loss + abs($profit);
                }
                $account->save();
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

}
