<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 产品分类管理
 *
 * @icon fa fa-list
 */
class Productcategory extends Backend
{

    protected $model = null;
    protected $searchFields = 'id,name,name_en';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('app\common\model\ProductCategory');
        $this->view->assign("statusList", ["0" => __('Status 0'), "1" => __('Status 1')]);
    }

    /**
     * 查看
     */
    public function index()
    {
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
            $result = ["total" => $list->total(), "rows" => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }
}
