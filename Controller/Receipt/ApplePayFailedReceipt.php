<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class ApplePayFailedReceipt implements ActionInterface
{
    public const ORDER_CANCEL_STATUSES  = ["canceled"];

    /**
     * ApplePayFailedReceipt constructor.
     *
     * @param FinnishReferenceNumber $referenceNumber
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private FinnishReferenceNumber $referenceNumber,
        private Session                $session,
        private ProcessPayment         $processPayment,
        private RequestInterface       $request,
        private ResultFactory          $resultFactory,
        private ManagerInterface       $messageManager
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
        $params = $this->getParamsToProcess($this->request->getParams(), $order);
        $status = $order->getStatus();

        $failMessages = $this->processPayment->process($params, $this->session);

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order  = $this->referenceNumber->getOrderByReference($params['checkout-reference']); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        if (in_array($status, self::ORDER_CANCEL_STATUSES)) {
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
     *
     * @return array
     *
     * @throws \Paytrail\PaymentService\Exceptions\CheckoutException
     */
    private function getParamsToProcess($params, $order): array
    {
        $datetime    = new \DateTime();

        return [
            'checkout-account' => $params['checkout-account'],
            'checkout-algorithm' => $params['checkout-algorithm'],
            'checkout-amount' => $params['amount'],
            'checkout-stamp' => $datetime->format('Y-m-d\TH:i:s.u\Z'),
            'checkout-reference' => $this->referenceNumber->getReference($order),
            'checkout-status' => 'fail',
            'checkout-provider' => 'applepay',
            'checkout-transaction-id' => $params['checkout-transaction-id'],
            'signature' => $params['signature']
        ];
    }
}
