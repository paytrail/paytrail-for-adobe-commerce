<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Controller\Card;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Model\ResourceModel\PaymentToken;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\PaymentService\Model\Validation\PreventAdminActions;
use Psr\Log\LoggerInterface;

class Change implements \Magento\Framework\App\ActionInterface
{
    /**
     * @param Context $context
     * @param SubscriptionRepositoryInterface $subscriptionRepositoryInterface
     * @param LoggerInterface $logger
     * @param PaymentToken $paymentToken
     * @param Session $customerSession
     * @param PreventAdminActions $preventAdminActions
     */
    public function __construct(
        private Context                         $context,
        private SubscriptionRepositoryInterface $subscriptionRepositoryInterface,
        private LoggerInterface                 $logger,
        private PaymentToken                    $paymentToken,
        private Session                         $customerSession,
        private PreventAdminActions             $preventAdminActions
    ) {
    }

    /**
     * @return ResultInterface|Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $resultRedirect = $this->context->getResultFactory()->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('paytrail/order/payments');

        if ($this->preventAdminActions->isAdminAsCustomer()) {
            $this->context->getMessageManager()->addErrorMessage(__('Admin user is not authorized for this operation'));

            return $resultRedirect;
        }

        $subscriptionId   = $this->context->getRequest()->getParam('subscription_id');
        $selectedTokenRaw = $this->context->getRequest()->getParam('selected_token');

        $selectedToken = $this->paymentToken->getByPublicHash(
            $selectedTokenRaw,
            (int)$this->customerSession->getCustomerId()
        );

        if (!$selectedToken) {
            $this->context->getMessageManager()->addErrorMessage(__('Unable to change card'));
            return $resultRedirect;
        }

        try {
            $subscription = $this->subscriptionRepositoryInterface->get((int)$subscriptionId);
            $subscription->setSelectedToken((int)$selectedToken[SubscriptionInterface::FIELD_ENTITY_ID]);
            $this->subscriptionRepositoryInterface->save($subscription);

            $this->context->getMessageManager()->addSuccessMessage(__('Card for subscription changed successfully'));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->context->getMessageManager()->addErrorMessage(__('Unable to change card'));
        }

        return $resultRedirect;
    }
}
