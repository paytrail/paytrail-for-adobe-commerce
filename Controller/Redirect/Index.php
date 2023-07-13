<?php

namespace Paytrail\PaymentService\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\ConfigProvider;
use Paytrail\PaymentService\Model\Email\Order\PendingOrderEmailConfirmation;
use Paytrail\SDK\Model\Provider;
use Paytrail\SDK\Response\PaymentResponse;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Index implements ActionInterface
{
    protected $urlBuilder;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ApiData
     */
    protected $apiData;

    /**
     * @var paytrailHelper
     */
    protected $paytrailHelper;

    /**
     * @var Config
     */
    protected $gatewayConfig;

    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var PendingOrderEmailConfirmation
     */
    private PendingOrderEmailConfirmation $pendingOrderEmailConfirmation;

    /**
     * Index constructor.
     *
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderManagementInterface $orderManagementInterface
     * @param LoggerInterface $logger
     * @param ApiData $apiData
     * @param paytrailHelper $paytrailHelper
     * @param Config $gatewayConfig
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param PendingOrderEmailConfirmation $pendingOrderEmailConfirmation
     */
    public function __construct(
        protected PendingOrderEmailConfirmation $pendingOrderEmailConfirmation,
        protected Session                     $checkoutSession,
        protected OrderRepositoryInterface    $orderRepositoryInterface,
        protected OrderManagementInterface    $orderManagementInterface,
        protected LoggerInterface             $logger,
        protected paytrailHelper              $paytrailHelper,
        protected Config                      $gatewayConfig,
        protected ResultFactory               $resultFactory,
        protected RequestInterface            $request,
        protected CommandManagerPoolInterface $commandManagerPool
    ) {
    }

    /**
     * Execute function
     *
     * @return mixed
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $order = null;
        try {
            if ($this->request->getParam('is_ajax')) {
                $selectedPaymentMethodRaw = $this->request->getParam(
                    'preselected_payment_method_id'
                );
                $selectedPaymentMethodId = preg_replace(
                    '/' . ConfigProvider::ID_INCREMENT_SEPARATOR . '[0-9]{1,3}$/',
                    '',
                    $selectedPaymentMethodRaw
                );

                if (empty($selectedPaymentMethodId)) {
                    $this->errorMsg = __('No payment method selected');
                    throw new LocalizedException(__('No payment method selected'));
                }

                $order = $this->checkoutSession->getLastRealOrder();
                $responseData = $this->getResponseData($order);
                $formData = $this->getFormFields(
                    $responseData,
                    $selectedPaymentMethodId
                );
                $formAction = $this->getFormAction(
                    $responseData,
                    $selectedPaymentMethodId
                );

                // send order confirmation for pending order
                if ($responseData) {
                    $this->pendingOrderEmailConfirmation->pendingOrderEmailSend($order);
                }

                if ($this->gatewayConfig->getSkipBankSelection()) {
                    $redirect_url = $responseData->getHref();

                    return $resultJson->setData([
                        'success' => true,
                        'data' => 'redirect',
                        'redirect' => $redirect_url
                    ]);
                }

                $block = $this->resultFactory->create(ResultFactory::TYPE_PAGE)
                    ->getLayout()
                    ->createBlock(\Paytrail\PaymentService\Block\Redirect\Paytrail::class)
                    ->setUrl($formAction)
                    ->setParams($formData);

                return $resultJson->setData([
                    'success' => true,
                    'data' => $block->toHtml(),
                ]);
            }
        } catch (\Exception $e) {
            // Error will be handled below
            $this->logger->error($e->getMessage());
        }

        if ($order->getId()) {
            $this->orderManagementInterface->cancel($order->getId());
            $order->addCommentToStatusHistory(
                __('Order canceled. Failed to redirect to Paytrail Payment Service.')
            );
            $this->orderRepositoryInterface->save($order);
        }

        $this->checkoutSession->restoreQuote();

        return $resultJson->setData([
            'success' => false,
            'message' => $this->errorMsg
        ]);
    }

    /**
     * GetFormFields function
     *
     * @param PaymentResponse $responseData
     * @param string $paymentMethodId
     * @return array
     */
    protected function getFormFields($responseData, $paymentMethodId = null): array
    {
        $formFields = [];

        /** @var Provider $provider */
        foreach ($responseData->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                foreach ($provider->getParameters() as $parameter) {
                    $formFields[$parameter->name] = $parameter->value;
                }
            }
        }

        return $formFields;
    }

    /**
     * GetFormAction function
     *
     * @param PaymentResponse $responseData
     * @param string $paymentMethodId
     * @return string
     */
    protected function getFormAction($responseData, $paymentMethodId = null): string
    {
        $returnUrl = '';

        /** @var Provider $provider */
        foreach ($responseData->getProviders() as $provider) {
            if ($provider->getId() == $paymentMethodId) {
                $returnUrl = $provider->getUrl();
            }
        }

        return $returnUrl;
    }

    /**
     * GetResponseData function
     *
     * @param \Magento\Sales\Model\Order $order
     * @return mixed
     * @throws CheckoutException
     * @throws \Magento\Framework\Exception\NotFoundException
     * @throws \Magento\Payment\Gateway\Command\CommandException
     */
    protected function getResponseData($order)
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode(
            'payment',
            null,
            [
                'order' => $order
            ]
        );

        if ($response['error']) {
            $this->errorMsg = ($response['error']);
            $this->paytrailHelper->processError($response['error']);
        }

        return $response["data"];
    }
}
