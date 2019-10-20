<?php
/**
 * Created by qbt.
 * User: zwq
 * Date: 2017/5/16
 * Time: 15:08
 */

namespace api\modules\v1\controllers;

use system\services\Goods;
use system\services\ProtocolService;
use system\services\Store;
use system\services\SystemMsg;
use Yii;
use system\services\ReportService;
use system\services\ComplainService;
use system\services\MemberConsult;
use system\services\OrderReturn;
use system\services\OrderReturnLog;
use system\services\StoreReturnAddress;
use yii\helpers\Url;
use system\helpers\SysHelper;
use yii\web\Response;
use yii\data\Pagination;

class MemberServiceController extends MemberBaseController
{

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->getMemberAndGradeInfo();
    }

    /**
     * 我的退货
     */
    public function actionMemberRepairList()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize']) ? intval($post['pagesize']) : 10;
        $page = isset($post['page']) ? $post['page'] : 1;
        $state = isset($post['state']) ? $post['state'] : 'doing';
        $condition = ['and'];
        $condition[] = ['buyer_id' => $this->member_info['member_id']];
        switch ($state) {
            case 'fail':
                $condition[] = ['seller_state' => Yii::$app->params['ORDER_REFUND_SELLER_NOT']];
                break;
            case 'done':
                $condition[] = ['refund_state' => Yii::$app->params['ORDER_REFUND_SUCCESS']];
                break;
            default:
                $condition[] = ['<>', 'refund_state', Yii::$app->params['ORDER_REFUND_SUCCESS']];
                $condition[] = ['<>', 'seller_state', Yii::$app->params['ORDER_REFUND_SELLER_NOT']];
                break;
        }

        $OrderReturnModel = new OrderReturn();
        $totalCount = $OrderReturnModel->getOrderReturnCount($condition);
        $list = $OrderReturnModel->getOrderReturnList($condition, $post['offset'], $post['limit'], "add_time desc");

        foreach ($list as $key => $val) {
            $list[$key]['goods_image'] = SysHelper::getImage($val['goods_image'], 0, 0, 0, [0, 0], 1);

        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);

    }

    /**
     * 我的退货--详情
     */
    public function actionMemberReturnView()
    {
        $OrderReturnModel = new OrderReturn();
        $refundLogModel = new OrderReturnLog();

        $post = SysHelper::postInputData();
        $refund_id = isset($post['refund_id']) ? $post['refund_id'] : 0;

        $condition['refund_id'] = $refund_id;
        $refundInfo = $OrderReturnModel->getOrderReturnInfo($condition);
        //跟踪信息

        $refundLog = $refundLogModel->getOrderReturnLogList($condition);
//        //卖家信息
//        $StoreReturnAddressModel = new StoreReturnAddress();
//        $storeInfo = $StoreReturnAddressModel->getStoreReturnAddressInfo(['sa_id'=>$refundInfo['refund_address_id']]);


        $this->jsonRet->jsonOutput(0, '', ['refundInfo' => $refundInfo, 'refundLog' => $refundLog]);
    }

    /**
     * 我的咨询
     */
    public function actionMemberQuestion()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize']) ? intval($post['pagesize']) : 10;
        $page = isset($post['page']) ? $post['page'] : 1;
        $type = isset($post['type']) ? $post['type'] : '0';
        $condition = ['and'];
        $condition[] = ['member_id' => $this->member_info['member_id']];
        if ($type) {
            $condition[] = ['consult_state' => $type];
        }
        $MemberConsultModel = new MemberConsult();
        $totalCount = $MemberConsultModel->getMemberConsultCount($condition);
        //商品
        $fields = '{{%goods}}.goods_id,{{%goods}}.goods_name,{{%goods}}.goods_description,
        {{%goods}}.goods_market_price,{{%goods}}.goods_price,{{%goods}}.goods_pic,{{%goods}}.goods_sales,{{%member_consult}}.*';
        $list = $MemberConsultModel->getMemberConsultList($condition, $post['offset'], $post['limit'], "consult_addtime desc", $fields);
        foreach ($list as $key => $val) {
            $list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);

    }

    /**
     * 添加咨询
     */
    public function actionMemberQuestionAdd()
    {

        $model = new MemberConsult();
        $post = SysHelper::postInputData();
        $goods_id = $post['goods_id'];
        $consult_content = isset($post['consult_content']) ? trim($post['consult_content']) : '';
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]);
        $store_info = (new Store())->getStoreInfo(['store_id' => $goods_info['store_id']]);
        if (!$consult_content) {
            $this->jsonRet->jsonOutput($this->errorRet['CONTENT_IS_NOT']['ERROR_NO'], $this->errorRet['CONTENT_IS_NOT']['ERROR_MESSAGE']);
        }
        $data = [
            'goods_id' => $goods_id,
            'goods_name' => $goods_info['goods_name'],
            'member_id' => $this->member_info['member_id'],
            'member_name' => $this->member_info['member_name'] ? $this->member_info['member_name'] : $this->member_info['member_username'],
            'store_id' => $goods_info['store_id'],
            'store_name' => $store_info['store_name'],
            'ct_id' => 1,
            'consult_content' => $consult_content,
            'consult_addtime' => time(),
        ];
        if ($model->insertMemberConsult($data)) {
            $this->jsonRet->jsonOutput(0, '提交成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['SUBMIT_FAIL']['ERROR_NO'], $this->errorRet['SUBMIT_FAIL']['ERROR_MESSAGE']);
        }

    }

    /**
     * 我的投诉
     */
    public function actionMemberComplaint()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize']) ? intval($post['pagesize']) : 10;
        $page = isset($post['page']) ? $post['page'] : 1;
        $type = isset($post['type']) ? $post['type'] : '2';
        $condition = ['and'];
        $condition[] = ['c.member_id' => $this->member_info['member_id']];
        if ($type != 2) {
            $condition[] = ['complain_reply_status' => $type];
        }
        $CoinsModel = new ComplainService();
        $totalCount = $CoinsModel->getTotalCount($condition);
        $list = $CoinsModel->getComplainList($condition, $post['offset'], $post['limit'], "complain_addtime desc, complain_id desc");

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * 添加我的投诉
     */
    public function actionMemberComplaintAdd()
    {
        $post = SysHelper::postInputData();
        $accountModel = new ComplainService();
        $complain_content = isset($post['complain_content']) ? trim($post['complain_content']) : '';
        $complain_type = isset($post['complain_type']) ? trim($post['complain_type']) : 1;
        if (!$complain_content) {
            $this->jsonRet->jsonOutput($this->errorRet['CONTENT_IS_NOT']['ERROR_NO'], $this->errorRet['CONTENT_IS_NOT']['ERROR_MESSAGE']);
        }
        $data['member_id'] = $this->member_info['member_id'];
        $data['complain_content'] = $complain_content;
        $data['complain_type'] = $complain_type;
        $data['complain_addtime'] = time();
        if ($accountModel->insertComplain($data)) {
            //添加后台消息推送
            (new SystemMsg())->insertSystemMsg(['sm_content' => '您有新的一笔投诉。用户号:' . $this->member_info['member_username']]);

            $this->jsonRet->jsonOutput(0, '提交成功，请等待审核');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['SUBMIT_FAIL']['ERROR_NO'], $this->errorRet['SUBMIT_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * 举报中心
     */
    public function actionMemberInformant()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize']) ? intval($post['pagesize']) : 10;
        $page = isset($post['page']) ? $post['page'] : 1;
        $type = isset($post['type']) ? $post['type'] : '2';
        $condition = ['and'];
        $condition[] = ['R.member_id' => $this->member_info['member_id']];
        if ($type != 2) {
            $condition[] = ['R.report_reply_status' => $type];
        }
        $CoinsModel = new ReportService();
        $totalCount = $CoinsModel->getTotalCount($condition);
        $list = $CoinsModel->getReportList($condition, $post['offset'], $post['limit'], "R.report_addtime desc, R.report_id desc");


        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);

    }

    /**
     * 添加我的举报
     */
    public function actionMemberInformantAdd()
    {

        $post = SysHelper::postInputData();
        $accountModel = new ReportService();
        $report_object = isset($post['report_object']) ? trim($post['report_object']) : '';
        $report_content = isset($post['report_content']) ? trim($post['report_content']) : '';
        if (!$report_content) {
            $this->jsonRet->jsonOutput($this->errorRet['CONTENT_IS_NOT']['ERROR_NO'], $this->errorRet['CONTENT_IS_NOT']['ERROR_MESSAGE']);
        }
        if (!$report_object) {
            $this->jsonRet->jsonOutput($this->errorRet['REPORT_OBJECT_IS_NOT']['ERROR_NO'], $this->errorRet['REPORT_OBJECT_IS_NOT']['ERROR_MESSAGE']);
        }
        $data['member_id'] = $this->member_info['member_id'];
        $data['report_object'] = $post['report_object'];
        $data['report_content'] = $report_content;
        $data['report_addtime'] = time();

        if ($accountModel->insertReport($data)) {
//添加后台消息推送
            (new SystemMsg())->insertSystemMsg(['sm_content' => '您有新的一条举报信息。用户号:' . $this->member_info['member_username']]);
            $this->jsonRet->jsonOutput(0, '提交成功，请等待审核');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['SUBMIT_FAIL']['ERROR_NO'], $this->errorRet['SUBMIT_FAIL']['ERROR_MESSAGE']);
        }

    }
}