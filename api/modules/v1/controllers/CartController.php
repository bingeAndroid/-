<?php

namespace api\modules\v1\controllers;

use frontend\services\payment\Alipay\alipay;
use frontend\services\payment\AlipayWap\AlipayTradeService;
use frontend\services\payment\WxPay\wx_qrcode;
use system\helpers\SysHelper;
use system\modules\operate\services\DiscountPackage;
use system\modules\operate\services\DiscountPackageGoods;
use system\services\Area;
use system\services\GoodsSpec;
use system\services\MemberAddress;
use system\services\Order;
use system\services\OrderGoods;
use system\services\Payment;
use system\services\ShoppingCar;
use system\services\SystemMsg;
use Yii;
use api\modules\v1\controllers\BaseController;
use system\services\Goods;
use yii\data\Pagination;


class CartController extends MemberBaseController
{

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->getMemberAndGradeInfo();
    }

    /**
     * 购物车
     */
    public function actionCart()
    {
        $cart_model = new ShoppingCar();
        $fields = 'G.goods_id,G.goods_name,G.goods_description,G.store_id,G.goods_market_price,G.goods_pic,G.goods_price,G.goods_sku,
        G.goods_promotion_type,ST.store_name,ST.store_logo,S.cart_id,S.goods_price as cart_goods_price,S.goods_num,S.goods_spec_id,
        S.cart_dp_id,S.goods_name as cart_goods_name,FD.fd_title';
        $cart_list = $cart_model->getShoppingCarListByStore(['buyer_id' => $this->member_info['member_id']], 0, '', '', $fields);
        if ($cart_list) {
            foreach ($cart_list as $key => $val) {
                $cart_list[$key]['store_logo'] = SysHelper::getImage($val['store_logo'], 0, 0, 0, [0, 0], 1);
                foreach ($val['goods_list'] as $k => $v) {
                    $cart_list[$key]['goods_list'][$k]['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
                }
            }
        }

        $this->jsonRet->jsonOutput(0, '', array_values($cart_list));
    }

    /**
     * 加入购物车
     */
    public function actionAddCart()
    {
        $post = Yii::$app->request->post();
        $goods_model = new Goods();
        $dp_model = new DiscountPackage();
        $dpg_model = new DiscountPackageGoods();
        $car_model = new ShoppingCar();

        $good_id = isset($post['goods_id']) ? $post['goods_id'] : 0;

        $quantity = isset($post['quantity']) ? intval($post['quantity']) : 1;//数量
        $good_spec_id = isset($post['good_spec_id']) ? intval($post['good_spec_id']) : 0;//规格
        $dp_id = isset($post['dp_id']) ? intval($post['dp_id']) : 0;//优惠套餐


        if ($good_id) {
            //如果已经存在

            $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $good_id], 'A.*,E.*,S.goods_spec_id,S.spec_group,S.spec_price,S.spec_inventory,S.spec_store_sku', ['good_spec_id' => $good_spec_id]);
            //验证商品是否能加入购物车，是否足够库存
            $check = $goods_model->checkCartGoods($goods_info, $quantity, $this->member_info);
            if (!$check['state']) {
                $this->jsonRet->jsonOutput(10000, $check['msg']);
            }
            //查询促销限购
            $limit_info = $goods_model->getGoodsOperation($goods_info);
            if ($limit_info) {
                $buynum = $quantity;
                if (isset($this->member_info)) {
                    $condition = ['and', ['A.order_goods_id' => $goods_info['goods_id']], ['A.order_buyer_id' => $this->member_info['member_id']], ['>=', 'B.order_state', Yii::$app->params['ORDER_STATE_PAID']]];
                    $order_goods_info = (new OrderGoods())->getOrderGoodsInfoAll($condition, 'SUM(A.order_goods_num) AS goods_num');
                    $buynum += $order_goods_info['goods_num'];
                }
                if ($buynum > $limit_info['limit_num']) {
                    $this->jsonRet->jsonOutput($this->errorRet['BUY_LIMIT']['ERROR_NO'], $this->errorRet['BUY_LIMIT']['ERROR_MESSAGE'] . $limit_info['limit_num'] . '件');
                }
            }

            //获取购物价格，是否含促销活动
            $goods_price = $goods_model->getGoodsPrice($goods_info, ['goods_spec_id' => $good_spec_id]);
            //加入购物车
            $data = [
                'store_id' => $goods_info['store_id'],
                'goods_id' => $goods_info['goods_id'],
                'goods_price' => $goods_price,
                'goods_name' => $goods_info['goods_name'],
                'goods_image' => $goods_info['goods_pic'],
                'goods_spec_id' => $good_spec_id,
                'goods_num' => $quantity,
            ];


        } elseif ($dp_id) {
            //如果已经存在购物车
            if ($car_model->getShoppingCarCount(['cart_dp_id' => $dp_id, 'buyer_id' => $this->member_info['member_id']])) {

                $this->jsonRet->jsonOutput($this->errorRet['GOODS_IS_EXIST_CART']['ERROR_NO'], $this->errorRet['GOODS_IS_EXIST_CART']['ERROR_MESSAGE']);
            }

            $dp_info = $dp_model->getDiscountPackageInfo(['dp_id' => $dp_id]);
            if ($dp_info['dp_state'] != 1) {
                $this->jsonRet->jsonOutput($this->errorRet['DP_NOT_EXIST']['ERROR_NO'], $this->errorRet['DP_NOT_EXIST']['ERROR_MESSAGE']);
            }

            $goods_info = $dpg_model->getDiscountPackageGoodsList(['dpg_dp_id' => $dp_id]);
            foreach ($goods_info as $val) {
                //验证商品是否能加入购物车，是否足够库存
                $check = $goods_model->checkCartGoods($val, $quantity, $this->member_info);
                if (!$check['state']) {
                    $this->jsonRet->jsonOutput(100, $check['msg']);
                }
            }
            //加入购物车
            $data = [
                'buyer_id' => $this->member_info['member_id'],
                'store_id' => $goods_info[0]['store_id'],
                'goods_id' => $goods_info[0]['goods_id'],
                'goods_price' => $dp_info['dp_discount_price'],
                'goods_name' => $dp_info['dp_title'],
                'goods_image' => $goods_info[0]['goods_pic'],
                'goods_num' => $quantity,
                'cart_dp_id' => $dp_id,
            ];


        }

        //已登录状态，存入数据库,未登录时，存入COOKIE
        if (isset($this->member_info['member_id'])) {
            $save_type = 'db';
            $data['buyer_id'] = $this->member_info['member_id'];
        } else {
            $save_type = 'cookie';
        }
        $res = $car_model->addCart($data, $save_type, $quantity);

        Yii::$app->response->send(); //exit 前加这句话,cookie才生效
        if ($res) {
            $this->jsonRet->jsonOutput(0, '加入购物车成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['ADD_CART_FAIL']['ERROR_NO'], $this->errorRet['ADD_CART_FAIL']['ERROR_MESSAGE']);
        }

    }

    /**
     * 修改购物车数量
     */
    public function actionCartUpdate()
    {
        $post = SysHelper::postInputData();

        $cart_id = intval(abs($post['cart_id']));
        $quantity = isset($post['quantity']) ? intval(abs($post['quantity'])) : 1;

        if (empty($cart_id) || empty($quantity)) {
            $this->jsonRet->jsonOutput($this->errorRet['UPDATE_FAIL']['ERROR_NO'], $this->errorRet['UPDATE_FAIL']['ERROR_MESSAGE']);
        }

        $model_cart = new ShoppingCar();
        $model_goods = new Goods();
        $model_goods_spec = new GoodsSpec();
        $model_discount = new DiscountPackage();

        //存放返回信息
        $return = array();

        $goods_info = $model_cart->getShoppingCarInfo(array('cart_id' => $cart_id, 'buyer_id' => $this->member_info['member_id']));
        if (!$goods_info) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_IN_CART']['ERROR_NO'], $this->errorRet['GOODS_NOT_IN_CART']['ERROR_MESSAGE']);
        }
        if ($goods_info['goods_num'] == $quantity) {
            $this->jsonRet->jsonOutput(0, '修改成功');
        }
        $goods_price = $model_goods->getGoodsPrice($goods_info, ['goods_spec_id' => $goods_info['goods_spec_id']]);
        if ($goods_info['goods_spec_id'] == '0') {

            //优惠套餐
            if ($goods_info['cart_dp_id']) {
                $res = (new DiscountPackage())->checkDiscountPackage($goods_info['cart_dp_id'], $quantity, $goods_info['goods_num'], $this->member_info);
                if (!$res['status']) {
                    $this->jsonRet->jsonOutput(10000, $res['msg']);
                } else {
                    $goods_price = $res['msg']['dp_discount_price'];
                }
            } else {
                //普通商品
                if (empty($goods_info) || $goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {

                    $this->jsonRet->jsonOutput($this->errorRet['GOODS_OFF_SHELF']['ERROR_NO'], $this->errorRet['GOODS_OFF_SHELF']['ERROR_MESSAGE']);
                }
                if (intval($goods_info['goods_stock']) < $quantity) {
                    $return['state'] = 'shortage';
                    $return['msg'] = '库存不足';
                    $return['goods_num'] = $goods_info['goods_stock'];
                    $return['goods_price'] = $goods_price;
                    $return['subtotal'] = $goods_price * intval($goods_info['goods_stock']);
                    $this->jsonRet->jsonOutput($this->errorRet['UNDERSTOCK']['ERROR_NO'], $this->errorRet['UNDERSTOCK']['ERROR_MESSAGE']);
                }
            }
        } else {
            //规格商品
            $goods_spec_info = $model_goods_spec->getGoodsSpecInfo(['goods_id' => $goods_info['goods_id'], 'goods_spec_id' => $goods_info['goods_spec_id']]);

            if (empty($goods_spec_info)) {
                $return['state'] = 'invalid';
                $return['msg'] = '商品已被下架';
                $return['subtotal'] = 0;
                $this->jsonRet->jsonOutput($this->errorRet['GOODS_OFF_SHELF']['ERROR_NO'], $this->errorRet['GOODS_OFF_SHELF']['ERROR_MESSAGE']);
            }

            if (intval($goods_spec_info['spec_inventory']) < $quantity) {
                $return['state'] = 'shortage';
                $return['msg'] = '库存不足';
                $return['goods_num'] = $goods_spec_info['spec_inventory'];
                $return['goods_price'] = $goods_price;
                $return['subtotal'] = $goods_price * intval($goods_spec_info['spec_inventory']);
                $this->jsonRet->jsonOutput($this->errorRet['UNDERSTOCK']['ERROR_NO'], $this->errorRet['UNDERSTOCK']['ERROR_MESSAGE']);
            }
            $goods_price = $goods_price;
        }

        $data = array();
        $data['goods_num'] = $quantity;
        $data['goods_price'] = $goods_price;
        $update = $model_cart->updateShoppingCarByCondition($data, array('cart_id' => $cart_id, 'buyer_id' => $this->member_info['member_id']));
        if ($update) {
            $return = array();
            $return['state'] = 'true';
            $return['subtotal'] = $goods_price * $quantity;
            $return['goods_price'] = $goods_price;
            $return['goods_num'] = $quantity;
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['UPDATE_FAIL']['ERROR_NO'], $this->errorRet['UPDATE_FAIL']['ERROR_MESSAGE']);
        }
        $this->jsonRet->jsonOutput(0, '修改成功');
    }

    /**
     * 删除购物车商品
     */
    function actionCartDel()
    {
        $post = SysHelper::postInputData();

        $cart_id = isset($post['cart_id']) ? $post['cart_id'] : 0;
        $map = ['and'];
        if ($cart_id) {
            $cart_id = array_unique((array)$cart_id);
            $map[] = ['in', 'cart_id', $cart_id];
        };
        $map[] = ['buyer_id' => $this->member_info['member_id']];
        $model_cart = new ShoppingCar();

        //登录状态下删除数据库内容
        $delete = $model_cart->deleteShoppingCar($map);
        if ($delete) {
            $this->jsonRet->jsonOutput(0, '删除成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['DELETE_FAIL']['ERROR_NO'], $this->errorRet['DELETE_FAIL']['ERROR_MESSAGE']);
        }

    }


    /**
     * 结算
     */
    public function actionShopping()
    {
        $cart_model = new ShoppingCar();
        $order_model = new Order();

        $post = SysHelper::postInputData();
        $cart_id = isset($post['cart_id']) ? $post['cart_id'] : 0;//购物车ID
        $ifcart = isset($post['ifcart']) ? $post['ifcart'] : 1;//购物车
        $good_spec_id = isset($post['good_spec_id']) ? $post['good_spec_id'] : 0;//规格
        $get_ma_id = isset($post['ma_id']) ? $post['ma_id'] : 0;//收货地址ID

        $condition = ['and'];
        if ($cart_id) {
            $condition[] = ['in', 'cart_id', $post['cart_id']];
        }
        $condition[] = ['buyer_id' => $this->member_info['member_id']];
        //商品列表
        $fields = 'G.goods_id,G.goods_name,G.goods_description,G.store_id,G.goods_market_price,G.goods_pic,G.goods_price,G.goods_sku
        ,ST.store_name,ST.store_logo,S.cart_id,S.goods_price as cart_goods_price,S.goods_num,S.goods_spec_id,
        S.cart_dp_id,FD.fd_title';
        $cart_list = $cart_model->getShoppingCarListByStoreApi($cart_id, $ifcart, $this->member_info['member_id'], $good_spec_id, $fields);
        if (!$cart_list['state']) {
            $this->jsonRet->jsonOutput(10000, $cart_list['msg']);
        } else {
            $goods_price_cart = !$ifcart ? $cart_list['goods_price_cart'] : 0;
            $goods_num_cart = !$ifcart ? $cart_list['goods_num_cart'] : 0;
            $cart_list = $cart_list['cart_list'];
        }
        //运费
        $default_address = (new MemberAddress())->getMemberAddressInfo(['ma_member_id' => $this->member_info['member_id'], 'ma_is_default' => 1]);
        if (!$default_address) {
            $default_address = (new MemberAddress())->getMemberAddressInfo(['ma_member_id' => $this->member_info['member_id']]);
        }
        $total['freight_fee'] = 0;
        $ma_id = $get_ma_id ? $get_ma_id : (isset($default_address['ma_id']) ? $default_address['ma_id'] : 0);
        if ($ma_id) {
            $total['freight_fee'] = $order_model->get_freight_fee($this->member_info['member_id'], $ma_id, 'int', $cart_id, $ifcart, ['goods_price_cart' => $goods_price_cart]);
        }
        //收货地址
        $member_address_list = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]);
        if ($member_address_list) {
            $area_model = new Area();
            $member_address_list['address'] = $area_model->getAreaTextById($member_address_list['ma_area_id'], ' ');
        }
        //运费含多个店铺运费（TODO::运费没有满多少免运费）
        $freight_fee_arr = $order_model->get_freight_fee($this->member_info['member_id'], $ma_id, 'array', $cart_id, $ifcart);
        //获取店铺优惠券
        $store_occupy = $cart_model->getStoreAvailableVoucherList($cart_list, $this->member_info['member_id']);

        if ($cart_list) {
            foreach ($cart_list as $key => $val) {
                $cart_list[$key]['store_logo'] = SysHelper::getImage($val['store_logo'], 0, 0, 0, [0, 0], 1);
                $cart_list[$key]['store_freight_fee'] = isset($freight_fee_arr[$val['store_id']]) ? $freight_fee_arr[$val['store_id']] : 0;;
                foreach ($val['goods_list'] as $k => $v) {
                    $cart_list[$key]['goods_list'][$k]['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
                    if (isset($v['dp_goods_list'])) {
                        foreach ($v['dp_goods_list'] as $kk => $vv) {
                            $cart_list[$key]['goods_list'][$k]['dp_goods_list'][$kk]['goods_pic'] = SysHelper::getImage($vv['goods_pic'], 0, 0, 0, [0, 0], 1);
                        }
                    }
                }
                $cart_list[$key]['store_occupy'] = isset($store_occupy[$val['store_id']]) ? $store_occupy[$val['store_id']] : [];
            }
        }

        //支付方式
        $payment = (new Payment())->getPaymentList(['state' => 1], '', '', '', 'id,code,title');

        //满减
        $total['fulldown_fee'] = $cart_model->getShoppingFullDown($this->member_info['member_id'], $cart_id, 'int', $ifcart, ['goods_price_cart' => $goods_price_cart]);
        //总费用
        if (!$ifcart) {//立即购买
            $total['order_total_info'] = [
                'sc_total_num' => $goods_num_cart,
                'sc_total_price' => $goods_num_cart * $goods_price_cart,
            ];
        } else {
            $total['order_total_info'] = $cart_model->getShoppingCarFee($condition);
        }

        //  $total_fee = bcsub($total['total_info']['sc_total_price'],$total['fulldown_fee'],2)+$total['freight_fee'];
        $total_fee = ($total['order_total_info']['sc_total_price'] * 100 - $total['fulldown_fee'] * 100) / 100 + $total['freight_fee'];

        $this->jsonRet->jsonOutput(0, '', ['show' => array_values($cart_list), 'payment' => $payment, 'address' => $member_address_list, 'total_info' => $total, 'total_fee' => $total_fee]);
    }

    /**
     * 更改快递，更新价格费用
     */
    function actionFreightFee()
    {
        $post = SysHelper::postInputData();

        $ma_id = isset($post['ma_id']) ? $post['ma_id'] : 0;
        $cart_id = isset($post['cart_id']) ? $post['cart_id'] : 0;
        $ifcart = isset($post['ifcart']) ? $post['ifcart'] : 1;


        $freight_fee = (new Order())->get_freight_fee($this->member_info['member_id'], $ma_id, 'int', $cart_id, $ifcart);

        $this->jsonRet->jsonOutput(0, '', $freight_fee);

    }

    /**
     * 生成订单
     */
    public function actionOrder()
    {
        $model_cart = new ShoppingCar();

        $post = SysHelper::postInputData();
        $order_model = new Order();
        $pd_pay = isset($post['pd_pay']) ? $post['pd_pay'] : 0;//余额支付
        $password = isset($post['password']) ? $post['password'] : '';//余额支付密码
        $cart_id = isset($post['cart_id']) ? $post['cart_id'] : [];//购物车ID
        $ma_id = isset($post['ma_id']) ? $post['ma_id'] : 0;//收货地址ID
        $bankItem = isset($post['bankItem']) ? $post['bankItem'] : 0;//支付方式
        $voucher = isset($post['voucher']) ? $post['voucher'] : [];//优惠券['store_id'=>'mc_id']
        $shipping_time = isset($post['shipping_time']) ? $post['shipping_time'] : 1;//优惠券['store_id'=>'mc_id']
        $ifcart = isset($post['ifcart']) ? $post['ifcart'] : 1;//购物车
        $good_spec_id = isset($post['good_spec_id']) ? $post['good_spec_id'] : 0;//规格
        //处理余额支付
        if ($pd_pay) {
            if (empty($password)) {
                $this->jsonRet->jsonOutput(10000, '请输入正确支付密码并确认');
            };
            if ($this->member_info['member_payword'] == '' || !Yii::$app->security->validatePassword($password, $this->member_info['member_payword'])) {
                $this->jsonRet->jsonOutput(10000, '请输入正确支付密码并确认');
            }
        }
        $data = [
            'pd_pay' => $pd_pay,
            'password' => $password,
            'cart_id' => $cart_id,
            'ma_id' => $ma_id,
            'bankItem' => $bankItem,
            'voucher' => $voucher,
            'shipping_time' => $shipping_time,
            'ifcart' => $ifcart,
            'good_spec_id' => $good_spec_id,
        ];

        $result = $order_model->joinOrder($this->member_info, $data);
        if ($result['state']) {
            if ($ifcart) {
                $condition_cart = ['and'];
                $condition_cart[] = ['in', 'cart_id', $cart_id];
                $condition_cart[] = ['buyer_id' => $this->member_info['member_id']];
                //删除购物车
                $delete = $model_cart->deleteShoppingCar($condition_cart);
            }
            //添加后台消息推送
            (new SystemMsg())->insertSystemMsg(['sm_content' => '您有新的一笔订单。支付单号:' . $result['pay_sn']]);

            $this->jsonRet->jsonOutput(0, '', ['pay_sn' => $result['pay_sn']]);
        } else {
            $this->jsonRet->jsonOutput(10000, $result['msg']);
        }

    }

    /**
     * @return bool|string
     * 去支付
     */
    public function actionPay()
    {
        $post = SysHelper::postInputData();
        if (!isset($post['pay_sn'])) {
            $this->jsonRet->jsonOutput(10000, '付款单号不能为空');
        }
        $order_sn = $post['pay_sn'];
        $openid = isset($post['openid']) ? $post['openid'] : '';


        $order_model = new Order();
        $order_info = $order_model->getOrderInfoByPay(['pay_sn' => $order_sn, 'order_state' => 20]);
        if (!isset($order_info)) {
            $this->jsonRet->jsonOutput(10000, '付款订单已支付');
        }
//        if(!isset($openid) && $order_info['payment_code']=='wx_qrcode'){
//            $this->jsonRet->jsonOutput(10000,'微信支付OPENID不能为空');
//        }
        //支付信息
        $payinfo = (new Payment())->getPaymentInfo(['code' => $order_info['payment_code']]);

        $pay_online = '';
        /* 取得支付信息，生成支付代码 */
        if ($order_info['pay_amount'] > 0) {
            if ($order_info['payment_code'] == 'alipay') {
                $alipay = new AlipayTradeService($payinfo);
            } elseif ($order_info['payment_code'] == 'wx_qrcode') {
                $alipay = new wx_qrcode($payinfo);
            }
            $order_info['pay_attach'] = 'goods_pay';//商品支付
            if ($order_info['payment_code'] == 'wx_qrcode' && !$openid) {
                $pay_online = $alipay->requestPayApiH5($order_info);
            } else {
                $pay_online = $alipay->requestPayApi($order_info, $openid);
            }


        } elseif ($order_info['pay_amount'] == 0) {
            //直接支付成功奖励成长值/金币
            $order_model->updateOrderData($order_sn);
        }
        $this->jsonRet->jsonOutput(0, '', $pay_online);
    }
}
