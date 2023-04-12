<?php

namespace Paytrail\PaymentService\Model\PaymentMethod;

use Magento\Framework\Locale\Resolver;

class PaymentMethodDataProvider
{
    /**
     * @param Resolver $localeResolver
     */
    public function __construct(
        private Resolver $localeResolver,
    ) {
    }

    /**
     * @return string
     */
    public function getStoreLocaleForPaymentProvider()
    {
        $locale = 'EN';
        if ($this->localeResolver->getLocale() === 'fi_FI') {
            $locale = 'FI';
        }
        if ($this->localeResolver->getLocale() === 'sv_SE') {
            $locale = 'SV';
        }
        return $locale;
    }
}
