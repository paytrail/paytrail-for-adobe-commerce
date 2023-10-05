<?php

namespace Paytrail\PaymentService\Model\OptionSource;

use Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\Collection;

class ProfileOptions implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\CollectionFactory
     */
    private $collectionFactory;

    public function __construct(
        \Paytrail\PaymentService\Model\ResourceModel\Subscription\Profile\CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        /** @var Collection $profiles */
        $profiles = $this->collectionFactory->create();
        $profiles->addFieldToSelect(['profile_id', 'name']);

        return $this->formatProfilesToOptions($profiles);
    }

    private function formatProfilesToOptions(Collection $profiles)
    {
        $options = [];
        /** @var \Paytrail\PaymentService\Api\Data\RecurringProfileInterface $profile */
        foreach ($profiles as $profile) {
            $options[] = [
                'value' => $profile->getId(),
                'label' => $profile->getName()
            ];
        }

        return $options;
    }
}
