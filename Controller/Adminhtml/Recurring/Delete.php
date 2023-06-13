<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;

class Delete extends Action implements HttpPostActionInterface
{
    /** @var SubscriptionRepositoryInterface  */
    private $subscriptionRepository;

    public function __construct(
        Context $context,
        SubscriptionRepositoryInterface $subscriptionRepository
    ) {
        parent::__construct($context);
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $payment = $this->subscriptionRepository->get($id);
            $this->subscriptionRepository->delete($payment);
            $resultRedirect->setPath('recurring_payments/recurring');
            $this->messageManager->addSuccessMessage('Recurring payment deleted');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('recurring_payments/recurring/edit', ['id' => $id]);
        }

        return $resultRedirect;
    }
}
