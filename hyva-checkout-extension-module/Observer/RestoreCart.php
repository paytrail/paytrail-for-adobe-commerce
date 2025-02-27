<?php

declare(strict_types=1);

namespace Goodahead\HyvaCheckout\Observer;

use Goodahead\HyvaCheckout\Service\RestoreQuoteService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class RestoreCart implements ObserverInterface
{
    /**
     * @param RestoreQuoteService $restoreQuoteService
     */
    public function __construct(
        protected RestoreQuoteService $restoreQuoteService,
    ) {}

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $this->restoreQuoteService->execute();
    }
}
