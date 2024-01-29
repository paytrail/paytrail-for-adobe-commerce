<?php

namespace Paytrail\PaymentService\Model\ApplePay;

use Magento\Framework\View\Asset\Repository;
use Paytrail\SDK\Model\Provider;

class ApplePayConfig
{
    /**
     * ApplePayConfig constructor.
     *
     */
    public function __construct(
        private Repository $assetRepository
    ) {
    }

    /**
     * Adds Apple Pay method data into payment methods groups.
     *
     * @param array $groupMethods
     * @return array
     */
    public function addApplePayPaymentMethod(array $groupMethods): array
    {
        $applePayPaymentData = [
            'id' => 'applepay',
            'name' => 'Apple Pay payment method',
            'icon' => $this->assetRepository->getUrl('Paytrail_PaymentService::images/apple-icon.png'),
            'svg' => $this->assetRepository->getUrl('Paytrail_PaymentService::images/apple-icon.svg'),
            'providers' => [0 => $this->getApplePayProviderData()]
        ];

        $groupMethods[] = $applePayPaymentData;

        return $groupMethods;
    }

    /**
     * Get Apple Pay provider data for payment render.
     *
     * @return Provider
     */
    private function getApplePayProviderData(): Provider
    {
        $applePayProvider = new Provider();

        $applePayProvider
            ->setId('applepay')
            ->setGroup('applepay')
            ->setUrl(null)
            ->setIcon($this->assetRepository->getUrl('Paytrail_PaymentService::images/apple-pay-logo.png'))
            ->setName('Apple Pay')
            ->setParameters(null)
            ->setSvg($this->assetRepository->getUrl('Paytrail_PaymentService::images/apple-pay-logo.svg'));

        return $applePayProvider;
    }
}
