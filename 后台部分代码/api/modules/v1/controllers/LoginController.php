<?php

namespace api\modules\v1\controllers;
use PhpOffice\PhpSpreadsheet\Reader\Xls\MD5;
use system\services\BulkList;
use system\services\Goods;
use system\services\GuiGe;
use system\services\Member;
use system\services\Order;
use system\services\Bulk;
use system\services\BulkStart;
use system\services\MemberCoin;
use system\services\OrderGoods;
use yii\helpers\Url;
use system\helpers\SysHelper;
use system\services\Article;
use system\services\Coupon;
use system\services\MemberPredeposit;
use system\services\MemberCoupon;
use system\services\OrderReturn;
use system\services\SysLog;
use system\services\SmsCode;
use system\services\PanicBuyGoods;
use system\services\AreaGys;
use system\services\GoodsImage;
use system\services\GoodsClass;
use system\services\MemberAddress;
use system\services\Express;
use api\services\JsonOutput;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use system\services\Setting;

use Yii;

class LoginController extends BaseController
{
    public $jsonRet;



    /**微信登录
     * @Desc   打开小程序更新用户信息返回token
     * @Author develop41
     * @Email  qbtlixiang@qq.com
     * @param $code
     */
    public function actionWxRegister($code, $img = '', $name, $sex, $tjm = '', $ency = '', $iv = '')
    {
//        $this->jsonRet->jsonOutput(-3, '数据整修中，稍后登录');
        if ($tjm) {
            $id = substr($tjm, 3); //默认id前加3位字符串
            $member = (new Member())->getMemberInfo(['member_id' => $id]);
            $member1 = (new Member())->getMemberInfo(['tjm' => $tjm]);
            if (!$member && !$member1) {
                $this->jsonRet->jsonOutput(-3, '推荐码错误');
            }
        }
        $openid_result = $this->getOpenId($code);
        $user_info     = NULL;
        $openid_       = $openid_result['openid'];
        $session_key = $openid_result['session_key'];
        if (!$openid_) {
            $this->jsonRet->jsonOutput(-5, '获取openid失败');
        }
        $union_arr = $this->decryptData($ency, $iv, $session_key);
        $unionId = @$union_arr['unionId'];
        $user_info = (new Member())->getMemberInfo(['member_openid' => $openid_]);
        $user_info1 = $unionId ? (new Member())->getMemberInfo(['weixin_unionid' => $unionId]) : null;
        if ($user_info === NULL && !$user_info1) {
            $member_data['member_token']  = md5(time().rand(100,999));//token
            $member_data['member_openid']  = $openid_;
            $member_data['member_time']  = time();
            $member_data['member_avatar'] = $img;
            $member_data['member_sex'] = $sex;
            $member_data['member_name'] = $name;
            $member_data['member_login_time']  = time();
            $member_data['weixin_unionid'] = $unionId;
            $result = (new Member())->insertMember($member_data);
            if ($result) {
                if ($tjm) {
                    $user_info = (new Member())->getMemberInfo(['member_id' => $result]);
                    $tjm = $member1 ? 'YGH' . $member1['member_id'] : $tjm;
                    $this->bind_member($user_info, $tjm);
                }
                $this->jsonRet->jsonOutput(0, '注册成功', ['token' => $member_data['member_token'], 'is_bind_phone' => 0, 'session_key' => $session_key, 'openid_result' => $union_arr, 'unionId' => $unionId]);
            }
            $this->jsonRet->jsonOutput(-1, '注册失败');
        } else {
            $user_info = $user_info1 ?: $user_info;
            $is_bind_phone = $user_info['member_mobile'] ? 1 : 0;
            $this->bind_member($user_info, $tjm);
            $token_time = strtotime('+30 days', $user_info['member_login_time']);
            if ($token_time > time()) {#在有效期内
                $this->jsonRet->jsonOutput(0, '登录成功', ['token' => $user_info['member_token'], 'is_bind_phone' => $is_bind_phone, 'session_key' => $session_key, 'openid_result' => $union_arr, 'unionId' => $unionId]);
            } else {#过期重新生成token
                if (!$user_info['member_avatar']) {
                    $member_data['member_avatar'] = $img;
                }
                $token = md5($user_info['member_id'] . time());
                $member_data['member_token'] = $token;
                $member_data['member_login_time'] = time();
                $member_data['member_id'] = $user_info['member_id'];
                $member_data['weixin_unionid'] = $unionId;
                $member_data['member_openid'] = $openid_;

                $result = (new Member())->updateMember($member_data);
                if ($result !== false) {
                    $user_info['member_token'] = $token;
                    $this->jsonRet->jsonOutput(0, '登录成功', ['token' => $token, 'is_bind_phone' => $is_bind_phone, 'session_key' => $session_key, 'openid_result' => $union_arr, 'unionId' => $unionId]);
                }
                $this->jsonRet->jsonOutput(-1, '登录失败');
            }
        }
    }

    public function actionBindPhone($mobile)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if ($member_info['member_mobile']) {
            $this->jsonRet->jsonOutput(-2, '该微信已绑定手机号');
        }
        if (!$member_info['member_mobile']) {
            $password = substr($mobile, -6); //截取字符串后六位为密码
            $pwd = MD5($password);
            $result = (new Member())->updateMember(['member_mobile' => $mobile, 'member_id' => $member_info['member_id'], 'member_password' => $pwd]);
            if (!$result) {
                $this->jsonRet->jsonOutput(-1, '操作失败');
            }
        }
        $this->jsonRet->jsonOutput(0, '操作成功');
    }

    public function actionDecryptionPhone($sessionKey, $encryptedData, $iv)
    {
//        $appid = $this->appid;
        $data = $this->decryptData($encryptedData, $iv, $sessionKey);
        $this->jsonRet->jsonOutput(0, '操作成功', $data);
    }

    private function decryptData($encryptedData, $iv, $sessionKey)
    {
        $aesKey = base64_decode($sessionKey);
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $str = stripslashes($result);
        $dataarr = json_decode($str, true);
        return $dataarr;
    }

    /**微信**/
    /**
     * @Desc   获取小程序openid
     * @Author develop41
     * @Email  qbtlixiang@qq.com
     * @param      $code
     * @param null $app_id
     * @param null $secret
     * @return bool|mixed
     */
    function getOpenId($code)
    {
        $app_id = $this->appid;
        $secret = $this->secret;
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $app_id . '&secret=' . $secret . '&js_code=' . $code . '&grant_type=authorization_code';
        $result = file_get_contents($url);
        $result = json_decode($result,true);
        if ($result['openid']) {
            $result['code'] = $code;
            return $result;
        }
        $this->jsonRet->jsonOutput(-500, '无效code', $result);
    }

    /**发送验证码
     * @param $mobile
     */
    public function actionSendSmsCode($mobile, $type = 1)
    {
        $now_time = time();
        $sms_condition = ['and'];
        $sms_condition[] = ['member_mobile'=>$mobile];
        $sms_condition[] = ['type'=>0];
        $sms_condition[] = ['>=', 'time', $now_time-10*60];
        $sms_info = (new SmsCode())->getSmsCodeInfo($sms_condition);
        if ($sms_info) {
            //短信有效期内再次发送 则直接查询数据库
            $time = $sms_info['time'] + 10 * 60 - $now_time;
            $this->jsonRet->jsonOutput(0, '发送成功,验证码十分钟内有效', ['code' => $sms_info['code'], 'time' => $time]);
        }
        $code = rand(100000, 999999);
        //发送验证2019-05-15 暂未写

        $result = (new SmsCode())->insertSmsCodeByMobile($mobile,$code);
        if ($result){
            $arr = ['', 'SMS_167345064', 'SMS_167345063', 'SMS_168340390'];
            $this->sendSms($mobile, $arr[$type], $code);
            $this->jsonRet->jsonOutput(0, '发送成功,验证码十分钟内有效',['code'=>$code,'time'=>10*60]);
        }
        $this->jsonRet->jsonOutput(-1, '操作失败');
    }

    /**手机号码注册
     * @param $mobile 手机号
     * @param $code 验证码
     * @param $password 密码
     */
    public function actionMobileRegister($wxcode, $mobile, $code, $password = '', $tjm = '')
    {
        if (trim($tjm)) {
            $id = substr($tjm, 3); //默认id前加3位字符串
            $member = (new Member())->getMemberInfo(['member_id' => $id]);
            $member1 = (new Member())->getMemberInfo(['tjm' => $tjm]);
            if (!$member && !$member1) {
                $this->jsonRet->jsonOutput(-3, '推荐码错误');
            }
        }
        $password = trim($password);
        if (!$password) {
            $this->jsonRet->jsonOutput(-4, '请输入密码');
        }
        if ((new Member())->getMemberInfo(['member_mobile'=>$mobile])){
            $this->jsonRet->jsonOutput(-2, '该手机号已注册');
        }
        $openid_result = $this->getOpenId($wxcode);

        $sms_condition = ['and'];
        $sms_condition[] = ['member_mobile'=>$mobile];
        $sms_condition[] = ['type'=>0];
        $sms_condition[] = ['code'=>$code];
        $sms_condition[] = ['>=', 'time', time()-10*60];
        $sms_info = (new SmsCode())->getSmsCodeInfo($sms_condition);
        if (!$sms_info){
            $this->jsonRet->jsonOutput(-3, '验证码错误或已过期');
        }else{
            (new SmsCode())->updateSmsCode(['id'=>$sms_info['id'],'type'=>1]); //标记验证码已使用
        }
        $member_data['member_token']     = md5(time().rand(100,999));
        $member_data['member_mobile'] = $mobile;
        $member_data['member_password'] = MD5($password);
        $member_data['member_login_time']      = time();
        $member_data['member_time'] = time();
        $user_info = NULL;
        $openid_ = $openid_result['openid'];
        if (!$openid_) {
            $this->jsonRet->jsonOutput(-5, '获取openid失败');
        }
        $user_info = (new Member())->getMemberInfo(['member_openid' => $openid_]);
        if ($user_info) {
            $member_data['member_id'] = $user_info['member_id'];
            $result = (new Member())->updateMember($member_data);
        } elseif ($user_info['member_mobile']) {
            $this->jsonRet->jsonOutput(-4, '该微信已注册手机号');
        } else {
            $member_data['member_openid'] = $openid_;
            $member_data['member_name'] = 'YGH' . rand(100, 999); //手机注册默认昵称
            $result = (new Member())->insertMember($member_data);
            $user_info = (new Member())->getMemberInfo(['member_id' => $result]);
        }
        if ($result) {
            if ($tjm) {
                $tjm = $member1 ? 'YGH' . $member1['member_id'] : $tjm;
                $this->bind_member($user_info, $tjm);
            }
            $this->jsonRet->jsonOutput(0, '注册成功',['token'=>$member_data['member_token']]);
        }
        $this->jsonRet->jsonOutput(-1, '注册失败');
    }

    /**手机号码登录
     * @param $mobile 手机号码
     * @param $password 密码
     */
    public function actionMobileLogin($mobile, $wxcode, $password = '')
    {
        $password = trim($password);
        if (!$password) {
            $this->jsonRet->jsonOutput(-4, '请输入密码');
        }
        $password = MD5($password);
        $member_condition = ['and'];
        $member_condition[] = ['member_state'=>1];
        $member_condition[] = ['member_mobile'=>$mobile];
        $member_condition[] = ['member_password'=>$password];
        $member_info = (new Member())->getMemberInfo($member_condition);
        
        if (!$member_info){
            $this->jsonRet->jsonOutput(-1, '账号或密码错误');
        }
        $member_data['member_token']     = md5(time().rand(100,999));
        $member_data['member_login_time']      = time();
        $member_data['member_id']      = $member_info['member_id'];
        if (!$member_info['member_openid']) {
            $openid_result = $this->getOpenId($wxcode);
            $openid_ = $openid_result['openid'];
            $member_data['member_openid'] = $openid_;
        }
        (new Member())->updateMember($member_data);
        $this->jsonRet->jsonOutput(0, '登录成功',['token'=>$member_data['member_token']]);
    }

    /**忘记密码
     * @param $mobile
     * @param $code
     * @param $new_password
     */
    public function actionUpdatePassword($mobile,$code,$new_password){
        $sms_condition = ['and'];
        $sms_condition[] = ['member_mobile'=>$mobile];
        $sms_condition[] = ['type'=>0];
        $sms_condition[] = ['code'=>$code];
        $sms_condition[] = ['>=', 'time', time()-10*60];
        $member_info = (new Member())->getMemberInfo(['member_mobile'=>$mobile]);
        if (!$member_info){
            $this->jsonRet->jsonOutput(-2, '用户不存在');
        }
        $sms_info = (new SmsCode())->getSmsCodeInfo($sms_condition);
        if (!$sms_info){
            $this->jsonRet->jsonOutput(-3, '验证码错误或已过期');
        }else{
            (new SmsCode())->updateSmsCode(['id'=>$sms_info['id'],'type'=>1]); //标记验证码已使用
        }
        $member_data['member_password']  = MD5($new_password);
        $member_data['member_id']  = $member_info['member_id'];
        $result = (new Member())->updateMember($member_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        }
        $this->jsonRet->jsonOutput(-1, '操作失败');
    }


//    /**订单支付回调
//     * @param $order_id
//     */
//    public function actionSetOrderState($order_id){
//        $member_info = $this->user_info;
//        (new Order())->updateOrder(['order_id'=>$order_id,'order_state'=>30]);
//        $order_info = (new Order())->getOrderOne(['order_id'=>$order_id]);
//        if ($member_info['member_recommended_no']){
//            $memberinfo = (new Member())->getMemberInfo(['member_id'=>$member_info['member_recommended_no']]);
//            //添加佣金到分销商余额
//            $available_predeposit = $memberinfo['available_predeposit']+$order_info['commission_amount'];
//            (new Member())->updateMember(['member_id'=>$memberinfo['member_id'],'available_predeposit'=>$available_predeposit]);
//            $predeposit_data['member_id'] = $memberinfo['member_id'];
//            $predeposit_data['predeposit_member_name'] = $memberinfo['member_name'];
//            $predeposit_data['predeposit_type'] = 'brokerage';
//            $predeposit_data['predeposit_av_amount'] = $order_info['commission_amount'];
//            $predeposit_data['predeposit_add_time'] = time();
//            $predeposit_data['order_id'] = $order_id;
//            (new MemberPredeposit())->insertMemberChange($predeposit_data);
//        }
//
//    }


    /*********************************发送短信*****************/
    /**
     * 阿里函数：生成签名并发起请求
     * @param $accessKeyId string AccessKeyId (https://ak-console.aliyun.com/)
     * @param $accessKeySecret string AccessKeySecret
     * @param $domain string API接口所在域名
     * @param $params array API具体参数
     * @param $security boolean 使用https
     * @return bool|\stdClass 返回API接口调用结果，当发生错误时返回false
     */
    function request($accessKeyId, $accessKeySecret, $domain, $params, $security = false)
    {
        $apiParams = array_merge(array(
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid(mt_rand(0, 0xffff), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $accessKeyId,
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);
        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
        }
        $stringToSign = "GET&%2F&" . $this->encode(substr($sortedQueryStringTmp, 1));
        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&", true));
        $signature = $this->encode($sign);
        $url = ($security ? 'https' : 'http') . "://{$domain}/?Signature={$signature}{$sortedQueryStringTmp}";
        try {
            $content = $this->fetchContent($url);
            return json_decode($content);
        } catch (\Exception $e) {
            return false;
        }
    }

    //阿里函数
    private function encode($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

    //阿里函数
    private function fetchContent($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "x-sdk-client" => "php/2.0.0"
        ));
        if (substr($url, 0, 5) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $rtn = curl_exec($ch);
        if ($rtn === false) {
            trigger_error("[CURL_" . curl_errno($ch) . "]: " . curl_error($ch), E_USER_ERROR);
        }
        curl_close($ch);
        return $rtn;
    }

    /**
     * 调用函数：只需调用此函数即可
     * @param $phoneNum string 接收短信号码
     * @param $tempCode string 短信模板Code
     * @param $temp_param string 短信模板中字段的值，如 验证码
     */
    private function sendSms($phoneNum, $tempCode, $temp_param)
    {
        $params = array();

        //*** 配置部分开始 ***
        $params["SignName"] = "游购会";
        $accessKeyId = "LTAIkmT8kx8kGilT";
        $accessKeySecret = "FKducg1NDh1u5KE4wg043X3B5nDP3S";
        //*** 配置部分结束 ***

        // fixme 必填: 短信接收号码
        $params["PhoneNumbers"] = $phoneNum;
        $params["TemplateCode"] = $tempCode;
        //参数
        $params['TemplateParam'] = '{"code":"' . $temp_param . '"}';
        // 此处可能会抛出异常，注意catch
        $content = $this->request(
            $accessKeyId,
            $accessKeySecret,
            "dysmsapi.aliyuncs.com",
            array_merge($params, array(
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25",
            ))
        );
        return $content;
    }





    /**********************************测试接口**************************************************************/


    //商品列表
//    public function actionGetGoodsList()
//    {
//        $config = $this->getconfig();
//        $url = $this->gethttp() . '/api/v2/goods/searchingGoods?';
//        $params['memberId'] = $config['memberId'];
//        $params['pageSize'] = 3;
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        echo $json;
//    }


    //获取商品详情
//    public function actionGoodsDetail($goodsId)
//    {
//        $config = $this->getconfig();
//        $url = $this->gethttp() . '/api/v2/goods/getGoodsInfo?';
//        $params['goodsId'] = $goodsId;
//        $params['memberId'] = $config['memberId'];
//
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        return $json;
//    }

    /*
     * 获取商品规格
     */
//    public function actionGoodsGuige($goodsId)
//    {
//        $config = $this->getconfig();
//        $url = $this->gethttp() . '/api/v2/goods/getGoodsSpecs?';
//        $params['goodsId'] = $goodsId;
//        $params['memberId'] = $config['memberId'];
//        $params['accountId'] = $config['accountId'];
//        $params['token'] = $config['token'];
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        echo $json;
//    }

//    /*
//         * 获取商品动态价格规格
//         */
//    public function actionGoodsPrice($goodsSn = '74f4c96da48d4dcd9c4d883b6bea1e5c', $num = 1, $regionId = '420100')
//    {
//        $config = $this->getconfig();
//        $url = $this->gethttp() . '/api/v2/goods/getGoodsPrice?';
//        $params['goodsId'] = $goodsSn;
//        $params['num'] = $num;
//        $params['productNum'] = 1;
//        $params['cityId'] = $regionId;
//        $params['memberId'] = $config['memberId'];
//        $params['accountId'] = $config['accountId'];
//        $params['token'] = $config['token'];
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        echo $json;
//    }



    /**查询供应商物流
     * @return mixed
     */
//    public function actionGetwl($orderSn, $dlyCode, $shipNo)
//    {
//        $config = $this->getconfig();
//        $url = $this->gethttp() . 'api/v2/order/orderKuaidi?';
//        $params['orderSn'] = $orderSn;
//        $params['dlyCode'] = $dlyCode;
//        $params['shipNo'] = $shipNo;
//        $params['memberId'] = $config['memberId'];
//        $params['accountId'] = $config['accountId'];
//        $params['token'] = $config['token'];
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        $arr = json_decode($json, true);
//        if ($arr['result'] == 1) {
//            return $arr['data'];
//        } elseif ($arr['result'] == 0 && $arr['code'] == 106) {
//            $this->login();
//            $this->actionGetwl($orderSn, $dlyCode, $shipNo);
//        }
//    }


//    /**
//     * 创建供应商订单
//     */
//    public function actionCreateOrder()
//    {
//        $order_list = (new Order())->getOrderList(['order_type' => 2]);
//        foreach ($order_list as $k => $v) {
//            $this->add_order($v['order_id']);
//        }
//    }
//
//    private function add_order($order_id)
//    {
//        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
//        if ($order_info && $order_info['order_type'] == 2 && $order_info['extend_order_goods'][0]) {
//            $order_goods_info = $order_info['extend_order_goods'][0];
//            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $order_goods_info['order_goods_id']]);
//            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $order_goods_info['order_goods_spec_id']]);
//            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $order_info['ma_id']]);
//            $params['area'] = $addr_info['regionId']; //区域编码
//            $params['nums'] = $order_goods_info['order_goods_num']; //数量
//            $params['name'] = urlencode($order_info['ma_true_name']); //用户名称
//            $params['productNums'] = $guige_info['productNum']; //规格数量
//            $params['mobile'] = $order_info['ma_phone']; //手机
//            $params['address'] = urlencode($order_info['buyer_address']); //详细地址
//            $params['customOrder'] = urlencode($order_info['order_sn']); //第三方订单号
//            $params['remarks'] = urlencode($order_info['buyer_message']); //买家备注
//            $params['identification'] = $addr_info['ma_card_no']; //身份证号码
//            if ($goods_info['tradeType'] == 1101) {
//                //保税商品暂不考虑 2019-06-16 14:38
//                $order_data['thirdBuyAmount'] = $order_info['regionId']; //代购价格 保税商品必填，非保税商品不需要填写
//            }
//            $params['goodsIds'] = $goods_info['goodsId']; //商品id编码
//
//            $config = $this->getconfig();
//            $url = $this->gethttp() . '/api/v2/order/createOrders?';
//            $params['memberId'] = $config['memberId'];
//            $params['accountId'] = $config['accountId'];
//            $params['token'] = $config['token'];
//            $str = $this->haidaiSign($params);
//            $url .= $str;
//            $json = file_get_contents($url);
//            $arr = json_decode($json, true);
//            if ($arr['result'] == 1) {
//                //创建订单成功
//                (new Order())->updateOrder(['order_id' => $order_id, 'order_type' => 3]);
//            } elseif ($arr['result'] == 0 && $arr['code'] == 106) {
//                $this->login();
//                $this->add_order($order_id);
//            }
//        }
//
//    }

//    public function actionAa()
//    {
//        $params['area'] = '420102'; //区域编码
//        $params['nums'] = 1; //数量
//        $params['name'] = 'xiaowang'; //用户名称
//        $params['productNums'] = 1; //规格数量
//        $params['mobile'] = '18694060590'; //手机
//        $params['address'] = urlencode('江湖救急'); //详细地址
//        $params['customOrder'] = 'wwrwer' . time(); //第三方订单号
//        $params['goodsIds'] = '30062573016c4a3096c51c6b75f185cc'; //商品id编码
//        $params['identification'] = '421125199005057034'; //身份证号码
//
//
//        $config = $this->getconfig();
//        $url = $this->gethttp() . '/api/v2/order/createOrders?';
//        $params['memberId'] = $config['memberId'];
//        $params['accountId'] = $config['accountId'];
//        $params['token'] = $config['token'];
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        $arr = json_decode($json, true);
//        echo "<pre>";
//        print_r($arr);
//        echo "<pre>";
//        die;
//    }


    /*
     * 添加海带地区信息到数据库
     */
//    public function actionGetArea()
//    {
//        $config = $this->getconfig();
//        $url = 'http://img.pre.seatent.com/statics/json/regionsAll.json?';
//        $params['memberId'] = $config['memberId'];
//        $params['accountId'] = $config['accountId'];
//        $params['token'] = $config['token'];
//        $str = $this->haidaiSign($params);
//        $url .= $str;
//        $json = file_get_contents($url);
//        $arr = json_decode($json, true);
//        $this->addarea($arr);
//    }
//
//    private function addarea($arr, $pid = 0)
//    {
//        set_time_limit(0);
//        foreach ($arr as $k => $v) {
//            $data['pid'] = 0;
//            $data['regionGrade'] = $v['regionGrade'];
//            $data['regionId'] = $v['regionId'];
//            $data['regionName'] = $v['regionName'];
//            $data['areaId'] = $v['areaId'];
//            $info = (new AreaGys)->getAreaGysInfo(['regionId' => $v['regionId']]);
//            if ($info) {
//                $data['id'] = $info['id'];
//                $pid = (new AreaGys)->updateAreaGys($data);
//            } else {
//                $pid = (new AreaGys)->insertAreaGys($data);
//            }
//            foreach ($v['children'] as $k1 => $v1) {
//                $data1['pid'] = $pid;
//                $data1['regionGrade'] = $v1['regionGrade'];
//                $data1['regionId'] = $v1['regionId'];
//                $data1['regionName'] = $v1['regionName'];
//                $data1['areaId'] = $v1['areaId'];
//                $info1 = (new AreaGys)->getAreaGysInfo(['regionId' => $v1['regionId']]);
//                if ($info1) {
//                    $data1['id'] = $info1['id'];
//                    $pid1 = (new AreaGys)->updateAreaGys($data1);
//                } else {
//                    $pid1 = (new AreaGys)->insertAreaGys($data1);
//                }
//                foreach ($v1['children'] as $k2 => $v2) {
//                    $data2['pid'] = $pid1;
//                    $data2['regionGrade'] = $v2['regionGrade'];
//                    $data2['regionId'] = $v2['regionId'];
//                    $data2['regionName'] = $v2['regionName'];
//                    $data2['areaId'] = $v2['areaId'];
//                    $info2 = (new AreaGys)->getAreaGysInfo(['regionId' => $v2['regionId']]);
//                    if ($info2) {
//                        $data2['id'] = $info2['id'];
//                        (new AreaGys)->updateAreaGys($data2);
//                    } else {
//                        (new AreaGys)->insertAreaGys($data2);
//                    }
//                }
//            }
//        }
//    }
//
//
//    public function actionBb()
//    {
//        set_time_limit(0);
//        $list = (new AreaGys())->getAreaGysList("id>15050");
//        foreach ($list as $k => $v) {
//            if ($v['id'] > 15050) {
//                $info = (new AreaGys())->getAreaGysInfo(['id' => $v['pid']]);
//                $area_path = $info['id'] . ',' . $v['pid'] . ',' . $v['id'];
//                (new AreaGys())->updateAreaGys(['id' => $v['id'], 'area_path' => $area_path]);
//            }
//        }
//
//    }


    /**测试订单退款 操作已完成订单 正式去掉
     * @param $order_id
     * @param $code
     * @throws \WxPayException
     */
//    public function actionTk()
//    {
//        $get = Yii::$app->request->get();
//        $refund_info = (new OrderReturn())->getOrderReturnInfo(['refund_sn' => $get['refund_sn']]);
//        $result = $this->pay($refund_info['refund_id'], $get['refund_amount']);
//        if ($result['return_code'] == 'SUCCESS' && $result['return_msg'] == 'OK') {
//            $this->jsonRet->jsonOutput(0, '退款成功');
//        }
//    }

//    public function pay($refund_id, $refund_fee)
//    {
//        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
//        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.JsApiPay.php");
//        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.Config.php");
//        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
//        $refund_info = (new OrderReturn())->getOrderReturnInfo(['refund_id' => $refund_id]);
//        $info = (new Order())->getOrderOne(['order_id' => $refund_info['order_id'], 'order_state' => 60]);
//        if (!$info) {
//            $this->jsonRet->jsonOutput(-1, '订单不存在');
//        }
//        $info1 = (new Order())->getOrderOne(['order_id' => $info['pid']]);
//
//        $wxpay = new \WxPayApi();
//        $input = new \WxPayRefund();
//        $input->SetOut_trade_no($info1['order_sn']);
//        $input->SetTotal_fee($info1['pd_amount'] * 100);
//        $input->SetRefund_fee($refund_fee * 100);
//        $config = new \WxPayConfig();
//        $input->SetOut_refund_no($refund_info['refund_sn']);
//        $input->SetOp_user_id($config->GetMerchantId());
//        $order = $wxpay->refund($config, $input);
//        return $order;
//    }

//    //超过三天未发货发送短信提醒
//    public function actionSendOrderSms()
//    {
//        set_time_limit(0);//无限请求超时时间
//        $time = 3600 * 24 * 3;
//        $time = time() - 60;
//        $order_list = (new Order)->getOrderList(['and', ['order_state' => 30], ['refund_state' => 1], ['type' => 1], ['is_code' => 0], ['<=', 'payment_time', $time]]);
//        foreach ($order_list as $k => $v) {
//            $member_info = (new Member())->getMemberInfo(['member_id' => $v['buyer_id']]);
//            if ($member_info['member_mobile']) {
//                $this->sendSms($member_info['member_mobile'], 'SMS_167345064', 123);
//                (new Order)->updateOrder(['order_id' => $v['order_id'], 'is_code' => 1]);
//            }
//        }
//        sleep(60);
//        $this->actionSendOrderSms();
//    }

    public function actionWxyz()
    {
        $token = 'yogoclub2016token';
        $timestamp = @$_GET['timestamp'];
        $signature = @$_GET['signature'];
        $nonce = @$_GET['nonce'];
        $echostr = @$_GET['echostr'];
        $array = @array($token, $timestamp, $nonce);
        sort($array);
        $str = implode($array);
        if (sha1($str) == $signature && $echostr) {
            // 首次验证
            echo $echostr;
        } else {
            // 已验证
            $this->res();
        }
    }

    private function res()
    {
        // 接收微信服务器推送的消息
        $postArr = @file_get_contents('php://input');
        $postObj = simplexml_load_string($postArr);
        //用户关注
        if (strtolower($postObj->Event) == 'subscribe') {
            // 推送关注词给用户
            $tjm = substr($postObj->EventKey, strpos($postObj->EventKey, 'qrscene_1') + 8);
            $gztztp = (new Setting())->getSetting('gztztp');
            $gztztp = SysHelper::getImage($gztztp, 0, 0, 0, [0, 0], 1);
            $gztzbt = (new Setting())->getSetting('gztzbt');
            $gztzms = (new Setting())->getSetting('gztzms');
            $url = Url::to(['login/tg', 'tjm' => 'YGH' . $tjm], true);
            $this->re_news($postObj, $url, $gztztp, $gztzbt, $gztzms);
        }
        if (strtolower($postObj->Event) == 'scan') {
            $tjm = $postObj->EventKey;
            //供应商老推荐码
            $member_info = (new Member())->getMemberInfo(['tjm'=>$tjm]);
            $tjm = $member_info ? 'YGH' . $member_info['member_id'] : $tjm;
//            $gztztp = (new Setting())->getSetting('gztztp');
//            $gztztp = SysHelper::getImage($gztztp, 0, 0, 0, [0, 0], 1);
            $gztzbt = (new Setting())->getSetting('gztzbt');
//            $gztzms = (new Setting())->getSetting('gztzms');
//            $url = Url::to(['login/tg', 'tjm' => 'YGH' . $tjm], true);
//            $this->re_news($postObj, $url, $gztztp, $gztzbt, $gztzms);
            $arr = (new Setting())->getSettingInfo(['name' => 'gztztp']);
            $thumb_media_id = $arr['renark'];
            $this->re_miniprogrampage($postObj, $gztzbt, $thumb_media_id, $tjm);
        }
    }

    public function actionTg()
    {
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $tjm = $_GET['tjm'];
        $url = $http_type . $_SERVER['SERVER_NAME'] . '/yghsc/api/web/index.php/v1/login/wx-register?tjm=' . $tjm;
        $this->layout = false;
        return $this->render('tg', ['url' => $url]);
    }

    private function re_news($postObj, $url, $gztztp, $gztzbt, $gztzms)
    {
        $toUser = $postObj->FromUserName;
        $fromUser = $postObj->ToUserName;
        $template = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[%s]]></MsgType>
                    <ArticleCount>2</ArticleCount>
                    <Articles>
                        <item>
                        <Title><![CDATA[%s]]></Title> 
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        <Url><![CDATA[%s]]></Url>
                        </item>
                        <item>
                        <Title><![CDATA[%s]]></Title> 
                        <Description><![CDATA[%s]]></Description>
                        <PicUrl><![CDATA[%s]]></PicUrl>
                        <Url><![CDATA[%s]]></Url>
                        </item>
                    </Articles>
                    </xml>";
        echo sprintf($template, $toUser, $fromUser, time(), 'news', $gztzbt, '', $gztztp, $url, $gztzms, '', '', $url);
    }

    /**
     * @param string $postObj 发送给谁
     * @param string $gztzbt 标题
     * @param string $thumb_media_id 小程序卡片图片素材id
     * @param string $tjm 自定义参数
     */
    public function re_miniprogrampage($postObj = '', $gztzbt = '', $thumb_media_id = '', $tjm = '')
    {
        $toUser = $postObj->FromUserName;
        $token = $this->get_wx_accesss_token();
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=$token";
        $data = '{
                    "touser":"' . $toUser . '",
                    "msgtype":"miniprogrampage",
                    "miniprogrampage":
                    {
                        "title":"' . $gztzbt . '",
                        "appid":"wx1fb0fc15950650fb",
                        "pagepath":"pages/login_way/login_way?tjm="' . $tjm . ',
                        "thumb_media_id":"' . $thumb_media_id . '"
                    }
                }';
        $this->curl($url, $data);
    }

    private function re_text($postObj, $content)
    {
        $toUser = $postObj->FromUserName;
        $fromUser = $postObj->ToUserName;
        $template = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[%s]]></MsgType>
                    <Content><![CDATA[%s]]></Content>
                    </xml>";
        echo sprintf($template, $toUser, $fromUser, time(), 'text', $content);
    }








    /**********************************自动更新供应商接口**************************************************************/

    //超过三天未发货发送短信提醒
    public function actionSendOrderSms()
    {
        $day = (new Setting())->getSetting('dayfah');
        $time = 3600 * 24 * $day;
        $time = time() - $time;
        $order_list = (new Order)->getOrderList(['and', ['order_state' => 30], ['refund_state' => 1], ['type' => 1], ['is_code' => 0], ['<=', 'payment_time', $time]]);
        foreach ($order_list as $k => $v) {
            $member_info = (new Member())->getMemberInfo(['member_id' => $v['buyer_id']]);
            if ($member_info['member_mobile']) {
                $goods_name = substr($v['goods_list'][0], 0, 10);
                if ($v['order_type'] == 1) {
                    $this->send_member_msg1('【游购会】亲，您的购买的“【' . $goods_name . '】...”仓库正在紧锣密鼓的备货中，预计未来2-3天就能发出，请您再耐心等待一下喔，更多信息可咨询游购会客服。');
                } else {
                    $this->send_member_msg1('【游购会】亲，您的购买的“【' . $goods_name . '】...”正在海关审核中，预计未来2-3天就能发出，请您再耐心等待一下喔，更多信息可咨询游购会客服。');
                }
                $this->sendSms($member_info['member_mobile'], 'SMS_167345064', 123);
                (new Order)->updateOrder(['order_id' => $v['order_id'], 'is_code' => 1]);
            }
        }
    }

    /**
     * 更新商品
     */
    public function actionUpGoods()
    {
        $goods_list = (new Goods())->getGoodsList(['goods_type' => 3]);
        foreach ($goods_list as $k => $v) {
            $this->update_goods($v['goods_sku']);
        }
    }

    public function update_goods($goods_sku)
    {
        $config = $this->getconfig();
        $url = $this->gethttp() . '/api/v2/goods/getGoodsInfo?';
        $params['goodsSn'] = $goods_sku;
        $params['memberId'] = $config['memberId'];
        $str = $this->haidaiSign($params);
        $url .= $str;
        $json = file_get_contents($url);
        $arr = json_decode($json, true);
        if ($arr['result'] == 1) {
            $info = $arr['data'];
            $goods_info = (new Goods())->getGoodsInfo(['goods_sku' => $goods_sku]);
            $goods_data['goods_name'] = $info['name'];
            $goods_data['goods_market_price'] = $info['price'];
            $goods_data['goods_pic'] = $info['thumbnail'];
            $goods_data['tradeType'] = $info['tradeType'];
            $goods_data['goods_sku'] = $info['sn'];
            $goods_data['goods_mobile_content'] = $info['intro'];
            //$goods_data['goods_price'] = $info['price']; //不更新商品售价
            (new Goods())->updateGoodsByCondition($goods_data, ['goods_sku' => $goods_sku]);

            //附图
            $imgarr = array(
                'goods_pic1' => isset($info['images'][0]) ? $info['images'][0] : '',
                'goods_pic2' => isset($info['images'][1]) ? $info['images'][1] : '',
                'goods_pic3' => isset($info['images'][2]) ? $info['images'][2] : '',
                'goods_pic4' => isset($info['images'][3]) ? $info['images'][3] : '',
            );

            (new GoodsImage())->updateGoodsImageByCondition($imgarr, ['goods_id' => $goods_info['goods_id']]);

            $guige_arr = $this->get_guige($goods_sku);

            $goods_stock = $info['enableStore'];
            $default_guige_id = 0;
            $guige_id_arr = false;
            foreach ($guige_arr as $k => $v) {
                $guige_data['goods_id'] = $goods_info['goods_id'];
                $guige_data['guige_name'] = $v['productName'];
                //$guige_data['guige_price'] = $v['price'];//不更新商品售价
                $guige_data['cost_price'] = $v['price'];
                $guige_data['guige_sort'] = 0;
                $guige_data['guige_no'] = $info['sn'];
                $guige_data['productNum'] = $v['productNum'];
                $guige_data['tax'] = $v['tax'];
                $guige_data['guige_num'] = $v['enableStore'];
                $guige_info = (new GuiGe())->getGuiGeInfo(['productNum' => $v['productNum'], 'goods_id' => $goods_info['goods_id']]);
                if ($guige_info) {
                    //该规格存在
                    $guige_data['guige_id'] = $guige_info['guige_id'];
                    (new GuiGe())->updateGuiGe($guige_data);
                    $guige_id = $guige_info['guige_id'];
                } else {
                    $guige_id = (new GuiGe())->insertGuiGe($guige_data);
                }
                $guige_id_arr[] = $guige_id;
                $goods_stock += $v['enableStore'];
                if ($v['productNum'] == 1) {
                    //规格数量为1时 设为默认规格
                    $default_guige_id = $guige_id;
                }
            }

            if (!$default_guige_id) {
                $guigeinfo = (new GuiGe())->getGuiGeInfo(['productNum' => 1, 'goods_id' => $goods_info['goods_id']]);
                $guige_data['goods_id'] = $goods_info['goods_id'];
                $guige_data['guige_name'] = '1' . $info['unit'] . '装';
                //$guige_data['guige_price'] = $info['price'];//不更新商品售价
                $guige_data['cost_price'] = $info['price'];//不更新商品售价
                $guige_data['guige_sort'] = 1;
                $guige_data['guige_no'] = $info['sn'];
                $guige_data['productNum'] = 1;
                $guige_data['tax'] = $info['tax'];
                $guige_data['guige_num'] = $info['enableStore'];
                if ($guigeinfo) {
                    $guige_data['guige_id'] = $guigeinfo['guige_id'];
                    $default_guige_id = (new GuiGe())->updateGuiGe($guige_data);
                } else {
                    $default_guige_id = (new GuiGe())->insertGuiGe($guige_data);
                }
                $guige_id_arr[] = $default_guige_id;
            }

            if ($guige_id_arr) {
                //删除不存在规格
                (new GuiGe())->deleteGuiGeByCondition(['and', ['not in', 'guige_id', $guige_id_arr], ['goods_id' => $goods_info['goods_id']]]);
            }
            (new Goods())->updateGoods(['goods_id' => $goods_info['goods_id'], 'goods_stock' => $goods_stock, 'default_guige_id' => $default_guige_id, 'gys_state' => $info['isSupply']]);
        }
    }

    private function get_guige($goodsSn)
    {
        $config = $this->getconfig();
        $url = $this->gethttp() . '/api/v2/goods/getGoodsSpecs?';
        $params['goodsSn'] = $goodsSn;
        $params['memberId'] = $config['memberId'];
        $params['accountId'] = $config['accountId'];
        $params['token'] = $config['token'];
        $str = $this->haidaiSign($params);
        $url .= $str;
        $json = file_get_contents($url);
        $arr = json_decode($json, true);

        if ($arr['result'] == 1) {
            return $arr['data'];
        }
        if ($arr['result'] == 0 && $arr['code'] == 106) {
            $this->login();
            $this->get_guige($goodsSn);
        }
    }


    /**
     * 更新订单
     */
    public function actionUpOrder()
    {
        $order_list = (new Order())->getOrderList(['order_type' => 3]);
        foreach ($order_list as $k => $v) {
            $this->order_detail($v);
        }
    }

    private function order_detail($order_info)
    {
        $config = $this->getconfig();
        $url = $this->gethttp() . '/api/v2/order/orderDetail?';
        $params['customOrder'] = $order_info['order_sn'];
        $params['memberId'] = $config['memberId'];

        $str = $this->haidaiSign($params);
        $url .= $str;
        $json = file_get_contents($url);
        $arr = json_decode($json, true);
        if ($arr['result'] == 1) {
            $data = $arr['data'];
            if ($data['status'] >= 5 && $order_info['order_state'] == 30) {
                //用户订单为已付款未发货状态 供应商已发货
                $order_data['dlyCode'] = $data['ships']['dlyCode']; //供应商快递代码
                $order_data['shipping_code'] = $data['ships']['shipNo']; //快递单号
                $order_data['orderSn'] = $data['sn']; //供应商单号
                $order_data['sent_time'] = $data['saleCmplTime']; //发货时间
                $order_data['order_id'] = $order_info['order_id'];
                $order_data['order_state'] = 40; //已发货
                //更新订单发货信息
                (new Order())->updateOrder($order_data);
            }
            if ($data['status'] == 5 && $order_info['order_state'] >= 50) {
                //平台订单状态 用户确认收货 供应商发货状态
                $this->rogConfirm($data['orderId']); //调用确认收货接口
            }

        } elseif ($arr['result'] == 0 && $arr['code'] == 106) {
            $this->login();
            $this->actionOrderDetail($order_info);
        }
    }
    private function rogConfirm($orderIds)
    {
        $config = $this->getconfig();
        $url = $this->gethttp() . '/api/v2/order/rogConfirm?';
        $params['orderIds'] = $orderIds;
        $params['memberId'] = $config['memberId'];
        $params['accountId'] = $config['accountId'];
        $params['token'] = $config['token'];
        $str = $this->haidaiSign($params);
        $url .= $str;
        $json = file_get_contents($url);
        $arr = json_decode($json, true);
        if ($arr['result'] == 1) {
            return $arr['data'];
        } elseif ($arr['result'] == 0 && $arr['code'] == 106) {
            $this->login();
            $this->rogConfirm($orderIds);
        }
    }


    /**
     * 检测订单
     */
    public function actionCheckOrder()
    {
        $qrsh = (new Setting())->getSetting('qrsh');
        $addtime = time() - 60 * 20;
        $sendtime = time() - 60 * 60 * 24 * $qrsh;
        $order_list = (new Order())->getOrderList(['and', ['type' => 1], ['in', 'order_state', [20, 40]]]);
        foreach ($order_list as $k => $v) {
            //未付款订单
            if ($v['order_state'] == 20 && $v['add_time'] < $addtime) {
                $this->cancel_order($v['order_id']);
            }
            //已发货未确认收货订单
            if ($v['order_state'] == 40 && $v['sent_time'] < $sendtime) {
                $data = array('order_id' => $v['order_id'], 'sh_time' => time(), 'order_state' => 50);
                $result = (new Order())->updateOrder($data);
                if ($result) {
                    (new Order())->orderaction($v['order_id'], 'buyer', $v['buyer_name'], '确认收货', 'ORDER_STATE_DONE');
                    //赠送购物积分
                    $member_info = (new Member())->getMemberInfo(['member_id' => $v['buyer_id']]);
                    $integral = $member_info['member_points'] + $v['point_amount']; //用户所剩积分
                    (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]); //修改用户积分
                    (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $v['point_amount'], 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '购物',]);
                    $this->send_member_msg1($member_info['member_id'], '积分变更', '您确认收货获得积分：' . $v['point_amount'] . '，订单号：' . $v['order_sn']);
                }
            }
        }
        //未完成拼团
        $bulk_list = (new Bulk())->getBulkList(['and', ['<', 'end_time', time()]]);
        $bulk_id_arr = $bulk_list ? array_column($bulk_list, 'bulk_id') : [];
        $bulk_start_list = (new BulkStart())->getBulkStartList(['and', ['state' => 0], ['in', 'bulk_id', $bulk_id_arr]]);
        $bulk_start_id_arr = $bulk_start_list ? array_column($bulk_start_list, 'list_id') : [];
        $bulk_list_list = (new BulkList())->getBulkListList(['and', ['in', 'state', [1]], ['in', 'list_id', $bulk_start_id_arr]]);
        foreach ($bulk_list_list as $k => $v) {
            (new BulkStart())->updateBulkStart(['list_id' => $v['list_id'], 'state' => -2]);
            $list_state = -1;
            $order_state = 32; //未退款
            $order_goods_info = (new OrderGoods())->getOrderGoodsInfo(['promotions_id'=>$v['id'],'goods_type'=>5]);
            $order_info = (new Order())->getOrderInfo(['order_id' => $order_goods_info['order_id']]);
            $member_info = (new Member())->getMemberInfo(['member_id' => $order_info['buyer_id']]);
            if ($order_goods_info){
                $result = $this->pay($order_goods_info['order_id']);
                if ($result['return_code'] == 'SUCCESS' && $result['return_msg'] == 'OK') {
                    $list_state = -2;
                    $order_state = 31; //已退款
                    $this->send_member_msg1('拼团失败', '您的拼团订单失败，系统将退回金额到您的微信,订单号为：' . $order_info['order_sn']);
                }
            }
            (new Order())->updateOrder(['order_id' => $order_info['order_id'], 'order_state' => $order_state]);
            (new BulkList())->updateBulkList(['id' => $v['id'], 'state' => $list_state]);
            $member_info['member_openid'] ? $this->xxmb1($member_info['member_openid'], 'A-Ft8Qb20vApjk5vZE_OXCCO5UdyU2v-lYRU690qMJw', '亲爱的' . $order_info['buyer_name'] . '您好，对不起，你的拼团失败了，我们会尽快返还你的订单金额！', $order_goods_info['order_goods_name'], floatval($order_info['pd_amount'])) : '';
        }
    }


    /**订单退款
     * @param $order_id
     * @param $code
     * @throws \WxPayException
     */
    private function pay($order_id)
    {
        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.JsApiPay.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.Config.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
        $info = (new Order())->getOrderOne(['order_id' => $order_id]);
        $wxpay = new \WxPayApi();
        $input = new \WxPayRefund();
        $input->SetOut_trade_no($info['order_sn']);
        $input->SetTotal_fee($info['pd_amount'] * 100);
        $input->SetRefund_fee($info['pd_amount'] * 100);
        $config = new \WxPayConfig();
        $input->SetOut_refund_no('TK'.date('YmdHis').rand(1000,9999));
        $input->SetOp_user_id($config->GetMerchantId());
        $order = $wxpay->refund($config, $input);
        return $order;
    }

    private function cancel_order($order_id)
    {
        $member_info = $this->user_info;
        $model_order = new Order();
        $order_info = $model_order->getOrderInfo(['order_id' => $order_id]);

        if (!$order_info['order_state'] == 20 || !time() - 60 * 20 > $order_info['add_time']) {
            return;
        }

        //修改订单状态
        $data = array('order_id' => $order_info['order_id'], 'order_state' => 10);
        $model_order->updateOrder($data);

        //返回优惠券
        if ($order_info['order_mc_id']) {
            $member_coupon_date['mc_use_time'] = 0;
            (new MemberCoupon())->updateMemberCoupon($member_coupon_date, ['mc_id' => $order_info['order_mc_id']]);
        }

        //返回库存
        $order_goods_list = (new OrderGoods())->getOrderGoodsList(['order_id' => $order_id]);
        foreach ($order_goods_list as $k => $v) {
            if ($v['goods_type'] == 1) {
                //抢购商品
                $panic_buy_info = (new PanicBuyGoods())->getPanicBuyGoodsInfo(['pbg_pb_id' => $v['promotions_id'], 'pbg_goods_id' => $v['order_goods_id']]);
                if ($panic_buy_info) {
                    //返回抢购数
                    (new PanicBuyGoods())->updatePanicBuyGoods(['pbg_id' => $panic_buy_info['pbg_id'], 'pbg_shop' => $panic_buy_info['pbg_shop'] - $v['order_goods_num']]); //抢购商品数量
                }
            }
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $v['order_goods_spec_id']]);
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['order_goods_id']]);
            (new GuiGe())->updateGuiGe(['guige_id' => $v['order_goods_spec_id'], 'guige_num' => $guige_info['guige_num'] + $v['order_goods_num']]);
            (new Goods())->updateGoods(['goods_id' => $v['order_goods_id'], 'goods_stock' => $goods_info['goods_stock'] + $v['order_goods_num']]);
        }

        //添加订单日志
        $model_order->orderaction($order_id, 'system', $member_info['member_name'], '取消订单', 'ORDER_STATE_CANCEL');
        $this->send_member_msg1($order_info['buyer_id'], '订单超时取消', '您的订单已超时，系统已为你取消');
    }

    /**
     * 佣金结算
     */
    public function actionSettlement()
    {
        $now_time = time();
        $day1 = (new Setting())->getSetting('daydd1');
        $day2 = (new Setting())->getSetting('daydd2');
        $time1 = $now_time - 3600 * 24 * $day2; //第十六天
        $time2 = $now_time - 3600 * 24 * $day1; //第二天
        $model = new Order();
        $condition = ['and'];
        $condition[] = ['type' => 1];
        $condition[] = ['>', 'commission_amount', 0];
        $condition[] = ['>', 'payment_time', 0];
        $condition[] = ['>', 'order_state', 30];
        $issettlement_list = (new MemberPredeposit())->getMemberChangeList(['and', ['predeposit_type' => 'brokerage']]); //已结算
        $arr = $issettlement_list ? array_column($issettlement_list, 'order_id') : [];
        $condition[] = ['not in', 'order_id', $arr]; //未结算
        $order_list = $model->getOrderList($condition);
        foreach ($order_list as $k => $v) {
            if ($v['buyer_id'] == 9) {
                //平台录入订单
                if ($v['add_time'] < $time2) {
                    $this->js_money($v);
                }
            } else {
                if ($v['sent_time'] < $time1) {
                    $this->js_money($v);
                    //完成订单
                    if ($v['order_state'] < 60) {
                        $this->complete_order($v['order_id']);
                    }
                }
            }
        }
    }
    private function js_money($order_info)
    {
        if ($order_info['commission_amount']) {
            $tjm_id = $order_info['fxs_id'];
            $member_info_fxs = (new Member())->getMemberInfo(['member_id' => $tjm_id]);
            //赠送佣金 存储到金额
            if ($member_info_fxs && $tjm_id) {
                $money = $member_info_fxs['available_predeposit'] + $order_info['commission_amount']; //用户余额
                (new Member())->updateMember(['member_id' => $tjm_id, 'available_predeposit' => $money]); //修改用户余额
                (new MemberPredeposit())->insertMemberChange(['member_id' => $tjm_id, 'after' => $money, 'before' => $member_info_fxs['available_predeposit'], 'predeposit_member_name' => $member_info_fxs['member_name'], 'predeposit_type' => 'brokerage', 'predeposit_av_amount' => $order_info['commission_amount'], 'predeposit_add_time' => time(), 'predeposit_desc' => '佣金', 'order_id' => $order_info['order_id']]); //余额变动记录
                $this->send_member_msg1($member_info_fxs['member_id'], '余额变更', '您获得佣金：' . $order_info['commission_amount'] . '，订单号：' . $order_info['order_sn']);
            }
        }
    }

    private function complete_order($order_id)
    {
        $model_order = new Order();
        $order_info = $model_order->getOrderInfo(['order_id' => $order_id], ['order_goods']);
        $goods_id = $order_info['extend_order_goods'][0]['order_goods_id'];
        $num = $order_info['extend_order_goods'][0]['order_goods_num'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]);
//        $member_info = (new Member())->getMemberInfo(['member_id' => $order_info['buyer_id']]);

        //增加商品销量
        $goods_sales = $goods_info['goods_sales'] + $num;
        (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_sales' => $goods_sales]);


        //已收货 超过可退款时间
        if (!$order_info['sh_time']) {
            $data = array('order_id' => $order_info['order_id'], 'order_state' => 60, 'sh_time' => time());
        } else {
            $data = array('order_id' => $order_info['order_id'], 'order_state' => 60);
        }
        (new Order())->updateOrder($data);


//        //赠送购物积分
//        $integral = $member_info['member_points'] + $order_info['point_amount']; //用户所剩积分
//        (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]); //修改用户积分
//        (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $order_info['point_amount'], 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '购物',]);
//        $this->send_member_msg1($member_info['member_id'], '积分变更', '您确认收货获得积分：' . $order_info['point_amount'] . '，订单号：' . $order_info['order_sn']);
    }


//    public function actionA()
//    {
//        set_time_limit(0);
//        ini_set("memory_limit", -1);
//        $str = file_get_contents('huiyuan.txt');
//        $arr = json_decode($str, true);
//        $array = [];
//        foreach ($arr as $k => $v) {
//            $array[] = array_values($v);
//        }
//        Yii::$app->db->createCommand()->batchInsert('qbt_member', ['member_id', 'member_name', 'member_truename', 'member_avatar', 'member_password', 'member_mobile', 'member_time', 'member_login_time', 'member_points', 'available_predeposit', 'weixin_unionid', 'member_recommended_no', 'tjm', 'member_type', 'institutions_id', 'member_type_time', 'ticket'], $array)->execute();
//    }

//    public function actionA(){
//        set_time_limit(0);
//        ini_set("memory_limit",-1);
//        $arr = (new Guige())->getGuiGeList(['>','A.goods_id',3934]);
//        foreach ($arr as $k => $v) {
//            if ($v['goods_pic']){
//                $arr = explode('/',$v['goods_pic']);
//                $goods_pic = end($arr);
//                (new Goods())->updateGoods(['goods_id'=>$v['goods_id'],'goods_pic'=>'http://www.yogoclub.com/data/common_static/upload/mall/store/goods/1/'.$goods_pic]);
//            }
//        }
//    }

    //    public function actionG($open_id = 'oZGQk0YbWNjNq6r3LLi0Jt6BcNc4')
//    public function actionC()
//    {
//        set_time_limit(0);
//        $member_list = (new Member())->getMemberList(['and',['>','member_mobile',0],['member_password'=>null]], '', '', 'member_id desc','member_id,member_mobile');
//        foreach ($member_list as $k => $v) {
//            $member_password = MD5(substr($v['member_mobile'],-6));
//            (new Member())->updateMember(['member_id' => $v['member_id'], 'member_password' => $member_password]);
//        }
//    }

//    public function actionC1()
//    {
//        set_time_limit(0);
//        $token = $this->get_wx_accesss_token1();
//        ECHO $token;
//        DIE;
//        for ($i = 1; $i <= 20; $i++) {
//            $offset = ($i - 1) * 100;
//            $member_list = (new Member())->getMemberList(['like', 'weixin_unionid', 'oqq8-'], $offset, '100', 'member_id asc');
//            if ($member_list) {
//                foreach ($member_list as $k => $v) {
//                    $user_list[$k] = ['openid' => $v['weixin_unionid']];
//                }
//                $url = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token=' . $token;
//                $data = ['user_list' => $user_list];
//                $data = json_encode($data);
//                $result = $this->curl($url, $data);
//                $result = json_decode($result, true);
//                foreach ($result['user_info_list'] as $k1 => $v1) {
//                    if ($v1['subscribe']) {
//                        $unionid = $v1['unionid'];
//                    } else {
//                        $unionid = null;
//                    }
//                    (new Member())->updateMemberByCondition(['weixin_unionid' => $unionid], ['weixin_unionid' => $v1['openid']]);
//                    echo '更新ID【' . $unionid . '】<br>';
//                }
//            }
//        }
//    }

}
