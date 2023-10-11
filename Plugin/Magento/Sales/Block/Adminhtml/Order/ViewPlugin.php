<?php

namespace Paytrail\PaymentService\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Model\Order\OrderActivation;
use Paytrail\PaymentService\Model\Invoice\Activation\Flag;
use Paytrail\PaymentService\Model\ReceiptDataProvider;
use Paytrail\PaymentService\Setup\Patch\Data\InstallPaytrail;

class ViewPlugin
{
    /**
     * ViewPlugin constructor.
     *
     * @param OrderActivation $orderActivation
     * @param RequestInterface $request
     * @param UrlInterface $url
     */
    public function __construct(
        private OrderActivation  $orderActivation,
        private RequestInterface $request,
        private UrlInterface     $url,
        private \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory $transactionFactory,
        private \Magento\Framework\Registry $registry
    ) {
    }

    /**
     * Before setLayout plugin.
     *
     * @param View $view
     * @return void
     */
    public function beforeSetLayout(View $view)
    {
        $orderId = (int)$this->request->getParam('order_id');
        if ($this->orderActivation->isCanceled($orderId)) {
            $view->addButton('rescueOrder', [
                'label' => __('Restore Order'),
                'onclick' =>
                    "confirmSetLocation('Are you sure you want to make changes to this order?'
                    , '{$this->getRestoreOrderUrl($orderId)}')",
                'onclick' => "confirmSetLocation('Are you sure you want to make changes to this order?', '{$this->getControllerUrl('paytrail_payment/order/restore', $orderId)}')",
            ]);
        }
        if ($this->isManualInvoiceOrder()) {
            $view->addButton('manualInvoice', [
                'label' => __('Activate Invoice'),
                'onclick' => "confirmSetLocation('Are you sure you want to activate invoice for this order?', '{$this->getControllerUrl('paytrail_payment/order/activate', $orderId)}')",
            ]);
        }
    }

    /**
     * Get restore order url.
     *
     * @param string $orderId
     * @return string
     */
    public function getRestoreOrderUrl($orderId): string
    {
        return $this->url->getUrl(
            'paytrail_payment/order/restore',
            [
                'order_id' => $orderId
            ]
        );
    }

    /**
     * Restore order URL getter
     *
     * @param string $path
     * @param string|int $orderId
     *
     * @return string
     */
    public function getControllerUrl($path, $orderId): string
    {
        return $this->url->getUrl(
            $path,
            [
                'order_id' => $orderId
            ]
        );
    }

    /**
     * @return bool
     */
    private function isManualInvoiceOrder()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->registry->registry('sales_order');
        if (!$order || !$this->isStatusValid($order)) {
            return false;
        }

        $transactions = $this->transactionFactory->create();
        $transactions->setOrderFilter($order->getId());

        foreach ($transactions as $transaction) {
            $info = $transaction->getAdditionalInformation();
            if (isset($info['raw_details_info']['method'])
                && in_array(
                    $info['raw_details_info']['method'],
                    Flag::SUB_METHODS_WITH_MANUAL_ACTIVATION_SUPPORT
                ) && $info['raw_details_info']['api_status'] === Config::PAYTRAIL_API_PAYMENT_STATUS_PENDING
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    private function isStatusValid(\Magento\Sales\Model\Order $order)
    {
        return $order->getStatus() === InstallPaytrail::ORDER_STATUS_CUSTOM_CODE;
    }
}
