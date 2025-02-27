<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */
declare(strict_types=1);

namespace Goodahead\HyvaCheckout\Service;

use Goodahead\Cart\Model\Queue\Publisher\CancellationPublisher as Publisher;
use Goodahead\Cart\Service\CancelGiftCardService as GiftCardService;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService;
use Psr\Log\LoggerInterface;

class RestoreQuoteService
{

    public const LOW_STOCK_THRESHOLD = 2;

    /**
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param Publisher $publisher
     * @param StockRegistry $stockRegistry
     * @param ProductRepository $productRepository
     * @param CancelGiftCardService $giftCardService
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected CheckoutSession $checkoutSession,
        protected OrderRepositoryInterface $orderRepository,
        protected Publisher $publisher,
        protected StockRegistry $stockRegistry,
        protected ProductRepository $productRepository,
        protected GiftCardService $giftCardService,
        protected OrderService $orderService,
        protected LoggerInterface $logger
    ) {}

    /**
     * We could use $this->orderService->cancel($lastOrder->getId());
     * However it is not compatible with queue, because we do not have a way to check if the GiftCard restoration is
     * done or pending
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(): void
    {
        $noCurrentQuote = !$this->checkoutSession->getQuote()->getGrandTotal();
        $lastOrder = $this->checkoutSession->getLastRealOrder();

        if (
            $noCurrentQuote && $lastOrder->getPayment() &&
            (
                $lastOrder->getData('status') === Order::STATE_PENDING_PAYMENT ||
                $lastOrder->getData('state') === Order::STATE_PENDING_PAYMENT
            )
        ) {
            $hasLowStockItems = $this->quoteHasLowStockItems($lastOrder);
            $lastOrderId = $lastOrder->getId();

            if (!$hasLowStockItems) {
                $this->publisher->publish(
                    Publisher::TOPIC_NAME,
                    $lastOrderId
                );
            } else {
                $order = $this->orderRepository->get($lastOrderId);
                $order->cancel();
                $this->orderRepository->save($order);
            }

            $this->giftCardService->execute($lastOrder);
            $operationResult = $this->checkoutSession->restoreQuote();

            if (!$operationResult) {
                $this->logger->warning(sprintf("Could not restore quote from order %s.", $lastOrder->getId()));
            }
        }
    }

    /**
     * @param Order $lastOrder
     * @return bool
     */
    private function quoteHasLowStockItems(Order $lastOrder): bool
    {
        $hasLowStockItems = false;

        foreach ($lastOrder->getItems() as $item) {
            $productId = $item->getProductId();

            try {
                $stockItem = $this->stockRegistry->getStockItem($productId);
                $stockQty = $stockItem->getQty();
                if ($stockQty <= self::LOW_STOCK_THRESHOLD) {
                    $hasLowStockItems = true;
                    break;
                }
            } catch (\Exception $e) {
                $this->logger->error('Error fetching stock quantity: ' . $e->getMessage());
            }
        }

        return $hasLowStockItems;
    }
}
