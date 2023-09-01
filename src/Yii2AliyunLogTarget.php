<?php

namespace Jimersylee\Yii2AliyunLogTarget;

use Aliyun_Log_SimpleLogger;
use yii\base\InvalidConfigException;
use yii\log\Logger;
use yii\log\Target;
use Yii;

class Yii2AliyunLogTarget extends Target
{
    public $endpoint = 'cn-shenzhen.sls.aliyuncs.com';
    public $accessKeyId = 'your_accesskeyid';
    public $accessKeySecret = 'your_accesskeysecret';
    public $project = 'your_project';
    public $logstore = 'your_logstore';

    public $topic = 'log';
    /**
     * @var Aliyun_Log_SimpleLogger
     */
    private $logger;

    public function init()
    {
        if (!isset($this->accessKeyId)) {
            throw new InvalidConfigException(Yii::t('app', 'please configure your accesskeyid'));
        }
        if (!isset($this->accessKeySecret)) {
            throw new InvalidConfigException(Yii::t('app', 'please configure your accesskeysecret'));
        }
        // var_dump($this->endpoint, $this->accessKeyId, $this->accessKeySecret, $this->project, $this->logstore,$this->topic);
        $client = new \Aliyun_Log_Client($this->endpoint, $this->accessKeyId, $this->accessKeySecret);
        $this->logger = \Aliyun_Log_LoggerFactory::getLogger($client, $this->project, $this->logstore, $this->topic);
        parent::init();
    }


    public function export()
    {
        //
        //     *   [0] => message (mixed, can be a string or some complex data, such as an exception object)
        //     *   [1] => level (integer)
        //     *   [2] => category (string)
        //     *   [3] => timestamp (float, obtained by microtime(true))
        //     *   [4] => traces (array, debug backtrace, contains the application code call stacks)
        //     *   [5] => memory usage in bytes (int, obtained by memory_get_usage()), available since version 2.0.11.
        //     * ]

        foreach ($this->messages as $message) {
            $logMap['message'] = $message[0];
            $logMap['level'] = Logger::getLevelName($message[1]);
           // $logMap['log'] = $message[4];
            switch ($message[1]) {
                case  Logger::LEVEL_ERROR:
                    $this->logger->errorArray($logMap);
                    break;
                case  Logger::LEVEL_WARNING:
                    $this->logger->warnArray($logMap);
                    break;
                case  Logger::LEVEL_INFO:
                    $this->logger->infoArray($logMap);
                    break;
                case  Logger::LEVEL_TRACE:
                    $this->logger->debugArray($logMap);
                    break;
                default:
                    $this->logger->infoArray($logMap);
            }

        }
        $this->logger->logFlush();

    }


}