<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Profile;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    private $filter;
    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\CollectionFactory
     */
    private $factory;
    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile
     */
    private $profileResource;

    public function __construct(
        Context                                                                 $context,
        \Magento\Ui\Component\MassAction\Filter                                 $filter,
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\CollectionFactory $factory,
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile                   $profileResource
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->factory = $factory;
        $this->profileResource = $profileResource;
    }

    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $collection = $this->filter->getCollection($this->factory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $this->profileResource->delete($item);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        return $resultRedirect->setPath('*/*/');
    }
}
