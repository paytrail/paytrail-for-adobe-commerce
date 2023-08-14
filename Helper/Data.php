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
     * Calculate Finnish reference number from order increment id
     * according to Finnish reference number algorithm
     * if increment id is not numeric - letters will be converted to numbers -> (ord($letter) % 10)
     *
     * @param string $incrementId
     *
     * @return string
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    public function calculateOrderReferenceNumber(string $incrementId): string
    {
        $prefixedId    = ($incrementId[0] == 0 || !is_numeric($incrementId[0]))
            ? '1' . $incrementId
            : $incrementId;
        $newPrefixedId = '';
        $sum           = 0;
        $length        = strlen($prefixedId);

        for ($i = 0; $i < $length; ++$i) {
            $substr        = substr($prefixedId, -1 - $i, 1);
            $numSubstring  = is_numeric($substr) ? (int)$substr : (ord($substr) % 10);
            $newPrefixedId = $numSubstring . $newPrefixedId;
            $sum           += $numSubstring * [7, 3, 1][$i % 3];
        }
        $num          = (10 - $sum % 10) % 10;
        $referenceNum = $newPrefixedId . $num;

        if ($referenceNum > 9999999999999999999) {
            throw new CheckoutException('Order reference number is too long');
        }

        return trim(chunk_split($referenceNum, 5, ' '));
    }

    /**
     * Get order increment id from checkout reference number
     *
     * @param string $reference
     *
     * @return string|null
     */
    public function getIdFromOrderReferenceNumber($reference)
    {
        return preg_replace('/\s+/', '', substr($reference, 1, -1));
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
     * Process error
     *
     * @param string $errorMessage
     *
     * @throws CheckoutException
     */
    public function processError($errorMessage): void
    {
        $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $errorMessage);
        throw new CheckoutException(__($errorMessage));
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
