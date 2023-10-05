<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Plugin;

use Magento\Checkout\Model\Cart;
use Magento\Framework\Exception\LocalizedException;

class PreventDifferentScheduledCart
{
    private const SCHEDULE_CODE = 'recurring_payment_schedule';

    /**
     * @param Cart $subject
     * @param $productInfo
     * @param $requestInfo
     * @return array
     * @throws LocalizedException
     */
    public function beforeAddProduct(Cart $subject, $productInfo, $requestInfo = null)
    {
        $cartItems = $subject->getQuote()->getItems() ?: [];
        $addItemSchedule = $productInfo->getCustomAttribute(self::SCHEDULE_CODE);
        if (!$addItemSchedule) {
            return [$productInfo, $requestInfo];
        }
        foreach ($cartItems as $item) {
            $cartItemSchedule = $item->getProduct()->getCustomAttribute(self::SCHEDULE_CODE);
            if ($cartItemSchedule && $cartItemSchedule->getValue() != $addItemSchedule->getValue()) {
                throw new LocalizedException(__("Can't add product with different payment schedule"));
            }
        }

        return [$productInfo, $requestInfo];
    }
}
