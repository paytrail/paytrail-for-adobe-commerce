<?php

namespace Paytrail\PaymentService\Gateway\Request;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Model\PaymentMethod\PaymentMethodDataProvider;
use Psr\Log\LoggerInterface;

class PaymentMethodProviderDataBuilder implements BuilderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Data
     */
    private $paytrailHelper;

    /**
     * @var LoggerInterface
     */
    private $log;
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Data $paytrailHelper
     * @param SubjectReader $subjectReader
     * @param LoggerInterface $log
     * @param PaymentMethodDataProvider $paymentMethodDataProvider
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Data $paytrailHelper,
        SubjectReader $subjectReader,
        LoggerInterface $log,
        private readonly PaymentMethodDataProvider $paymentMethodDataProvider
    ) {
        $this->paytrailHelper = $paytrailHelper;
        $this->storeManager = $storeManager;
        $this->log = $log;
        $this->subjectReader = $subjectReader;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws LocalizedException
     */
    public function build(array $buildSubject)
    {
        $amount = $this->subjectReader->readAmount($buildSubject);
        $errMsg = null;

        if ($amount <= 0) {
            $errMsg = 'Invalid amount for refund.';
        }

        if (isset($errMsg)) {
            $this->log->error($errMsg);
            $this->paytrailHelper->processError($errMsg);
        }

        return [
            'amount' => round($amount * 100),
            'locale' => $this->paymentMethodDataProvider->getStoreLocaleForPaymentProvider(),
            'groups' => []
        ];
    }
}
