<?php

require "../vendor/autoload.php";

use Jimersylee\Yii2AliyunLogTarget\Yii2AliyunLogTarget;


$test=new Yii2AliyunLogTarget();
$test->init();
$test->export();
