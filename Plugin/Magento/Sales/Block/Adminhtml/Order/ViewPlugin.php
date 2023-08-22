<?php

namespace Paytrail\PaymentService\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Block\Adminhtml\Order\View;
use Paytrail\PaymentService\Model\Order\OrderActivation;

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
        private UrlInterface     $url
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
        $orderId = $this->request->getParam('order_id');
        if ($this->orderActivation->isCanceled($orderId)) {
            $view->addButton('rescueOrder', [
                'label' => __('Restore Order'),
                'onclick' =>
                    "confirmSetLocation('Are you sure you want to make changes to this order?'
                    , '{$this->getRestoreOrderUrl($orderId)}')",
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
}
