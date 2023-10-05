<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;

class Index extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    public function execute()
    {
        $page = $this->initialize();
        $page->getConfig()->getTitle()->prepend(__('Subscriptions'));

        return $page;
    }

    private function initialize()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Magento_Sales::sales_order');

        return $resultPage;
    }
}
