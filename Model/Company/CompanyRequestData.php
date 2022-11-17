<?php

namespace Paytrail\PaymentService\Model\Company;

use Magento\Customer\Model\Session;
use Magento\Framework\Module\Manager;
use Paytrail\PaymentService\Model\Company\CompanyCommerceRequestData\Proxy as CompanyCommerceRequestData;

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
     * @var Manager
     */
    private Manager $moduleManager;

    /**
     * @var CompanyCommerceRequestData
     */
    private CompanyCommerceRequestData $companyCommerceRequestData;

    /**
     * @param Session $customerSession
     * @param Manager $moduleManager
     * @param CompanyCommerceRequestData $companyCommerceRequestData
     */
    public function __construct(
        Session                    $customerSession,
        Manager                    $moduleManager,
        CompanyCommerceRequestData $companyCommerceRequestData
    )
    {
        $this->customerSession = $customerSession;
        $this->moduleManager = $moduleManager;
        $this->companyCommerceRequestData = $companyCommerceRequestData;
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
