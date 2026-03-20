<?php

namespace app\index\controller;

use app\common\controller\Frontend;

/**
 * 客服页面
 */
class Service extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        return $this->view->fetch();
    }

}
