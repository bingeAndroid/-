<?php
/**
 * Created by qbt.
 * User: lihz
 * Date: 2017/5/16
 * Time: 15:08
 */

namespace api\modules\v1\controllers;

use frontend\services\payment\WxPay\wx_qrcode;
use frontend\services\payment\WxPay\WxPayPubHelper\JsApi_pub;
use system\services\MemberAddress;
use system\services\MemberCoin;
use system\services\MemberCoupon;
use system\services\MemberGrow;
use system\services\MemberMail;
use system\services\Order;
use system\services\MemberFollow;
use system\services\Goods;
use system\services\MemberMessages;
use system\services\Payment;
use system\services\Setting;
use system\services\SmsCode;
use Yii;
use system\services\Member;
use system\services\Area;
use yii\helpers\Url;
use system\helpers\SysHelper;
use yii\web\Response;
use system\services\Captcha;
use yii\data\Pagination;
use yii\db\Expression;

class MemberController extends MemberBaseController
{

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->getMemberAndGradeInfo();
    }

    /**
     * 用户信息
     */
    public function actionMemberInfo()
    {
        //商品收藏数
        $good_follow_num = (new MemberFollow())->getMemberFollowCount(['member_id' => $this->member_info['member_id'], 'fav_type' => 'goods']);
        //店铺收藏数
        $store_follow_num = (new MemberFollow())->getMemberFollowCount(['member_id' => $this->member_info['member_id'], 'fav_type' => 'store']);
        //未使用优惠券数
        $condition = ['and'];
        $condition[] = ['{{%member_coupon}}.mc_member_id' => $this->member_info['member_id']];
        $condition[] = ['{{%member_coupon}}.mc_state' => 1];
        $condition[] = ['{{%coupon}}.coupon_state' => 1];
        $condition[] = ['{{%member_coupon}}.mc_use_time' => null];
        $coupons_num = (new MemberCoupon())->getMemberCouponCount($condition);
        //一周内足迹
        $footprint_num = 0;
        $footprint_class = (new MemberFollow())->getMemberFollowViewClass([
            'and',
            ['=','{{%view_rec}}.member_id',$this->member_info['member_id']],
            ['>=','browsetime',strtotime(date('Y-m-d 00:00:00', strtotime('-7 day', time())))]
        ]);
        foreach ($footprint_class['class_num'] as $v){
            $footprint_num +=intval($v);
        }

        $data = [
            'member_id' => $this->member_info['member_id'],
            'member_username' => $this->member_info['member_username'],
            'member_name' => $this->member_info['member_name'],
            'member_avatar' => SysHelper::getImage($this->member_info['member_avatar'], 0, 0, 0, [0, 0], 1),
            'member_sex' => $this->member_info['member_sex'],
            'member_email' => $this->member_info['member_email'],
            'member_email_bind' => $this->member_info['member_email_bind'],
            'member_mobile' => $this->member_info['member_mobile'],
            'member_mobile_bind' => $this->member_info['member_mobile_bind'],
            'member_login_time' => $this->member_info['member_login_time'],
            'member_old_login_time' => $this->member_info['member_old_login_time'],
            'member_points' => $this->member_info['member_points'],
            'member_grow_points' => $this->member_info['member_grow_points'],
            'available_predeposit' => $this->member_info['available_predeposit'],
            'freeze_predeposit' => $this->member_info['freeze_predeposit'],
            'member_bank_bind' => $this->member_info['member_bank_bind'],
            'member_bank_real_name' => $this->member_info['member_bank_real_name'],
            'member_bank_name' => $this->member_info['member_bank_name'],
            'member_bank_account' => $this->member_info['member_bank_account'],
            'member_bank_mobile' => $this->member_info['member_bank_mobile'],
            'member_state' => $this->member_info['member_state'],
            'member_sign_num' => $this->member_info['member_sign_num'],
            'member_sign_day' => $this->member_info['member_sign_day'],
            'member_token' => $this->member_info['member_token'],
            'good_follow_num' =>$good_follow_num,
            'store_follow_num' =>$store_follow_num,
            'footprint_num' =>$footprint_num,
            'coupons_num' =>$coupons_num
        ];
        $this->jsonRet->jsonOutput(0, '', $data);
    }

    /**
     * 签到
     */
    public function actionSign()
    {
        $member_model = new Member();
        if ($this->member_info['member_sign_day'] == date('Y-m-d')) {
            $this->jsonRet->jsonOutput($this->errorRet['SIGNED']['ERROR_NO'],$this->errorRet['SIGNED']['ERROR_MESSAGE']);
        }
        $time_c = strtotime(date('Y-m-d')) - strtotime($this->member_info['member_sign_day']);
        //没连续签到
        if ($time_c != 86400) {
            $data = [
                'member_id' => $this->member_info['member_id'],
                'member_sign_num' => 1,
                'member_sign_day' => date('Y-m-d')
            ];
        } else {
            $data = [
                'member_id' => $this->member_info['member_id'],
                'member_sign_num' => intval($this->member_info['member_sign_num']) + 1,
                'member_sign_day' => date('Y-m-d')
            ];
        }
        if ($member_model->updateMember($data)) {
            //奖励成长值/金币
            $member_model->rewardMember($this->member_info['member_username'],'login', $this->member_info['member_grow_points']);
        }
        $member_level = $member_model->getOneMemberLevel($this->member_info['member_grow_points']);
        $data = [
            'signday' => $this->member_info['member_sign_num'] + 1,
            'gold' => $member_level['level_gold_login'],
            'grow' => $member_level['level_grow_login']
        ];
        $this->jsonRet->jsonOutput(0, '', $data);
    }

    /**
     * @return string
     * 修改会员头像
     */
    public function actionMemberUpdate()
    {
        $post = SysHelper::postInputData();
        $type = isset($post['type']) ? $post['type'] : 'member_name';

        if ($type == 'avatar') {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $base64_image_content = $post['avatar'];
            if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                $file_type = $result[2];
                $file_path = Yii::getAlias('@uploads') . '/member/avatar/';
                if (!file_exists($file_path)) {
                    mkdir($file_path, 0700);
                }
                $new_file = $file_path . "avatar_" . $this->member_info['member_id'] . "." . $file_type;
                $ifp = fopen($new_file, "wb");
                fwrite($ifp, base64_decode(str_replace($result[1], '', $base64_image_content)));
                fclose($ifp);

                $avatar = str_replace(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . Yii::getAlias('@root_site')), '', str_replace('\\', '/', $new_file));
                (new Member())->updateMember(['member_id' => $this->member_info['member_id'], 'member_avatar' => $avatar]);

                $this->jsonRet->jsonOutput(0, '更改成功');
            }
        } elseif ($type == 'member_name') {
            $member_name = isset($post['member_name']) ? $post['member_name'] : '';
            if (empty($member_name)) {
                $this->jsonRet->jsonOutput($this->errorRet['MEMBER_NAME_NOT_NULL']['ERROR_NO'],$this->errorRet['MEMBER_NAME_NOT_NULL']['ERROR_MESSAGE']);
            }
            (new Member())->updateMember(['member_id' => $this->member_info['member_id'], 'member_name' => $member_name]);

            $this->jsonRet->jsonOutput(0, '更改成功');
        }

        $this->jsonRet->jsonOutput($this->errorRet['UPDATE_FAIL']['ERROR_NO'],$this->errorRet['UPDATE_FAIL']['ERROR_MESSAGE']);
    }
    /**
     * 修改登录密码
     */
    public function actionMemberSafeModifyPwd()
    {

        $post = SysHelper::postInputData();
        $member_password = isset($post['member_password'])?trim($post['member_password']):'';
        $confirm_password = isset($post['confirm_password'])?trim($post['confirm_password']):'';
        $mobile_auth_code = isset($post['auth_code'])?trim($post['auth_code']):'';
        $member_model = new Member();
        $sms_model = new SmsCode();
        if (empty($member_password)) {
            $this->jsonRet->jsonOutput($this->errorRet['PASSWORD_NOT_NULL']['ERROR_NO'],$this->errorRet['PASSWORD_NOT_NULL']['ERROR_MESSAGE']);
        }
        if ($member_password != $confirm_password) {
            $this->jsonRet->jsonOutput($this->errorRet['PASSWORD_NOT_CONFIRM']['ERROR_NO'],$this->errorRet['PASSWORD_NOT_CONFIRM']['ERROR_MESSAGE']);
        }

        if (empty($mobile_auth_code)) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_MESSAGE']);
        }
        $auth_code = $sms_model->getSmsCodeInfo(['member_mobile' => $this->member_info['member_mobile']]);
        if ($auth_code['time'] < time()) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_MESSAGE']);
        }
        if ($auth_code['code'] != $mobile_auth_code) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_MESSAGE']);
        }

        $update = $member_model->updateMember(['member_id' => $this->member_info['member_id'], 'member_password' => $member_password]);
        if ($update) {
            $this->jsonRet->jsonOutput(0, '密码修改成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['UPDATE_FAIL']['ERROR_NO'],$this->errorRet['UPDATE_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * @throws \yii\base\Exception
     * 设置支付密码
     */
    public function actionMemberSafeModifyPayword()
    {

        $post = SysHelper::postInputData();
        $model = new Member();

        $sms_model = new SmsCode();
       /* if (empty($post['member_mobile'])) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_NOT_NULL']['ERROR_NO'],$this->errorRet['MOBILE_NOT_NULL']['ERROR_MESSAGE']);
        }

        if ($this->member_info['member_mobile'] != $post['member_mobile']) {
            $this->jsonRet->jsonOutput($this->errorRet['BINDING_MOBILE_IS_ERROR']['ERROR_NO'],$this->errorRet['BINDING_MOBILE_IS_ERROR']['ERROR_MESSAGE']);
        }*/
        if (empty($post['auth_code'])) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_MESSAGE']);
        }
        $auth_code = $sms_model->getSmsCodeInfo(['member_mobile' => $this->member_info['member_mobile']]);
        if ($auth_code['time'] < time()) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_MESSAGE']);
        }
        if ($auth_code['code'] != $post['auth_code']) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_MESSAGE']);
        }
        $member_payword = trim($post['member_payword']);
        $confirm_payword = trim($post['confirm_payword']);

        if (empty($member_payword)) {
            $this->jsonRet->jsonOutput($this->errorRet['PAYWORD_NOT_NULL']['ERROR_NO'],$this->errorRet['PAYWORD_NOT_NULL']['ERROR_MESSAGE']);
        }
        if ($member_payword != $confirm_payword) {
            $this->jsonRet->jsonOutput($this->errorRet['PASSWORD_NOT_CONFIRM']['ERROR_NO'],$this->errorRet['PASSWORD_NOT_CONFIRM']['ERROR_MESSAGE']);
        }
        $update = $model->updateMember(['member_id' => $this->member_info['member_id'], 'member_payword' => Yii::$app->security->generatePasswordHash($member_payword)]);

        if ($update) {
            $this->jsonRet->jsonOutput(0, '支付密码设置成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['SETTING_FAIL']['ERROR_NO'],$this->errorRet['SETTING_FAIL']['ERROR_MESSAGE']);
        }

    }

    /**
     * 绑定/修改手机
     */
    public function actionMemberSafeModifyMobile()
    {
        $post = SysHelper::postInputData();
        $sms_model = new SmsCode();
        $member_mobile = isset($post['member_mobile'])?trim($post['member_mobile']):'';
        $mobile_auth_code = isset($post['auth_code'])?trim($post['auth_code']):'';
        if (empty($member_mobile)) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_NOT_NULL']['ERROR_NO'],$this->errorRet['MOBILE_NOT_NULL']['ERROR_MESSAGE']);
        }
        if (empty($mobile_auth_code)) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_NOT_NULL']['ERROR_MESSAGE']);
        }
        $auth_code = $sms_model->getSmsCodeInfo(['member_mobile' => $member_mobile]);
        if ($auth_code['time'] < time()) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_EXPIRE']['ERROR_MESSAGE']);
        }
        if ($auth_code['code'] != $mobile_auth_code) {
            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_NO'],$this->errorRet['MOBILE_AUTH_CODE_ERROR']['ERROR_MESSAGE']);
        }
        if ((new Member())->getMemberCount(['and', ['member_mobile' => $member_mobile], ['<>', 'member_id', $this->member_info['member_id']]]) > 0) {

            $this->jsonRet->jsonOutput($this->errorRet['MOBILE_IS_EXIST']['ERROR_NO'],$this->errorRet['MOBILE_IS_EXIST']['ERROR_MESSAGE']);
        }
        $update = (new Member())->updateMember(['member_id' => $this->member_info['member_id'], 'member_mobile' => $member_mobile, 'member_mobile_bind'=>1]);
        if ($update) {
            $this->jsonRet->jsonOutput(0, '手机绑定成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['BINDING_FAIL']['ERROR_NO'],$this->errorRet['BINDING_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * 绑定/修改银行卡
     */
    public function actionMemberSafeModifyBank()
    {

        $post = SysHelper::postInputData();
        $member_bank_real_name = trim($post['member_bank_real_name']);
        $member_bank_name = trim($post['member_bank_name']);
        $member_bank_account = trim($post['member_bank_account']);
        $member_bank_mobile = trim($post['member_bank_mobile']);
        $member_model = new Member();

        if (empty($member_bank_real_name) || empty($member_bank_name) || empty($member_bank_account) || empty($member_bank_mobile)) {
            $this->jsonRet->jsonOutput(100, '请完善信息');
        }
        $data['member_id'] = $this->member_info['member_id'];
        $data['member_bank_real_name'] = $member_bank_real_name;
        $data['member_bank_name'] = $member_bank_name;
        $data['member_bank_account'] = $member_bank_account;
        $data['member_bank_mobile'] = $member_bank_mobile;
        $data['member_bank_bind'] = 1;

        $update = $member_model->updateMember($data);
        if ($update) {
            $this->jsonRet->jsonOutput(0, '银行卡绑定成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['BINDING_FAIL']['ERROR_NO'],$this->errorRet['BINDING_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * @return string
     * 会员消息
     */
    public function actionMemberMessages()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):10;
        $page = isset($post['page'])?$post['page']:1;

        $messages_model = new MemberMail();
        $totalCount = $messages_model->getMailCount($this->member_info['member_id']);
        $list = $messages_model->getMailList($this->member_info['member_id'], $post['offset'], $post['limit']);

        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);

        $show = ['list'=>$list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);
    }

    /**
     * @return string
     * 会员消息详情
     */
    public function actionMemberMessagesView()
    {

        $model = new MemberMail();
        $post= SysHelper::postInputData();
        $messages_info = $model->getMailInfo(['AND',['OR',['mail_member_id' => $this->member_info['member_id']],['mail_member_id'=>'all']],['mail_id' => $post['mail_id']]]);
        if ($messages_info) {
            if(!$model->getMailDelInfo(['mail_id'=>$post['mail_id'],'member_id'=>$this->member_info['member_id']])){
                $model->insertMailDel(['mail_id'=>$post['mail_id'],'member_id'=>$this->member_info['member_id'],'mail_state'=>1]);
            }
            $this->jsonRet->jsonOutput(0,'',$messages_info);
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['MEMBER_MESSAGE_IS_NOT_EXIST']['ERROR_NO'],$this->errorRet['MEMBER_MESSAGE_IS_NOT_EXIST']['ERROR_MESSAGE']);
        }
    }
}