<?php

namespace Paytrail\PaymentService\Plugin\Invoice;

use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Paytrail\PaymentService\Model\Invoice\InvoiceActivation;
use Paytrail\SDK\Client;

class SubmitShipmentPlugin
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var Client
     */
    private Client $paytrailClient;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param Client $paytrailClient
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Client $paytrailClient
    ) {
        $this->orderRepository = $orderRepository;
        $this->paytrailClient = $paytrailClient;
    }

    /**
     * @param Save $subject
     * @param ResultInterface $result
     * @return ResultInterface
     */
    public function afterExecute(Save $subject, ResultInterface $result): ResultInterface
    {
        $orderId = $subject->getRequest()->getParams()['order_id'];
        $order = $this->orderRepository->get($orderId);
//        if ('paymentMethod' === InvoiceActivate::COLLECTOR_PAYMENT_METHOD_CODE) {
//            $this->paytrailClient->activateInvoice($order->getPayment()->getLastTransId());
//        }

        return $result;
    }
}
