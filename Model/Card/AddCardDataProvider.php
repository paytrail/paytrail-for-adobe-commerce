<?php

namespace Paytrail\PaymentService\Model\Card;

use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\UrlDataProvider;
use Paytrail\SDK\Request\AddCardFormRequest;

class AddCardDataProvider
{
    /**
     * AddCardDataProvider constructor.
     *
     * @param Config $gatewayConfig
     * @param UrlDataProvider $urlDataProvider
     */
    public function __construct(
        private Config $gatewayConfig,
        private UrlDataProvider $urlDataProvider
    ) {
    }

    /**
     * Set add card form request data.
     *
     * @param AddCardFormRequest $addCardFormRequest
     * @param array $buildSubject
     * @return AddCardFormRequest
     */
    public function setAddCardFormRequestData(AddCardFormRequest $addCardFormRequest, $buildSubject): AddCardFormRequest
    {
        $datetime    = new \DateTime();
        $saveCardUrl = $this->gatewayConfig->getSaveCardUrl();

        $addCardFormRequest->setCheckoutAccount($this->gatewayConfig->getMerchantId());
        $addCardFormRequest->setCheckoutAlgorithm($this->gatewayConfig->getCheckoutAlgorithm());
        $addCardFormRequest->setLanguage($this->gatewayConfig->getStoreLocaleForPaymentProvider());
        $addCardFormRequest->setCheckoutMethod('POST');
        $addCardFormRequest->setCheckoutTimestamp($datetime->format('Y-m-d\TH:i:s.u\Z'));
        $addCardFormRequest->setCheckoutNonce(uniqid(true));

        if ($buildSubject['custom_redirect_url']) {
            $addCardFormRequest->setCheckoutRedirectSuccessUrl(
                $this->getCustomRedirectUrl($buildSubject['custom_redirect_url'], $saveCardUrl)
            );
            $addCardFormRequest->setCheckoutRedirectCancelUrl(
                $this->getCustomRedirectUrl($buildSubject['custom_redirect_url'], $saveCardUrl)
            );
        } else {
            $addCardFormRequest->setCheckoutRedirectSuccessUrl($this->urlDataProvider->getCallbackUrl($saveCardUrl));
            $addCardFormRequest->setCheckoutRedirectCancelUrl($this->urlDataProvider->getCallbackUrl($saveCardUrl));
        }

        return $addCardFormRequest;
    }

    /**
     * Get custom redirect url after adding card.
     *
     * @param string $customRedirectUrl
     * @param string $saveCardUrl
     * @return string
     */
    private function getCustomRedirectUrl($customRedirectUrl, $saveCardUrl): string
    {
        $saveCardUrl = $this->urlDataProvider->getCallbackUrl($saveCardUrl);

        return $saveCardUrl . '?custom_redirect_url=' . $customRedirectUrl;
    }
}
