<?php

declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class RestoreCart implements ObserverInterface
{
    private Session       $checkoutSession;
    private ResultFactory $resultFactory;

    /**
     * RestoreCart constructor.
     *
     * @param Session $checkoutSession
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Session       $checkoutSession,
        ResultFactory $resultFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Execute.
     *
     * @param Observer $observer
     *
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        $lastOrder = $this->checkoutSession->getLastRealOrder();
        $result    = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if ($lastOrder->getPayment() && $lastOrder->getData('status') === Order::STATE_PENDING_PAYMENT) {
            $this->checkoutSession->restoreQuote();
        }

        return $result->setPath('checkout/payment');
    }
}
