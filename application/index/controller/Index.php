<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        // 重定向到/web
        return $this->redirect('/web');
        // return $this->view->fetch();
    }

}
