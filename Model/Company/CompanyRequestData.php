<?php

namespace Paytrail\PaymentService\Model\Company;

use Magento\Customer\Model\Session;
use Magento\Framework\Module\Manager;

/**
 * Class CompanyRequestData
 */
class CompanyRequestData
{
    /**
     * @var Session
     */
    private Session $customerSession;

    /**
     * @var CompanyCommerceRequestData
     */
    private CompanyCommerceRequestData $companyCommerceRequestData;

    /**
     * @var Manager
     */
    private Manager $moduleManager;

    /**
     * @param Session $customerSession
     * @param CompanyCommerceRequestData $companyCommerceRequestData
     * @param Manager $moduleManager
     */
    public function __construct(
        Session                    $customerSession,
        CompanyCommerceRequestData $companyCommerceRequestData,
        Manager                    $moduleManager
    )
    {
        $this->customerSession = $customerSession;
        $this->companyCommerceRequestData = $companyCommerceRequestData;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param $customer
     * @param $billingAddress
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setCompanyRequestData($customer, $billingAddress)
    {
        if ($billingAddress->getCompany()) {
            $customer->setCompanyName($billingAddress->getCompany());

            return $customer;
        }
        if ($this->customerSession->isLoggedIn() && $this->moduleManager->isEnabled('Magento_Company')) {
            $this->companyCommerceRequestData->setCompanyCommerceRequestData($customer);
        }

        return $customer;
    }
}
