<?php

namespace Paytrail\PaymentService\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * @api
 */
interface CardRepositoryInterface
{
    /**
     * Initialize add card process
     *
     * @return string
     * @throws LocalizedException
     */
    public function save(): string;

    /**
     * Delete unused card
     *
     * @param string $cardId
     * @return bool
     * @throws LocalizedException
     */
    public function delete(string $cardId): bool;
}
