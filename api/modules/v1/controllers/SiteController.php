<?php

namespace api\modules\v1\controllers;

use backend\controllers\ProtocolsManageController;
use frontend\services\payment\WxPay\wx_qrcode;
use frontend\services\payment\WxPay\WxPayPubHelper\JsApi_pub;
use frontend\services\wechat\JSSDK;
use system\helpers\SysHelper;
use system\services\Area;
use system\services\ComplainService;
use system\services\MemberMessages;
use system\services\Payment;
use system\services\ProtocolService;
use system\services\Setting;
use system\services\Sms;
use system\services\SmsCode;
use Yii;
use yii\base\InvalidParamException;
use yii\debug\components\search\matchers\Base;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use frontend\services\LoginForm;
use frontend\services\PasswordResetRequestForm;
use frontend\services\ResetPasswordForm;
use frontend\services\SignupForm;
use frontend\services\ContactForm;
use system\services\Member;
use system\action\CaptchaAction;
use system\services\Captcha;
use yii\helpers\Url;
use yii\web\Cookie;

/**
 * Site controller
 */
class SiteController extends BaseController
{


    /**
     * @return string|\yii\web\Response
     * 会员登录
     */
    public function actionLogin()
    {

        $post = SysHelper::postInputData();
        $model = new Member();
        $member_username = $post['member_username'];
        $member_password = $post['member_password'];
        if (empty($member_username)) {
            $this->jsonRet->jsonOutput($this->errorRet['ACCOUNT_IS_NULL']['ERROR_NO'], $this->errorRet['ACCOUNT_IS_NULL']['ERROR_MESSAGE']);
        }
        if (empty($member_password)) {
            $this->jsonRet->jsonOutput($this->errorRet['PASSWORD_NOT_NULL']['ERROR_NO'], $this->errorRet['PASSWORD_NOT_NULL']['ERROR_MESSAGE']);
        }
        $condition = ['and', ['or', ['=', 'member_username', $member_username], ['=', 'member_mobile', $member_username],
            ['=', 'member_email', $member_username]]];
        $fields = 'member_id,member_username,member_name,member_avatar,member_sex,member_email,member_email_bind,
        member_mobile,member_mobile_bind,member_points,member_grow_points,available_predeposit,freeze_predeposit,
        member_bank_bind,member_bank_real_name,member_bank_name,member_bank_account,member_bank_mobile,member_state,
        member_sign_num,member_sign_day,member_token,member_password,member_login_time,member_login_ip';
        $member = $model->getMemberInfo($condition);
        if (empty($member) || !Yii::$app->security->validatePassword($member_password, $member['member_password'])) {

            $this->jsonRet->jsonOutput($this->errorRet['LOGIN_ERROR']['ERROR_NO'], $this->errorRet['LOGIN_ERROR']['ERROR_MESSAGE']);
        } elseif ($member['member_state'] != 1) {
            $this->jsonRet->jsonOutput($this->errorRet['MEMBER_DISABLED']['ERROR_NO'], $this->errorRet['MEMBER_DISABLED']['ERROR_MESSAGE']);
        }
        $member['member_avatar'] = SysHelper::getImage($member['member_avatar'], 0, 0, 0, [0, 0], 1);
        //更新登录时间和ip  TOKEN
        $token_key = $member['member_mobile'] ? $member['member_mobile'] : $member['member_id'];
        $token = md5(sha1($token_key . uniqid()));
        $new_data['member_login_time'] = time();
        $new_data['member_old_login_time'] = $member['member_login_time'];
        $new_data['member_login_ip'] = Yii::$app->request->getUserIP();
        $new_data['member_old_login_ip'] = $member['member_login_ip'];
        $new_data['member_token'] = $token;
        $model->updateMemberByCondition($new_data, ['member_id' => $member['member_id']]);


        $this->jsonRet->jsonOutput(0, '', ['member_token' => $token]);

    }

    /**
     * @return string|\yii\web\Response
     * 会员登录  手机验证码登录
     */
    public function actionLoginByMobile()
    {

        $post = SysHelper::postInputData();
        $model = new Member();
        $sms_model = new SmsCode();
        $member_mobile = isset($post['member_mobile']) ? $post['member_mobile'] : '';
        $auth_code_mobile = isset($post['auth_code']) ? $post['auth_code'] : '';
        if (empty($member_mobile)) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_NOT_NULL']['ERROR_MESSAGE']);
        }
        if (empty($auth_code_mobile)) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_MESSAGE']);
        }

        $auth_code = $sms_model->getSmsCodeInfo(['member_mobile' => $post['member_mobile']]);
        if ($auth_code['time'] < time()) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_MESSAGE']);
        }
        if ($auth_code['code'] != $auth_code_mobile) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_MESSAGE']);
        }
        $member = $model->getMemberInfo(['member_mobile' => $post['member_mobile']]);
        if (!$member) {
            $this->jsonRet->jsonOutput($this->errorRet['MEMBER_MOBILE_IS_EXIST']['ERROR_NO'], $this->errorRet['MEMBER_MOBILE_IS_EXIST']['ERROR_MESSAGE']);
        }
        if ($member['member_state'] != 1) {
            $this->jsonRet->jsonOutput($this->errorRet['MEMBER_DISABLED']['ERROR_NO'], $this->errorRet['MEMBER_DISABLED']['ERROR_MESSAGE']);
        }
        //    $member['member_avatar'] = SysHelper::getImage($member['member_avatar'],0,0,0,[0,0],1);
        //更新登录时间和ip  TOKEN
        $token_key = $member['member_mobile'] ? $member['member_mobile'] : $member['member_id'];
        $token = md5(sha1($token_key . uniqid()));
        $new_data['member_login_time'] = time();
        $new_data['member_old_login_time'] = $member['member_login_time'];
        $new_data['member_login_ip'] = Yii::$app->request->getUserIP();
        $new_data['member_old_login_ip'] = $member['member_login_ip'];
        $new_data['member_token'] = $token;
        $model->updateMemberByCondition($new_data, ['member_id' => $member['member_id']]);


        $this->jsonRet->jsonOutput(0, '', ['member_token' => $token]);

    }

    /**
     * @return string|\yii\web\Response
     * 注册
     */
    public function actionRegister()
    {

        $post = SysHelper::postInputData();

        $model = new Member();
        $sms_model = new SmsCode();
        if (empty($post['member_mobile'])) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_NOT_NULL']['ERROR_MESSAGE']);
        }
        if ($model->getMemberInfo(['member_mobile' => $post['member_mobile']], 'member_mobile')) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_IS_EXIST']['ERROR_NO'], $this->errorRet['MOBILE_IS_EXIST']['ERROR_MESSAGE']);
        }
        if (empty($post['auth_code'])) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_MESSAGE']);
        }
        $auth_code = $sms_model->getSmsCodeInfo(['member_mobile' => $post['member_mobile']]);
        if ($auth_code['time'] < time()) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_MESSAGE']);
        }
        if ($auth_code['code'] != $post['auth_code']) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_MESSAGE']);
        }
        $token = md5(sha1($post['member_mobile'] . uniqid()));
        $data = [
            'member_username' => $post['member_mobile'],
            'member_name' => substr_replace($post['member_mobile'], '****', 3, 4),
            'member_mobile' => $post['member_mobile'],
            'member_mobile_bind' => 1,
            'member_password' => '123456',
            'member_token' => $token,
            'member_login_time' => time(),
        ];
        if ($model->insertMember($data)) {
            //奖励成长值/金币
            (new Member())->rewardMember($post['member_mobile'], 'register');
            $this->jsonRet->jsonOutput(0, '', ['token' => $token]);
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['REGISTER_FAIL']['ERROR_NO'], $this->errorRet['REGISTER_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * @return string
     * 找回密码
     */
    public function actionForgot()
    {

        $post = SysHelper::postInputData();
        $model = new Member();

        $sms_model = new SmsCode();
        if (empty($post['member_mobile'])) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_NOT_NULL']['ERROR_MESSAGE']);
        }
        $u_info = $model->getMemberInfo(['member_mobile' => $post['member_mobile']]);
        if (!$u_info) {
            $this->jsonRet->jsonOutput($this->errorRet['MEMBER_IS_EXIST']['ERROR_NO'], $this->errorRet['MEMBER_IS_EXIST']['ERROR_MESSAGE']);
        }
        if (empty($post['auth_code'])) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_MESSAGE']);
        }
        $auth_code = $sms_model->getSmsCodeInfo(['member_mobile' => $post['member_mobile']]);
        if ($auth_code['time'] < time()) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_MESSAGE']);
        }
        if ($auth_code['code'] != $post['auth_code']) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_NO'], $this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_MESSAGE']);
        }
        $member_password = trim($post['member_password']);
        $confirm_password = trim($post['confirm_password']);

        if (empty($member_password)) {
            $this->jsonRet->jsonOutput($this->errorRet['PASSWORD_NOT_NULL']['ERROR_NO'], $this->errorRet['PASSWORD_NOT_NULL']['ERROR_MESSAGE']);
        }
        if ($member_password != $confirm_password) {
            $this->jsonRet->jsonOutput($this->errorRet['PASSWORD_NOT_CONFIRM']['ERROR_NO'], $this->errorRet['PASSWORD_NOT_CONFIRM']['ERROR_MESSAGE']);
        }
        $update = $model->updateMember(['member_id' => $u_info['member_id'], 'member_password' => $member_password]);

        if ($update) {
            $this->jsonRet->jsonOutput(0, '密码修改成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['UPDATE_FAIL']['ERROR_NO'], $this->errorRet['UPDATE_FAIL']['ERROR_MESSAGE']);
        }

    }

    /**
     * 发送验证码
     */
    public function actionSendAuthCode()
    {
        $post = SysHelper::postInputData();
        //生成验证码
        $verify_code = rand(100, 999) . rand(100, 999);
        $sms_model = new Sms();
        $sms_code_model = new SmsCode();
        $type = isset($post['type']) ? $post['type'] : 'mobile';

        if ($type == 'mobile') {
            if (!$post['mobile']) {
                $this->jsonRet->jsonOutput($this->errorRet['MOBILE_NOT_NULL']['ERROR_NO'], $this->errorRet['MOBILE_NOT_NULL']['ERROR_MESSAGE']);
            } else {
                $res = $sms_code_model->getSmsCodeInfo(['member_mobile' => $post['mobile']]);
                $time = $res['time'] - time();
                if ($res && $time > 540) {
                    $this->jsonRet->jsonOutput($this->errorRet['AUTH_CODE_LIMIT']['ERROR_NO'], $this->errorRet['AUTH_CODE_LIMIT']['ERROR_MESSAGE']);
                }

            }
            $sms_code_model->insertSmsCodeByMobile($post['mobile'], $verify_code);
            //发送短信验证码
            $sms_model->sendSms($post['mobile'], $verify_code);
            $this->jsonRet->jsonOutput(0, '验证码已发送到您的手机，请注意查收！');
        }
        if ($type == 'email') {
            if ($post['email'] == '') {
                $this->jsonRet->jsonOutput($this->errorRet['EMAIL_IS_NULL']['ERROR_NO'], $this->errorRet['EMAIL_IS_NULL']['ERROR_MESSAGE']);
            } else {
                $res = $sms_code_model->getSmsCodeInfo(['email' => $post['email']]);
                $time = $res['time'] - time();
                if ($res && $time > 540) {
                    $this->jsonRet->jsonOutput($this->errorRet['AUTH_CODE_LIMIT']['ERROR_NO'], $this->errorRet['AUTH_CODE_LIMIT']['ERROR_MESSAGE']);
                }
            }
            $sms_code_model->insertSmsCodeByMobile($post['email'], $verify_code);
            //发送邮箱验证码
            $body = '您的邮箱验证码是：' . $verify_code;
            SysHelper::send_mail($post['email'], '邮箱验证通知', $body);
            $this->jsonRet->jsonOutput(0, '验证码已发送到您的邮箱，请注意查收！');
        }
    }

    /***
     * 跳转获取微信用户信息
     */
    public function actionGetUinfo()
    {
        $post = SysHelper::postInputData();
        $code = isset($post['code']) ? $post['code'] : '';
        if (!$code) {
            $this->jsonRet->jsonOutput(10000, '请传参数code');
        }
        $payment_code = 'wx_qrcode';

        $payinfo = (new Payment())->getPaymentInfo(['code' => $payment_code]);

        /* 取得支付信息，生成支付代码 */

        $alipay = new wx_qrcode($payinfo);

        $jsApi = new JsApi_pub();
        $jsApi->setCode($code);
        $openid = $jsApi->getOpenid();
        $this->jsonRet->jsonOutput(0, '', $openid);
    }

    /**
     * 获取微信config信息
     */
    public function actionGetWxConfig()
    {
        $payinfo = (new Payment())->getPaymentInfo(['code' => 'wx_qrcode']);

        $jssdk = new JSSDK($payinfo['appid'], $payinfo['appsecret']);
        $signPackage = $jssdk->GetSignPackage();


        $data = ['appid' => $signPackage['appId'], 'timestamp' => $signPackage['timestamp'], 'noncestr' => $signPackage['nonceStr'], 'signature' => $signPackage['signature']];

        $this->jsonRet->jsonOutput(0, '', $data);
    }


    /**
     * @return \yii\web\Response
     * 退出登录
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        $ref_url = Yii::$app->request->get('ref_url');
        if (empty($ref_url)) {
            $ref_url = Yii::$app->request->getReferrer();
        }
        return $this->redirect(Url::to(['site/login', 'ref_url' => urlencode($ref_url)]));
    }

    /**
     * 检查用户名是否存在
     */
    public function actionCheckMemberExist()
    {
        $post = SysHelper::postInputData();
        $result = (new Member())->getMemberInfo(['member_username' => $post['member_username']]);
        if ($result) {
            die(json_encode(true));
        } else {
            die(json_encode(false));
        }
    }

    /**
     * 检查用户名是否可用
     */
    public function actionCheckMemberName()
    {
        $post = SysHelper::postInputData();
        $result = (new Member())->getMemberInfo(['member_username' => $post['member_username']]);
        if ($result) {
            die(json_encode(false));
        } else {
            die(json_encode(true));
        }
    }

    /**
     * 检查邮箱是否可用
     */
    public function actionCheckMemberEmail()
    {
        $post = SysHelper::postInputData();
        $result = (new Member())->getMemberInfo(['member_email' => $post['member_email']]);
        if ($result) {
            die(json_encode(false));
        } else {
            die(json_encode(true));
        }
    }

    /**
     * 检查手机是否可用
     */
    public function actionCheckMemberMobile()
    {
        $post = SysHelper::postInputData();
        $result = (new Member())->getMemberInfo(['member_mobile' => $post['member_mobile']]);
        if ($result) {
            die(json_encode(false));
        } else {
            die(json_encode(true));
        }
    }

    /**
     * 检测图形验证码
     */
    public function actionCheckCaptcha()
    {
        $post = SysHelper::postInputData();
        if (!(new Captcha())->checkCaptcha($post['captcha'])) {
            die(json_encode(false));
        } else {
            die(json_encode(true));
        }
    }

    /**
     * @return string
     * 网站关闭页面
     */
//    public function actionSiteClose()
//    {
//        $this->layout = false;
//        $this->view->title = '网站关闭';
//        $close_reason = (new Setting())->getSettingInfo(['name' => 'close_reason']);
//
//        return $this->render('site_close', ['info' => $close_reason['value']]);
//    }

    /**
     * 地址下级
     */
    public function actionAreaChild()
    {
        $post = SysHelper::postInputData();
        $class_id = isset($post['class_id']) ? $post['class_id'] : 0;
        $model = new Area();
        $list = $model->getAreaList(['area_parent_id' => $class_id]);
        $this->jsonRet->jsonOutput(0, '', $list);
    }

    /**
     * 获取投诉类型
     */
    public function actionMemberComplaintType()
    {
        $type = [];
        $data = ComplainService::$type;
        foreach ($data as $k => $v) {
            $type[] = ['name' => $v, 'value' => $k];
        }
        $this->jsonRet->jsonOutput(0, '', $type);
    }
}
