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
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const LOGO = 'payment/paytrail/logo';

    /**
     * @var Resolver
     */
    private $localeResolver;
    /**
     * @var TaxHelper
     */
    private $taxHelper;
    /**
     * @var Config
     */
    private $gatewayConfig;

    /** @var PaytrailLogger */
    private $paytrailLogger;

    /**
     * @param Context $context
     * @param Resolver $localeResolver
     * @param TaxHelper $taxHelper
     * @param PaytrailLogger $paytrailLogger
     * @param Config $gatewayConfig
     */
    public function __construct(
        Context $context,
        Resolver $localeResolver,
        TaxHelper $taxHelper,
        PaytrailLogger $paytrailLogger,
        Config $gatewayConfig
    ) {
        $this->localeResolver = $localeResolver;
        $this->taxHelper = $taxHelper;
        $this->paytrailLogger = $paytrailLogger;
        $this->gatewayConfig = $gatewayConfig;
        parent::__construct($context);
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
     * @param string $incrementId
     * @return string
     */
    public function calculateOrderReferenceNumber($incrementId)
    {
        $prefixedId = '1' . $incrementId;

        $sum = 0;
        $length = strlen($prefixedId);

        for ($i = 0; $i < $length; ++$i) {
            $sum += substr($prefixedId, -1 - $i, 1) * [7, 3, 1][$i % 3];
        }
        $num = (10 - $sum % 10) % 10;
        $referenceNum = $prefixedId . $num;

        return trim(chunk_split($referenceNum, 5, ' '));
    }

    /**
     * Get order increment id from checkout reference number
     * @param string $reference
     * @return string|null
     */
    public function getIdFromOrderReferenceNumber($reference)
    {
        return preg_replace('/\s+/', '', substr($reference, 1, -1));
    }

    /**
     * @param string $logType
     * @param string $level
     * @param mixed $data
     *
     * @deprecated implementation replaced by dedicated logger class
     * @see \Paytrail\PaymentService\Logger\PaytrailLogger::logData
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
     * @return string reference number
     */
    public function getReference($order)
    {
        return $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->calculateOrderReferenceNumber($order->getIncrementId())
            : $order->getIncrementId();
    }
}
