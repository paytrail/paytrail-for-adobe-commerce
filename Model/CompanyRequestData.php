<?php

namespace Paytrail\PaymentService\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;

/**
 * Class CompanyRequestData
 */
class CompanyRequestData
{
    /**
     * @var Session
     */
    private Session $customerSession;
    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
    )
    {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
    }

    public function setCompanyRequestData($customer, $billingAddress)
    {
        if (!$customer->getCompanyName() && !$this->customerSession->isLoggedIn()) {
            $customer->setCompanyName($billingAddress->getCompany());
        }

        return $customer;
    }
}
