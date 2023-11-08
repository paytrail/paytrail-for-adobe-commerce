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
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Email\Order\PendingOrderEmailConfirmation;
use Paytrail\PaymentService\Model\Receipt\ProcessService;
use Paytrail\PaymentService\Model\Ui\DataProvider\PaymentProvidersData;
use Paytrail\SDK\Model\Provider;
use Paytrail\SDK\Response\PaymentResponse;
use Psr\Log\LoggerInterface;

class Index implements ActionInterface
{
    /**
     * @var $errorMsg
     */
    protected $errorMsg = null;

    /**
     * Index constructor.
     *
     * @param PendingOrderEmailConfirmation $pendingOrderEmailConfirmation
     * @param Session $checkoutSession
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderManagementInterface $orderManagementInterface
     * @param LoggerInterface $logger
     * @param Config $gatewayConfig
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param ProcessService $processService
     * @param PaymentProvidersData $paymentProvidersData
     */
    public function __construct(
        private readonly PendingOrderEmailConfirmation $pendingOrderEmailConfirmation,
        private readonly Session                       $checkoutSession,
        private readonly OrderRepositoryInterface      $orderRepositoryInterface,
        private readonly OrderManagementInterface      $orderManagementInterface,
        private readonly LoggerInterface               $logger,
        private readonly Config                        $gatewayConfig,
        private readonly ResultFactory                 $resultFactory,
        private readonly RequestInterface              $request,
        private readonly CommandManagerPoolInterface   $commandManagerPool,
        private readonly ProcessService                $processService,
        private readonly PaymentProvidersData          $paymentProvidersData
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

                $selectedPaymentMethodId = $this->payment($selectedPaymentMethodRaw);

                if (empty($selectedPaymentMethodId)) {
                    $this->errorMsg = __('No payment method selected');
                    throw new LocalizedException(__('No payment method selected'));
                }

                $order        = $this->checkoutSession->getLastRealOrder();
                $responseData = $this->getResponseData($order, $selectedPaymentMethodId);
                $formData     = $this->getFormFields(
                    $responseData,
                    $selectedPaymentMethodId
                );
                $formAction   = $this->getFormAction(
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
                                                    'success'  => true,
                                                    'data'     => 'redirect',
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
                                                'data'    => $block->toHtml(),
                                            ]);
            }
        } catch (\Exception $e) {
            // Error will be handled below
            $this->logger->error($e->getMessage());
        }

        if ($order && $order->getId()) {
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
     *
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
     *
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
     * @param Order $order
     * @param string $paymentMethod
     *
     * @return mixed
     * @throws CheckoutException
     * @throws \Magento\Framework\Exception\NotFoundException
     * @throws \Magento\Payment\Gateway\Command\CommandException
     */
    protected function getResponseData($order, $paymentMethod)
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response        = $commandExecutor->executeByCode(
            'payment',
            null,
            [
                'order'          => $order,
                'payment_method' => $paymentMethod
            ]
        );

        if ($response['error']) {
            $this->errorMsg = ($response['error']);
            $this->processService->processError($response['error']);
        }

        return $response["data"];
    }
}
