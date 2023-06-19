<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Profile;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;

class Edit extends Action implements HttpGetActionInterface
{
    public function execute()
    {
        $page = $this->initialize();
        $title = $this->getRequest()->getParam('id') ? __('Edit Recurring Payment profile') : __('Add new profile');
        $page->getConfig()->getTitle()->prepend($title);

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
