<?php

namespace Paytrail\PaymentService\Model\PaymentMethod;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Monolog\Logger;
use Paytrail\PaymentService\Logger\PaytrailLogger;

class OrderPaymentMethodData
{
    public const SELECTED_PAYMENT_METHOD_CODE = 'selected_payment_method';
    public const METHOD_TITLE_CODE = 'method_title';
    private const CREDIT_CARD_VALUE = 'creditcard';

    /**
     * OrderPaymentMethodData constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param PaytrailLogger $paytrailLogger
     */
    public function __construct(
        private OrderRepositoryInterface        $orderRepository,
        private PaymentTokenManagementInterface $paymentTokenManagement,
        private PaytrailLogger                  $paytrailLogger
    ) {
    }

    /**
     * Sets selected method to order payment additional_data.
     *
     * @param Order $order
     * @param string $selectedPaymentMethod
     * @return void
     */
    public function setSelectedPaymentMethodData($order, $selectedPaymentMethod): void
    {
        try {
            $order->getPayment()->setAdditionalInformation(self::SELECTED_PAYMENT_METHOD_CODE, $selectedPaymentMethod);

            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->paytrailLogger->logData(
                Logger::ERROR, 'Error setting selected payment method data: '
                . $e->getMessage()
            );
        }
    }

    /**
     * Sets selected card details to order payment additional_data.
     *
     * @param Order $order
     * @param string $selectedTokenId
     * @return void
     */
    public function setSelectedCardTokenData($order, $selectedTokenId): void
    {
        try {
            $cardDetails = $this->paymentTokenManagement->getByPublicHash($selectedTokenId, $order->getCustomerId());

            if (isset($cardDetails) || $selectedTokenId === 'pay_and_add_card') {
                $currentAdditionalInformation = $order->getPayment()->getAdditionalInformation()[self::METHOD_TITLE_CODE];
                $order->getPayment()->setAdditionalInformation(
                    [
                        self::METHOD_TITLE_CODE => $currentAdditionalInformation,
                        self::SELECTED_PAYMENT_METHOD_CODE => self::CREDIT_CARD_VALUE
                    ]
                );

                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $this->paytrailLogger->logData(
                Logger::ERROR, 'Error setting selected card token data: ' . $e->getMessage()
            );
        }
    }
}
