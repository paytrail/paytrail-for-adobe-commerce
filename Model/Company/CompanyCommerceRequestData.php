<?php

namespace Paytrail\PaymentService\Model\Company;

use Magento\Company\Api\CompanyRepositoryInterface\Proxy as CompanyRepositoryProxy;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * Class CompanyCommerceRequestData
 */
class CompanyCommerceRequestData
{
    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;
    private CompanyRepositoryProxy $companyRepositoryProxy;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param CompanyRepositoryProxy $companyRepositoryProxy
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CompanyRepositoryProxy $companyRepositoryProxy
    )
    {
        $this->customerRepository = $customerRepository;
        $this->companyRepositoryProxy = $companyRepositoryProxy;
    }

    /**
     * @param $customer
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setCompanyCommerceRequestData($customer)
    {
        $companyId = $this->customerRepository->get($customer->getEmail())
            ->getExtensionAttributes()
            ->getCompanyAttributes()
            ->getCompanyId();
        if ($companyId) {
            $customer->setCompanyName($this->companyRepositoryProxy->get($companyId)->getCompanyName());

            return $customer;
        }

        return $customer;
    }
}
