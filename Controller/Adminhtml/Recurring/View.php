<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Framework\App\ResponseInterface;

class View extends \Magento\Backend\App\Action
{
    public function execute()
    {
        $page = $this->initialize();
        $page->getConfig()->getTitle()->prepend(__('View Recurring Payment'));

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
