<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\HmacValidator;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class ApplePayFailedReceipt implements ActionInterface
{
    /**
     * ApplePayFailedReceipt constructor.
     *
     * @param FinnishReferenceNumber $referenceNumber
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     * @param PaymentDataProvider $paymentDataProvider
     */
    public function __construct(
        private FinnishReferenceNumber $referenceNumber,
        private Session                $session,
        private ProcessPayment         $processPayment,
        private RequestInterface       $request,
        private ResultFactory          $resultFactory,
        private ManagerInterface       $messageManager,
        private PaymentDataProvider    $paymentDataProvider
    ) {
    }

    /**
     * Order status is manipulated by another callback:
     *
     * @throws \Exception
     * execute method
     */
    public function execute()
    {
        $order = $this->session->getLastRealOrder();
        $params = $this->getParamsToProcess($this->request->getParams()['params'], $order);
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $failMessages = $this->processPayment->process($params, $this->session);

        if ($failMessages) {
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }
        }

        return $resultJson->setData([
            'redirectUrl' => 'checkout/cart',
            'message' => __('Apple Pay payment canceled or failed.')
        ]);
    }

    /**
     * Get params for processing order and payment.
     *
     * @param array $params
     * @param Order $order
     * @return array
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    private function getParamsToProcess($params, $order): array
    {
        $paramsToProcess = [
            'checkout-transaction-id' => '',
            'checkout-account' => '',
            'checkout-method' => '',
            'checkout-algorithm' => '',
            'checkout-timestamp' => '',
            'checkout-nonce' => '',
            'checkout-reference' => $this->referenceNumber->getReference($order),
            'checkout-provider' => Config::APPLE_PAY_PAYMENT_CODE,
            'checkout-status' => Config::PAYTRAIL_API_PAYMENT_STATUS_FAIL,
            'checkout-stamp' => $this->paymentDataProvider->getStamp($order),
            'signature' => '',
            'skip_validation' => 1
        ];

        foreach ($params as $param) {
            if (array_key_exists($param['name'], $paramsToProcess)) {
                $paramsToProcess[$param['name']] = $param['value'];
            }
        }

        return $paramsToProcess;
    }
}
