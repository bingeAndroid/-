<?php
/**
 * Created by qbt.
 * User: lihz
 * Date: 2017/4/14
 * Time: 11:42
 */

namespace api\modules\v1\controllers;

use system\helpers\SysHelper;
use system\services\MobileHome;
use system\services\Setting;
use yii\helpers\ArrayHelper;
use Yii;
use yii\base\Action;
use yii\base\Exception;
use yii\web\Controller;
use api\services\JsonOutput;
use system\services\Goods;
use system\services\MemberMail;
use system\action\CaptchaAction;
use system\services\Member;
use system\services\Coupon;
use system\services\MemberCoupon;

class BaseController extends Controller
{

    protected $user_info;

    //public $enableCsrfValidation = false;

    /* json */
    public $jsonRet;
    public $errorRet;

    //小程序
    protected $appid = 'wx1fb0fc15950650fb';
    protected $mch_id = '1388052902';
    protected $secret = 'f700f82880b5a7f66edd493f60a5467f';

    //公众号
    protected $appid1 = 'wxaecab1d056c80422';
    protected $secret1 = 'c3f900f020d4f3a48ee166916743c567';


    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        //默认ajax return
        $this->jsonRet = new JsonOutput();
        $view = Yii::$app->view;

        //配置数据
        $view->params['setting_data'] = (new Setting())->getSettingByCache();
        $this->errorRet = SysHelper::getData('api', 'error');
        //网站是否关闭
        if(!$view->params['setting_data']['site_status'] && !stristr($_SERVER['REQUEST_URI'],'/site/site-close')){

            /* 商店关闭了，输出关闭的消息 */
            header('Content-type: text/html; charset=utf-8');

            die('<div style="margin: 150px; text-align: center; font-size: 14px"><p>网站维护</p><p>'.$view->params['setting_data']['close_reason'].'</p></div>');

        }

        //签名认证
        $post = Yii::$app->request->post();
//        if (!isset($post['sign']) || empty($post['sign'])) {
//            $this->jsonRet->jsonOutput(-3,'签名不能为空');
//        } else {
//            $old_sign = $post['sign'];
//            unset($post['sign']);
//            $sign = $this->getSign($post);
//            if ($sign != $old_sign) {
//                $this->jsonRet->jsonOutput(-4,'参数签名验证错误');
//            }
//        }


        /* 用户登录检测 正式打开 2019-05-15 */
        if (!empty($post['token'])) {
            $user_info = (new Member())->getMemberInfo(['member_token' => $post['token'], 'member_state' => 1]);
            if ($user_info) {
                $this->user_info = $user_info;
            }
        } else {
            $this->user_info = null;
        }

//        $this->user_info = (new Member())->getMemberInfo(['member_id' => 1]); //正式去掉
    }

    public function runAction($id, $params = []){
        $params = ArrayHelper::merge(Yii::$app->request->post(),$params);
        return parent::runAction($id, $params);
    }

    public function bind_member($user_info, $tjm)
    {
        if ($user_info['member_type'] == 0 && !$user_info['member_recommended_no'] && $tjm) {
            $id = substr($tjm, 3); //默认id前加3位字符串
            if ($user_info['member_id'] == $id) {
                return;
            }
            $member = (new Member())->getMemberInfo(['member_id' => $id, 'member_type' => 1]);
            if (!$member) {
                return;
            }
            if ($user_info['member_recommended_no']) {
                return;
            }
            $member_id = $user_info['member_id'];
            $member_data1['member_id'] = $member_id;
            $member_data1['member_recommended_no'] = $tjm;
            $member_data1['fs_time'] = time();
            (new Member())->updateMember($member_data1);
            $this->get_coupon($member_id); //获得注册大礼包
            //更新导游用户信息
            $member_data2['member_id'] = $id;
            $member_data2['fs_num'] = $member['fs_num'] + 1;
            (new Member())->updateMember($member_data2);
        }
    }

    //发送小程序模板消息 统一服务消息
    protected function xxmb($member_openid, $template_id, $title, $goods_name, $pd_amount, $members)
    {
        $token = $this->get_wx_accesss_token(); //微信小程序token
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=$token";
        $data = '{
                    "touser":"' . $member_openid . '",
                    "mp_template_msg":{
                        "appid":"wxaecab1d056c80422",
                        "template_id":"' . $template_id . '",
                        "url":"http://weixin.qq.com/download",
                        "miniprogram":{
                            "appid":"wx1fb0fc15950650fb",
                            "pagepath":"pages/wdpt/wdpt"
                        },
                        "data":{
                            "first":{
                                "value":"' . $title . '",
                                "color":"#173177"
                            },
                            "keyword1": {
                                "value": "' . $goods_name . '"
                            },
                            "keyword2": {
                                "value": "' . $pd_amount . '"
                            },
                            "keyword3": {
                                "value": "' . $members . '"
                            },
                            "remark":{
                                "value":"我们将尽快为您发货，欢迎再次光临！",
                                "color":"#173177"
                            }
                        }
                    }
                }';
        $this->curl($url, $data);
    }

    protected function xxmb1($member_openid, $template_id, $title, $goods_name, $pd_amount)
    {
        $token = $this->get_wx_accesss_token(); //微信小程序token
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/uniform_send?access_token=$token";
        $data = '{
                    "touser":"' . $member_openid . '",
                    "mp_template_msg":{
                        "appid":"wxaecab1d056c80422",
                        "template_id":"' . $template_id . '",
                        "url":"http://weixin.qq.com/download",
                        "miniprogram":{
                            "appid":"wx1fb0fc15950650fb",
                            "pagepath":"pages/wdpt/wdpt"
                        },
                        "data":{
                            "first":{
                                "value":"' . $title . '",
                                "color":"#173177"
                            },
                            "keyword1": {
                                "value": "' . $goods_name . '"
                            },
                            "keyword2": {
                                "value": "' . $pd_amount . '"
                            },
                            "remark":{
                                "value":"这次就差一丢丢，再来新开一团试试吧！",
                                "color":"#173177"
                            }
                        }
                    }
                }';
        $this->curl($url, $data);
    }


    protected function curl($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
    //注册获取优惠券
    private function get_coupon($member_id)
    {
        $member_info = (new Member)->getMemberInfo(['member_id' => $member_id]);
        $now_time = time();
        $coupon_list = (new Coupon())->getCouponList(['and', "coupon_cc_id=10", "coupon_type = 2"]);
        foreach ($coupon_list as $k => $v) {
            $coupon_data['mc_member_id'] = $member_id;
            $coupon_data['mc_member_username'] = $member_info['member_name']; //领取用户昵称
            $coupon_data['mc_coupon_id'] = $v['coupon_id'];
            $coupon_data['mc_receive_time'] = $now_time;
            $coupon_data['mc_start_time'] = $now_time;
            $coupon_data['mc_end_time'] = $now_time + 3600 * 24 * $v['day'];
            $coupon_data['mc_min_price'] = $v['coupon_min_price'];
            $coupon_data['mc_quota'] = $v['coupon_quota'];
            (new MemberCoupon())->insertMemberCoupon($coupon_data);
        }
    }

    /** 获取小程序accesss_token
     * @return mixed
     */
    public function get_wx_accesss_token()
    {
        $cache = \Yii::$app->cache;
        if ($cache->exists('wxxcx_accesss_token') && $cache->get('wxxcx_accesss_token')) {
            $accesss_token = $cache->get('wxxcx_accesss_token');
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->secret;
            $result = file_get_contents($url);
            $access_token_arr = json_decode($result, true);
            if (!empty($access_token_arr['access_token'])) {
                $accesss_token = $access_token_arr['access_token'];
                $cache->add('wxxcx_accesss_token', $accesss_token, 300);
            } else {
                $accesss_token = '';
            }
        }
        return $accesss_token;
    }

    /** 获取公众号accesss_token
     * @return mixed
     */
    public function get_wx_accesss_token1()
    {
        $cache = \Yii::$app->cache;
        if ($cache->exists('wx_accesss_token') && $cache->get('wx_accesss_token')) {
            $accesss_token = $cache->get('wx_accesss_token');
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid1 . '&secret=' . $this->secret1;
            $result = file_get_contents($url);
            $access_token_arr = json_decode($result, true);
            if (!empty($access_token_arr['access_token'])) {
                $accesss_token = $access_token_arr['access_token'];
                $cache->add('wx_accesss_token', $accesss_token, 300);
            } else {
                $accesss_token = '';
            }
        }
        return $accesss_token;
    }

    /**
     * 登录海带
     */
    private function login()
    {
        $url = $this->gethttp() . '/ssoapi/v2/login/login?';
        $arr['username'] = '13570577375';
        $arr['password'] = MD5(123456);
        $str = $this->haidaiSign($arr);
        $url .= $str;
        $json = file_get_contents($url);
        $arr = json_decode($json, true);
        if ($arr['result'] == 1) {
            $data['time'] = time();
            $data['accountId'] = $arr['data']['accountId'];
            $data['memberId'] = $arr['data']['memberId'];
            $data['token'] = $arr['data']['token'];
            $data['siteId'] = $arr['data']['siteId'];
            (new Setting())->updateSetting('haidai', json_encode($data));
        }
    }

    //获取储存token
    function getconfig()
    {
        $haidai_json = (new Setting())->getSetting('haidai');
        if (!$haidai_json) {
            $this->login();
        }
        $arr = json_decode($haidai_json, true);
        if ($arr['time'] + 3600 * 24 * 15 < time()) {
            $this->login();
        }
        return $arr;
    }

    //获取域名
    function gethttp()
    {
        return (new Setting())->getSetting('gysym');
    }

    function haidaiSign($arr)
    {
        $arr['timestamp'] = $this->getMillisecond();
        $arr['appkey'] = '93029969';
        $secret = 'c01c5507cb364e0593d87f0e3df8319a';
        ksort($arr);
        $str = '';
        foreach ($arr as $index => $item) {
            $str .= "$index=$item&";
        }
        $str = rtrim($str, "&");
        $str1 = $secret . $str . $secret;
        $topSign = strtoupper(SHA1($str1));

        $url_str = $str . '&topSign=' . $topSign;
        return $url_str;
    }


    //获取毫秒
    function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    protected function getSign($parm)
    {
        $parm['key'] = 'e10adc3949ba59abbe56e057f20f883e';
        $sign_string = $this->formatBizQueryParaMap($parm, false);

        //签名步骤三：MD5加密
        return strtolower(md5($sign_string));
    }

    /**
     * 格式化参数，签名过程需要使用
     * add by 黄老邪 181023
     * @access protected
     * @param array $paraMap 格式化数组
     * @param bool $urlencode 是否需要URL编码
     * @return string 排列参数顺序字符串
     */
    protected function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    /**
     * 统一处理action
     */
    public function actions()
    {
        return [
            /* selectInfinite控件默认处理url */
            'widget-select-infinite' => [
                'class' => 'api\component\WidgetSelectInfiniteAction',
            ],
            'widget-upload' => [
                'class' => 'api\component\WidgetUploadAction'
            ],
            /* ueditor控件默认处理url */
            'ueditor-handle' => [
                'class' => 'api\component\WidgetUEditorAction',
            ],
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'get-captcha' => [
                'class' => CaptchaAction::className(),
                'minLength' => 4,
                'maxLength' => 4,
                'offset' => 5,
                'width'=>96,
                'height'=>30,
                'disturbCharCount' => 2,//干扰字符数量
                'disturbLine' => 1,//干扰线
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
//                'fontFile'=>'@root/data/resource/FetteSteinschrift.ttf'
            ]
        ];
    }


    //商品是否促销 2019-05-08
    public function activity($good_id){
        $goods_model = new Goods();
        $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $good_id]);
        $goods_price = $goods_model->getGoodsDiscount($goods_info);
        if ($goods_price['state'] == 2) {
            $goods_price = $goods_price['discount_price'][$goods_info['default_guige_id']];
        } else {
            $goods_price = 0;
        }
        return $goods_price;
    }

    /**
     * @param Action $action action
     * @throws Exception error
     * @return bool true/false
     **/
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action))
            return false;


        return true;
    }

    /**
     */
    public function behaviors()
    {
        return [
        ];
    }

    //发送站内信到用户
    protected function send_member_msg($title, $content = '')
    {
        $member_info = $this->user_info;
        $data['mail_member_id'] = $member_info['member_id'];
        $data['mail_count'] = 1;
        $data['mail_subject'] = $title;
        $data['mail_content'] = $content ?: $title;
        $data['mail_time'] = time();
        (new MemberMail())->insertMail($data);
    }

    //发送站内信到用户
    protected function send_member_msg1($member_id, $title, $content = '')
    {
        $data['mail_member_id'] = $member_id;
        $data['mail_count'] = 1;
        $data['mail_subject'] = $title;
        $data['mail_content'] = $content ?: $title;
        $data['mail_time'] = time();
        (new MemberMail())->insertMail($data);
    }
}