<?php

namespace Paytrail\PaymentService\Helper;

use Magento\Framework\Locale\Resolver;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Exceptions\TransactionSuccessException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;

/**
 * Class Data
 * Helper to get data from config
 */
class Data
{
    public const LOGO = 'payment/paytrail/logo';

    /**
     * @var Resolver
     */
    private Resolver $localeResolver;

    /**
     * @var Config
     */
    private Config $gatewayConfig;

    /**
     * @var PaytrailLogger
     */
    private PaytrailLogger $paytrailLogger;

    /**
     * @param \Magento\Framework\Locale\Resolver             $localeResolver
     * @param \Paytrail\PaymentService\Logger\PaytrailLogger $paytrailLogger
     * @param \Paytrail\PaymentService\Gateway\Config\Config $gatewayConfig
     */
    public function __construct(
        Resolver $localeResolver,
        PaytrailLogger $paytrailLogger,
        Config $gatewayConfig
    ) {
        $this->localeResolver = $localeResolver;
        $this->paytrailLogger = $paytrailLogger;
        $this->gatewayConfig  = $gatewayConfig;
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

        $level = $level == 'error' ? $level : $this->paytrailLogger->resolveLogLevel($logType);
        $this->paytrailLogger->logData($level, $data);
    }
    
    /**
     * Process success
     *
     * @throws TransactionSuccessException
     */
    public function processSuccess(): void
    {
        throw new TransactionSuccessException(__('Success'));
    }
}
