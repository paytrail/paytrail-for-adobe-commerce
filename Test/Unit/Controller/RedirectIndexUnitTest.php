<?php

namespace Paytrail\PaymentService\Test\Unit\Controller;

use Error;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Paytrail\PaymentService\Controller\Redirect\Index;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Email\Order\PendingOrderEmailConfirmation;
use Paytrail\PaymentService\Model\ProviderForm;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\Ui\DataProvider\PaymentProvidersData;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RedirectIndexUnitTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $pendingOrderEmailConfirmationMock;

    /**
     * @var MockObject
     */
    private $sessionMock;

    /**
     * @var MockObject
     */
    private $orderRepositoryMock;

    /**
     * @var MockObject
     */
    private $orderManagementMock;

    /**
     * @var MockObject
     */
    private $loggerMock;

    /**
     * @var MockObject
     */
    private $gatewayConfigMock;

    /**
     * @var MockObject
     */
    private $resultFactoryMock;

    /**
     * @var MockObject
     */
    private $requestMock;

    /**
     * @var MockObject
     */
    private $commandManagerPoolMock;

    /**
     * @var MockObject
     */
    private $processServiceMock;

    /**
     * @var Index
     */
    private $redirectIndex;

    /**
     * @var MockObject
     */
    private $jsonMock;

    /**
     * @var MockObject
     */
    private $orderFactoryMock;

    /**
     * @var MockObject
     */
    private $paymentProvidersData;

    /**
     * @var MockObject
     */
    private            $ProviderForm;
    private MockObject $resultMock;

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
        $this->sessionMock                       = $this->getSimpleMock(Session::class);
        $this->orderRepositoryMock               = $this->getSimpleMock(OrderRepositoryInterface::class);
        $this->orderManagementMock               = $this->getSimpleMock(OrderManagementInterface::class);
        $this->loggerMock                        = $this->getSimpleMock(LoggerInterface::class);
        $this->gatewayConfigMock                 = $this->getSimpleMock(Config::class);
        $this->resultFactoryMock                 = $this->getSimpleMock(ResultFactory::class);
        $this->resultMock                        = $this->getSimpleMock(
            \Magento\Framework\Controller\Result\Json::class
        );
        $this->resultFactoryMock
            ->method('create')
            ->willReturn($this->resultMock);

        $this->requestMock            = $this->getSimpleMock(RequestInterface::class);
        $this->commandManagerPoolMock = $this->getSimpleMock(CommandManagerPoolInterface::class);
        $this->processServiceMock     = $this->getSimpleMock(ProcessService::class);
        $this->paymentProvidersData   = $this->getSimpleMock(PaymentProvidersData::class);
        $this->ProviderForm           = $this->getSimpleMock(ProviderForm::class);

        $this->redirectIndex = new Index(
            $this->pendingOrderEmailConfirmationMock,
            $this->sessionMock,
            $this->orderRepositoryMock,
            $this->orderManagementMock,
            $this->loggerMock,
            $this->gatewayConfigMock,
            $this->resultFactoryMock,
            $this->requestMock,
            $this->commandManagerPoolMock,
            $this->processServiceMock,
            $this->paymentProvidersData,
            $this->ProviderForm
        );

        $this->jsonMock         = $this->getSimpleMock(Json::class);
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

        $this->resultMock->expects($this->once())
            ->method('setData')
            ->with([
                       'success' => false,
                       'message' => 'No payment method selected'
                   ]);

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

        $this->resultMock->expects($this->once())
            ->method('setData')
            ->with([
                       'success' => false,
                       'message' => 'No payment method selected'
                   ]);

        $this->redirectIndex->execute();
    }
}
