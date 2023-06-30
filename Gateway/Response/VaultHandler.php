<?php

namespace Paytrail\PaymentService\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;


class VaultHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $transaction = $this->subjectReader->readTransaction($response);
        $payment = $paymentDO->getPayment();

// add vault payment token entity to extension attributes
        $paymentToken = $this->getVaultPaymentToken($transaction);
        if (null !== $paymentToken) {
            $extensionAttributes = $this->getExtensionAttributes($payment);
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        }
    }

    /**
     * @param Transaction $transaction
     * @return PaymentTokenInterface|null
     */
    protected function getVaultPaymentToken(Transaction $transaction)
    {
// Check token existing in gateway response
        $token = $transaction->creditCardDetails->token;
        if (empty($token)) {
            return null;
        }

        /** @var PaymentTokenInterface $paymentToken */
        $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
        $paymentToken->setGatewayToken($token);
        $paymentToken->setExpiresAt($this->getExpirationDate($transaction));

        $paymentToken->setTokenDetails(
            $this->convertDetailsToJSON(
                [
                    'type' => $this->getCreditCardType($transaction->creditCardDetails->cardType),
                    'maskedCC' => $transaction->creditCardDetails->last4,
                    'expirationDate' => $transaction->creditCardDetails->expirationDate
                ]
            )
        );

        return $paymentToken;
    }
}
