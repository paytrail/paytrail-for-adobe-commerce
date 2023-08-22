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
     * @return AddCardFormRequest
     */
    public function setAddCardFormRequestData(AddCardFormRequest $addCardFormRequest): AddCardFormRequest
    {
        $datetime    = new \DateTime();
        $saveCardUrl = $this->gatewayConfig->getSaveCardUrl();

        $addCardFormRequest->setCheckoutAccount($this->gatewayConfig->getMerchantId());
        $addCardFormRequest->setCheckoutAlgorithm($this->gatewayConfig->getCheckoutAlgorithm());
        $addCardFormRequest->setCheckoutRedirectSuccessUrl($this->urlDataProvider->getCallbackUrl($saveCardUrl));
        $addCardFormRequest->setCheckoutRedirectCancelUrl($this->urlDataProvider->getCallbackUrl($saveCardUrl));
        $addCardFormRequest->setLanguage($this->gatewayConfig->getStoreLocaleForPaymentProvider());
        $addCardFormRequest->setCheckoutMethod('POST');
        $addCardFormRequest->setCheckoutTimestamp($datetime->format('Y-m-d\TH:i:s.u\Z'));
        $addCardFormRequest->setCheckoutNonce(uniqid(true));

        return $addCardFormRequest;
    }

}
