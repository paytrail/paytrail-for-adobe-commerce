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

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param $companyRepository
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        $object
    ) {
        $this->customerRepository = $customerRepository;
        $this->_object = $object;
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

            $customer->setCompanyName($this->_object->get($companyId)->getCompanyName());

            return $customer;
        }

        return $customer;
    }
}
