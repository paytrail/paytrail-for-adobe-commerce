<?php

namespace Goodahead\Paytrail\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;
use Psr\Log\LoggerInterface;

class Index extends \Paytrail\PaymentService\Controller\Receipt\Index
{
    /**
     * There also can be 'fraud' status
     * For this case message 'Order processing has been aborted. Please contact customer service.' sounds reasonable.
     */
    public const ORDER_SUCCESS_STATUSES = [
        "new",
        "ndc_pending",
        "processing",
        "pending_paytrail",
        "pending",
        "pending_payment",
        "payment_review",
        "complete"
    ];

    /**
     * Index constructor.
     *
     * @param FinnishReferenceNumber $referenceNumber
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly FinnishReferenceNumber $referenceNumber,
        private readonly Session                $session,
        private readonly ProcessPayment         $processPayment,
        private readonly RequestInterface       $request,
        private readonly ResultFactory          $resultFactory,
        private readonly ManagerInterface       $messageManager,
        private readonly LoggerInterface        $logger
    ) {
        parent::__construct($referenceNumber, $session, $this->processPayment, $this->request, $this->resultFactory, $this->messageManager);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $reference = $this->request->getParam('checkout-reference');

        $order  = $this->referenceNumber->getOrderByReference($reference);
        $status = $order->getStatus();

        $failMessages = $this->processPayment->process($this->request->getParams(), $this->session);

        if ($status == 'pending_payment') { // status could be changed by callback, if not, it needs to be forced
            $order  = $this->referenceNumber->getOrderByReference($reference); // refreshing order
            $status = $order->getStatus(); // getting current status
        }

        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        if (in_array($status, self::ORDER_SUCCESS_STATUSES)) {
            return $result->setPath($this->getSuccessUrl($order));
        } elseif (in_array($status, self::ORDER_CANCEL_STATUSES)) {
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }

            return $result->setPath($this->getCartUrl($order));
        }

        $this->messageManager->addErrorMessage(
            __('Order processing has been aborted. Please contact customer service.')
        );

        $this->logger->debug(
            sprintf(
                "Success page wasn't shown to customer.Order: %s. Status %s. Trans ID: %s Provider: %s",
                $order->getIncrementId(),
                $status,
                $this->request->getParam('checkout-transaction-id'),
                $this->request->getParam('checkout-provider'),
            ),
        );

        return $result->setPath($this->getCartUrl($order));
    }
}
