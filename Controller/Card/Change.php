<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Controller\Card;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Model\ResourceModel\PaymentToken;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Psr\Log\LoggerInterface;

class Change extends Action\Action
{
    /**
     * @var Session
     */
    protected Session $customerSession;

    /**
     * @var SubscriptionRepositoryInterface
     */
    protected SubscriptionRepositoryInterface $subscriptionRepositoryInterface;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var PaymentToken
     */
    private PaymentToken $paymentToken;

    /**
     * @param Context $context
     * @param SubscriptionRepositoryInterface $subscriptionRepositoryInterface
     * @param LoggerInterface $logger
     * @param PaymentToken $paymentToken
     * @param Session $customerSession
     */
    public function __construct(
        Context                             $context,
        SubscriptionRepositoryInterface     $subscriptionRepositoryInterface,
        LoggerInterface                     $logger,
        PaymentToken                        $paymentToken,
        Session                             $customerSession
    ) {
        parent::__construct($context);
        $this->subscriptionRepositoryInterface = $subscriptionRepositoryInterface;
        $this->logger = $logger;
        $this->paymentToken = $paymentToken;
        $this->customerSession = $customerSession;
    }

    /**
     * @return ResultInterface|Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $subscriptionId = $this->getRequest()->getParam('subscription_id');
        $selectedTokenRaw = $this->getRequest()->getParam('selected_token');

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('paytrail/order/payments');

        $selectedToken = $this->paymentToken->getByPublicHash($selectedTokenRaw, (int) $this->customerSession->getCustomerId());

        if (!$selectedToken) {
            $this->messageManager->addErrorMessage(__('Unable to change card'));
            return $resultRedirect;
        }

        try {
            $subscription = $this->subscriptionRepositoryInterface->get((int) $subscriptionId);
            $subscription->setSelectedToken((int) $selectedToken[SubscriptionInterface::FIELD_ENTITY_ID]);
            $this->subscriptionRepositoryInterface->save($subscription);

            $this->messageManager->addSuccessMessage(__('Card for subscription changed successfully'));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(__('Unable to change card'));
        }

        return $resultRedirect;
    }
}
