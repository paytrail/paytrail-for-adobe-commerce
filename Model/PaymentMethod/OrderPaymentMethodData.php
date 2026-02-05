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
        private PaytrailLogger                  $paytrailLogger,
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

    /**
     * Sets selected payment method data from callback/receipt response.
     *
     * This method is used when payment method selection is on a separate page (SkipBankSelection enabled)
     * and the checkout-provider is returned from Paytrail in the callback/receipt response.
     * It sets the selected payment method code and the manual invoice activation flag if applicable.
     *
     * @param Order $order
     * @param string $checkoutProvider The checkout-provider value from Paytrail callback/receipt params
     * @param bool $saveOrder Whether to save the order after setting the data
     * @return void
     */
    public function setPaymentMethodDataFromCallback(Order $order, string $checkoutProvider, bool $saveOrder = true): void
    {
        try {
            $payment = $order->getPayment();

            // Only set if not already set (to avoid overwriting pre-selected method)
            $existingMethod = $payment->getAdditionalInformation(self::SELECTED_PAYMENT_METHOD_CODE);
            if (empty($existingMethod) || $existingMethod != $checkoutProvider) {
                $payment->setAdditionalInformation(self::SELECTED_PAYMENT_METHOD_CODE, $checkoutProvider);
            }

            if ($saveOrder) {
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $this->paytrailLogger->logData(
                Logger::ERROR,
                'Error setting payment method data from callback: ' . $e->getMessage()
            );
        }
    }
}
