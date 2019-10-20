<?php
/**
 * Created by qbt.
 * User: lihz
 * Date: 2017/5/16
 * Time: 15:08
 */

namespace api\modules\v1\controllers;

use frontend\services\payment\AlipayWap\AlipayTradeService;
use system\services\Express;
use system\services\Goods;
use system\services\Member;
use system\services\MemberPredeposit;
use system\services\OrderPay;
use system\services\Payment;
use system\services\Store;
use system\services\SystemMsg;
use Yii;
use yii\data\Pagination;
use system\helpers\SysHelper;
use system\services\Order;
use system\services\OrderReturn;
use system\services\OrderGoods;
use system\services\ShippingLog;
use system\services\Evaluate;
use yii\db\Expression;
use yii\helpers\Url;
use frontend\services\payment\WxPay\wx_qrcode;
use frontend\services\payment\Alipay\alipay;

class MemberOrderController extends MemberBaseController
{

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->getMemberAndGradeInfo();
    }

    /**
     * @return string
     * 订单中心
     */
    public function actionMemberOrder()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize']) ? intval($post['pagesize']) : 10;
        $page = isset($post['page']) ? $post['page'] : 1;
        $order_model = new Order();

        $type = isset($post['type']) ? $post['type'] : '';
        $str_state = '全部状态';

//10=>'已取消',20=>'未付款',30=>'已付款',40=>'已发货',50=>'已完成'
        $condition = ['and'];
        if ($type == Yii::$app->params['ORDER_STATE_NEW']) {
            $condition[] = ['order_state' => Yii::$app->params['ORDER_STATE_NEW']];
            $str_state = '等待付款';

        } elseif ($type == Yii::$app->params['ORDER_STATE_PAID']) {
            $condition[] = ['order_state' => Yii::$app->params['ORDER_STATE_PAID']];
            $str_state = '已付款/待发货';
        } elseif ($type == Yii::$app->params['ORDER_STATE_SEND']) {
            $condition[] = ['order_state' => Yii::$app->params['ORDER_STATE_SEND']];
            $str_state = '等待收货';

        } elseif ($type == 60) {
            $condition[] = ['order_state' => Yii::$app->params['ORDER_STATE_DONE']];
            $condition[] = ['evaluation_state' => Yii::$app->params['ORDER_COMMENT_STATE_NOT']];

        } elseif ($type == Yii::$app->params['ORDER_STATE_CANCEL']) {
            $condition[] = ['order_state' => Yii::$app->params['ORDER_STATE_CANCEL']];
            $str_state = '已取消';
        } elseif ($type == Yii::$app->params['ORDER_STATE_DONE']) {
            $condition[] = ['order_state' => Yii::$app->params['ORDER_STATE_DONE']];
            $str_state = '已完成';
        }


        $condition[] = ['buyer_id' => $this->member_info['member_id']];
        $totalCount = $order_model->getOrderCount($condition);

        $fields = 'order_id,order_sn,pay_sn,store_id,store_name,add_time,order_amount,shipping_fee,order_state,pay_state,payment_code';
        $order_list = $order_model->getOrderList($condition, $post['offset'], $post['limit'], 'order_id desc', $fields);
        foreach ($order_list as $key => $val) {
            if ($val['goods_list']) {
                foreach ($val['goods_list'] as $k => $v) {
                    $order_list[$key]['goods_list'][$k]['order_goods_image'] = SysHelper::getImage($v['order_goods_image'], 0, 0, 0, [0, 0], 1);
                }
            }
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);


        $show = ['list' => $order_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);


    }

    /**
     * @return string
     * 订单详情
     */
    public function actionMemberOrderView()
    {
        $this->layout = 'member_detail';
        $post = SysHelper::postInputData();
        $order_model = new Order();
        $express_model = new Express();

        $orderId = isset($post['order_id']) ? $post['order_id'] : 0;


        $condition['order_id'] = $orderId;
        $fields = 'order_id,order_sn,pay_sn,store_id,store_name,add_time,payment_time,sent_time,complete_time,
        goods_amount,order_amount,pd_amount,shipping_fee,evaluation_state,order_state,pay_state,
        shipping_code,buyer_address,ma_true_name,ma_phone,refund_state,refund_amount,
        trade_no,freight_id,order_mc_id,order_coupon_price,shipping_express_id,promotion_total,';
        $info = $order_model->getOrderInfo($condition, ['order_goods'], $fields);
        $info['express'] = $express_model->getExpressInfo(['id' => $info['shipping_express_id']]);

        //快递信息
        $info['shipping_list'] = SysHelper::get_express($info['express']['e_code'], $info['shipping_code']);


        $this->jsonRet->jsonOutput(0, '', $info);
    }

    /**
     * 支付订单
     */
    public function actionMemberOrderPay()
    {
        $post = SysHelper::postInputData();
        $order_id = $post['order_id'];
        $type = isset($post['type']) ? $post['type'] : 'goods_pay';
        $openid = isset($post['openid']) ? $post['openid'] : '';


        if ($type == 'goods_pay') {//商品支付
            //更新支付单号
            $pay_sn = SysHelper::buildOrderNo(10);
            (new OrderPay())->insertOrderPay(['pay_sn' => $pay_sn, 'buyer_id' => $this->member_info['member_id']]);
            (new Order())->updateOrderByCondition(['pay_sn' => $pay_sn], ['order_id' => $order_id]);

            $order_model = new Order();
            $order_info = $order_model->getOrderInfoByPay(['pay_sn' => $pay_sn]);

            $data = [
                'pay_sn' => $order_info['pay_sn'],
                'shipping_time' => $order_info['shipping_time'],
                'payment_code' => $order_info['payment_code'],
                'pay_amount' => $order_info['pay_amount'],
                'pay_attach' => $type,
            ];

        } elseif ($type == 'pd_pay') {//充值支付
            $pd_model = new MemberPredeposit();
            $pd_info = $pd_model->getMemberChargeInfo(['predeposit_id' => $order_id]);

            $data = [
                'pay_sn' => $pd_info['predeposit_sn'],
                'shipping_time' => '',
                'payment_code' => $pd_info['predeposit_payment_code'],
                'pay_amount' => $pd_info['predeposit_amount'],
                'pay_attach' => $type,
            ];
        }
//支付信息
        $payinfo = (new Payment())->getPaymentInfo(['code' => $data['payment_code']]);
        $pay_online = '';
        /* 取得支付信息，生成支付代码 */
        if ($data['pay_amount'] > 0) {
            if ($data['payment_code'] == 'alipay') {
                $alipay = new AlipayTradeService($payinfo);
            } elseif ($data['payment_code'] == 'wx_qrcode') {
                $alipay = new wx_qrcode($payinfo);
            }
            if($data['payment_code']=='wx_qrcode' && !$openid){
                $pay_online = $alipay->requestPayApiH5($data);
            }else{
                $pay_online = $alipay->requestPayApi($data,$openid);
            }
        }

        $this->jsonRet->jsonOutput(0, '', ['show' => $data, 'pay_online' => $pay_online]);
    }


    /**
     * 取消订单
     */
    public function actionMemberOrderCancel()
    {
        $post = SysHelper::postInputData();
        $order_id = isset($post['order_id']) ? $post['order_id'] : 0;
        $model_order = new Order();
        $order_info = $model_order->getOrderInfo(['order_id' => $order_id]);
        $if_allow = $model_order->getOrderOperateState('buyer_cancel', $order_info);
        if (!$if_allow) {
            $this->jsonRet->jsonOutput($this->errorRet['PERMISSION_DENIED']['ERROR_NO'], $this->errorRet['PERMISSION_DENIED']['ERROR_MESSAGE']);
        }
        $result = $model_order->changeOrderStateCancel($order_info, 'buyer', $order_info['buyer_name'], '', true);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['OPERATE_FAIL']['ERROR_NO'], $this->errorRet['OPERATE_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * @return string
     * 确认收货
     */
    public function actionMemberOrderReceivedGoods()
    {

        $post = SysHelper::postInputData();
        $model_order = new Order();
        $order_id = isset($post['order_id']) ? $post['order_id'] : 0;

        $order_info = $model_order->getOrderInfo(['order_id'=>$order_id,'buyer_id'=>$this->member_info['member_id']]);
        if (!$order_info) {
            $this->jsonRet->jsonOutput($this->errorRet['PERMISSION_DENIED']['ERROR_NO'], $this->errorRet['PERMISSION_DENIED']['ERROR_MESSAGE']);
        }

        $if_allow = $model_order->getOrderOperateState('receive', $order_info);
        if (!$if_allow) {
            $this->jsonRet->jsonOutput($this->errorRet['PERMISSION_DENIED']['ERROR_NO'], $this->errorRet['PERMISSION_DENIED']['ERROR_MESSAGE']);
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $data = array('order_id' => $order_info['order_id'], 'order_state' => Yii::$app->params['ORDER_STATE_DONE'], 'complete_time' => time());
            $result = $model_order->updateOrder($data);
            if (!$result) {
                $this->jsonRet->jsonOutput($this->errorRet['OPERATE_FAIL']['ERROR_NO'], $this->errorRet['OPERATE_FAIL']['ERROR_MESSAGE']);
            }

            //更新店铺销量
            $order_goods_num = (new OrderGoods())->getOrderGoodsInfo(['order_id' => $order_info['order_id']], 'sum(order_goods_num) As goods_num')['goods_num'];
            $rs = (new Store())->updateStoreByCondition(['store_sales' => new Expression('store_sales+' . $order_goods_num)], ['store_id' => $order_info['store_id']]);

            if(!$rs){
                throw new \Exception('店铺更新失败');
            }

            //发送奖励
            $model_order->updateOrderReward($order_info['order_id']);

            /*记录日志*/
            $model_order->orderaction($order_info['order_id'], 'buyer', $order_info['buyer_name'], '确认收货', 'ORDER_STATE_DONE');
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '操作成功');

        }
        catch (\Exception $e)
        {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput($this->errorRet['OPERATE_FAIL']['ERROR_NO'], $e->getMessage());
        }
    }

    /**
     * @return string
     * 打印订单
     */
    public function actionMemberOrderPrint()
    {
        $this->layout = 'member_other';
        $get = Yii::$app->request->get();
        $order_model = new Order();
        $shippingLog_model = new ShippingLog();
        $orderId = isset($get['order_id']) ? $get['order_id'] : 0;

        $shippingLog = $shippingLog_model->getShippingLogList(['order_id' => $orderId]);
        $condition['order_id'] = $orderId;
        $info = $order_model->getOrderInfo($condition, ['order_goods']);
        $this->view->title = '打印订单';

        return $this->render('member_order_print', ['info' => $info, 'shippingLog' => $shippingLog]);
    }

    /**
     * @return string
     * 申请售后
     */

    public function actionMemberOrderSalesService()
    {

        $order_model = new Order();
        $OrderGoodsModel = new OrderGoods();
        if (Yii::$app->request->isPost) {
            $post = SysHelper::postInputData();
            $OrderReturnModel = new OrderReturn();

            $order_id = $post['order_id'];
            $order_goods_id = $post['order_goods_id'];
            $reason_info = htmlspecialchars($post['reason_info']);
            $refund_type = intval($post['refund_type']);//退款类型
            $refund_amount = floatval($post['refund_amount']);//退款金额
            $img_upload = isset($post['img_upload']) ? serialize($post['img_upload']) : '';

            if (!$reason_info) {
                $this->jsonRet->jsonOutput(100, '描述不能为空');
            }
            if (!$img_upload) {
                $this->jsonRet->jsonOutput(100, '请上传图片');
            }
            $order_info = $order_model->getOrderInfo(['order_id' => $order_id]);
            $order_goods_info = $OrderGoodsModel->getOrderGoodsInfo(['order_id' => $order_id, 'order_goods_id' => $order_goods_id]);
            if ($order_goods_info['refund_state']) {
                $this->jsonRet->jsonOutput(100, '该商品已经申请售后');
            }
            if ($refund_amount > floatval($order_goods_info['order_goods_pay_price'])) {
                $this->jsonRet->jsonOutput(100, '退款金额不能大于实际订单商品额度');
            }


            $data = array(
                'order_id' => $order_id,
                'order_sn' => $order_info['order_sn'],
                'refund_sn' => SysHelper::buildOrderNo(16),
                'store_id' => $order_info['store_id'],
                'store_name' => $order_info['store_name'],
                'buyer_id' => $order_info['buyer_id'],
                'buyer_name' => $order_info['buyer_name'],
                'goods_id' => $order_goods_id,
                'goods_name' => $order_goods_info['order_goods_name'],
                'goods_num' => $order_goods_info['order_goods_num'],
                'order_goods_price' => $order_goods_info['order_goods_price'],
                'goods_image' => $order_goods_info['order_goods_image'],
                'add_time' => time(),
                'reason_info' => $reason_info,
                'pic_info' => $img_upload,
                'refund_type' => $refund_type,
                'refund_amount' => $refund_amount,
            );
            //插入售后数据
            $OrderReturnModel->insertOrderReturn($data);
            //更改订单商品已申请售后
            //添加后台消息推送
            (new SystemMsg())->insertSystemMsg(['sm_content' => '您有新的一笔退款订单。订单号:' . $order_info['order_sn']]);

            $OrderGoodsModel->updateOrderGoods(['order_rec_id' => $order_goods_info['order_rec_id'], 'refund_state' => 1]);
            $this->jsonRet->jsonOutput(0, '请等待审核');

        } else {
            $get = Yii::$app->request->get();
            $order_id = isset($get['order_id']) ? intval($get['order_id']) : 0;
            $order_goods_id = isset($get['order_goods_id']) ? intval($get['order_goods_id']) : 0;

            $order_info = $order_model->getOrderInfo(['order_id' => $order_id]);
            $order_goods_info = $OrderGoodsModel->getOrderGoodsInfo(['order_id' => $order_id, 'order_goods_id' => $order_goods_id]);

            if ($order_goods_info['refund_state']) {
                return $this->redirect(array('member-service/member-repair-list'));
            }


            $show = ['order_info' => $order_info, 'order_goods_info' => $order_goods_info];
            $this->view->title = '申请售后';

            return $this->render('member_order_sales_service', ['show' => $show]);
        }
    }

    /**
     * @return string
     * 订单评价
     */

    public function actionMemberOrderComment()
    {

        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize']) ? intval($post['pagesize']) : 10;
        $page = isset($post['page']) ? $post['page'] : 1;
        $OrderGoddsModel = new OrderGoods();
        $state = isset($post['state']) ? trim($post['state']) : 'evaluation';
        $condition = ['and'];
        if ($state == 'done') {
            $condition[] = ['A.evaluation_state' => 1, 'A.share_state' => 1];
        } elseif ($state == 'evaluation') {
            $condition[] = ['A.evaluation_state' => 0];
        } elseif ($state == 'share') {
            $condition[] = ['A.share_state' => 0];
        }

        $condition[] = ['A.order_buyer_id' => $this->member_info['member_id']];
        $condition[] = ['B.order_state' => Yii::$app->params['ORDER_STATE_DONE']];
        $totalCount = $OrderGoddsModel->getOrderGoodsCommentCount($condition);
        $order_list = $OrderGoddsModel->getOrderGoodsCommentList($condition, $post['offset'], $post['limit']);

        foreach ($order_list as $k => $v) {
            $order_list[$k]['order_goods_image'] = SysHelper::getImage($v['order_goods_image'], 0, 0, 0, [0, 0], 1);
        }
        //带评价数量，待晒单数量
//        $evalCondtion=['B.order_state'=>Yii::$app->params['ORDER_STATE_DONE'],'A.order_buyer_id'=>$this->member_info['member_id'],'A.evaluation_state'=>0];
//        $evaluationNum = $OrderGoddsModel->getOrderGoodsCommentCount($evalCondtion);
//        $shareCondtion=['B.order_state'=>Yii::$app->params['ORDER_STATE_DONE'],'A.order_buyer_id'=>$this->member_info['member_id'],'A.share_state'=>0];
//        $shareNum = $OrderGoddsModel->getOrderGoodsCommentCount($shareCondtion);

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);


        $show = ['list' => $order_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * @return string
     * 评价
     */
    public function actionMemberOrderCommentView()
    {
        $this->layout = 'member_detail';
        $orderGoodsModel = new OrderGoods();
        $EvaluateModel = new Evaluate();

        $post = SysHelper::postInputData();
        $order_rec_id = $post['order_rec_id'];
        $type = $post['type'];
        $info = $orderGoodsModel->getOrderGoodsInfoAll(['A.order_rec_id' => $order_rec_id]);
        $EvaluateGoodsInfo = $EvaluateModel->getEvaluateGoodsInfo(['geval_ordergoodsid' => $order_rec_id]);
        $EvaluateStoreInfo = $EvaluateModel->getEvaluateStoreInfo(['seval_order_rec_id' => $order_rec_id]);

        //已经添加评论跟晒单
        if ($EvaluateGoodsInfo['geval_content'] && $EvaluateGoodsInfo['geval_image']) {
            $this->jsonRet->jsonOutput($this->errorRet['SHARE_IS_EXIST']['ERROR_NO'], $this->errorRet['SHARE_IS_EXIST']['ERROR_MESSAGE']);
        }

        if ($type == 'share') {
            $img_upload = isset($post['img_upload']) ? $post['img_upload'] : '';
            if (!$img_upload) {
                $this->jsonRet->jsonOutput($this->errorRet['SHARE_IMG_IS_NULL']['ERROR_NO'], $this->errorRet['SHARE_IMG_IS_NULL']['ERROR_MESSAGE']);
            }
            $img_str = '';
            foreach ($img_upload as $base64_image_content) {
                if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
                    $file_type = $result[2];
                    $file_path = Yii::getAlias('@uploads') . '/' . date('Ymd') . '/';
                    if (!file_exists($file_path)) {
                        mkdir($file_path, 0700);
                    }
                    $new_file = $file_path . date('YmdHis') . '_' . rand(1000, 9999) . $this->member_info['member_id'] . "." . $file_type;
                    $ifp = fopen($new_file, "wb");
                    fwrite($ifp, base64_decode(str_replace($result[1], '', $base64_image_content)));
                    fclose($ifp);

                    $avatar = str_replace(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . Yii::getAlias('@root_site')), '', str_replace('\\', '/', $new_file));
                    $img_str .= $avatar . '|';
                }
            }

            $img_str = substr($img_str, 0, -1);
            $geval_data = array(
                'geval_image' => $img_str
            );
            //更改已晒单
            $orderGoodsModel->updateOrderGoods(array('order_rec_id' => $order_rec_id, 'share_state' => 1));
        } else {
            //已经添加分数
            if ($EvaluateStoreInfo) {
                $this->jsonRet->jsonOutput($this->errorRet['EVALUATION_IS_EXIST']['ERROR_NO'], $this->errorRet['EVALUATION_IS_EXIST']['ERROR_MESSAGE']);
            }

            //店铺评论
            $desccredit = isset($post['desccredit']) ? intval($post['desccredit']) : 0;
            $servicecredit = isset($post['servicecredit']) ? intval($post['servicecredit']) : 0;
            $deliverycredit = isset($post['deliverycredit']) ? intval($post['deliverycredit']) : 0;
            $message = trim($post['message']) ? htmlspecialchars($post['message']) : '';
            if (!$desccredit || !$servicecredit || !$deliverycredit || !$message) {

                $this->jsonRet->jsonOutput($this->errorRet['SUBMIT_EVALUATION_IS_NULL']['ERROR_NO'], $this->errorRet['SUBMIT_EVALUATION_IS_NULL']['ERROR_MESSAGE']);
            }

            $seval_data = array(
                'seval_orderid' => $info['order_id'],
                'seval_orderno' => $info['order_sn'],
                'seval_addtime' => time(),
                'seval_storeid' => $info['order_store_id'],
                'seval_storename' => $info['order_store_name'],
                'seval_memberid' => $info['order_buyer_id'],
                'seval_membername' => $info['order_buyer_name'],
                'seval_order_rec_id' => $order_rec_id,
                'seval_desccredit' => $desccredit,
                'seval_servicecredit' => $servicecredit,
                'seval_deliverycredit' => $deliverycredit,
            );
            $EvaluateModel->insertEvaluateStore($seval_data);
            $geval_data = array(
                'geval_content' => $message
            );

            //评价增加金币成长值
            (new Member())->rewardMember($this->member_info['member_username'],'order_comment', $this->member_info['member_grow_points']);

            //更改已评价
            $orderGoodsModel->updateOrderGoods(array('order_rec_id' => $order_rec_id, 'evaluation_state' => 1));
            //添加商品评论量
            (new Goods())->updateGoodsByCondition(['goods_evaluate' => new Expression('goods_evaluate+1')], ['goods_id' => $info['order_goods_id']]);
        }
        //商品评论
        if ($EvaluateGoodsInfo) {
            $geval_data['geval_id'] = $EvaluateGoodsInfo['geval_id'];
            $EvaluateModel->updateEvaluateGoods($geval_data);
        } else {
            $geval_insert = array(
                'geval_orderid' => $info['order_id'],
                'geval_orderno' => $info['order_sn'],
                'geval_ordergoodsid' => $info['order_rec_id'],
                'geval_goodsid' => $info['order_goods_id'],
                'geval_goodsname' => $info['order_goods_name'],
                'geval_goodsprice' => $info['order_goods_price'],
                'geval_goodsimage' => $info['order_goods_image'],
                'geval_scores' => 5,
                'geval_addtime' => time(),
                'geval_storeid' => $info['order_store_id'],
                'geval_storename' => $info['order_store_name'],
                'geval_frommemberid' => $info['order_buyer_id'],
                'geval_frommembername' => $info['order_buyer_name'],
            );
            $geval_insert = array_merge($geval_insert, $geval_data);
            $EvaluateModel->insertEvaluateGoods($geval_insert);
        }

        $this->jsonRet->jsonOutput(0, '提交成功');

    }

    /**
     * @return string
     * 晒单
     */
    public function actionMemberCommentDetail()
    {
        $orderGoodsModel = new OrderGoods();
        $EvaluateModel = new Evaluate();

        $post = SysHelper::postInputData();
        $id = isset($post['order_rec_id']) ? $post['order_rec_id'] : 0;
        $info = $orderGoodsModel->getOrderGoodsInfoAll(['A.order_rec_id' => $id]);
        $EvaluateGoodsInfo = $EvaluateModel->getEvaluateGoodsInfo(['geval_ordergoodsid' => $id]);
        $EvaluateStoreInfo = $EvaluateModel->getEvaluateStoreInfo(['seval_order_rec_id' => $id]);
        $geval_image = [];
        if ($EvaluateGoodsInfo['geval_image']) {
            $img = explode('|', $EvaluateGoodsInfo['geval_image']);
            foreach ($img as $key => $val) {
                $geval_image[] = SysHelper::getImage($val, 0, 0, 0, [0, 0], 1);

            }
        }

        $data = [
            'order_rec_id' => $info['order_rec_id'],
            'order_id' => $info['order_id'],
            'order_goods_id' => $info['order_goods_id'],
            'order_goods_name' => $info['order_goods_name'],
            'order_goods_image' => SysHelper::getImage($info['order_goods_image'], 0, 0, 0, [0, 0], 1),
            'order_store_id' => $info['order_store_name'],
            'order_store_name' => $info['order_store_name'],
            'order_sn' => $info['order_sn'],
            'geval_content' => $EvaluateGoodsInfo['geval_content'],
            'geval_image' => $geval_image,
            'seval_desccredit' => $EvaluateStoreInfo['seval_desccredit'],
            'seval_servicecredit' => $EvaluateStoreInfo['seval_servicecredit'],
            'seval_deliverycredit' => $EvaluateStoreInfo['seval_deliverycredit'],
        ];


        $this->jsonRet->jsonOutput(0, '', $data);
    }
}