<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */
declare(strict_types=1);

namespace Goodahead\HyvaCheckout\Magewire\Checkout\Payment;

use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class MethodList extends \Hyva\Checkout\Magewire\Checkout\Payment\MethodList
{
    public const DEFAULT_PAYMENT_METHOD_CODE = 'paytrail';
    public const DEFAULT_FREE_PAYMENT_METHOD_CODE = 'free';

    protected $listeners = [
        'billing_address_saved' => 'refresh',
        'shipping_address_saved' => 'refresh',
        'coupon_code_applied' => 'refresh',
        'coupon_code_revoked' => 'refresh',
        'giftcard_code_applied' => 'refresh',
        'giftcard_code_revoked' => 'refresh',
        'cart_items_updated' => 'refresh'
    ];

    protected $loader = [];

    public function __construct(
        SessionCheckout $sessionCheckout,
        CartRepositoryInterface $cartRepository,
        EvaluationResultFactory $evaluationResultFactory,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($sessionCheckout, $cartRepository, $evaluationResultFactory);
    }

    public function boot(): void
    {
        parent::boot();
        // This is part of default Magento logic that is missing in Hyva Checkout implementation
        // So, in Hyva Checkout Payment Methods you can set any payment method directly to the DB
        // Look at \Magento\Payment\Model\Checks\ZeroTotal::isApplicable() method
        $isQuoteFree = $this->sessionCheckout->getQuote()->getBaseGrandTotal() < 0.0001;
        $defaultApplicableMethod = $isQuoteFree ? self::DEFAULT_FREE_PAYMENT_METHOD_CODE : self::DEFAULT_PAYMENT_METHOD_CODE;

        if (!$this->method || ($isQuoteFree && $this->method !== $defaultApplicableMethod) || (!$isQuoteFree && $this->method === self::DEFAULT_FREE_PAYMENT_METHOD_CODE)) {
            try {
                $this->method = $this->updatedMethod($defaultApplicableMethod);
            } catch (\Exception $e) {
                $this->method = null;
                $this->logger->error('GA HC Payment MethodList: ' . $e->getMessage());
            }
        }
    }
}
