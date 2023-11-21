<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;

class StopSchedule extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    const ORDER_PENDING_STATUS = 'pending';
    /**
     * @var SubscriptionRepositoryInterface
     */
    private $subscriptionRepository;
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var SubscriptionLinkRepositoryInterface
     */
    private $subscriptionLinkRepoInterface;

    public function __construct(
        Context                         $context,
        SubscriptionRepositoryInterface $subscriptionRepository,
        OrderManagementInterface        $orderManagement,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepoInterface
    ) {
        parent::__construct($context);
        $this->subscriptionRepository = $subscriptionRepository;
        $this->orderManagement = $orderManagement;
        $this->subscriptionLinkRepoInterface = $subscriptionLinkRepoInterface;
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath($this->_redirect->getRefererUrl());
        $id = $this->getRequest()->getParam('id');

        $subscription = $this->getRecurringPayment($id);
        if (!$subscription) {
            return $resultRedirect;
        }
        $this->cancelOrder($subscription);
        $this->updateRecurringStatus($subscription);

        return $resultRedirect;
    }

    /**
     * @param $id
     * @return false|SubscriptionInterface
     */
    private function getRecurringPayment($subscriptionId)
    {
        try {
            return $this->subscriptionRepository->get($subscriptionId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(\__(
                'Unable to load subscription with ID: %id',
                ['id' => $subscriptionId]
            ));
        }

        return false;
    }

    /**
     * @param SubscriptionInterface $subscription
     */
    private function cancelOrder(SubscriptionInterface $subscription): void
    {
        try {
            // Only cancel unpaid orders.
            $ordersId = $this->subscriptionLinkRepoInterface->getOrderIdsBySubscriptionId($subscription->getId());
            if ($subscription->getStatus() !== SubscriptionInterface::STATUS_CLOSED) {
                foreach ($ordersId as $orderId) {
                    $this->orderManagement->cancel($orderId);
                }
            } else {
                $this->messageManager->addWarningMessage(\__(
                    'Order ID %id has a status other than %status, automatic order cancel disabled. If the order is unpaid please cancel it manually',
                    [
                        'id' => array_shift($ordersId),
                        'status' => self::ORDER_PENDING_STATUS
                    ]
                ));
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(\__(
                'Error occurred while cancelling the order: %error',
                ['error' => $exception->getMessage()]
            ));
        }
    }

    private function updateRecurringStatus(SubscriptionInterface $subscription)
    {
        $subscription->setStatus(SubscriptionInterface::STATUS_CLOSED);

        try {
            $this->subscriptionRepository->save($subscription);
            $this->messageManager->addSuccessMessage(\__(
                'Recurring payments stopped for payment id: %id',
                [
                    'id' => $subscription->getId()
                ]
            ));
        } catch (CouldNotSaveException $exception) {
            $this->messageManager->addErrorMessage(\__(
                'Error occurred while updating recurring payment: %error',
                ['error' => $exception->getMessage()]
            ));
        }
    }
}
