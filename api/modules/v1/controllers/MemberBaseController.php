<?php
/**
 * Created by qbt.
 * User: lihz
 * Date: 2017/5/16
 * Time: 15:11
 */

namespace api\modules\v1\controllers;

use api\services\JsonOutput;
use system\helpers\SysHelper;
use system\services\Member;
use system\services\MemberMail;
use system\services\MemberMessages;
use system\services\Setting;
use system\services\ShoppingCar;
use Yii;
use yii\helpers\ArrayHelper;

class MemberBaseController extends BaseController
{
    /**
     * @var array 会员信息
     */
    public $member_info = [];
    /* json */
    public $jsonRet;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        //默认ajax return
        $this->jsonRet = new JsonOutput();
    }

    /**
     * 统一处理action
     */
    public function actions()
    {
        return ArrayHelper::merge(parent::actions(), []);
    }

    /**
     * @param \yii\base\Action $action
     * @return bool
     * 执行控制器action之前
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action))
            return false;

        return true;
    }

    /**
     * @return array
     * 获取会员信息
     */
    protected function getMemberAndGradeInfo()
    {
        $post = SysHelper::postInputData();
        $model_member = new Member();
        //会员详情及会员级别处理

        $member_token = isset($post['member_token']) ? $post['member_token'] : '';
        //empty($member_token) and $this->jsonRet->jsonOutput($this->errorRet['TOKEN_ERROR']['ERROR_NO'], $this->errorRet['TOKEN_ERROR']['ERROR_MESSAGE']);
        $fields = 'member_id,member_username,member_name,member_avatar,member_sex,member_email,member_email_bind,
        member_mobile,member_mobile_bind,member_points,member_grow_points,available_predeposit,freeze_predeposit,
        member_bank_bind,member_bank_real_name,member_bank_name,member_bank_account,member_bank_mobile,
        member_sign_num,member_sign_day,member_token,member_state';
        //$member_info = $model_member->getMemberInfo(['member_token' => $member_token]);
        $member_info = $model_member->getMemberInfo(['member_id' => 120]);
        //time() - $member_info['member_login_time'] > 72000 and $this->jsonRet->jsonOutput($this->errorRet['TOKEN_ERROR']['ERROR_NO'], $this->errorRet['TOKEN_ERROR']['ERROR_MESSAGE']);
        if (!$member_info['member_state']) {
            $this->jsonRet->jsonOutput($this->errorRet['LOGIN_ERROR']['ERROR_NO'], $this->errorRet['LOGIN_ERROR']['ERROR_MESSAGE']);
        }
        if ($member_info) {
            $member_grade_info = $model_member->getOneMemberLevel(intval($member_info['member_grow_points']));
            $member_info = array_merge($member_info, $member_grade_info);
        }

        $this->member_info = $member_info;
    }
}