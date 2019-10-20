<?php

namespace api\modules\v1\controllers;
use yii\web\Controller;
use yii;

class WxPayController extends Controller
{
    var $config = [
        'appid' => 'wx1fb0fc15950650fb', /*微信开放平台上的应用id*/
        'mch_id' => '1388052902',  /*微信申请成功之后邮件中的商户id*/
        'api_key' => 'yogowechatpayment201612345678901',  /*在微信商户平台上自己设定的api密钥 32位*/
    ];

    /** 微信支付
     * @param $data
     * @return int|\json数据，可直接填入js函数作为参数
     * @throws \WxPayException
     */
    public function actionPay()
    {
        $data["appid"]            = $this->config["appid"];
        $data["body"]             = '测试';
        $data["mch_id"]           = $this->config['mch_id'];
        $data["nonce_str"]        = $this->getNonceStr();
        $data["notify_url"] = 'http://' . $_SERVER['SERVER_NAME'] . '/yghsc/api/web/index.php/v1/WxPay/notify';
        $data["out_trade_no"]     = time().rand(100,999);
        $data["spbill_create_ip"] = $this->get_client_ip();
        $data["total_fee"] = 1;
        $data["trade_type"]       = 'JSAPI';
        $data["openid"] = 'o3iiL5bdeeO3ou4Q4OM7G2-P2WVg';
        $order = $this->unifiedOrder($data);
        dump($order);
    }

    /*
    获取当前服务器的IP
    */
    function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } else if (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    public function unifiedOrder($data, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //检测必填参数
        if(empty($data["out_trade_no"])) {
            die("缺少统一支付接口必填参数out_trade_no！");
        }else if(empty($data["body"])){
            die("缺少统一支付接口必填参数body！");
        }else if(empty($data["total_fee"])) {
            die("缺少统一支付接口必填参数total_fee！");
        }else if(empty($data["trade_type"])) {
            die("缺少统一支付接口必填参数trade_type！");
        }

        //关联参数
        if($data["trade_type"] == "JSAPI" && empty($data["openid"])){
            die("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }
        if($data["trade_type"] == "NATIVE" && empty($data["product_id"])){
            die("统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！");
        }

        //签名
        $sign = $this->MakeSign($data);

        $xml = $sign->ToXml();

        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = self::postXmlCurl($xml, $url, false, $timeOut);
        $result = self::Init($response);
        self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
        return $result;
    }

    /**
     *
     * 上报数据， 上报的时候将屏蔽所有异常流程
     * @param WxPayConfigInterface $config  配置对象
     * @param string $usrl
     * @param int $startTimeStamp
     * @param array $data
     */
    private static function reportCostTime($config, $url, $startTimeStamp, $data)
    {
        //如果不需要上报数据
        $reportLevenl = $config->GetReportLevenl();
        if($reportLevenl == 0){
            return;
        }
        //如果仅失败上报
        if($reportLevenl == 1 &&
            array_key_exists("return_code", $data) &&
            $data["return_code"] == "SUCCESS" &&
            array_key_exists("result_code", $data) &&
            $data["result_code"] == "SUCCESS")
        {
            return;
        }

        //上报逻辑
        $endTimeStamp = self::getMillisecond();
        $objInput = new WxPayReport();
        $objInput->SetInterface_url($url);
        $objInput->SetExecute_time_($endTimeStamp - $startTimeStamp);
        //返回状态码
        if(array_key_exists("return_code", $data)){
            $objInput->SetReturn_code($data["return_code"]);
        }
        //返回信息
        if(array_key_exists("return_msg", $data)){
            $objInput->SetReturn_msg($data["return_msg"]);
        }
        //业务结果
        if(array_key_exists("result_code", $data)){
            $objInput->SetResult_code($data["result_code"]);
        }
        //错误代码
        if(array_key_exists("err_code", $data)){
            $objInput->SetErr_code($data["err_code"]);
        }
        //错误代码描述
        if(array_key_exists("err_code_des", $data)){
            $objInput->SetErr_code_des($data["err_code_des"]);
        }
        //商户订单号
        if(array_key_exists("out_trade_no", $data)){
            $objInput->SetOut_trade_no($data["out_trade_no"]);
        }
        //设备号
        if(array_key_exists("device_info", $data)){
            $objInput->SetDevice_info($data["device_info"]);
        }

        try{
            self::report($config, $objInput);
        } catch (WxPayException $e){
            //不做任何处理
        }
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
//    public static function Init($xml)
//    {
//        $obj = new self();
//        $obj->FromXml($xml);
//        //fix bug 2015-06-29
//        if($obj->values['return_code'] != 'SUCCESS'){
//            return $obj->GetValues();
//        }
//        $obj->CheckSign();
//        return $obj->GetValues();
//    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param WxPayConfigInterface $config  配置对象
     * @param string $xml  需要post的xml数据
     * @param string $url  url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second   url执行超时时间，默认30s
     * @throws WxPayException
     */
    private static function postXmlCurl($config, $xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        $curlVersion = curl_version();
        $ua = "WXPaySDK/3.0.9 (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." "
            .$config->GetMerchantId();

        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        $proxyHost = "0.0.0.0";
        $proxyPort = 0;
        $config->GetProxy($proxyHost, $proxyPort);
        //如果有配置代理这里就设置代理
        if($proxyHost != "0.0.0.0" && $proxyPort != 0){
            curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
            curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
        }
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        curl_setopt($ch,CURLOPT_USERAGENT, $ua);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if($useCert == true){
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            //证书文件请放入服务器的非web目录下
            $sslCertPath = Yii::getAlias('@vendor') . "/wxpay/cert/apiclient_cert.pem";
            $sslKeyPath = Yii::getAlias('@vendor') . "/wxpay/cert/apiclient_key.pem";
            $config->GetSSLCertPath($sslCertPath, $sslKeyPath);
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, $sslCertPath);
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, $sslKeyPath);
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }

    /**
     * 获取毫秒级别的时间戳
     */
    private static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode ( " ", microtime () );
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode( ".", $time );
        $time = $time2[0];
        return $time;
    }

    /**
     * 生成签名
     * @param WxPayConfigInterface $config  配置对象
     * @param bool $needSignType  是否需要补signtype
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function MakeSign($config)
    {
        //签名步骤一：按字典序排序参数
        ksort($config);
        $string = $this->ToUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$this->config['api_key'];
        //签名步骤三：MD5加密或者HMAC-SHA256
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    /**
     * 格式化参数格式化成url参数
     */
    public function ToUrlParams()
    {
        $buff = "";
        foreach ($this->values as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**支付回调
     * @throws \WxPayException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function notify()
    {
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xmlObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $xmlArr = json_decode(json_encode($xmlObj), true);
        $result_code = $xmlArr['result_code'];
        $transaction_id = $xmlArr['transaction_id'];
        if ($result_code == 'SUCCESS') {
            //查询订单支付情况，并标注支付
            $this->orderquery($transaction_id);
        }
    }

    /** 查询支付状态
     * @param $transaction_id 微信支付订单号
     * @return bool
     * @throws \WxPayException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function orderquery($transaction_id){
        $input = new \WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);
        $config = new \WxPayConfig();
        $result = \WxPayApi::orderQuery($config, $input);
        //添加支付日志
        if (array_key_exists("return_code", $result) && $result["return_code"] == "SUCCESS") {
            //修改订单状态

            //通知微信服务器
            echo exit('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>');
        }
        return false;
    }
}