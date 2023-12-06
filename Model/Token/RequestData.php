<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Model\Token;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\SDK\Request\AbstractPaymentRequest;

class RequestData
{
    /**
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param PaytrailLogger $paytrailLogger
     */
    public function __construct(
        private readonly OrderRepositoryInterface        $orderRepositoryInterface,
        private readonly PaymentTokenManagementInterface $paymentTokenManagement,
        private readonly PaytrailLogger                  $paytrailLogger
    ) {
    }

    /**
     * Set specific token payment parameters.
     *
     * @param AbstractPaymentRequest $paytrailPayment
     * @param Order $order
     * @param string $tokenId
     * @param $rcustomer
     *
     * @return AbstractPaymentRequest
     */
    public function setTokenPaymentRequestData(
        AbstractPaymentRequest $paytrailPayment,
        Order                  $order,
        string                 $tokenId,
                               $rcustomer
    ): AbstractPaymentRequest {
        $customerId = $rcustomer->getId();
        $this->paytrailLogger->logCheckoutData('request', 'info', 'we have customer:' . $customerId);

        // set token
        $token        = $this->getPaymentToken($tokenId, $customerId);
        $paymentToken = $token->getGatewayToken();

        $paymentExtensionAttributes = $order->getPayment()->getExtensionAttributes();
        $paymentExtensionAttributes->setVaultPaymentToken($token);
        $this->orderRepositoryInterface->save($order);

        $this->paytrailLogger->logCheckoutData('request', 'info', 'we have token:' . $paymentToken);
        $paytrailPayment->setToken($paymentToken);

        // Log payment data
        $this->paytrailLogger->logCheckoutData('request', 'info', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * Get payment token.
     *
     * @param string $tokenHash
     * @param string $customerId
     *
     * @return PaymentTokenInterface|null
     */
    private function getPaymentToken($tokenHash, $customerId): ?PaymentTokenInterface
    {
        return $this->paymentTokenManagement->getByPublicHash($tokenHash, $customerId);
    }
}
