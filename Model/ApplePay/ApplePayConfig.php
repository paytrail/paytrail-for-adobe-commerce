<?php

namespace Paytrail\PaymentService\Model\ApplePay;

use Magento\Framework\View\Asset\Repository;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\SDK\Model\Provider;

class ApplePayConfig
{
    /**
     * ApplePayConfig constructor.
     *
     * @param Config $gatewayConfig
     * @param Repository $assetRepository
     */
    public function __construct(
        private Config     $gatewayConfig,
        private Repository $assetRepository
    ) {
    }

    public function canApplePay(): bool
    {
        if ($this->isSafariBrowser() && $this->gatewayConfig->isApplePayEnabled()) {
            return true;
        }
    
        return false;
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

    /**
     * Checks if user browser is Safari.
     *
     * @return bool
     */
    private function isSafariBrowser(): bool
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        if (stripos($user_agent, 'Chrome') !== false && stripos($user_agent, 'Safari') !== false) {
            return false;
        } elseif (stripos($user_agent, 'Safari') !== false) {
            return true;
        } else {
            return false;
        }
    }
}
