<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 新闻资讯管理
 *
 * @icon fa fa-circle-o
 */
class News extends Backend
{

    /**
     * News模型对象
     * @var \app\admin\model\News
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\News;
        $this->view->assign("statusList", $this->model->getStatusList());

        // 新闻分类列表
        $categoryList = \app\admin\model\Newscategory::where('status', 1)
            ->order('sort desc, id asc')
            ->column('name', 'id');
        $this->view->assign("categoryList", $categoryList);
    }



    /**
     * 查看
     */
    public function index()
    {
        // 设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            // 如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with(['category'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }

}
