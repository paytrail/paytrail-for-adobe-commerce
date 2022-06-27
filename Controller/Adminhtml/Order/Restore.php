<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Paytrail\PaymentService\Helper\ActivateOrder;

class Restore extends Action implements HttpGetActionInterface
{
    /**
     * @var ActivateOrder $activateOrderHelper
     */
    private $activateOrderHelper;

    public function __construct(
        Context $context,
        ActivateOrder $activateOrderHelper
    ) {
        parent::__construct($context);
        $this->activateOrderHelper = $activateOrderHelper;
    }

    public function execute()
    {
        $this->_getSession()->clearStorage();
        $orderId = $this->getRequest()->getParam('order_id');
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        try {
            if ($this->activateOrderHelper->isCanceled($orderId)) {
                $this->activateOrderHelper->activateOrder($orderId);
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
