<?php

use yii\helpers\Url;
use yii\helpers\Html;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>游购会</title>
</head>
<body style="margin: 0">
<img src="<?= Url::to('@web/../../frontend/web/img/fxewm.png') ?>" alt="" style="width: 100%">
<div id="code" style="display: none"></div>
<div style="width: 300px;height: 300px;margin:0 auto;">
    <img id="qrcodeImg" style="border-radius: 10px;">
</div>
</body>
<?= Html::jsFile('@web/../../frontend/web/js/jquery-1.8.3.min.js') ?>
<?= Html::jsFile('@web/../../frontend/web/js/store/jquery.qrcode.min.js') ?>
<script>
    function code() {
        var qrcode = $('#code').qrcode({
            render: "canvas", //也可以替换为table
            width: 300,  //宽
            height: 300, //高
            text: '<?= $url ?>'  //可以通过ajax请求动态设置
        });
        var canvas = qrcode.find('canvas').get(0);
        $('#qrcodeImg').attr('src', canvas.toDataURL('image/jpg'));

    };
    code();
</script>
</html>