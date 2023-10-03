<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Block\Order;

use Magento\Customer\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\PaymentTokenRepository;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Model\ConfigProvider;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;
use Paytrail\PaymentService\Model\SubscriptionRepository;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection as SubscriptionCollection;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory;

class Payments extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Paytrail_PaymentService::order/payments.phtml';

    /**
     * @var CollectionFactory
     */
    private $subscriptionCollectionFactory;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PaymentTokenRepository
     */
    private $paymentTokenRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var MessageManagerInterface
     */
    private MessageManagerInterface $messageManager;

    /**
     * @var TotalConfigProvider
     */
    private $totalConfigProvider;

    /**
     * @param Context $context
     * @param SubscriptionRepository $subscriptionRepository
     * @param CollectionFactory $subscriptionCollectionFactory
     * @param Session $customerSession
     * @param StoreManagerInterface $storeManager
     * @param PaymentTokenRepository $paymentTokenRepository
     * @param SerializerInterface $serializer
     * @param ConfigProvider $configProvider
     * @param MessageManagerInterface $messageManager
     * @param TotalConfigProvider $totalConfigProvider
     * @param array $data
     */
    public function __construct(
        Context                 $context,
        SubscriptionRepository  $subscriptionRepository,
        CollectionFactory       $subscriptionCollectionFactory,
        Session                 $customerSession,
        StoreManagerInterface   $storeManager,
        PaymentTokenRepository  $paymentTokenRepository,
        SerializerInterface     $serializer,
        ConfigProvider          $configProvider,
        MessageManagerInterface $messageManager,
        TotalConfigProvider     $totalConfigProvider,
        array                   $data = []
    ) {
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionCollectionFactory = $subscriptionCollectionFactory;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->serializer = $serializer;
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
        $this->messageManager = $messageManager;
        $this->totalConfigProvider = $totalConfigProvider;
    }

    /**
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->pageConfig->getTitle()->set(__('My Subscriptions'));
    }

    /**
     * @return bool
     */
    public function isSubscriptionsEnabled(): bool
    {
        return $this->totalConfigProvider->isRecurringPaymentEnabled();
    }

    /**
     * @return SubscriptionCollection
     */
    public function getRecurringPayments()
    {
        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter('main_table.status', ['active','pending_payment','failed','rescheduled']);

        $collection->getSelect()->join(
            ['link' => 'paytrail_subscription_link'],
            'main_table.entity_id = link.subscription_id'
        )->columns(array('MAX(link.order_id) as max_id')
        )->group('link.subscription_id');

        $collection->getSelect()->join(
            ['so' => 'sales_order'],
            'link.order_id = so.entity_id',
            ['main_table.entity_id','so.base_grand_total']
        );
        $collection->getSelect()->join(
            ['rpp' => 'recurring_payment_profiles'],
            'main_table.recurring_profile_id = rpp.profile_id',
            'name'
        );

        $collection->addFieldToFilter('main_table.customer_id', $this->customerSession->getId());

        return $collection;
    }

    /**
     * @return SubscriptionCollection
     */
    public function getClosedSubscriptions()
    {
        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter('main_table.status', SubscriptionInterface::STATUS_CLOSED);

        $collection->getSelect()->join(
            ['link' => 'paytrail_subscription_link'],
            'main_table.entity_id = link.subscription_id'
        )->columns(array('MAX(link.order_id) as max_id')
        )->group('link.subscription_id');

        $collection->getSelect()->join(
            ['so' => 'sales_order'],
            'link.order_id = so.entity_id',
            ['main_table.entity_id','so.base_grand_total']
        );
        $collection->getSelect()->join(
            ['rpp' => 'recurring_payment_profiles'],
            'main_table.recurring_profile_id = rpp.profile_id',
            'name'
        );

        $collection->addFieldToFilter('main_table.customer_id', $this->customerSession->getId());

        return $collection;
    }

    /**
     * @param $_order
     * @return string
     * @throws NoSuchEntityException
     */
    public function validateDate($date): string
    {
        $newDate = explode(' ',$date);
        return $newDate[0];
    }

    /**
     * @param $recurringPaymentStatus
     * @return \Magento\Framework\Phrase|string
     */
    public function getRecurringPaymentStatusName($recurringPaymentStatus)
    {
        switch ($recurringPaymentStatus) {
            case 'active':
                return __('Active');
            case 'paid':
                return __('Paid');
            case 'failed':
                return __('Failed');
            case 'pending_payment':
                return __('Pending Payment');
            case 'rescheduled':
                return __('Rescheduled');
            case 'closed':
                return __('Closed');
        }
        return '';
    }

    public function getCurrentCurrency()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCurrencySymbol();
    }

    /**
     * @param $recurringPayment
     * @return string
     */
    public function getViewUrl($recurringPayment)
    {
        return $this->getUrl('sales/order/view', ['order_id' => $recurringPayment->getOrderId()]);
    }

    /**
     * @return $this|Payments
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->getRecurringPayments()) {
            $pager = $this->getLayout()->createBlock(
                \Magento\Theme\Block\Html\Pager::class,
                'checkout.order.recurring.payments.pager'
            )->setCollection(
                $this->getRecurringPayments()
            );
            $this->setChild('pager', $pager);
            $this->getRecurringPayments()->load();
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * @return string
     */
    public function getStopPaymentUrl($recurringPayment)
    {
        return $this->getUrl('paytrail/payments/stop', ['payment_id' => $recurringPayment->getId()]);
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getEmptyRecurringPaymentsMessage()
    {
        return __('You have no payments to display.');
    }

    /**
     * @param $recurringPayment
     * @return string
     */
    public function getCardNumber($recurringPayment)
    {
        $token = $this->paymentTokenRepository->getById($recurringPayment->getSelectedToken());
        if ($token) {
            $tokenDetails = $this->serializer->unserialize($token->getTokenDetails());
            return '**** **** **** ' . $tokenDetails['maskedCC'];
        }

        return '';
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getAddCardRedirectUrl(): ?string
    {
        $config = $this->configProvider->getConfig();

        return $config['payment']['paytrail']['addcard_redirect_url'] ?? null;
    }

    /**
     * @return Phrase|null
     * @throws NoSuchEntityException
     */
    public function getPreviousError(): ?Phrase
    {
        $config = $this->configProvider->getConfig();

        $previousError = $config['payment']['paytrail']['previous_error'] ?? null;
        if ($previousError) {
            $this->messageManager->addErrorMessage(__($previousError));
        }

        return $previousError;
    }
}
