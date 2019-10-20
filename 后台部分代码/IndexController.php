<?php

namespace api\modules\v1\controllers;

use PhpOffice\PhpSpreadsheet\Reader\Xls\MD5;
use system\helpers\SysHelper;
use system\services\Navigation;
use system\services\Setting;
use system\services\TimeBuyGoods;
use yii\helpers\Html;
use system\services\TradeType;
use system\services\Institutions;
use Yii;
use frontend\services\imagine\Image;
use system\services\AdService;
use system\services\ApplyMoney;
use system\services\Goods;
use system\services\Theme;
use system\services\Warehouse;
use system\services\PanicBuy;
use system\services\PanicBuyGoods;
use system\services\GoodsClass;
use system\services\AdSite;
use system\services\FullDown;
use system\services\Article;
use system\services\MemberMail;
use system\services\PanicBuyScreenings;
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
use system\services\ProtocolService;
use system\services\Apply;
use system\services\SmsCode;
use system\services\TimeBuy;
use system\services\AreaGys;
use system\services\LanMu;
use system\services\DiscountPackageGoods;
use vendor\wxpay\pay;

use yii\helpers\Url;
use system\services\WxPay;
use system\models\UploadForm;
use yii\web\UploadedFile;
use system\models\MemberDB;


class IndexController extends BaseController
{

    /**
     * 首页
     */
    public function actionIndex()
    {
        $user_info = $this->user_info;
        $goods_model = new Goods();

        //消息 2019-05-08
        if ($user_info) {
            $messages_count = (new MemberMail())->getNewMailCount1($user_info['member_id']);
            if ($messages_count) {
                $messages_state = 1;
            } else {
                $messages_state = 0;
            }
        }else{
            $messages_state = 0;
        }

        $model_list = (new Setting())->getSettingConfigurator(['set_type' => 1]);
        $model_state = array_column($model_list, 'value', 'name');

        //轮播图
//        $info = (new AdSite())->getAdSiteInfo(['page_type' => 1, 'ads_type' => 2, 'ads_limit' => 0], 'ads_id');

        $adv = (new AdService())->getAdList(['ad_ads_id' => 49], '', '', '', 'ad_link,ad_pic,type');
        foreach ($adv as $key=> $val){
            $adv[$key]['ad_pic'] = SysHelper::getImage($val['ad_pic'],0,0,0,[0,0],1);
        }
        if (!count($adv)) {
            $adv[0]['ad_pic'] = SysHelper::getImage('/frontend/web/img/bg.jpg', 0, 0, 0, [0, 0], 1);
        }

        //系统公告 2019-05-08
        if ($model_state['xtgg'] == 1) {
//            $article = (new Article())->getArticleList(['a.article_type_id' => 24], '', '2', 'a.article_sort desc', 'article_id,article_title');
            $article = (new Article())->getArticleList(['a.article_type_id' => 24], '', '', 'a.article_sort desc', 'article_id,article_title');
        } else {
            $article = [];
        }

        //导航
        $navigation = (new Navigation())->getNavigationList(['nav_location' => 0, 'state' => 1], '', '', 'nav_sort asc,nav_id asc', 'nav_id,nav_new_open as type,nav_title,nav_pic,nav_url as class_id');
        foreach ($navigation as $key=> $val){
//            if ($val['type'] == 6) {
//                $lanm_info = (new LanMu())->getLanMuInfo(['id' => $val['class_id']]);
//                $navigation[$key]['class_id'] = $lanm_info ? $lanm_info['class_id'] : 0;
//            }
            $navigation[$key]['nav_pic'] = SysHelper::getImage($val['nav_pic'],0,0,0,[0,0],1);
        }

        //限时秒杀
        if ($model_state['xsms'] == 1) {
            $time = time();
            $map = ['and', 'pb_state=2', "pb_start_time<=$time", "pb_end_time>$time"];
            $xsms = (new PanicBuy())->getPanicBuyList($map, '', '', 'pb_pbs_start_time ASC', 'pb_id,pb_start_time,pb_end_time');
            $array = array_column($xsms, 'pb_id');
            $arr = [];
            $seconds_kill = [];
            if (!empty($xsms[0])) {
                $end_time = $xsms[0]['pb_end_time'];
                $state = 1;//活动进行中
                $time = $end_time - time(); //距离结束时间
                $array = (new PanicBuyGoods())->getPanicBuyGoodsList(['and', ['in', 'pbg_pb_id', $array], ['goods_state' => Yii::$app->params['GOODS_STATE_PASS']]], '', '', '', 'goods_id,goods_name,goods_price,goods_pic,pbg_stock,pbg_shop,goods_description');
                foreach ($array as $k1 => $v1) {
                    //限时抢购首页显示优惠价
                    $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $v1['goods_id']]);
                    $goods_discount_res = $goods_model->getGoodsDiscount($goods_info);

                    $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $goods_info['default_guige_id']]);

                    //多规格
                    $preferential = $goods_discount_res['discount_price'];
                    $pbg_price = $preferential[$goods_info['default_guige_id']] ?: $guige_info['guige_price'];
                    if ($v1['pbg_stock'] == $v1['pbg_shop']) {
                        $pbg_price = $guige_info['guige_price'];
                    }
                    $array[$k1]['pbg_price'] = floatval($pbg_price);
                    $array[$k1]['goods_price'] = floatval($v1['goods_price']);
                    $array[$k1]['goods_pic'] = SysHelper::getImage($v1['goods_pic'], 0, 0, 0, [0, 0], 1);
                    $array[$k1]['discount'] = round($pbg_price / $v1['goods_price'] * 10, 1); //折扣
//                    $array[$k1]['goods_description'] = $goods_info['goods_description'] ? explode('|', $goods_info['goods_description']) : [];
                }
                $arr = array_merge($arr, $array);
                $seconds_kill['state'] = $state;
                $seconds_kill['time'] = $this->timediff1($time);
                $seconds_kill['list'] = $arr;
            }
//            $time = time();
//            $date_h = date('H:i', time());
//            $info = (new PanicBuyScreenings())->getPanicBuyScreeningsInfo(['and', ['>', 'pbs_end_time', $date_h], ['<', 'pbs_start_time', $date_h]]);
//            $info['pbs_id'] = $info ? $info['pbs_id'] : 0;
//            $map = ['and', 'pb_state=2', "pb_start_time<=$time", "pb_end_time>$time", "pb_screenings_code={$info['pbs_id']}"];
//            $xsms = (new PanicBuy())->getPanicBuyList($map, '', '', 'pb_pbs_start_time ASC', 'pb_id,pb_pbs_start_time,pb_pbs_end_time');

//            $array = array_column($xsms, 'pb_id');
//            $arr = [];
//            $seconds_kill = [];
//            if (!empty($xsms[0])) {
//                $end_time = strtotime($xsms[0]['pb_pbs_end_time']);
//                $state = 1;//活动进行中
//                $time = $end_time - time(); //距离结束时间
//                $array = (new PanicBuyGoods())->getPanicBuyGoodsList(['and', ['in', 'pbg_pb_id', $array], ['goods_state' => Yii::$app->params['GOODS_STATE_PASS']]], '', '', '', 'goods_id,goods_name,goods_price,goods_pic,pbg_stock');
//                foreach ($array as $k1 => $v1) {
//                    //限时抢购首页显示优惠价
//                    $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $v1['goods_id']]);
//                    $goods_price = $goods_model->getGoodsDiscountAll($goods_info);
//                    //多规格
//                    $pbg_price = $goods_price['discount_price'][$goods_info['default_guige_id']];
//                    $array[$k1]['pbg_price'] = $pbg_price; //取最小价格
//                    $array[$k1]['goods_pic'] = SysHelper::getImage($v1['goods_pic'], 0, 0, 0, [0, 0], 1);
//                    $array[$k1]['discount'] = sprintf("%.2f", $pbg_price / $v1['goods_price']) * 10; //折扣
//                }
//                $arr = array_merge($arr, $array);
//                $seconds_kill['state'] = $state;
//                $seconds_kill['time'] = $this->timediff($time);
//                $seconds_kill['list'] = $arr;
//            }
        } else {
            $seconds_kill = [];
        }

        if (empty($seconds_kill['list']) || !count($seconds_kill['list'])){
            $model_state['xsms'] = 0;
        }


        //营销活动 2019-05-07
        if ($model_state['yxhd'] == 1) {
            $time = time();
            $map = ['and', 'fd_state=1', "fd_start_time<=$time", "fd_end_time>$time"];
            $yxhd = (new FullDown())->getFullDownList($map, '', '5', '', 'fd_id,fd_pic,fd_pic1');
            foreach ($yxhd as $k => $v) {
                if ($k > 1) {
                    $v['fd_pic'] = $v['fd_pic1'];
                }
                $yxhd[$k]['fd_pic'] = SysHelper::getImage($v['fd_pic'], 0, 0, 0, [0, 0], 1);
            }
        } else {
            $yxhd = [];
        }


        //模块
        if ($model_state['fllm'] == 1) {
            $good_class = (new LanMu())->getLanMuList(['state' => 1, 'type' => 2], '', '', 'sort desc', 'id,class_id,title as class_name');
            $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.default_guige_id,A.goods_description';
            foreach ($good_class as $k => $v) {
                $info = (new AdSite())->getAdSiteInfo(['ad_class' => $v['id'], 'page_type' => 1, 'ads_type' => 1], 'ads_id');
                $good_class_nav = (new AdService())->getAdList(['ad_ads_id' => $info['ads_id']], '', '1', '', 'ad_pic,ad_link,type');
                foreach ($good_class_nav as $k1 => $v1) {
                    $good_class_nav[$k1]['ad_pic'] = SysHelper::getImage($v1['ad_pic'], 0, 0, 0, [0, 0], 1);
                }
                $list = [];
                $theme_info = (new Theme())->getThemeInfo(['id' => $v['class_id']]);
                if ($theme_info) {
                    $goods_ids = $theme_info['goods_ids'];
                    $goods_state = Yii::$app->params['GOODS_STATE_PASS'];

                    $list = $goods_model->getGoodsList("A.goods_type in (1,3) and A.goods_state = $goods_state  and A.goods_id in ($goods_ids)", 0, 3, 'goods_sales desc', $fields);
                    foreach ($list as $key => $val) {
                        $pbg_price = $this->activity($val['goods_id']) ?: 0;
                        $list[$key]['pbg_price'] = floatval($pbg_price);
                        $list[$key]['goods_price'] = floatval($val['goods_price']);
                        $list[$key]['state'] = $pbg_price ? 1 : 0; //商品是否促销
                        $list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
//                        $list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
                    }
                }
                $good_class[$k]['list'] = $list;
                $good_class[$k]['good_class_nav'] = $good_class_nav;
            }
        } else {
            $good_class = [];
        }

        //热销
        if ($model_state['rx'] == 1) {
            $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.default_guige_id,A.goods_description';
            $hot_goods_where = ['and'];
            $hot_goods_where[] = ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']];
            $hot_goods_where[] = ['in', 'goods_type', [1, 3]];
            $hot_goods_list = $goods_model->getGoodsList($hot_goods_where, 0, 20, 'goods_sales desc,goods_id desc', $fields);
            foreach ($hot_goods_list as $key => $val) {
                $pbg_price = $this->activity($val['goods_id']);
                $hot_goods_list[$key]['pbg_price'] = floatval($pbg_price);
                $hot_goods_list[$key]['goods_price'] = floatval($val['goods_price']);
                $hot_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
                $hot_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
//                $hot_goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
            }
        } else {
            $hot_goods_list = [];
        }
        //特别推荐
        if ($model_state['tbtj'] == 1) {
            $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_description';
            $tj_goods_where = ['and'];
            $tj_goods_where[] = ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']];
            $tj_goods_where[] = ['goods_hot' => 1];
            $tj_goods_where[] = ['in', 'goods_type', [1, 3]];
            $tj_goods_list = $goods_model->getGoodsList($tj_goods_where, 0, 20, 'goods_hot desc', $fields);
            foreach ($tj_goods_list as $key => $val) {
                $pbg_price = $this->activity($val['goods_id']) ?: 0;
                $tj_goods_list[$key]['goods_price'] = floatval($val['goods_price']);
                $tj_goods_list[$key]['pbg_price'] = floatval($pbg_price);
                $tj_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
                $tj_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
//                $tj_goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
            }
        } else {
            $tj_goods_list = [];
        }

        //达人推荐 暂按评分排序
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_description';
        $talent_tj_goods_where = ['and'];
        $talent_tj_goods_where[] = ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']];
        $talent_tj_goods_where[] = ['in', 'goods_type', [1, 3]];
        $talent_tj_goods_list = $goods_model->getGoodsList($talent_tj_goods_where, 0, 20, 'goods_score desc', $fields);
        foreach ($talent_tj_goods_list as $key => $val) {
            $pbg_price = $this->activity($val['goods_id']) ?: 0;
            $talent_tj_goods_list[$key]['pbg_price'] = floatval($pbg_price);
            $talent_tj_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
            $talent_tj_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
//            $talent_tj_goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
        }
        $list = [
            'messages_state' => $messages_state,
            'adv_list' => $adv,
            'article' => $article, //公告
            'nav_list' => $navigation,
            'seconds_kill' => $seconds_kill, //秒杀
            'hot_goods_list' => $hot_goods_list, //热销
            'yxhd' => $yxhd, //营销活动
            'good_class' => $good_class, //栏目
            'tj_goods_list' => $tj_goods_list, //推荐
            'talent_tj_goods_list' => $talent_tj_goods_list,
            'model_state' => $model_state, //模块状态
        ];
        $this->jsonRet->jsonOutput(0,'加载成功',$list);
    }

    /**主题详情
     * @param $id 主题id
     * @param int $page
     */
    public function actionGoodsTheme($id, $page = 1)
    {
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $info = (new Theme())->getThemeInfo(['id' => $id]);
        $arr_id = explode(',', $info['goods_ids']);
        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['in', 'A.goods_type', [1, 3]], ['in', 'A.goods_id', $arr_id]];
        $totalCount = (new Goods())->getGoodsCount($condition);
        $goods_list = (new Goods())->getGoodsList($condition, $offset, $pageSize, 'goods_id desc', 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_sales,A.goods_description');
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            $goods_list[$key]['goods_price'] = floatval($val['goods_price']);
//            $goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**消息列表
     * @param int $page
     */
    public function actionMsg($page = 1)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $pageSize = 15;
        $offset = ($page - 1) * $pageSize;
        $messages_list = (new MemberMail())->getMailList($member_info['member_id'], $offset, $pageSize);
        foreach ($messages_list as $k => $v) {
            $messages_list[$k]['mail_time'] = $this->formatTime($v['mail_time']);
            $messages_list[$k]['mail_state'] = $v['mail_state'] ? 1 : 0;
            if ($v['mail_member_id'] == 'all') {
                $info = (new MemberMail())->getMailDelInfo(['mail_id' => $v['mail_id'], 'member_id' => $member_info['member_id']]);
                if ($info) {
                    $messages_list[$k]['mail_state'] = 1;
                }
            }
        }
        $totalCount = (new MemberMail())->getMailCount($member_info['member_id']);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $messages_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);

    }

    /**阅读信息
     * @param $mail_id 信息id
     */
    public function actionReadMsg($mail_id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $messages_info = (new MemberMail())->getMailInfo(['mail_id' => $mail_id]);
        if ($messages_info['mail_member_id'] == 'all') {
            $del_count = (new MemberMail())->getMemberMailDelCount(['mail_id' => $mail_id]);
            if ($del_count == $messages_info['mail_count']) {
                (new MemberMail())->updateMail(['mail_id' => $mail_id, 'mail_match' => 1]);
            }
        } else {
            (new MemberMail())->updateMail(['mail_id' => $mail_id, 'mail_match' => 1]);
        }
        $messages_info['mail_time'] = date('Y-m-d H:i:s', $messages_info['mail_time']);
        $del_info = (new MemberMail())->getMailDelInfo(['mail_id' => $mail_id, 'member_id' => $member_info['member_id']]);
        if (!$del_info) {
            (new MemberMail())->insertMailDel(['mail_id' => $mail_id, 'member_id' => $member_info['member_id'], 'mail_state' => 1]);
        }

        $this->jsonRet->jsonOutput(0, '加载成功', $messages_info);

    }

    function timediff($timediff)
    {
        $remain = $timediff % 86400;
        $hours = intval($remain / 3600);
        $remain = $remain % 3600;
        $mins = intval($remain / 60);
        $secs = $remain % 60;
        return $hours . ':' . $mins . ':' . $secs;
    }

    function timediff1($timediff)
    {
        $days = intval($timediff / 86400);
        $remain = $timediff % 86400;
        $hours = intval($remain / 3600);
        $remain = $remain % 3600;
        $mins = intval($remain / 60);
        $secs = $remain % 60;
        return $days . ',' . $hours . ':' . $mins . ':' . $secs;
    }

    /**商品列表|商品搜索|热销商品|推荐列表
     * @param int $class_id 分类id
     * @param string $keyword 搜索关键字
     */
    public function actionGoodsList($class_id = 0, $keyword = '', $coupon_id = 0, $is_zk = 0, $order = 'A.goods_id desc', $page = 1, $brand_id = '', $countrie_id = '', $low_price = 0, $high_price = 0, $is_tj = 0)
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['in', 'A.goods_type', [1, 3]]];

        //搜索关键字
        if ($keyword) {
            $member_info = $this->user_info;
            if ($member_info) {
                $sear_info = (new MemberSearch())->getMemberSearchInfo(['member_id' => $member_info['member_id'], 'str' => $keyword]);
                if (!$sear_info) {
                    (new MemberSearch())->insertMemberSearch(['member_id' => $member_info['member_id'], 'str' => $keyword, 'create_time' => time()]);
                }
            }
            $condition[] = ['or', ['like', 'A.goods_name', $keyword], ['like', 'A.goods_description', $keyword]];
        }

        $coupon_info = [];
        //优惠券
        if ($coupon_id) {
            $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $coupon_id]);
            $coupon_info['coupon_quota'] = floatval($coupon_info['coupon_quota']);
            $coupon_info['coupon_min_price'] = floatval($coupon_info['coupon_min_price']);
            $goods_id_arr = [];
            if ($coupon_info['type'] == 2) {
                $class_id_arr = explode(',', $coupon_info['goods_class_id']);
                $goods_list = (new Goods())->getGoodsList(['and', ['in', 'A.goods_class_id', $class_id_arr]]);
                if ($goods_list) {
                    $goods_id_arr = array_column($goods_list, 'goods_id');
                }
            }
            if ($coupon_info['type'] == 3) {
                $goods_id_arr = explode(',', $coupon_info['goods_id']);
            }
            if ($coupon_info['type'] != 1) {
                $condition[] = ['in', 'A.goods_id', $goods_id_arr];
            }
        }

        //最低价
        if ($low_price) {
            $condition[] = ['>=', 'A.goods_price', $low_price];
        }

        //最高价
        if ($high_price) {
            $condition[] = ['<=', 'A.goods_price', $high_price];
        }

        //是否限时折扣
        if ($is_zk) {
            $time_buy_list = (new TimeBuy())->getTimeBuyList(['and', ['<=', 'tb_start_time', time()], ['>=', 'tb_end_time', time()]]);
            $tb_id_arr = $time_buy_list ? array_column($time_buy_list, 'tb_id') : [];
            $time_buy_goods_list = (new TimeBuyGoods())->getTimeBuyGoodsList(['and', ['in', 'tbg_tb_id', $tb_id_arr]]);
            $goods_id_arr = $time_buy_goods_list ? array_column($time_buy_goods_list, 'tbg_goods_id') : [];
            $condition[] = ['in', 'A.goods_id', $goods_id_arr];
        }

        //分类
        $good_class_list = [];
        if ($class_id) {
            $good_class_info = (new GoodsClass())->getGoodsClassInfo(['class_id' => $class_id]);
            if ($good_class_info['class_parent_id'] == 0) {
                $good_class_list = (new GoodsClass())->getGoodsClassList(['class_parent_id' => $class_id], '', '', 'class_sort desc', 'class_id,class_name');
                $class_id_arr = $good_class_list ? array_column($good_class_list, 'class_id') : [];
                $condition[] = ['in', 'A.goods_class_id', $class_id_arr];
            } else {
                $condition[] = ['A.goods_class_id' => $class_id];
                $good_class_list = (new GoodsClass())->getGoodsClassList(['class_parent_id' => $good_class_info['class_parent_id']], '', '', 'class_sort desc', 'class_id,class_name');
            }
        }

        //品牌
        if ($brand_id) {
            $condition[] = ['A.brand_id' => $brand_id];
        }

        //是否推荐
        if ($is_tj) {
            $condition[] = ['A.goods_hot' => 1];
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
            $byorder = str_replace(array('1', '2', '3', '4'), array('A.goods_sales desc,A.goods_id desc', 'A.goods_sales asc,A.goods_id desc', 'A.goods_create_time desc,A.goods_id desc', 'A.goods_create_time asc,A.goods_id desc'), $order);
        }
        //商品
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_sales,A.goods_description';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            $pbg_price = $this->activity($val['goods_id'])?:0;
            $goods_list[$key]['pbg_price'] = floatval($pbg_price);
            $goods_list[$key]['goods_price'] = floatval($val['goods_price']);
            $goods_list[$key]['state'] =$pbg_price?1:0; //是否有优惠
//            $goods_list[$key]['goods_description'] = explode('|', $val['goods_description']);
        }

        if (!count($goods_list)) {
            //推荐商品 无商品时显示
            $goods_list1 = $goods_model->getGoodsList(['and', ['A.goods_hot' => 1], ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['in', 'A.goods_type', [1, 3]]], '', '6', 'A.goods_sales desc', $fields);
            foreach ($goods_list1 as $key => $val) {
                $goods_list1[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
                $pbg_price = $this->activity($val['goods_id']) ?: 0;
                $goods_list1[$key]['pbg_price'] = floatval($pbg_price);
                $goods_list1[$key]['state'] = $pbg_price ? 1 : 0; //是否有优惠
//                $goods_list1[$key]['goods_description'] = explode('|', $val['goods_description']);
            }
        } else {
            $goods_list1 = [];
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'list1' => $goods_list1, 'goods_class' => $good_class_list, 'pages' => $page_arr, 'coupon_info' => $coupon_info];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**营销活动列表
     * @param int $page
     */
    public function actionGoodsFullDownList($page=1){
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $time = time();
        $map = ['and', 'fd_state=1', "fd_start_time<=$time", "fd_end_time>$time"];
        $yxhd = (new FullDown())->getFullDownList($map,$offset, $pageSize,'','fd_id,fd_pic');
        $totalCount = (new FullDown())->getFullDownCount($map);
        foreach ($yxhd as $k => $v){
            $yxhd[$k]['fd_pic'] = SysHelper::getImage($v['fd_pic'],0,0,0,[0,0],1);
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $yxhd, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0,'加载成功',$show);
    }

    /**营销活动商品列表
     * @param int $class_id 分类id
     * @param string $keyword 搜索关键字
     */
    public function actionGoodsFullDownGoodsList($fd_id,$order='A.goods_id desc',$page=1,$brand_id='',$countrie_id='',$low_price=0,$high_price=0)
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;

        $info = (new FullDown())->getFullDownInfo(['fd_id' => $fd_id], 'fd_title,type,fd_end_time,fd_start_time');
        $rule_list = (new FullDownRule())->getFullDownRuleList(['fdr_fd_id' => $fd_id], 'fdr_deduct,fdr_quata');
        $rule_info = $rule_list[0];

        $info['fdr_deduct'] = floatval($rule_info['fdr_deduct']);
        $info['fdr_quata'] = floatval($rule_info['fdr_quata']);
        if ($info['type'] == 1) {
            $info['fd_title'] = '以下商品满' . floatval($rule_info['fdr_quata']) . '元减' . floatval($rule_info['fdr_deduct']) . '元';
        }
        if ($info['type'] == 2) {
            $info['fd_title'] = '以下商品满' . floatval($rule_info['fdr_quata']) . '件享' . floatval($rule_info['fdr_deduct']) . '折';
        }
        if ($info['fd_start_time'] > time()) {
            $this->jsonRet->jsonOutput(-1, '活动还未开始');
        }
        if ($info['fd_end_time'] < time()) {
            $this->jsonRet->jsonOutput(-1, '活动已结束');
        }

        $yxhd = (new FullDownGoods())->getFullDownGoodsList(['fdg_fd_id'=>$fd_id]);
        $ids_arr = array_column($yxhd,'fdg_goods_id');
        foreach ($yxhd as $k => $v){
            $yxhd[$k]['fd_pic'] = SysHelper::getImage($v['fd_pic'],0,0,0,[0,0],1);
        }

        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['in', 'A.goods_type', [1, 3]], ['in', 'A.goods_id', $ids_arr]];

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
            $byorder = str_replace(array('1', '2', '3', '4'), array('A.goods_sales desc', 'A.goods_sales asc', 'A.goods_create_time desc', 'A.goods_create_time asc'), $order);
        }
        //商品
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.goods_sales,A.goods_description';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            $pbg_price = $this->activity($val['goods_id'])?:0;
            $goods_list[$key]['pbg_price'] = floatval($pbg_price);
            $goods_list[$key]['goods_price'] = floatval($val['goods_price']);
            $goods_list[$key]['state'] =$pbg_price?1:0; //是否有优惠
//            $goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr, 'info' => $info];
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
    public function actionSalesGoodsList($class_id=0,$keyword='',$order='A.goods_sales desc',$page=1,$brand_id='',$countrie_id='',$low_price=0,$high_price=0){
        $this->actionGoodsList($class_id,$keyword,$order,$page,$brand_id,$countrie_id,$low_price,$high_price);
    }

    /**
     * @return string 商品详情 常规商品详情|限时秒杀详情
     */
    public function actionDetail($goods_id, $tjm = '')
    {
        $member_info = $this->user_info;
        $goods_model = new Goods();
        if (!$goods_id) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_NULL']['ERROR_NO'], $this->errorRet['GOODS_NOT_NULL']['ERROR_MESSAGE']);
        }

        if ($tjm) {
            if (!$member_info) {
                $this->jsonRet->jsonOutput(-400, '请登录');
            } elseif ($member_info) {
                $id = substr($tjm, 3); //默认id前加3位字符串
                $member = (new Member())->getMemberInfo(['member_id' => $id]);
                $member1 = (new Member())->getMemberInfo(['tjm' => $tjm]);
                if ($member || $member1) {
                    $tjm = $member1 ? 'YGH' . $member1['member_id'] : $tjm;
                    $this->bind_member($member_info, $tjm);
                }
            }

        }
        $goods_url = '';
        if ($member_info) {
            $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
            $goods_url = $http_type . $_SERVER['SERVER_NAME'] . "/yghsc/api/modules/detail?goods_is=$goods_id&tjm=YGH" . $member_info['member_id'];
        }

        $fields = 'A.goods_id,A.goods_name,A.goods_mobile_content,A.goods_pc_content,A.default_guige_id,A.goods_remark,A.goods_pic,A.goods_description as labels,A.tradetype,A.goods_price,A.brand_id,A.goods_stock,A.countrie_id,A.goods_sales,A.goods_promotion_type,A.goods_promotion_id,A.goods_full_down_id,E.goods_pic1,E.goods_pic2,E.goods_pic3,E.goods_pic4';
        $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $goods_id], $fields);
        $goods_info['goods_remark'] ?: null;
        if (!$goods_info) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_EXIST']['ERROR_NO'], $this->errorRet['GOODS_NOT_EXIST']['ERROR_MESSAGE']);
        }
        $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);

        $goods_pic_list = empty($goods_info['goods_pic1']) ? [] : explode(',', $goods_info['goods_pic1']);
        $goods_pic1[] = $goods_info['goods_pic'];
        if (count($goods_pic_list)) {
            foreach ($goods_pic_list as $k => $v) {
                $goods_pic1[] = SysHelper::getImage($v, 0, 0, 0, [0, 0], 1);
            }
        }
        $goods_info['goods_pic'] = $goods_pic1;
        $goods_info['labels'] = $goods_info['labels'] ? explode('|', $goods_info['labels']) : []; //商品标签
        //品牌
        $goods_info['brand'] = (new Brand())->getBrandInfo(['brand_id'=>$goods_info['brand_id']],'brand_id,brand_name,brand_pic');
        $goods_info['brand']['brand_pic'] = SysHelper::getImage($goods_info['brand']['brand_pic'], 0, 0, 0, [0, 0], 1);

        //地区
        $goods_info['countries'] = (new Countrie())->getCountrieInfo(['countrie_id'=>$goods_info['countrie_id']],'countrie_id,countrie_pic,countrie_name');
        $goods_info['countries']['countrie_pic'] = SysHelper::getImage($goods_info['countries']['countrie_pic'], 0, 0, 0, [0, 0], 1);

        //商品类型
        $goods_info['tradeType'] = [];
        if ($goods_info['tradetype']) {
            $goods_info['tradeType'] = (new Warehouse())->getWarehouseInfo(['tradeType' => $goods_info['tradetype']]);
        }

        //获取商品规格
        $goods_info['goods_guige_list'] = (new GuiGe())->getGuiGeList(['goods_id' => $goods_id], '', '', 'guige_sort desc,guige_price asc', 'guige_id,guige_name,guige_price,guige_no,guige_num');

        $member_coupon_where =  ['and'];
        $member_coupon_where[] =  ['A.mc_member_id'=>$member_info['member_id']];
        $member_coupon_where[] =  ['A.mc_state'=>1];
        $member_coupon_where[] =  ['>','A.mc_use_time',0];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where);
        $member_coupon_id_arr = $member_coupon_list?array_column($member_coupon_list,'mc_coupon_id'):[];

        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $goods_info['goods_mobile_content'] = str_replace('/yghsc/data/uploads/store/', $http_type . $_SERVER['SERVER_NAME'] . '/yghsc/data/uploads/store/', $goods_info['goods_mobile_content']);
        $goods_info['goods_mobile_content'] = str_replace('/ueditor/image/', $http_type . $_SERVER['SERVER_NAME'] . '/ueditor/image/', $goods_info['goods_mobile_content']);


        //获取可领取优惠券
        $now_time = time();
        $coupon_where = ['and'];
        $coupon_where[] = ['coupon_state' => 1];
        $coupon_where[] = ['coupon_type' => 1];
        $coupon_where[] = ['<=', 'coupon_start_time', $now_time];
        $coupon_where[] = ['>=', 'coupon_end_time', $now_time];
        $coupon_where[] = ['!=', 'coupon_cc_id', 10]; //注册礼包除外
        $coupon_list1 = [];
        $coupon_list = (new Coupon())->getCouponList($coupon_where, '', '', 'coupon_quota desc', 'coupon_quota,coupon_min_price,coupon_title,coupon_img,coupon_id,coupon_start_time,coupon_end_time,coupon_img,coupon_limit,coupon_num,coupon_grant_num,coupon_content');
        foreach ($coupon_list as $k => $v) {
            if (!$this->checkCoupon($goods_id, $v['coupon_id'])) {
                //对商品不可用
                unset($coupon_list[$k]);
                continue;
            }
            if (in_array($v['coupon_id'],$member_coupon_id_arr)) {
                //已使用
                unset($coupon_list[$k]);
                continue;
            }
            $coupon_list[$k]['state'] = 0; //可领取
            $v['state'] = 0;
            if ($member_info) {
                $member_coupon_num = (new MemberCoupon())->getMemberCouponCount(['mc_coupon_id' => $v['coupon_id'], 'mc_member_id' => $member_info['member_id']]);
                if ($member_coupon_num == $v['coupon_limit']) {
                    //已领取张数满 单人
                    $coupon_list[$k]['state'] = 1; //已领取
                    $v['state'] = 1;
                }
                if ($v['coupon_num'] == $v['coupon_grant_num']) {
                    //已领完
                    $coupon_list[$k]['state'] = 2; //已领完
                    $v['state'] = 2;
                }
            }
            $couponlist1 = $v;
            $couponlist1['coupon_start_time'] = date('Y.m.d', $v['coupon_start_time']);
            $couponlist1['coupon_end_time'] = date('Y.m.d', $v['coupon_end_time']);
            $couponlist1['coupon_min_price'] = floatval($v['coupon_min_price']);
            $couponlist1['coupon_quota'] = floatval($v['coupon_quota']);
            $couponlist1['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1);
            $coupon_list1[] = $couponlist1;
        }
        //获取可用优惠券

        //促销情况
        $goods_info['is_panic_buy'] = 0;
        $goods_discount_res = $goods_model->getGoodsDiscount($goods_info);
        $panic_buy_state = 1; //有促销
        if ($goods_discount_res['state'] == 2) {//多规格商品促销
            $preferential = $goods_discount_res['discount_price'];
            //距离促销结束时间
            if ($goods_info['goods_promotion_type'] == 1) {

                $panic_buy_goods_info = (new PanicBuyGoods())->getPanicBuyGoodsInfo(['pbg_pb_id' => $goods_info['goods_promotion_id'], 'pbg_goods_id' => $goods_id]); //抢购商品详情

                //获取秒杀活动
                $pb_info = (new PanicBuy())->getPanicBuyInfo(['pb_id' => $goods_info['goods_promotion_id']]);
                $to_end_time = $pb_info['pb_end_time'] - time();
                $goods_info['to_end_time'] = $this->timediff1($to_end_time); //距离抢购结束时间
                $goods_info['is_panic_buy'] = 1; //是否抢购商品
                $goods_info['remaining'] = $panic_buy_goods_info['pbg_stock']; //限购件数
                $goods_info['remaining'] = $panic_buy_goods_info['pbg_stock']; //限购件数
            } elseif ($goods_info['goods_promotion_type'] == 2) {
                //限时折扣
                $pb_info = (new TimeBuy())->getTimeBuyInfo(['tb_id' => $goods_info['goods_promotion_id']]);
                $to_end_time = $pb_info['tb_end_time'] - time();
                $goods_info['to_end_time'] = $this->timediff1($to_end_time); //距离抢购结束时间
            }
        }else{
            $preferential = [];
            $goods_info['to_end_time'] = 0;
            $panic_buy_state = 0; //没有促销
        }
        $goods_info['panic_buy_state'] = $panic_buy_state; //是否有促销
        $goods_info['goods_price'] = floatval($goods_info['goods_price']);

        foreach ($goods_info['goods_guige_list'] as $k => $v){
            $preferential_price = !empty($preferential[$v['guige_id']]) ? $preferential[$v['guige_id']] : $v['guige_price'];
            $goods_info['goods_guige_list'][$k]['preferential_price'] = floatval($preferential_price);
            $goods_info['goods_guige_list'][$k]['guige_price'] = floatval($v['guige_price']);
            if ($v['guige_id'] == $goods_info['default_guige_id']){
                //商品默认规格信息
                $goods_info['goods_no'] = $v['guige_no'];
                $goods_info['guige_name'] = $v['guige_name'];
                $goods_info['preferential_price'] = floatval($preferential_price);
            }
        }

        //用户评论
        $fields = 'G.geval_id,G.geval_content,G.geval_isanonymous,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $goods_evaluate = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_id, 'G.geval_state' => 0], 0, 2, 'geval_id desc', $fields);
        foreach ($goods_evaluate as $k => $v) {
            if ($v['geval_isanonymous']) {
                $goods_evaluate[$k]['geval_frommembername'] = '匿名';
            }
        }
        //总评论数
        $goods_evaluate_count = (new Evaluate())->getEvaluateGoodsCount(['geval_goodsid' => $goods_id, 'geval_state' => 0]);

        //营销信息(满减)
        $goods_promotion_full_info = (new FullDownGoods())->getFullDownGoodsRule($goods_id, 'fd.fd_id,fd.type,fd.fd_title');

        //服务说明
        $fwsm_list = (new ProtocolService)->getProtocolList(['del' => 0, 'type' => 2], '', '', '', 'title,content,img');
        foreach ($fwsm_list as $k => $v) {
            $fwsm_list[$k]['img'] = SysHelper::getImage($v['img'], 0, 0, 0, [0, 0], 1);
        }


        //拼团
        $now_time = time();
        $bulk_condition = ['and'];
        $bulk_condition[] = ['goods_id'=>$goods_id];
        $bulk_condition[] = ['>=', 'end_time', $now_time];
        $bulk_condition[] = ['<=', 'start_time', $now_time];
        $goods_bulk_list = (new Bulk())->getBulkList($bulk_condition,'','','','');
        $goods_bulk_state = count($goods_bulk_list)?1:0; //是否参与拼团

        //优惠套餐
        $package = (new DiscountPackage())->getDiscountPackageListByGoodsIdByApi1($goods_id);
        foreach ($package as $k => $v) {
            $old_money = 0;
            foreach ($v['goods_list'] as $k1 => $v1) {
                $old_money += $v1['guige_price'];
                $package[$k]['goods_list'][$k1]['guige_price'] = floatval($v1['guige_price']);
            }
            $package[$k]['old_money'] = floatval($old_money);
            $package[$k]['dp_discount_price'] = floatval($v['dp_discount_price']);
            $package[$k]['preferential_money'] = floatval(($old_money * 100 - $v['dp_discount_price'] * 100) / 100);
        }

        //是否收藏该商品
        $is_collect_goods = 0;
        $member_info = $this->user_info;
        if ($member_info) {
            if ($member_info && (new MemberFollow())->getMemberFollowCount(['fav_id' => $goods_id, 'fav_type' => 'goods', 'member_id' => $member_info['member_id']]) > 0) {
                $is_collect_goods = 1;
            }
        }

        unset($goods_info['goods_market_price']);
        unset($goods_info['goods_promotion_type']);
        unset($goods_info['goods_promotion_id']);
        unset($goods_info['goods_full_down_id']);
        unset($goods_info['goods_pic1']);
        unset($goods_info['goods_pic2']);
        unset($goods_info['goods_pic3']);
        unset($goods_info['goods_pic4']);

        $data = [
            'goods_info' => $goods_info, //商品详情
            'is_collect_goods' => $is_collect_goods, //是否收藏
            'goods_evaluate_count' => $goods_evaluate_count, //评论数
            'goods_evaluate' => $goods_evaluate, //评论
            'goods_bulk_state' => $goods_bulk_state, //是否参与拼团
            'discount_package_state' => count($package)?1:0, //是否有优惠套餐
            'discount_package' => $package, //优惠套餐
            'goods_promotion_full_state' => $goods_promotion_full_info?1:0, //是否有满减
            'goods_promotion_full_info' => $goods_promotion_full_info, //满减
            'coupon_list' => $coupon_list1,
            'goods_url' => $goods_url,
            'fwsm_list' => $fwsm_list,
        ];
        $this->jsonRet->jsonOutput(0, '', $data);
    }

    /**
     * 获取商品评价列表
     */
    public function actionEvaluate($goods_id,$page=1)
    {
        $evaluate_model = new Evaluate();
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;

        if (!$goods_id) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_NULL']['ERROR_NO'], $this->errorRet['GOODS_NOT_NULL']['ERROR_MESSAGE']);
        }
        $condition = ['geval_goodsid' => $goods_id, 'geval_state' => 0];
        $totalCount = $evaluate_model->getEvaluateGoodsCount($condition);

        //商品
        $fields = 'G.geval_id,G.geval_isanonymous,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $list = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_id, 'G.geval_state' => 0], $offset, $pageSize, 'geval_addtime desc', $fields);
        foreach ($list as $k => $v) {
            if ($v['geval_isanonymous']) {
                $list[$k]['geval_frommembername'] = '匿名';
            }
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * 商品分类
     */
    public function actionClass($parent_id=0)
    {
        $class_model = new GoodsClass();
        $class1 = $class_model->getGoodsClassList(['class_parent_id' => 0], '', '', 'class_sort desc', 'class_id,class_name,class_pic,class_pic1');
        foreach ($class1 as $key1 => $val1) {
            $class1[$key1]['class_pic'] = SysHelper::getImage($val1['class_pic'], 0, 0, 0, [0, 0], 1);
            $class1[$key1]['class_pic1'] = SysHelper::getImage($val1['class_pic1'], 0, 0, 0, [0, 0], 1);
            $class2 = $class_model->getGoodsClassList(['class_parent_id' => $val1['class_id']], '', '', 'class_sort desc', 'class_id,class_name,class_pic');
            foreach ($class2 as $key2 => $val2) {
                $class2[$key2]['class_pic'] = SysHelper::getImage($val2['class_pic'], 0, 0, 0, [0, 0], 1);
            }
            $class1[$key1]['child'] = $class2;
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $class1);
    }

    /**品牌详情
     * @param $brand_id 品牌id
     */
    public function actionBrandDetail($brand_id){
        $brand_info = (new Brand())->getBrandInfo(['brand_id'=>$brand_id],'brand_id,brand_name,brand_pic,brand_content');
        $brand_info['brand_pic'] = SysHelper::getImage($brand_info['brand_pic'], 0, 0, 0, [0, 0], 1);
        $brand_info['goods_num'] = (new Goods())->getGoodsCount(['and', ['in', 'A.goods_type', [1, 3]], ['A.brand_id' => $brand_id], ['A.goods_state' => 20]]);
        $goods_list = (new Goods())->getGoodsList(['and', ['in', 'A.goods_type', [1, 3]], ['A.brand_id' => $brand_id], ['A.goods_state' => 20]], '', '6', 'A.goods_sales desc', 'A.goods_id,A.goods_pic,A.goods_name,A.goods_pic,A.goods_price'); //取前六条
        foreach ($goods_list as $k => $v) {
            $goods_list[$k]['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
            $pbg_price = $this->activity($v['goods_id']) ?: 0;
            $goods_list[$k]['pbg_price'] = floatval($pbg_price);
            $goods_list[$k]['state'] = $pbg_price ? 1 : 0; //是否有优惠
        }
        $brand_info['goods_list'] = $goods_list;
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_info);
    }

    /**
     * 品牌列表
     */
    public function actionBrandList(){
        $brand_list = (new Brand())->getBrandList([],'','','brand_sort desc','brand_id,brand_name,brand_pic');
        foreach ($brand_list as $key => $val) {
            $brand_list[$key]['brand_pic'] = SysHelper::getImage($val['brand_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $brand_list);
    }

    /**
     * 地区列表
     */
    public function actionCountriesList(){
        $countrie_list = (new Countrie())->getCountrieList('', '', '', 'countrie_sort desc', 'countrie_id,countrie_pic,countrie_name,zhou_id');
        foreach ($countrie_list as $key => $val) {
            $countrie_list[$key]['countrie_pic'] = SysHelper::getImage($val['countrie_pic'], 0, 0, 0, [0, 0], 1);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $countrie_list);
    }

    /**
     * 品牌列表
     */
    public function actionBrandList1()
    {
        $brand_list = (new Brand())->getBrandList([], '', '', 'brand_sort desc', 'brand_id,brand_name,brand_pic,brandf');
        foreach ($brand_list as $key => $val) {
            $brand_list[$key]['brand_pic'] = SysHelper::getImage($val['brand_pic'], 0, 0, 0, [0, 0], 1);
            $k = $val['brandf'] ?: 0;
            $data[$k][] = $val;
        }
        ksort($data);
        $this->jsonRet->jsonOutput(0, '加载成功', $data);
    }

    /**
     * 地区列表
     */
    public function actionCountriesList1()
    {
        $arr = ['', '亚洲', '欧洲', '北美洲', '南美洲', '非洲', '大洋洲'];
        $countrie_list = (new Countrie())->getCountrieList('', '', '', 'countrie_sort desc', 'countrie_id,countrie_pic,countrie_name,zhou_id');
        foreach ($countrie_list as $key => $val) {
            $countrie_list[$key]['countrie_pic'] = SysHelper::getImage($val['countrie_pic'], 0, 0, 0, [0, 0], 1);
            $data[$arr[$val['zhou_id']]][] = $val;
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $data);
    }

    /**
     * 我的地址列表
     */
    public function actionAddrList(){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $addr_list = (new MemberAddress())->getMemberAddressList(['ma_member_id'=>$member_id],'','','','');
        foreach ($addr_list as $k => $v){
            //$member_address = (new Area())->lowGetHigh($v['ma_area_id']);
            $list[$k]['ma_id'] = $v['ma_id'];
            $list[$k]['ma_true_name'] = $v['ma_true_name'];
//            $list[$k]['ma_addr'] = $member_address.$v['ma_area_info'];
            $list[$k]['ma_addr'] = $v['ma_area'] . $v['ma_area_info'];
            $list[$k]['ma_mobile'] = $v['ma_mobile'];
            $list[$k]['ma_is_default'] = $v['ma_is_default'];
            $list[$k]['ma_label'] = $v['ma_label'] ?: null;
        }
        $this->jsonRet->jsonOutput(0, '加载成功',$list);
    }

    /**
     * 地址默认标签
     */
    public function actionDefaultAddrLabel()
    {
        $addr_label = (new Setting())->getSetting('addr_label');
        $list = explode('|', $addr_label);
        $this->jsonRet->jsonOutput(0, '加载成功', ['list' => $list]);
    }

    /**
     * 基础设置
     */
    public function actionSet($type)
    {
        if ($type == 1) {
            //评论标签
            $addr_label = (new Setting())->getSetting('pjbq');
            $list = explode('|', $addr_label);
            $this->jsonRet->jsonOutput(0, '加载成功', ['list' => $list]);
        }
        if ($type == 2) {
            //积分任务
            $list['gwjf'] = (new Setting())->getSetting('gwjf');
            $list['comments_integral'] = (new Setting())->getSetting('comments_integral');
            $this->jsonRet->jsonOutput(0, '加载成功', $list);
        }
    }

    /** 添加收货地址
     * @param $ma_true_name 身份证姓名
     * @param $ma_mobile 手机号
     * @param $ma_area_id 省市区
     * @param $ma_area_info 详细地址
     * @param $ma_label 标签
     * @param $ma_card_no 身份证号
     * @param $ma_card1 身份证正面
     * @param $ma_card2 身份证反面
     * @param $ma_is_default 是否默认地址
     */
    public function actionAddAddr($ma_true_name, $ma_mobile, $ma_area_id, $ma_area_info, $ma_label = '', $ma_card_no, $ma_card1 = '', $ma_card2 = '', $ma_is_default = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $info = (new MemberAddress())->getMemberAddressInfo(['ma_member_id' => $member_info['member_id'], 'ma_card_no' => $ma_card_no]);
        if ($info) {
            $this->jsonRet->jsonOutput(-1, '该身份证号码已添加');
        }
        $city = explode(',', $ma_area_id)[2];
        $info = (new AreaGys)->getAreaGysInfo(['and', ['like', 'regionName', $city], ['regionGrade' => 3]]);
        if (!$info) {
            $this->jsonRet->jsonOutput(-1, '城市不存在');
        }
        if (!is_numeric($ma_mobile)) {
            $this->jsonRet->jsonOutput(-1, '手机号不合法');
        }
        if ($ma_is_default == 1) {
            (new MemberAddress())->updateMemberAddressByCondition(['ma_is_default' => 0], ['ma_member_id' => $member_id]);
        }
        $addr_data['ma_member_id'] = $member_id;
        $addr_data['ma_mobile'] = $ma_mobile;
        $addr_data['ma_true_name'] = $ma_true_name;
        $addr_data['ma_area'] = $ma_area_id;
        $addr_data['ma_area_info'] = $ma_area_info;
        $addr_data['regionId'] = $info['regionId']; //城市编码 供应商添加订单需要 //第三极 区级
        $addr_data['ma_label'] = $ma_label;
        $addr_data['ma_card_no'] = $ma_card_no;
        $addr_data['ma_card1'] = $ma_card1;
        $addr_data['ma_card2'] = $ma_card2;
        $addr_data['ma_is_default'] = $ma_is_default;
        $result = (new MemberAddress())->insertMemberAddress($addr_data);
        if ($result){
            $this->jsonRet->jsonOutput(0, '添加成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '添加失败');
        }
    }

    /**上传图片
     * @return string|void
     */
    public function actionUpImg($img_type = 0)
    {
        $file = $_FILES['file'];//得到传输的数据
        $name = $file['name'];
        $type = strtolower(substr($name,strrpos($name,'.')+1)); //得到文件类型，并且都转化成小写
        $allow_type = array('jpg','jpeg','gif','png'); //定义允许上传的类型
        if(!in_array($type, $allow_type)){
            $this->jsonRet->jsonOutput(-2, '文件不合法');
        }
        $upload_path = "../../data/uploads/"; //上传文件的存放路径
        $new_file_name = date('YmdHis').rand(100,999).'.'.$type;
        //开始移动文件到相应的文件夹
        if(move_uploaded_file($file['tmp_name'],$upload_path.$new_file_name)){
            if ($img_type == 1) {
                $arr = getimagesize("../../data/uploads/" . $new_file_name);
                $size = $arr[0] > $arr[1] ? $arr[1] : $arr[0];
                Image::crop("../../data/uploads/" . $new_file_name, $size, $size)->save(Yii::getAlias("../../data/uploads/" . $new_file_name), ['quality' => 100]);
            }
            $this->jsonRet->jsonOutput(0, '上传成功',['filename'=>"/data/uploads/".$new_file_name]);
        }else{
            $this->jsonRet->jsonOutput(-1, '上传失败');
        }
    }

    /**
     * 地址详情
     * @param $ma_id 地址id
     */
    public function actionAddrDetail($ma_id){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $addr_detail = (new MemberAddress())->getMemberAddressInfo(['ma_member_id'=>$member_id,'ma_id'=>$ma_id]);
        if (!$addr_detail){
            $this->jsonRet->jsonOutput(-1, '参数错误');
        }
//        $member_address = (new Area())->lowGetHigh($addr_detail['ma_area_id']);
        $detail['ma_id'] = $addr_detail['ma_id'];
        $detail['ma_true_name'] = $addr_detail['ma_true_name'];
        $detail['ma_addr'] = $addr_detail['ma_area_info'];
        $detail['ma_ssq'] = $addr_detail['ma_area'];
        $detail['ma_mobile'] = $addr_detail['ma_mobile'];
        $detail['ma_is_default'] = $addr_detail['ma_is_default'];
        $detail['ma_label'] = $addr_detail['ma_label'];
        $detail['ma_card_no'] = $addr_detail['ma_card_no'];
        $detail['ma_card1'] = SysHelper::getImage($addr_detail['ma_card1'], 0, 0, 0, [0, 0], 1);
        $detail['ma_card2'] = SysHelper::getImage($addr_detail['ma_card2'], 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '加载成功',$detail);
    }

    /**修改收货地址
     * @param $ma_id
     * @param $ma_true_name 身份证姓名
     * @param $ma_mobile 手机号
     * @param $ma_area_id 省市区
     * @param $ma_area_info 详细地址
     * @param $ma_label 标签
     * @param $ma_card_no 身份证号
     * @param $ma_card1 身份证正面
     * @param $ma_card2 身份证反面
     * @param $ma_is_default 是否默认地址
     */
    public function actionEditAddr($ma_id,$ma_true_name='',$ma_mobile='',$ma_area_id='',$ma_area_info='',$ma_label='',$ma_card_no='',$ma_card1='',$ma_card2='',$ma_is_default=0){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $addr_data['ma_id'] = $ma_id;
        $ma_mobile?$addr_data['ma_mobile'] = $ma_mobile:'';
        $ma_true_name?$addr_data['ma_true_name'] = $ma_true_name:'';
        $ma_area_id ? $addr_data['ma_area'] = $ma_area_id : '';
        $ma_area_info?$addr_data['ma_area_info'] = $ma_area_info:'';
        $ma_label?$addr_data['ma_label'] = $ma_label:'';
        $ma_card_no?$addr_data['ma_card_no'] = $ma_card_no:'';
        $ma_card1?$addr_data['ma_card1'] = $ma_card1:'';
        $ma_card2?$addr_data['ma_card2'] = $ma_card2:'';
        $addr_data['ma_is_default'] = $ma_is_default;
        $result = (new MemberAddress())->updateMemberAddress($addr_data);
        if ($ma_is_default==1){
            (new MemberAddress())->updateMemberAddressByCondition(['ma_is_default'=>0],['!=','ma_id',$ma_id]);
        }
        if ($result !== false){
            $this->jsonRet->jsonOutput(0, '修改成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '修改失败');
        }
    }

    /**删除收货地址
     * @param $ma_id
     */
    public function actionDelAddr($ma_id){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $result = (new MemberAddress())->deleteMemberAddress($ma_id);
        if ($result){
            $this->jsonRet->jsonOutput(0, '操作成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**提交推荐码
     * @param $recommended_no
     */
    public function actionBindRecommended($recommended_no){
        $id = substr($recommended_no, 3); //默认id前加3位字符串
        $member = (new Member())->getMemberInfo(['member_id' => $id]);
        if (!$member || $recommended_no != 'YGH' . $id) {
            $this->jsonRet->jsonOutput(-3, '推荐码错误');
        }
        $this->jsonRet->jsonOutput(0, '推荐码正确');
    }

    /**绑定推荐码
     * @param $recommended_no
     */
    public function actionBindTjm($tjm)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $id = substr($tjm, 3); //默认id前加3位字符串
        $member = (new Member())->getMemberInfo(['member_id' => $id]);
        $member1 = (new Member())->getMemberInfo(['tjm' => $tjm]);
        if (!$member && !$member1) {
            $this->jsonRet->jsonOutput(-3, '推荐码错误');
        }
        $this->bind_member($member_info, $tjm);
        $this->jsonRet->jsonOutput(0, '成功');
    }

    /**
     * 我的资料
     */
    public function actionMy(){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $data['member_avatar'] = SysHelper::getImage($member_info['member_avatar'], 0, 0, 0, [0, 0], 1);
        $data['member_name'] = $member_info['member_name'];
        $data['member_mobile'] = $member_info['member_mobile'];
        $data['member_sex'] = $member_info['member_sex'];
        $this->jsonRet->jsonOutput(0, '加载成功',$data);
    }

    /**修改我的资料
     * @param $member_avatar
     * @param $member_name
     * @param $member_sex
     */
    public function actionUpdateMy($member_avatar = '', $member_name = '', $member_sex = '')
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (mb_strlen($member_name, 'utf-8') > 10) {
            $this->jsonRet->jsonOutput(-2, '昵称最多只能为10位汉字或字母数字');
        }
        $member_data['member_id'] = $member_info['member_id'];
        $member_data['member_avatar'] = $member_avatar;
        $member_data['member_name'] = $member_name;
        $member_data['member_sex'] = $member_sex;
        $result = (new Member())->updateMember($member_data);
        if ($result){
            $this->jsonRet->jsonOutput(0, '修改成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '修改失败');
        }
    }

    /**修改密码
     * @param $old_pwd
     * @param $new_pwd
     */
    public function actionEditPassword($old_pwd = '', $new_pwd = '')
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $old_pwd = trim($old_pwd);
        if (!$old_pwd) {
            $this->jsonRet->jsonOutput(-4, '请输入旧密码');
        }
        $new_pwd = trim($new_pwd);
        if (!$new_pwd) {
            $this->jsonRet->jsonOutput(-4, '请输入新密码');
        }
        $old_pwd = md5($old_pwd);
        $info =  (new Member())->getMemberInfo(['member_password'=>$old_pwd,'member_id'=>$member_info['member_id']]);
        if (!$info){
            $this->jsonRet->jsonOutput(-2, '原密码不正确');
        }
        $member_data['member_id'] = $member_info['member_id'];
        $member_data['member_password'] = md5($new_pwd);
        $result = (new Member())->updateMember($member_data);
        if ($result){
            $this->jsonRet->jsonOutput(0, '修改成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '修改失败');
        }

    }

    /**修改手机号码
     * @param $mobile
     * @param $code
     */
    public function actionUpdateMobile($mobile, $pwd = '', $code)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if ((new Member())->getMemberInfo(['member_mobile' => $mobile])) {
            $this->jsonRet->jsonOutput(-2, '该手机号已被其他用户绑定');
        }
        $sms_condition = ['and'];
        $sms_condition[] = ['member_mobile'=>$mobile];
        $sms_condition[] = ['type'=>0];
        $sms_condition[] = ['code'=>$code];
        $sms_condition[] = ['>=', 'time', time()-10*60];
        if (!(new SmsCode())->getSmsCodeInfo($sms_condition)){
            $this->jsonRet->jsonOutput(-3, '验证码错误或已过期');
        }
        $member_data['member_id'] = $member_info['member_id'];
        $member_data['member_mobile']  = $mobile;
        $pwd ? $member_data['member_password'] = MD5($pwd) : '';
        $result = (new Member())->updateMember($member_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '修改成功');
        }
        $this->jsonRet->jsonOutput(-1, '修改失败');
    }

    /**
     * 关于游购会
     */
    public function actionAbouUs($type = 1)
    {
        $info = (new ProtocolService())->getOneProtocol($type, 'title as article_title,content as article_content');
        if ($info) {
            $this->jsonRet->jsonOutput(0, '加载成功',$info);
        }
        $this->jsonRet->jsonOutput(-1, '加载成功');
    }

    /**
     * 帮助中心
     */
    public function actionHelpCenter(){
        $helt_list = (new Article())->getArticleList(['a.article_type_id'=>25],'','','a.article_sort desc','article_title,article_content');
        if ($helt_list) {
            $this->jsonRet->jsonOutput(0, '加载成功',$helt_list);
        }
        $this->jsonRet->jsonOutput(-1, '加载成功');
    }

    /**
     * 公告详情
     */
    public function actionGgDetail($article_id)
    {
        $info = (new Article())->getArticleInfo(['article_id' => $article_id]);
        $info['article_time'] = date('Y-m-d', $info['article_time']);
        if ($info) {
            $this->jsonRet->jsonOutput(0, '加载成功', $info);
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
        if (strlen($name)>10){
            $this->jsonRet->jsonOutput(-2, '姓名文字过长');
        }
        if (strlen($content1)>255){
            $this->jsonRet->jsonOutput(-2, '产品建议文字过长');
        }
        if (strlen($content2)>255){
            $this->jsonRet->jsonOutput(-2, '运输建议文字过长');
        }
        if (strlen($content3)>255){
            $this->jsonRet->jsonOutput(-2, '平台建议文字过长');
        }
        $member_info = $this->user_info;
        $feedback_data['member_id'] = $member_info['member_id'] ?: 0;
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
    public function actionKefuCenter(){
        $list = (new Setting())->getKefuCenter();
        $info = array_combine(array_column($list,'name'),array_column($list,'value'));
        $info['store_qcode'] = SysHelper::getImage($info['store_qcode'], 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '操作成功',$info);
    }

    /**
     * 最近搜索|热门搜索
     */
    public function actionSearchStr(){
        $member_info = $this->user_info;
        if ($member_info) {
            $s_list = (new MemberSearch())->getMemberSearchList(['member_id' => $member_info['member_id']], '', '5', 'create_time desc', 'str');
            $list['search'] = array_column($s_list, 'str');
        } else {
            $list['search'] = [];
        }
        $host_info = (new Setting())->getSetting('index_search_tip');
        $list['host_search'] = explode('|',$host_info);
        $this->jsonRet->jsonOutput(0, '操作成功',$list);
    }

    /**
     * 删除搜索记录
     */
    public function actionDelSearch()
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $result = (new MemberSearch())->deleteMemberSearchByCondition(['member_id' => $member_info['member_id']]);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**我的收藏
     * @param int $page
     */
    public function actionMyCollect($page=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $collect_list = (new MemberFollow())->getMemberFollowList(['member_id' => $member_info['member_id'], 'fav_type' => 'goods'], $offset, $pageSize, 'fav_time desc', 'fav_id,log_id');
        foreach ($collect_list as $k => $v){
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id'=>$v['fav_id']],'A.goods_id,A.goods_price,A.goods_pic,A.goods_name,A.default_guige_id');
            $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
            $goods_info['log_id'] = $v['log_id'];
            //商品是否促销 暂不用
            //$goods_info['pbg_price'] = $this->activity($goods_info['goods_id'])?$this->activity($goods_info['goods_id'])[$goods_info['default_guige_id']]:null;
            //$goods_info['state'] = $this->activity($goods_info['goods_id'])?1:0; //商品是否促销
            $collect_list[$k]= $goods_info;
        }
        $totalCount = (new MemberFollow())->getMemberFollowCount(['member_id'=>$member_info['member_id'],'fav_type'=>'goods']);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $collect_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '操作成功',$show);
    }

    /**添加收藏
     * @param $goods_id 商品id
     */
    public function actionAddCollect($goods_id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $info = (new MemberFollow())->getMemberFollowInfo(['member_id' => $member_info['member_id'], 'fav_id' => $goods_id, 'store_id' => 1]);
        if ($info) {
            $this->jsonRet->jsonOutput(-2, '您已收藏该商品');
        }
        $collect_data['member_id'] = $member_info['member_id'];
        $collect_data['member_name'] = $member_info['member_name'];
        $collect_data['fav_id'] = $goods_id;
        $collect_data['fav_time'] = time();
        $collect_data['store_id'] = 1;
        $collect_data['store_name'] = 1;
        $result = (new MemberFollow())->insertMemberFollow($collect_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '收藏成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '收藏失败');
        }
    }

    /**删除收藏
     * @param $log_ids 收藏id拼接 如 ： 1,5,8
     */
    public function actionDelCollect($log_ids){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $log_id_arr = explode(',',$log_ids);
        $member_id = $member_info['member_id'];
        $condition = ['and'];
        $condition[] = ['member_id'=>$member_id];
        $condition[] = ['in', 'log_id', $log_id_arr];

        $result = (new MemberFollow())->deleteMemberFollowByCondition($condition);
        if ($result){
            $this->jsonRet->jsonOutput(0, '操作成功');
        }else{
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**
     * 我的分销
     */
    public function actionMyDistribution(){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (!$member_info['member_type']) {
            $this->jsonRet->jsonOutput(-1, '您还不是分销商');
        }
        $info = (new Institutions())->getInstitutionsInfo(['id' => $member_info['institutions_id']]);
        $time = strtotime(date('Y-m'));
        $data['img'] = SysHelper::getImage($member_info['member_avatar'], 0, 0, 0, [0, 0], 1);
        $data['member_name'] = $member_info['member_name'];
        $data['qr_code'] = 'YGH' . $member_info['member_id'];
        $predeposit = (new MemberPredeposit())->getMemberChangeList(['member_id'=>$member_info['member_id'],'predeposit_type'=>'brokerage'],'','','',"SUM(predeposit_av_amount) as all_predeposit ");
        $predeposit1 = (new MemberPredeposit())->getMemberChangeList("member_id={$member_info['member_id']} and predeposit_type = 'brokerage' and predeposit_add_time>=$time",'','','',"SUM(predeposit_av_amount) as all_predeposit ");
        $data['all_predeposit'] = $predeposit[0]['all_predeposit']?:0; //累计收益
        $data['now_predeposit'] = $predeposit1[0]['all_predeposit']?:0; //本月收益
        $data['available_predeposit'] = $member_info['available_predeposit']?:0; //可提现收益
        $data['lxs'] = !empty($info['name']) ? $info['name'] : ''; //可提现收益
        $this->jsonRet->jsonOutput(0, '操作成功',$data);
    }

    /**
     * 分销二维码
     */
    public function actionMyQrCode(){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
//        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
//        $info['qr_code_url'] = $http_type . $_SERVER['SERVER_NAME'] . "/yghsc/api/web/index.php/v1/login/wx-register?tjm=YGH" . $member_info['member_id'];
        $info['qr_code'] = 'YGH' . $member_info['member_id'];
//        $info['qr_code1'] = $member_info['ticket'];
        $info['qrcodeimg'] = SysHelper::getImage($member_info['qrcodeimg'], 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '操作成功',$info);
    }

    /**
     * 我的粉丝
     */
    public function actionMyFans($page = 1)
    {
        $pageSize = 5;
        $offset = ($page - 1) * $pageSize;
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (!$member_info['member_type']) {
            $this->jsonRet->jsonOutput(-1, '您还不是分销商');
        }
        $fans_count = $member_info['fs_num']; //总粉丝数
        $time = time()-7*24*3600;
        $fans_count1 = (new Member())->getMemberCount("member_recommended_no = 'YGH{$member_info['member_id']}' and member_time>=$time");
        $predeposit = (new MemberPredeposit())->getMemberChangeList(['member_id'=>$member_info['member_id'],'predeposit_type'=>'brokerage'],'','','',"SUM(predeposit_av_amount) as all_predeposit ");
        $order_list = (new MemberPredeposit())->getMemberChangeList(['predeposit_type' => 'brokerage', 'member_id' => $member_info['member_id']], $offset, $pageSize, 'predeposit_add_time desc');
        $list = [];
        foreach ($order_list as $k => $v){
            $info = (new Order())->getOrderInfo(['order_id'=>$v['order_id']],['member']);
            $list[$k]['order_sn'] = $info['order_sn'];
            $list[$k]['order_amount'] = $info['order_amount'];
            $list[$k]['commission_amount'] = $info['commission_amount'];
            $list[$k]['member_info']['name'] = $info['extend_member']['member_name'];
            $list[$k]['member_info']['member_avatar'] = SysHelper::getImage($info['extend_member']['member_avatar'],0,0,0,[0,0],1);
            $list[$k]['member_info']['member_time'] = date('Y-m-d',$info['extend_member']['member_time']);
        }
        $totalCount = (new MemberPredeposit())->getMemberChangeCount(['predeposit_type' => 'brokerage', 'member_id' => $member_info['member_id']]);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $data['all_predeposit'] = $predeposit[0]['all_predeposit'] ?: 0; //累计收益
        $data['all_fans_count'] = $fans_count; //总粉丝数
        $data['fans_count'] = $fans_count1; //近七天粉丝数
        $data['order_list'] = $list; //最近5条佣金收益
        $data['pages'] = $page_arr;
        $this->jsonRet->jsonOutput(0, '操作成功',$data);
    }

    /**
     * 我的奖励
     */
    public function actionMyReward($page = 1)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (!$member_info['member_type']) {
            $this->jsonRet->jsonOutput(-1, '您还不是分销商');
        }
        $list = (new ApplyMoney())->getApplyMoneyList(['and', ['member_id' => $member_info['member_id']], ['in', 'state', [0, 3]]], '', '1');
        $return_info = !empty($list[0]) ? $list[0] : [];
        if (count($return_info)) {
            $tx_state = 1;
        } else {
            $tx_state = 0;
        }

        $pageSize = 3;
        $offset = ($page - 1) * $pageSize;
        $time = strtotime(date('Y-m'));
        $predeposit = (new MemberPredeposit())->getMemberChangeList(['member_id' => $member_info['member_id'], 'predeposit_type' => 'brokerage'], '', '', '', "SUM(predeposit_av_amount) as all_predeposit ");
        $predeposit1 = (new MemberPredeposit())->getMemberChangeList(['and', ['member_id' => $member_info['member_id']], ['predeposit_type' => 'brokerage'], ['>=', 'predeposit_add_time', $time]], '', '', '', "SUM(predeposit_av_amount) as all_predeposit ");
        $apply_money_list = Yii::$app->db->createCommand("select FROM_UNIXTIME(create_time,\"%Y-%m\") as month from qbt_apply_money where member_id={$member_info['member_id']}  group by month order by month desc limit $offset,$pageSize")->queryAll();//
        foreach ($apply_money_list as $k => $v) {
            $list = (new ApplyMoney())->getApplyMoneyList(['and', ['member_id' => $member_info['member_id']], ['>=', 'create_time', strtotime($v['month'])]], '', '', '', "create_time,balance,money,content,state");
            foreach ($list as $k1 => &$v1) {
                $v1['create_time'] = date('Y-m-d H:i:s', $v1['create_time']);
            }
            $apply_money_list[$k]['list'] = $list;
        }
        $totalCount = (new ApplyMoney())->getApplyMoneyCount(['state' => 1, 'member_id' => $member_info['member_id']]);
        $data['money'] = $member_info['available_predeposit']; //余额
        $data['available_predeposit'] = $member_info['available_predeposit']; //可提现余额
        $data['all_predeposit'] = $predeposit[0]['all_predeposit'] ?: 0; //累计收益
        $data['month_predeposit'] = $predeposit1[0]['all_predeposit'] ?: 0; //本月收益
        $data['apply_money_list'] = $apply_money_list; //提现记录
        $data['tx_state'] = $tx_state; //是否有审核中申请

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $data, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '操作成功', $show);
    }

    /**粉丝列表
     * @param int $page
     */
    public function actionFs($page = 1)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $pageSize = 20;
        $offset = ($page - 1) * $pageSize;
        $totalCount = (new Member())->getMemberCount(['member_recommended_no' => 'YGH' . $member_id]);
        $list = (new Member())->getMemberList(['member_recommended_no' => 'YGH' . $member_id], $offset, $pageSize, 'fs_time desc', 'member_avatar,member_name,fs_time');
        foreach ($list as $k => $v) {
            $list[$k]['member_avatar'] = SysHelper::getImage($v['member_avatar'], 0, 0, 0, [0, 0], 1);
            $list[$k]['fs_time'] = $v['fs_time'] ? date('Y-m-d', $v['fs_time']) : '';
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**奖励明细-全部清单
     * @param int $page 页码
     */
    public function actionRewardList($page = 1)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (!$member_info['member_type']) {
            $this->jsonRet->jsonOutput(-1, '您还不是分销商');
        }
        $pageSize = 12;
        $offset = $page * $pageSize;

        $member_years = date('Y', $member_info['member_time']);
        $new_years = date('Y');
        $totalCount = ($new_years - $member_years + 1) * 12;
        for ($i = $offset - 12; $i <= $offset; $i++) {
            //已付款订单
            $time1 = strtotime("-" . ($i + 1) . "month");
            $time2 = strtotime("-$i month");

            $all = (new Order())->getOrderList(['and', ['fxs_id' => $member_info['member_id']], ['>', 'payment_time', 0], ['type' => 1], ['>=', 'add_time', $time1], ['<=', 'add_time', $time2]], '', '', '', 'sum(commission_amount) as num'); //所有佣金
//            $issettlement = (new Order())->getOrderList(['and', ['>=', 'order_state', 60], ['type' => 1], ['in', 'buyer_id', $member_id_arr], ['>', 'payment_time', 0], ['>=', 'add_time', $time1], ['<=', 'add_time', $time2]], '', '', '', 'sum(commission_amount) as num'); //已完成订单
            $issettlement = (new MemberPredeposit())->getMemberChangeList(['and', ['predeposit_type' => 'brokerage'], ['member_id' => $member_info['member_id']], ['>=', 'predeposit_add_time', $time1], ['<=', 'predeposit_add_time', $time2]], '', '', '', 'sum(predeposit_av_amount) as num'); //已结算
            $data['all'] = $all[0]['num'] ? round($all[0]['num'], 2) : 0;
            $data['issettlement'] = $issettlement[0]['num'] ? round($issettlement[0]['num'], 2) : 0;
            $data['nosettlement'] = ($data['all'] * 100 - $data['issettlement'] * 100) / 100;
            $data['year'] = date('Y', strtotime("-$i month"));
            $data['month'] = date('m', strtotime("-$i month"));
            $list[] = $data;
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**奖励明细-未结算清单、已结算清单
     * @param int $page 页码
     */
    public function actionIsNoRewardList($type = 1, $page = 1, $start_time = '', $end_time = '')
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (!$member_info['member_type']) {
            $this->jsonRet->jsonOutput(-1, '您还不是分销商');
        }
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $condition = ['and'];
        $condition[] = ['fxs_id' => $member_info['member_id']];
        $condition[] = ['>', 'payment_time', 0];
        $condition[] = ['type' => 1];
        if ($start_time) {
            $condition[] = ['>=', 'add_time', strtotime($start_time)]; //开始时间
        }

        if ($start_time) {
            $condition[] = ['<=', 'add_time', strtotime($end_time)]; //结束时间
        }

        $issettlement_list = (new MemberPredeposit())->getMemberChangeList(['and', ['predeposit_type' => 'brokerage'], 'member_id' => $member_info['member_id']]); //已结算
        $arr = $issettlement_list ? array_column($issettlement_list, 'order_id') : [];

        if ($type == 1) {
            $condition[] = ['in', 'order_id', $arr]; //已结算
        }
        if ($type == 2) {
            //未结算 已付款
            $condition[] = ['not in', 'order_id', $arr];
        }

        $totalCount = (new Order())->getOrderCount($condition);
        $list = (new Order())->getOrderList($condition, $offset, $pageSize, 'add_time desc');
        $arr = ['10' => '已取消', '20' => '待支付', '30' => '待发货', '40' => '已发护', '50' => '已收货', '60' => '已完成'];
        $data = [];
        foreach ($list as $k => $v) {
            $order_info = (new Member())->getMemberInfo(['member_id' => $v['buyer_id']], 'member_name,member_mobile');
            $data[$k]['order_time'] = date('Y-m-d', $v['add_time']);
            $data[$k]['order_sn'] = $v['order_sn'];
            $data[$k]['order_amount'] = $v['order_amount'];
            $data[$k]['commission_amount'] = $v['commission_amount'];
            $data[$k]['order_state'] = $arr[$v['order_state']];
            $data[$k]['member_name'] = $order_info['member_name'] ?: $order_info['member_mobile'];
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $data, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**
     * 限时秒杀
     */
    public function actionSecondsKill($type=1,$page=1){
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        //当天限时抢购
        $time = time();
        if ($type == 1) {
            //当前抢购
            $map = ['and', 'pb_state=2', "pb_start_time<=$time", "pb_end_time>$time"];
        }
        if ($type == 2) {
            //下一场
            $map = ['and', 'pb_state=2', "pb_start_time>$time"];
        }
        $xsms = (new PanicBuy())->getPanicBuyList($map, '', '', '', 'pb_id,pb_end_time,pb_start_time');
        $array = array_column($xsms, 'pb_id');
        if (!empty($xsms[0])) {
            $arr = [];
            $start_time = $xsms[0]['pb_start_time'];
            $end_time = $xsms[0]['pb_end_time'];
            if ($start_time > $time) {
                $state = 2;//活动即将开始
                $time = $this->timediff1($start_time - $time);//距离开始时间
            } else {
                $state = 1;//活动进行中
                $time = $this->timediff1($end_time - $time); //距离结束时间
            }
            $array = (new PanicBuyGoods())->getPanicBuyGoodsList(['and', ['in', 'pbg_pb_id', $array], ['goods_state' => Yii::$app->params['GOODS_STATE_PASS']]], $offset, '', '', 'goods_id,goods_name,goods_stock,goods_price,goods_pic,pbg_stock,pbg_shop');
            foreach ($array as $k => $v) {
                //限时抢购首页显示优惠价
                $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['goods_id']]);
                $goods_price = (new Goods())->getGoodsDiscountAll($goods_info);

                $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $goods_info['default_guige_id']]);

                //多规格
                $pbg_price = $goods_price['discount_price'][$goods_info['default_guige_id']] ?: $guige_info['guige_price'];
                if ($v['pbg_stock'] == $v['pbg_shop']) {
                    $pbg_price = $guige_info['guige_price'];
                }
                $array[$k]['pbg_price'] = $pbg_price;
                $array[$k]['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
            }
            $arr = array_merge($arr, $array);
            $panic_buy['state'] = $state;
            $panic_buy['time'] = $time;
            $panic_buy['goods_list'] = $arr;

            $totalCount = (new PanicBuyGoods())->getPanicBuyGoodsCount(['pbg_pb_id' => $xsms[0]['pb_id'], 'goods_state' => Yii::$app->params['GOODS_STATE_PASS']]);
            $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        } else {
            $this->jsonRet->jsonOutput(-1, '暂无数据');
        }
        $show = ['list' => $panic_buy, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '操作成功',$show);
    }

    /**领取优惠券
     * @param $coupon_id
     */
    public function actionGetCoupon($coupon_id){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $coupon_info = (new Coupon())->getCouponInfo(['and', "coupon_id=$coupon_id", "coupon_end_time > $now_time", "coupon_cc_id != 10"]);
        if (!$coupon_info){
            $this->jsonRet->jsonOutput(-2, '优惠券已过期或不存');
        }
        if ($coupon_info['coupon_grant_num'] == $coupon_info['coupon_num']){
            $this->jsonRet->jsonOutput(-3, '优惠券已领完');
        }
        $member_coupon_count = (new MemberCoupon())->getMemberCouponCount(['mc_member_id'=>$member_info['member_id'],'mc_coupon_id'=>$coupon_id]);
        if ($member_coupon_count == $coupon_info['coupon_limit']){
            $this->jsonRet->jsonOutput(-4, '该优惠券领取次数已超限');
        }
        $coupon_data['mc_member_id'] = $member_info['member_id'];
        $coupon_data['mc_member_username'] = $member_info['member_name']; //领取用户昵称
        $coupon_data['mc_coupon_id'] = $coupon_id;
        $coupon_data['mc_receive_time'] = time();
        $coupon_data['coupon_grant_num'] = $coupon_info['coupon_grant_num'] + 1;
        $coupon_data['mc_start_time'] = $coupon_info['coupon_start_time'];
        $coupon_data['mc_end_time'] = $coupon_info['coupon_end_time'];
        $coupon_data['mc_quota'] = $coupon_info['coupon_quota'];
        $coupon_data['mc_min_price'] = $coupon_info['coupon_min_price'];
        $result = (new MemberCoupon())->insertMemberCoupon($coupon_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '领取成功');
        }
        $this->jsonRet->jsonOutput(-1, '领取失败');
    }

    /**
     * 购物车
     */
    public function actionShopCar(){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $shop_car_list = (new ShoppingCar())->getShoppingCarList(['S.buyer_id' => $member_info['member_id'], 'G.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], '', '', 'cart_id desc', 'S.cart_id,GG.guige_price as goods_price,S.goods_num,S.goods_id,G.goods_pic as goods_image,S.goods_spec_id,G.goods_name');
        foreach ($shop_car_list as $k => &$v) {
            $v['goods_image'] = SysHelper::getImage($v['goods_image'], 0, 0, 0, [0, 0], 1);
            $v['goods_price'] = floatval($v['goods_price']);
        }
        $this->jsonRet->jsonOutput(0, '加载成功',$shop_car_list);
    }

    /**添加商品到购物车
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $num 购买数量
     */
    public function actionAddCar($goods_id, $guige_id = '', $num = 1)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if (!$guige_id) {
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
            $guige_id = $goods_info['default_guige_id'];
        }
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够，请重新添加！');
        }
        $car_info = (new ShoppingCar())->getShoppingCarInfo(['S.buyer_id'=>$member_info['member_id'],'S.goods_id'=>$goods_id,'S.goods_spec_id'=>$guige_id],'cart_id,goods_num');
        if ($car_info){
            //该商品的该规格已存在购物车
            $car_data['goods_num'] = $num+$car_info['goods_num'];
            $car_data['cart_id'] = $car_info['cart_id'];
            $result = (new ShoppingCar())->updateShoppingCar($car_data);
            if ($result) {
                $this->jsonRet->jsonOutput(0, '添加成功');
            }else{
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
        } else {
            $this->jsonRet->jsonOutput(-1, '添加失败');
        }
    }

    /**修改购物车商品数量
     * @param $cart_ids 购物车id拼接
     */
    public function actionAddCarNum($cart_id, $num)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if ($num <= 0) {
            $this->jsonRet->jsonOutput(-2, '数量不得小于1');
        }
        if ($num >= 1000) {
            $this->jsonRet->jsonOutput(-2, '数量不得大于1000');
        }
        $result = (new ShoppingCar())->updateShoppingCar(['cart_id' => $cart_id, 'goods_num' => $num]);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**删除购物车商品
     * @param $cart_ids 购物车id拼接
     */
    public function actionDelCar($cart_ids)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $cart_id_arr = explode(',', $cart_ids);
        $car_where = ['and'];
        $car_where[] = ['in', 'cart_id', $cart_id_arr];
        $car_where[] = ['buyer_id' => $member_info['member_id']];
        $result = (new ShoppingCar())->deleteShoppingCar($car_where);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '删除成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '删除失败');
        }
    }

    /**
     * 清空购物车
     */
    public function actionEmptyCar()
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $result = (new ShoppingCar())->deleteShoppingCar(['buyer_id' => $member_info['member_id']]);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功');
        } else {
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**购物车结算页面
     * @param $cart_ids 购物车id拼接
     */
    public function actionCarToPrepare1($cart_ids, $ma_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $cart_id_arr = explode(',',$cart_ids);
        $car_where =  ['and'];
        $car_where[] =  ['in','cart_id',$cart_id_arr];
        $car_list = (new ShoppingCar())->getShoppingCarList($car_where,'','','','G.goods_name,G.goods_id,S.cart_id,S.goods_num,S.goods_spec_id,G.goods_name,GG.guige_name,GG.guige_price'); //所选购物车记录
        if (!$car_list) {
            $this->jsonRet->jsonOutput(-1, '参数错误');
        }

        $yh_money = 0;
        $goods_amount = 0;
        $goods_amount2 = 0;
        $now_time = time();
        foreach ($car_list as $key => $value){
            $guige_id = $value['goods_spec_id']; //规格
            $num = $value['goods_num']; //数量
            $goods_id = $value['goods_id']; //商品
            $goods_id_arr[] = $goods_id;
            $member_info = $this->user_info;
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
            if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
                $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
            }
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格
            if ($guige_info['guige_num']-$num<0){
                $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
            }
            $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info, $num); //商品促销价格
            $state = 0;
            if ($goods_discount_res['state'] != 0){
                //正在参加促销活动，使用促销价
                $state = 1;
                $discount_price = $goods_discount_res['discount_price'][$guige_id];
                if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                    $goods_amount1 = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                    $goodsamount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goods_amount1;
                } else {
                    $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $num;
                }
                $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //抢购活动id
            }else{
                //未参加促销活动，使用该规格相应的价格
                $goodsamount = $guige_info['guige_price'] * $num;
            }
            $goods_amount += $goodsamount;
            $goods_amount2 += $guige_info['guige_price'] * $num;

            $yh_money += $guige_info['guige_price'] * $num - $goodsamount;


            //营销活动 满减
            $num_goodsamount = ['num' => $num, 'goodsamount' => $goodsamount];
            $narr[$goods_id] = $num_goodsamount;
            //营销活动 满减

            //商品信息
            list($goods[$key]['goods_id'], $goods[$key]['goods_name'], $goods[$key]['goods_pic'], $goods[$key]['price'], $goods[$key]['discount_price'], $goods[$key]['num'], $goods[$key]['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), floatval($guige_info['guige_price']), $discount_price, $num, $state];
        }

        //营销活动 满减
        $full_down = $this->funll_down($narr);

        //获取可用优惠券
        $member_coupon_where = ['and'];
        $member_coupon_where[] = ['A.mc_member_id' => $member_info['member_id']];
        $member_coupon_where[] = ['A.mc_state' => 1];
        $member_coupon_where[] = ['A.mc_use_time' => 0];
        $member_coupon_where[] = ['<=', 'A.mc_start_time', $now_time];
        $member_coupon_where[] = ['>=', 'A.mc_end_time', $now_time];
//        $member_coupon_where[] = ['<=', 'A.mc_min_price', ($goods_amount - $full_down)];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,A.mc_min_price,A.mc_start_time,A.mc_end_time,B.coupon_title,B.coupon_img,B.type,B.goods_class_id,B.goods_id,B.coupon_content');
        foreach ($member_coupon_list as $k1 => $v1) {
            if ($v1['type'] == 1) {
                if ($v1['mc_min_price'] > $goods_amount - $full_down) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
            if ($v1['type'] == 2) {
                $class_id_arr = explode(',', $v1['goods_class_id']);
                $list = (new Goods())->getGoodsList(['and', ['in', 'A.goods_class_id', $class_id_arr]]);
                $goodsidarr = array_column($list, 'goods_id');
                $goods_id_com = array_intersect($goods_id_arr, $goodsidarr);
                if (!count($goods_id_com)) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
                $allamount = 0;
                foreach ($goods_id_com as $k2 => $v2) {
                    $allamount += $narr[$v2]['goodsamount'];
                }
                if ($v1['mc_min_price'] > $allamount) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
            if ($v1['type'] == 3) {
                $goods_id_arr1 = explode(',', $v1['goods_id']);
                $goods_id_com = array_intersect($goods_id_arr, $goods_id_arr1);
                if (!count($goods_id_com)) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
                $allamount = 0;
                foreach ($goods_id_com as $k2 => $v2) {
                    $allamount += $narr[$v2]['goodsamount'];
                }
                if ($v1['mc_min_price'] > $allamount) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
        }
        foreach ($member_coupon_list as $k => &$v) {
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1);
            $v['coupon_quota'] = floatval($v['coupon_quota']);
            $v['coupon_min_price'] = floatval($v['coupon_min_price']);
            $v['coupon_start_time'] = date('Y-m-d', $v['mc_start_time']);
            $v['coupon_end_time'] = date('Y-m-d', $v['mc_end_time']);
        }
        //获取可用优惠券

        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
//        if ($addr_info) {
//            $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']); //省市区
//        }
        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $addr_info['ma_area'] . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址

        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $goods_amount2; //商品总价
        $prepare_data['full_down'] = floatval(round($full_down + $yh_money, 2)); //优惠价格
        $prepare_data['full_down_state'] = $full_down ? 1 : 0;
        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['shipping_fee'] = (int)$shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount2 - $full_down - $yh_money); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**购物车结算页面 2019-10-08 备份
     * @param $cart_ids 购物车id拼接
     */
    public function actionCarToPrepare($cart_ids, $ma_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $cart_id_arr = explode(',', $cart_ids);
        $car_where = ['and'];
        $car_where[] = ['in', 'cart_id', $cart_id_arr];
        $car_list = (new ShoppingCar())->getShoppingCarList($car_where, '', '', '', 'G.goods_name,G.goods_id,S.cart_id,S.goods_num,S.goods_spec_id,G.goods_name,GG.guige_name,GG.guige_price'); //所选购物车记录
        if (!$car_list) {
            $this->jsonRet->jsonOutput(-1, '参数错误');
        }

        $yh_money = 0;
        $goods_amount = 0;
        $goods_amount2 = 0;
        $now_time = time();
        foreach ($car_list as $key => $value) {
            $guige_id = $value['goods_spec_id']; //规格
            $num = $value['goods_num']; //数量
            $goods_id = $value['goods_id']; //商品
            $goods_id_arr[] = $goods_id;
            $member_info = $this->user_info;
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
            if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
                $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
            }
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
            if ($guige_info['guige_num'] - $num < 0) {
                $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
            }
            $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info, $num); //商品促销价格
            $state = 0;
            if ($goods_discount_res['state'] != 0) {
                //正在参加促销活动，使用促销价
                $state = 1;
                $discount_price = $goods_discount_res['discount_price'][$guige_id];
                if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                    $goods_amount1 = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                    $goodsamount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goods_amount1;
                } else {
                    $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $num;
                }
                $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //抢购活动id
            } else {
                //未参加促销活动，使用该规格相应的价格
                $goodsamount = $guige_info['guige_price'] * $num;
            }
            $goods_amount += $goodsamount;
            $goods_amount2 += $guige_info['guige_price'] * $num;

            $yh_money += $guige_info['guige_price'] * $num - $goodsamount;


            //营销活动 满减
            $num_goodsamount = ['num' => $num, 'goodsamount' => $goodsamount];
            $narr[$goods_id] = $num_goodsamount;
            //营销活动 满减

            //商品信息
            list($goods[$key]['goods_id'], $goods[$key]['goods_name'], $goods[$key]['goods_pic'], $goods[$key]['price'], $goods[$key]['discount_price'], $goods[$key]['num'], $goods[$key]['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), floatval($guige_info['guige_price']), $discount_price, $num, $state];
        }

        //营销活动 满减
        $full_down = $this->funll_down($narr);

        //获取可用优惠券
        $member_coupon_where =  ['and'];
        $member_coupon_where[] =  ['A.mc_member_id'=>$member_info['member_id']];
        $member_coupon_where[] =  ['A.mc_state'=>1];
        $member_coupon_where[] =  ['A.mc_use_time'=>0];
        $member_coupon_where[] =  ['<=','A.mc_start_time',$now_time];
        $member_coupon_where[] =  ['>=','A.mc_end_time',$now_time];
        $member_coupon_where[] = ['<=', 'A.mc_min_price', ($goods_amount - $full_down)];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,A.mc_min_price,A.mc_start_time,A.mc_end_time,B.coupon_title,B.coupon_img,B.type,B.goods_class_id,B.goods_id,B.coupon_content');
        foreach ($member_coupon_list as $k1 => $v1) {
            if ($v1['type'] == 2) {
                $class_id_arr = explode(',', $v1['goods_class_id']);
                $list = (new Goods())->getGoodsList(['and', ['in', 'A.goods_class_id', $class_id_arr]]);
                $goodsidarr = array_column($list, 'goods_id');
                if (count(array_diff($goods_id_arr, $goodsidarr))) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
            if ($v1['type'] == 3) {
                $goods_id_arr1 = explode(',', $v1['goods_id']);
                $result = array_diff($goods_id_arr, $goods_id_arr1);
                if (!count($result)) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
        }
        foreach ($member_coupon_list as $k=>&$v){
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'],0,0,0,[0,0],1);
            $v['coupon_quota'] = floatval($v['coupon_quota']);
            $v['coupon_min_price'] = floatval($v['coupon_min_price']);
            $v['coupon_start_time'] = date('Y-m-d', $v['mc_start_time']);
            $v['coupon_end_time'] = date('Y-m-d', $v['mc_end_time']);
        }
        //获取可用优惠券

        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $addr_info['ma_area'] . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址

        $shipping_fee = (new Setting())->getSettingInfo(['name'=>'shipping_fee']); //运费
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $goods_amount2; //商品总价
        $prepare_data['full_down'] = floatval(round($full_down + $yh_money, 2)); //优惠价格
        $prepare_data['full_down_state'] = $full_down ? 1 : 0;
        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['shipping_fee'] = (int)$shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount2 - $full_down - $yh_money); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功',$prepare_data);
    }

    /**满减
     * @param $arr
     * @return float|int|mixed
     */
    private function funll_down($arr)
    {
        $goods_id_arr = array_keys($arr);
        $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['in', 'fdg_goods_id', $goods_id_arr]);
        $fdg_fd_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_fd_id') : [];
        $full_list = (new FullDown())->getFullDownList(['and', ['in', 'fd_id', $fdg_fd_id_arr], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
        $full_down_money = 0;
        foreach ($full_list as $k => $v) {
            if ($v['type'] == 1) {
                $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_fd_id' => $v['fd_id']]);
                $fdg_goods_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_goods_id') : [];
                $goodsamount = 0;
                foreach ($fdg_goods_id_arr as $k1 => $v1) {
                    $goodsamount += $arr[$v1]['goodsamount'];
                }
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $v['fd_id']], ['<=', 'fdr_quata', $goodsamount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $full_down_money += !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] : 0;
            } else {
                $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_fd_id' => $v['fd_id']]);
                $fdg_goods_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_goods_id') : [];
                $num = 0;
                $goodsamount = 0;
                foreach ($fdg_goods_id_arr as $k1 => $v1) {
                    $num += $arr[$v1]['num'];
                    $goodsamount += $arr[$v1]['goodsamount'];
                }
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $v['fd_id']], ['<=', 'fdr_quata', $num]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $zkj = !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] * $goodsamount / 10 : $goodsamount; //折扣价
//                $zkj = ceil(($zkj) * 100) / 100;
                $full_down_money += $goodsamount - $zkj;
            }
        }
        return $full_down_money;
    }

    /**满减
     * @param $arr
     * @return float|int|mixed
     */
    private function funll_down1($arr)
    {
        $goods_id_arr = array_keys($arr);
        $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['in', 'fdg_goods_id', $goods_id_arr]);
        $fdg_fd_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_fd_id') : [];
        $full_list = (new FullDown())->getFullDownList(['and', ['in', 'fd_id', $fdg_fd_id_arr], ['fd_state' => 1], ['>=', 'fd_end_time', time()], ['<=', 'fd_start_time', time()]]);
        $full_down_money = 0;
        foreach ($full_list as $k => $v) {
            if ($v['type'] == 1) {
                $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_fd_id' => $v['fd_id']]);
                $fdg_goods_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_goods_id') : [];
                $goodsamount = 0;
                $goodsids = [];
                foreach ($fdg_goods_id_arr as $k1 => $v1) {
                    if ($arr[$v1]['goodsamount']) {
                        $goodsamount += $arr[$v1]['goodsamount'];
                        $goodsids[] = $v1;
                    }
                }
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $v['fd_id']], ['<=', 'fdr_quata', $goodsamount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $full_down_money += !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] : 0;
                if (!empty($yxhd_rule[0]['fdr_deduct'])) {
                    if (count($goodsids) > 1) {
                        //多商品优惠
                        foreach ($goodsids as $k2 => $v2) {
                            $order_rec_id = $arr[$v2]['order_rec_id'];
                            $yh_money = round($yxhd_rule[0]['fdr_deduct'] * $arr[$v2]['goodsamount'] / $goodsamount, 2);
                            $order_goods_pay_price = $arr[$v2]['goodsamount'] - $yh_money;
                            (new OrderGoods())->updateOrderGoods(['order_rec_id' => $order_rec_id, 'order_goods_pay_price' => $order_goods_pay_price]);
                        }
                    } else {
                        //单商品优惠
                        $order_rec_id = $arr[$goodsids[0]]['order_rec_id'];
                        $order_goods_pay_price = $goodsamount - $yxhd_rule[0]['fdr_deduct'];
                        (new OrderGoods())->updateOrderGoods(['order_rec_id' => $order_rec_id, 'order_goods_pay_price' => $order_goods_pay_price]);
                    }
                }
            } else {
                $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_fd_id' => $v['fd_id']]);
                $fdg_goods_id_arr = $full_goods_list ? array_column($full_goods_list, 'fdg_goods_id') : [];
                $num = 0;
                $goodsamount = 0;
                $goodsids = [];
                foreach ($fdg_goods_id_arr as $k1 => $v1) {
                    if ($arr[$v1]['num']) {
                        $num += $arr[$v1]['num'];
                        $goodsamount += $arr[$v1]['goodsamount'];
                        $goodsids[] = $v1;
                    }
                }
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $v['fd_id']], ['<=', 'fdr_quata', $num]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $zkj = !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] * $goodsamount / 10 : $goodsamount; //折扣价
//                $zkj = ceil(($zkj) * 100) / 100;
                $full_down_money += $goodsamount - $zkj;
                if (!empty($yxhd_rule[0]['fdr_deduct'])) {
                    if (count($goodsids) > 1) {
                        //多商品优惠
                        foreach ($goodsids as $k2 => $v2) {
                            $order_rec_id = $arr[$v2]['order_rec_id'];
                            $order_goods_pay_price = $arr[$v2]['goodsamount'] * $yxhd_rule[0]['fdr_deduct'] / 10;
                            (new OrderGoods())->updateOrderGoods(['order_rec_id' => $order_rec_id, 'order_goods_pay_price' => $order_goods_pay_price]);
                        }
                    } else {
                        //单商品优惠
                        $order_rec_id = $arr[$goodsids[0]]['order_rec_id'];
                        $order_goods_pay_price = $zkj;
                        (new OrderGoods())->updateOrderGoods(['order_rec_id' => $order_rec_id, 'order_goods_pay_price' => $order_goods_pay_price]);
                    }
                }
            }
        }
        return $full_down_money;
    }

    /**直接购买商品结算页面
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $num 该规格数量
     */
    public function actionPrepareOrder($goods_id, $guige_id, $num = 1, $ma_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $member_info = $this->user_info; //用户信息
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格
        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id'=>$goods_id]); //商品

        //地址
        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
        if ($addr_info) {
            $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']); //省市区
        }

        $shipping_fee = (new Setting())->getSettingInfo(['name'=>'shipping_fee']); //运费
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格

        $discount_price = 0;
        $state = 0;
        if ($goods_discount_res['state'] != 0){
            $discount_price =  $goods_discount_res['discount_price'][$guige_id];
            $state = 1; //商品有促销活动
            //正在参加促销活动，使用促销价
            if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                $goods_amount1 = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                $goods_amount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goods_amount1;
            } else {
                $goods_amount = $goods_discount_res['discount_price'][$guige_id] * $num;
            }
        }else{
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price']*$num;
        }
        $yh_money = $guige_info['guige_price'] * $num - $goods_amount;

        //营销活动 满减
        $full_down = 0;
        $full_down_state = 0;
        //商品是否参加满减
        $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_goods_id'=>$goods_id]);
        $fdg_fd_id_arr = array_column($full_goods_list,'fdg_fd_id');
        $full_info = (new FullDown())->getFullDownInfo(['and',['in','fd_id',$fdg_fd_id_arr],['fd_state'=>1],['>=','fd_end_time',time()],['<=','fd_start_time',time()]]);
        if ($full_info){
            $full_down_state = 1;
            if ($full_info['type'] == 1) {
                //满减规则 满额减
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['<=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $full_down = !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] : 0;
            }
            if ($full_info['type'] == 2) {
                //满减规则 满件折扣
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['<=', 'fdr_quata', $num]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $zkj = !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] * $goods_amount / 10 : $goods_amount; //折扣价
                $zkj = floor(($zkj) * 100) / 100;
                $full_down = ($goods_amount * 100 - $zkj * 100) / 100;
            }
        }
        //营销活动 满减

        //获取可用优惠券 为满减之后
        $member_coupon_where =  ['and'];
        $member_coupon_where[] =  ['A.mc_member_id'=>$member_info['member_id']];
        $member_coupon_where[] =  ['A.mc_state'=>1];
        $member_coupon_where[] =  ['A.mc_use_time'=>0];
        $member_coupon_where[] =  ['<=','A.mc_start_time',$now_time];
        $member_coupon_where[] =  ['>=','A.mc_end_time',$now_time];
        $member_coupon_where[] = ['<=', 'A.mc_min_price', $goods_amount - $full_down];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,A.mc_end_time,A.mc_start_time,A.mc_min_price,B.coupon_title,B.coupon_img,B.goods_class_id,B.coupon_id,B.coupon_content');
        foreach ($member_coupon_list as $k=>&$v){
            if (!$this->checkCoupon($goods_id, $v['coupon_id'])) {
                //对商品不可用
                unset($member_coupon_list[$k]);
            }

            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'],0,0,0,[0,0],1);
            $v['coupon_start_time'] = date('Y-m-d', $v['mc_start_time']);
            $v['coupon_end_time'] = date('Y-m-d', $v['mc_end_time']);
            $v['coupon_quota'] = floatval($v['coupon_quota']);
            $v['coupon_min_price'] = floatval($v['coupon_min_price']);
        }
        //获取可用优惠券

        //商品信息
        list($goods[0]['goods_id'], $goods[0]['goods_name'], $goods[0]['goods_pic'], $goods[0]['price'], $goods[0]['discount_price'], $goods[0]['num'], $goods[0]['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), floatval($guige_info['guige_price']), $discount_price, $num, $state];
        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $member_address . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = floatval($guige_info['guige_price'] * $num); //商品总价
        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['full_down_state'] = $full_down_state; //是否参加满减活动
        $prepare_data['full_down'] = floatval($full_down + $yh_money); //商品满减金额
        $prepare_data['shipping_fee'] = (int)$shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($guige_info['guige_price'] * $num - $full_down - $yh_money); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功',$prepare_data);
    }

    /**活动营销直接购买商品结算页面 暂不用 2019-05-27
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $num 该规格数量
     */
    public function actionPreparePullDownOrder($fd_id,$goods_id,$guige_id,$num=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $member_info = $this->user_info; //用户信息
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格
        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id'=>$goods_id]); //商品
        $default_addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default'=>1,'ma_member_id'=>$member_info['member_id']],''); //默认地址
        if ($default_addr_info){
//            $member_address = (new Area())->lowGetHigh($default_addr_info['ma_area_id']); //省市区
            $member_address = $default_addr_info['ma_area']; //省市区
        }else{
            $member_address='';
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name'=>'shipping_fee']); //运费
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
        $discount_price = 0;
        $state = 0;
        if ($goods_discount_res['state'] != 0){
            $discount_price =  $goods_discount_res['discount_price'][$guige_id];
            $state = 1; //商品有促销活动
            //正在参加促销活动，使用促销价
            $goods_amount = $goods_discount_res['discount_price'][$guige_id]*$num;
        }else{
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price']*$num;
        }

        //满减
        $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and',['fdr_fd_id'=>$fd_id],['>=','fdr_quata',$goods_amount]],'','','fdr_quata DESC','fdr_deduct');
        $full_down = $yxhd_rule[0]['fdr_deduct'];

        //获取可用优惠券
        $member_coupon_where =  ['and'];
        $member_coupon_where[] =  ['A.mc_member_id'=>$member_info['member_id']];
        $member_coupon_where[] =  ['A.mc_state'=>1];
        $member_coupon_where[] =  ['A.mc_use_time'=>0];
        $member_coupon_where[] =  ['<=','A.mc_start_time',$now_time];
        $member_coupon_where[] =  ['>=','A.mc_end_time',$now_time];
        $member_coupon_where[] = ['<=', 'A.mc_min_price', $goods_amount];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,A.mc_min_price,B.coupon_title,B.coupon_img');
        foreach ($member_coupon_list as $k=>&$v){
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'],0,0,0,[0,0],1);
        }
        //获取可用优惠券

        //商品信息
        list($goods['goods_id'],$goods['goods_name'],$goods['goods_pic'],$goods['price'],$goods['discount_price'],$goods['num'],$goods['state']) = [$goods_info['goods_id'],$goods_info['goods_name'],SysHelper::getImage($goods_info['goods_pic'],0,0,0,[0,0],1),$guige_info['guige_price'],$discount_price,$num,$state];
        //地址信息
        list($addr['name'],$addr['mobile'],$addr['ma_id'],$addr['info']) = [$default_addr_info['ma_true_name'],$default_addr_info['ma_mobile'],$default_addr_info['ma_id'],$member_address.$default_addr_info['ma_area_info']];

        $prepare_data['addr'] = $default_addr_info?$addr:[]; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = $goods_amount; //商品总价
        $prepare_data['coupon_list'] = $member_coupon_list; //可用优惠券列表
        $prepare_data['full_down'] = $full_down; //商品满减金额
        $prepare_data['shipping_fee'] = $shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功',$prepare_data);
    }

    /**添加订单-直接下单 未经过购物车
     * @param $goods_id 商品id
     * @param $guige_id 规格id
     * @param $ma_id 地址id
     * @param string $buyer_message 买家留言
     * @param int $mc_id 优惠券id
     * @param int $num 购买数量
     */
    public function actionAddOrder($goods_id,$guige_id,$ma_id,$buyer_message='',$mc_id=0,$num=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
            $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
        }
        if ($member_info['member_type']) {
            $fxs_id = $member_info['member_id'];
        } else {
            $fxs_id = $member_info['member_recommended_no'] ? substr($member_info['member_recommended_no'], 3) : 0;
        }
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格

        $num = $num ?: 1;

        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够，请重新下单！');
        }

        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id'=>$ma_id,'ma_member_id'=>$member_id]); //地址
        if(!$addr_info){
            $this->jsonRet->jsonOutput(-3, '地址不存在');
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name'=>'shipping_fee']); //运费
//        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格

        if ($goods_discount_res['state'] != 0){
            //正在参加促销活动，使用促销价
            $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //限时抢购或促销id
            $order_goods_date['goods_type'] = $goods_info['goods_promotion_type']; //限时抢购id
            //如果为抢购商品
            if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                $goods_amount1 = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                $goods_amount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goods_amount1;
            } else {
                $goods_amount = $goods_discount_res['discount_price'][$guige_id] * $num;
            }
        }else{
            //未参加促销活动，使用该规格相应的价格
            $goods_amount = $guige_info['guige_price'] * $num;
        }
        //优惠价格
        $yh_money = $guige_info['guige_price'] * $num - $goods_amount;

        //商品是否参加满减
        $full_down = 0;
        $full_goods_list = (new FullDownGoods())->getFullDownGoodsList(['fdg_goods_id'=>$goods_id]);
        $fdg_fd_id_arr = $full_goods_list?array_column($full_goods_list,'fdg_fd_id'):[];
        $full_info = (new FullDown())->getFullDownInfo(['and',['in','fd_id',$fdg_fd_id_arr],['fd_state'=>1],['>=','fd_end_time',time()],['<=','fd_start_time',time()]]);
        if ($full_info){
            if ($full_info['type'] == 1) {
                //满减规则 满额减
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['<=', 'fdr_quata', $goods_amount]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $full_down = !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] : 0;
            }
            if ($full_info['type'] == 2) {
                //满减规则 满件折扣
                $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $full_info['fd_id']], ['<=', 'fdr_quata', $num]], '', '', 'fdr_quata DESC', 'fdr_deduct');
                $zkj = !empty($yxhd_rule[0]['fdr_deduct']) ? $yxhd_rule[0]['fdr_deduct'] * $goods_amount / 10 : $goods_amount; //折扣价
                $zkj = floor(($zkj) * 100) / 100;
                $full_down = $goods_amount - $zkj;
            }
        }
        //营销活动 满减

        $order_coupon_price = 0;
        if ($mc_id){
            //如果使用了优惠券
            $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id'=>$mc_id,'mc_state'=>1,'mc_use_time'=>0]);
            $coupon_info = (new Coupon())->getCouponInfo(['coupon_id'=>$member_coupon_info['mc_coupon_id'],'coupon_state'=>1]);
            if ($member_coupon_info['mc_start_time'] > $now_time || $member_coupon_info['mc_end_time'] < $now_time || $coupon_info['coupon_min_price'] > $goods_amount) {
                $this->jsonRet->jsonOutput(-3, '无效优惠券！');
            }
            $order_coupon_price = $coupon_info['coupon_quota'];
        }
        $transaction = Yii::$app->db->beginTransaction();


        try {
            //添加订单
//            $order_date['buyer_address'] = $member_address.$addr_info['ma_area_info']; //收货地址
            $order_date['buyer_address'] = $addr_info['ma_area'] . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
//            $order_date['point_amount'] = ceil($goods_amount * $num); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH'.date('YmdHis',time()).rand(100,999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $guige_info['guige_price'] * $num; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['regionId'] = $addr_info['regionId'];
            $order_date['ordertype'] = 1; //
            $order_date['order_mc_id'] = $mc_id?:''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price; //优惠券金额
            $order_date['promotion_total'] = $order_coupon_price + $full_down + $yh_money; //优惠金额 优惠券金额+满减金额
            $order_date['order_amount'] = $guige_info['guige_price'] * $num + $shipping_fee['value'] - $order_coupon_price - $full_down - $yh_money; //订单总价格 商品总价格+运费-优惠价格-满减
            $order_date['commission_amount'] = $order_date['order_amount'] * $goods_info['commission'] / 100; //佣金 商品价格*佣金百分比
            $order_date['fxs_id'] = $fxs_id; //订单佣金所属分销商
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            //添加订单

            //添加订单商品
            $order_goods_date['order_id'] = $order_id; //订单id
            $order_goods_date['order_goods_id'] = $goods_id; //商品id
            $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
            $order_goods_date['order_goods_price'] = $guige_info['guige_price']; //商品价格 单价
            $order_goods_date['order_goods_pay_price'] = $goods_amount - $full_down;
            $order_goods_date['order_goods_num'] = $num; //商品数量
            $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
            $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
            $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
            $order_goods_date['order_buyer_id'] = $member_id; //买家id
            if ($full_down){
                //如果参加满减
                $order_goods_date['goods_type'] = 4; //订单类型 营销活动
                $order_goods_date['promotions_id'] = $full_info['fd_id']; //营销活动id
            }
            (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
            //添加订单商品


            //如果为抢购商品
            if ($goods_discount_res['state'] != 0 && $goods_info['goods_promotion_type'] == 1) {
                $panic_buy_goods_info = (new PanicBuyGoods())->getPanicBuyGoodsInfo(['pbg_pb_id' => $goods_info['goods_promotion_id'], 'pbg_goods_id' => $goods_id]); //抢购商品详情
                $pbg_shop = $goods_discount_res['num'] < $num ? $panic_buy_goods_info['pbg_stock'] : $panic_buy_goods_info['pbg_shop'] + $num;
                (new PanicBuyGoods())->updatePanicBuyGoods(['pbg_id' => $panic_buy_goods_info['pbg_id'], 'pbg_shop' => $pbg_shop]); //抢购商品数量
            }

            (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - $num]); //减少商品库存

            //减少该规格下商品数量
            (new GuiGe())->updateGuiGe(['guige_num'=>$guige_info['guige_num']-$num,'guige_id'=>$guige_id,]);

            //标记优惠券为已使用
            if ($mc_id){
                $member_coupon_date['mc_member_id'] = $member_id;
                $member_coupon_date['mc_use_time'] = time();
                (new MemberCoupon())->updateMemberCoupon($member_coupon_date,['mc_id'=>$mc_id]);
            }
            //标记优惠券为已使用

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功',['order_id'=>$order_id]);
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
    public function actionAddPullDownOrder($fd_id,$goods_id,$guige_id,$ma_id,$buyer_message='',$mc_id=0,$num=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
            $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
        }
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格
        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-2, '该规格商品库存不够，请重新下单！');
        }
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id'=>$ma_id]); //地址
        $shipping_fee = (new Setting())->getSettingInfo(['name'=>'shipping_fee']); //运费
        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
        if ($goods_discount_res['state'] != 0){
            //正在参加促销活动，使用促销价
            $goods_price = $goods_discount_res['discount_price'][$guige_id];

            $order_goods_date['goods_type'] = $goods_info['goods_promotion_type']; //订单商品类型
            $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //拼团活动id
        }else{
            //未参加促销活动，使用该规格相应的价格
            $goods_price = $guige_info['guige_price'];
        }
        $goods_amount = $goods_price * $num;
        $order_coupon_price = 0;
        if ($mc_id){
            //如果使用了优惠券
            $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id'=>$mc_id,'mc_state'=>1,'mc_use_time'=>0]);
            $coupon_info = (new Coupon())->getCouponInfo(['coupon_id'=>$member_coupon_info['mc_coupon_id'],'coupon_state'=>1]);
            if ($coupon_info['coupon_start_time']>$now_time || $coupon_info['coupon_end_time']<$now_time || $coupon_info['coupon_end_time']>$goods_amount){
                $this->jsonRet->jsonOutput(-3, '无效优惠券！');
            }
            $order_coupon_price = $coupon_info['coupon_quota'];
        }

        //满减
        $yxhd_rule = (new FullDownRule())->getFullDownRuleList(['and', ['fdr_fd_id' => $fd_id]], '', '', 'fdr_quata DESC', 'fdr_deduct');
        $full_down = $yxhd_rule[0]['fdr_deduct']; //满减金额

        $transaction = Yii::$app->db->beginTransaction();
        try {
            //添加订单
            $order_date['buyer_address'] = $member_address.$addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
//            $order_date['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH'.date('YmdHis',time()).rand(100,999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['commission_amount'] = $goods_amount*$goods_info['commission']/100; //佣金 商品价格*佣金百分比
            $order_date['order_mc_id'] = $mc_id?:''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price+$full_down; //优惠金额 优惠券金额+满减金额
            $order_date['order_amount'] = $goods_amount+$shipping_fee['value']-$order_coupon_price-$full_down; //订单总价格 商品总价格+运费-优惠价格-满减
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            //添加订单

            //添加订单商品
            $order_goods_date['order_id'] = $order_id; //订单id
            $order_goods_date['order_goods_id'] = $goods_id; //商品id
            $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
            $order_goods_date['order_goods_price'] = $goods_price; //商品价格
            $order_goods_date['order_goods_num'] = $num; //商品数量
            $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
            $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
            $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
            $order_goods_date['goods_type'] = 4; //订单类型 营销活动
            $order_goods_date['promotions_id'] = $fd_id; //营销活动id
            $order_goods_date['order_buyer_id'] = $member_id; //买家id
            (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
            //添加订单商品

            //减少该规格下商品数量
            (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - $num]); //减少商品库存
            (new GuiGe())->updateGuiGe(['guige_num'=>$guige_info['guige_num']-$num,'guige_id'=>$guige_id,]); //减少该规格下商品数量

            //标记优惠券为已使用
            if ($mc_id){
                $member_coupon_date['mc_member_id'] = $member_id;
                $member_coupon_date['mc_use_time'] = time();
                (new MemberCoupon())->updateMemberCoupon($member_coupon_date,['mc_id'=>$mc_id]);
            }
            //标记优惠券为已使用

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功',['order_id'=>$order_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    /**添加订单-购物车下单 经过购物车
     * @param $cart_ids
     * @param $ma_id
     * @param string $buyer_message
     * @param int $mc_id
     */
    public function actionCarToOrder1($cart_ids, $ma_id, $buyer_message = '', $mc_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $member_id = $member_info['member_id'];
        if ($member_info['member_type']) {
            $fxs_id = $member_info['member_id'];
        } else {
            $fxs_id = $member_info['member_recommended_no'] ? substr($member_info['member_recommended_no'], 3) : 0;
        }
        $cart_id_arr = explode(',',$cart_ids);
        $car_where =  ['and'];
        $car_where[] =  ['in','cart_id',$cart_id_arr];
        $car_where[] =  ['buyer_id' => $member_id];
        $car_list = (new ShoppingCar())->getShoppingCarList($car_where,'','','','G.goods_name,G.goods_id,S.cart_id,S.goods_num,S.goods_spec_id,G.goods_name,GG.guige_name,GG.guige_price'); //所选购物车记录
        $goods_amount = 0; //商品总额
        $full_down = 0; //优惠金额
        $commission_amount = 0; //佣金金额
        $yh_money = 0;
        $goods_amount1 = 0;
        $order_no = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号

        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        if ($addr_info) {
            $member_address = $addr_info['ma_area']; //省市区
        } else {
            $member_address = '';
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费

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

                    if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                        $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                        $goodsamount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goodsamount;
                    } else {
                        $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $num;
                    }

                    $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //抢购活动id
                } else {
                    //未参加促销活动，使用该规格相应的价格
                    $goodsamount = $guige_info['guige_price'] * $num;
                }
                $goods_amount += $goodsamount;

                $yh_money += $guige_info['guige_price'] * $num - $goodsamount;
                $goods_amount1 += $guige_info['guige_price'] * $num;


                $order_goods_date['order_goods_id'] = $goods_id; //商品id
                $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
                $order_goods_date['order_goods_price'] = $guige_info['guige_price']; //商品价格
                $order_goods_date['order_goods_num'] = $num; //商品数量
                $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
                $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
                $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
                $order_goods_date['order_buyer_id'] = $member_id; //买家id
                $order_rec_id = (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
                $order_goods_id_arr[] = $order_rec_id;

                //营销活动 满减
                $num_goodsamount = ['num' => $num, 'goodsamount' => $goodsamount, 'order_rec_id' => $order_rec_id];
                $narr[$goods_id] = $num_goodsamount;
                //营销活动 满减


                //如果为抢购商品
                if ($goods_discount_res['state'] != 0 && $goods_info['goods_promotion_type'] == 1) {
                    $panic_buy_goods_info = (new PanicBuyGoods())->getPanicBuyGoodsInfo(['pbg_pb_id' => $goods_info['goods_promotion_id'], 'pbg_goods_id' => $goods_id]); //抢购商品详情
                    $pbg_shop = $goods_discount_res['num'] < $num ? $panic_buy_goods_info['pbg_stock'] : $panic_buy_goods_info['pbg_shop'] + $num;
                    (new PanicBuyGoods())->updatePanicBuyGoods(['pbg_id' => $panic_buy_goods_info['pbg_id'], 'pbg_shop' => $pbg_shop]); //抢购商品数量
                }

                (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - $num]); //减少商品库存
                (new GuiGe())->updateGuiGe(['guige_num' => $guige_info['guige_num'] - $num, 'guige_id' => $guige_id,]); //减少该规格下商品数量

//                $commission_amount += $goodsamount * $goods_info['commission'] / 100;


            }

            //营销活动 满减
            $full_down = $this->funll_down1($narr);

            $goods_amount = ceil(($goods_amount) * 100) / 100;
            $order_coupon_price = 0;
            if ($mc_id) {
                //如果使用了优惠券
                $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $mc_id, 'mc_state' => 1, 'mc_use_time' => 0]);
                $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
                if ($member_coupon_info['mc_start_time'] > $now_time || $member_coupon_info['mc_end_time'] < $now_time || $coupon_info['coupon_min_price'] > $goods_amount - $full_down) {
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
            $order_date['regionId'] = $addr_info['regionId'];
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
//            $order_date['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = $order_no; //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount1; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['commission_amount'] = $commission_amount; //佣金 商品价格*佣金百分比
            $order_date['order_mc_id'] = $mc_id ?: ''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price; //优惠券金额
            $order_date['promotion_total'] = $order_coupon_price + $full_down + $yh_money; //优惠金额 优惠券金额+满减金额
            $order_date['order_amount'] = $goods_amount1 + $shipping_fee['value'] - $order_coupon_price - $full_down - $yh_money; //订单总价格 商品总价格+运费-优惠价格
//            $order_date['commission_amount'] = $order_date['order_amount'] * $goods_info['commission'] / 100; //佣金 订单价格*佣金百分比
            $order_date['fxs_id'] = $fxs_id; //订单佣金所属分销商
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

    /**添加订单-购物车下单 经过购物车 2019-10-08备份
     * @param $cart_ids
     * @param $ma_id
     * @param string $buyer_message
     * @param int $mc_id
     */
    public function actionCarToOrder($cart_ids, $ma_id, $buyer_message = '', $mc_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $member_id = $member_info['member_id'];
        if ($member_info['member_type']) {
            $fxs_id = $member_info['member_id'];
        } else {
            $fxs_id = $member_info['member_recommended_no'] ? substr($member_info['member_recommended_no'], 3) : 0;
        }
        $cart_id_arr = explode(',', $cart_ids);
        $car_where = ['and'];
        $car_where[] = ['in', 'cart_id', $cart_id_arr];
        $car_where[] = ['buyer_id' => $member_id];
        $car_list = (new ShoppingCar())->getShoppingCarList($car_where, '', '', '', 'G.goods_name,G.goods_id,S.cart_id,S.goods_num,S.goods_spec_id,G.goods_name,GG.guige_name,GG.guige_price'); //所选购物车记录
        $goods_amount = 0; //商品总额
        $full_down = 0; //优惠金额
        $commission_amount = 0; //佣金金额
        $yh_money = 0;
        $goods_amount1 = 0;
        $order_no = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号

        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        if ($addr_info) {
            $member_address = $addr_info['ma_area']; //省市区
        } else {
            $member_address = '';
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($car_list as $key => $value){
                $guige_id = $value['goods_spec_id']; //规格
                $num = $value['goods_num']; //数量
                $goods_id = $value['goods_id']; //商品
                $goods_info = (new Goods())->getGoodsInfo(['A.goods_id'=>$goods_id]); //商品
                $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格
                if ($guige_info['guige_num']-$num<0){
                    $this->jsonRet->jsonOutput(-2, $guige_info['goods_name'].'商品的'.$guige_info['guige_name'].'规格库存不够，请重新下单！');
                }
                $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
                if ($goods_discount_res['state'] != 0){
                    //正在参加促销活动，使用促销价

                    if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                        $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                        $goodsamount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goodsamount;
                    } else {
                        $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $num;
                    }

                    $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //抢购活动id
                }else{
                    //未参加促销活动，使用该规格相应的价格
                    $goodsamount = $guige_info['guige_price'] * $num;
                }
                $goods_amount += $goodsamount;

                $yh_money += $guige_info['guige_price'] * $num - $goodsamount;
                $goods_amount1 += $guige_info['guige_price'] * $num;


                $order_goods_date['order_goods_id'] = $goods_id; //商品id
                $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
                $order_goods_date['order_goods_price'] = $guige_info['guige_price']; //商品价格
                $order_goods_date['order_goods_num'] = $num; //商品数量
                $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
                $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
                $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
                $order_goods_date['order_buyer_id'] = $member_id; //买家id
                $order_rec_id = (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
                $order_goods_id_arr[] = $order_rec_id;

                //营销活动 满减
                $num_goodsamount = ['num' => $num, 'goodsamount' => $goodsamount, 'order_rec_id' => $order_rec_id];
                $narr[$goods_id] = $num_goodsamount;
                //营销活动 满减


                //如果为抢购商品
                if ($goods_discount_res['state'] != 0 && $goods_info['goods_promotion_type'] == 1) {
                    $panic_buy_goods_info = (new PanicBuyGoods())->getPanicBuyGoodsInfo(['pbg_pb_id' => $goods_info['goods_promotion_id'], 'pbg_goods_id' => $goods_id]); //抢购商品详情
                    $pbg_shop = $goods_discount_res['num'] < $num ? $panic_buy_goods_info['pbg_stock'] : $panic_buy_goods_info['pbg_shop'] + $num;
                    (new PanicBuyGoods())->updatePanicBuyGoods(['pbg_id' => $panic_buy_goods_info['pbg_id'], 'pbg_shop' => $pbg_shop]); //抢购商品数量
                }

                (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - $num]); //减少商品库存
                (new GuiGe())->updateGuiGe(['guige_num'=>$guige_info['guige_num']-$num,'guige_id'=>$guige_id,]); //减少该规格下商品数量

//                $commission_amount += $goodsamount * $goods_info['commission'] / 100;


            }

            //营销活动 满减
            $full_down = $this->funll_down1($narr);

            $goods_amount = ceil(($goods_amount) * 100) / 100;
            $order_coupon_price = 0;
            if ($mc_id){
                //如果使用了优惠券
                $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id'=>$mc_id,'mc_state'=>1,'mc_use_time'=>0]);
                $coupon_info = (new Coupon())->getCouponInfo(['coupon_id'=>$member_coupon_info['mc_coupon_id'],'coupon_state'=>1]);
                if ($member_coupon_info['mc_start_time'] > $now_time || $member_coupon_info['mc_end_time'] < $now_time || $coupon_info['coupon_min_price'] > $goods_amount - $full_down) {
                    $this->jsonRet->jsonOutput(-3, '无效优惠券！');
                }
                $order_coupon_price = $coupon_info['coupon_quota']; //优惠金额

                //标记优惠券为已使用
                $member_coupon_date['mc_member_id'] = $member_id;
                $member_coupon_date['mc_use_time'] = time();
                (new MemberCoupon())->updateMemberCoupon($member_coupon_date,['mc_id'=>$mc_id]);
                //标记优惠券为已使用
            }
            $order_date['buyer_address'] = $member_address.$addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['regionId'] = $addr_info['regionId'];
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
//            $order_date['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = $order_no; //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount1; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['commission_amount'] = $commission_amount; //佣金 商品价格*佣金百分比
            $order_date['order_mc_id'] = $mc_id?:''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price; //优惠券金额
            $order_date['promotion_total'] = $order_coupon_price + $full_down + $yh_money; //优惠金额 优惠券金额+满减金额
            $order_date['order_amount'] = $goods_amount1 + $shipping_fee['value'] - $order_coupon_price - $full_down - $yh_money; //订单总价格 商品总价格+运费-优惠价格
//            $order_date['commission_amount'] = $order_date['order_amount'] * $goods_info['commission'] / 100; //佣金 订单价格*佣金百分比
            $order_date['fxs_id'] = $fxs_id; //订单佣金所属分销商
            $order_id = (new Order())->insertOrder($order_date); //添加订单

            $order_goods_up_date['order_id'] = $order_id; //订单id
            (new OrderGoods())->updateOrderGoodsByCondition($order_goods_up_date,['in','order_rec_id',$order_goods_id_arr]); //更新订单商品 添加订单id
            (new ShoppingCar())->deleteShoppingCar(['in','cart_id',$cart_id_arr]);
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功',['order_id'=>$order_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    /**再次购买结算页面
     * @param $order_id 订单号
     */
    public function actionTwoBuy($order_id, $ma_id = '')
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
        $goods_amount = 0;
        $yh_money = 0;
        $goods_amount2 = 0;
        $full_down = 0;
        foreach ($order_info['extend_order_goods'] as $key => $value) {
            $guige_id = $value['order_goods_spec_id']; //规格
            $num = $value['order_goods_num']; //数量
            $goods_id = $value['order_goods_id']; //商品
            $goods_id_arr[] = $goods_id;
            $member_info = $this->user_info;
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
            if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
                $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
            }
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
            if ($guige_info['guige_num'] - $num < 0) {
                $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
            }
            $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
            $discount_price = 0;
            $state = 0;
            if ($goods_discount_res['state'] != 0) {
                $discount_price = $goods_discount_res['discount_price'][$guige_id];
                $state = 1; //商品有促销活动
                //正在参加促销活动，使用促销价

                if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                    $goods_amount1 = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                    $goodsamount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goods_amount1;
                } else {
                    $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $num;
                }
            } else {
                //未参加促销活动，使用该规格相应的价格
                $goodsamount = $guige_info['guige_price'] * $num;
            }

            $goods_amount += $goodsamount;
            $goods_amount2 += $guige_info['guige_price'] * $num;

            $yh_money += $guige_info['guige_price'] * $num - $goodsamount;

            //营销活动 满减
            $num_goodsamount = ['num' => $num, 'goodsamount' => $goodsamount];
            $narr[$goods_id] = $num_goodsamount;
            //营销活动 满减

            //商品信息
            list($goods[$key]['goods_id'], $goods[$key]['goods_name'], $goods[$key]['goods_pic'], $goods[$key]['price'], $goods[$key]['discount_price'], $goods[$key]['num'], $goods[$key]['state']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), $guige_info['guige_price'], $discount_price, $num, $state];
        }

        //营销活动 满减
        $full_down = $this->funll_down($narr);

        //获取可用优惠券
        $now_time = time();
        $member_coupon_where =  ['and'];
        $member_coupon_where[] =  ['A.mc_member_id'=>$member_info['member_id']];
        $member_coupon_where[] =  ['A.mc_state'=>1];
        $member_coupon_where[] =  ['A.mc_use_time'=>0];
        $member_coupon_where[] =  ['<=','A.mc_start_time',$now_time];
        $member_coupon_where[] =  ['>=','A.mc_end_time',$now_time];
        $member_coupon_where[] = ['<=', 'A.mc_min_price', ($goods_amount - $full_down)];
        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,B.coupon_quota,A.mc_min_price,A.mc_start_time,A.mc_end_time,B.coupon_title,B.coupon_img,B.type,B.goods_class_id,B.goods_id,B.coupon_content');
        foreach ($member_coupon_list as $k1 => $v1) {
            if ($v1['type'] == 2) {
                $class_id_arr = explode(',', $v1['goods_class_id']);
                $list = (new Goods())->getGoodsList(['and', ['in', 'A.goods_class_id', $class_id_arr]]);
                $goodsidarr = array_column($list, 'goods_id');
                if (count(array_diff($goods_id_arr, $goodsidarr))) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
            if ($v1['type'] == 3) {
                $goods_id_arr1 = explode(',', $v1['goods_id']);
                $result = array_diff($goods_id_arr, $goods_id_arr1);
                if (!count($result)) {
                    unset($member_coupon_list[$k1]);
                    continue;
                }
            }
        }
        foreach ($member_coupon_list as $k=>&$v){
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'],0,0,0,[0,0],1);
            $v['coupon_quota'] = floatval($v['coupon_quota']);
            $v['coupon_min_price'] = floatval($v['coupon_min_price']);
            $v['coupon_start_time'] = date('Y-m-d', $v['mc_start_time']);
            $v['coupon_end_time'] = date('Y-m-d', $v['mc_end_time']);
        }
        //获取可用优惠券

        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $addr_info['ma_area'] . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址

        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['coupon_list'] = $member_coupon_list; //优惠券
        $prepare_data['goods_amount'] = floatval($goods_amount2); //商品总价
        $prepare_data['shipping_fee'] = (int)$shipping_fee['value']; //运费
        $prepare_data['point_amount'] = ceil($goods_amount); //赠送积分 为商品总价格向上取整
        $prepare_data['full_down'] = round($full_down + $yh_money, 2); //优惠价格
        $prepare_data['point_amount'] = ceil($goods_amount2 - $full_down - $yh_money); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**再次购买下单
     * @param $cart_ids
     * @param $ma_id
     * @param string $buyer_message
     * @param int $mc_id
     */
    public function actionTwoOrder($order_id, $ma_id = '', $buyer_message = '', $mc_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        if ($member_info['member_type']) {
            $fxs_id = $member_info['member_id'];
        } else {
            $fxs_id = $member_info['member_recommended_no'] ? substr($member_info['member_recommended_no'], 3) : 0;
        }
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
        $now_time = time();
        $goods_amount = 0; //商品总额
        $full_down = 0; //满减金额
        $commission_amount = 0; //佣金金额
        $order_no = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号

        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        if ($addr_info) {
            $member_address = $addr_info['ma_area']; //省市区
        } else {
            $member_address = '';
        }
        $shipping_fee = (new Setting())->getSettingInfo(['name' => 'shipping_fee']); //运费

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $yh_money = 0;
            $goods_amount2 = 0;
            foreach ($order_info['extend_order_goods'] as $key => $value) {
                $guige_id = $value['order_goods_spec_id']; //规格
                $num = $value['order_goods_num']; //数量
                $goods_id = $value['order_goods_id']; //商品
                $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
                if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
                    $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
                }
                $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $guige_id]); //规格
                if ($guige_info['guige_num'] - $num < 0) {
                    $this->jsonRet->jsonOutput(-2, $guige_info['goods_name'] . '商品的' . $guige_info['guige_name'] . '规格库存不够，请重新下单！');
                }
                $goods_discount_res = (new Goods())->getGoodsDiscount($goods_info); //商品促销价格
                if ($goods_discount_res['state'] != 0) {
                    //正在参加促销活动，使用促销价
                    if ($goods_info['goods_promotion_type'] == 1 && $goods_discount_res['num'] < $num) {
                        $goods_amount1 = $goods_discount_res['discount_price'][$guige_id] * $goods_discount_res['num'];
                        $goodsamount = $guige_info['guige_price'] * ($num - $goods_discount_res['num']) + $goods_amount1;
                    } else {
                        $goodsamount = $goods_discount_res['discount_price'][$guige_id] * $num;
                    }
                    $order_goods_date['promotions_id'] = $goods_info['goods_promotion_id']; //抢购活动id
                } else {
                    //未参加促销活动，使用该规格相应的价格
                    $goodsamount = $guige_info['guige_price'] * $num;
                }

                $goods_amount += $goodsamount;
                $goods_amount2 += $guige_info['guige_price'] * $num;

                $yh_money += $guige_info['guige_price'] * $num - $goodsamount;

                $order_goods_date['order_goods_id'] = $goods_id; //商品id
                $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
                $order_goods_date['order_goods_price'] = $guige_info['guige_price']; //商品价格
//                $order_goods_date['order_goods_pay_price'] = $goodsamount - $full_down_money / $num; //商品实际成交价
                $order_goods_date['order_goods_num'] = $num; //商品数量
                $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
                $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
                $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
                $order_goods_date['order_buyer_id'] = $member_id; //买家id
                $order_rec_id = (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品
                $order_goods_id_arr[] = $order_rec_id;

                //营销活动 满减
                $num_goodsamount = ['num' => $num, 'goodsamount' => $goodsamount, 'order_rec_id' => $order_rec_id];
                $narr[$goods_id] = $num_goodsamount;
                //营销活动 满减

                //如果为抢购商品
                if ($goods_discount_res['state'] != 0 && $goods_info['goods_promotion_type'] == 1) {
                    $panic_buy_goods_info = (new PanicBuyGoods())->getPanicBuyGoodsInfo(['pbg_pb_id' => $goods_info['goods_promotion_id'], 'pbg_goods_id' => $goods_id]); //抢购商品详情
                    $pbg_shop = $goods_discount_res['num'] < $num ? $panic_buy_goods_info['pbg_stock'] : $panic_buy_goods_info['pbg_shop'] + $num;
                    (new PanicBuyGoods())->updatePanicBuyGoods(['pbg_id' => $panic_buy_goods_info['pbg_id'], 'pbg_shop' => $pbg_shop]); //抢购商品数量
                }

                (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - $num]); //减少商品库存
                (new GuiGe())->updateGuiGe(['guige_num' => $guige_info['guige_num'] - $num, 'guige_id' => $guige_id,]); //减少该规格下商品数量

                $commission_amount += $goodsamount * $goods_info['commission'] / 100;


            }

            //营销活动 满减
            $full_down = $this->funll_down1($narr);

            $order_coupon_price = 0;
            if ($mc_id) {
                //如果使用了优惠券
                $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $mc_id, 'mc_state' => 1, 'mc_use_time' => 0]);
                $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $member_coupon_info['mc_coupon_id'], 'coupon_state' => 1]);
                if ($coupon_info['coupon_start_time'] > $now_time || $coupon_info['coupon_end_time'] < $now_time || $coupon_info['coupon_min_price'] > $goods_amount - $full_down) {
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
            $order_date['regionId'] = $addr_info['regionId'];
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['order_sn'] = $order_no; //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount2; //商品总价格
            $order_date['shipping_fee'] = $shipping_fee['value']; //运费
            $order_date['order_mc_id'] = $mc_id ?: ''; //优惠券id
            $order_date['order_coupon_price'] = $order_coupon_price+$full_down + $yh_money; //优惠金额
            $order_date['order_amount'] = $goods_amount2 + $shipping_fee['value'] - $order_coupon_price - $full_down - $yh_money; //订单总价格 商品总价格+运费-优惠价格
            $order_date['fxs_id'] = $fxs_id; //订单佣金所属分销商
            $order_date['point_amount'] = ceil($order_date['order_amount']); //赠送积分 为商品总价格向上取整
            $order_id = (new Order())->insertOrder($order_date); //添加订单

            $order_goods_up_date['order_id'] = $order_id; //订单id
            (new OrderGoods())->updateOrderGoodsByCondition($order_goods_up_date, ['in', 'order_rec_id', $order_goods_id_arr]); //更新订单商品 添加订单id
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
    public function actionMyOrder($page=1,$type=0){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];

        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $order_condition = ['and'];
        $order_condition[] = ['buyer_id'=>$member_id];
        $order_condition[] = ['type' => 1];
        if ($type){
            if ($type == -10) {
                //退款
                $order_condition[] = ['refund_state' => 3];
            } elseif ($type == 50) {
                $order_condition[] = ['evaluation_state' => 1, 'type' => 1, 'refund_state' => 1];
                $order_condition[] = ['>=', 'order_state', 50];
            } else {
                $order_condition[] = ['order_state' => $type, 'refund_state' => 1];
            }
        }
        $refund_day = (new Setting())->getSetting('refund_day');
        $cancel_list = (new Order())->getOrderList(['buyer_id' => $member_id, 'type' => 1], '', '', '', '');
        foreach ($cancel_list as $k => $v) {
            if ($v['order_state'] == 20 && time() - 60 * 20 > $v['add_time']) {
                //超过二十分钟
                $this->cancel_order($v['order_id']);
            }
            if ($v['order_state'] == 50 && $v['refund_state'] == 1 && time() - 60 * 60 * 24 * $refund_day > $v['sh_time']) {
                $this->complete_order($v['order_id']);
            }
        }

        $order_list = (new Order())->getOrderList($order_condition, $offset, $pageSize, 'add_time desc', 'order_id,order_sn,add_time,order_state,pd_amount,order_amount,pay_state,pid,refund_state,order_mc_id,ordertype');
        foreach ($order_list as $k => $v){
            $goods_list = (new OrderGoods())->getOrderGoodsList(['order_id'=>$v['order_id']],'','','','');
            $order_goods_num = 0;
            foreach ($goods_list as $k1 => $v1){
                $order_goods_num += $v1['order_goods_num'];
                $goods_list[$k1]['order_goods_image'] = SysHelper::getImage($v1['order_goods_image'],0,0,0,[0,0],1);
                //待支付订单
                if ($v['order_state'] == 20){
                    if ($v['order_mc_id']){
                        //存在优惠券 检查优惠券是否过期
                        $now_time = time();
                        $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $v['order_mc_id'], 'mc_state' => 1]);
                        $coupon_info = (new Coupon())->getCouponInfo(['coupon_id'=>$member_coupon_info['mc_coupon_id'],'coupon_state'=>1]);
                        if ($coupon_info['coupon_start_time']>$now_time || $coupon_info['coupon_end_time']<$now_time){
                            $this->cancel_order($v['order_id']);
                            $order_list[$k]['order_state'] = 10; //订单已取消
                        }
                    }
                    if ($v1['goods_type']==4){
                        //满减活动是否过期
                        $full_info = (new FullDown())->getFullDownInfo(['and',['fd_id'=>$v1['promotions_id']],['fd_state'=>1],['>=','fd_end_time',time()],['<=','fd_start_time',time()]]);
                        if (!$full_info){
                            $this->cancel_order($v['order_id']);
                            $order_list[$k]['order_state'] = 10; //订单已取消
                        }
                    }
                    if ($v1['order_goods_dp_id']){
                        //限时抢购活动是否过期
                        $pb_info = (new PanicBuy())->getPanicBuyInfo(['pb_id' => $v1['order_goods_dp_id']]);
                        if ($pb_info['pb_end_time'] <= time() || $pb_info['pb_state'] != 2) {
                            $this->cancel_order($v['order_id']);
                            $order_list[$k]['order_state'] = 10; //订单已取消
                        }
                    }
                }
            }
            $order_list[$k]['refund_address'] = '';
            if ($v['refund_state'] == 3) {
                $order_return_info = (new OrderReturn())->getOrderReturnInfo(['order_id' => $v['order_id']]);
                if (in_array($order_return_info['refund_state'], [1, 2])) {
                    $order_list[$k]['order_state'] = 2; //退货-平台审核中
                } elseif ($order_return_info['refund_state'] == 3) {
                    $order_list[$k]['order_state'] = 3; //退货-待发货
                    $info = (new OrderReturn())->getOrderReturnInfo(['order_id' => $v['order_id']], 'refund_address,contact_phone,contact_person');
                    $order_list[$k]['refund_address'] = empty($info) ? '' : '收货人：' . $info['contact_person'] . '，电话：' . $info['contact_phone'] . '，地址：' . $info['refund_address'];
                } elseif ($order_return_info['refund_state'] == 4) {
                    $order_list[$k]['order_state'] = 4; //退货-平台拒绝
                    $order_list[$k]['msg'] = $order_return_info['seller_message'];
                } elseif ($order_return_info['refund_state'] == 5) {
                    $order_list[$k]['order_state'] = 5; //退货-买家已发货
                } elseif ($order_return_info['refund_state'] == 6) {
                    $order_list[$k]['order_state'] = 6; //退货-商家已收货
                } elseif ($order_return_info['refund_state'] == 7) {
                    $order_list[$k]['order_state'] = 7; //退货-商家退款中
                } elseif ($order_return_info['refund_state'] == 8) {
                    $order_list[$k]['order_state'] = 8; //退货完成
                }
            }
            $order_list[$k]['order_state'] = $type == 50 ? 50 : $order_list[$k]['order_state'];
            $order_list[$k]['goods_list'] = $goods_list;
            $order_list[$k]['add_time'] = date('Y-m-d',$v['add_time']);
            $order_list[$k]['order_goods_num'] = $order_goods_num;
            $order_list[$k]['pd_amount'] = floatval($v['pd_amount']);
            $order_list[$k]['order_amount'] = floatval($v['order_amount']);
        }


        $order['num1'] = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'order_state' => 20, 'type' => 1, 'refund_state' => 1]); //待付款订单数
        $order['num2'] = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'order_state' => 30, 'type' => 1, 'refund_state' => 1]); //待发货订单数
        $order['num3'] = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'order_state' => 40, 'type' => 1, 'refund_state' => 1]); //待收货订单数
        $order['num4'] = (new Order())->getOrderCount(['and', ['>=', 'order_state', 50], ['buyer_id' => $member_info['member_id']], ['evaluation_state' => 1], ['type' => 1], ['refund_state' => 1]]); //待评价订单数
        $n = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'refund_state' => 3, 'type' => 1]); //退货售后订单数
        $n1 = (new OrderReturn())->getOrderReturnCount(['buyer_id' => $member_info['member_id'], 'refund_state' => 8]);
        $order['num5'] = $n - $n1;

        $totalCount = (new Order())->getOrderCount($order_condition);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $order_list, 'pages' => $page_arr, 'order_num' => $order];
        $this->jsonRet->jsonOutput(0, '加载成功！',$show);
    }

    //买家退货
    public function actionMjth($order_id, $gs, $no)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $info = (new OrderReturn())->getOrderReturnInfo(['order_id' => $order_id]);
        if (!$info) {
            $this->jsonRet->jsonOutput(-1, '该售后订单不存在！');
        }
        $result = (new OrderReturn())->updateOrderReturn1(['refund_id' => $info['refund_id'], 'return_sn' => $no, 'return_ems' => $gs, 'return_date' => time(), 'refund_state' => 5]);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '操作成功！');
        } else {
            $this->jsonRet->jsonOutput(-2, '操作失败！');
        }
    }

    /**取消超时订单
     * @param $oeder
     */
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
        $this->send_member_msg('订单超时取消', '您的订单已超时，系统已为你取消');
    }

    /**
     * 加载供应商省市区
     */
    public function actionGetGysArea()
    {
        $arr = json_decode(file_get_contents('gys.txt'), true);
        $this->jsonRet->jsonOutput(0, '加载成功', $arr);

//        $list1 = (new AreaGys())->getAreaGysList(['regionGrade'=>1],'','','','regionName,id');
//        foreach ($list1 as $k1=>$v1){
//            $list2 = (new AreaGys())->getAreaGysList(['regionGrade'=>2,'pid'=>$v1['id']],'','','','regionName,id');
//            foreach ($list2 as $k2=>$v2){
//                $list3 = (new AreaGys())->getAreaGysList(['regionGrade'=>3,'pid'=>$v2['id']],'','','','regionName,id');
//                $list2[$k2]['c'] = $list3;
//            }
//            $list1[$k1]['c'] = $list2;
//        }
//        file_put_contents('gys.txt',json_encode($list1));
    }

    /**完成订单
     * @param $order_id
     * @throws \yii\base\Exception
     */
    private function complete_order($order_id)
    {
        $model_order = new Order();
        $order_info = $model_order->getOrderInfo(['order_id' => $order_id], ['order_goods']);

        //已收货 超过可退款时间
        $data = array('order_id' => $order_info['order_id'], 'order_state' => 60);
        (new Order())->updateOrder($data);
    }

    /**订单详情
     * @param $order_id 订单ID
     */
    public function actionOrderDetail($order_id){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }

        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id],['order_goods']);
        $info['order_id'] = $order_info['order_id']; //订单id
        $info['order_sn'] = $order_info['order_sn']; //订单号
        $info['ma_true_name'] = $order_info['ma_true_name']; //收货人
        $info['ma_phone'] = $order_info['ma_phone']; //收货人电话
        $info['buyer_address'] = $order_info['buyer_address']; //收货人地址
        $info['order_sn'] = $order_info['order_sn']; //订单号
        $info['add_time'] = date('Y-m-d H:i:s',$order_info['add_time']); //下单时间
        $info['payment_code'] = $order_info['payment_code']; //支付方式
        $info['payment_time'] = $order_info['payment_time']?date('Y-m-d H:i:s',$order_info['payment_time']):''; //支付时间
        $info['e_name'] = (new Express())->getExpressInfo(['id'=>$order_info['shipping_express_id']])['e_name']?:''; //物流公司
        $info['shipping_code'] = $order_info['shipping_code']?:''; //物流单号
        $info['goods_amount'] = floatval($order_info['goods_amount']); //商品总额
        $info['order_amount'] = floatval($order_info['order_amount']); //应付金额
        $info['order_coupon_price'] = floatval($order_info['promotion_total']); //优惠金额
        $info['point_amount'] = ceil($order_info['pd_amount']); //获得积分
        $info['pd_amount'] = floatval($order_info['pd_amount']); //实付款
        $info['order_state'] = $order_info['order_state']; //订单状态
        $info['shipping_fee'] = floatval($order_info['shipping_fee']); //运费
        foreach ($order_info['extend_order_goods'] as $k => $v){

            //商品类型
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['order_goods_id']]);
            $info['tradeType'] = (new Warehouse())->getWarehouseInfo(['tradeType' => $goods_info['tradeType']]);
            if ($order_info['order_state'] == 20){
                if ($order_info['order_mc_id']){
                    //存在优惠券 检查优惠券是否过期
                    $now_time = time();
                    $member_coupon_info = (new MemberCoupon())->getMemberCouponInfo(['mc_id' => $v['order_mc_id'], 'mc_state' => 1]);
                    if ($member_coupon_info['coupon_start_time'] > $now_time || $member_coupon_info['coupon_end_time'] < $now_time) {
                        $this->cancel_order($order_id);
                        $info['order_state'] = 10; //订单已取消
                    }
                }
                if ($v['goods_type']==4){
                    //满减活动是否过期
                    $full_info = (new FullDown())->getFullDownInfo(['and',['fd_id'=>$v['promotions_id']],['fd_state'=>1],['>=','fd_end_time',time()],['<=','fd_start_time',time()]]);
                    if (!$full_info){
                        $this->cancel_order($order_id);
                        $info['order_state'] = 10; //订单已取消
                    }
                }
                if ($v['order_goods_dp_id']){
                    //限时抢购活动是否过期
                    $pb_info = (new PanicBuy())->getPanicBuyInfo(['pb_id' => $v['order_goods_dp_id']]);
                    if ($pb_info['pb_end_time'] <= time() || $pb_info['pb_state'] != 2) {
                        $this->cancel_order($order_id);
                        $info['order_state'] = 10; //订单已取消
                    }
                }
            }
            $info['good_list'][$k]['dhsj'] = '';
            if ($info['order_state'] > 20) {
                $info['dhsj'] = $info['tradeType']['time'];
            }
            $info['good_list'][$k]['goods_name'] = $v['order_goods_name'];
            $info['good_list'][$k]['order_goods_price'] = floatval($v['order_goods_price']);
            $info['good_list'][$k]['order_goods_num'] = $v['order_goods_num'];
            $info['good_list'][$k]['order_goods_id'] = $v['order_goods_id'];
            $info['good_list'][$k]['order_goods_image'] = SysHelper::getImage($v['order_goods_image'], 0, 0, 0, [0, 0], 1);;
        }
        $this->jsonRet->jsonOutput(0, '加载成功！',$info);
    }


    /*****************优惠组合*******************************************************************************************************/

    /**优惠组合套餐
     * @param $goods_id
     */
    public function actionPackages($goods_id)
    {
        $package_list = (new DiscountPackage())->getDiscountPackageListByGoodsIdByApi1($goods_id);
        $this->jsonRet->jsonOutput(0, '加载成功！', $package_list);
    }

    /**优惠组合结算 无运费
     * @param $dp_id 优惠组合id
     */
    public function actionPackageOrder($dp_id, $ma_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $package_info = (new DiscountPackage())->getDiscountPackageInfo(['dp_id' => $dp_id], 'dp_id,dp_title,dp_discount_price');
        $package_goods_list = (new DiscountPackageGoods())->getDiscountPackageGoodsList(['dpg.dpg_dp_id' => $dp_id], '', '', '', 'gs.guige_name,gs.guige_price as goods_price,gs.guige_num as num,g.goods_id,g.goods_name,g.goods_pic');
        $goods_prices = 0;
        foreach ($package_goods_list as $k => &$v) {
            if ($v['num'] < 1) {
                //库存不够
                $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
            }
            $v['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
            $v['num'] = 1;
            $v['goods_price'] = floatval($v['goods_price']);
            $v['state'] = 1;
            $goods_prices += $v['goods_price'];
        }


        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
//        if ($addr_info) {
//            $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']); //省市区
//        }

        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $addr_info['ma_area'] . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址
        $prepare_data['package_info'] = $package_info; //优惠组合信息
        $prepare_data['order_info'] = $package_goods_list; //商品信息
        $prepare_data['full_down'] = floatval($goods_prices - $package_info['dp_discount_price']); //优惠金额
        $prepare_data['goods_amount'] = floatval($goods_prices); //商品总价
        $prepare_data['point_amount'] = ceil($package_info['dp_discount_price']); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功', $prepare_data);
    }

    /**优惠组合下单
     * @param $goods_id
     * @param $guige_id
     * @param $ma_id
     * @param string $buyer_message
     * @param int $mc_id
     * @param int $num
     */
    public function actionAddPackageOrder($dp_id, $ma_id, $buyer_message = '')
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        if ($member_info['member_type']) {
            $fxs_id = $member_info['member_id'];
        } else {
            $fxs_id = $member_info['member_recommended_no'] ? substr($member_info['member_recommended_no'], 3) : 0;
        }
        $package_info = (new DiscountPackage())->getDiscountPackageInfo(['dp_id' => $dp_id], 'dp_id,dp_title,dp_discount_price');
        $package_goods_list = (new DiscountPackageGoods())->getDiscountPackageGoodsList(['dpg.dpg_dp_id' => $dp_id], '', '', '', 'gs.guige_name,gs.guige_id,gs.guige_price,gs.guige_num,g.goods_id,g.goods_stock,g.commission,g.goods_name,g.goods_pic,dpg.dpg_goods_price');

        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id]); //地址
        if (!$addr_info) {
            $this->jsonRet->jsonOutput(-3, '地址不存在');
        }
//        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);

        $goods_amount = 0;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $commission_amount = 0; //佣金
            foreach ($package_goods_list as $k => &$v) {
                $goods_amount += $v['guige_price'];
                $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['goods_id']]); //商品
                if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
                    $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
                }
                if ($v['guige_num'] < 1) {
                    //库存不够
                    $this->jsonRet->jsonOutput(-2, '该规格商品库存不够！');
                }
                $v['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
                //添加订单商品
                $order_goods_date['order_goods_id'] = $v['goods_id']; //商品id
                $order_goods_date['order_goods_name'] = $v['goods_name']; //商品名称
                $order_goods_date['order_goods_price'] = $v['guige_price']; //商品单价 优惠组合优惠价
                $order_goods_date['order_goods_pay_price'] = $v['dpg_goods_price']; //商品实际成交价 优惠组合优惠价
                $order_goods_date['order_goods_num'] = 1; //商品数量
                $order_goods_date['order_goods_image'] = $v['goods_pic']; //商品图片
                $order_goods_date['commission_rate'] = $v['commission']; //佣金比例
                $order_goods_date['order_goods_spec_id'] = $v['guige_id']; //商品规格
                $order_goods_date['order_buyer_id'] = $member_info['member_id']; //买家id
                $order_goods_date['goods_type'] = 3; //订单类型 优惠组合
                $order_goods_date['promotions_id'] = $dp_id; //优惠组合id

                $order_rec_id[] = (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品

                //佣金
                $commission_amount += $v['commission'] * $v['dpg_goods_price'] / 100; //优惠组合商品价格*分佣

                (new Goods())->updateGoods(['goods_id' => $v['goods_id'], 'goods_stock' => $v['goods_stock'] - 1]); //减少商品库存

                //减少该规格下商品数量
                $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $v['guige_id']]); //规格
                (new GuiGe())->updateGuiGe(['guige_num' => ($guige_info['guige_num'] - 1), 'guige_id' => $v['guige_id']]);

            }
            //添加订单
            $order_date['buyer_address'] = $addr_info['ma_area'] . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['regionId'] = $addr_info['regionId'];
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['point_amount'] = ceil($package_info['dp_discount_price']); //赠送积分 为优惠组合价格向上取整
            $order_date['order_sn'] = 'YGH' . date('YmdHis', time()) . rand(100, 999); //订单编号
            $order_date['buyer_id'] = $member_info['member_id']; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $goods_amount; //商品总价格 为优惠组合价格
            $order_date['shipping_fee'] = 0; //运费
            $order_date['commission_amount'] = $commission_amount; //佣金
            $order_date['promotion_total'] = $goods_amount - $package_info['dp_discount_price']; //优惠金额
            $order_date['order_amount'] = $package_info['dp_discount_price']; //订单总价格 优惠组合价格
            $order_date['fxs_id'] = $fxs_id; //订单佣金所属分销商
            $order_id = (new Order())->insertOrder($order_date); //添加订单

            //添加订单商品
            $order_goods_up_date['order_id'] = $order_id; //订单id
            (new OrderGoods())->updateOrderGoodsByCondition($order_goods_up_date, ['in', 'order_rec_id', $order_rec_id]); //更新订单商品 添加订单id

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交订单成功', ['order_id' => $order_id]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交订单失败');
        }
    }

    /**
     * 退款原因
     */
    public function actionRefund()
    {
        $v = (new Setting())->getSetting('refund_type');
        $list['refund_type'] = explode('|', $v);
        $list['img'][0] = SysHelper::getImage('/data/uploads/产品问题点存在.png', 0, 0, 0, [0, 0], 1);
        $list['img'][1] = SysHelper::getImage('/data/uploads/产品在快递箱中的照片.png', 0, 0, 0, [0, 0], 1);
        $list['img'][2] = SysHelper::getImage('/data/uploads/产品正面照与快递单.png', 0, 0, 0, [0, 0], 1);
        $list['img'][3] = SysHelper::getImage('/data/uploads/快递包裹与快递面单.png', 0, 0, 0, [0, 0], 1);
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }

    /**
     * 退款快递列表
     */
    public function actionRefundArea()
    {
        $list = (new AreaGys())->getAreaGysList([], '', '', '', 'id,regionName');
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }

    /**
     * 提交退款快递信息
     */
    public function actionRefundAddr($refund_id, $regionName, $area_no)
    {
        $result = (new OrderReturn())->updateOrderReturn(['return_ems' => $regionName, 'return_sn' => $area_no, 'return_date' => time()], $refund_id, $type = '20');
        if ($result) {
            $this->jsonRet->jsonOutput(-1, '提交失败');
        } else {
            $this->jsonRet->jsonOutput(0, '提交成功');
        }
    }

    /**申请退款
     * @param $order_id
     * @param string $why
     * @param string $imgs
     * @param string $content
     */
    public function actionAddRefundOrder($order_id,$why='',$imgs='',$content=''){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        //订单信息
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);

        $refund_day = (new Setting())->getSetting('refund_day');
        if ($order_info['order_state'] == 60 && time() - 60 * 60 * 24 * $refund_day > $order_info['sh_time']) {
            //已收货 超过可退款时间
            $this->jsonRet->jsonOutput(-3, '该订单已过申请退款时间');
        }

        $return_info = (new OrderReturn())->getOrderReturnInfo(['order_id' => $order_id]);
        if ($return_info) {
            $this->jsonRet->jsonOutput(-2, '该订单已提交过申请');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $return_data['order_id'] = $order_info['order_id']; //订单id
            $return_data['order_sn'] = $order_info['order_sn']; //订单号
            $return_data['refund_sn'] = 'RE'.date('YmdHis').rand(100,999); //退货编号
            $return_data['buyer_name'] = $order_info['buyer_name']; //买家姓名
            $return_data['buyer_id'] = $order_info['buyer_id']; //买家id
            $return_data['refund_amount'] = $order_info['pd_amount']; //退款金额 为订单付款金额
            $return_data['refund_state'] = 1; //退款订单状态
            $return_data['add_time'] = time(); //申请退款时间
            $return_data['buyer_message'] = $why; //退款原因
            $return_data['refund_state'] = 1; //售后退款状态
            $return_data['seller_state'] = 1; //卖家处理状态
            $return_data['refund_type'] = $order_info['order_state'] == 30 ? 2 : 1; //退货类型 未发货 则退货订单状态为仅退款
            $imgs ? $return_data['pic_info'] = $imgs : ''; //图片
            $return_data['reason_info'] = $content; //退款原因描述
            $return_data['goods_id'] = $order_info['extend_order_goods'][0]['order_goods_id']; //
            $return_data['goods_num'] = $order_info['extend_order_goods'][0]['order_goods_num']; //
            $return_data['goods_name'] = $order_info['extend_order_goods'][0]['order_goods_name']; //
            $return_data['goods_image'] = $order_info['extend_order_goods'][0]['order_goods_image']; //


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

    /**提现申请-详情
     * @param $order_id
     * @param string $why
     * @param string $imgs
     * @param string $content
     */
    public function actionRefundOrderDetail()
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $list = (new ApplyMoney())->getApplyMoneyList(['member_id' => $member_info['member_id']], '', '1');
        $return_info = !empty($list[0]) ? $list[0] : [];
        !empty($return_info['create_time']) ? $return_info['create_time'] = date('Y-m-d H:i:s', $return_info['create_time']) : '';
        if (count($return_info) && $return_info['state'] == 3) {
            $return_info['state'] = 0;
        }
        $this->jsonRet->jsonOutput(0, '提交成功', $return_info);
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
    public function actionAddOrderEvaluate($geval_ordergoodsid,$geval_scores,$score1,$score2,$score3,$geval_image='',$geval_content='',$geval_isanonymous=0,$geval_tag=''){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $info = (new OrderGoods())->getOrderGoodsInfo(['order_rec_id' => $geval_ordergoodsid]);
        $order_info = (new Order())->getOrderOne(['order_id' => $info['order_id']]);
        if ($order_info['evaluation_state'] == 2) {
            $this->jsonRet->jsonOutput(-1, '该订单已评论');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
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
            $evaluate_data['geval_state'] = 1; //默认不显示
            $evaluate_data['geval_tag'] = $geval_tag; //评价标签
            $other = json_encode(['score1' => $score1, 'score2' => $score2, 'score3' => $score3]);
            $evaluate_data['geval_other'] = $other;

            $result = (new Evaluate())->insertEvaluateGoods($evaluate_data);


            if ($result) {
                //评价积分配置
                $comments_integral = (new Setting())->getSetting('comments_integral');
                //评价送积分
                $integral = $member_info['member_points'] + $comments_integral; //用户所剩积分
                (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]); //修改用户积分
                (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $comments_integral, 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '评价',]);
                $this->send_member_msg('积分变更', '您评论获得积分：' . $comments_integral . '，订单号：' . $order_info['order_sn']);
                $order_goods_data['order_rec_id'] = $geval_ordergoodsid;
                $order_goods_data['evaluation_state'] = 1; //已评论
                $geval_image ? $order_goods_data['share_state'] = 1 : ''; //已晒单

                (new OrderGoods())->updateOrderGoods($order_goods_data); //修改订单商品为已评论

                (new Order())->updateOrder(['order_id' => $info['order_id'], 'evaluation_state' => 2]); //修改订单为已评论

                $this->complete_order($info['order_id']);

                //修改商品评分
                $eg_info = (new Evaluate())->getEvaluateGoodsInfo(['geval_goodsid' => $info['order_goods_id']], 'sum(geval_scores) as all_geval_scores,count(*) as num_geval_scores');
                $goods_score = $eg_info['num_geval_scores'] ? number_format($eg_info['all_geval_scores'] / $eg_info['num_geval_scores'], 1) : 0;
                (new Goods())->updateGoods(['goods_id' => $info['order_goods_id'], 'goods_score' => $goods_score]);
                $transaction->commit();
                $this->jsonRet->jsonOutput(0, '提交成功', $comments_integral);
            } else {
                $this->jsonRet->jsonOutput(-1, '提交失败');
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交失败');
        }
    }


    /**追加评论
     * @param $geval_id
     * @param $content
     */
    public function actionTwoEvaluate($geval_id, $content)
    {
        $info = (new Evaluate())->getEvaluateGoodsInfo(['geval_id' => $geval_id]);
        if ($info['two_evaluate']) {
            $this->jsonRet->jsonOutput(-1, '不能再次评论');
        }
        $result = (new Evaluate())->updateEvaluateGoods(['geval_id' => $geval_id, 'two_evaluate' => $content]);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '评论成功');
        } else {
            $this->jsonRet->jsonOutput(-2, '评论失败');
        }
    }

    /**我的优惠券
     * @param $state 优惠券状态
     */
    public function actionMyCoupon($state=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        //获取优惠券
        $member_coupon_where =  ['and'];
        $member_coupon_where[] =  ['A.mc_member_id'=>$member_info['member_id']];
        $member_coupon_where[] =  ['A.mc_state'=>1];
        if ($state==1){
            //未使用 在使用期内
            $member_coupon_where[] =  ['A.mc_use_time'=>0];
//            $member_coupon_where[] =  ['<=','A.mc_start_time',$now_time];
            $member_coupon_where[] =  ['>=','A.mc_end_time',$now_time];
        }
        if ($state==2){
            //已使用
            $member_coupon_where[] = ['>', 'A.mc_use_time', 0];
        }
        if ($state==3){
            //已失效
            $member_coupon_where[] =  ['A.mc_use_time'=>0];
            $member_coupon_where[] =  ['<=','A.mc_end_time',$now_time];
        }


        $member_coupon_list = (new MemberCoupon())->getMemberCouponList($member_coupon_where, '', '', 'B.coupon_quota desc', 'A.mc_id,A.mc_coupon_id,B.coupon_quota,A.mc_min_price,A.mc_start_time,A.mc_end_time,B.coupon_title,B.coupon_img,B.coupon_content,C.cc_title,B.coupon_start_time,B.coupon_end_time');
        foreach ($member_coupon_list as $k=>&$v){
            $v['coupon_img'] = SysHelper::getImage($v['coupon_img'],0,0,0,[0,0],1);
            $v['coupon_start_time'] = date('Y.m.d', $v['mc_start_time']);
            $v['coupon_end_time'] = date('Y.m.d', $v['mc_end_time']);
            $v['coupon_min_price'] = floatval($v['coupon_min_price']);
            $v['mc_min_price'] = floatval($v['mc_min_price']);
            $v['coupon_quota'] = floatval($v['coupon_quota']);
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $member_coupon_list);
    }





    /**************************************拼团限时购模块**************************************************************************************************************/


    /**
     * 拼团限时购列表
     */
    public function actionBulk(){
        $now_time = time();
        $bulk_condition = ['and'];
        $bulk_condition[] = ['>=', 'end_time', $now_time];
        $goods_bulk_list = (new Bulk())->getBulkList($bulk_condition,'','','','');
        $list = [];
        foreach ($goods_bulk_list as $k => $v){
            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['goods_id'], 'A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], 'A.goods_name,A.goods_pic,A.goods_description');
            if (!$goods_info) {
                continue;
            }
            if ($v['start_time']<$now_time){
                $data['state'] = 1; //进行中
//                $list[$k]['time'] = ceil(($v['end_time']-$now_time)/(24*3600));
                $data['time'] = $this->timediff1($v['end_time'] - $now_time);
            }else{
                $data['state'] = 0; //待开团
//                $list[$k]['time'] = ceil(($v['start_time']-$now_time)/(24*3600));
                $data['time'] = $this->timediff1($v['start_time'] - $now_time);
            }
            $data['goods_id'] = $v['goods_id'];
            $data['bulk_id'] = $v['bulk_id'];
            $data['num'] = $v['num'];
            $data['bulk_price'] = floatval($v['bulk_price']);
            $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $v['guige_id']],'guige_price');
            $data['price'] = $guige_info['guige_price'];
            $data['goods_name'] = $goods_info['goods_name'];
            $data['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
            $list[] = $data;
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }


    /**拼团结算页面 不考虑促销价 不可用优惠券 免运费
     * @param $bulk_id 拼团活动id
     * @param $list_id 拼团id
     */
    public function actionPrepareBulkStart($bulk_id, $ma_id = 0)
    {
        $now_time = time();
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id'=>$bulk_id]);
        if ($goods_bulk_info['end_time']<$now_time || $goods_bulk_info['start_time']>$now_time){
            $this->jsonRet->jsonOutput(-2, '活动未开始或已结束！');
        }

        $goods_id = $goods_bulk_info['goods_id']; //商品
        $guige_id = $goods_bulk_info['guige_id']; //规格
        $bulk_price = floatval($goods_bulk_info['bulk_price']); //拼团价格
        $num=1;
        $member_info = $this->user_info; //用户信息
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
            $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
        }
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格
        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-3, '该规格商品库存不够！');
        }

        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
//        if ($addr_info) {
//            $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']); //省市区
//        }

        //商品信息
        list($goods[0]['goods_id'], $goods[0]['goods_name'], $goods[0]['goods_pic'], $goods[0]['price'], $goods[0]['bulk_price'], $goods[0]['num']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1), floatval($guige_info['guige_price']), $bulk_price, 1];
        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $addr_info['ma_area'] . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['goods_amount'] = floatval($bulk_price); //商品总价
        $prepare_data['point_amount'] = ceil($bulk_price); //赠送积分 为商品总价格向上取整
        $this->jsonRet->jsonOutput(0, '加载成功',$prepare_data);
    }

    /**拼团限时购下单 类内部调用
     * @param $id 拼团记录id
     * @param $ma_id
     * @param string $buyer_message
     */
    public function addBulkStartOrder($id, $ma_id, $buyer_message = '')
    {
        $now_time = time();
        $info = (new BulkList())->getBulkListInfo(['id' => $id]); //拼团记录
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id' => $info['bulk_id']]);
        if ($goods_bulk_info['end_time']<$now_time || $goods_bulk_info['start_time']>$now_time){
            $this->jsonRet->jsonOutput(-2, '活动未开始或已结束！');
        }
        $goods_id = $goods_bulk_info['goods_id']; //商品
        $guige_id = $goods_bulk_info['guige_id']; //规格
        $bulk_price = $goods_bulk_info['bulk_price']; //拼团价格
        $num=1;
        $member_info = $this->user_info;
        $member_id = $member_info['member_id'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]); //商品
        if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
            $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
        }
        if ($member_info['member_type']) {
            $fxs_id = $member_info['member_id'];
        } else {
            $fxs_id = $member_info['member_recommended_no'] ? substr($member_info['member_recommended_no'], 3) : 0;
        }
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id'=>$guige_id]); //规格

        if ($guige_info['guige_num']-$num<0){
            $this->jsonRet->jsonOutput(-3, '该规格商品库存不够，请重新下单！');
        }
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id'=>$ma_id]); //地址
//        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $order_date['buyer_address'] = $addr_info['ma_area'] . $addr_info['ma_area_info']; //收货地址
            $order_date['ma_phone'] = $addr_info['ma_mobile']; //收货人电话
            $order_date['regionId'] = $addr_info['regionId'];
            $order_date['ma_id'] = $ma_id; //地址id
            $order_date['ma_true_name'] = $addr_info['ma_true_name']; //收货人姓名
            $order_date['buyer_message'] = $buyer_message; //买家留言
            $order_date['point_amount'] = ceil($bulk_price); //赠送积分 为商品总价格向上取整
            $order_date['order_sn'] = 'YGH'.date('YmdHis',time()).rand(100,999); //订单编号
            $order_date['buyer_id'] = $member_id; //买家id
            $order_date['add_time'] = time(); //订单生成时间
            $order_date['buyer_name'] = $member_info['member_name']; //买家昵称
            $order_date['goods_amount'] = $bulk_price; //商品总价格
            $order_date['ordertype'] = 2; //
            $order_date['commission_amount'] = $bulk_price*$goods_info['commission']/100; //佣金 商品价格*佣金百分比
            $order_date['order_amount'] = $bulk_price; //订单总价格 为拼团价格
            $order_date['fxs_id'] = $fxs_id; //订单佣金所属分销商
            $order_id = (new Order())->insertOrder($order_date); //添加订单
            $order_goods_date['order_id'] = $order_id; //订单id
            $order_goods_date['order_goods_id'] = $goods_id; //商品id
            $order_goods_date['order_goods_name'] = $goods_info['goods_name']; //商品名称
            $order_goods_date['order_goods_price'] = $guige_info['guige_price']; //商品价格
            $order_goods_date['order_goods_pay_price'] = $bulk_price; //商品实际成交价 为商品拼团价
            $order_goods_date['order_goods_num'] = $num; //商品数量
            $order_goods_date['order_goods_image'] = $goods_info['goods_pic']; //商品图片
            $order_goods_date['commission_rate'] = $goods_info['commission']; //佣金比例
            $order_goods_date['order_goods_spec_id'] = $guige_id; //商品规格
            $order_goods_date['goods_type'] = 5; //订单商品类型
            $order_goods_date['type'] = 2; //拼团
            $order_goods_date['promotions_id'] = $id; //拼团记录id
            (new OrderGoods())->insertOrderGoods($order_goods_date); //添加订单商品

            (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - $num]); //减少商品库存
            (new GuiGe())->updateGuiGe(['guige_num'=>$guige_info['guige_num']-$num,'guige_id'=>$guige_id,]); //减少该规格下商品数量
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
    public function actionBulkDetail($bulk_id){

        $member_info = $this->user_info;
        $goods_bulk_info = (new Bulk())->getBulkInfo(['bulk_id'=>$bulk_id],'');
        if ($goods_bulk_info['end_time'] < time()) {
            $this->jsonRet->jsonOutput(-2, '已结束！');
        }
        $guige_info = (new GuiGe())->getGuiGeInfo(['guige_id' => $goods_bulk_info['guige_id']], 'guige_price,guige_no,guige_name');
        $info['price'] = floatval($guige_info['guige_price']);
        $info['guige_no'] = $guige_info['guige_no'];
        $info['guige_name'] = $guige_info['guige_name'];
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_bulk_info['goods_id']]);
        $info['goods_name'] = $goods_info['goods_name'];
        $info['goods_description'] = $goods_info['goods_description'];
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $info['goods_mobile_content'] = str_replace('/yghsc/data/uploads/store/', $http_type . $_SERVER['SERVER_NAME'] . '/yghsc/data/uploads/store/', $goods_info['goods_mobile_content']);
        $info['goods_mobile_content'] = str_replace('/ueditor/image/', $http_type . $_SERVER['SERVER_NAME'] . '/ueditor/image/', $goods_info['goods_mobile_content']);
        $info['goods_stock'] = $goods_info['goods_stock'];
        $info['goods_remark'] = $goods_info['goods_remark'];
        $info['goods_sales'] = $goods_info['goods_sales'];
        $info['goods_id'] = $goods_bulk_info['goods_id'];
        $info['bulk_id'] = $goods_bulk_info['bulk_id'];
        $info['num'] = $goods_bulk_info['num'];
        $info['bulk_price'] = floatval($goods_bulk_info['bulk_price']);
        $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        $goods_pic1 = empty($goods_info['goods_pic1']) ? [] : explode(',', $goods_info['goods_pic1']);
        if (count($goods_pic1)) {
            foreach ($goods_pic1 as $k => $v) {
                $goods_pic1[$k] = SysHelper::getImage($v, 0, 0, 0, [0, 0], 1);
            }
        }
        $goods_pic1[] = $goods_info['goods_pic'];
        $info['goods_pic'] = $goods_pic1;
//        $info['labels'] = $goods_info['labels'] ? explode('|', $goods_info['labels']) : []; //商品标签
        //用户评论
        $fields = 'G.geval_id,G.geval_content,G.geval_addtime,G.geval_frommemberid,G.geval_frommembername,G.geval_image,M.member_avatar';
        $goods_evaluate = (new Evaluate())->getEvaluateGoodsAndMemberList(['G.geval_goodsid' => $goods_bulk_info['goods_id'], 'G.geval_state' => 0], 0, 2, 'geval_id desc', $fields);

        //总评论数
        $goods_evaluate_count = (new Evaluate())->getEvaluateGoodsCount(['geval_goodsid' => $goods_bulk_info['goods_id'], 'geval_state' => 0]);

        //品牌
        $brand = (new Brand())->getBrandInfo(['brand_id'=>$goods_info['brand_id']],'brand_id,brand_name,brand_pic');
        $brand['brand_pic'] = SysHelper::getImage($brand['brand_pic'], 0, 0, 0, [0, 0], 1);
        $info['brand'] = $brand;

        //商品类型
        $info['tradeType'] = [];
        if ($goods_info['tradeType']) {
            $info['tradeType'] = (new Warehouse())->getWarehouseInfo(['tradeType' => $goods_info['tradetype']]);
        }

        //地区
        $countries = (new Countrie())->getCountrieInfo(['countrie_id'=>$goods_info['countrie_id']],'countrie_pic,countrie_name');
        $countries['countrie_pic'] = SysHelper::getImage($countries['countrie_pic'], 0, 0, 0, [0, 0], 1);
        $info['countrie'] = $countries;

        //团购状态
        $now_time = time();
        if ($goods_bulk_info['start_time'] < $now_time) {
            $info['state'] = 1; //进行中
//            $info['time'] = ceil(($goods_bulk_info['end_time'] - $now_time) / (24 * 3600)); //距离结束时间（天）
            $info['time'] = $this->timediff1($goods_bulk_info['end_time'] - $now_time); //距离结束时间
        } else {
            $info['state'] = 0; //待开始
//            $info['time'] = ceil(($goods_bulk_info['start_time'] - $now_time) / (24 * 3600)); //距离开始时间（天）
            $info['time'] = $this->timediff1($goods_bulk_info['start_time'] - $now_time); //距离开始时间
        }

        //拼团列表 未完成拼团
        $bulk_start_list = (new BulkStart())->getBulkStartList(['bulk_id' => $goods_bulk_info['bulk_id'], 'state' => 0], '', '', 'create_time desc', 'list_id,now_num,need_num,menber_id');
        foreach ($bulk_start_list as $k => $v){
            if ($v['now_num'] == $v['need_num']) {
                //拼团未完成 人数已满 但有用户订单未付款
                unset($bulk_start_list[$k]);
                continue;
            }
            $bulk_start_list[$k]['state'] = 1;
            $bulk_list_list = (new BulkList())->getBulkListList(['bulk_id'=>$goods_bulk_info['bulk_id'],'list_id'=>$v['list_id']],'','','create_time asc','member_id,member_state');
            foreach ($bulk_list_list as $k1 => $v1) {
                $member_avatar = (new Member())->getMemberInfo(['member_id' => $v1['member_id']], 'member_avatar')['member_avatar'];
                $bulk_list_list[$k1]['member_avatar'] = SysHelper::getImage($member_avatar, 0, 0, 0, [0, 0], 1);
                if ($member_info && $v1['member_id'] == $member_info['member_id']) {
                    $bulk_start_list[$k]['state'] = 0;
                }
            }
            $bulk_start_list[$k]['datail'] = $bulk_list_list;
        }

        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $info['goods_mobile_content'] = str_replace('/yghsc/data/uploads/store/', $http_type . $_SERVER['SERVER_NAME'] . '/yghsc/data/uploads/store/', $info['goods_mobile_content']);

        $info['bulk_list'] = array_column($bulk_start_list, 'datail');
        $info['guige_id'] = $goods_bulk_info['guige_id'];

        //是否收藏该商品
        $is_collect_goods = 0;
        $member_info = $this->user_info;
        if ($member_info) {
            if ($member_info && (new MemberFollow())->getMemberFollowCount(['fav_id' => $goods_bulk_info['goods_id'], 'fav_type' => 'goods', 'member_id' => $member_info['member_id']]) > 0) {
                $is_collect_goods = 1;
            }
        }

        $data = [
            'goods_info' => $info, //商品详情
            'goods_evaluate' => $goods_evaluate,
            'goods_evaluate_count' => $goods_evaluate_count,
            'bulk_list' => array_values($bulk_start_list),
            'is_collect_goods' => $is_collect_goods,
        ];

        $this->jsonRet->jsonOutput(0, '加载成功', $data);
    }

    /**开团
     * @param $member_id 用户
     * @param $bulk_id 团购活动id
     * @param $ma_id 地址
     * @param $buyer_message 买家留言
     */
    public function actionAddBulkStart($bulk_id,$ma_id,$buyer_message=''){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }

        $member_id = $member_info['member_id'];
        $transaction = Yii::$app->db->beginTransaction();
        try {
            //添加开团记录
            $start_data['menber_id'] = $member_id;
            $start_data['bulk_id'] = $bulk_id;
            $bulk_info = (new Bulk())->getBulkInfo(['bulk_id'=>$bulk_id,'state'=>1],'num');
            $start_data['need_num'] = $bulk_info['num'];
            $start_data['goods_id'] = $bulk_info['num'];
            $start_data['now_num'] = 1;
            $start_data['create_time'] = time();
            $start_data['state'] = -1;
            $list_id = (new BulkStart())->insertBulkStart($start_data);
            //添加开团记录

            //添加团购记录
            $list_data['member_id'] = $member_id;
            $list_data['bulk_id'] = $bulk_id;
            $list_data['list_id'] = $list_id;
            $list_data['create_time'] = time();
            $list_data['member_state'] = 1;
            $id = (new BulkList())->insertBulkList($list_data);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
        $order_id = $this->addBulkStartOrder($id, $ma_id, $buyer_message);
        if ($order_id){
            $this->jsonRet->jsonOutput(0, '操作成功', ['order_id' => $order_id, 'list_id' => $list_id]);
        }else{
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /** 拼团
     * @param $member_id 用户
     * @param $list_id 团购id
     * @param $ma_id 地址
     * @param $buyer_message 买家留言
     */
    public function actionAddBulkList($list_id,$ma_id,$buyer_message=''){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }

        $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $list_id]);
        if ($bulk_start_info['need_num'] == $bulk_start_info['now_num']) {
            $this->jsonRet->jsonOutput(-4, '该团人数已满！');
        }

        $member_id = $member_info['member_id'];
        $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $list_id], 'bulk_id,now_num');
        $list_data['member_id'] = $member_id;
        $list_data['bulk_id'] = $bulk_start_info['bulk_id'];
        $list_data['list_id'] = $list_id;
        $list_data['create_time'] = time();
        $list_data['member_state'] = 0;
        $id = (new BulkList())->insertBulkList($list_data);

        //添加拼团人数 订单过期取消后 恢复
        (new BulkStart())->updateBulkStart(['list_id' => $list_id, 'now_num' => $bulk_start_info['now_num'] + 1]);

        $order_id = $this->addBulkStartOrder($id, $ma_id, $buyer_message);
        if ($order_id){
            $this->jsonRet->jsonOutput(0, '操作成功', ['order_id' => $order_id, 'list_id' => $list_id]);
        }else{
            $this->jsonRet->jsonOutput(-1, '操作失败');
        }
    }

    /**
     * 我的拼团
     */
    public function actionMyBulkList($page = 1, $type = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $now_time = time();

        $where =  ['and'];
        $where[] =  ['A.member_id'=>$member_info['member_id']];
        if ($type==1){
            //拼团中
            $where[] = ['<=', 'C.state', 0];
            $where[] =  ['>=','B.end_time',$now_time];
        }
        if ($type==2){
            //拼团成功
            $where[] =  ['C.state'=>1];
        }
        if ($type==3){
            //拼团失败
            $where[] =  ['<=','B.end_time',$now_time];
            $where[] = ['<=', 'C.state', 0];
        }

        $bulk_list = (new BulkList())->getBulkListList1($where,$offset,$pageSize,'A.create_time desc');

        $totalCount = (new BulkList())->getBulkListCount1($where);
        foreach ($bulk_list as $k => $v){
            $order_goods_info = (new OrderGoods())->getOrderGoodsInfo(['goods_type' => 5, 'promotions_id' => $v['id']]); //订单商品详细信息
            $order_info = (new Order())->getOrderOne(['order_id'=>$order_goods_info['order_id']]); //订单详细信息

            if ($order_info['order_state'] == 20 && time() - 60 * 20 > $order_info['add_time']) {
                //超过二十分钟
                $this->cancel_order($order_goods_info['order_id']);
                $buil_state = -1; //订单超时 拼团失败
            }

            if ($v['state'] <= 0 && $v['end_time'] >= $now_time) {
                //拼团中
                if ($order_info['order_state'] == 20) {
                    $buil_state = 2; //待付款
                }
                if ($order_info['order_state'] == 30) {
                    $buil_state = 3; //已付款
                }
            }
            if ($v['state'] == 1) {
                //拼团成功
                if ($order_info['order_state'] == 30) {
                    $buil_state = 4; //待发货
                }
                if ($order_info['order_state'] == 40) {
                    $buil_state = 5; //已发货
                }
                if ($order_info['order_state'] == 50) {
                    $buil_state = 6; //已收货
                }
                if ($order_info['order_state'] == 60) {
                    $buil_state = 7; //已完成
                }
            }
            if ($v['state'] <= 0 && $v['end_time'] <= $now_time) {
                $buil_state = -1; //拼团过期 拼团失败
            }
            $list[$k]['create_time'] = date('Y-m-d',$v['create_time']);
            $list[$k]['order_state'] = $buil_state;
            $list[$k]['order_price'] = floatval($order_info['order_amount']);
            $list[$k]['goods_num'] = $order_goods_info['order_goods_num'];
            $list[$k]['goods_id'] = $order_goods_info['order_goods_id'];
            $list[$k]['goods_pic'] = SysHelper::getImage($order_goods_info['order_goods_image'], 0, 0, 0, [0, 0], 1);
            $list[$k]['order_goods_name'] = $order_goods_info['order_goods_name'];
            $list[$k]['order_id'] = $order_goods_info['order_id'];
            $list[$k]['bulk_id'] = $v['bulk_id'];
            $list[$k]['list_id'] = $v['list_id'];
            $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $v['list_id']], 'need_num,now_num');
            $list[$k]['need_num'] = $bulk_start_info['need_num'] - $bulk_start_info['now_num'];
        }
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list ?: [], 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功！',$show);
    }

    /**拼团成功
     * @param $list_id
     */
    public function actionBullEnd($list_id)
    {
        $memberinfo = $this->user_info;
        if (!$memberinfo) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $list_id], 'need_num,now_num,state,bulk_id');
        $bulk_list = (new BulkList())->getBulkListList(['list_id' => $list_id, 'state' => 1], '', '', 'id asc');
        $member_id_arr = array_column($bulk_list, 'member_id');
        foreach ($bulk_list as $k => $v) {
            $member_info = (new Member())->getMemberInfo(['member_id' => $v['member_id']], 'member_name,member_avatar');
            $member_info['member_avatar'] = SysHelper::getImage($member_info['member_avatar'], 0, 0, 0, [0, 0], 1);
            $bulk_list[$k] = $member_info;
        }

        $bulk_info = (new Bulk())->getBulkInfo(['bulk_id' => $bulk_start_info['bulk_id']]);
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $bulk_info['goods_id']], 'A.goods_name,A.goods_pic');
        $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $list_id], 'need_num,now_num,state,bulk_id');
        $bulk_start_info['state'] = $bulk_start_info['state'] == 1 ? 1 : 0;
        $bulk_start_info['goods_info'] = $goods_info;
        $bulk_start_info['member_list'] = $bulk_list;
        $bulk_start_info['bulk_price'] = floatval($bulk_info['bulk_price']);
        $bulk_start_info['time'] = $this->timediff1($bulk_info['end_time'] - time()); //距离结束时间（天）
        $bulk_start_info['is_can_join'] = in_array($memberinfo['member_id'], $member_id_arr) || $bulk_start_info['state'] == 1 ? 0 : 1;
        $this->jsonRet->jsonOutput(0, '加载成功！', $bulk_start_info);
    }



    /**************************************积分商城模块**************************************************************************************************************/

    /**
     * 积分商城首页
     */
    public function actionIntegralIndex($page = 1, $keyword = '')
    {
        $member_info = $this->user_info;
        //轮播图
        $info = (new AdSite())->getAdSiteInfo(['page_type' => 3, 'ads_type' => 2], 'ads_id');
        $adv = (new AdService())->getAdList(['ad_ads_id' => $info['ads_id']], '', '', '', 'ad_link,ad_pic,type');
        foreach ($adv as $key=> $val){
            $adv[$key]['ad_pic'] = SysHelper::getImage($val['ad_pic'],0,0,0,[0,0],1);
        }

        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']], ['A.goods_type' => 2], ['like', 'A.goods_name', $keyword]];

        //商品
        $totalCount = $goods_model->getGoodsCount($condition);
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.integral,A.goods_pic,A.goods_sales,A.goods_description';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, 'goods_id desc', $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
//            $goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
        }

        $integral = !empty($member_info['member_points']) ? $member_info['member_points'] : 0; //我的积分

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['adv' => $adv, 'goods_list' => $goods_list, 'pages' => $page_arr, 'integral' => $integral];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**积分商品列表|积分商品搜索
     * @param int $class_id 分类
     * @param string $keyword 搜索
     * @param string $order 排序
     * @param int $page 页码
     * @param string $brand_id 品牌
     * @param string $countrie_id 地区
     * @param int $low_price 最低价
     * @param int $high_price 最高价
     */
    public function actionIntegralGoodsList($class_id=0,$keyword='',$order='A.goods_id desc',$page=1,$brand_id='',$countrie_id='',$low_integral=0,$high_integral=0)
    {
        $goods_model = new Goods();
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $condition = ['and', ['like', 'A.goods_name', $keyword], ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']],['A.goods_type' => 2]];

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
            $class_info = (new GoodsClass())->getGoodsClassInfo(['class_id' => $class_id], 'class_path');
            $condition[] = ['or', ['like', 'C.class_path', $class_info['class_path'] . ',%', false], ['C.class_path' => $class_info['class_path']]];
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
            $byorder = str_replace(array('1', '2', '3', '4'), array('A.goods_sales desc', 'A.goods_sales asc', 'A.goods_create_time desc', 'A.goods_create_time asc'), $order);
        }
        //商品
        $fields = 'A.goods_id,A.goods_name,A.goods_description,A.integral,A.goods_pic,A.goods_sales,A.goods_description';
        $goods_list = $goods_model->getGoodsList($condition, $offset, $pageSize, $byorder, $fields);
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
//            $goods_list[$key]['goods_description'] = $val['goods_description'] ? explode('|', $val['goods_description']) : [];
        }

        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $goods_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**我的积分明细
     * @param int $coin_type 1增加 2减少
     * @param int $page
     */
    public function actionIntegralDetailList($coin_type=1,$page=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $state_str = $coin_type == 1 ? '+' : '-';
        $pageSize = 15;
        $offset = ($page-1)*$pageSize;
        $integral_list = (new MemberCoin())->getMemberCoinList(['member_id' => $member_id,'coin_type'=>$coin_type],$offset,$pageSize,'coin_addtime desc','coin_addtime,coin_desc,coin_points');
        foreach ($integral_list as $k => &$v){
            $v['coin_addtime'] = date('Y.m.d',$v['coin_addtime']);
            $v['coin_points'] = $state_str . $v['coin_points'];
        }
        $totalCount = (new MemberCoin())->getMemberCoinCount(['member_id' => $member_id,'coin_type'=>$coin_type]);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['integral_list' => $integral_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**积分兑换记录
     * @param int $page 页码
     */
    public function actionIntegralOrder($page=1){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $pageSize = 10;
        $offset = ($page-1)*$pageSize;
        $integral_order_list = (new IntegralOrder())->getIntegralOrderList(['member_id' => $member_id], $offset, $pageSize, 'create_time desc', 'id,goods_name,goods_pic,integral,create_time');
        foreach ($integral_order_list as $k => &$v){
            $v['create_time'] = date('Y.m.d',$v['create_time']);
            $v['goods_pic'] = SysHelper::getImage($v['goods_pic'], 0, 0, 0, [0, 0], 1);
        }
        $totalCount = (new MemberCoin())->getMemberCoinCount(['member_id' => $member_id]);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $integral_order_list, 'pages' => $page_arr];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**积分兑换详情
     * @param
     */
    public function actionIntegralOrderDetail($id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $integral_order_info = (new IntegralOrder())->getIntegralOrderInfo(['id' => $id]);
        $integral_order_info['goods_pic'] = SysHelper::getImage($integral_order_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        $integral_order_info['create_time'] = date('Y-m-d H:i:s', $integral_order_info['create_time']);
        $ex_info = (new Express)->getExpressInfo(['id' => $integral_order_info['shipping_express_id']]);
        $result = $this->getetOrderTracesByJson($ex_info['e_code'], $integral_order_info['shipping_code']);
        $result = json_decode($result, true);
        $integral_order_info['e_name'] = $ex_info['e_name'];
        $data['order_info'] = $integral_order_info;
        if ($result['Success']) {
            $data['wl'] = $result['Traces'];
        } else {
            $data['wl'] = [];
        }
        $this->jsonRet->jsonOutput(0, '查询成功', $data);
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
        $fields = 'A.goods_id,A.goods_name,A.tradetype,A.goods_mobile_content as goods_content,A.goods_pc_content,A.goods_remark,A.goods_description as labels,A.goods_sku,A.goods_pic,A.integral,A.brand_id,A.goods_stock,A.countrie_id,A.goods_sales,A.goods_promotion_type,A.goods_promotion_id,A.goods_full_down_id,E.goods_pic1,E.goods_pic2,E.goods_pic3,E.goods_pic4';
        $goods_info = $goods_model->getGoodsInfo(['A.goods_id' => $goods_id], $fields);
        if (!$goods_info) {
            $this->jsonRet->jsonOutput($this->errorRet['GOODS_NOT_EXIST']['ERROR_NO'], $this->errorRet['GOODS_NOT_EXIST']['ERROR_MESSAGE']);
        }
        $goods_info['goods_sku'] = $goods_info['goods_id'];
        $goods_info['goods_pic'] = SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1);
        $goods_pic1 = empty($goods_info['goods_pic1']) ? [] : explode(',', $goods_info['goods_pic1']);
        if (count($goods_pic1)) {
            foreach ($goods_pic1 as $k => $v) {
                $goods_pic1[$k] = SysHelper::getImage($v, 0, 0, 0, [0, 0], 1);
            }
        }
        $goods_pic1[] = $goods_info['goods_pic'];
        $goods_info['goods_pic'] = $goods_pic1;
//        $goods_info['labels'] = $goods_info['labels'] ? explode('|', $goods_info['labels']) : []; //商品标签
        //品牌
        $goods_info['brand'] = (new Brand())->getBrandInfo(['brand_id'=>$goods_info['brand_id']],'brand_id,brand_name,brand_pic');
        $goods_info['brand']['brand_pic'] = SysHelper::getImage($goods_info['brand']['brand_pic'], 0, 0, 0, [0, 0], 1);

        //地区
        $goods_info['countries'] = (new Countrie())->getCountrieInfo(['countrie_id'=>$goods_info['countrie_id']],'countrie_pic,countrie_name');
        $goods_info['countries']['countrie_pic'] = SysHelper::getImage($goods_info['countries']['countrie_pic'], 0, 0, 0, [0, 0], 1);

        //收藏该商品
        $is_collect_goods = 0;
        $member_info = $this->user_info;
        if ($member_info) {
            if ($member_info && (new MemberFollow())->getMemberFollowCount(['fav_id' => $goods_id, 'fav_type' => 'goods', 'member_id' => $member_info['member_id']]) > 0) {
                $is_collect_goods = 1;
            }
        }

        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        $goods_info['goods_content'] = str_replace('/yghsc/data/uploads/store/', $http_type . $_SERVER['SERVER_NAME'] . '/yghsc/data/uploads/store/', $goods_info['goods_content']);
        $goods_info['goods_content'] = str_replace('/ueditor/image/', $http_type . $_SERVER['SERVER_NAME'] . '/ueditor/image/', $goods_info['goods_content']);

        //商品类型
        $goods_info['tradeType'] = [];
        if ($goods_info['tradetype']) {
            $goods_info['tradeType'] = (new Warehouse())->getWarehouseInfo(['tradeType' => $goods_info['tradetype']]);
        }

        //去除无用参数
        unset($goods_info['goods_market_price']);
        unset($goods_info['goods_promotion_type']);
        unset($goods_info['goods_promotion_id']);
        unset($goods_info['goods_full_down_id']);
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

    /**我的积分
     * @param int $page
     */
    public function actionMyIntegral()
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }

        //签到积分配置
        $sign_integral = (new Setting())->getSettingInfo(['name' => 'sign']);
        $sign_arr = explode('|', $sign_integral['value']);
        $count = count($sign_arr);

        $goods_model = new Goods();

        $date = date('Ymd',time());
        $sign_day = 1;
        $last_sign_info = (new Sign())->getSignList(['member_id' => $member_info['member_id']],'','1','create_time desc');

        if ($last_sign_info && $date - $last_sign_info[0]['create_time'] == 1) {
            $sign_day = $last_sign_info[0]['day_num'] + 1; //当前第几天
        }
        if ($last_sign_info && $date - $last_sign_info[0]['create_time'] == 0) {
            $sign_day = $last_sign_info[0]['day_num']; //当前第几天
        }

        if ($last_sign_info && $last_sign_info[0]['day_num'] == $count) {
            $sign_day = 1; //超过最大签到天数从第一天开始
        }

        $sign_info = (new Sign())->getSignInfo(['member_id' => $member_info['member_id'],'create_time'=>$date]);

        $sign_state = $sign_info?1:0; //当天是否签到


        $sign_list = [];
        foreach ($sign_arr as $k => $v ){
            $sign_list[$k]['integral'] = $v;
            $sign_list[$k]['day_num'] = $k+1;
            //$sign_list[$k]['now_day'] = $sign_day==$k+1?1:0; //当前第几天
            //$sign_list[$k]['sign_state'] = $sign_state; //当天是否签到
        }

        //商品
        $condition = ['and', ['A.goods_state' => Yii::$app->params['GOODS_STATE_PASS']],['A.goods_type' => 2]];
        $fields = 'A.goods_id,A.goods_name,A.integral,A.goods_pic,A.goods_sales,A.goods_stock';
        $goods_list = $goods_model->getGoodsList($condition, '', 4, 'goods_id desc', $fields); //取四条
        foreach ($goods_list as $key => $val) {
            $goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
        }

        $show = ['goods_list' => $goods_list, 'sign_list' => $sign_list, 'integral' => $member_info['member_points'], 'sign_state' => $sign_state, 'sign_day' => $sign_day];
        $this->jsonRet->jsonOutput(0, '', $show);
    }

    /**
     * 签到
     */
    public function actionSign(){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $member_id = $member_info['member_id'];
        $date = date('Ymd',time());
        $sign_day = 1;

        $last_sign_info = (new Sign())->getSignList(['member_id' => $member_info['member_id']],'','1','create_time desc');

        if ($last_sign_info && $date - $last_sign_info[0]['create_time'] == 1) {
            $sign_day = $last_sign_info[0]['day_num'] + 1; //当前第几天
        }

        $sign_info = (new Sign())->getSignInfo(['member_id' => $member_info['member_id'],'create_time'=>$date]);

        if ($sign_info){
            $this->jsonRet->jsonOutput(-2, '今天已经签到！');
        }
        //签到积分配置
        $sign_integral = (new Setting())->getSettingInfo(['name'=>'sign']);
        $sign_arr =explode('|',$sign_integral['value']);
        foreach ($sign_arr as $k => $v ){
            if ($sign_day==($k+1)){
                $integral = $v;
            }
        }
        $sign_data['member_id'] = $member_id;
        $sign_data['day_num'] = $sign_day;
        $sign_data['integral'] = $integral;
        $sign_data['create_time'] = $date;

        (new Sign())->insertSign($sign_data);
        $result = (new Member())->updateMember(['member_id' => $member_id, 'member_points' => $member_info['member_points'] + $integral]);
        if ($result){
            (new MemberCoin())->insertMemberCoin1(['member_id' => $member_id, 'coin_member_name' => $member_info['member_name'], 'coin_points' => $integral, 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '签到获得积分',]);
            $this->jsonRet->jsonOutput(0, '签到成功！', ['integral' => $integral, 'sign_day' => $sign_day]);
        }else{
            $this->jsonRet->jsonOutput(-2, '签到失败！');
        }
    }

    /**积分兑换结算 免运费
     * @param $goods_id
     */
    public function actionSignPrepareOrder($goods_id, $ma_id = 0)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id'=>$goods_id]); //商品
        if ($goods_info['goods_state'] != Yii::$app->params['GOODS_STATE_PASS']) {
            $this->jsonRet->jsonOutput(-2, $goods_info['goods_name'] . '已下架！');
        }

        if ($goods_info['goods_stock'] < 1) {
            $this->jsonRet->jsonOutput(-3, '库存不足');
        }

        //地址
        if ($ma_id) {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id' => $ma_id, 'ma_member_id' => $member_info['member_id']], ''); //地址
        } else {
            $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_is_default' => 1, 'ma_member_id' => $member_info['member_id']], ''); //默认地址
        }
        if ($addr_info) {
            $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']); //省市区
        }

        //商品信息
        list($goods['goods_id'], $goods['goods_name'], $goods['goods_pic']) = [$goods_info['goods_id'], $goods_info['goods_name'], SysHelper::getImage($goods_info['goods_pic'], 0, 0, 0, [0, 0], 1)];
        //地址信息
        if ($addr_info) {
            list($addr['name'], $addr['mobile'], $addr['ma_id'], $addr['info']) = [$addr_info['ma_true_name'], $addr_info['ma_mobile'], $addr_info['ma_id'], $member_address . $addr_info['ma_area_info']];
        }
        $prepare_data['addr'] = $addr_info ? $addr : []; //默认收货地址
        $prepare_data['order_info'] = $goods; //商品信息
        $prepare_data['integral'] = $goods_info['integral']; //商品积分
        $this->jsonRet->jsonOutput(0, '加载成功',$prepare_data);
    }

    /**积分兑换
     * @param $goods_id
     * @param $ma_id
     */
    public function actionAddSignOrder($goods_id,$ma_id){
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id'=>$goods_id]); //商品
        $addr_info = (new MemberAddress())->getMemberAddressInfo(['ma_id'=>$ma_id]); //地址
//        $member_address = (new Area())->lowGetHigh($addr_info['ma_area_id']);
        $address = $addr_info['ma_area'] . $addr_info['ma_area_info']; //收货地址
        $integral = $member_info['member_points']-$goods_info['integral']; //用户所剩积分
        if ($integral<0){
            $this->jsonRet->jsonOutput(-2, '积分不足');
        }
        if ($goods_info['goods_stock'] < 1) {
            $this->jsonRet->jsonOutput(-3, '库存不足');
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
            $order_date['order_no'] = 'YGH-JF-' . time() . rand(100, 999); // 单号
            $order_date['mobile'] = $addr_info['ma_mobile']; //收货人手机
            $order_date['username'] = $addr_info['ma_true_name']; //收货人
            $order_date['member_id'] = $member_info['member_id']; //用户
            (new IntegralOrder())->insertIntegralOrder($order_date); //添加订单商品
            (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]); //修改用户积分
            (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_stock' => $goods_info['goods_stock'] - 1, 'goods_sales' => $goods_info['goods_sales'] + 1]); //修改商品库存和销量
            (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $goods_info['integral'], 'coin_type' => 2, 'coin_addtime' => time(), 'coin_desc' => '兑换商品',]);

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '兑换成功！');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '兑换失败');
        }
    }

    /**
     * 个人中心
     */
    public function actionMyCenter()
    {
        $member['state'] = 0;
        $member_info = $this->user_info;
        $order = [];
        if ($member_info) {
            $member['state'] = 1; //用户登录状态
            $member['integral'] = $member_info['member_points']; //用户积分
            $member['member_id'] = $member_info['member_id'];
            $member['member_name'] = $member_info['member_name'];
            $member['member_type'] = $member_info['member_type'];
            $member['member_avatar'] = SysHelper::getImage($member_info['member_avatar'], 0, 0, 0, [0, 0], 1);
            $count = (new Apply())->getApplyCount(['state' => 0, 'member_id' => $member_info['member_id']]);
            if ($count) {
                $member['member_type'] = 2; //申请中
            }
        }
        $order['num1'] = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'order_state' => 20, 'type' => 1, 'refund_state' => 1]); //待付款订单数
        $order['num2'] = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'order_state' => 30, 'type' => 1, 'refund_state' => 1]); //待发货订单数
        $order['num3'] = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'order_state' => 40, 'type' => 1, 'refund_state' => 1]); //待收货订单数
        $order['num4'] = (new Order())->getOrderCount(['and', ['>=', 'order_state', 50], ['buyer_id' => $member_info['member_id']], ['evaluation_state' => 1], ['type' => 1], ['refund_state' => 1]]); //待评价订单数
        $n = (new Order())->getOrderCount(['buyer_id' => $member_info['member_id'], 'refund_state' => 3, 'type' => 1]); //退货售后订单数
        $n1 = (new OrderReturn())->getOrderReturnCount(['buyer_id' => $member_info['member_id'], 'refund_state' => 8]);
        $order['num5'] = $n - $n1;
        $this->jsonRet->jsonOutput(0, '加载成功！', ['member_info' => $member, 'order' => $order]);
    }

    /**我的评价
     * @param int $type 类型 0 未评论 1 已评论
     * @param int $page
     */
    public function actionMyEvaluate($type = 0, $page = 1)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;
        $order_list = (new Order())->getOrderList(['and', ['>=', 'order_state', 50], ['buyer_id' => $member_info['member_id']], ['evaluation_state' => 1], ['type' => 1], ['refund_state' => 1]]); //待评价订单
        $order_list1 = (new Order())->getOrderList(['buyer_id' => $member_info['member_id'], 'order_state' => 60, 'evaluation_state' => 2]); //已评价订单
        $order_id_arr = array_column($order_list, 'order_id');
        $order_id_arr1 = array_column($order_list1, 'order_id');
        $num1 = (new OrderGoods())->getOrderGoodsCount(['and', ['evaluation_state' => 0], ['in', 'order_id', $order_id_arr]]); //已评论
        $num2 = (new OrderGoods())->getOrderGoodsCount(['and', ['evaluation_state' => 1], ['in', 'order_id', $order_id_arr1]]); //未评论
        if ($type == 1) {
            $order_id_arr = $order_id_arr1;
        }
        $where = ['and'];
        $where[] = ['evaluation_state' => $type];
        $where[] = ['in', 'order_id', $order_id_arr];
        //评价积分配置
        $comments_integral = (new Setting())->getSettingInfo(['name' => 'comments_integral'])['value'];
        $list = (new OrderGoods())->getOrderGoodsList($where, $offset, $pageSize, 'order_rec_id desc', 'order_rec_id,order_goods_id,order_goods_name,order_goods_image');

        foreach ($list as $key => $val) {
            $list[$key]['order_goods_image'] = SysHelper::getImage($val['order_goods_image'], 0, 0, 0, [0, 0], 1);
            if ($type == 0) {
                $list[$key]['comments_integral'] = $comments_integral;
            }
            if ($type == 1) {
                $goods_evaluate_info = (new Evaluate())->getEvaluateGoodsInfo(['geval_ordergoodsid' => $val['order_rec_id']]);
                if ($goods_evaluate_info) {
                    $list[$key]['geval_scores'] = $goods_evaluate_info['geval_scores'];
                    $list[$key]['geval_isanonymous'] = $goods_evaluate_info['geval_isanonymous'];
                    $list[$key]['geval_content'] = $goods_evaluate_info['geval_content'];
                    $list[$key]['two_evaluate'] = $goods_evaluate_info['two_evaluate'];
                    $list[$key]['two_evaluate_state'] = $goods_evaluate_info['two_evaluate'] ? 1 : 0;
                } else {
                    unset($list[$key]);
                }
            }
        }
        $totalCount = (new OrderGoods())->getOrderGoodsCount($where);
        $page_arr = SysHelper::get_page($pageSize, $totalCount, $page);
        $show = ['list' => $list, 'pages' => $page_arr, 'num1' => $num1, 'num2' => $num2];
        $this->jsonRet->jsonOutput(0, '加载成功', $show);
    }

    /**分类栏目
     * @param $class_id 分类id
     * @throws \yii\db\Exception
     */
    public function actionClassColumns($class_id)
    {
        $member_info = $this->user_info;
        if (!$class_id) {
            $this->jsonRet->jsonOutput(-1, '栏目不存在');
        }

        $lanm_info = (new LanMu())->getLanMuInfo(['id' => $class_id]);

        //轮播
        $info = (new AdSite())->getAdSiteInfo(['ad_class' => $class_id, 'page_type' => 2, 'ads_type' => 2], 'ads_id');
        $good_class_nav = (new AdService())->getAdList(['ad_ads_id' => $info['ads_id']], '', '', '', 'ad_pic,ad_link,type');
        foreach ($good_class_nav as $key => $val) {
            $good_class_nav[$key]['ad_pic'] = SysHelper::getImage($val['ad_pic'], 0, 0, 0, [0, 0], 1);
        }

        //二级分类
        $class_list = (new GoodsClass())->getGoodsClassList(['class_parent_id' => $lanm_info['class_id']], '', '', 'class_sort desc', 'class_id,class_name');
        $classid_arr = array_column($class_list, 'class_id');

        //该分类下未过期优惠券优惠券
        $coupon = (new Coupon())->getCouponList(['and', ['coupon_state' => 1], ['coupon_type' => 1], ['in', 'type', [1, 2]], ['>=', 'coupon_end_time', time()], ['!=', 'coupon_cc_id', 10]]);
        foreach ($coupon as $k => $v) {
            if ($v['type'] == 2) {
                $class_id_arr = explode(',', $v['goods_class_id']);
                $class_list1 = (new GoodsClass())->getGoodsClassList(['in', 'class_id', $class_id_arr], '', '', 'class_sort desc', 'class_id,class_name,class_parent_id');
                $class_id_arr1 = array_column($class_list1, 'class_parent_id');
                $class_list2 = (new GoodsClass())->getGoodsClassList(['in', 'class_id', $class_id_arr1], '', '', 'class_sort desc', 'class_id,class_name,class_parent_id');
                $class_id_arr2 = array_column($class_list2, 'class_id');
                if (!in_array($lanm_info['class_id'], $class_id_arr2)) {
                    continue;
                }
            }
            $list['state'] = 0; //是否领完
            $list['status'] = 0; //是否已领取
            $list['coupon_id'] = $v['coupon_id']; //优惠券id
            $list['coupon_title'] = $v['coupon_title']; //优惠券名称
            $list['coupon_img'] = SysHelper::getImage($v['coupon_img'], 0, 0, 0, [0, 0], 1); //优惠券图片
            $list['coupon_min_price'] = floatval($v['coupon_min_price']); //优惠券满多少可用
            $list['coupon_quota'] = floatval($v['coupon_quota']); //优惠券额度

            if ($v['coupon_num'] == $v['coupon_grant_num']) {
                $list['state'] = 1; //已领光
            }
            if ($member_info) {
                $member_coupon_count = (new MemberCoupon())->getMemberCouponCount(['mc_member_id' => $member_info['member_id'], 'mc_coupon_id' => $v['coupon_id']]);
                $list['status'] = $member_coupon_count ? 1 : 0;
            }
            $coupon_list[] = $list; //优惠券列表
        }

        //周热销榜 取前五 已付款订单
        $hot_goods_list = [];
        $order = (new Order())->getOrderList(['and', ['>=', 'add_time', time() - 24 * 3600 * 7], ['>=', 'order_state', 30]]);
        if ($order) {
            $order_id_arr = array_column($order, 'order_id');
            $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic,A.default_guige_id';
            $order_goods_list = Yii::$app->db->createCommand("select order_goods_id,sum(order_goods_num) as num from qbt_order_goods where order_id in (" . implode(',', $order_id_arr) . ") order by num desc limit 5")->queryAll();
            $goods_id_arr = array_column($order_goods_list, 'order_goods_id');
            $where = ['and'];
            $where[] = ['in', 'A.goods_type', [1, 3]]; //售卖商品
            $where[] = ['A.goods_state' => 20]; //上架商品
            $where[] = ['in', 'A.goods_class_id', $classid_arr];
            $where[] = ['in', 'A.goods_id', $goods_id_arr];
            $hot_goods_list = (new Goods())->getGoodsList($where, 0, 5, 'goods_sales desc,goods_id desc', $fields);
            foreach ($hot_goods_list as $key => $val) {
                $pbg_price = $this->activity($val['goods_id']);
                $hot_goods_list[$key]['pbg_price'] = $pbg_price ? floatval($pbg_price[$val['default_guige_id']]) : 0;
                $hot_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
                $hot_goods_list[$key]['goods_price'] = floatval($val['goods_price']);
                $hot_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            }
        }

        //精选推荐商品 取前五 暂按销量排序
        $fields = 'A.goods_id,A.goods_name,A.goods_price,A.goods_pic';

        //精选推荐广告
        $info = (new AdSite())->getAdSiteList(['ad_class' => $class_id, 'page_type' => 2, 'ads_type' => 1], 'ads_id');
        $arr = array_column($info, 'ads_id');
        $good_class_link = (new AdService())->getAdList(['in', 'ad_ads_id', $arr], '', '', 'ad_sort desc', 'ad_pic,ad_link,type');
        foreach ($good_class_link as $k => $v) {
            $good_class_link[$k]['ad_pic'] = SysHelper::getImage($v['ad_pic'], 0, 0, 0, [0, 0], 1);
            //精选推荐商品 取前五 暂按销量排序
            $tj_goods_list = (new Goods())->getGoodsList(['and', ['in', 'A.goods_type', [1, 3]], ['A.goods_hot' => 1], ['in', 'A.goods_class_id', $classid_arr], ['A.goods_state' => 20]], $k * 3, 3, 'goods_sales desc', $fields);
            foreach ($tj_goods_list as $key => $val) {
                $pbg_price = $this->activity($val['goods_id']) ?: 0;
                $tj_goods_list[$key]['pbg_price'] = floatval($pbg_price);
                $tj_goods_list[$key]['state'] = $pbg_price ? 1 : 0;; //是否有优惠
                $tj_goods_list[$key]['goods_price'] = floatval($val['goods_price']);
                $tj_goods_list[$key]['goods_pic'] = SysHelper::getImage($val['goods_pic'], 0, 0, 0, [0, 0], 1);
            }
            $good_class_link[$k]['tj_goods_list'] = $tj_goods_list;
        }

        $list = [
            'good_class_nav' => $good_class_nav, //轮播
            'class_list' => $class_list, //二级分类
            'coupon_list' => $coupon_list, //优惠券列表
            'hot_goods_list' => $hot_goods_list, //周热销榜
            'good_class_link' => $good_class_link, //精选推荐广告
        ];
        $this->jsonRet->jsonOutput(0, '加载成功', $list);
    }

    /**申请分销
     * @param $name 姓名
     * @param $mobile 手机
     * @param $apply_content 申请内容
     */
    public function actionAddApply($name, $mobile, $apply_content)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $info = (new Apply())->getApplyInfo(['and', ['member_id' => $member_info['member_id']], ['in', 'state', [0]]]);
        if ($info) {
            $this->jsonRet->jsonOutput(-2, '您已提交过申请');
        }
        $apply_data['name'] = $name;
        $apply_data['mobile'] = $mobile;
        $apply_data['apply_content'] = $apply_content;
        $apply_data['member_id'] = $member_info['member_id'];
        $apply_data['create_time'] = time();
        $result = (new Apply())->insertApply($apply_data);
        if ($result) {
            $this->jsonRet->jsonOutput(0, '提交成功');
        }
        $this->jsonRet->jsonOutput(-1, '提交失败');
    }

    /**提现申请
     * @param $name 姓名
     * @param $money 提现金额
     */
    public function actionAddApplyMoney($name, $money)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }

//        if ($member_info['member_type'] == 0) {
//            $this->jsonRet->jsonOutput(-5, '普通用户不能进行提现操作');
//        }

        $last_apply_money = (new Setting())->getSetting('apply_money'); //最低提现金额
        if ($last_apply_money > $money) {
            $this->jsonRet->jsonOutput(-2, '提现金额不得小于最低提现金额');
        }
        $lmoney = $member_info['available_predeposit'] - $money;
        if ($lmoney < 0) {
            $this->jsonRet->jsonOutput(-3, '佣金不足');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $data['name'] = trim($name);
            $data['money'] = $money;
            $data['order_no'] = 'tx' . time() . rand(100, 999);
            $data['member_id'] = $member_info['member_id'];
            $data['create_time'] = time();
            $data['balance'] = $member_info['available_predeposit'] - $money;
            $id = (new ApplyMoney())->insertApplyMoney($data);
            (new Member())->updateMember(['available_predeposit' => $lmoney, 'member_id' => $member_info['member_id']]);
            (new MemberPredeposit())->insertMemberChange(['member_id' => $member_info['member_id'], 'after' => $lmoney, 'before' => $member_info['available_predeposit'], 'predeposit_member_name' => $member_info['member_name'], 'predeposit_type' => 'cash_apply', 'predeposit_av_amount' => $money, 'predeposit_add_time' => time(), 'predeposit_desc' => '申请提现扣款', 'order_id' => $id]); //余额变动记录
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '提交成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput(-1, '提交失败');
        }
    }

    /**
     * 最低提现金额
     */
    public function actionLowMoney()
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $data['last_apply_money'] = (new Setting())->getSetting('apply_money'); //最低提现金额
        $data['available_predeposit'] = $member_info['available_predeposit']; //金额
        $this->jsonRet->jsonOutput(0, '加载成功', $data);
    }

    /**检查优惠券对商品是否可用
     * @param $goods_id
     * @param $coupon_id
     * @return bool
     */
    public function checkCoupon($goods_id, $coupon_id)
    {
        $coupon_info = (new Coupon())->getCouponInfo(['coupon_id' => $coupon_id]);
        if ($coupon_info['type'] == 1) {
            return true;
        } elseif ($coupon_info['type'] == 2) {
            $class_id_arr = explode(',', $coupon_info['goods_class_id']);
            $count = (new Goods())->getGoodsCount(['and', ['A.goods_id' => $goods_id], ['in', 'A.goods_class_id', $class_id_arr]]);
            return $count ? true : false;
        } elseif ($coupon_info['type'] == 3) {
            $goods_id_arr = explode(',', $coupon_info['goods_id']);
            return in_array($goods_id, $goods_id_arr);
        }
    }

    /**
     * 取消订单
     */
    public function actionMemberOrderCancel($order_id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $model_order = new Order();
        $order_info = $model_order->getOrderInfo(['order_id' => $order_id]);
        $transaction = Yii::$app->db->beginTransaction();
        try {
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
            $model_order->orderaction($order_id, 'buyer', $member_info['member_name'], '取消订单', 'ORDER_STATE_CANCEL');
            $this->send_member_msg('订单取消', '您的已取消订单，订单号：' . $order_info['order_sn']);
            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '操作成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput($this->errorRet['OPERATE_FAIL']['ERROR_NO'], $e->getMessage());
        }
    }

    /**
     * @return string
     * 确认收货
     */
    public function actionMemberOrderReceivedGoods($order_id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $model_order = (new Order());
        $order_info = $model_order->getOrderInfo(['order_id' => $order_id, 'buyer_id' => $member_info['member_id']]);
        if (!$order_info) {
            $this->jsonRet->jsonOutput(-2, '参数错误');
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $data = array('order_id' => $order_info['order_id'], 'sh_time' => time(), 'order_state' => 50);
            $result = $model_order->updateOrder($data);
            if (!$result) {
                $this->jsonRet->jsonOutput(-3, '操作失败');
            }

            /*记录日志*/
            $model_order->orderaction($order_info['order_id'], 'buyer', $order_info['buyer_name'], '确认收货', 'ORDER_STATE_DONE');

            //赠送购物积分
            $integral = $member_info['member_points'] + $order_info['point_amount']; //用户所剩积分
            (new Member())->updateMember(['member_id' => $member_info['member_id'], 'member_points' => $integral]); //修改用户积分
            (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $order_info['point_amount'], 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '购物',]);
            $this->send_member_msg('积分变更', '您确认收货获得积分：' . $order_info['point_amount'] . '，订单号：' . $order_info['order_sn']);

            $transaction->commit();
            $this->jsonRet->jsonOutput(0, '操作成功');

        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->jsonRet->jsonOutput($this->errorRet['OPERATE_FAIL']['ERROR_NO'], $e->getMessage());
        }
    }

    /**待支付页面
     * @param $order_id 订单号
     */
    public function actionNopay($order_id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $now_time = time();
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id, 'buyer_id' => $member_info['member_id']], ['order_goods'], 'ma_phone,ma_true_name,buyer_address,order_id,order_sn,add_time,goods_amount,promotion_total,shipping_fee,order_amount,point_amount');
        if ($now_time - $order_info['add_time'] > 60 * 20) {
            $this->cancel_order($order_id);
            $order_info['order_state'] = 10;
        }
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id, 'order_state' => 20, 'buyer_id' => $member_info['member_id']], ['order_goods'], 'ma_phone,ma_true_name,buyer_address,order_id,order_sn,add_time,goods_amount,promotion_total,shipping_fee,order_amount,point_amount');
        if ($order_info) {
            $order_info['end_time'] = $this->timediff(60 * 20 - ($now_time - $order_info['add_time'])); //待支付剩余时间
            $order_info['add_time'] = date('Y-m-d H:i:s', $order_info['add_time']);
            $order_info['point_amount'] = ceil($order_info['order_amount']);
            $order_info['order_amount'] = floatval($order_info['order_amount']);
            $order_info['shipping_fee'] = floatval($order_info['shipping_fee']);
            $order_info['promotion_total'] = floatval($order_info['promotion_total']);
            foreach ($order_info['extend_order_goods'] as $k => $v) {
                $list['order_goods_id'] = $v['order_goods_id'];
                $list['order_goods_name'] = $v['order_goods_name'];
                $list['order_goods_image'] = SysHelper::getImage($v['order_goods_image'], 0, 0, 0, [0, 0], 1);
                $list['order_goods_num'] = $v['order_goods_num'];
                $list['order_goods_price'] = $v['order_goods_price'];
                $order_info['extend_order_goods'][$k] = $list;
            }
        }
        $this->jsonRet->jsonOutput(0, '加载成功', $order_info);
    }


    /***********************支付**************************************************************************************/

    /**获取openid
     * @param $code 小程序code
     * @return bool|mixed|string
     */
    function getOpenId($code)
    {
        $app_id = $this->appid;
        $secret = $this->secret;
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid=' . $app_id . '&secret=' . $secret . '&js_code=' . $code . '&grant_type=authorization_code';

        $result = file_get_contents($url);

        $result = json_decode($result, true);
        if ($result['openid']) {
            $result['code'] = $code;
            return $result;
        }
        $this->jsonRet->jsonOutput(-500, '无效code');
    }

    /**订单支付
     * @param $order_id
     * @param $code
     * @throws \WxPayException
     */
    public function actionPay($order_id, $code)
    {
        //正式去掉
        $this->pay($order_id);
        $this->jsonRet->jsonOutput(0, '成功');
        //正式去掉

        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.JsApiPay.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.Config.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }

        $openid = $this->getOpenId($code);
        $info = (new Order())->getOrderOne(['order_id' => $order_id]);
        if ($info['payment_time'] > 0) {
            $this->jsonRet->jsonOutput(-1, '该订单已支付');
        }

        $tools = new \JsApiPay();
        $input = new \WxPayUnifiedOrder();
        $wxpay = new \WxPayApi();
        $input->SetBody('test');
        $input->SetDetail('test');
        $input->SetOut_trade_no($info['order_sn']);
        $input->SetTotal_fee($info['order_amount'] * 100);
//        $input->SetTotal_fee(1);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetNotify_url(Url::to(['index/notify'], true));
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openid['openid']);
        $config = new \WxPayConfig();
        $order = $wxpay->unifiedOrder($config, $input);
        $jsApiParameters = $tools->GetJsApiParameters($order);
        $this->jsonRet->jsonOutput(0, '成功', ['jsApiParameters' => $jsApiParameters]);
    }

    /**增加商品销量
     * @param $goods_id
     * @param $num
     */
    public function goods_sales($goods_id, $num)
    {
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]);

        //增加商品销量
        $goods_sales = $goods_info['goods_sales'] + $num;
        (new Goods())->updateGoods(['goods_id' => $goods_id, 'goods_sales' => $goods_sales]);
    }

    //模拟支付 正式去掉
    public function pay($order_id)
    {
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
        if ($order_info['payment_time'] > 0) {
            $this->jsonRet->jsonOutput(-1, '该订单已支付');
        }
        //修改订单状态
        $order_data['order_state'] = 30; //改状态为已付款
        $order_data['pay_state'] = 2; //改状态为已付款
        $order_data['payment_time'] = time(); //付款时间
        $order_data['payment_code'] = 'wechat'; //付款方式
        $order_data['pd_amount'] = $order_info['order_amount']; //付款金额
        $order_data['pay_sn'] = '1'; //付款单号
        $order_data['type'] = 0;
        (new Order())->updateOrderByCondition($order_data, ['order_id' => $order_info]);
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id], ['order_goods']);
        //积分任务 赠送购物积分
        $gwjf = (new Setting())->getSetting('gwjf');
        if ($gwjf > 0) {
            $member_info = (new Member())->getMemberInfo(['member_id' => $order_info['buyer_id']]);
            (new Member())->updateMember(['member_id' => $order_info['buyer_id'], 'member_points' => $member_info['member_points'] + $gwjf]);
            (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $gwjf, 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '购物任务',]);
            $this->send_member_msg('积分变更', '您购物获得积分：' . $gwjf . '，订单号：' . $order_info['order_sn']);
        }

        $order_goods_pay_price_arr = array_column($order_info['extend_order_goods'], 'order_goods_pay_price');
        $order_goods_pay_price_sum = array_sum($order_goods_pay_price_arr);
        //付款成功拆单 一种商品一个订单
        foreach ($order_info['extend_order_goods'] as $k => $v) {
            $goods_amount = $v['order_goods_price'] * $v['order_goods_num'];
            $order_coupon_price = round($order_info['order_coupon_price'] * $v['order_goods_pay_price'] / $order_goods_pay_price_sum, 2); //优惠券金额
            $shipping_fee = round($order_info['shipping_fee'] * $v['order_goods_pay_price'] / $order_goods_pay_price_sum, 2); //优惠券金额
            $order_amount = $v['order_goods_pay_price'] - $order_coupon_price + $shipping_fee;
            $promotion_total = $goods_amount - $order_amount;

            $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['order_goods_id']]);
            if ($goods_info['goods_type'] == 3) {
                $date['order_type'] = 2; //供应商
            } else {
                $date['order_type'] = 1; //自营
            }
            $date['buyer_address'] = $order_info['buyer_address']; //收货地址
            $date['shipping_fee'] = $order_info['shipping_fee']; //运费
            $date['regionId'] = $order_info['regionId'];
            $date['ma_phone'] = $order_info['ma_phone']; //收货人电话
            $date['ma_id'] = $order_info['ma_id']; //地址id
            $date['ma_true_name'] = $order_info['ma_true_name']; //收货人姓名
            $date['buyer_message'] = $order_info['buyer_message']; //买家留言
            $date['point_amount'] = ceil($order_amount); //赠送积分 为支付金额向上取整
            $date['order_sn'] = $order_info["order_sn"] . '-' . ($k + 1); //订单编号
            $date['buyer_id'] = $order_info['buyer_id']; //买家id

            $date['order_state'] = $order_info['order_state'];
            $date['pay_state'] = $order_info['pay_state'];
            $date['payment_time'] = $order_info['payment_time'];
            $date['payment_code'] = $order_info['payment_code'];
            $date['pay_sn'] = $order_info['pay_sn'];
            $date['fxs_id'] = $order_info['fxs_id'];
            $date['pid'] = $order_info['order_id'];

            $date['add_time'] = $order_info['add_time']; //订单生成时间
            $date['buyer_name'] = $order_info['buyer_name']; //买家昵称
            $date['goods_amount'] = $goods_amount; //商品总价格
            $date['order_amount'] = $order_amount; //订单总价格
            $date['pd_amount'] = $order_amount; //支付金额
            $date['ordertype'] = $order_info['ordertype']; //
            $date['promotion_total'] = $promotion_total;  //优惠金额
            $date['shipping_fee'] = $shipping_fee;  //运费
            $date['order_coupon_price'] = $order_coupon_price;  //优惠券金额
            $date['commission_amount'] = $order_amount * $goods_info['commission'] / 100;  //佣金
            $order_id = (new Order())->insertOrder($date); //添加订单

            //修改商品状态
            (new OrderGoods())->updateOrderGoods(['order_rec_id' => $v['order_rec_id'], 'order_id' => $order_id]);
            $this->goods_sales($v['order_goods_id'], $v['order_goods_num']); //增加销量
        }
        if ($order_info['ordertype'] == 2) {
            //拼团订单
            (new BulkList())->updateBulkList(['id' => $order_info['extend_order_goods'][0]['promotions_id'], 'state' => 1]);
            $bulk_list_info = (new BulkList())->getBulkListInfo(['id' => $order_info['extend_order_goods'][0]['promotions_id']]);
            $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $bulk_list_info['list_id']]);
            $state = 0;
            if ($bulk_start_info['need_num'] == $bulk_start_info['now_num']) {
                //满员 拼团成功
                $state = 1;
                //推送成功通知
                $bulk_list_list = (new BulkList())->getBulkListList(['list_id' => $bulk_list_info['list_id']]);
                $member_id_arr = array_column($bulk_list_list, 'member_id');
                $member_list = (new Member())->getMemberList(['in', 'member_id', $member_id_arr]);
                $member_anme_arr = $member_list ? array_column($member_list, 'member_name') : [];
                $members = implode('、', $member_anme_arr);
                foreach ($bulk_list_list as $k => $v) {
                    $member_info = (new Member())->getMemberInfo(['member_id' => $v['member_id']]);
                    $member_info['member_openid'] ? $re = $this->xxmb($member_info['member_openid'], 'gdQh5CpH9rcwh3kHXy6HZ_xon5sO1Jl7w3r9xL-DW1s', '亲爱的' . $order_info['buyer_name'] . '您好，你的拼团成功了！', $goods_info['goods_name'], floatval($order_data['pd_amount']), $members) : '';
                    $this->send_member_msg1($v['member_id'], '拼团成功', '您的商品【' . $goods_info['goods_name'] . '】拼团成功');
                }

            }
            (new BulkStart())->updateBulkStart(['list_id' => $bulk_list_info['list_id'], 'state' => $state]);
        }
    }

    /**支付回调
     * @throws \WxPayException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function actionNotify()
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
    private function orderquery($transaction_id)
    {
        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Api.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/lib/WxPay.Data.php");
        require_once(Yii::getAlias('@vendor') . "/wxpay/example/WxPay.Config.php");
        $input = new \WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);
        $config = new \WxPayConfig();
        $result = \WxPayApi::orderQuery($config, $input);

        if (array_key_exists("return_code", $result) && $result["return_code"] == "SUCCESS") {
            //修改订单状态
            $order_data['order_state'] = 30; //改状态为已付款
            $order_data['pay_state'] = 2; //改状态为已付款
            $order_data['payment_time'] = time(); //付款时间
            $order_data['payment_code'] = 'wechat'; //付款方式
            $order_data['pd_amount'] = $result["total_fee"] / 100; //付款金额
            $order_data['pay_sn'] = $result["transaction_id"]; //付款单号
            $order_data['type'] = 0;
            (new Order())->updateOrderByCondition($order_data, ['order_sn' => $result["out_trade_no"]]);


            $order_info = (new Order())->getOrderInfo(['order_sn' => $result["out_trade_no"]], ['order_goods']);

            //积分任务 赠送购物积分
            $gwjf = (new Setting())->getSetting('gwjf');
            if ($gwjf > 0) {
                $member_info = (new Member())->getMemberInfo(['member_id' => $order_info['buyer_id']]);
                (new Member())->updateMember(['member_id' => $order_info['buyer_id'], 'member_points' => $member_info['member_points'] + $gwjf]);
                (new MemberCoin())->insertMemberCoin1(['member_id' => $member_info['member_id'], 'coin_member_name' => $member_info['member_name'], 'coin_points' => $gwjf, 'coin_type' => 1, 'coin_addtime' => time(), 'coin_desc' => '购物任务',]);
                $this->send_member_msg('积分变更', '您购物获得积分：' . $gwjf . '，订单号：' . $order_info['order_sn']);
            }

            $order_goods_pay_price_arr = array_column($order_info['extend_order_goods'], 'order_goods_pay_price');
            $order_goods_pay_price_sum = array_sum($order_goods_pay_price_arr);
            //付款成功拆单 一种商品一个订单
            foreach ($order_info['extend_order_goods'] as $k => $v) {
                $goods_amount = $v['order_goods_price'] * $v['order_goods_num'];
                $order_coupon_price = round($order_info['order_coupon_price'] * $v['order_goods_pay_price'] / $order_goods_pay_price_sum, 2); //优惠券金额
                $shipping_fee = round($order_info['shipping_fee'] * $v['order_goods_pay_price'] / $order_goods_pay_price_sum, 2); //优惠券金额
                $order_amount = $v['order_goods_pay_price'] - $order_coupon_price + $shipping_fee;
                $promotion_total = $goods_amount - $order_amount;

                $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $v['order_goods_id']]);
                if ($goods_info['goods_type'] == 3) {
                    $date['order_type'] = 2; //供应商
                } else {
                    $date['order_type'] = 1; //自营
                }
                $date['buyer_address'] = $order_info['buyer_address']; //收货地址
                $date['shipping_fee'] = $order_info['shipping_fee']; //运费
                $date['regionId'] = $order_info['regionId'];
                $date['ma_phone'] = $order_info['ma_phone']; //收货人电话
                $date['ma_id'] = $order_info['ma_id']; //地址id
                $date['ma_true_name'] = $order_info['ma_true_name']; //收货人姓名
                $date['buyer_message'] = $order_info['buyer_message']; //买家留言
                $date['point_amount'] = ceil($order_amount); //赠送积分 为支付金额向上取整
                $date['order_sn'] = $order_info["order_sn"] . '-' . ($k + 1); //订单编号
                $date['buyer_id'] = $order_info['buyer_id']; //买家id

                $date['order_state'] = $order_info['order_state'];
                $date['pay_state'] = $order_info['pay_state'];
                $date['payment_time'] = $order_info['payment_time'];
                $date['payment_code'] = $order_info['payment_code'];
                $date['pay_sn'] = $order_info['pay_sn'];
                $date['fxs_id'] = $order_info['fxs_id'];
                $date['pid'] = $order_info['order_id'];

                $date['add_time'] = $order_info['add_time']; //订单生成时间
                $date['buyer_name'] = $order_info['buyer_name']; //买家昵称
                $date['goods_amount'] = $goods_amount; //商品总价格
                $date['order_amount'] = $order_amount; //订单总价格
                $date['pd_amount'] = $order_amount; //支付金额
                $date['ordertype'] = $order_info['ordertype']; //
                $date['promotion_total'] = $promotion_total;  //优惠金额
                $date['shipping_fee'] = $shipping_fee;  //运费
                $date['order_coupon_price'] = $order_coupon_price;  //优惠券金额
                $date['commission_amount'] = $order_amount * $goods_info['commission'] / 100;  //佣金
                $order_id = (new Order())->insertOrder($date); //添加订单

                //修改商品状态
                (new OrderGoods())->updateOrderGoods(['order_rec_id' => $v['order_rec_id'], 'order_id' => $order_id]);
                $this->goods_sales($v['order_goods_id'], $v['order_goods_num']); //增加销量
            }
            if ($order_info['ordertype'] == 2) {
                //拼团订单
                (new BulkList())->updateBulkList(['id' => $order_info['extend_order_goods'][0]['promotions_id'], 'state' => 1]);
                $bulk_list_info = (new BulkList())->getBulkListInfo(['id' => $order_info['extend_order_goods'][0]['promotions_id']]);
                $bulk_start_info = (new BulkStart())->getBulkStartInfo(['list_id' => $bulk_list_info['list_id']]);
                $state = 0;
                if ($bulk_start_info['need_num'] == $bulk_start_info['now_num']) {
                    //满员 拼团成功
                    $state = 1;
                    //推送成功通知
                    $bulk_list_list = (new BulkList())->getBulkListList(['list_id' => $bulk_list_info['list_id']]);
                    $member_id_arr = array_column($bulk_list_list, 'member_id');
                    $member_list = (new Member())->getMemberList(['in', 'member_id', $member_id_arr]);
                    $member_anme_arr = $member_list ? array_column($member_list, 'member_name') : [];
                    $members = implode('、', $member_anme_arr);
                    foreach ($bulk_list_list as $k => $v) {
                        $member_info = (new Member())->getMemberInfo(['member_id' => $v['member_id']]);
                        $member_info['member_openid'] ? $re = $this->xxmb($member_info['member_openid'], 'gdQh5CpH9rcwh3kHXy6HZ_xon5sO1Jl7w3r9xL-DW1s', '亲爱的' . $order_info['buyer_name'] . '您好，你的拼团成功了！', $goods_info['goods_name'], floatval($order_data['pd_amount']), $members) : '';
                        $this->send_member_msg1($v['member_id'], '拼团成功', '您的商品【' . $goods_info['goods_name'] . '】拼团成功');
                    }
                }
                (new BulkStart())->updateBulkStart(['list_id' => $bulk_list_info['list_id'], 'state' => $state]);
            }

            //通知微信服务器
            echo exit('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>');
        }
        return false;
    }

    /**物流查询接口
     * @param $order_id 订单id
     */
    public function actionGetpost($order_id)
    {
        $member_info = $this->user_info;
        if (!$member_info) {
            $this->jsonRet->jsonOutput(-400, '请登录');
        }
        $order_info = (new Order())->getOrderInfo(['order_id' => $order_id]);
        if (!$order_info['shipping_code']) {
            $this->jsonRet->jsonOutput(-1, '该订单还未发货');
        }
        if ($order_info['order_type'] == 3) {
            $result = $this->gyswl($order_info['orderSn'], $order_info['dlyCode'], $order_info['shipping_code']);
            $this->jsonRet->jsonOutput(0, '查询成功', $result['data']);
        } else {
            $ex_info = (new Express)->getExpressInfo(['id' => $order_info['shipping_express_id']]);
            $result = $this->getetOrderTracesByJson($ex_info['e_code'], $order_info['shipping_code']);
            $result = json_decode($result, true);
            if ($result['Success']) {
                $this->jsonRet->jsonOutput(0, '查询成功', $result['Traces']);
            } else {
                $this->jsonRet->jsonOutput(-2, '查询失败');
            }
        }
    }

    /**查询供应商物流
     * @return mixed
     */
    public function gyswl($orderSn, $dlyCode, $shipNo)
    {
        $config = $this->getconfig();
        $url = $this->gethttp() . 'api/v2/order/orderKuaidi?';
        $params['orderSn'] = $orderSn;
        $params['dlyCode'] = $dlyCode;
        $params['shipNo'] = $shipNo;
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
            $this->actionGetwl($orderSn, $dlyCode, $shipNo);
        }
    }



    /*****************************快递**********************************************/
    /**
     * Json方式 查询订单物流轨迹
     */
    public function getetOrderTracesByJson($ShipperCode, $LogisticCode)
    {
        $requestData = "{'OrderCode':'','ShipperCode':\"$ShipperCode\",'LogisticCode':\"$LogisticCode\"}";
        $datas = array(
            'EBusinessID' => '1489867',
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData),
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, '49ae885c-71cb-4191-9205-3ce6c27c0ec3');
        $result = $this->sendPost('http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx', $datas);

        //根据公司业务处理返回的信息......
        return $result;
    }

    /**
     *  post提交数据
     * @param  string $url 请求Url
     * @param  array $datas 提交的数据
     * @return url响应返回的html
     */
    private function sendPost($url, $datas)
    {
        $temps = array();
        foreach ($datas as $key => $value) {
            $temps[] = sprintf('%s=%s', $key, $value);
        }
        $post_data = implode('&', $temps);
        $url_info = parse_url($url);
        if (empty($url_info['port'])) {
            $url_info['port'] = 80;
        }
        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader .= "Host:" . $url_info['host'] . "\r\n";
        $httpheader .= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader .= "Content-Length:" . strlen($post_data) . "\r\n";
        $httpheader .= "Connection:close\r\n\r\n";
        $httpheader .= $post_data;
        $fd = fsockopen($url_info['host'], $url_info['port']);
        fwrite($fd, $httpheader);
        $gets = "";
        $headerFlag = true;
        while (!feof($fd)) {
            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                break;
            }
        }
        while (!feof($fd)) {
            $gets .= fread($fd, 128);
        }
        fclose($fd);

        return $gets;
    }

    /**
     * 电商Sign签名生成
     * @param data 内容
     * @param appkey Appkey
     * @return DataSign签名
     */
    private function encrypt($data, $appkey)
    {
        return urlencode(base64_encode(md5($data . $appkey)));
    }

    function getFirstCharter($str)
    {
        if (empty($str)) {
            return '';
        }

        $fchar = ord($str{0});

        if ($fchar >= ord('A') && $fchar <= ord('z')) return strtoupper($str{0});

        $s1 = iconv('UTF-8', 'gb2312', $str);

        $s2 = iconv('gb2312', 'UTF-8', $s1);

        $s = $s2 == $str ? $s1 : $str;

        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;

        if ($asc >= -20319 && $asc <= -20284) return 'A';

        if ($asc >= -20283 && $asc <= -19776) return 'B';

        if ($asc >= -19775 && $asc <= -19219) return 'C';

        if ($asc >= -19218 && $asc <= -18711) return 'D';

        if ($asc >= -18710 && $asc <= -18527) return 'E';

        if ($asc >= -18526 && $asc <= -18240) return 'F';

        if ($asc >= -18239 && $asc <= -17923) return 'G';

        if ($asc >= -17922 && $asc <= -17418) return 'H';

        if ($asc >= -17417 && $asc <= -16475) return 'J';

        if ($asc >= -16474 && $asc <= -16213) return 'K';

        if ($asc >= -16212 && $asc <= -15641) return 'L';

        if ($asc >= -15640 && $asc <= -15166) return 'M';

        if ($asc >= -15165 && $asc <= -14923) return 'N';

        if ($asc >= -14922 && $asc <= -14915) return 'O';

        if ($asc >= -14914 && $asc <= -14631) return 'P';

        if ($asc >= -14630 && $asc <= -14150) return 'Q';

        if ($asc >= -14149 && $asc <= -14091) return 'R';

        if ($asc >= -14090 && $asc <= -13319) return 'S';

        if ($asc >= -13318 && $asc <= -12839) return 'T';

        if ($asc >= -12838 && $asc <= -12557) return 'W';

        if ($asc >= -12556 && $asc <= -11848) return 'X';

        if ($asc >= -11847 && $asc <= -11056) return 'Y';

        if ($asc >= -11055 && $asc <= -10247) return 'Z';

        return "#";
    }

    private function formatTime($time) {
        $day = date('d');
        if ($day==date('d',$time)){
            return date('H:i',$time);
        } elseif ($day-date('d',$time)==1){
            return '一天前';
        }else{
            return date('Y/m/d',$time);
        }
    }

    public function actionTest()
    {
        echo 555;
    }

    public function actionFximg($goods_id)
    {
        $width = 500;
        $height = 400;
        $font = dirname(Yii::$app->BasePath) . '/frontend/web/fonts/hyzyj.ttf';
        $z = 15;
        $goods_info = (new Goods())->getGoodsInfo(['A.goods_id' => $goods_id]);
        $str1 = '￥' . $goods_info['goods_price'];
        $str1 = $this->to_entities($str1);
        $str2 = '已售' . $goods_info['goods_sales'] . '件';
        $str2 = $this->to_entities($str2);
        $img = $goods_info['goods_pic'];
        $img = SysHelper::getImage($img, 0, 0, 0, [0, 0], 1);
        $src_im = imagecreatefromjpeg($img);
        $b = imagettfbbox($z, 0, $font, $str2);
        $font_width = abs($b[2] - $b[0]);
        header("Content-type: image/png; charset=utf-8"); //显示图片需要
        $im = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $red = imagecolorallocate($im, 255, 0, 0);
        $gray = imagecolorallocate($im, 150, 150, 150);
        imagefill($im, 0, 0, $white);
        imagettftext($im, 20, 0, 50, 350, $red, $font, $str1);
        imagettftext($im, 20, 0, 51, 350, $red, $font, $str1);
        $str2_w = 500 - 50 - $font_width;
        imagettftext($im, $z, 0, $str2_w, 350, $gray, $font, $str2);
        $img_arr = getimagesize($img);
        imagecopyresampled($im, $src_im, 150, 50, 0, 0, 200, 200, $img_arr[0], $img_arr[1]);
        $new_file_name = "../../data/uploads/goods_id_" . $goods_id . '.' . 'png';
        imagepng($im, $new_file_name); //显示图片
        imagedestroy($im);
        return SysHelper::getImage("/data/uploads/goods_id_" . $goods_id . '.' . 'png', 0, 0, 0, [0, 0], 1);
    }


    public function to_entities($string)
    {
        $len = strlen($string);
        $buf = "";
        for ($i = 0; $i < $len; $i++) {
            if (ord($string[$i]) <= 127) {
                $buf .= $string[$i];
            } else if (ord($string[$i]) < 192) {
                //unexpected 2nd, 3rd or 4th byte
                $buf .= "&#xfffd";
            } else if (ord($string[$i]) < 224) {
                //first byte of 2-byte seq
                $buf .= sprintf("&#%d;",
                    ((ord($string[$i + 0]) & 31) << 6) +
                    (ord($string[$i + 1]) & 63)
                );
                $i += 1;
            } else if (ord($string[$i]) < 240) {
                //first byte of 3-byte seq
                $buf .= sprintf("&#%d;",
                    ((ord($string[$i + 0]) & 15) << 12) +
                    ((ord($string[$i + 1]) & 63) << 6) +
                    (ord($string[$i + 2]) & 63)
                );
                $i += 2;
            } else {
                //first byte of 4-byte seq
                $buf .= sprintf("&#%d;",
                    ((ord($string[$i + 0]) & 7) << 18) +
                    ((ord($string[$i + 1]) & 63) << 12) +
                    ((ord($string[$i + 2]) & 63) << 6) +
                    (ord($string[$i + 3]) & 63)
                );
                $i += 3;
            }
        }
        return $buf;
    }

}