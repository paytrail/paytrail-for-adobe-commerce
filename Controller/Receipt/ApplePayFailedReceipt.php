<?php

namespace Paytrail\PaymentService\Controller\Receipt;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Receipt\ProcessPayment;

class ApplePayFailedReceipt implements ActionInterface
{
    public const ORDER_CANCEL_STATUSES  = ["canceled"];

    /**
     * ApplePayFailedReceipt constructor.
     *
     * @param FinnishReferenceNumber $referenceNumber
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private FinnishReferenceNumber $referenceNumber,
        private Session                $session,
        private ProcessPayment         $processPayment,
        private RequestInterface       $request,
        private ResultFactory          $resultFactory,
        private ManagerInterface       $messageManager
    ) {
    }

    /**
     * Order status is manipulated by another callback:
     *
     * @throws \Exception
     * execute method
     */
    public function execute()
    {
        $params = $this->getParamsToProcess($this->request->getParams()['params']);
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $failMessages = $this->processPayment->process($params, $this->session);

        if ($failMessages) {
            foreach ($failMessages as $failMessage) {
                $this->messageManager->addErrorMessage($failMessage);
            }
        }

        return $resultJson->setData([
            'redirectUrl' => 'checkout/cart',
            'message' => __('Apple Pay payment canceled or failed.')
        ]);
    }

    /**
     * Get params for processing order and payment.
     *
     * @param array $params
     * @return string[]
     */
    private function getParamsToProcess($params): array
    {
        $paramsToProcess = [
            'checkout-transaction-id' => '',
            'checkout-account' => '',
            'checkout-method' => '',
            'checkout-algorithm' => '',
            'checkout-timestamp' => '',
            'checkout-nonce' => '',
            'signature' =>  ''
        ];

        foreach ($params as $param) {
            if (array_key_exists($param['name'], $paramsToProcess)) {
                $paramsToProcess[$param['name']] = $param['value'];
            }
        }

        return $paramsToProcess;
    }
}
