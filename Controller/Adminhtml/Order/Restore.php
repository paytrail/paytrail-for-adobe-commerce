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

    /**
     * @var \Magento\Backend\Model\Session
     */
    private $session;
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    public function __construct(
        Context $context,
        ActivateOrder $activateOrderHelper,
        \Magento\Backend\Model\Session $session,
        \Magento\Framework\App\RequestInterface $request
    ) {
        parent::__construct($context);
        $this->session  = $session;
        $this->request = $request;
        $this->activateOrderHelper = $activateOrderHelper;
    }

    public function execute()
    {
        $this->session->clearStorage();
        $orderId = $this->request->getParam('order_id');
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
