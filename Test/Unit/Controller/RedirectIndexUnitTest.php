<?php

namespace Paytrail\PaymentService\Test\Unit\Controller;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Controller\Redirect\Index;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Email\Order\PendingOrderEmailConfirmation;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RedirectIndexUnitTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $pendingOrderEmailConfirmationMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $sessionMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $orderManagementMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $gatewayConfigMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $resultMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $requestMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $commandManagerPoolMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $processServiceMock;

    /**
     * @var Index
     */
    private $redirectIndex;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $jsonMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $orderFactoryMock;

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->pendingOrderEmailConfirmationMock = $this->getSimpleMock(PendingOrderEmailConfirmation::class);
        $this->sessionMock = $this->getSimpleMock(Session::class);
        $this->orderRepositoryMock = $this->getSimpleMock(OrderRepositoryInterface::class);
        $this->orderManagementMock = $this->getSimpleMock(OrderManagementInterface::class);
        $this->loggerMock = $this->getSimpleMock(LoggerInterface::class);
        $this->gatewayConfigMock = $this->getSimpleMock(Config::class);
        $this->resultMock = $this->getSimpleMock(ResultFactory::class);
        $this->requestMock = $this->getSimpleMock(RequestInterface::class);
        $this->commandManagerPoolMock = $this->getSimpleMock(CommandManagerPoolInterface::class);
        $this->processServiceMock = $this->getSimpleMock(ProcessService::class);

        $this->redirectIndex = new Index(
            $this->pendingOrderEmailConfirmationMock,
            $this->sessionMock,
            $this->orderRepositoryMock,
            $this->orderManagementMock,
            $this->loggerMock,
            $this->gatewayConfigMock,
            $this->resultMock,
            $this->requestMock,
            $this->commandManagerPoolMock,
            $this->processServiceMock
        );

        $this->jsonMock = $this->getSimpleMock(Json::class);
        $this->orderFactoryMock = $this->getSimpleMock(OrderFactory::class);
    }

    /**
     * @return void
     */
    public function testExecuteFail(): void
    {
        $this->requestMock
            ->expects($this->atLeast(1))
            ->method('getParam')
            ->willReturn('true');

        $this->requestMock
            ->expects($this->atLeast(1))
            ->method('getParam')
            ->willReturn(null);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage(__('Call to a member function executeByCode() on null'));
        $this->redirectIndex->execute();
    }

    /**
     * @return void
     */
    public function testExecuteAjaxRequestFail(): void
    {
        $this->requestMock
            ->expects($this->atLeast(1))
            ->method('getParam')
            ->willReturn('false');

        $this->loggerMock
            ->method('error');

        $this->expectException(\Error::class);
        $this->redirectIndex->execute();
    }
}
