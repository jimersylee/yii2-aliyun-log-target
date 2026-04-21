<?php

use Jimersylee\Yii2AliyunLogTarget\Yii2StdoutLogTarget;

require "../vendor/autoload.php";


$yii2StdoutLogTarget = new Yii2StdoutLogTarget();
$yii2StdoutLogTarget->init();
$yii2StdoutLogTarget->export();