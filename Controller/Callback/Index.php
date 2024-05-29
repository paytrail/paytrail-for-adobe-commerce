<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Paytrail\PaymentService\Controller\Receipt\Index as Receipt;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class Index implements ActionInterface
{
    /**
     * Index constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param FinnishReferenceNumber $referenceNumber
     * @param PaytrailLogger $logger
     */
    public function __construct(
        private Session          $session,
        private ProcessPayment   $processPayment,
        private RequestInterface $request,
        private ResultFactory    $resultFactory,
        private FinnishReferenceNumber $referenceNumber,
        private PaytrailLogger   $logger
    ) {
    }

    /**
     * Execute function
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $this->logger->debugLog(
                'request',
                'callback received' . PHP_EOL .
                'params: ' . json_encode($this->request->getParams(), JSON_PRETTY_PRINT)
            );

            $reference     = $this->request->getParam('checkout-reference');
            $response      = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $responseError = [];

            $order  = $this->referenceNumber->getOrderByReference($reference);
            $status = $order->getStatus();

            if ($status == 'pending_payment' || in_array($status, Receipt::ORDER_CANCEL_STATUSES)) {
                // order status could be changed by receipt
                // if not, status change needs to be forced by processing the payment
                $responseError = $this->processPayment->process($this->request->getParams(), $this->session);

                $this->logger->debugLog(
                    'request',
                    'callback response' . PHP_EOL .
                    'order status: ' . $status . PHP_EOL .
                    'orderNo: ' . $order->getId() . PHP_EOL .
                    'responseErrors: ' . json_encode($responseError, JSON_PRETTY_PRINT)
                );
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $responseError['process_error'] = $e->getMessage();
        }

        return $response->setData([
            'success' => !$responseError ? 'true' : 'false',
            'error' => !$responseError ? '' : $responseError
        ]);
    }
}
