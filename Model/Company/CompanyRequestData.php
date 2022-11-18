<?php

namespace Paytrail\PaymentService\Model\Company;

/**
 * Class CompanyRequestData
 */
class CompanyRequestData
{
    /**
     * @param $customer
     * @param $billingAddress
     * @return mixed
     */
    public function setCompanyRequestData($customer, $billingAddress)
    {
        if ($billingAddress->getCompany()) {
            $customer->setCompanyName($billingAddress->getCompany());

            return $customer;
        }

        return $customer;
    }
}
