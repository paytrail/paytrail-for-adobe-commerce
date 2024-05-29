<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\ApplePay\ApplePayConfig;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class ApplePayFailedReceipt implements ActionInterface
{
    /**
     * ApplePayFailedReceipt constructor.
     *
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     * @param ApplePayConfig $applePayConfig
     * @param PaytrailLogger $logger
     */
    public function __construct(
        private Session          $session,
        private ProcessPayment   $processPayment,
        private RequestInterface $request,
        private ResultFactory    $resultFactory,
        private ManagerInterface $messageManager,
        private ApplePayConfig   $applePayConfig,
        private PaytrailLogger   $logger
    ) {
    }

    /**
     * Failed Apple Pay payment processor.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        try {
            $order = $this->session->getLastRealOrder();
            $params = $this->applePayConfig->getApplePayFailParams($this->request->getParams()['params'], $order);
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

            $failMessages = $this->processPayment->process($params, $this->session);

            if ($failMessages) {
                foreach ($failMessages as $failMessage) {
                    $this->messageManager->addErrorMessage($failMessage);
                }
            }
        } catch (CheckoutException $e) {
            $this->logger->error(
                'Error while processing failed Apple Pay payment: ' . $e->getMessage()
            );
        }

        return $resultJson->setData([
            'redirectUrl' => 'checkout/cart',
            'message' => __('Apple Pay payment canceled or failed.')
        ]);
    }
}
