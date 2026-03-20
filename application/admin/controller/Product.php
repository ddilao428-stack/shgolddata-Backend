<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\model\ProductTradeTime;
use think\Db;

/**
 * 产品管理
 *
 * @icon fa fa-circle-o
 */
class Product extends Backend
{

    /**
     * Product模型对象
     * @var \app\admin\model\Product
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Product;
        $this->view->assign("statusList", $this->model->getStatusList());
        // 产品分类列表
        $categoryList = Db::name('product_category')->where('status', 1)->order('sort asc')->column('id,name');
        $this->view->assign("categoryList", $categoryList);
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = false;
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            // 追加分类名称
            $categoryList = Db::name('product_category')->where('status', 1)->column('name', 'id');
            foreach ($list as $row) {
                $row['category_name'] = isset($categoryList[$row['category_id']]) ? $categoryList[$row['category_id']] : '-';
            }
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }



    /**
     * 交易时间配置
     */
    public function tradetime($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));

        if ($this->request->isPost()) {
            $times = $this->request->post('times/a', []);
            ProductTradeTime::where('product_id', $ids)->delete();
            foreach ($times as $i => $t) {
                if (!empty($t['deal_time_start']) && !empty($t['deal_time_end'])) {
                    ProductTradeTime::create([
                        'product_id'      => $ids,
                        'deal_time_start' => $t['deal_time_start'],
                        'deal_time_end'   => $t['deal_time_end'],
                        'time_order'      => $i + 1,
                    ]);
                }
            }
            $this->success('保存成功');
        }

        $times = ProductTradeTime::where('product_id', $ids)->order('time_order asc')->select();
        $this->view->assign('row', $row);
        $this->view->assign('times', $times);
        return $this->view->fetch();
    }

    /**
     * 时间盘配置
     */
    public function timeconfig($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) $this->error(__('No Results were found'));

        if ($this->request->isPost()) {
            $config = $this->request->post('config/a', []);
            $list = [];
            foreach ($config as $c) {
                if (!empty($c['minute'])) {
                    $list[] = [
                        'minute'   => intval($c['minute']),
                        'odds'     => floatval($c['odds'] ?? 0),
                    ];
                }
            }
            $row->save(['time_config' => json_encode($list, JSON_UNESCAPED_UNICODE)]);
            $this->success('保存成功');
        }

        $timeConfig = $row['time_config'] ? json_decode($row['time_config'], true) : [];
        $this->view->assign('row', $row);
        $this->view->assign('timeConfig', $timeConfig);
        return $this->view->fetch();
    }

}
