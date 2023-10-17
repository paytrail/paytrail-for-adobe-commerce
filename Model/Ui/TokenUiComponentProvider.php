<?php
namespace Paytrail\PaymentService\Model\Ui;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;
use Paytrail\PaymentService\Gateway\Config\Config;

class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
    /**
     * TokenUiComponentProvider constructor.
     *
     * @param TokenUiComponentInterfaceFactory $componentFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private TokenUiComponentInterfaceFactory $componentFactory,
        private SerializerInterface              $serializer
    ) {
    }

    /**
     * Get UI component for token
     *
     * @param PaymentTokenInterface $paymentToken
     * @return TokenUiComponentInterface
     */
    public function getComponentForToken(PaymentTokenInterface $paymentToken)
    {
        $jsonDetails = $this->serializer->unserialize($paymentToken->getTokenDetails() ?: '{}');

        return $this->componentFactory->create(
            [
                'config' => [
                    'code' => Config::VAULT_CODE,
                    TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
                    TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash()
                ],
                'name' => 'Paytrail_PaymentService/js/view/payment/method-renderer/paytrail-vault'
            ]
        );
    }
}
