<?php

namespace Paytrail\PaymentService\Model\Ui\DataProvider;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Psr\Log\LoggerInterface;

class PaymentProvidersData
{
    /**
     * ConfigProvider constructor
     *
     * @param paytrailHelper $paytrailHelper
     * @param Session $checkoutSession
     * @param LoggerInterface $log
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        private paytrailHelper $paytrailHelper,
        private Session $checkoutSession,
        private LoggerInterface $log,
        private CommandManagerPoolInterface $commandManagerPool
    ) {
    }

    /**
     * Get all payment methods and groups with order total value
     *
     * @return mixed|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllPaymentMethods()
    {
        $orderValue = $this->checkoutSession->getQuote()->getGrandTotal();

        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode(
            'method_provider',
            null,
            ['amount' => $orderValue]
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->log->error(
                'Error occurred during providing payment methods: '
                . $errorMsg
            );
            $this->paytrailHelper->processError($errorMsg);
        }

        return $response["data"];
    }
}
