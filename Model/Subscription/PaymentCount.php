<?php

namespace Paytrail\PaymentService\Model\Subscription;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;

class PaymentCount
{
    /**
     * @var SubscriptionRepositoryInterface
     */
    private $subscriptionRepository;

    private \Psr\Log\LoggerInterface $logger;

    public function __construct(
        SubscriptionRepositoryInterface $subscriptionRepository,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->logger = $logger;
    }

    public function reduceFailureRetryCount($subscription)
    {
        $subscription->setRetryCount($subscription->getRetryCount()-1);
        if ($subscription->getRetryCount() <= 0) {
            $subscription->setStatus(SubscriptionInterface::STATUS_FAILED);
        }

        $this->save($subscription);
    }

    /**
     * @param $subscription
     * @return void
     */
    private function save($subscription): void
    {
        try {
            $this->subscriptionRepository->save($subscription);
        } catch (CouldNotSaveException $e) {
            $this->logger->error(\__(
                'Unable to reduce subscription retry count or mark subscription id: %id as failed',
                ['id' => $subscription->getId()]
            ));
        }
    }
}
