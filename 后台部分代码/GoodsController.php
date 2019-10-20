<?php

namespace api\modules\v1\controllers;

use system\helpers\SysHelper;
use system\services\Article;
use system\services\DiscountPackage;
use system\services\Evaluate;
use system\services\FullDownGoods;
use system\services\GoodsClass;
use system\services\GoodsClassNav;
use system\services\Guige;
use system\services\Member;
use system\services\MemberFollow;
use system\services\NavigationMobile;
use system\services\Store;
use system\services\StoreNavigation;
use Yii;
use api\modules\v1\controllers\BaseController;
use system\services\AdService;
use system\services\Goods;
use system\services\Brand;
use system\services\Countrie;
use system\services\Bulk;
use system\services\BulkStart;
use system\services\BulkList;
use yii\data\Pagination;


class GoodsController extends BaseController
{

    /**商品列表
     * @param int $class_id 分类id
     * @param string $keyword 搜索关键字
     */
    public function actionGoodsList($class_id=0,$keyword='',$order='A.goods_id desc',$page=1,$brand_id='',$countrie_id='')
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $condition = ['and', ['like', 'A.goods_name', $keyword], ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']]];

        //分类
        if ($class_id) {
            $condition[] = ['A.goods_class_id' => $class_id];
        }

        //品牌
        if ($brand_id) {
            $condition[] = ['A.brand_id' => $brand_id];
        }

        //国家
        if ($countrie_id) {
            $condition[] = ['A.countrie_id' => $countrie_id];
        }

        //商品列表
        $totalCount = $goods_model->getGoodsCount($condition);

        //排序
        $byorder = '';
        if ($order) {
            $byorder = str_replace(array('1', '2', '3', '4'), array('A.goods_sales desc', 'A.goods_sales asc', 'A.goods_price desc', 'A.goods_price asc'), $order);
        }
        //商品
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.goods_market_price,A.goods_price,A.goods_pic,A.goods_sales';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }


        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * @return string 商品详情
     */
    public function actionDetail($goods_id)
    {
        $discount_package_model = new DiscountPackage();
        $goods_model = new Goods();
        $get = SysHelper::getInputData();
        if (!$goods_id) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_NULL']['ERROR_NO'], $this->errorRet['GOODS_NOT_NULL']['ERROR_MESSAGE']);
        }
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.default_guige_id,A.goods_pic,A.goods_price,A.brand_id,A.goods_stock,A.countrie_id,A.goods_sales,A.goods_promotion_type,A.goods_promotion_id,A.goods_full_down_id,E.goods_pic1,E.goods_pic2,E.goods_pic3,E.goods_pic4';
        $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $goods_id], $fields);
        if (!$goods_info) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_EXIST']['ERROR_NO'], $this->errorRet['GOODS_NOT_EXIST']['ERROR_MESSAGE']);
        }
        $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        $goods_info['goods_pic1'] = empty($goods_info['goods_pic1']) ? '' : SysHelper::getImage($goods_info['goods_pic1'], 0, 0, 0, [0, 0], 1);
        $goods_info['goods_pic2'] = empty($goods_info['goods_pic2']) ? '' : SysHelper::getImage($goods_info['goods_pic2'], 0, 0, 0, [0, 0], 1);
        $goods_info['goods_pic3'] = empty($goods_info['goods_pic3']) ? '' : SysHelper::getImage($goods_info['goods_pic3'], 0, 0, 0, [0, 0], 1);
        $goods_info['goods_pic4'] = empty($goods_info['goods_pic4']) ? '' : SysHelper::getImage($goods_info['goods_pic4'], 0, 0, 0, [0, 0], 1);

        //品牌
        $goods_info['brand'] = (new Brand())->getBrandInfo(['brand_id'=>$goods_info['brand_id']],'brand_id,brand_name,brand_pic');
        $goods_info['brand']['brand_pic'] = SysHelper::getImage($goods_info['brand']['brand_pic'], 0, 0, 0, [0, 0], 1);

        //地区
        $goods_info['countries'] = (new Countrie())->getCountrieInfo(['countrie_id'=>$goods_info['countrie_id']],'countrie_pic,countrie_name');
        $goods_info['countries']['countrie_pic'] = SysHelper::getImage($goods_info['countries']['countrie_pic'], 0, 0, 0, [0, 0], 1);

        //获取商品规格
        $goods_info['goods_guige_list'] = (new Guige())->getGuiGeList(['goods_id' => $goods_id], '', '','guige_sort desc','guige_id,guige_name,guige_price,guige_no,guige_num');

        //商品默认规格信息
        $default_guige_info = (new Guige())->getGuiGeInfo(['guige_id' => $goods_info['default_guige_id']], 'guige_name,guige_no');
        $goods_info['default_guige_name'] = $default_guige_info['guige_name'];
        $goods_info['default_guige_no'] = $default_guige_info['guige_no'];

        //促销情况
        $goods_discount_res = $goods_model->getGoodsDiscount($goods_info);
        $goods_info['state'] = 1; //有促销
        if ($goods_discount_res['state'] == 2) {//多规格商品促销
            $preferential = $goods_discount_res['discount_price'];
            foreach ($goods_info['goods_guige_list'] as $k => $v){
                $goods_info['goods_guige_list'][$k]['preferential_price'] = $preferential[$v['guige_id']];
            }
        }else{
            $goods_info['state'] = 0; //没有促销
        }
        //用户评论
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $goods_evaluate = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_id, 'G.geval_state' => 0], 0, 2, '', $fields);

        //总评论数
        $goods_evaluate_count = (new Evaluate())->getEvaluateGoodsCount(['geval_goodsid' => $goods_id, 'geval_state' => 0]);

        //营销信息(满减)
        $goods_info['goods_promotion_full_info'] = (new FullDownGoods())->getFullDownGoodsRule($goods_id,'fd.fd_id,fd.fd_title');

        //拼团
        $now_time = time();
        $bulk_condition = ['and'];
        $bulk_condition[] = ['goods_id'=>$goods_id];
        $bulk_condition[] = ['>=', 'end_time', $now_time];
        $bulk_condition[] = ['<=', 'start_time', $now_time];
        $goods_bulk_list = (new Bulk())->getBulkList($bulk_condition,'','','','');
        $goods_info['goods_bulk_state'] = count($goods_bulk_list)?1:0; //是否参与拼团

        //优惠套餐
        $discount_package = $discount_package_model->getDiscountPackageListByGoodsIdByApi($goods_id);
        //2019-05-13
        foreach ($discount_package as $k => $v){
            $package[$k]['dp_id'] = $v['dp_id'];
            $package[$k]['dp_title'] = $v['dp_title'];
        }

        //收藏该商品
        $is_collect_goods = 0;
        $member_info = $this->user_info;
        if ($member_info && (new MemberFollow())->getMemberFollowCount(['fav_id' => $goods_id, 'fav_type' => 'goods', 'member_id' => $member_info['member_id']]) > 0) {
            $is_collect_goods = 1;
        }
        $goods_info['goods_pic1']?$goods_info['good_pic'][] = $goods_info['goods_pic1']:'';
        $goods_info['goods_pic2']?$goods_info['good_pic'][] = $goods_info['goods_pic2']:'';
        $goods_info['goods_pic3']?$goods_info['good_pic'][] = $goods_info['goods_pic3']:'';
        $goods_info['goods_pic4']?$goods_info['good_pic'][] = $goods_info['goods_pic4']:'';

        //去除无用参数
        unset($goods_info['goods_market_price']);
        unset($goods_info['goods_promotion_type']);
        unset($goods_info['goods_promotion_id']);
        unset($goods_info['goods_full_down_id']);
        unset($goods_info['goods_pic']);
        unset($goods_info['goods_pic1']);
        unset($goods_info['goods_pic2']);
        unset($goods_info['goods_pic3']);
        unset($goods_info['goods_pic4']);
        unset($goods_info['goods_pic4']);

        $data = [
            'goods_info' => $goods_info,
            'is_collect_goods' => $is_collect_goods,
            'goods_evaluate_count' => $goods_evaluate_count,
            'goods_evaluate' => $goods_evaluate,
            'discount_package' => $package,
        ];
        $this->jsonRet->jsonOutput(0, '', $data);
    }

    /**
     * 获取商品评价列表
     */
    public function actionEvaluate()
    {
        $evaluate_model = new Evaluate();
        $get = SysHelper::getInputData();
        $goods_id = isset($get['goods_id']) ? intval($get['goods_id']) : 0;
        $pageSize = isset($get['pagesize']) ? intval($get['pagesize']) : 10;
        $page = isset($get['page']) ? $get['page'] : 1;

        if (!$goods_id) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_NULL']['ERROR_NO'], $this->errorRet['GOODS_NOT_NULL']['ERROR_MESSAGE']);
        }
        $condition = ['geval_goodsid' => $goods_id, 'geval_state' => 0];
        $totalCount = $evaluate_model->getEvaluateGoodsCount($condition);

        //商品
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $list = (new Evaluate())->getEvaluateGoodsAndMemberList(['geval_goodsid' => $goods_id, 'geval_state' => 0], $get['offset'], $get['limit'], '', $fields);

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * 商品分类
     */
    public function actionClass()
    {
        $class_model = new GoodsClass();
        $show = $class_model->getMobileClassList();
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**品牌详情
     * @param $brand_id 品牌id
     */
    public function actionBrandDetail($brand_id){
        $brand_info = (new Brand())->getBrandInfo(['brand_id'=>$brand_id],'brand_id,brand_name,brand_pic,brand_content');
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_info);
    }

    /**
     * 品牌列表
     */
    public function actionBrandList(){
        $brand_list = (new Brand())->getBrandList(['brand_enable'=>1],'','','brand_sort desc','brand_id,brand_name,brand_pic');
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_list);
    }

    /**
     * 地区列表
     */
    public function actionCountriesList(){
        $brand_list = (new Countrie())->getCountrieList('','','','countrie_sort desc','countrie_id,countrie_pic,countrie_name');
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_list);
    }


    /**
     * 拼团列表
     */
    public function actionBulk(){
        $now_time = time();
        $bulk_condition = ['and'];
        $bulk_condition[] = ['>=', 'end_time', $now_time];
        $goods_bulk_list = (new Bulk())->getBulkList($bulk_condition,'','','','');
        foreach ($goods_bulk_list as $k => $v){
            if ($v['start_time']<$now_time){
                $goods_bulk_list[$k]['state'] = 1; //进行中
                $list[$k]['time'] = ceil(($v['end_time']-$now_time)/(24*3600));
            }else{
                $goods_bulk_list[$k]['state'] = 0; //待开团
                $list[$k]['time'] = ceil(($v['start_time']-$now_time)/(24*3600));
            }
            $list[$k]['goods_id'] = $v['goods_id'];
            $list[$k]['bulk_id'] = $v['bulk_id'];
            $list[$k]['num'] = $v['num'];
            $list[$k]['bulk_price'] = $v['bulk_price'];
            $guige_info = (new Guige())->getGuiGeInfo(['guige_id' => $v['guige_id']],'guige_price');
            $list[$k]['price'] = $guige_info['guige_price'];
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['goods_id']],'A.goods_name,A.goods_pic');
            $list[$k]['goods_name'] = $goods_info['goods_name'];
            $list[$k]['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }

    /**拼团限时购详情
     * @param $bulk_id
     */
    public function actionBulkDetail($bulk_id){
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id'=>$bulk_id],'');
        $guige_info = (new Guige())->getGuiGeInfo(['guige_id' => $goods_bulk_info['guige_id']],'guige_price,guige_no,guige_name');
        $info['price'] = $guige_info['guige_price'];
        $info['guige_no'] = $guige_info['guige_no'];
        $info['guige_name'] = $guige_info['guige_name'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_bulk_info['goods_id']]);
        $info['goods_name'] = $goods_info['goods_name'];
        $info['goods_description'] = $goods_info['goods_description'];
//        $info['goods_mobile_content'] = $goods_info['goods_mobile_content'];
        $info['goods_stock'] = $goods_info['goods_stock'];
        $info['goods_sales'] = $goods_info['goods_sales'];
        $info['goods_id'] = $goods_bulk_info['goods_id'];
        $info['bulk_id'] = $goods_bulk_info['bulk_id'];
        $info['num'] = $goods_bulk_info['num'];
        $info['bulk_price'] = $goods_bulk_info['bulk_price'];
        $goods_info['goods_pic1']?$info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic1'], 0, 0, 0, [0, 0], 1):'';
        $goods_info['goods_pic2']?$info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic2'], 0, 0, 0, [0, 0], 1):'';
        $goods_info['goods_pic3']?$info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic3'], 0, 0, 0, [0, 0], 1):'';
        $goods_info['goods_pic4']?$info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic4'], 0, 0, 0, [0, 0], 1):'';

        //用户评论
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $info['goods_evaluate'] = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_bulk_info['goods_id'], 'G.geval_state' => 0], 0, 2, '', $fields);

        //总评论数
        $info['goods_evaluate_count'] = (new Evaluate())->getEvaluateGoodsCount(['geval_goodsid' => $goods_bulk_info['goods_id'], 'geval_state' => 0]);

        //品牌
        $brand = (new Brand())->getBrandInfo(['brand_id'=>$goods_info['brand_id']],'brand_id,brand_name,brand_pic');
        $brand['brand_pic'] = SysHelper::getImage($brand['brand_pic'], 0, 0, 0, [0, 0], 1);
        $info['brand'] = $brand;

        //地区
        $countries = (new Countrie())->getCountrieInfo(['countrie_id'=>$goods_info['countrie_id']],'countrie_pic,countrie_name');
        $countries['countrie_pic'] = SysHelper::getImage($countries['countrie_pic'], 0, 0, 0, [0, 0], 1);
        $info['countrie'] = $countries;

        //距离结束时间（天）
        $info['time'] = ceil(($goods_bulk_info['end_time']-time())/(24*3600));

        //拼团列表
        $bulk_start_list = (new BulkStart())->getBulkStartList(['bulk_id'=>$goods_bulk_info['bulk_id'],'state'=>0],'','','create_time desc','list_id');
        foreach ($bulk_start_list as $k => $v){
            $bulk_list_list = (new BulkList())->getBulkListList(['bulk_id'=>$goods_bulk_info['bulk_id'],'list_id'=>$v['list_id']],'','','create_time asc','member_id,member_state');
            foreach ($bulk_list_list as $k => $v){
                $member_avatar = (new Member())->getMemberInfo(['member_id'=>$v['member_id']],'member_avatar')['member_avatar'];
                $bulk_list_list[$k]['member_avatar'] = SysHelper::getImage($member_avatar, 0, 0, 0, [0, 0], 1);
            }
            $bulk_start_list[$k]['datail'] = $bulk_list_list;
        }
        $info['bulk_list'] = $bulk_start_list;

        $this->jsonRet->jsonOutput(0, '加载成功', $info);
    }

    /**开团 暂未加入订单
     * @param $member_id 用户
     * @param $bulk_id 团购活动id
     */
    public function actionAddBulkStart($bulk_id){
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $start_data['menber_id'] = $member_id;
            $start_data['bulk_id'] = $bulk_id;
            $bulk_info = (new Bulk())->getBulkInfo(['bulk_id'=>$bulk_id,'state'=>1],'num');
            $start_data['need_num'] = $bulk_info['num'];
            $start_data['now_num'] = 1;
            $start_data['create_time'] = time();
            $list_id = (new BulkStart())->insertBulkStart($start_data);
            $list_data['menber_id'] = $member_id;
            $list_data['bulk_id'] = $bulk_id;
            $list_data['list_id'] = $list_id;
            $list_data['create_time'] = time();
            $list_data['member_state'] = 1;
            (new BulkList())->insertBulkList($list_data);
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交失败');
        }
    }

    /** 拼团
     * @param $member_id 用户
     * @param $list_id 团购id
     */
    public function actionAddBulkList($list_id){
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id'=>$list_id],'bulk_id');
        $list_data['menber_id'] = $member_id;
        $list_data['bulk_id'] = $bulk_start_info['bulk_id'];
        $list_data['list_id'] = $list_id;
        $list_data['create_time'] = time();
        $list_data['member_state'] = 0;
        $result = (new BulkList())->insertBulkList($list_data);
        if ($result){
            $this->jsonRet->jsonOutput(0, '提交成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '提交失败');
        }
    }

    public function actionAddAddr(){

    }

    public function actionAddOrder(){
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $order_date['order_sn'] = 'YGH'.date('YmdHis',time()).rand(100,999); //订单编号
        $order_date['buyer_id'] = $member_id; //买家id
        $order_date['store_name'] = 1; //店铺名称 默认1
        $order_date['store_id'] = 1; //店铺id 默认1
        $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
    }

}
