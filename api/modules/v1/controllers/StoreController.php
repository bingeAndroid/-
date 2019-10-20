<?php

namespace api\modules\v1\controllers;
use system\helpers\SysHelper;
use system\services\Navigation;
use system\services\Setting;
use Yii;
use system\services\AdService;
use system\services\Goods;
use system\services\PanicBuy;
use system\services\PanicBuyGoods;
use system\services\GoodsClass;
use system\services\AdSite;
use system\services\FullDown;
use system\services\Article;
use system\services\MemberMail;
use system\services\MemberAddress;
use system\services\DiscountPackage;
use system\services\Evaluate;
use system\services\FullDownGoods;
use system\services\GuiGe;
use system\services\Member;
use system\services\MemberFollow;
use system\services\Brand;
use system\services\Countrie;
use system\services\Bulk;
use system\services\BulkStart;
use system\services\BulkList;
use system\services\Feedback;
use system\services\MemberSearch;
use system\services\Coupon;
use system\services\MemberCoupon;
use system\services\Order;
use system\services\OrderGoods;
use system\services\Area;
use system\services\ShoppingCar;
use system\services\MemberCoin;
use system\services\IntegralOrder;
use system\services\Sign;
use system\services\Express;
use system\services\OrderReturn;
use system\services\MemberPredeposit;
use system\services\FullDownRule;
use system\models\UploadForm;
use yii\web\UploadedFile;
use system\models\MemberDB;



class StoreController extends BaseController
{

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);

        /* 用户登录检测 正式打开 2019-05-15 */
//        $user_info = (new Member())->getMemberInfo(['member_token' => $post['token'],'member_state' => 1]);
//        if (!$user_info) {
//            $this->jsonRet->jsonOutput(-400, 'token已过期，请重新登录');
//        }
//        $this->user_info = $user_info;
        $this->user_info = (new Member())->getMemberInfo(['member_id' => 1]); //正式去掉
    }

    /**
     * 首页
     */
    public function actionIndex()
    {
        $user_info = $this->user_info;
        $goods_model = new Goods();

        //消息 2019-05-08
        $messages_count = (new MemberMail())->getNewMailCount($user_info['member_id']);
        if ($messages_count) {
            $messages_state = 1;
        } else {
            $messages_state = 0;
        }

        //轮播图
        $adv = (new AdService())->getAdList(['ad_ads_id' => 1], '', '', '', 'ad_link,ad_pic');
        foreach ($adv as $key => $val) {
            $adv[$key]['ad_pic'] = SysHelper::getImage($val['ad_pic'], 0, 0, 0, [0, 0], 1);
        }

        //系统公告 2019-05-08
        $article = (new Article())->getArticleList(['a.article_type_id' => 24], '', '2', 'a.article_sort desc', 'article_id,article_title');

        //导航
        $navigation = (new Navigation())->getNavigationList(['nav_location' => 0], '', '', 'nav_sort desc', 'nav_title,nav_pic');
        foreach ($navigation as $key => $val) {
            $navigation[$key]['nav_pic'] = SysHelper::getImage($val['nav_pic'], 0, 0, 0, [0, 0], 1);
        }

        //限时秒杀 当天 2019-05-07
        $time = time();
        $date_h = date('H', time());
        $map = ['and', 'pb_state=2', "pb_start_time<=$time", "pb_end_time>$time", "pb_pbs_end_time>$date_h"];
        $xsms = (new PanicBuy())->getPanicBuyList($map, '', '1', 'pb_pbs_start_time ASC', 'pb_id,pb_pbs_start_time,pb_pbs_end_time');
        $arr = [];
        $seconds_kill = [];
        if ($xsms) {
            $start_time = strtotime($xsms[0]['pb_pbs_start_time']);
            $end_time = strtotime($xsms[0]['pb_pbs_end_time']);
            if ($start_time > $time) {
                $state = 2;//活动即将开始
                $time = $start_time - $time; //距离开始时间
            } else {
                $state = 1;//活动进行中
                $time = $end_time - $time; //距离结束时间
            }
            $array = (new PanicBuyGoods())->getPanicBuyGoodsList(['pbg_pb_id' => $xsms[0]['pb_id'], 'goods_state' => Yii::$app->params['GOODS_STATE_PASS']], '', '', '', 'goods_id,goods_name,goods_price,goods_pic');
            foreach ($array as $k1 => $v1) {
                //限时抢购首页显示优惠价
                $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $v1['goods_id']]);
                $goods_price = $goods_model->getGoodsDiscountAll($goods_info);
                //多规格
                $pbg_price = $goods_price['discount_price'][$goods_info['default_guige_id']];
                $array[$k1]['pbg_price'] = $pbg_price; //取最小价格
                $array[$k1]['goods_pic'] = SysHelper::getImage($v1['goods_pic'], 0, 0, 0, [0, 0], 1);
                $array[$k1]['discount'] = sprintf("%.2f", $pbg_price / $v1['goods_price']) * 10; //折扣
            }
            $arr = array_merge($arr, $array);
            $seconds_kill['state'] = $state;
            $seconds_kill['time'] = date('H:i:s', $time);
            $seconds_kill['list'] = $arr;
        }

        //营销活动 2019-05-07
        $time = time();
        $map = ['and', 'fd_state=1', "fd_start_time<=$time", "fd_end_time>$time"];
        $yxhd = (new FullDown())->getFullDownList($map, '', '5', '', 'fd_id,fd_pic');
        foreach ($yxhd as $k => $v) {
            $yxhd[$k]['fd_pic'] = SysHelper::getImage($v['fd_pic'], 0, 0, 0, [0, 0], 1);
        }


        //分类 2019-05-07
        $good_class = (new GoodsClass())->getGoodsClassList(['class_enable' => 1, 'class_parent_id' => 0], '', '', 'class_sort desc', 'class_name,class_id');
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.default_guige_id';
        foreach ($good_class as $k => $v) {
            $info = (new AdSite())->getAdSiteInfo(['ad_class' => $v['class_id']], 'ads_id');
            $good_class_nav = (new AdService())->getAdList(['ad_ads_id' => $info['ads_id']], '', '1', '', 'ad_pic,ad_link');
            foreach ($good_class_nav as $k1 => $v1) {
                $good_class_nav[$k1]['ad_pic'] = SysHelper::getImage($v1['ad_pic'], 0, 0, 0, [0, 0], 1);
            }
            $list = [];
            $class_info1 = (new GoodsClass())->getGoodsClassList(['class_enable' => 1, 'class_parent_id' => $v['class_id']], '', '', '', 'class_id');
            if ($class_info1) {
                $class_ids1 = implode(',', array_column($class_info1, 'class_id'));
                $class_info2 = (new GoodsClass())->getGoodsClassList("class_enable = 1 and class_parent_id in ($class_ids1)", '', '', '', 'class_id');
                if ($class_info2) {
                    $class_ids2 = implode(',', array_column($class_info2, 'class_id'));
                    $goods_state = Yii::$app->params['GOODS_STATE_PASS'];
                    $list = $goods_model->getGoodsList("A.goods_type = 1 and A.goods_state = $goods_state  and A.goods_class_id in ($class_ids2)", 0, 3, 'goods_sales desc', $fields);
                    foreach ($list as $key => $val) {
                        $pbg_price = $this->activity($val['goods_id']) ?: 0;
                        $list[$key]['pbg_price'] = $pbg_price ? $this->activity($val['goods_id'])[$val['default_guige_id']] : 0;
                        $list[$key]['state'] = $pbg_price ? 1 : 0; //商品是否促销
                        $list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
                    }
                }
            }
            $good_class[$k]['list'] = $list;
            $good_class[$k]['good_class_nav'] = $good_class_nav;
        }

        //热销
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.default_guige_id';
        $hot_goods_list = $goods_model->getGoodsList("A.goods_type = 1 and A.goods_state = 20", 0, 20, 'goods_sales desc', $fields);
        foreach ($hot_goods_list as $key => $val) {
            $pbg_price = $this->activity($val['goods_id']);
            $hot_goods_list[$key]['pbg_price'] = $pbg_price ? $pbg_price[$val['default_guige_id']] : 0;
            $hot_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
            $hot_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }

        //特别推荐
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic';
        $tj_goods_list = $goods_model->getGoodsList(['A.goods_type' => 1, 'A.goods_state' => Yii::$app->params['GOODS_STATE_PASS'], 'goods_hot' => 1], 0, 20, 'RAND()', $fields);
        foreach ($tj_goods_list as $key => $val) {
            $pbg_price = $this->activity($val['goods_id']) ?: 0;
            $tj_goods_list[$key]['pbg_price'] = $pbg_price;
            $tj_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
            $tj_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }
        $list = [
            'messages_state' => $messages_state,
            'adv_list' => $adv,
            'article' => $article,
            'nav_list' => $navigation,
            'seconds_kill' => $seconds_kill,
            'hot_goods_list' => $hot_goods_list,
            'yxhd' => $yxhd,
            'good_class' => $good_class,
            'tj_goods_list' => $tj_goods_list,
        ];
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }

    /**商品列表|商品搜索|热销商品
     * @param int $class_id 分类id
     * @param string $keyword 搜索关键字
     */
    public function actionGoodsList($class_id = 0, $keyword = '', $order = 'A.goods_id desc', $page = 1, $brand_id = '', $countrie_id = '', $low_price = 0, $high_price = 0)
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $condition = ['and', ['like', 'A.goods_name', $keyword], ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['A.goods_type' => 1]];

        //最低价
        if ($low_price) {
            $condition[] = ['>=', 'A.goods_price', $low_price];
        }

        //最高价
        if ($high_price) {
            $condition[] = ['<=', 'A.goods_price', $high_price];
        }

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
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_sales';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            $pbg_price = $this->activity($val['goods_id']) ?: 0;
            $goods_list[$key]['pbg_price'] = $pbg_price;
            $goods_list[$key]['state'] = $pbg_price ? 1 : 0; //是否有优惠
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**营销活动列表
     * @param int $page
     */
    public function actionGoodsFullDownList($page = 1)
    {
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $time = time();
        $map = ['and', 'fd_state=1', "fd_start_time<=$time", "fd_end_time>$time"];
        $yxhd = (new FullDown())->getFullDownList($map, $offset, $pageSize, '', 'fd_id,fd_pic');
        $totalCount = (new FullDown())->getFullDownCount($map);
        foreach ($yxhd as $k => $v) {
            $yxhd[$k]['fd_pic'] = SysHelper::getImage($v['fd_pic'], 0, 0, 0, [0, 0], 1);
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $yxhd, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**营销活动商品列表
     * @param int $class_id 分类id
     * @param string $keyword 搜索关键字
     */
    public function actionGoodsFullDownGoodsList($fd_id, $order = 'A.goods_id desc', $page = 1, $brand_id = '', $countrie_id = '', $low_price = 0, $high_price = 0)
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $yxhd = (new FullDownGoods())->getFullDownGoodsList(['fdg_fd_id' => $fd_id]);
        $ids_arr = array_column($yxhd, 'fdg_goods_id');
        foreach ($yxhd as $k => $v) {
            $yxhd[$k]['fd_pic'] = SysHelper::getImage($v['fd_pic'], 0, 0, 0, [0, 0], 1);
        }

        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['A.goods_type' => 1], ['in', 'A.goods_id', $ids_arr]];

        //最低价
        if ($low_price) {
            $condition[] = ['>=', 'A.goods_price', $low_price];
        }

        //最高价
        if ($high_price) {
            $condition[] = ['<=', 'A.goods_price', $high_price];
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
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_sales';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            $pbg_price = $this->activity($val['goods_id']) ?: 0;
            $goods_list[$key]['pbg_price'] = $pbg_price;
            $goods_list[$key]['state'] = $pbg_price ? 1 : 0; //是否有优惠
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /** 热销商品 暂不用 2019-05-24
     * @param int $class_id
     * @param string $keyword
     * @param string $order
     * @param int $page
     * @param string $brand_id
     * @param string $countrie_id
     * @param int $low_price
     * @param int $high_price
     */
    public function actionSalesGoodsList($class_id = 0, $keyword = '', $order = 'A.goods_sales desc', $page = 1, $brand_id = '', $countrie_id = '', $low_price = 0, $high_price = 0)
    {
        $this->actionGoodsList($class_id, $keyword, $order, $page, $brand_id, $countrie_id, $low_price, $high_price);
    }

    /**
     * @return string 商品详情 常规商品详情|限时秒杀详情
     */
    public function actionDetail($goods_id)
    {
        $discount_package_model = new DiscountPackage();
        $goods_model = new Goods();
        if (!$goods_id) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_NULL']['ERROR_NO'], $this->errorRet['GOODS_NOT_NULL']['ERROR_MESSAGE']);
        }
        $fields = 'A.goods_id,A.goods_name,A.goods_mobile_content,A.default_guige_id,A.goods_pic,A.goods_price,A.brand_id,A.goods_stock,A.countrie_id,A.goods_sales,A.goods_promotion_type,A.goods_promotion_id,A.goods_full_down_id,E.goods_pic1,E.goods_pic2,E.goods_pic3,E.goods_pic4';
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
        $goods_info['brand'] = (new Brand())->getBrandInfo(['brand_id' => $goods_info['brand_id']], 'brand_id,brand_name,brand_pic');
        $goods_info['brand']['brand_pic'] = SysHelper::getImage($goods_info['brand']['brand_pic'], 0, 0, 0, [0, 0], 1);

        //地区
        $goods_info['countries'] = (new Countrie())->getCountrieInfo(['countrie_id' => $goods_info['countrie_id']], 'countrie_id,countrie_pic,countrie_name');
        $goods_info['countries']['countrie_pic'] = SysHelper::getImage($goods_info['countries']['countrie_pic'], 0, 0, 0, [0, 0], 1);

        //获取商品规格
        $goods_info['goods_guige_list'] = (new GuiGe())->getGuiGeList(['goods_id' => $goods_id], '', '', 'guige_sort desc', 'guige_id,guige_name,guige_price,guige_no,guige_num');

        //促销情况
        $goods_discount_res = $goods_model->getGoodsDiscount($goods_info);
        $panic_buy_state = 1; //有促销
        if ($goods_discount_res['state'] == 2) {//多规格商品促销
            $preferential = $goods_discount_res['discount_price'];
            //距离促销结束时间
            if ($goods_info['goods_promotion_type'] == 1) {
                //获取秒杀活动
                $pb_info = (new PanicBuy())->getPanicBuyInfo(['pb_id' => $goods_info['goods_promotion_id']]);
                $to_end_time = strtotime($pb_info['pb_pbs_end_time']) - time();
                $goods_info['to_end_time'] = date('H:i:s', $to_end_time); //距离抢购结束时间
            } elseif ($goods_info['goods_promotion_type'] == 2) {
                //限时折扣 2019-05-16暂未使用
                $pb_info = (new TimeBuy())->getTimeBuyInfo(['tb_id' => $goods_info['goods_promotion_id']]);
            }
        } else {
            $preferential = [];
            $goods_info['to_end_time'] = 0;
            $panic_buy_state = 0; //没有促销
        }
        $goods_info['panic_buy_state'] = $panic_buy_state; //是否有促销

        foreach ($goods_info['goods_guige_list'] as $k => $v) {
            $preferential_price = !empty($preferential[$v['guige_id']]) ? $preferential[$v['guige_id']] : 0;
            $goods_info['goods_guige_list'][$k]['preferential_price'] = $preferential_price;
            if ($v['guige_id'] == $goods_info['default_guige_id']) {
                //商品默认规格信息
                $goods_info['goods_no'] = $v['guige_no'];
                $goods_info['guige_name'] = $v['guige_name'];
                $goods_info['preferential_price'] = $preferential_price;
            }
        }

        //用户评论
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $goods_evaluate = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_id, 'G.geval_state' => 0], 0, 2, '', $fields);

        //总评论数
        $goods_evaluate_count = (new Evaluate())->getEvaluateGoodsCount(['geval_goodsid' => $goods_id, 'geval_state' => 0]);

        //营销信息(满减)
        $goods_promotion_full_info = (new FullDownGoods())->getFullDownGoodsRule($goods_id, 'fd.fd_id,fd.fd_title');

        //拼团
        $now_time = time();
        $bulk_condition = ['and'];
        $bulk_condition[] = ['goods_id' => $goods_id];
        $bulk_condition[] = ['>=', 'end_time', $now_time];
        $bulk_condition[] = ['<=', 'start_time', $now_time];
        $goods_bulk_list = (new Bulk())->getBulkList($bulk_condition, '', '', '', '');
        $goods_bulk_state = count($goods_bulk_list) ? 1 : 0; //是否参与拼团

        //优惠套餐
        $package = [];
        $discount_package = $discount_package_model->getDiscountPackageListByGoodsIdByApi($goods_id);
        //2019-05-13
        foreach ($discount_package as $k => $v) {
            $package[$k]['dp_id'] = $v['dp_id'];
            $package[$k]['dp_title'] = $v['dp_title'];
        }

        //收藏该商品
        $is_collect_goods = 0;
        $member_info = $this->user_info;
        if ($member_info && (new MemberFollow())->getMemberFollowCount(['fav_id' => $goods_id, 'fav_type' => 'goods', 'member_id' => $member_info['member_id']]) > 0) {
            $is_collect_goods = 1;
        }
        $goods_info['goods_pic1'] ? $goods_info['good_pic'][] = $goods_info['goods_pic1'] : '';
        $goods_info['goods_pic2'] ? $goods_info['good_pic'][] = $goods_info['goods_pic2'] : '';
        $goods_info['goods_pic3'] ? $goods_info['good_pic'][] = $goods_info['goods_pic3'] : '';
        $goods_info['goods_pic4'] ? $goods_info['good_pic'][] = $goods_info['goods_pic4'] : '';

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
            'goods_bulk_state' => $goods_bulk_state, //是否参与拼团
            'discount_package_state' => count($package) ? 1 : 0, //是否有优惠套餐
            'discount_package' => $package, //优惠套餐
            'goods_promotion_full_state' => $goods_promotion_full_info ? 1 : 0, //是否有满减
            'goods_promotion_full_info' => $goods_promotion_full_info, //满减
        ];
        $this->jsonRet->jsonOutput(0, '', $data);
    }

    /**
     * 获取商品评价列表
     */
    public function actionEvaluate($goods_id, $page = 1)
    {
        $evaluate_model = new Evaluate();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        if (!$goods_id) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_NULL']['ERROR_NO'], $this->errorRet['GOODS_NOT_NULL']['ERROR_MESSAGE']);
        }
        $condition = ['geval_goodsid' => $goods_id, 'geval_state' => 0];
        $totalCount = $evaluate_model->getEvaluateGoodsCount($condition);

        //商品
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $list = (new Evaluate())->getEvaluateGoodsAndMemberList(['geval_goodsid' => $goods_id, 'geval_state' => 0], $offset, $pageSize, 'geval_addtime desc', $fields);

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * 商品分类
     */
    public function actionClass($parent_id = 0)
    {
        $class_model = new GoodsClass();
        $show = $class_model->getGoodsClassList(['class_parent_id' => $parent_id], '', '', 'class_sort desc', 'class_id,class_name,class_pic');
        foreach ($show as $key => $val) {
            $show[$key]['class_pic'] = SysHelper::getImage($val['class_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**品牌详情
     * @param $brand_id 品牌id
     */
    public function actionBrandDetail($brand_id)
    {
        $brand_info = (new Brand())->getBrandInfo(['brand_id' => $brand_id], 'brand_id,brand_name,brand_pic,brand_content');
        $brand_info['brand_pic'] = SysHelper::getImage($brand_info['brand_pic'], 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_info);
    }

    /**
     * 品牌列表
     */
    public function actionBrandList()
    {
        $brand_list = (new Brand())->getBrandList(['brand_enable' => 1], '', '', 'brand_sort desc', 'brand_id,brand_name,brand_pic');
        foreach ($brand_list as $key => $val) {
            $brand_list[$key]['brand_pic'] = SysHelper::getImage($val['brand_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_list);
    }

    /**
     * 地区列表
     */
    public function actionCountriesList()
    {
        $countrie_list = (new Countrie())->getCountrieList('', '', '', 'countrie_sort desc', 'countrie_id,countrie_pic,countrie_name');
        foreach ($countrie_list as $key => $val) {
            $countrie_list[$key]['countrie_pic'] = SysHelper::getImage($val['countrie_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $countrie_list);
    }

    /**
     * 我的地址列表
     */
    public function actionAddrList()
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $addr_list = (new MemberAddress())->getMemberAddressList(['ma_member_id' => $member_id], '', '', '', '');
        foreach ($addr_list as $k => $v) {
            $member_address = (new Area())->lowGetHigh($v['ma_area_id']);
            $list[$k]['ma_id'] = $v['ma_id'];
            $list[$k]['ma_true_name'] = $v['ma_true_name'];
            $list[$k]['ma_addr'] = $member_address . $v['ma_area_info'];
            $list[$k]['ma_mobile'] = $v['ma_mobile'];
            $list[$k]['ma_is_default'] = $v['ma_is_default'];
            $list[$k]['ma_label'] = $v['ma_label'];
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }

    /** 添加收货地址
     * @param $ma_true_name 身份证姓名
     * @param $ma_mobile 手机号
     * @param $ma_area_id 省市区id
     * @param $ma_area_info 详细地址
     * @param $ma_label 标签
     * @param $ma_card_no 身份证号
     * @param $ma_card1 身份证正面
     * @param $ma_card2 身份证反面
     * @param $ma_is_default 是否默认地址
     */
    public function actionAddAddr($ma_true_name, $ma_mobile, $ma_area_id, $ma_area_info, $ma_label = '', $ma_card_no, $ma_card1, $ma_card2, $ma_is_default = 0)
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $addr_data['ma_member_id'] = $member_id;
        $addr_data['ma_mobile'] = $ma_mobile;
        $addr_data['ma_true_name'] = $ma_true_name;
        $addr_data['ma_area_id'] = $ma_area_id;
        $addr_data['ma_area_info'] = $ma_area_info;
        $addr_data['ma_label'] = $ma_label;
        $addr_data['ma_card_no'] = $ma_card_no;
        $addr_data['ma_card1'] = $ma_card1;
        $addr_data['ma_card2'] = $ma_card2;
        $addr_data['ma_is_default'] = $ma_is_default;
        $result = (new MemberAddress())->insertMemberAddress($addr_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**上传图片
     * @return string|void
     */
    public function actionUpImg()
    {
        $file = $_FILES['file'];//得到传输的数据
        $name = $file['name'];
        $type = strtolower(substr($name, strrpos($name, '.') + 1)); //得到文件类型，并且都转化成小写
        $allow_type = array('jpg', 'jpeg', 'gif', 'png'); //定义允许上传的类型
        if (!in_array($type, $allow_type)) {
            $this->jsonRet->jsonOutput(-2, '文件不合法');
        }
        $upload_path = "../../data/uploads/"; //上传文件的存放路径
        $new_file_name = date('YmdHis') . rand(100, 999) . '.' . $type;
        //开始移动文件到相应的文件夹
        if (move_uploaded_file($file['tmp_name'], $upload_path . $new_file_name)) {
            $this->jsonRet->jsonOutput(0, '上传成功', ['filename' => "/data/uploads/" . $new_file_name]);
        } else {
            $this->jsonRet->jsonOutput(-1, '上传失败');
        }
    }

    /**
     * 地址详情
     * @param $ma_id 地址id
     */
    public function actionAddrDetail($ma_id)
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $addr_detail = (new MemberAddress())->getMemberAddressInfo(['ma_member_id' => $member_id, 'ma_id' => $ma_id]);
        if (!$addr_detail) {
            $this->jsonRet->jsonOutput(-1, '参数错误');
        }
        $member_address = (new Area())->lowGetHigh($addr_detail['ma_area_id']);
        $detail['ma_id'] = $addr_detail['ma_id'];
        $detail['ma_true_name'] = $addr_detail['ma_true_name'];
        $detail['ma_addr'] = $member_address . $addr_detail['ma_area_info'];
        $detail['ma_mobile'] = $addr_detail['ma_mobile'];
        $detail['ma_is_default'] = $addr_detail['ma_is_default'];
        $detail['ma_label'] = $addr_detail['ma_label'];
        $detail['ma_card_no'] = $addr_detail['ma_card_no'];
        $detail['ma_card1'] = SysHelper::getImage($addr_detail['ma_card1'], 0, 0, 0, [0, 0], 1);
        $detail['ma_card2'] = SysHelper::getImage($addr_detail['ma_card2'], 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '加载成功', $detail);
    }

    /**修改收货地址
     * @param $ma_id
     * @param $ma_true_name 身份证姓名
     * @param $ma_mobile 手机号
     * @param $ma_area_id 省市区id
     * @param $ma_area_info 详细地址
     * @param $ma_label 标签
     * @param $ma_card_no 身份证号
     * @param $ma_card1 身份证正面
     * @param $ma_card2 身份证反面
     * @param $ma_is_default 是否默认地址
     */
    public function actionEditAddr($ma_id, $ma_true_name = '', $ma_mobile = '', $ma_area_id = '', $ma_area_info = '', $ma_label = '', $ma_card_no = '', $ma_card1 = '', $ma_card2 = '', $ma_is_default = 0)
    {
        $addr_data['ma_id'] = $ma_id;
        $ma_mobile ? $addr_data['ma_mobile'] = $ma_mobile : '';
        $ma_true_name ? $addr_data['ma_true_name'] = $ma_true_name : '';
        $ma_area_id ? $addr_data['ma_area_id'] = $ma_area_id : '';
        $ma_area_info ? $addr_data['ma_area_info'] = $ma_area_info : '';
        $ma_label ? $addr_data['ma_label'] = $ma_label : '';
        $ma_card_no ? $addr_data['ma_card_no'] = $ma_card_no : '';
        $ma_card1 ? $addr_data['ma_card1'] = $ma_card1 : '';
        $ma_card2 ? $addr_data['ma_card2'] = $ma_card2 : '';
        $ma_is_default ? $addr_data['ma_is_default'] = $ma_is_default : '';
        $result = (new MemberAddress())->updateMemberAddress($addr_data);
        if ($ma_is_default == 1) {
            (new MemberAddress())->updateMemberAddressByCondition(['ma_is_default' => 0], ['!=', 'ma_id', $ma_id]);
        }
        if ($result !== false) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**删除收货地址
     * @param $ma_id
     */
    public function actionDelAddr($ma_id)
    {
        $result = (new MemberAddress())->deleteMemberAddress($ma_id);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**提交推荐码
     * @param $recommended_no
     */
    public function actionBindRecommended($recommended_no)
    {
        $member_info = $this->user_info;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $id = substr($recommended_no, 8); //默认id前加8位字符串
            $member = (new Member())->getMemberInfo(['member_id' => $id]);
            if ($member_info['member_recommended_no']) {
                $this->jsonRet->jsonOutput(-2, '你已绑定推荐码');
            }
            if (!$member) {
                $this->jsonRet->jsonOutput(-3, '推荐码错误');
            }
            $member_id = $member_info['member_id'];
            $member_data1['member_id'] = $member_id;
            $member_data1['member_recommended_no'] = $id;
            (new Member())->updateMember($member_data1);
            //更新导游用户信息
            $member_recommended_arr = explode('|', $member['member_recommended']);
            array_push($member_recommended_arr, $member_id);
            $member_data2['member_id'] = $member_id;
            $member_data2['member_recommended'] = implode('|', $member_recommended_arr);
            (new Member())->updateMember($member_data2);
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '操作成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**
     * 我的资料
     */
    public function actionMy()
    {
        $member_info = $this->user_info;
        $data['member_avatar'] = SysHelper::getImage($member_info['member_avatar'], 0, 0, 0, [0, 0], 1);
        $data['member_name'] = $member_info['member_name'];
        $data['member_mobile'] = $member_info['member_mobile'];
        $data['member_sex'] = $member_info['member_sex'];
        $this->jsonRet->jsonOutput(0, '加载成功', $data);
    }

    /**修改我的资料
     * @param $member_avatar
     * @param $member_name
     * @param $member_sex
     */
    public function actionUpdateMy($member_avatar, $member_name, $member_sex)
    {
        $member_info = $this->user_info;
        $member_data['member_id'] = $member_info['member_id'];
        $member_data['member_avatar'] = $member_avatar;
        $member_data['member_name'] = $member_name;
        $member_data['member_sex'] = $member_sex;
        $result = (new Member())->updateMember($member_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**修改密码
     * @param $old_pwd
     * @param $new_pwd
     */
    public function actionEditPassword($old_pwd, $new_pwd)
    {
        $member_info = $this->user_info;
        $old_pwd = md5($old_pwd);
        $info = (new Member())->getMemberInfo(['member_password' => $old_pwd, 'member_id' => $member_info['member_id']]);
        if (!$info) {
            $this->jsonRet->jsonOutput(-2, '原密码不正确');
        }
        $member_data['member_id'] = $member_info['member_id'];
        $member_data['member_password'] = md5($new_pwd);
        $result = (new Member())->updateMember($member_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }

    }

    /**修改手机号码
     * @param $mobile
     * @param $code
     */
    public function actionUpdateMobile($mobile, $code)
    {
        $sms_condition = ['and'];
        $sms_condition[] = ['member_mobile' => $mobile];
        $sms_condition[] = ['type' => 0];
        $sms_condition[] = ['code' => $code];
        $sms_condition[] = ['>=', 'time', time() - 10 * 60];
        if (!(new SmsCode())->getSmsCodeInfo($sms_condition)) {
            $this->jsonRet->jsonOutput(-3, '验证码错误或已过期');
        }
        $member_info = $this->user_info;
        $member_data['member_id'] = $member_info['member_id'];
        $member_data['member_mobile'] = $mobile;
        $result = (new Member())->updateMember($member_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        }
        $this->jsonRet->jsonOutput(-1, '操作失败');
    }

    /**
     * 关于游购会
     */
    public function actionAbouUs()
    {
        $info = (new Article())->getArticleInfo(['article_id' => 24], 'article_title,article_content');
        if ($info) {
            $this->jsonRet->jsonOutput(0, '加载成功', $info);
        }
        $this->jsonRet->jsonOutput(-1, '加载成功');
    }

    /**
     * 帮助中心
     */
    public function actionHelpCenter()
    {
        $helt_list = (new Article())->getArticleList(['a.article_type_id' => 25], '', '', 'a.article_sort desc', 'article_title,article_content');
        if ($helt_list) {
            $this->jsonRet->jsonOutput(0, '加载成功', $helt_list);
        }
        $this->jsonRet->jsonOutput(-1, '加载成功');
    }

    /**意见反馈
     * @param $content1 产品建议
     * @param $content2 运输建议
     * @param $content3 平台建议
     */
    public function actionFeedback($content1, $content2, $content3, $name, $mobile)
    {
        if (strlen($content1) > 255) {
            $this->jsonRet->jsonOutput(-2, '产品建议文字过长');
        }
        if (strlen($content2) > 255) {
            $this->jsonRet->jsonOutput(-2, '运输建议文字过长');
        }
        if (strlen($content3) > 255) {
            $this->jsonRet->jsonOutput(-2, '平台建议文字过长');
        }
        //$member_info = $this->user_info;
        //$feedback_data['member_id'] = $member_info['member_id'];
        $feedback_data['content1'] = $content1;
        $feedback_data['content2'] = $content2;
        $feedback_data['content3'] = $content3;
        $feedback_data['name'] = $name;
        $feedback_data['mobile'] = $mobile;
        $feedback_data['create_time'] = time();
        $result = (new Feedback())->insertFeedback($feedback_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        }
        $this->jsonRet->jsonOutput(-1, '操作失败');
    }

    /**
     * 客服中心
     */
    public function actionKefuCenter()
    {
        $list = (new Setting())->getKefuCenter();
        $info = array_combine(array_column($list, 'name'), array_column($list, 'value'));
        $info['store_qcode'] = SysHelper::getImage($info['store_qcode'], 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '操作成功', $info);
    }

    /**
     * 最近搜索|热门搜索
     */
    public function actionSearchStr()
    {
        $list['search'] = (new MemberSearch())->getMemberSearchList('', '', '5', 'create_time desc', 'str');
        $host_info = (new Setting())->getSetting('index_search_tip');
        $list['host_search'] = explode('|', $host_info);
        $this->jsonRet->jsonOutput(0, '操作成功', $list);
    }

    /**我的收藏
     * @param int $page
     */
    public function actionMyCollect($page = 1)
    {
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $member_info = $this->user_info;
        $collect_list = (new MemberFollow())->getMemberFollowList(['member_id' => $member_info['member_id'], 'fav_type' => 'goods'], $offset, $pageSize, 'fav_time desc', 'fav_id');
        foreach ($collect_list as $k => $v) {
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['fav_id']], 'A.goods_id,A.goods_price,A.goods_pic,A.goods_name,A.default_guige_id');
            $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
            $goods_info['log_id'] = $v['log_id'];
            //商品是否促销 暂不用
            //$goods_info['pbg_price'] = $this->activity($goods_info['goods_id'])?$this->activity($goods_info['goods_id'])[$goods_info['default_guige_id']]:null;
            //$goods_info['state'] = $this->activity($goods_info['goods_id'])?1:0; //商品是否促销
            $collect_list[$k] = $goods_info;
        }
        $totalCount = (new MemberFollow())->getMemberFollowCount(['member_id' => $member_info['member_id'], 'fav_type' => 'goods']);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $collect_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '操作成功', $show);
    }

    /**删除收藏
     * @param $log_ids 收藏id拼接 如 ： 1,5,8
     */
    public function actionDelCollect($log_ids)
    {
        $log_id_arr = explode(',', $log_ids);
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $condition = ['and'];
        $condition[] = ['member_id' => $member_id];
        $condition[] = ['in', 'log_id', $log_id_arr];
        $result = (new MemberFollow())->deleteMemberFollowByCondition($condition);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**
     * 我的分销
     */
    public function actionMyDistribution()
    {
        $member_info = $this->user_info;
        $time = strtotime(date('Y-m'));
        $user['img'] = SysHelper::getImage($member_info['member_avatar'], 0, 0, 0, [0, 0], 1);
        $user['member_name'] = $member_info['member_name'];
        $user['qr_code'] = 'YGH' . rand(10000, 99999) . $member_info['member_id'];
        $predeposit = (new MemberPredeposit())->getMemberChangeList(['member_id' => $member_info['member_id'], 'predeposit_type' => 'brokerage'], '', '', '', "SUM(predeposit_av_amount) as all_predeposit ");
        $predeposit1 = (new MemberPredeposit())->getMemberChangeList("member_id={$member_info['member_id']} and predeposit_type = 'brokerage' and predeposit_add_time>=$time", '', '', '', "SUM(predeposit_av_amount) as all_predeposit ");
        $data['all_predeposit'] = $predeposit[0]['all_predeposit'] ?: 0; //累计收益
        $data['now_predeposit'] = $predeposit1[0]['all_predeposit'] ?: 0; //本月收益
        $data['available_predeposit'] = $member_info['available_predeposit'] ?: 0; //可提现收益
        $this->jsonRet->jsonOutput(0, '操作成功', $data);
    }

    /**
     * 分销二维码
     */
    public function actionMyQrCode()
    {
        $member_info = $this->user_info;
        $info['qr_code_url'] = "";
        $info['qr_code'] = 'YGH' . rand(10000, 99999) . $member_info['member_id'];
        $this->jsonRet->jsonOutput(0, '操作成功', $info);
    }

    /**
     * 我的粉丝
     */
    public function actionMyFans()
    {
        $member_info = $this->user_info;
        $fans_count = (new Member())->getMemberCount(['member_recommended_no' => $member_info['member_id']]); //总粉丝数
        $time = time() - 7 * 24 * 3600;
        $fans_count1 = (new Member())->getMemberCount("member_recommended_no = {$member_info['member_id']} and member_time>=$time");
        $predeposit = (new MemberPredeposit())->getMemberChangeList(['member_id' => $member_info['member_id'], 'predeposit_type' => 'brokerage'], '', '', '', "SUM(predeposit_av_amount) as all_predeposit ");
        //取最近5条佣金收益
        $order_list = (new MemberPredeposit())->getMemberChangeList(['predeposit_type' => 'brokerage'], '', '5', 'predeposit_add_time desc');
        foreach ($order_list as $k => $v) {
            $info = (new Order())->getOrderInfo(['order_id' => $v['order_id']], ['member']);
            $list[$k]['order_sn'] = $info['order_sn'];
            $list[$k]['order_amount'] = $info['order_amount'];
            $list[$k]['commission_amount'] = $info['commission_amount'];
            $list[$k]['member_info']['name'] = $info['extend_member']['member_name'];
            $list[$k]['member_info']['member_avatar'] = SysHelper::getImage($info['extend_member']['member_avatar'], 0, 0, 0, [0, 0], 1);
            $list[$k]['member_info']['member_time'] = date('Y-m-d', $info['extend_member']['member_time']);
        }
        $data['all_predeposit'] = $predeposit[0]['all_predeposit']; //累计收益
        $data['all_fans_count'] = $fans_count; //总粉丝数
        $data['fans_count'] = $fans_count1; //近七天粉丝数
        $data['order_list'] = $list; //最近5条佣金收益
        $this->jsonRet->jsonOutput(0, '操作成功', $data);
    }

    /**
     * 限时秒杀
     */
    public function actionSecondsKill($type = 1, $page = 1)
    {
        $k = $type - 1;
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        //当天限时抢购
        $time = time();
        $date_h = date('H', time());
        $map = ['and', 'pb_state=2', "pb_start_time<=$time", "pb_end_time>$time", "pb_pbs_end_time>$date_h"];
        $xsms = (new PanicBuy())->getPanicBuyList($map, '', '2', 'pb_pbs_start_time ASC', 'pb_id,pb_pbs_start_time,pb_pbs_end_time');
        $arr = [];
        $start_time = strtotime($xsms[$k]['pb_pbs_start_time']);
        $end_time = strtotime($xsms[$k]['pb_pbs_end_time']);
        if ($start_time > $time) {
            $state = 2;//活动即将开始
            $time = date("H:i:s", $start_time - $time); //距离开始时间
        } else {
            $state = 1;//活动进行中
            $time = date("H:i:s", $end_time - $time); //距离结束时间
        }
        $array = (new PanicBuyGoods())->getPanicBuyGoodsList(['pbg_pb_id' => $xsms[$k]['pb_id'], 'goods_state' => Yii::$app->params['GOODS_STATE_PASS']], $offset, '', '', 'goods_id,goods_name,goods_stock,goods_price,goods_pic,pbg_stock');
        foreach ($array as $k => $v) {
            //限时抢购首页显示优惠价
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['goods_id']]);
            $goods_price = (new Goods())->getGoodsDiscountAll($goods_info);
            //多规格
            $pbg_price = $goods_price['discount_price'][$goods_info['default_guige_id']];
            $array[$k]['pbg_price'] = $pbg_price; //取最小价格
            $array[$k]['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
        }
        $arr = array_merge($arr, $array);
        $panic_buy['state'] = $state;
        $panic_buy['time'] = $time;
        $panic_buy['goods_list'] = $arr;

        $totalCount = (new PanicBuyGoods())->getPanicBuyGoodsCount(['pbg_pb_id' => $xsms[$k]['pb_id'], 'goods_state' => Yii::$app->params['GOODS_STATE_PASS']]);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $panic_buy, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '操作成功', $show);
    }

    /**领取优惠券
     * @param $coupon_id
     */
    public function actionGetCoupon($coupon_id)
    {
        $member_info = $this->user_info;
        $now_time = time();
        $coupon_info = (new Coupon())->getCouponInfo(['and', "coupon_id=$coupon_id", "coupon_end_time > $now_time"]);
        if (!$coupon_info) {
            $this->jsonRet->jsonOutput(-2, '优惠券已过期');
        }
        if ($coupon_info['coupon_grant_num'] == $coupon_info['coupon_num']) {
            $this->jsonRet->jsonOutput(-3, '优惠券已领完');
        }
        $member_coupon_count = (new MemberCoupon())->getMemberCouponCount(['mc_member_id' => $member_info['member_id'], 'mc_coupon_id' => $coupon_id]);
        if ($member_coupon_count == $coupon_info['coupon_limit']) {
            $this->jsonRet->jsonOutput(-4, '该优惠券领取次数已超限');
        }
        $coupon_data['mc_member_id'] = $member_info['member_id'];
        $coupon_data['mc_member_username'] = $member_info['member_name']; //领取用户昵称
        $coupon_data['mc_coupon_id'] = $coupon_id;
        $coupon_data['mc_receive_time'] = time();
        $coupon_data['mc_start_time'] = $coupon_info['coupon_start_time'];
        $coupon_data['mc_end_time'] = $coupon_info['coupon_end_time'];
        $result = (new MemberCoupon())->insertMemberCoupon($coupon_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        }
        $this->jsonRet->jsonOutput(-1, '操作失败');
    }

    /**
     * 购物车
     */
    public function actionShopCar()
    {
        $member_info = $this->user_info;
        $shop_car_list = (new ShoppingCar())->getShoppingCarList(['buyer_id' => $member_info['member_id']], '', '', 'cart_id desc', 'S.cart_id,S.goods_price,S.goods_num,S.goods_id,S.goods_image,S.goods_spec_id,S.goods_name');
        $this->jsonRet->jsonOutput(0, '加载成功', $shop_car_list);
    }

    /**添加商品到购物车
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $num 购买数量
     */
    public function actionAddCar($goods_id, $guige_id, $num)
    {
        $member_info = $this->user_info;
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够，请重新添加！');
        }
        $car_info = (new ShoppingCar())->getShoppingCarInfo(['S.buyer_id' => $member_info['member_id'], 'S.goods_id' => $goods_id, 'S.goods_spec_id' => $guige_id], 'cart_id,goods_num');
        if ($car_info) {
            //该商品的该规格已存在购物车
            $car_data['goods_num'] = $num + $car_info['goods_num'];
            $car_data['cart_id'] = $car_info['cart_id'];
            $result = (new ShoppingCar())->updateShoppingCar($car_data);
            if ($result) {
                $this->jsonRet->jsonOutput(0, '添加成功');
            } else {
                $this->jsonRet->jsonOutput(-1, '添加失败');
            }
        }
        $car_data['buyer_id'] = $member_info['member_id'];
        $car_data['goods_id'] = $goods_id;
        $car_data['goods_spec_id'] = $guige_id;
        $car_data['goods_num'] = $num;
        $result = (new ShoppingCar())->insertShoppingCar($car_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '添加成功');
        }
    }

    /**购物车结算页面
     * @param $cart_ids 购物车id拼接
     */
    public function actionCarToPrepare($cart_ids)
    {
        $cart_id_arr = explode(',', $cart_ids);
        $car_where = ['and'];
        $car_where[] = ['in', 'cart_id', $cart_id_arr];
        $car_list = (new ShoppingCar())->getShoppingCarList($car_where, '', '', '', 'G.goods_name,G.goods_id,S.cart_id,S.goods_num,S.goods_spec_id,G.goods_name,GG.guige_name,GG.guige_price'); //所选购物车记录
        $goods_amount = 0;
        $now_time = time();
        foreach ($car_list as $key => $value) {
            $guige_id = $value['goods_spec_id']; //规格
            $num = $value['goods_num']; //数量
            $goods_id = $value['goods_id']; //商品
            $member_info = $this->user_info;
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
            if ($guige_info['guige_num'] - $num < 0) {
                $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
            }
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
            $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
            $discount_price = 0;
            $state = 0;
            if ($goods_discount_res['state'] != 0) {
                $discount_price = $goods_discount_res['discount_price'][$guige_id];
                $state = 1; //商品有促销活动
                //正在参加促销活动，使用促销价
                $goods_amount += $goods_discount_res['discount_price'][$guige_id] * $num;
            } else {
                //未参加促销活动，使用该规格相应的价格
                $goods_amount += $guige_info['guige_price'] * $num;
            }
            //商品信息
            list($goods[$key]['goods_id'], $goods[$key]['goods_name'], $goods[$key]['goods_pic'], $goods[$key]['price'], $goods[$key]['discount_price'], $goods[$key]['num'], $goods[$key]['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), $guige_info['guige_price'], $discount_price, $num, $state];
        }

        //获取可用优惠券 暂不使用 购物车商品暂不可用优惠券 2019-05-24
        $member_coupon_where = ['and'];
        $member_coupon_where[] = ['A.mc_member_id' => $member_info['member_id']];
        $member_coupon_where[] = ['A.mc_state' => 1];
        $member_coupon_where[] = ['A.mc_use_time' => 0];
        $member_coupon_where[] = ['<=', 'A.mc_start_time', $now_time];
        $member_coupon_where[] = ['>=', 'A.mc_end_time', $now_time];
        $member_coupon_where[] = ['<=', 'B.coupon_min_price', $goods_amount];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,B.coupon_min_price,B.coupon_title,B.coupon_img');
        foreach ($member_coupon_list as $k => &$v) {
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1);
        }
        //获取可用优惠券

        //获取默认地址
        $default_addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        if ($default_addr_info) {
            $member_address = (new Area())->lowGetHigh($default_addr_info['ma_area_id']); //省市区
        } else {
            $member_address = '';
        }
        list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$default_addr_info['ma_true_name'], $default_addr_info['ma_mobile'], $default_addr_info['ma_id'], $member_address . $default_addr_info['ma_area_info']];
        //获取默认地址

        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $prepare_data['addr'] = $default_addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $goods_amount; //商品总价
//        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['shipping_fee'] = $shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**直接购买商品结算页面
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $num 该规格数量
     */
    public function actionPrepareOrder($goods_id, $guige_id, $num = 1)
    {
        $now_time = time();
        $member_info = $this->user_info; //用户信息
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $default_addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        if ($default_addr_info) {
            $member_address = (new Area())->lowGetHigh($default_addr_info['ma_area_id']); //省市区
        } else {
            $member_address = '';
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
        $discount_price = 0;
        $state = 0;
        if ($goods_discount_res['state'] != 0) {
            $discount_price = $goods_discount_res['discount_price'][$guige_id];
            $state = 1; //商品有促销活动
            //正在参加促销活动，使用促销价
            $goods_amount = $goods_discount_res['discount_price'][$guige_id] * $num;
        } else {
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price'] * $num;
        }

        //营销活动 满减
        $full_down = 0;
        $full_down_state = 0;
        //商品是否参加满减
        $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_goods_id' => $goods_id]);
        $fdg_fd_id_arr = array_column($full_goods_list, 'fdg_fd_id');
        $full_info = (new FullDown())->getFullDownInfo(['and', ['in', 'fd_id', $fdg_fd_id_arr], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
        if ($full_info) {
            $full_down_state = 1;
            //满减规则
            $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['>=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
            $full_down = $yxhd_rule[0]['fdr_deduct'] ?: 0;
        }
        //营销活动 满减

        $class_id = (new Goods())->Topclass($goods_info['goods_class_id']); //商品所属分类  最高级分类

        //获取可用优惠券 为满减之后
        $member_coupon_where = ['and'];
        $member_coupon_where[] = ['A.mc_member_id' => $member_info['member_id']];
        $member_coupon_where[] = ['A.mc_state' => 1];
        $member_coupon_where[] = ['A.mc_use_time' => 0];
        $member_coupon_where[] = ['<=', 'A.mc_start_time', $now_time];
        $member_coupon_where[] = ['>=', 'A.mc_end_time', $now_time];
        $member_coupon_where[] = ['<=', 'B.coupon_min_price', $goods_amount - $full_down];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,B.coupon_min_price,B.coupon_title,B.coupon_img,B.goods_class_id');
        foreach ($member_coupon_list as $k => &$v) {
            $class_id_arr = explode(',', $v['goods_class_id']);
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1);
            if (!in_array($class_id, $class_id_arr)) {
                unset($member_coupon_list[$k]);
            }
        }
        //获取可用优惠券

        //商品信息
        list($goods['goods_id'], $goods['goods_name'], $goods['goods_pic'], $goods['price'], $goods['discount_price'], $goods['num'], $goods['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), $guige_info['guige_price'], $discount_price, $num, $state];
        //地址信息
        list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$default_addr_info['ma_true_name'], $default_addr_info['ma_mobile'], $default_addr_info['ma_id'], $member_address . $default_addr_info['ma_area_info']];

        $prepare_data['addr'] = $default_addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $goods_amount; //商品总价
        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['full_down_state'] = $full_down_state; //是否参加满减活动
        $prepare_data['full_down'] = $full_down; //商品满减金额
        $prepare_data['shipping_fee'] = $shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**活动营销直接购买商品结算页面 暂不用 2019-05-27
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $num 该规格数量
     */
    public function actionPreparePullDownOrder($fd_id, $goods_id, $guige_id, $num = 1)
    {
        $now_time = time();
        $member_info = $this->user_info; //用户信息
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $default_addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        if ($default_addr_info) {
            $member_address = (new Area())->lowGetHigh($default_addr_info['ma_area_id']); //省市区
        } else {
            $member_address = '';
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
        $discount_price = 0;
        $state = 0;
        if ($goods_discount_res['state'] != 0) {
            $discount_price = $goods_discount_res['discount_price'][$guige_id];
            $state = 1; //商品有促销活动
            //正在参加促销活动，使用促销价
            $goods_amount = $goods_discount_res['discount_price'][$guige_id] * $num;
        } else {
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price'] * $num;
        }

        //满减
        $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $fd_id], ['>=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
        $full_down = $yxhd_rule[0]['fdr_deduct'];

        //获取可用优惠券
        $member_coupon_where = ['and'];
        $member_coupon_where[] = ['A.mc_member_id' => $member_info['member_id']];
        $member_coupon_where[] = ['A.mc_state' => 1];
        $member_coupon_where[] = ['A.mc_use_time' => 0];
        $member_coupon_where[] = ['<=', 'A.mc_start_time', $now_time];
        $member_coupon_where[] = ['>=', 'A.mc_end_time', $now_time];
        $member_coupon_where[] = ['<=', 'B.coupon_min_price', $goods_amount];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,B.coupon_min_price,B.coupon_title,B.coupon_img');
        foreach ($member_coupon_list as $k => &$v) {
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1);
        }
        //获取可用优惠券

        //商品信息
        list($goods['goods_id'], $goods['goods_name'], $goods['goods_pic'], $goods['price'], $goods['discount_price'], $goods['num'], $goods['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), $guige_info['guige_price'], $discount_price, $num, $state];
        //地址信息
        list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$default_addr_info['ma_true_name'], $default_addr_info['ma_mobile'], $default_addr_info['ma_id'], $member_address . $default_addr_info['ma_area_info']];

        $prepare_data['addr'] = $default_addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $goods_amount; //商品总价
        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['full_down'] = $full_down; //商品满减金额
        $prepare_data['shipping_fee'] = $shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**添加订单-直接下单 未经过购物车
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $ma_id 地址id
     * @param string $buyer_message 买家留言
     * @param int $mc_id 优惠券id
     * @param int $num 购买数量
     */
    public function actionAddOrder($goods_id, $guige_id, $ma_id, $buyer_message = '', $mc_id = 0, $num = 1)
    {
        $now_time = time();
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够，请重新下单！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_id]); //地址
        if (!$addr_info) {
            $this->jsonRet->jsonOutput(-3, '地址不存在');
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
        if ($goods_discount_res['state'] != 0) {
            //正在参加促销活动，使用促销价
            $goods_amount = $goods_discount_res['discount_price'][$guige_id] * $num;
            $order_goods_date['order_goods_dp_id'] = $goods_info['goods_promotion_id']; //限时抢购id
        } else {
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price'] * $num;
        }

        //营销活动 满减
        $full_down = 0;
        //商品是否参加满减
        $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_goods_id' => $goods_id]);
        $fdg_fd_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_fd_id') : [];
        $full_info = (new FullDown())->getFullDownInfo(['and', ['in', 'fd_id', $fdg_fd_id_arr], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
        if ($full_info) {
            //满减规则
            $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['>=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
            $full_down = $yxhd_rule[0]['fdr_deduct'] ?: 0;
        }
        //营销活动 满减

        $order_coupon_price = 0;
        if ($mc_id) {
            //如果使用了优惠券
            $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $mc_id, 'mc_state' => 1, 'mc_use_time' => 0]);
            $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
            if ($coupon_info['coupon_start_time'] > $now_time || $coupon_info['coupon_end_time'] < $now_time || $coupon_info['coupon_end_time'] > $goods_amount) {
                $this->jsonRet->jsonOutput(-3, '无效优惠券！');
            }
            $order_coupon_price = $coupon_info['coupon_quota'];
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            //添加订单
            $order_date['buyer_address'] = $member_address . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['commission_amount'] = $goods_amount * $goods_info['commission'] / 100; //佣金 商品价格*佣金百分比
            $order_date['order_mc_id'] = $mc_id ?: ''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price + $full_down; //优惠金额 优惠券金额+满减金额
            $order_date['order_amount'] = $goods_amount + $shipping_fee['value'] - $order_coupon_price - $full_down; //订单总价格 商品总价格+运费-优惠价格-满减
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            //添加订单

            //添加订单商品
            $order_goods_date['order_id'] = $order_id; //订单id
            $order_goods_date['order_goods_id'] = $goods_id; //商品id
            $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
            $order_goods_date['order_goods_price'] = $goods_amount; //商品价格
            $order_goods_date['order_goods_num'] = $num; //商品数量
            $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
            $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
            $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
            if ($full_down) {
                //如果参加满减
                $order_goods_date['goods_type'] = 4; //订单类型 营销活动
                $order_goods_date['promotions_id'] = $full_info['fd_id']; //营销活动id
            }
            (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
            //添加订单商品

            //减少该规格下商品数量
            (new GuiGe())->updateGuiGe(['guige_num' => $guige_info['guige_num'] - $num, 'guige_id' => $guige_id,]);

            //标记优惠券为已使用
            if ($mc_id) {
                $member_coupon_date['mc_member_id'] = $member_id;
                $member_coupon_date['mc_use_time'] = time();
                (new MemberCoupon())->updateMemberCoupon($member_coupon_date, ['mc_id' => $mc_id]);
            }
            //标记优惠券为已使用

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功', ['order_id' => $order_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    /**营销活动添加订单-直接下单 未经过购物车 暂不使用
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $ma_id 地址id
     * @param string $buyer_message 买家留言
     * @param int $mc_id 优惠券id
     * @param int $num 购买数量
     */
    public function actionAddPullDownOrder($fd_id, $goods_id, $guige_id, $ma_id, $buyer_message = '', $mc_id = 0, $num = 1)
    {
        $now_time = time();
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够，请重新下单！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
        if ($goods_discount_res['state'] != 0) {
            //正在参加促销活动，使用促销价
            $goods_amount = $goods_discount_res['discount_price'][$guige_id] * $num;

            $order_goods_date['goods_type'] = $goods_info['goods_promotion_type']; //订单商品类型
            $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //拼团活动id
        } else {
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price'] * $num;
        }
        $order_coupon_price = 0;
        if ($mc_id) {
            //如果使用了优惠券
            $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $mc_id, 'mc_state' => 1, 'mc_use_time' => 0]);
            $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
            if ($coupon_info['coupon_start_time'] > $now_time || $coupon_info['coupon_end_time'] < $now_time || $coupon_info['coupon_end_time'] > $goods_amount) {
                $this->jsonRet->jsonOutput(-3, '无效优惠券！');
            }
            $order_coupon_price = $coupon_info['coupon_quota'];
        }

        //满减
        $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $fd_id], ['>=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
        $full_down = $yxhd_rule[0]['fdr_deduct']; //满减金额

        $transaction = Yii::$app->db->beginTransaction();
        try {
            //添加订单
            $order_date['buyer_address'] = $member_address . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['commission_amount'] = $goods_amount * $goods_info['commission'] / 100; //佣金 商品价格*佣金百分比
            $order_date['order_mc_id'] = $mc_id ?: ''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price + $full_down; //优惠金额 优惠券金额+满减金额
            $order_date['order_amount'] = $goods_amount + $shipping_fee['value'] - $order_coupon_price - $full_down; //订单总价格 商品总价格+运费-优惠价格-满减
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            //添加订单

            //添加订单商品
            $order_goods_date['order_id'] = $order_id; //订单id
            $order_goods_date['order_goods_id'] = $goods_id; //商品id
            $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
            $order_goods_date['order_goods_price'] = $goods_amount; //商品价格
            $order_goods_date['order_goods_num'] = $num; //商品数量
            $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
            $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
            $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
            $order_goods_date['goods_type'] = 4; //订单类型 营销活动
            $order_goods_date['promotions_id'] = $fd_id; //营销活动id
            (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
            //添加订单商品

            //减少该规格下商品数量
            (new GuiGe())->updateGuiGe(['guige_num' => $guige_info['guige_num'] - $num, 'guige_id' => $guige_id,]);

            //标记优惠券为已使用
            if ($mc_id) {
                $member_coupon_date['mc_member_id'] = $member_id;
                $member_coupon_date['mc_use_time'] = time();
                (new MemberCoupon())->updateMemberCoupon($member_coupon_date, ['mc_id' => $mc_id]);
            }
            //标记优惠券为已使用

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功', ['order_id' => $order_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    //支付 未完成 暂时搁置 2019-05-21
    public function actionPayOrder($order_id = 14)
    {
        require_once(__DIR__ . "/../../../../vendor/wxpay/lib/WxPay.Api.php");
        require_once(__DIR__ . "/../../../../vendor/wxpay/example/WxPay.JsApiPay.php");
        require_once(__DIR__ . "/../../../../vendor/wxpay/example/log.php");
        require_once(__DIR__ . "/../../../../vendor/wxpay/example/WxPay.Config.php");
        require_once(__DIR__ . "/../../../../vendor/wxpay/lib/WxPay.Notify.php");
        $info = (new Order())->getOrderOne(['order_id' => $order_id]);
        $tools = new \JsApiPay();
        //②、统一下单
        $input = new \WxPayUnifiedOrder();
        $input->SetBody('body');
        $input->SetAttach('xiaoyou');
        $input->SetOut_trade_no($info['order_sn']);
        $input->SetTotal_fee($info['order_amount'] * 100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("test");
        $input->SetNotify_url('http://www.bd.com/ygh/api/web/index.php/v1/index/pay-order');
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid('2423423');
        $config = new \WxPayConfig();
        $order = \WxPayApi::unifiedOrder($config, $input);
        $jsApiParameters = $tools->GetJsApiParameters($order);
        var_dump($jsApiParameters);
        die;
    }

    /**添加订单-购物车下单 经过购物车
     * @param $cart_ids
     * @param $ma_id
     * @param string $buyer_message
     * @param int $mc_id
     */
    public function actionCarToOrder($cart_ids, $ma_id, $buyer_message = '', $mc_id = 0)
    {
        $now_time = time();
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $cart_id_arr = explode(',', $cart_ids);
        $car_where = ['and'];
        $car_where[] = ['in', 'cart_id', $cart_id_arr];
        $car_where[] = ['buyer_id' => $member_id];
        $car_list = (new ShoppingCar())->getShoppingCarList($car_where, '', '', '', 'G.goods_name,G.goods_id,S.cart_id,S.goods_num,S.goods_spec_id,G.goods_name,GG.guige_name,GG.guige_price'); //所选购物车记录
        $goods_amount = 0;
        $full_down = 0;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($car_list as $key => $value) {
                $guige_id = $value['goods_spec_id']; //规格
                $num = $value['goods_num']; //数量
                $goods_id = $value['goods_id']; //商品
                $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
                $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
                if ($guige_info['guige_num'] - $num < 0) {
                    $this->jsonRet->jsonOutput(-2, $guige_info['goods_name'] . '商品的' . $guige_info['guige_name'] . '规格库存不够，请重新下单！');
                }
                $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
                if ($goods_discount_res['state'] != 0) {
                    //正在参加促销活动，使用促销价
                    $goods_amount += $goods_discount_res['discount_price'][$guige_id] * $num;
                    $order_goods_date['order_goods_dp_id'] = $goods_info['goods_promotion_id']; //抢购活动id
                } else {
                    //未参加促销活动，使用该规格相应的价格
                    $goods_amount += $guige_info['guige_price'] * $num;
                }

                //营销活动 满减
                //商品是否参加满减
                $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_goods_id' => $goods_id]);
                $fdg_fd_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_fd_id') : [];
                $full_info = (new FullDown())->getFullDownInfo(['and', ['in', 'fd_id', $fdg_fd_id_arr], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
                if ($full_info) {
                    //满减规则
                    $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['>=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                    $full_down_money = $yxhd_rule[0]['fdr_deduct'] ?: 0;
                    if ($full_down_money) {
                        $order_goods_date['goods_type'] = 4; //订单类型 营销活动
                        $order_goods_date['promotions_id'] = $full_info['fd_id']; //营销活动id
                    }
                } else {
                    $full_down_money = 0;
                }
                $full_down += $full_down_money;
                //营销活动 满减

                $order_goods_date['order_goods_id'] = $goods_id; //商品id
                $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
                $order_goods_date['order_goods_price'] = $goods_amount; //商品价格
                $order_goods_date['order_goods_num'] = $num; //商品数量
                $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
                $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
                $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
                $order_goods_id_arr[] = (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
                (new GuiGe())->updateGuiGe(['guige_num' => $guige_info['guige_num'] - $num, 'guige_id' => $guige_id,]); //减少该规格下商品数量
            }
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
            if ($addr_info) {
                $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']); //省市区
            } else {
                $member_address = '';
            }
            $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
            $order_coupon_price = 0;
            if ($mc_id) {
                //如果使用了优惠券
                $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $mc_id, 'mc_state' => 1, 'mc_use_time' => 0]);
                $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
                if ($coupon_info['coupon_start_time'] > $now_time || $coupon_info['coupon_end_time'] < $now_time || $coupon_info['coupon_end_time'] > $goods_amount) {
                    $this->jsonRet->jsonOutput(-3, '无效优惠券！');
                }
                $order_coupon_price = $coupon_info['coupon_quota']; //优惠金额

                //标记优惠券为已使用
                $member_coupon_date['mc_member_id'] = $member_id;
                $member_coupon_date['mc_use_time'] = time();
                (new MemberCoupon())->updateMemberCoupon($member_coupon_date, ['mc_id' => $mc_id]);
                //标记优惠券为已使用
            }
            $order_date['buyer_address'] = $member_address . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['commission_amount'] = $goods_amount * $goods_info['commission'] / 100; //佣金 商品价格*佣金百分比
            $order_date['order_mc_id'] = $mc_id ?: ''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price; //优惠金额
            $order_date['order_amount'] = $goods_amount + $shipping_fee['value'] - $order_coupon_price; //订单总价格 商品总价格+运费-优惠价格
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            $order_goods_up_date['order_id'] = $order_id; //订单id
            (new OrderGoods())->updateOrderGoodsByCondition($order_goods_up_date, ['in', 'order_rec_id', $order_goods_id_arr]); //更新订单商品 添加订单id
            (new ShoppingCar())->deleteShoppingCar(['in', 'cart_id', $cart_id_arr]);
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功', ['order_id' => $order_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    /**我的订单
     * @param int $page
     * @param int $type 订单状态
     */
    public function actionMyOrder($page = 1, $type = 0)
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $order_condition = ['and'];
        $order_condition[] = ['buyer_id' => $member_id];
        if ($type) {
            $order_condition[] = ['order_state' => $type];
        }
        $order_list = (new Order())->getOrderList($order_condition, $offset, $pageSize, 'add_time desc', 'order_id,order_sn,add_time,order_state,pd_amount,order_amount');
        foreach ($order_list as $k => $v) {
            $goods_list = (new OrderGoods())->getOrderGoodsList(['order_id' => $v['order_id']], '', '', '', 'order_goods_id,order_goods_name,order_goods_image,order_goods_num,');
            foreach ($goods_list as $k1 => $v1) {
                $goods_list[$k1]['order_goods_image'] = SysHelper::getImage($v1['order_goods_image'], 0, 0, 0, [0, 0], 1);
                //待支付订单
                if ($v['order_state'] == 20) {
                    if ($v['order_mc_id']) {
                        //存在优惠券 检查优惠券是否过期
                        $now_time = time();
                        $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $v['order_mc_id'], 'mc_state' => 1, 'mc_use_time' => 0]);
                        $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
                        if ($coupon_info['coupon_start_time'] > $now_time || $coupon_info['coupon_end_time'] < $now_time) {
                            $order_list['order_state'] = -1; //订单已过期
                        }
                    }
                    if ($v1['goods_type'] == 4) {
                        //满减活动是否过期
                        $full_info = (new FullDown())->getFullDownInfo(['and', ['fd_id' => $v1['promotions_id']], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
                        if (!$full_info) {
                            $order_list['order_state'] = -1; //订单已过期
                        }
                    }
                    if ($v1['order_goods_dp_id']) {
                        //限时抢购活动是否过期
                        $pb_info = (new PanicBuy())->getPanicBuyInfo(['pb_id' => $v1['order_goods_dp_id']]);
                        if ($pb_info['pb_end_time'] <= time() || $pb_info['pb_state'] != 2) {
                            $order_list['order_state'] = -1; //订单已过期
                        }
                    }
                }
            }
            $order_list[$k]['goods_list'] = $goods_list;
            $order_list[$k]['add_time'] = date('Y-m-d', $v['add_time']);
        }
        $totalCount = (new Order())->getOrderCount($order_condition);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $order_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功！', $show);
    }

    /**订单详情
     * @param $order_id 订单ID
     */
    public function actionOrderDetail($order_id)
    {
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
        $info['order_id'] = $order_info['order_id']; //订单id
        $info['order_sn'] = $order_info['order_sn']; //订单号
        $info['ma_true_name'] = $order_info['ma_true_name']; //收货人
        $info['ma_phone'] = $order_info['ma_phone']; //收货人电话
        $info['buyer_address'] = $order_info['buyer_address']; //收货人地址
        $info['order_sn'] = $order_info['order_sn']; //订单号
        $info['add_time'] = date('Y-m-d H:i:s', $order_info['add_time']); //下单时间
        $info['payment_code'] = $order_info['payment_code']; //支付方式
        $info['payment_time'] = $order_info['payment_time'] ? date('Y-m-d H:i:s', $order_info['payment_time']) : ''; //支付时间
        $info['e_name'] = (new Express())->getExpressInfo(['id' => $order_info['shipping_express_id']])['e_name'] ?: ''; //物流公司
        $info['shipping_code'] = $order_info['shipping_code'] ?: ''; //物流单号
        $info['goods_amount'] = $order_info['goods_amount']; //商品总额
        $info['order_amount'] = $order_info['order_amount']; //应付金额
        $info['order_coupon_price'] = $order_info['order_coupon_price']; //优惠金额
        $info['point_amount'] = $order_info['point_amount']; //获得积分
        $info['pd_amount'] = $order_info['pd_amount']; //实付款
        $info['order_state'] = $order_info['order_state']; //订单状态
        foreach ($order_info['extend_order_goods'] as $k => $v) {
            if ($order_info['order_state'] == 20) {
                if ($order_info['order_mc_id']) {
                    //存在优惠券 检查优惠券是否过期
                    $now_time = time();
                    $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $v['order_mc_id'], 'mc_state' => 1, 'mc_use_time' => 0]);
                    $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
                    if ($coupon_info['coupon_start_time'] > $now_time || $coupon_info['coupon_end_time'] < $now_time) {
                        $info['order_state'] = -1; //订单已过期
                    }
                }
                if ($v['goods_type'] == 4) {
                    //满减活动是否过期
                    $full_info = (new FullDown())->getFullDownInfo(['and', ['fd_id' => $v['promotions_id']], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
                    if (!$full_info) {
                        $info['order_state'] = -1; //订单已过期
                    }
                }
                if ($v['order_goods_dp_id']) {
                    //限时抢购活动是否过期
                    $pb_info = (new PanicBuy())->getPanicBuyInfo(['pb_id' => $v['order_goods_dp_id']]);
                    if ($pb_info['pb_end_time'] <= time() || $pb_info['pb_state'] != 2) {
                        $info['order_state'] = -1; //订单已过期
                    }
                }
            }
            $info['good_list'][$k]['goods_name'] = $v['order_goods_name'];
            $info['good_list'][$k]['order_goods_price'] = $v['order_goods_price'];
            $info['good_list'][$k]['order_goods_num'] = $v['order_goods_num'];
            $info['good_list'][$k]['order_goods_id'] = $v['order_goods_id'];
            $info['good_list'][$k]['order_goods_image'] = SysHelper::getImage($v['order_goods_image'], 0, 0, 0, [0, 0], 1);;
        }
        $this->jsonRet->jsonOutput(0, '加载成功！', $info);
    }

    /**申请退款
     * @param $order_id
     * @param string $why
     * @param string $imgs
     * @param string $content
     */
    public function actionAddRefundOrder($order_id, $why = '', $imgs = '', $content = '')
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            //订单信息
            $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
            $return_data['order_id'] = $order_info['order_id']; //订单id
            $return_data['order_sn'] = $order_info['order_sn']; //订单号
            $return_data['refund_sn'] = 'RE' . date('YmdHis') . rand(100, 999); //退货编号
            $return_data['buyer_name'] = $order_info['buyer_name']; //买家姓名
            $return_data['buyer_id'] = $order_info['buyer_id']; //买家id
            $return_data['refund_amount'] = $order_info['pd_amount']; //退款金额 为订单付款金额
            $return_data['refund_state'] = 1; //退款订单状态
            $return_data['add_time'] = time(); //申请退款时间
            $return_data['buyer_message'] = $why; //退款原因
            $imgs ? $return_data['pic_info'] = $imgs : ''; //图片
            $return_data['reason_info'] = $content; //退款原因描述

            //提交退款申请
            (new OrderReturn())->insertOrderReturn($return_data);

            //修改订单退货状态
            $order_data['order_id'] = $order_id;
            $order_data['refund_state'] = 3;
            (new Order())->updateOrder($order_data);

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    /**提交评论
     * @param $geval_ordergoodsid 订单商品表id
     * @param $geval_scores 描述相符评分
     * @param $score1 商品包装评分
     * @param $score2 到货时效评分
     * @param $score3 购物体验评分
     * @param $geval_image 图片
     * @param $geval_content 评价内容
     * @param $geval_isanonymous 是否匿名
     * @param $geval_tag 评价标签
     */
    public function actionAddOrderEvaluate($geval_ordergoodsid, $geval_scores, $score1, $score2, $score3, $geval_image = '', $geval_content = '', $geval_isanonymous = 0, $geval_tag = '')
    {
        $member_info = $this->user_info;
        $info = (new OrderGoods())->getOrderGoodsInfo(['order_rec_id' => $geval_ordergoodsid]);
        $evaluate_data['geval_orderid'] = $info['order_id']; //订单id
        $evaluate_data['geval_ordergoodsid'] = $geval_ordergoodsid; //订单商品表编号
        $evaluate_data['geval_goodsid'] = $info['order_goods_id']; //商品id
        $evaluate_data['geval_goodsimage'] = $info['order_goods_image']; //订单商品图片
        $evaluate_data['geval_goodsname'] = $info['order_goods_name']; //订单商品名称
        $evaluate_data['geval_goodsprice'] = $info['order_goods_price']; //订单商品价格
        $evaluate_data['geval_scores'] = $geval_scores; //评分
        $evaluate_data['geval_content'] = $geval_content; //评价内容
        $evaluate_data['geval_isanonymous'] = $geval_isanonymous; //是否匿名
        $evaluate_data['geval_addtime'] = time(); //评价时间
        $evaluate_data['geval_frommemberid'] = $member_info['member_id']; //评价人ID
        $evaluate_data['geval_frommembername'] = $member_info['member_name']; //评价人昵称
        $evaluate_data['geval_image'] = $geval_image; //评价图片
        $evaluate_data['geval_tag'] = $geval_tag; //评价标签
        $evaluate_data['geval_other'] = json_encode(['score1' => $score1, 'score2' => $score2, 'score3' => $score3]);
        $result = (new Evaluate())->insertEvaluateGoods($evaluate_data);
        if ($result) {
            //评价积分配置
            $comments_integral = (new Setting())->getSettingInfo(['name' => 'comments_integral'])['value'];
            //评价送积分
            $integral = $member_info['member_points'] + $comments_integral; //用户所剩积分
            (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]);
            $this->jsonRet->jsonOutput(0, '提交成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '提交失败');
        }
    }

    /**我的优惠券
     * @param $state 优惠券状态
     */
    public function actionMyCoupon($state = 1)
    {
        $member_info = $this->user_info;
        $now_time = time();
        //获取优惠券
        $member_coupon_where = ['and'];
        $member_coupon_where[] = ['A.mc_member_id' => $member_info['member_id']];
        $member_coupon_where[] = ['A.mc_state' => 1];
        if ($state == 1) {
            //未使用 在使用期内
            $member_coupon_where[] = ['A.mc_use_time' => 0];
            $member_coupon_where[] = ['<=', 'A.mc_start_time', $now_time];
            $member_coupon_where[] = ['>=', 'A.mc_end_time', $now_time];
        }
        if ($state == 2) {
            //已使用
            $member_coupon_where[] = ['>=', 'A.mc_use_time', 0];
        }
        if ($state == 3) {
            //已失效
            $member_coupon_where[] = ['A.mc_use_time' => 0];
            $member_coupon_where[] = ['<=', 'A.mc_end_time', $now_time];
        }

        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,B.coupon_min_price,B.coupon_title,B.coupon_img');
        foreach ($member_coupon_list as $k => &$v) {
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1);
        }
        if ($member_coupon_list) {
            $this->jsonRet->jsonOutput(0, '加载成功', $member_coupon_list);
        } else {
            $this->jsonRet->jsonOutput(-1, '加载失败');
        }
    }





    /**************************************拼团限时购模块**************************************************************************************************************/


    /**
     * 拼团限时购列表
     */
    public function actionBulk()
    {
        $now_time = time();
        $bulk_condition = ['and'];
        $bulk_condition[] = ['>=', 'end_time', $now_time];
        $goods_bulk_list = (new Bulk())->getBulkList($bulk_condition, '', '', '', '');
        foreach ($goods_bulk_list as $k => $v) {
            if ($v['start_time'] < $now_time) {
                $list[$k]['state'] = 1; //进行中
                $list[$k]['time'] = ceil(($v['end_time'] - $now_time) / (24 * 3600));
            } else {
                $list[$k]['state'] = 0; //待开团
                $list[$k]['time'] = ceil(($v['start_time'] - $now_time) / (24 * 3600));
            }
            $list[$k]['goods_id'] = $v['goods_id'];
            $list[$k]['bulk_id'] = $v['bulk_id'];
            $list[$k]['num'] = $v['num'];
            $list[$k]['bulk_price'] = $v['bulk_price'];
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $v['guige_id']], 'guige_price');
            $list[$k]['price'] = $guige_info['guige_price'];
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['goods_id']], 'A.goods_name,A.goods_pic');
            $list[$k]['goods_name'] = $goods_info['goods_name'];
            $list[$k]['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }


    /**拼团结算页面 不考虑促销价 不可用优惠券 免运费
     * @param $bulk_id 拼团活动id
     */
    public function actionPrepareBulkStart($bulk_id)
    {
        $now_time = time();
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id' => $bulk_id]);
        if ($goods_bulk_info['end_time'] < $now_time || $goods_bulk_info['start_time'] > $now_time) {
            $this->jsonRet->jsonOutput(-2, '活动未开始或已结束！');
        }
        $goods_id = $goods_bulk_info['goods_id']; //商品
        $guige_id = $goods_bulk_info['guige_id']; //规格
        $bulk_price = $goods_bulk_info['bulk_price']; //拼团价格
        $num = 1;
        $member_info = $this->user_info; //用户信息
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-3, '该规格商品库存不够！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $default_addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        if ($default_addr_info) {
            $member_address = (new Area())->lowGetHigh($default_addr_info['ma_area_id']); //省市区
        } else {
            $member_address = '';
        }
        //商品信息
        list($goods['goods_id'], $goods['goods_name'], $goods['goods_pic'], $goods['price'], $goods['bulk_price'], $goods['num']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), $guige_info['guige_price'], $bulk_price, 1];
        //地址信息
        list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$default_addr_info['ma_true_name'], $default_addr_info['ma_mobile'], $default_addr_info['ma_id'], $member_address . $default_addr_info['ma_area_info']];
        $prepare_data['addr'] = $default_addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $bulk_price; //商品总价
        $prepare_data['point_amount'] = ceil($bulk_price); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**拼团限时购下单 类内部调用
     * @param $bulk_id
     * @param $ma_id
     * @param string $buyer_message
     */
    public function addBulkStartOrder($bulk_id, $ma_id, $buyer_message = '')
    {
        $now_time = time();
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id' => $bulk_id]);
        if ($goods_bulk_info['end_time'] < $now_time || $goods_bulk_info['start_time'] > $now_time) {
            $this->jsonRet->jsonOutput(-2, '活动未开始或已结束！');
        }
        $goods_id = $goods_bulk_info['goods_id']; //商品
        $guige_id = $goods_bulk_info['guige_id']; //规格
        $bulk_price = $goods_bulk_info['bulk_price']; //拼团价格
        $num = 1;
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num'] - $num < 0) {
            $this->jsonRet->jsonOutput(-3, '该规格商品库存不够，请重新下单！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $order_date['buyer_address'] = $member_address . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['point_amount'] = ceil($bulk_price); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $bulk_price; //商品总价格
            $order_date['commission_amount'] = $bulk_price * $goods_info['commission'] / 100; //佣金 商品价格*佣金百分比
            $order_date['order_amount'] = $bulk_price; //订单总价格 为拼团价格
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            $order_goods_date['order_id'] = $order_id; //订单id
            $order_goods_date['order_goods_id'] = $goods_id; //商品id
            $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
            $order_goods_date['order_goods_price'] = $bulk_price; //商品价格
            $order_goods_date['order_goods_num'] = $num; //商品数量
            $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
            $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
            $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
            $order_goods_date['goods_type'] = 5; //订单商品类型
            $order_goods_date['promotions_id'] = $bulk_id; //拼团活动id
            (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
            (new GuiGe())->updateGuiGe(['guige_num' => $guige_info['guige_num'] - $num, 'guige_id' => $guige_id,]); //减少该规格下商品数量
            $transaction->commit();
            return $order_id;
        } catch (\Exception $e) {
            $transaction->rollBack();
            return 0;
        }
    }

    /**拼团限时购详情
     * @param $bulk_id 拼团活动id
     */
    public function actionBulkDetail($bulk_id)
    {
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id' => $bulk_id], '');
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $goods_bulk_info['guige_id']], 'guige_price,guige_no,guige_name');
        $info['price'] = $guige_info['guige_price'];
        $info['guige_no'] = $guige_info['guige_no'];
        $info['guige_name'] = $guige_info['guige_name'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_bulk_info['goods_id']]);
        $info['goods_name'] = $goods_info['goods_name'];
        $info['goods_description'] = $goods_info['goods_description'];
        $info['goods_mobile_content'] = $goods_info['goods_mobile_content'];
        $info['goods_stock'] = $goods_info['goods_stock'];
        $info['goods_sales'] = $goods_info['goods_sales'];
        $info['goods_id'] = $goods_bulk_info['goods_id'];
        $info['bulk_id'] = $goods_bulk_info['bulk_id'];
        $info['num'] = $goods_bulk_info['num'];
        $info['bulk_price'] = $goods_bulk_info['bulk_price'];
        $goods_info['goods_pic1'] ? $info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic1'], 0, 0, 0, [0, 0], 1) : '';
        $goods_info['goods_pic2'] ? $info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic2'], 0, 0, 0, [0, 0], 1) : '';
        $goods_info['goods_pic3'] ? $info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic3'], 0, 0, 0, [0, 0], 1) : '';
        $goods_info['goods_pic4'] ? $info['good_pic'][] = SysHelper::getImage($goods_info['goods_pic4'], 0, 0, 0, [0, 0], 1) : '';

        //用户评论
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $info['goods_evaluate'] = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_bulk_info['goods_id'], 'G.geval_state' => 0], 0, 2, '', $fields);

        //总评论数
        $info['goods_evaluate_count'] = (new Evaluate())->getEvaluateGoodsCount(['geval_goodsid' => $goods_bulk_info['goods_id'], 'geval_state' => 0]);

        //品牌
        $brand = (new Brand())->getBrandInfo(['brand_id' => $goods_info['brand_id']], 'brand_id,brand_name,brand_pic');
        $brand['brand_pic'] = SysHelper::getImage($brand['brand_pic'], 0, 0, 0, [0, 0], 1);
        $info['brand'] = $brand;

        //地区
        $countries = (new Countrie())->getCountrieInfo(['countrie_id' => $goods_info['countrie_id']], 'countrie_pic,countrie_name');
        $countries['countrie_pic'] = SysHelper::getImage($countries['countrie_pic'], 0, 0, 0, [0, 0], 1);
        $info['countrie'] = $countries;

        //团购状态
        $now_time = time();
        if ($goods_bulk_info['start_time'] < $now_time) {
            $info['state'] = 1; //进行中
            $info['time'] = ceil(($goods_bulk_info['end_time'] - $now_time) / (24 * 3600)); //距离结束时间（天）
        } else {
            $info['state'] = 0; //待开始
            $info['time'] = ceil(($goods_bulk_info['start_time'] - $now_time) / (24 * 3600)); //距离开始时间（天）
        }

        //拼团列表
        $bulk_start_list = (new BulkStart())->getBulkStartList(['bulk_id' => $goods_bulk_info['bulk_id'], 'state' => 0], '', '', 'create_time desc', 'list_id,now_num,need_num');
        foreach ($bulk_start_list as $k => $v) {
            $bulk_list_list = (new BulkList())->getBulkListList(['bulk_id' => $goods_bulk_info['bulk_id'], 'list_id' => $v['list_id']], '', '', 'create_time asc', 'member_id,member_state');
            foreach ($bulk_list_list as $k1 => $v1) {
                $member_avatar = (new Member())->getMemberInfo(['member_id' => $v1['member_id']], 'member_avatar')['member_avatar'];
                $bulk_list_list[$k1]['member_avatar'] = SysHelper::getImage($member_avatar, 0, 0, 0, [0, 0], 1);
            }
            $bulk_start_list[$k]['datail'] = $bulk_list_list;
        }
        $info['bulk_list'] = $bulk_start_list;

        $this->jsonRet->jsonOutput(0, '加载成功', $info);
    }

    /**开团
     * @param $member_id 用户
     * @param $bulk_id 团购活动id
     * @param $ma_id 地址
     * @param $buyer_message 买家留言
     */
    public function actionAddBulkStart($bulk_id, $ma_id, $buyer_message = '')
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $transaction = Yii::$app->db->beginTransaction();
        try {
            //添加开团记录
            $start_data['menber_id'] = $member_id;
            $start_data['bulk_id'] = $bulk_id;
            $bulk_info = (new Bulk())->getBulkInfo(['bulk_id' => $bulk_id, 'state' => 1], 'num');
            $start_data['need_num'] = $bulk_info['num'];
            $start_data['now_num'] = 1;
            $start_data['create_time'] = time();
            $list_id = (new BulkStart())->insertBulkStart($start_data);
            //添加开团记录

            //添加团购记录
            $list_data['menber_id'] = $member_id;
            $list_data['bulk_id'] = $bulk_id;
            $list_data['list_id'] = $list_id;
            $list_data['create_time'] = time();
            $list_data['member_state'] = 1;
            (new BulkList())->insertBulkList($list_data);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
        $order_id = $this->addBulkStartOrder($bulk_id, $ma_id, $buyer_message);
        if ($order_id) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /** 拼团
     * @param $member_id 用户
     * @param $list_id 团购id
     * @param $ma_id 地址
     * @param $buyer_message 买家留言
     */
    public function actionAddBulkList($list_id, $ma_id, $buyer_message = '')
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $list_id], 'bulk_id');
        $list_data['menber_id'] = $member_id;
        $list_data['bulk_id'] = $bulk_start_info['bulk_id'];
        $list_data['list_id'] = $list_id;
        $list_data['create_time'] = time();
        $list_data['member_state'] = 0;
        (new BulkList())->insertBulkList($list_data);
        $order_id = $this->addBulkStartOrder($bulk_start_info['bulk_id'], $ma_id, $buyer_message);
        if ($order_id) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**
     * 我的拼团
     */
    public function actionBulkList($page = 1, $type = 0)
    {
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $member_info = $this->user_info;
        $now_time = time();
        $where = ['and'];
        $where[] = ['A.member_id' => $member_info['member_id']];
        if ($type == 1) {
            //拼团中
            $where[] = ['C.state' => 0];
            $where[] = ['>=', 'B.end_time', $now_time];
        }
        if ($type == 2) {
            //拼团成功
            $where[] = ['C.state' => 1];
        }
        if ($type == 3) {
            //拼团失败
            $where[] = ['<=', 'B.end_time', $now_time];
            $where[] = ['C.state' => 0];
        }

        $bulk_list = (new BulkList())->getBulkListList1($where, $offset, $pageSize, 'A.create_time desc');
        $totalCount = (new BulkList())->getBulkListCount1($where);
        foreach ($bulk_list as $k => $v) {
            $order_goods_info = (new OrderGoods())->getOrderGoodsInfo(['goods_type' => 5, 'promotions_id' => $v['bulk_id']]); //订单商品详细信息
            $order_info = (new Order())->getOrderOne(['order_id' => $order_goods_info['order_id']]); //订单详细信息
            $list[$k]['create_time'] = date('Y-m-d', $v['create_time']);
            $list[$k]['order_state'] = $order_info['order_state'];
            $list[$k]['order_price'] = $order_info['order_amount'];
            $list[$k]['goods_num'] = $order_goods_info['order_goods_num'];
            $list[$k]['goods_id'] = $order_goods_info['order_goods_id'];
            $list[$k]['goods_pic'] = $order_goods_info['order_goods_image'];
            $list[$k]['order_goods_name'] = $order_goods_info['order_goods_name'];
            $list[$k]['order_id'] = $order_goods_info['order_id'];
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功！', $show);
    }



    /**************************************积分商城模块**************************************************************************************************************/

    /**
     * 积分商城首页
     */
    public function actionIntegralIndex($page = 1)
    {

        $member_info = $this->user_info;

        //轮播图
        $adv = (new AdService())->getAdList(['ad_ads_id' => 22], '', '', '', 'ad_id,ad_link,ad_pic');
        foreach ($adv as $key => $val) {
            $adv[$key]['ad_pic'] = SysHelper::getImage($val['ad_pic'], 0, 0, 0, [0, 0], 1);
        }

        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['A.goods_type' => 2]];

        //商品
        $totalCount = $goods_model->getGoodsCount($condition);
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.integral,A.goods_pic,A.goods_sales';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, 'goods_id desc', $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['goods_list' => $goods_list, 'pages' => $page_arr, 'integral' => $member_info['member_points']];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**积分产品列表|积分产品搜索
     * @param int $class_id 分类
     * @param string $keyword 搜索
     * @param string $order 排序
     * @param int $page 页码
     * @param string $brand_id 品牌
     * @param string $countrie_id 地区
     * @param int $low_price 最低价
     * @param int $high_price 最高价
     */
    public function actionIntegralGoodsList($class_id = 0, $keyword = '', $order = 'A.goods_id desc', $page = 1, $brand_id = '', $countrie_id = '', $low_integral = 0, $high_integral = 0)
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $condition = ['and', ['like', 'A.goods_name', $keyword], ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['A.goods_type' => 2]];

        //最低积分
        if ($low_integral) {
            $condition[] = ['>=', 'A.integral', $low_integral];
        }

        //最高积分
        if ($high_integral) {
            $condition[] = ['<=', 'A.integral', $high_integral];
        }

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
            $byorder = str_replace(array('1', '2', '3', '4'), array('A.goods_sales desc', 'A.goods_sales asc', 'A.integral desc', 'A.integral asc'), $order);
        }
        //商品
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.integral,A.goods_pic,A.goods_sales';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**我的积分明细
     * @param int $coin_type 1增加 2减少
     * @param int $page
     */
    public function actionIntegralDetailList($coin_type = 1, $page = 1)
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $integral_list = (new MemberCoin())->getMemberCoinList(['member_id' => $member_id, 'coin_type' => $coin_type], $offset, $pageSize, 'coin_addtime desc', 'coin_addtime,coin_desc,coin_points');
        foreach ($integral_list as $k => &$v) {
            $v['coin_addtime'] = date('Y.m.d', $v['coin_addtime']);
        }
        $totalCount = (new MemberCoin())->getMemberCoinCount(['member_id' => $member_id, 'coin_type' => $coin_type]);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['goods_list' => $integral_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**积分兑换记录
     * @param int $page 页码
     */
    public function actionIntegralOrder($page = 1)
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $integral_order_list = (new IntegralOrder())->getIntegralOrderList(['member_id' => $member_id], $offset, $pageSize, 'create_time desc', 'goods_name,goods_pic,integral,create_time');
        foreach ($integral_order_list as $k => &$v) {
            $v['create_time'] = date('Y.m.d', $v['create_time']);
        }
        $totalCount = (new MemberCoin())->getMemberCoinCount(['member_id' => $member_id]);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['goods_list' => $integral_order_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**
     * @return string 积分商品详情
     */
    public function actionIntegralDetail($goods_id)
    {
        $goods_model = new Goods();
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
        $goods_info['brand'] = (new Brand())->getBrandInfo(['brand_id' => $goods_info['brand_id']], 'brand_id,brand_name,brand_pic');
        $goods_info['brand']['brand_pic'] = SysHelper::getImage($goods_info['brand']['brand_pic'], 0, 0, 0, [0, 0], 1);

        //地区
        $goods_info['countries'] = (new Countrie())->getCountrieInfo(['countrie_id' => $goods_info['countrie_id']], 'countrie_pic,countrie_name');
        $goods_info['countries']['countrie_pic'] = SysHelper::getImage($goods_info['countries']['countrie_pic'], 0, 0, 0, [0, 0], 1);

        //收藏该商品
        $is_collect_goods = 0;
        $member_info = $this->user_info;
        if ($member_info && (new MemberFollow())->getMemberFollowCount(['fav_id' => $goods_id, 'fav_type' => 'goods', 'member_id' => $member_info['member_id']]) > 0) {
            $is_collect_goods = 1;
        }
        $goods_info['goods_pic1'] ? $goods_info['good_pic'][] = $goods_info['goods_pic1'] : '';
        $goods_info['goods_pic2'] ? $goods_info['good_pic'][] = $goods_info['goods_pic2'] : '';
        $goods_info['goods_pic3'] ? $goods_info['good_pic'][] = $goods_info['goods_pic3'] : '';
        $goods_info['goods_pic4'] ? $goods_info['good_pic'][] = $goods_info['goods_pic4'] : '';

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
        ];
        $this->jsonRet->jsonOutput(0, '', $data);
    }

    /**
     * @param int $page
     */
    public function actionMyIntegral($page = 1)
    {
        $member_info = $this->user_info;
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $date = date('Ymd', time());
        $sign_day = 1;
        $last_sign_info = (new Sign())->getSignList(['member_id' => $member_info['member_id']], '', '1', 'create_time desc');
        if ($last_sign_info && $date - $last_sign_info[0]['create_time'] = 1) {
            $sign_day = $last_sign_info['day_num'] + 1; //当前第几天
        }
        $sign_info = (new Sign())->getSignInfo(['member_id' => $member_info['member_id'], 'create_time' => $date]);
        $sign_state = $sign_info ? 1 : 0; //当天是否签到

        //签到积分配置
        $sign_integral = (new Setting())->getSettingInfo(['name' => 'sign']);
        $sign_arr = explode('|', $sign_integral['value']);
        $sign_list = [];
        foreach ($sign_arr as $k => $v) {
            $sign_list[$k]['integral'] = $v;
            $sign_list[$k]['day_num'] = $k + 1;
            $sign_list[$k]['now_day'] = $sign_day == $k + 1 ? 1 : 0; //当前第几天
            $sign_list[$k]['sign_state'] = $sign_state; //当天是否签到
        }

        //商品
        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['A.goods_type' => 2]];
        $totalCount = $goods_model->getGoodsCount($condition);
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.integral,A.goods_pic,A.goods_sales';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, 'goods_id desc', $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['goods_list' => $goods_list, 'sign_list' => $sign_list, 'pages' => $page_arr, 'integral' => $member_info['member_points'], 'sign_state' => $sign_state, 'sign_day' => $sign_day];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * 签到
     */
    public function actionSign()
    {
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $date = date('Ymd', time());
        $sign_day = 1;

        $last_sign_info = (new Sign())->getSignList(['member_id' => $member_info['member_id']], '', '1', 'create_time desc');
        if ($last_sign_info && $date - $last_sign_info[0]['create_time'] = 1) {
            $sign_day = $last_sign_info['day_num'] + 1; //当前第几天
        }
        $sign_info = (new Sign())->getSignInfo(['member_id' => $member_info['member_id'], 'create_time' => $date]);
        if ($sign_info) {
            $this->jsonRet->jsonOutput(-2, '今天已经签到！');
        }
        //签到积分配置
        $sign_integral = (new Setting())->getSettingInfo(['name' => 'sign']);
        $sign_arr = explode('|', $sign_integral['value']);
        foreach ($sign_arr as $k => $v) {
            if ($sign_day == ($k + 1)) {
                $integral = $v;
            }
        }
        $sign_data['member_id'] = $member_id;
        $sign_data['day_num'] = $sign_day;
        $sign_data['integral'] = $integral;
        $sign_data['create_time'] = $date;
        $result = (new Sign())->insertSign($sign_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '签到成功！');
        } else {
            $this->jsonRet->jsonOutput(-2, '签到失败！');
        }
    }

    /**积分兑换结算 暂不考虑库存 免运费
     * @param $goods_id
     */
    public function actionSignPrepareOrder($goods_id)
    {
        $member_info = $this->user_info; //用户信息
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $default_addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        if ($default_addr_info) {
            $member_address = (new Area())->lowGetHigh($default_addr_info['ma_area_id']); //省市区
        } else {
            $member_address = '';
        }
        //商品信息
        list($goods['goods_id'], $goods['goods_name'], $goods['goods_pic'], $goods['price'], $goods['discount_price'], $goods['num'], $goods['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), $guige_info['guige_price'], $discount_price, $num, $state];
        //地址信息
        list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$default_addr_info['ma_true_name'], $default_addr_info['ma_mobile'], $default_addr_info['ma_id'], $member_address . $default_addr_info['ma_area_info']];
        $prepare_data['addr'] = $default_addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['integral'] = $goods_info['integral']; //商品积分
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**积分兑换下单
     * @param $goods_id
     * @param $ma_id
     */
    public function actionAddSignOrder($goods_id, $ma_id)
    {
        $member_info = $this->user_info; //用户信息
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $address = $member_address . $addr_info['ma_area_info']; //收货地址
        $integral = $member_info['member_points'] - $goods_info['integral']; //用户所剩积分
        if ($integral < 0) {
            $this->jsonRet->jsonOutput(-2, '积分不足');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            //添加订单
            $order_date['goods_name'] = $goods_info['goods_name']; //商品名称
            $order_date['goods_pic'] = $goods_info['goods_pic']; //商品图片
            $order_date['create_time'] = time(); //兑换时间
            $order_date['integral'] = $goods_info['integral']; //需要积分
            $order_date['goods_id'] = $goods_id; //商品
            $order_date['addr'] = $address; //收货地址
            $order_date['ma_id'] = $ma_id; //
            $order_date['mobile'] = $addr_info['ma_mobile']; //收货人手机
            $order_date['username'] = $addr_info['ma_true_name']; //收货人
            $order_date['member_id'] = $member_info['member_id']; //用户
            (new OrderGoods())->insertOrderGoods($order_date); //添加订单商品
            (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '兑换失败');
        }
    }

}
