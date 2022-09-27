<?php

namespace Paytrail\PaymentService\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Helper\Reorder;
use Magento\Sales\Model\ConfigInterface;
use Paytrail\PaymentService\Helper\ActivateOrder;

class ViewPlugin
{
    /**
     * @var ActivateOrder
     */
    private $activateOrder;

    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var UrlInterface
     */
    private $url;

    public function __construct(
        ActivateOrder $activateOrder,
        RequestInterface $request,
        UrlInterface $url
    ) {
        $this->activateOrder = $activateOrder;
        $this->request = $request;
        $this->url = $url;
    }

    public function beforeSetLayout(View $view)
    {
        $orderId = $this->request->getParam('order_id');
        if ($this->activateOrder->isCanceled($orderId)) {
            $view->addButton('rescueOrder', [
                'label' => __('Restore Order'),
                'onclick' => "confirmSetLocation('Are you sure you want to make changes to this order?', '{$this->getRestoreOrderUrl($orderId)}')",
            ]);
        }
    }

    /**
     * Restore order URL getter
     *
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
