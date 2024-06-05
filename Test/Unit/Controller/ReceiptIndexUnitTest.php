<?php

namespace Paytrail\PaymentService\Test\Unit\Controller;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;
use Paytrail\PaymentService\Controller\Receipt\Index;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReceiptIndexUnitTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $referenceNumberMock;

    /**
     * @var MockObject
     */
    private $sessionMock;

    /**
     * @var MockObject
     */
    private $processPaymentMock;
    /**
     * @var MockObject
     */
    private $requestMock;

    /**
     * @var MockObject
     */
    private $resultFactoryMock;

    /**
     * @var MockObject|null
     */
    private MockObject $messageManagerMock;

    /**
     * @var MockObject|null
     */
    private MockObject $orderMock;

    /**
     * @var (ResultInterface&MockObject)|MockObject|null
     */
    private $resultInterfaceMock;

    /**
     * @var Index
     */
    private Index $indexController;

    /**
     * @var MockObject|(Quote&MockObject)
     */
    private MockObject $quoteMock;

    /**
     * @var MockObject
     */
    private MockObject $quoteRepositoryMock;

    /**
     * @param $originalClassName
     *
     * @return MockObject
     */
    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->referenceNumberMock = $this->getSimpleMock(FinnishReferenceNumber::class);
        $this->sessionMock         = $this->getSimpleMock(Session::class);
        $this->processPaymentMock  = $this->getSimpleMock(ProcessPayment::class);
        $this->requestMock         = $this->getSimpleMock(RequestInterface::class);
        $this->resultFactoryMock   = $this->getSimpleMock(ResultFactory::class);
        $this->messageManagerMock  = $this->getSimpleMock(ManagerInterface::class);

        $this->indexController = new Index(
            $this->referenceNumberMock,
            $this->sessionMock,
            $this->processPaymentMock,
            $this->requestMock,
            $this->resultFactoryMock,
            $this->messageManagerMock
        );

        $this->quoteMock           = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(['setIsActive'])
            ->getMock();
        $methods                   = ['setPath', 'setHeader', 'setHttpResponseCode', 'renderResult'];
        $this->resultInterfaceMock = $this->getMockBuilder(ResultInterface::class)
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteRepositoryMock = $this->getSimpleMock(QuoteRepository::class);
        $this->orderMock           = $this->getSimpleMock(Order::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testExecuteSuccess()
    {
        $this->requestMock
            ->expects($this->atLeast(1))
            ->method('getParam')
            ->willReturn('checkout-reference');

        $this->referenceNumberMock
            ->expects($this->once())
            ->method('getOrderByReference')
            ->willReturn($this->orderMock);

        $this->orderMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn('processing');

        $this->resultFactoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($this->resultInterfaceMock);

        $this->resultInterfaceMock
            ->method('setPath')
            ->willReturn($this->resultInterfaceMock);

        $this->indexController->execute();
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testExecuteFail()
    {
        $this->requestMock
            ->expects($this->atLeast(1))
            ->method('getParam')
            ->willReturn('checkout-reference');

        $this->referenceNumberMock
            ->expects($this->once())
            ->method('getOrderByReference')
            ->willReturn($this->orderMock);

        $this->orderMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn('canceled');

        $failMessage = ['failMessage'];

        $this->processPaymentMock
            ->method('process')
            ->willReturn($failMessage);

        $this->resultFactoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($this->resultInterfaceMock);

        $this->resultInterfaceMock
            ->method('setPath')
            ->willReturn($this->resultInterfaceMock);

        $this->indexController->execute();
    }
}
