<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */

namespace Goodahead\Paytrail\Model\Magewire\Checkout\Payment;

use Goodahead\Paytrail\Service\PaymentService;
use Hyva\Checkout\Model\Magewire\Payment\AbstractOrderData;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;

class PaytrailPlaceOrderService extends AbstractPlaceOrderService
{

    private PaymentService $paymentService;

    /**
     * @param CartManagementInterface $cartManagement
     * @param PaymentService $paymentService
     * @param AbstractOrderData|null $orderData
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        PaymentService $paymentService,
        AbstractOrderData $orderData = null
    ) {
        parent::__construct($cartManagement, $orderData);
        $this->paymentService = $paymentService;
    }

    /**
     * @param Quote $quote
     * @param int|null $orderId
     * @return string
     * @throws CommandException
     * @throws NotFoundException
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        $response = $this->paymentService->execute();
        return $response->getHref();
    }

    /**
     * @return bool
     */
    public function canRedirect(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canPlaceOrder(): bool
    {
        return true;
    }
}
