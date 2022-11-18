<?php

namespace Paytrail\PaymentService\Model\Company;

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

    private $companyRepository;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param $companyRepository
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
                                    $companyRepository = null
    ) {
        $this->customerRepository = $customerRepository;
        $this->companyRepository = $companyRepository;
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
            $customer->setCompanyName($this->companyRepository->get($companyId)->getCompanyName());

            return $customer;
        }

        return $customer;
    }
}
