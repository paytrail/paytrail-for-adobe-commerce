<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Paytrail\PaymentService\Model\PaymentMethod\OrderPaymentMethodData;
use Paytrail\PaymentService\Observer\OrderInvoiceCancellation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderInvoiceCancellationTest extends TestCase
{
    /**
     * @var CommandManagerPoolInterface|Stub
     */
    private $commandManagerPool;

    /**
     * @var CommandManagerInterface|MockObject
     */
    private $commandManager;

    /**
     * @var OrderInvoiceCancellation
     */
    private $observer;

    protected function setUp(): void
    {
        $this->commandManager = $this->createMock(CommandManagerInterface::class);
        $this->commandManagerPool = $this->createStub(CommandManagerPoolInterface::class);
        $this->commandManagerPool->method('get')->willReturn($this->commandManager);

        $this->observer = new OrderInvoiceCancellation(
            $this->commandManagerPool,
            $this->createStub(LoggerInterface::class)
        );
    }

    public function testSkipsKlarnaInvoiceCancellationWithoutTransactionId(): void
    {
        $this->commandManager->expects($this->never())->method('executeByCode');

        $this->observer->execute($this->createObserver('klarna', null));
    }

    public function testSkipsKlarnaInvoiceCancellationWithEmptyTransactionId(): void
    {
        $this->commandManager->expects($this->never())->method('executeByCode');

        $this->observer->execute($this->createObserver('klarna', ''));
    }

    public function testCancelsKlarnaInvoiceWithTransactionId(): void
    {
        $this->commandManager->expects($this->once())
            ->method('executeByCode')
            ->with('invoice_cancellation', null, ['transaction_id' => 'trx-123'])
            ->willReturn(['error' => 'declined', 'data' => null]);

        $this->observer->execute($this->createObserver('klarna', 'trx-123'));
    }

    public function testDoesNotCancelInvoiceForOtherPaymentMethods(): void
    {
        $this->commandManager->expects($this->never())->method('executeByCode');

        $this->observer->execute($this->createObserver('nordea', 'trx-123'));
    }

    /**
     * @param string $paymentMethod
     * @param string|null $lastTransId
     * @return Observer
     */
    private function createObserver(string $paymentMethod, ?string $lastTransId): Observer
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('getAdditionalInformation')
            ->willReturn([OrderPaymentMethodData::SELECTED_PAYMENT_METHOD_CODE => $paymentMethod]);
        $payment->method('getLastTransId')->willReturn($lastTransId);

        $order = $this->createStub(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getState')->willReturn(Order::STATE_CANCELED);

        return new Observer(['event' => new Event(['order' => $order])]);
    }
}
