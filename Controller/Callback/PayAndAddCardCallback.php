<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Vault\Model\PaymentTokenFactory;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;
use Paytrail\SDK\Model\Token\Card;

class PayAndAddCardCallback implements \Magento\Framework\App\ActionInterface
{
    /**
     * Index constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param Config $gatewayConfig
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        private Session $session,
        private ProcessPayment $processPayment,
        private RequestInterface $request,
        private ResultFactory $resultFactory,
        private Config $gatewayConfig,
        private OrderFactory $orderFactory,
    ) {
    }

    /**
     * Execute function
     *
     * @return ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(): ResultInterface
    {
        $reference = $this->request->getParam('checkout-reference');
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if ($this->request->getParam('checkout-card-token')) {
            $this->saveToken($this->request->getParams());
        }

        /** @var string $orderNo */
        $orderNo = $this->gatewayConfig->getGenerateReferenceForOrder()
            ? $this->gatewayConfig->getIdFromOrderReferenceNumber($reference)
            : $reference;

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderNo);
        $status = $order->getStatus();

        if ($status == 'pending_payment' || in_array($status, Receipt::ORDER_CANCEL_STATUSES)) {
            // order status could be changed by receipt
            // if not, status change needs to be forced by processing the payment
            $response['error'] = $this->processPayment->process($this->request->getParams(), $this->session);
        }

        return $response;
    }

    /**
     * @param $cardData
     * @return void
     */
    private function saveToken($cardData)
    {
        $vaultPaymentToken = $this->paymentTokenFactory->create(PaymentTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $customerId = $this->customerSession->getCustomer()->getId();
        $vaultPaymentToken->setCustomerId($customerId);
        $vaultPaymentToken->setPaymentMethodCode($this->gatewayConfig->getCcVaultCode());
        $vaultPaymentToken->setExpiresAt(
            sprintf(
                '%s-%s-01 00:00:00',
                $cardData->getExpireYear(),
                $cardData->getExpireMonth()
            )
        );
        $tokenDetails = $this->jsonSerializer->serialize(
            [
                'type' => $this->cardTypes[$cardData->getType()],
                'maskedCC' => $cardData->getPartialPan(),
                'expirationDate' => $cardData->getExpireYear() . '/' . $cardData->getExpireMonth()
            ]
        );
        $vaultPaymentToken->setGatewayToken($cardData->getCheckoutCardToken());
        $vaultPaymentToken->setTokenDetails($tokenDetails);
        $vaultPaymentToken->setPublicHash($this->createPublicHash($cardData, $customerId));
        $this->tokenRepository->save($vaultPaymentToken);
    }
}
