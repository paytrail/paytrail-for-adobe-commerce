<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Profile;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;

class Index extends Action implements HttpGetActionInterface
{
    public function execute()
    {
        $page = $this->initialize();
        $page->getConfig()->getTitle()->prepend(__('Recurring Payment Profiles'));

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
