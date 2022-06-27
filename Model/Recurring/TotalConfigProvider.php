<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Model\Recurring;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class TotalConfigProvider implements ConfigProviderInterface
{
    private const NO_SCHEDULE_VALUE = null;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @param Session $checkoutSession
     */
    public function __construct(
        Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getConfig(): array
    {
        return [
            'isRecurringScheduled' => $this->isRecurringScheduled(),
            'recurringSubtotal' => $this->getRecurringSubtotal()
            ];
    }

    /**
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function isRecurringScheduled(): bool
    {
        $quoteItems = $this->checkoutSession->getQuote()->getItems();
        foreach ($quoteItems as $item) {
            if ($item->getProduct()->getCustomAttribute('recurring_payment_schedule') != self::NO_SCHEDULE_VALUE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getRecurringSubtotal(): float
    {
        $recurringSubtotal = 0.00;
        if ($this->isRecurringScheduled()) {
            $quoteItems = $this->checkoutSession->getQuote()->getItems();
            foreach ($quoteItems as $item) {
                if ($item->getProduct()->getCustomAttribute('recurring_payment_schedule') != self::NO_SCHEDULE_VALUE) {
                    $recurringSubtotal = $recurringSubtotal + ($item->getPrice() * $item->getQty());
                }
            }
        }

        return $recurringSubtotal;
    }
}
