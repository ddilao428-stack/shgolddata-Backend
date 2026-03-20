<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\News as NewsModel;
use app\common\model\NewsCategory;

/**
 * 新闻资讯接口
 */
class News extends Api
{
    protected $noNeedLogin = ['categories', 'list', 'detail'];
    protected $noNeedRight = '*';

    /**
     * 新闻分类列表
     * @ApiMethod (GET)
     */
    public function categories()
    {
        $list = NewsCategory::where('status', 1)
            ->order('sort asc, id asc')
            ->field('id,name,name_en,flag')
            ->select();
        $this->success('', $list);
    }

    /**
     * 新闻列表
     * @ApiMethod (GET)
     * @ApiParams (name="category_id", type="int", required=false, description="分类ID")
     * @ApiParams (name="page", type="int", required=false, description="页码")
     * @ApiParams (name="limit", type="int", required=false, description="每页数量")
     */
    public function list()
    {
        $categoryId = $this->request->get('category_id/d', 0);
        $categoryFlag = $this->request->get('category_flag', '');
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $limit = min(max($limit, 1), 50);

        // 支持通过 flag 查找分类
        if (!$categoryId && $categoryFlag) {
            $cat = NewsCategory::where('flag', $categoryFlag)->where('status', 1)->find();
            if ($cat) {
                $categoryId = $cat->id;
            }
        }

        $countQuery = NewsModel::where('status', 1);
        if ($categoryId > 0) {
            $countQuery->where('category_id', $categoryId);
        }
        $total = $countQuery->count();

        $listQuery = NewsModel::where('status', 1);
        if ($categoryId > 0) {
            $listQuery->where('category_id', $categoryId);
        }
        $list = $listQuery->order('publish_time desc, id desc')
            ->page($page, $limit)
            ->field('id,category_id,title,cover,summary,author,views,publish_time')
            ->select();
        $this->success('', [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 新闻详情
     * @ApiMethod (GET)
     * @ApiParams (name="id", type="int", required=true, description="新闻ID")
     */
    public function detail()
    {
        $id = $this->request->get('id/d');
        if (!$id) {
            $this->error(__('Invalid parameters'));
        }
        $news = NewsModel::get($id);
        if (!$news || $news->status != 1) {
            $this->error('资讯不存在');
        }
        // 浏览量+1
        $news->setInc('views');
        $this->success('', $news);
    }
}
