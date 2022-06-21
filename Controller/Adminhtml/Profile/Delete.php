<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Profile;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface;

class Delete extends Action implements HttpPostActionInterface
{
    /** @var RecurringProfileRepositoryInterface  */
    private $profileRepo;

    public function __construct(
        Context $context,
        RecurringProfileRepositoryInterface $profileRepository
    ) {
        parent::__construct($context);
        $this->profileRepo = $profileRepository;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $profile = $this->profileRepo->get($id);
            $this->profileRepo->delete($profile);
            $resultRedirect->setPath('recurring_payments/profile');
            $this->messageManager->addSuccessMessage('Recurring profile deleted');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('recurring_payments/profile/edit', ['id' => $id]);
        }

        return $resultRedirect;
    }
}
