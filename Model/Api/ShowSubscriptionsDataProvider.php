<?php

namespace Paytrail\PaymentService\Model\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;

class ShowSubscriptionsDataProvider
{
    private PaymentTokenRepositoryInterface $paymentTokenRepository;
    private SubscriptionLinkRepositoryInterface $subscriptionLinkRepository;
    private OrderRepositoryInterface $orderRepository;
    private Json $jsonSerializer;


    public function __construct(
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepository,
        OrderRepositoryInterface $orderRepository,
        Json $jsonSerializer
    ) {
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->subscriptionLinkRepository = $subscriptionLinkRepository;
        $this->orderRepository = $orderRepository;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return array|mixed
     */
    public function getMaskedCCById(SearchCriteriaInterface $searchCriteria)
    {
        $paymentTokenCollection = $this->paymentTokenRepository->getList($searchCriteria)->getItems();

        $paymentToken = [];
        foreach ($paymentTokenCollection as $paymentTokenItem) {
            $paymentToken[$paymentTokenItem->getEntityId()] =
                $this->jsonSerializer->unserialize($paymentTokenItem->getTokenDetails())['maskedCC'];
        }

        return $paymentToken;
    }

    /**
     * @param $subscriptionId
     * @return array
     */
    public function getOrderDataFromSubscriptionId($subscriptionId)
    {
        $orderIds = array_last($this->subscriptionLinkRepository->getOrderIdsBySubscriptionId($subscriptionId));
        $order = $this->orderRepository->get($orderIds);

        return [
            'increment_id' => $order->getIncrementId(),
            'grand_total' => $order->getGrandTotal()
        ];
    }
}
