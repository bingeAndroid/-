<?php
/**
 * Created by qbt.
 * User: lihz
 * Date: 2019/1/31
 * Time: 17:39
 */

namespace api\modules\v1\controllers;
use system\helpers\SysHelper;
use system\services\Article;
use Yii;

class ArticleController extends BaseController
{
    /**
     * 获取帮助文章
     */
   public function actionGetHelpList()
   {
       $list = (new Article())->getArticleListByType(['article_parent_id'=>11]);
       $this->jsonRet->jsonOutput(0, '', $list);
   }
}