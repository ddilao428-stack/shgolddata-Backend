<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Product;
use app\common\model\UserFavorite;

/**
 * 用户收藏（自选）接口
 */
class Favorite extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 自选列表（关联产品实时行情）
     * @ApiMethod (GET)
     */
    public function list()
    {
        $userId = $this->auth->id;
        $favorites = UserFavorite::where('user_id', $userId)
            ->order('createtime desc')
            ->column('product_id');
        if (empty($favorites)) {
            $this->success('', []);
        }
        $products = Product::where('id', 'in', $favorites)
            ->where('status', 1)
            ->field('id,product_code,capital_key,name,name_en,category_id,price,open_price,close_price,high_price,low_price,diff,diff_rate,buy_price,sell_price,icon')
            ->select();
        $this->success('', $products);
    }

    /**
     * 添加收藏
     * @ApiMethod (POST)
     * @ApiParams (name="product_id", type="int", required=true, description="产品ID")
     */
    public function add()
    {
        $productId = $this->request->post('product_id/d');
        if (!$productId) {
            $this->error(__('Invalid parameters'));
        }
        $product = Product::get($productId);
        if (!$product) {
            $this->error(__('Product not found'));
        }
        $userId = $this->auth->id;
        $exists = UserFavorite::where('user_id', $userId)->where('product_id', $productId)->find();
        if ($exists) {
            $this->error(__('Already in favorites'));
        }
        UserFavorite::create([
            'user_id'    => $userId,
            'product_id' => $productId,
        ]);
        $this->success(__('Added to favorites'));
    }

    /**
     * 取消收藏
     * @ApiMethod (POST)
     * @ApiParams (name="product_id", type="int", required=true, description="产品ID")
     */
    public function remove()
    {
        $productId = $this->request->post('product_id/d');
        if (!$productId) {
            $this->error(__('Invalid parameters'));
        }
        $userId = $this->auth->id;
        $favorite = UserFavorite::where('user_id', $userId)->where('product_id', $productId)->find();
        if (!$favorite) {
            $this->error(__('Not in favorites'));
        }
        $favorite->delete();
        $this->success(__('Removed from favorites'));
    }
}
