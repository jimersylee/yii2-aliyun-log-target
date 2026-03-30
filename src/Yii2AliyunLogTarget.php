<?php

namespace Jimersylee\Yii2AliyunLogTarget;

use Aliyun_Log_Client;
use Aliyun_Log_Exception;
use Aliyun_Log_LoggerFactory;
use Aliyun_Log_SimpleLogger;
use DateTime;
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
    public $enableTrace = false;

    public $topic = 'log';
    /**
     * @var Aliyun_Log_SimpleLogger
     */
    private $logger;

    /**
     * @var string|null 存储 CLI 模式下生成的 traceId
     */
    private static $cliTraceId = null;

    /**
     * @throws InvalidConfigException
     * @throws Aliyun_Log_Exception
     * @throws \Exception
     */
    public function init()
    {
        if (!isset($this->accessKeyId)) {
            throw new InvalidConfigException(Yii::t('app', 'please configure your accesskeyid'));
        }
        if (!isset($this->accessKeySecret)) {
            throw new InvalidConfigException(Yii::t('app', 'please configure your accesskeysecret'));
        }
        $client = new Aliyun_Log_Client($this->endpoint, $this->accessKeyId, $this->accessKeySecret);
        $this->logger = Aliyun_Log_LoggerFactory::getLogger($client, $this->project, $this->logstore, $this->topic);
        parent::init();
    }


    public function export()
    {
        // log format
        //     *   [0] => message (mixed, can be a string or some complex data, such as an exception object)
        //     *   [1] => level (integer)
        //     *   [2] => category (string)
        //     *   [3] => timestamp (float, obtained by microtime(true))
        //     *   [4] => traces (array, debug backtrace, contains the application code call stacks)
        //     *   [5] => memory usage in bytes (int, obtained by memory_get_usage()), available since version 2.0.11.
        //     * ]
        foreach ($this->messages as $message) {
            $msg = $message[0];
            if (!is_string($msg)) {
                $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
            }
            $logMap['message'] = $msg;
            $logMap['level'] = Logger::getLevelName($message[1]);
            $millis= round($message[3] * 1000);
            $datetime = new DateTime();
            $timestamp = floor($millis / 1000);
            $datetime->setTimestamp($timestamp);
            $logMap['time']=$datetime->format('Y-m-d H:i:s') . '.' . str_pad($millis % 1000, 3, '0', STR_PAD_LEFT);
            if ($this->enableTrace) {
                $logMap['traceId'] = $this->getTraceId();
            }
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

    /**
     * Obtain the traceId in the request. This is obtained from the http request, and there is no traceId in cli mode
     * @return string
     */
    public function getTraceId(): string
    {
        // check 'traceId' or 'trace_id' header key
        $keys_to_check = [
            'HTTP_TRACE_ID',
            'HTTP_TRACEID',
        ];
        foreach ($keys_to_check as $key) {
            if (isset($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }
        //  traceparent header
        $traceParent = $_SERVER['HTTP_TRACEPARENT'] ?? '';
        if (!empty($traceParent)) {
            // traceparent format: 00-TRACE_ID-SPAN_ID-00
            if (preg_match('/^[\da-fA-F]{2}-([\da-fA-F]{32})-([\da-fA-F]{16})-/', $traceParent, $matches)) {
                return $matches[1];
            }
        }
        // CLI mode: auto-generate traceId and reuse it during the same command execution
        if (PHP_SAPI === 'cli') {
            if (self::$cliTraceId === null) {
                self::$cliTraceId = $this->generateTraceId();
            }
            return self::$cliTraceId;
        }
        return "";
    }

    /**
     * Generate a unique traceId for CLI mode
     * @return string
     */
    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}