<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */

namespace Goodahead\HyvaCheckout\Observer;

use Aheadworks\Giftcard\Api\GiftcardCartManagementInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class SessionObserver implements ObserverInterface
{

    /**
     * @param GiftcardCartManagementInterface $giftCardManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected GiftcardCartManagementInterface $giftCardManagement,
        protected LoggerInterface $logger
    ) {}

    /**
     * Observer for restore_quote
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();

        $order = $event->getData('order');
        $quote = $event->getData('quote');

        if (!$order) {
            return;
        }

        if (!$quote) {
            return;
        }

        if ($order->getExtensionAttributes() && $order->getExtensionAttributes()->getAwGiftcardCodes()) {
            $orderGiftCards = $order->getExtensionAttributes()->getAwGiftcardCodes();
            foreach ($orderGiftCards as $giftCard) {
                try {
                    $this->giftCardManagement->set($quote->getId(), $giftCard->getGiftcardCode());
                } catch (CouldNotSaveException|NoSuchEntityException|LocalizedException $e) {
                    $this->logger->critical($e->getMessage());
                }
            }
        }
    }
}
