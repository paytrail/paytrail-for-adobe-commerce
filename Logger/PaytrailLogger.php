<?php
namespace Paytrail\PaymentService\Logger;

use Magento\Framework\Serialize\SerializerInterface;
use Paytrail\PaymentService\Gateway\Config\Config;

class PaytrailLogger extends \Magento\Framework\Logger\Monolog
{
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var array
     */
    private $debugActive = [];

    public function __construct(
        $name,
        SerializerInterface $serializer,
        private Config $gatewayConfig,
        array $handlers = [],
        array $processors = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($name, $handlers, $processors);
    }

    /**
     * @param $level
     * @param $message
     */
    public function logData($level, $message)
    {
        if ($message instanceof \Throwable) {
            $message = $message->getMessage();
        }

        if (is_array($message) || is_object($message)) {
            $message = $this->serializer->serialize($message);
        }

        $this->log(
            $level,
            $message
        );
    }

    /**
     * @param $type
     * @param $message
     */
    public function debugLog($type, $message)
    {
        if (!$this->isDebugActive($type)) {
            return;
        }

        $level = $this->resolveLogLevel($type);
        $this->logData($level, $message);
    }

    /**
     * @param string $logType
     * @return string
     */
    public function resolveLogLevel(string $logType) : string
    {
        $level = \Monolog\Logger::DEBUG;

        if ($logType == 'request') {
            $level = \Monolog\Logger::INFO;
        } elseif ($logType == 'response') {
            $level = \Monolog\Logger::NOTICE;
        }

        return $level;
    }

    /**
     * @param $type
     * @return int
     */
    private function isDebugActive($type)
    {
        if (!isset($this->debugActive[$type])) {
            $this->debugActive[$type] = $type == 'request'
                ? $this->gatewayConfig->getRequestLog()
                : $this->gatewayConfig->getResponseLog();
        }

        return $this->debugActive[$type];
    }

    /**
     * Log data to file
     *
     * @param string $logType
     * @param string $level
     * @param mixed  $data
     *
     * @deprecated   implementation replaced by dedicated logger class
     * @see          \Paytrail\PaymentService\Logger\PaytrailLogger::logData
     */
    public function logCheckoutData($logType, $level, $data): void
    {
        if ($level !== 'error' &&
            (($logType === 'request' && $this->gatewayConfig->getRequestLog() == false)
                || ($logType === 'response' && $this->gatewayConfig->getResponseLog() == false))
        ) {
            return;
        }

        $level = $level == 'error' ? $level : $this->resolveLogLevel($logType);
        $this->logData($level, $data);
    }
}
