<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Paytrail\PaymentService\Api\Data\SubscriptionLinkInterfaceFactory;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;

class Save extends Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    /**
     * @var SubscriptionRepositoryInterface
     */
    private $paymentRepo;
    /**
     * @var SubscriptionLinkInterfaceFactory
     */
    private $factory;

    public function __construct(
        Context $context,
        SubscriptionRepositoryInterface $paymentRepository,
        SubscriptionLinkInterfaceFactory $factory
    ) {
        parent::__construct($context);
        $this->paymentRepo = $paymentRepository;
        $this->factory = $factory;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('entity_id');

        if ($id) {
            $payment = $this->paymentRepo->get($id);
        } else {
            $payment = $this->factory->create();
        }

        $data = $this->getRequest()->getParams();
        $payment->setData($data);
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $this->paymentRepo->save($payment);
            $resultRedirect->setPath('recurring_payments/recurring');
        } catch (CouldNotSaveException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('recurring_payments/recurring/edit', ['id' => $id]);
        }

        return $resultRedirect;
    }
}
