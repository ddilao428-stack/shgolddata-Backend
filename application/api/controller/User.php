<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\User as UserModel;
use app\common\model\UserAccount;
use app\common\model\UserVerify;
use fast\Random;
use think\captcha\Captcha;
use think\Config;
use think\Validate;

/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = ['login', 'register', 'resetpwd', 'captcha'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 会员登录
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="captcha", type="string", required=true, description="图形验证码")
     */
    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        $captcha = $this->request->post('captcha');
        if (!$account || !$password || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!captcha_check($captcha)) {
            $this->error(__('Captcha is incorrect'));
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 会员注册
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="nickname", type="string", required=true, description="姓名")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="password", type="string", required=true, description="密码")
     * @ApiParams (name="trade_password", type="string", required=true, description="提现密码")
     * @ApiParams (name="captcha", type="string", required=true, description="图形验证码")
     */
    public function register()
    {
        $account = $this->request->post('account');
        $nickname = $this->request->post('nickname');
        $mobile = $this->request->post('mobile');
        $password = $this->request->post('password');
        $tradePassword = $this->request->post('trade_password');
        $captcha = $this->request->post('captcha');
        if (!$account || !$nickname || !$mobile || !$password || !$tradePassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        // if (!captcha_check($captcha)) {
        //     $this->error(__('Captcha is incorrect'));
        // }
        if (!Validate::regex($mobile, '/^1\d{10}$/')) {
            $this->error(__('Mobile is incorrect'));
        }
        if (strlen($password) < 6) {
            $this->error(__('Password must be at least 6 characters'));
        }
        if (strlen($tradePassword) < 6) {
            $this->error(__('Trade password must be at least 6 characters'));
        }
        $salt = Random::alnum();
        $encryptTradePassword = $this->auth->getEncryptPassword($tradePassword, $salt);
        $extend = [
            'nickname'       => $nickname,
            'trade_password' => $encryptTradePassword,
        ];
        $ret = $this->auth->register($account, $password, '', $mobile, $extend);
        if ($ret) {
            $userId = $this->auth->getUser()->id;
            UserAccount::create(['user_id' => $userId, 'frozen' => 0]);
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 图形验证码
     * @ApiParams (name="id", type="string", required=false, description="验证码标识")
     */
    public function captcha($id = "")
    {
        Config::set([
            'captcha' => array_merge(config('captcha'), [
                'fontSize' => 44,
                'imageH'   => 150,
                'imageW'   => 350,
            ])
        ]);
        $captcha = new Captcha((array)Config::get('captcha'));
        return $captcha->entry($id);
    }

    /**
     * 重置密码
     * @ApiMethod (POST)
     * @ApiParams (name="account", type="string", required=true, description="账号")
     * @ApiParams (name="mobile", type="string", required=true, description="手机号")
     * @ApiParams (name="newpassword", type="string", required=true, description="新密码")
     * @ApiParams (name="captcha", type="string", required=true, description="图形验证码")
     */
    public function resetpwd()
    {
        $account = $this->request->post('account');
        $mobile = $this->request->post('mobile');
        $newpassword = $this->request->post('newpassword');
        $captcha = $this->request->post('captcha');
        if (!$account || !$mobile || !$newpassword || !$captcha) {
            $this->error(__('Invalid parameters'));
        }
        if (!captcha_check($captcha)) {
            $this->error(__('Captcha is incorrect'));
        }
        if (strlen($newpassword) < 6) {
            $this->error(__('Password must be at least 6 characters'));
        }
        $user = UserModel::where('username', $account)->where('mobile', $mobile)->find();
        if (!$user) {
            $this->error(__('Account not exist'));
        }
        $salt = Random::alnum();
        $newpwd = $this->auth->getEncryptPassword($newpassword, $salt);
        $user->save(['password' => $newpwd, 'salt' => $salt, 'loginfailure' => 0]);
        $this->success(__('Reset password successful'));
    }

    /**
     * 获取用户信息
     * @ApiMethod (GET)
     */
    public function profile()
    {
        $user = $this->auth->getUser();
        $account = UserAccount::where('user_id', $user->id)->find();
        $verify = UserVerify::where('user_id', $user->id)->order('id', 'desc')->find();
        $data = [
            'id'          => $user->id,
            'username'    => $user->username,
            'nickname'    => $user->nickname,
            'mobile'      => $user->mobile,
            'avatar'      => $user->avatar,
            'is_verified' => $user->is_verified ?? 0,
            'language'    => $user->language ?? 'zh-CN',
            'jointime'    => $user->jointime,
            'account'     => [
                'balance'        => $user->money ?? '0.00',
                'total_recharge' => $account ? $account->total_recharge : '0.00',
                'total_withdraw' => $account ? $account->total_withdraw : '0.00',
                'total_profit'   => $account ? $account->total_profit : '0.00',
            ],
            'verify'      => $verify ? [
                'status'        => $verify->status,
                'real_name'     => $verify->real_name,
                'id_type'       => $verify->id_type,
                'id_card'       => $verify->id_card,
                'id_card_front' => $verify->id_card_front,
                'id_card_back'  => $verify->id_card_back,
                'reject_reason' => $verify->reject_reason ?? '',
            ] : null,
        ];
        $this->success('', $data);
    }

    /**
     * 修改登录密码
     * @ApiMethod (POST)
     * @ApiParams (name="oldpassword", type="string", required=true, description="原密码")
     * @ApiParams (name="newpassword", type="string", required=true, description="新密码")
     */
    public function changepwd()
    {
        $oldpassword = $this->request->post('oldpassword');
        $newpassword = $this->request->post('newpassword');
        if (!$oldpassword || !$newpassword) {
            $this->error(__('Invalid parameters'));
        }
        if (strlen($newpassword) < 6) {
            $this->error(__('Password must be at least 6 characters'));
        }
        $ret = $this->auth->changepwd($newpassword, $oldpassword);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 修改提现密码
     * @ApiMethod (POST)
     * @ApiParams (name="oldpassword", type="string", required=true, description="原提现密码")
     * @ApiParams (name="newpassword", type="string", required=true, description="新提现密码")
     */
    public function changetradepwd()
    {
        $oldpassword = $this->request->post('oldpassword');
        $newpassword = $this->request->post('newpassword');
        if (!$oldpassword || !$newpassword) {
            $this->error(__('Invalid parameters'));
        }
        if (strlen($newpassword) < 6) {
            $this->error(__('Trade password must be at least 6 characters'));
        }
        $user = $this->auth->getUser();
        // 验证原提现密码
        if ($user->trade_password != $this->auth->getEncryptPassword($oldpassword, $user->salt)) {
            $this->error(__('Password is incorrect'));
        }
        // 复用用户salt加密提现密码
        $user->save(['trade_password' => $this->auth->getEncryptPassword($newpassword, $user->salt)]);
        $this->success(__('Reset password successful'));
    }

    /**
     * 实名认证提交
     * @ApiMethod (POST)
     * @ApiParams (name="real_name", type="string", required=true, description="真实姓名")
     * @ApiParams (name="id_card", type="string", required=true, description="证件号码")
     * @ApiParams (name="id_type", type="int", required=true, description="证件类型:1=身份证,2=驾驶证,3=SSN,4=护照")
     * @ApiParams (name="id_card_front", type="string", required=true, description="证件正面照")
     * @ApiParams (name="id_card_back", type="string", required=false, description="证件反面照")
     */
    public function realname()
    {
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        if ($user->is_verified == 1) {
            $this->error(__('Already verified'));
        }
        // 检查是否有待审核的认证
        $pending = UserVerify::where('user_id', $userId)->where('status', 0)->find();
        if ($pending) {
            $this->error(__('Verification is pending'));
        }
        $realName = $this->request->post('real_name');
        $idCard = $this->request->post('id_card');
        $idType = $this->request->post('id_type/d', 1);
        $idCardFront = $this->request->post('id_card_front');
        $idCardBack = $this->request->post('id_card_back', '');
        if (!$realName || !$idCard || !$idCardFront) {
            $this->error(__('Invalid parameters'));
        }
        if (!in_array($idType, [1, 2, 3, 4])) {
            $this->error(__('Invalid parameters'));
        }
        $data = [
            'real_name'     => $realName,
            'id_card'       => $idCard,
            'id_type'       => $idType,
            'id_card_front' => $idCardFront,
            'id_card_back'  => $idCardBack,
            'status'        => 0,
            'reject_reason' => '',
            'admin_id'      => 0,
            'audit_time'    => null,
        ];
        // 被拒绝的记录直接更新，否则新建
        $rejected = UserVerify::where('user_id', $userId)->where('status', 2)->find();
        if ($rejected) {
            $rejected->save($data);
        } else {
            $data['user_id'] = $userId;
            UserVerify::create($data);
        }
        $this->success(__('Verification submitted'));
    }

    /**
     * 退出登录
     * @ApiMethod (POST)
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }
}
