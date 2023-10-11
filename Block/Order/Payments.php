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
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\PaymentTokenRepository;
use Paytrail\PaymentService\Api\Data\SubscriptionInterface;
use Paytrail\PaymentService\Model\Recurring\TotalConfigProvider;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\Collection as SubscriptionCollection;
use Paytrail\PaymentService\Model\ResourceModel\Subscription\CollectionFactory;

class Payments extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Paytrail_PaymentService::order/payments.phtml';

    /**
     * Payments constructor.
     *
     * @param Context $context
     * @param CollectionFactory $subscriptionCollectionFactory
     * @param Session $customerSession
     * @param StoreManagerInterface $storeManager
     * @param PaymentTokenRepository $paymentTokenRepository
     * @param SerializerInterface $serializer
     * @param TotalConfigProvider $totalConfigProvider
     * @param MessageManagerInterface $messageManager
     * @param array $data
     */
    public function __construct(
        Context                 $context,
        private CollectionFactory       $subscriptionCollectionFactory,
        private Session                 $customerSession,
        private StoreManagerInterface   $storeManager,
        private PaymentTokenRepository  $paymentTokenRepository,
        private SerializerInterface     $serializer,
        private TotalConfigProvider $totalConfigProvider,
        private MessageManagerInterface $messageManager,
        array                   $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Payments protected constructor.
     *
     * @return void
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
     * Get recurring payments (subscriptions).
     *
     * @return SubscriptionCollection
     */
    public function getRecurringPayments()
    {
        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter('main_table.status', ['active','pending_payment','failed','rescheduled']);

        $collection->getSelect()->join(
            ['link' => 'paytrail_subscription_link'],
            'main_table.entity_id = link.subscription_id'
        )->columns('MAX(link.order_id) as max_id')
            ->group('link.subscription_id');

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
     * Get closed subscriptions.
     *
     * @return SubscriptionCollection
     */
    public function getClosedSubscriptions()
    {
        $collection = $this->subscriptionCollectionFactory->create();
        $collection->addFieldToFilter('main_table.status', SubscriptionInterface::STATUS_CLOSED);

        $collection->getSelect()->join(
            ['link' => 'paytrail_subscription_link'],
            'main_table.entity_id = link.subscription_id'
        )->columns('MAX(link.order_id) as max_id')
            ->group('link.subscription_id');

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
     * Validate date.
     *
     * @param string $date
     * @return string
     */
    public function validateDate($date): string
    {
        $newDate = explode(' ', $date);
        return $newDate[0];
    }

    /**
     * Get recurring payment status name.
     *
     * @param string $recurringPaymentStatus
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

    /**
     * Get current currency.
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCurrentCurrency()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCurrencySymbol();
    }

    /**
     * Get view url.
     *
     * @param Subscription $recurringPayment
     * @return string
     */
    public function getViewUrl($recurringPayment)
    {
        return $this->getUrl('sales/order/view', ['order_id' => $recurringPayment->getOrderId()]);
    }

    /**
     * Prepare layout.
     *
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
     * Get pager html.
     *
     * @return string
     */
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }

    /**
     * Get stop payment url.
     *
     * @param Subscription $recurringPayment
     * @return string
     */
    public function getStopPaymentUrl($recurringPayment)
    {
        return $this->getUrl('paytrail/payments/stop', ['payment_id' => $recurringPayment->getId()]);
    }

    /**
     * Get empty recurring payment message.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getEmptyRecurringPaymentsMessage()
    {
        return __('You have no payments to display.');
    }

    /**
     * Get credit card number.
     *
     * @param Subscription $recurringPayment
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
     * Get add_card request redirect url.
     *
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getAddCardRedirectUrl(): ?string
    {
        $config = $this->configProvider->getConfig();

        return $config['payment']['paytrail']['addcard_redirect_url'] ?? null;
    }

    /**
     * Get previous error.
     *
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
