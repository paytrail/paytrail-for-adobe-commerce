<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Paytrail\PaymentService\Model\Order\OrderActivation;

class Restore extends Action implements HttpGetActionInterface
{
    /**
     * Restore constructor.
     *
     * @param Context $context
     * @param OrderActivation $orderActivation
     * @param Session $session
     * @param RequestInterface $request
     */
    public function __construct(
        Context $context,
        private OrderActivation $orderActivation,
        private Session $session,
        private RequestInterface $request
    ) {
        parent::__construct($context);
    }

    /**
     * Execute
     *
     * @return Redirect
     */
    public function execute()
    {
        $this->session->clearStorage();
        $orderId = $this->request->getParam('order_id');
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            if ($this->orderActivation->isCanceled($orderId)) {
                $this->orderActivation->activateOrder($orderId);
            } else {
                $this->messageManager->addErrorMessage('This order cannot be restored.');
            }
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(
                sprintf('An error occurred while trying to restore an order: %s', $exception->getMessage())
            );
        }

        $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);

        return $resultRedirect;
    }
}
