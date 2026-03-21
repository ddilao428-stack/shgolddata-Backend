<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;
use app\common\service\MoneyService;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            // 批量查询实名认证信息
            $userIds = array_column($list->items(), 'id');
            $verifyMap = [];
            if ($userIds) {
                $verifyList = \think\Db::name('user_verify')->where('user_id', 'in', $userIds)->where('status', 1)->column('real_name,id_card', 'user_id');
                $verifyMap = $verifyList;
            }
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
                $v->real_name = isset($verifyMap[$v->id]) ? $verifyMap[$v->id]['real_name'] : '';
                $v->id_card = isset($verifyMap[$v->id]) ? $verifyMap[$v->id]['id_card'] : '';
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
            // 处理实名认证信息修改
            $verifyData = $this->request->post('verify/a');
            if ($verifyData) {
                $row = $this->model->get($ids);
                if ($row) {
                    $verify = \think\Db::name('user_verify')->where('user_id', $row['id'])->where('status', 1)->find();
                    if ($verify) {
                        \think\Db::name('user_verify')->where('id', $verify['id'])->update([
                            'real_name'  => $verifyData['real_name'],
                            'id_card'    => $verifyData['id_card'],
                            'updatetime' => time(),
                        ]);
                        // 同步昵称为真实姓名
                        $this->model->where('id', $row['id'])->update(['nickname' => $verifyData['real_name']]);
                    }
                }
            }
            // 自动填充昵称为用户名（昵称字段已隐藏）
            $params = $this->request->post('row/a');
            if (empty($params['nickname'])) {
                $_POST['row']['nickname'] = $params['username'] ?? '';
            }
            // 提现密码为空时不更新，非空时由模型 beforeUpdate 自动加密
            if (empty($params['trade_password'])) {
                unset($_POST['row']['trade_password']);
            }
        }
        
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        $this->modelSceneValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        // 查询实名认证信息
        $verify = \think\Db::name('user_verify')->where('user_id', $row['id'])->where('status', 1)->find();
        $this->view->assign('verify', $verify);
        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        return parent::edit($ids);
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

    /**
     * 上下分
     */
    public function changemoney($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $type = $this->request->param('type', 'add');

        if ($this->request->isPost()) {
            $amount = $this->request->post('amount/f');
            $memo = $this->request->post('memo', '');

            if ($amount <= 0) {
                $this->error('请输入正确的金额');
            }

            $before = $row['money'];
            if ($type == 'add') {
                $after = bcadd($before, $amount, 2);
                $changeMoney = $amount;
                $defaultMemo = '管理员上分';
            } else {
                if (bccomp($before, $amount, 2) < 0) {
                    $this->error('余额不足，当前余额：' . $before);
                }
                $after = bcsub($before, $amount, 2);
                $changeMoney = -$amount;
                $defaultMemo = '管理员下分';
            }

            \think\Db::startTrans();
            try {
                $this->model->where('id', $ids)->update(['money' => $after]);
                MoneyService::log($ids, MoneyService::TYPE_ADMIN, $changeMoney, $before, $after, $memo ?: $defaultMemo);
                \think\Db::commit();
            } catch (\Exception $e) {
                \think\Db::rollback();
                $this->error('操作失败：' . $e->getMessage());
            }
            $this->success('操作成功');
        }

        $this->view->assign('row', $row);
        $this->view->assign('type', $type);
        return $this->view->fetch();
    }

}
