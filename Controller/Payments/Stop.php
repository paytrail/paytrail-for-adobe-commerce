<?php
declare(strict_types=1);

namespace Paytrail\PaymentService\Controller\Payments;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionRepositoryInterface;
use Paytrail\PaymentService\Api\SubscriptionLinkRepositoryInterface;
use Psr\Log\LoggerInterface;

class Stop extends Action\Action implements Action\HttpGetActionInterface
{
    protected const STATUS_CLOSED = 'closed';
    protected const ORDER_STATUS = 'pending_payment';

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var SubscriptionRepositoryInterface
     */
    protected $subscriptionRepositoryInterface;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagementInterface;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SubscriptionLinkRepositoryInterface
     */
    protected $subscriptionLinkRepositoryInterface;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param SubscriptionRepositoryInterface $subscriptionRepositoryInterface
     * @param OrderRepositoryInterface $orderRepositoryInterface
     * @param OrderManagementInterface $orderManagementInterface
     * @param LoggerInterface $logger
     * @param SubscriptionLinkRepositoryInterface $subscriptionLinkRepositoryInterface
     */
    public function __construct(
        Context                             $context,
        Session                             $customerSession,
        SubscriptionRepositoryInterface     $subscriptionRepositoryInterface,
        OrderRepositoryInterface            $orderRepositoryInterface,
        OrderManagementInterface            $orderManagementInterface,
        LoggerInterface                     $logger,
        SubscriptionLinkRepositoryInterface $subscriptionLinkRepositoryInterface
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->subscriptionRepositoryInterface = $subscriptionRepositoryInterface;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->orderManagementInterface = $orderManagementInterface;
        $this->logger = $logger;
        $this->subscriptionLinkRepositoryInterface = $subscriptionLinkRepositoryInterface;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $subscriptionId = $this->getRequest()->getParam('payment_id');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $subscription = $this->subscriptionRepositoryInterface->get((int)$subscriptionId);
            $orderIds = $this->subscriptionLinkRepositoryInterface->getOrderIdsBySubscriptionId((int)$subscriptionId);

            foreach ($orderIds as $orderId) {
                $order = $this->orderRepositoryInterface->get($orderId);
                if (!$this->customerSession->getId() || $this->customerSession->getId() != $order->getCustomerId()) {
                    throw new LocalizedException(__('Customer is not authorized for this operation'));
                }
                $subscription->setStatus(self::STATUS_CLOSED);
                if ($order->getStatus() === self::ORDER_STATUS) {
                    $this->orderManagementInterface->cancel($order->getId());
                }
            }

            $this->subscriptionRepositoryInterface->save($subscription);
            $resultRedirect->setPath('paytrail/order/payments');
            $this->messageManager->addSuccessMessage('Subscription stopped successfully');
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageManager->addErrorMessage(__('Unable to stop payment'));
            $resultRedirect->setPath('paytrail/order/payments');
        }

        return $resultRedirect;
    }
}
