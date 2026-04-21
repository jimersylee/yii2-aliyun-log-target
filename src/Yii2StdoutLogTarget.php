<?php

namespace Jimersylee\Yii2AliyunLogTarget;

use DateTime;
use yii\log\Logger;
use yii\log\Target;

/**
 * 输出到stderr标准输出, 用于 Web/FPM 模式下将日志写入容器日志, 方便k8s直接采集
 */
class Yii2StdoutLogTarget extends Target
{
    /**
     * @var string|null 存储 CLI 模式下生成的 traceId
     */
    private static $cliTraceId = null;

    /**
     * @var resource|null 日志输出流，Web/FPM 下写到 stderr 才能进入容器日志
     */
    private static $stream = null;

    public $enableTrace = true;

    public function export()
    {
        foreach ($this->messages as $message) {
            $logMap = [];
            $msg = $message[0];
            if (!is_string($msg)) {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($msg === false) {
                    $msg = '[unserializable log message]';
                }
            }
            $logMap['message'] = $msg;
            $logMap['level'] = Logger::getLevelName($message[1]);
            $millis = (int) round($message[3] * 1000);
            $datetime = new DateTime();
            $timestamp = floor($millis / 1000);
            $datetime->setTimestamp($timestamp);
            $logMap['time'] = $datetime->format('Y-m-d H:i:s') . '.' . str_pad((string) ($millis % 1000), 3, '0', STR_PAD_LEFT);
            if ($this->enableTrace) {
                $logMap['traceId'] = $this->getTraceId();
            }
            $line = json_encode($logMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                $line = '{"message":"[json encode failed]"}';
            }
            $line .= PHP_EOL;

            $stream = $this->getStream();
            if ($stream !== null) {
                fwrite($stream, $line);
                fflush($stream);
                continue;
            }
            error_log(rtrim($line, PHP_EOL));
        }
    }

    /**
     * Web/FPM 环境优先写到 stderr；CLI 也统一写到 stderr，方便 docker logs 直接查看。
     * @return resource|null
     */
    private function getStream()
    {
        if (self::$stream !== null) {
            return self::$stream;
        }

        $stream = fopen('php://stderr', 'wb');
        if ($stream === false) {
            return null;
        }

        self::$stream = $stream;
        return self::$stream;
    }

    /**
     * Obtain the traceId in the request. This is obtained from the http request, and there is no traceId in cli mode
     * @return string
     */
    public function getTraceId(): string
    {
        // CLI mode: auto-generate traceId and reuse it during the same command execution
        if (PHP_SAPI === 'cli') {
            if (self::$cliTraceId === null) {
                self::$cliTraceId = $this->generateTraceId();
            }
            return self::$cliTraceId;
        }

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

        if (self::$cliTraceId === null) {
            self::$cliTraceId = $this->generateTraceId();
        }
        return self::$cliTraceId;


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
