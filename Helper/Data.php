<?php

namespace Paytrail\PaymentService\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Sales\Model\Order;
use Magento\Tax\Helper\Data as TaxHelper;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Exceptions\TransactionSuccessException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;

/**
 * Class Data
 */
class Data
{
    public const LOGO = 'payment/paytrail/logo';
    
    private Resolver $localeResolver;
    
    private TaxHelper $taxHelper;
    
    private Config $gatewayConfig;
    
    private PaytrailLogger $paytrailLogger;

    /**
     * @param Context        $context
     * @param Resolver       $localeResolver
     * @param TaxHelper      $taxHelper
     * @param PaytrailLogger $paytrailLogger
     * @param Config         $gatewayConfig
     */
    public function __construct(
        Resolver $localeResolver,
        TaxHelper $taxHelper,
        PaytrailLogger $paytrailLogger,
        Config $gatewayConfig
    ) {
        $this->localeResolver = $localeResolver;
        $this->taxHelper      = $taxHelper;
        $this->paytrailLogger = $paytrailLogger;
        $this->gatewayConfig  = $gatewayConfig;
    }

    /**
     * @return array
     */
    public function getValidAlgorithms()
    {
        return ["sha256", "sha512"];
    }

    /**
     * @return string
     */
    public function getStoreLocaleForPaymentProvider()
    {
        $locale = 'EN';
        if ($this->localeResolver->getLocale() === 'fi_FI') {
            $locale = 'FI';
        }
        if ($this->localeResolver->getLocale() === 'sv_SE') {
            $locale = 'SV';
        }

        return $locale;
    }

    /**
     * Calculate Finnish reference number from order increment id
     * according to Finnish reference number algorithm
     * if increment id is not numeric - letters will be converted to numbers -> (ord($letter) % 10)
     *
     * @param string $incrementId
     *
     * @return string
     */
    public function calculateOrderReferenceNumber(string $incrementId): string
    {
        $prefixedId    = '1' . $incrementId;
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

        return $referenceNum;
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
     * @param string $logType
     * @param string $level
     * @param mixed  $data
     *
     * @deprecated implementation replaced by dedicated logger class
     * @see        \Paytrail\PaymentService\Logger\PaytrailLogger::logData
     */
    public function logCheckoutData($logType, $level, $data)
    {
        if (
            $level !== 'error' &&
            (($logType === 'request' && $this->gatewayConfig->getRequestLog() == false)
                || ($logType === 'response' && $this->gatewayConfig->getResponseLog() == false))
        ) {
            return;
        }

        $level = $level == 'error' ? $level : $this->paytrailLogger->resolveLogLevel($logType);
        $this->paytrailLogger->logData($level, $data);
    }

    /**
     * @param $errorMessage
     *
     * @throws CheckoutException
     */
    public function processError($errorMessage)
    {
        $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $errorMessage);
        throw new CheckoutException(__($errorMessage));
    }

    /**
     * @throws TransactionSuccessException
     */
    public function processSuccess()
    {
        throw new TransactionSuccessException(__('Success'));
    }

    /**
     * @param Order $order
     *
     * @return string reference number
     */
    public function getReference($order)
    {
        return $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->calculateOrderReferenceNumber($order->getIncrementId())
            : $order->getIncrementId();
    }
}
