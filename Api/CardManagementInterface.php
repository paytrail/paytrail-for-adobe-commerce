<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Exception\LocalizedException;
use Paytrail\SDK\Exception\ValidationException;

/**
 * @api
 */
interface CardManagementInterface
{
    /**
     * Initialize add card process.
     *
     * @return string
     * @throws ValidationException
     */
    public function generateAddCardUrl(): string;

    /**
     * Delete unused card.
     *
     * @param string $cardId
     * @return bool
     * @throws LocalizedException
     */
    public function delete(string $cardId): bool;
}
