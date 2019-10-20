<?php
/**
 * Created by qbt.
 * User: zwq
 * Date: 2017/5/16
 * Time: 15:08
 */

namespace api\modules\v1\controllers;


use MongoDB\Driver\Exception\SSLConnectionException;
use system\services\Area;
use system\services\Goods;
use system\services\GoodsClass;
use system\services\MemberAddress;
use system\services\MemberCoin;
use system\services\MemberCoupon;
use system\services\MemberFollow;
use system\services\MemberGrow;
use system\services\Setting;
use system\services\Store;
use Yii;
use system\services\Member;
use system\services\MemberPredeposit;
use yii\db\Exception;
use yii\db\Expression;
use yii\helpers\Url;
use system\helpers\SysHelper;
use yii\web\Response;
use system\services\Captcha;
use yii\data\Pagination;

class MemberAccountController extends MemberBaseController
{
    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->getMemberAndGradeInfo();
    }

    /**
     * 最新余额
     */
    public function actionMemberAccount()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):10;
        $page = isset($post['page'])?$post['page']:1;
        $account = new MemberPredeposit();
        $condition = ['and'];

        $condition[] = ['member_id' => $this->member_info['member_id']];
        $totalCount = $account->getMemberChangeCount($condition);
        $list = $account->getMemberChangeList($condition, $post['offset'], $post['limit']);

        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);
    }

    /**
     * 充值明细
     */
    public function actionMemberRechargeLog()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):10;
        $page = isset($post['page'])?$post['page']:1;
        $account = new MemberPredeposit();

        $condition = ['and'];
        $condition[] = ['predeposit_payment_state' => 2];
        $condition[] = ['member_id' => $this->member_info['member_id']];

        $totalCount = $account->getMemberChargeCount($condition);
        $list = $account->getMemberChargeList($condition, $post['offset'], $post['limit']);


        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);

    }


    /**
     * 余额提现
     */
    public function actionMemberWithdrawal()
    {

            $post = SysHelper::postInputData();
            $accountModel = new MemberPredeposit();
            $money = isset($post['money'])?intval($post['money']):0;
            if($money<=0){
                $this->jsonRet->jsonOutput($this->errorRet['CASHOUT_NOT_NULL']['ERROR_NO'],$this->errorRet['CASHOUT_NOT_NULL']['ERROR_MESSAGE']);
            }
            if ($money > $this->member_info['available_predeposit']) {
                $this->jsonRet->jsonOutput($this->errorRet['CASHOUT_LACK']['ERROR_NO'],$this->errorRet['CASHOUT_LACK']['ERROR_MESSAGE']);
            }
            if(!$this->member_info['member_bank_name']){
                $this->jsonRet->jsonOutput($this->errorRet['BANK_NOT_BINDING']['ERROR_NO'],$this->errorRet['BANK_NOT_BINDING']['ERROR_MESSAGE']);
            }

            $data['predeposit_sn'] = $accountModel->makeSn();
            $data['member_id'] = $this->member_info['member_id'];
            $data['predeposit_member_name'] = $this->member_info['member_username'];
            $data['predeposit_amount'] = $money;
            $data['predeposit_bank_name'] = $this->member_info['member_bank_name'];
            $data['predeposit_bank_no'] = $this->member_info['member_bank_account'];
            $data['predeposit_bank_user'] = $this->member_info['member_bank_real_name'];
            $data['predeposit_add_time'] = time();


            if ($accountModel->getMemberCashInfo(array('predeposit_sn' => $data['predeposit_sn']))) {
                $this->jsonRet->jsonOutput($this->errorRet['NO_RESUBMIT']['ERROR_NO'],$this->errorRet['NO_RESUBMIT']['ERROR_MESSAGE']);
            }
            if ($accountModel->insertMemberCash($data)) {
                $data_change = array('member_id' => $this->member_info['member_id'], 'member_name' => $this->member_info['member_username'], 'order_sn' => $data['predeposit_sn'], 'amount' => $money);
                $accountModel->changePredeposit('cash_apply', $data_change);
                $this->jsonRet->jsonOutput(0,'提交成功，请等待审核');
            } else {
                $this->jsonRet->jsonOutput($this->errorRet['SUBMIT_FAIL']['ERROR_NO'],$this->errorRet['SUBMIT_FAIL']['ERROR_MESSAGE']);
            }
    }


    /**
     * 优惠卷
     */
    public function actionMemberCoupons()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):10;
        $page = isset($post['page'])?$post['page']:1;
        $type = isset($post['type']) ? $post['type'] : 0;//0 未使用  1已使用  2 已失效



        $couponModel = new MemberCoupon();
        $condition = ['and'];
        $condition[] = ['{{%member_coupon}}.mc_member_id' => $this->member_info['member_id']];
        $condition[] = ['{{%member_coupon}}.mc_state' => 1];
        $condition[] = ['{{%coupon}}.coupon_state' => 1];


        if ($type == 0) {
            $condition[] = ['{{%member_coupon}}.mc_use_time' => null];
        } elseif ($type == 2) {
            $condition[] = ['<', '{{%coupon}}.coupon_end_time', time()];
        }elseif($type == 1){

            $condition[] = ['not',['{{%member_coupon}}.mc_use_time' => null]];
        }

        //列表
        $totalCount = $couponModel->getMemberCouponCount($condition);
        $list = $couponModel->getMemberCouponList($condition, $post['offset'], $post['limit']);
        foreach ($list as $k => $v) {
            //优惠价状态
            if ($v['mc_use_time']) {
                $list[$k]['coupon_state'] = 'done';
            } elseif ($v['coupon_end_time'] < time()) {
                $list[$k]['coupon_state'] = 'late';
            }  else {
                $list[$k]['coupon_state'] = 'doing';
            }
        }


        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);

    }



    /**
     * 关注的商品
     */
    public function actionFollowGoods()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):10;
        $page = isset($post['page']) && !empty($post['page']) ? intval($post['page']) : 1;

        $follow_model = new MemberFollow();

        //获取商品ID
        $arr = $follow_model->getMemberFollowList(array('member_id' => $this->member_info['member_id'], 'fav_type' => 'goods'), '', '', '', 'fav_id');
        $arr = $follow_model->getForeachArray($arr, 'fav_id');
        $where['member_id'] = $this->member_info['member_id'];
        $where['fav_type'] = 'goods';

        $totalCount = $follow_model->getMemberFollowCount($where);

        $goods_where = ['and'];
        $goods_where[] = ['in', 'A.goods_id', $arr];

        //商品
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.goods_market_price,A.goods_price,A.goods_pic,A.goods_sales';
        $list = (new Goods())->getGoodsList($goods_where, $post['offset'], $post['limit'],'',$fields);
        foreach ($list as $key=>$val){
            $list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'],0,0,0,[0,0],1);
        }

        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);
    }

    /**
     * 关注的店铺
     */
    public function actionFollowStores()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):10;
        $page = isset($post['page'])?$post['page']:1;
        $follow_model = new MemberFollow();
        $condition = ['and'];
        $condition[] = ['{{%collect}}.member_id' => $this->member_info['member_id']];
        $condition[] = ['{{%collect}}.fav_type' => 'store'];

        $totalCount = $follow_model->getMemberFollowCount($condition);

        $fields = '{{%store}}.store_id,{{%store}}.store_name,{{%store}}.store_logo,{{%store}}.store_collect';
        $store_list = $follow_model->getMemberFollowStoreList($condition, $post['offset'], $post['limit'],'',$fields,'',0);
        foreach ($store_list as $key=>$val){
            $store_list[$key]['store_logo'] = SysHelper::getImage($val['store_logo'],0,0,0,[0,0],1);
        }

        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$store_list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);

    }

    /**
     * 批量取消关注
     */
    public function actionBatchMemberFollowDelete()
    {
        $post = SysHelper::getInputData();
        if(empty($post['fav_ids']) || empty($post['fav_type'])){
            $this->jsonRet->jsonOutput(1,'参数缺失');
        }
        $fav_ids = explode(',',$post['fav_ids']);
        $result = (new MemberFollow())->deleteMemberFollowByCondition([
            'and',
            ['=','fav_type',$post['fav_type']],
            ['in','fav_id', $fav_ids],
            ['=','member_id',$this->member_info['member_id']]
        ]);
        if ($result > 0) {
            if($post['fav_type'] == 'store'){
                //店铺收藏量减少
                foreach ($fav_ids as $v){
                    (new MemberFollow())->updateStoreCollect($v,0);
                }
            }
            $this->jsonRet->jsonOutput(0,'取消成功');
        } else {
            $this->jsonRet->jsonOutput(1,'取消失败');
        }
    }


    /**
     * 取消关注
     */
    public function actionMemberFollowDelete()
    {
        $post = SysHelper::postInputData();
        if ((new MemberFollow())->deleteMemberFollow(['fav_type' => $post['type'], 'fav_id' => $post['fav_id'], 'member_id' => $this->member_info['member_id']]) > 0) {
            if($post['type'] == 'store'){
                //店铺收藏量减少
                (new MemberFollow())->updateStoreCollect($post['fav_id'],0);
            }
            $this->ajaxRet->ajaxShowNotice('取消成功');
            $this->ajaxRet->ajaxReload();
            $this->ajaxRet->ajaxOutput();
        } else {
            $this->ajaxRet->ajaxShowNotice('取消失败');
            $this->ajaxRet->ajaxOutput();
        }
    }


    /**
     * 收藏商品/店铺
     */
    public function actionFollowAdd()
    {

        $post = SysHelper::postInputData();
        $fav_type = isset($post['fav_type'])?$post['fav_type']:'store';
        $store_id = isset($post['store_id'])?$post['store_id']:0;
        $fav_id = isset($post['fav_id'])?$post['fav_id']:0;
        $log_price = isset($post['log_price'])?$post['log_price']:0;
        $model = new MemberFollow();
        $data = [
            'member_id' => $this->member_info['member_id'],
            'fav_id' => $fav_id,
            'fav_type' => $fav_type,
            'store_id' => 0
        ];
        if(!$fav_id){
            $this->jsonRet->jsonOutput($this->errorRet['COLLECT_IS_NULL']['ERROR_NO'],$this->errorRet['COLLECT_IS_NULL']['ERROR_MESSAGE']);
        }
        $is_exist = $model->getMemberFollowInfo($data);
        if ($is_exist) {
            //取消收藏
            $res = $model->deleteMemberFollow($is_exist['log_id']);
            if ($res) {
                if($post['fav_type'] == 'store'){
                    //店铺收藏量减少
                    (new MemberFollow())->updateStoreCollect($post['fav_id'],0);
                }
                $this->jsonRet->jsonOutput(0,'取消成功',['collect_state'=>0]);
            } else {
                $this->jsonRet->jsonOutput($this->errorRet['CANCEL_FAIL']['ERROR_NO'],$this->errorRet['CANCEL_FAIL']['ERROR_MESSAGE']);
            }
        } else {
            if($post['fav_type'] == 'store') {
                $store_info = (new Store())->getStoreInfo(['store_id' => $fav_id]);
                if (!$store_info) {
                    $this->jsonRet->jsonOutput($this->errorRet['STORE_IS_NOT_EXIST']['ERROR_NO'], $this->errorRet['STORE_IS_NOT_EXIST']['ERROR_MESSAGE']);
                }
                $data['store_name'] = $store_info['store_name'];
                $data['store_id'] = $fav_id;
            }
            //添加收藏
            $data['member_name'] = $this->member_info['member_name'];
            $data['fav_time'] = time();
            $data['log_price'] = $log_price;

            $res = $model->insertMemberFollow($data);
            if ($res) {
                if($post['fav_type'] == 'store'){
                    //店铺收藏量
                    (new MemberFollow())->updateStoreCollect($post['fav_id'],1);
                }
                $this->jsonRet->jsonOutput(0,'添加成功',['collect_state'=>1]);
            } else {
                $this->jsonRet->jsonOutput($this->errorRet['ADD_FAIL']['ERROR_NO'],$this->errorRet['ADD_FAIL']['ERROR_MESSAGE']);
            }
        }

    }



    /**
     * @return string
     * 收货地址
     */
    public function actionMemberAddress()
    {

        $member_address_list = (new MemberAddress())->getMemberAddressList(['ma_member_id' => $this->member_info['member_id']]);
        $area_model = new Area();
        foreach ($member_address_list as $k => $v) {
            $member_address_list[$k]['address'] = $area_model->getAreaTextById($v['ma_area_id'], ' ');
        }
        $this->jsonRet->jsonOutput(0,'',$member_address_list);
    }

    /**
     * @return string
     * 添加收货地址
     */
    public function actionMemberAddressAdd()
    {

        $post = SysHelper::postInputData();
        $member_address_model = new MemberAddress();
        if ($member_address_model->getMemberAddressCount(['ma_member_id' => $this->member_info['member_id']]) >= 20) {
            $this->jsonRet->jsonOutput($this->errorRet['ADDRESS_LIMIT']['ERROR_NO'],$this->errorRet['ADDRESS_LIMIT']['ERROR_MESSAGE']);
        }
        $ma_true_name = isset($post['ma_true_name'])?trim($post['ma_true_name']):'';
        $ma_area_id = isset($post['ma_area_id'])?trim($post['ma_area_id']):0;
        $ma_area_info = isset($post['ma_area_info'])?trim($post['ma_area_info']):'';
        $ma_mobile = isset($post['ma_mobile'])?trim($post['ma_mobile']):'';
        $ma_phone = isset($post['ma_phone'])?trim($post['ma_phone']):'';
        $ma_is_default = isset($post['ma_is_default'])?trim($post['ma_is_default']):0;
        if(!$ma_true_name || !$ma_area_id || !$ma_area_info || !$ma_mobile){
            $this->jsonRet->jsonOutput($this->errorRet['ADDRESS_NOT_NULL']['ERROR_NO'],$this->errorRet['ADDRESS_NOT_NULL']['ERROR_MESSAGE']);
        }

        $data['ma_true_name'] = $ma_true_name;
        $data['ma_area_id'] = $ma_area_id;
        $data['ma_area_info'] = $ma_area_info;
        $data['ma_phone'] = $ma_phone;
        $data['ma_mobile'] = $ma_mobile;
        $data['ma_member_id'] = $this->member_info['member_id'];
        $data['ma_is_default'] = $ma_is_default;

        $result = $member_address_model->insertMemberAddress($data);
        if ($result > 0) {
            if ($ma_is_default) {
                $member_address_model->updateMemberAddressByCondition(['ma_is_default' => 0], ['and',['ma_member_id'=>$this->member_info['member_id']], ['<>', 'ma_id', $result], ['ma_is_default'=>1]]);
            }
            $this->jsonRet->jsonOutput(0,'添加成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['ADD_FAIL']['ERROR_NO'],$this->errorRet['ADD_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * @return string
     * 编辑收货地址
     */
    public function actionMemberAddressEdit()
    {
        $member_address_model = new MemberAddress();

        $post = SysHelper::postInputData();
        $ma_id = isset($post['ma_id'])?$post['ma_id']:0;
        $ma_true_name = isset($post['ma_true_name'])?trim($post['ma_true_name']):'';
        $ma_area_id = isset($post['ma_area_id'])?trim($post['ma_area_id']):0;
        $ma_area_info = isset($post['ma_area_info'])?trim($post['ma_area_info']):'';
        $ma_mobile = isset($post['ma_mobile'])?trim($post['ma_mobile']):'';
        $ma_phone = isset($post['ma_phone'])?trim($post['ma_phone']):'';
        $ma_is_default = isset($post['ma_is_default'])?trim($post['ma_is_default']):0;
        if(!$ma_true_name || !$ma_area_id || !$ma_area_info || !$ma_mobile){
            $this->jsonRet->jsonOutput($this->errorRet['ADDRESS_NOT_NULL']['ERROR_NO'],$this->errorRet['ADDRESS_NOT_NULL']['ERROR_MESSAGE']);
        }
        if(!$ma_id){
            $this->jsonRet->jsonOutput($this->errorRet['ADDRESS_ID_NOT_NULL']['ERROR_NO'],$this->errorRet['ADDRESS_ID_NOT_NULL']['ERROR_MESSAGE']);
        }

        $data['ma_true_name'] = $ma_true_name;
        $data['ma_area_id'] = $ma_area_id;
        $data['ma_area_info'] = $ma_area_info;
        $data['ma_phone'] = $ma_phone;
        $data['ma_mobile'] = $ma_mobile;
        $data['ma_is_default'] = $ma_is_default;
        $data['ma_member_id'] = $this->member_info['member_id'];

        $data['ma_id'] = $ma_id;
        $result = $member_address_model->updateMemberAddress($data);
        if ($result) {
            if ($ma_is_default) {
                $member_address_model->updateMemberAddressByCondition(['ma_is_default' => 0], ['and',['ma_member_id'=>$this->member_info['member_id']], ['<>', 'ma_id', $ma_id], [ 'ma_is_default'=>1]]);
            }
            $this->jsonRet->jsonOutput(0,'更新成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['UPDATE_FAIL']['ERROR_NO'],$this->errorRet['UPDATE_FAIL']['ERROR_MESSAGE']);
        }
    }

    /**
     * @return string
     * 获取收货地址单条信息
     */
    public function actionMemberAddressOne()
    {
        $member_address_model = new MemberAddress();
        $area_model = new Area();
        $post = SysHelper::postInputData();
        $ma_id = isset($post['ma_id'])?$post['ma_id']:0;
        $member_address_info = $member_address_model->getMemberAddressInfo(['ma_id' => $ma_id]);
        if (!$member_address_info) {

            $this->jsonRet->jsonOutput($this->errorRet['ADDRESS_NOT_EXIST']['ERROR_NO'],$this->errorRet['ADDRESS_NOT_EXIST']['ERROR_MESSAGE']);
        } else {
            $member_address_info['address'] = $area_model->getAreaTextById($member_address_info['ma_area_id'], ' ');
           $this->jsonRet->jsonOutput(0,'',$member_address_info);
        }
    }

    /**
     * 删除收货地址
     */
    public function actionMemberAddressDelete()
    {
        $post = SysHelper::postInputData();
        $ma_id = isset($post['ma_id'])?$post['ma_id']:0;
        if(!$ma_id){
            $this->jsonRet->jsonOutput($this->errorRet['ADDRESS_ID_NOT_NULL']['ERROR_NO'],$this->errorRet['ADDRESS_ID_NOT_NULL']['ERROR_MESSAGE']);
        }
        if ((new MemberAddress())->deleteMemberAddress(['ma_id' => $ma_id, 'ma_member_id' => $this->member_info['member_id']]) > 0) {
            $this->jsonRet->jsonOutput(0,'删除成功');
        } else {
            $this->jsonRet->jsonOutput($this->errorRet['DELETE_FAIL']['ERROR_NO'],$this->errorRet['DELETE_FAIL']['ERROR_MESSAGE']);
        }
    }
    /**
     * 地区下级
     */
    public function actionAreaChild()
    {
        $post = SysHelper::postInputData();
        $class_id = isset($post['class_id'])?$post['class_id']:0;
        $model = new Area();
        $list = $model->getAreaList(['area_parent_id'=>$class_id]);
        $this->jsonRet->jsonOutput(0,'',$list);
    }
    /**
     * 地区下级
     */
    public function actionAreaChildByAll()
    {
        $model = new Area();
        $list = $model->getAreaList('','','','','area_name as name,area_id as value,area_parent_id as parent');
        $this->jsonRet->jsonOutput(0,'',$list);
    }


    /**
     * 浏览足迹
     */
    public function actionFootprint()
    {
        $post = SysHelper::postInputData();
        //删除一周前足迹
        $lastweek_end = date("Y-m-d", mktime(23, 59, 59, date("m"), date("d") - date("w") + 7 - 7, date("Y")));
        //(new MemberFollow())->deleteMemberView(['and',['<' , 'browsetime' , $lastweek_end],['member_id'=>$this->member_info['member_id']]]);

        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):0;
        $page = isset($post['page'])?$post['page']:1;

        $follow_model = new MemberFollow();
        $condition = ['and'];
        $condition[] = ['{{%view_rec}}.member_id' => $this->member_info['member_id']];

        $fields = '{{%view_rec}}.browsetime,{{%goods}}.goods_id,{{%goods}}.goods_name,{{%goods}}.goods_description,{{%goods}}.goods_market_price,{{%goods}}.goods_price,{{%goods}}.goods_pic,{{%goods}}.goods_sales';
        $totalCount = count($follow_model->getMemberFollowViewList2($condition,0, '','{{%view_rec}}.browsetime desc',$fields));
        $goods_lists = $follow_model->getMemberFollowViewList2($condition,$post['offset'], $post['limit'],'{{%view_rec}}.browsetime desc',$fields);
        foreach ($goods_lists as $key=>$val){
            /*foreach ($val as $k=>$v){
                $goods_lists[$key][$k]['browsetime'] = date('Y-m-d',$v['browsetime']);;
                $goods_lists[$key][$k]['goods_pic'] = SysHelper::getImage($v['goods_pic'],0,0,0,[0,0],1);
            }*/
            $goods_lists[$key]['browsetime'] = date('Y-m-d',$val['browsetime']);;
            $goods_lists[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'],0,0,0,[0,0],1);
        }
        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$goods_lists,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);

    }

    /**
     * 金币明细
     */
    public function actionMemberGoldCoin()
    {
        $post = SysHelper::postInputData();
        $pageSize = isset($post['pagesize'])?intval($post['pagesize']):0;
        $page = isset($post['page'])?$post['page']:1;

        $type = isset($post['type']) ? intval($post['type']) : 0;
        $condition = ['and'];
        switch ($type)
        {
            case 1:
                //收入
                $condition[] = ['coin_type' => 1];
                break;
            case 2:
                //支出
                $condition[] = ['coin_type' => 2];
                break;
        }

        $condition[] = ['member_id' => $this->member_info['member_id']];
        $coinsModel = new MemberCoin();

        $totalCount = $coinsModel->getMemberCoinCount($condition);
        $list = $coinsModel->getMemberCoinList($condition, $post['offset'], $post['limit'],'','coin_points,coin_addtime,coin_desc');
        foreach ($list as $k=>$v)
        {
            $list[$k]['coin_addtime'] = date('Y-m-d H:i:s',$v['coin_addtime']);
        }

        $page_arr = SysHelper::get_page($pageSize,$totalCount,$page);
        $show = ['list'=>$list,'pages'=>$page_arr];
        $this->jsonRet->jsonOutput(0,'',$show);

    }
}