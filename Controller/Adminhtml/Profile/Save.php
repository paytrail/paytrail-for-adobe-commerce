<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Profile;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Save extends Action implements HttpPostActionInterface
{
    const DELETE_QUOTE_AFTER = 'checkout/cart/delete_quote_after';

    /**
     * @var \Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface
     */
    private $profileRepo;

    /**
     * @var \Paytrail\PaymentService\Api\Data\RecurringProfileInterfaceFactory
     */
    private $factory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        Context $context,
        \Paytrail\PaymentService\Api\RecurringProfileRepositoryInterface $profileRepository,
        \Paytrail\PaymentService\Api\Data\RecurringProfileInterfaceFactory $factory,
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->profileRepo = $profileRepository;
        $this->factory = $factory;
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        $id = $this->getRequest()->getParam('profile_id');
        if ($id) {
            $profile = $this->profileRepo->get($id);
        } else {
            $profile = $this->factory->create();
        }

        $data = $this->getRequestData();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if($this->validateProfile($data) === false) {
            $this->messageManager->addErrorMessage(
                "Schedule can't be saved due to the profile's payment period exceeding your store's quote lifetime.
                Please make sure the quote lifetime is longer than your profile's payment schedule in days.
                See Stores->Configuration->Sales->Checkout->Shopping Cart->Quote Lifetime (days)");
            $resultRedirect->setPath('recurring_payments/profile/edit', ['id' => $id]);
            return $resultRedirect;
        }

        $profile->setData($data);
        try {
            $this->profileRepo->save($profile);
            $resultRedirect->setPath('recurring_payments/profile');
        } catch (CouldNotSaveException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect->setPath('recurring_payments/profile/edit', ['id' => $id]);
        }

        return $resultRedirect;
    }

    private function getRequestData()
    {
        $data = $this->getRequest()->getParams();

        if (isset($data['interval_period']) && isset($data['interval_unit'])) {
            $schedule = [
                'interval' => $data['interval_period'],
                'unit' => $data['interval_unit'],
            ];

            $data['schedule'] = $this->serializer->serialize($schedule);
        }

        if (!$data['profile_id']) {
            unset($data['profile_id']);
        }

        return $data;
    }

   private function validateProfile($data)
   {
       $quoteLimit = $this->scopeConfig->getValue(
           self::DELETE_QUOTE_AFTER,
           \Magento\Store\Model\ScopeInterface::SCOPE_STORE
       );
       switch ($data['interval_unit']) {
           case 'D':
               $days = 1;
               break;
           case 'W':
               $days = 7;
               break;
           case 'M':
               $days = 30.436875;
               break;
           case 'Y':
               $days = 365.2425;
               break;
       }
       if($data['interval_period'] * $days > $quoteLimit) {
           return false;
       }
       return true;
   }
}
