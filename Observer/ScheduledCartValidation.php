<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Paytrail\PaymentService\Plugin\PreventDifferentScheduledCart;

class ScheduledCartValidation implements ObserverInterface
{
    /**
     * ScheduledCartValidation constructor.
     *
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * Execute.
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $cartSchedule = null;
        $cartId = $observer->getEvent()->getOrder()->getQuoteId();
        $cart = $this->cartRepository->get($cartId);

        foreach ($cart->getItems() as $cartItem) {
            $cartItemSchedule = $cartItem
                ->getProduct()
                ->getCustomAttribute(PreventDifferentScheduledCart::SCHEDULE_CODE)
                ->getValue();
            if ($cartItemSchedule) {
                if (isset($cartSchedule) && $cartSchedule !== $cartItemSchedule) {
                    throw new LocalizedException(__("Can't place order with different scheduled products in cart"));
                } else {
                    $cartSchedule = $cartItem
                        ->getProduct()
                        ->getCustomAttribute(PreventDifferentScheduledCart::SCHEDULE_CODE)
                        ->getValue();
                }
            }
        }
    }
}
