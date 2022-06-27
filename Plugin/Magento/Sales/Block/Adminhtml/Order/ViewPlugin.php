<?php

namespace Paytrail\PaymentService\Plugin\Magento\Sales\Block\Adminhtml\Order;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Sales\Helper\Reorder;
use Magento\Sales\Model\ConfigInterface;
use Paytrail\PaymentService\Helper\ActivateOrder;

class ViewPlugin extends View
{
    /**
     * @var ActivateOrder
     */
    private $activateOrder;

    public function __construct(
        ActivateOrder $activateOrder,
        Context $context,
        Registry $registry,
        ConfigInterface $salesConfig,
        Reorder $reorderHelper,
        array $data = []
    ) {
        parent::__construct($context, $registry, $salesConfig, $reorderHelper, $data);
        $this->activateOrder = $activateOrder;
    }

    public function beforeSetLayout(View $view)
    {
        $orderId = $this->getRequest()->getParam('order_id');
        if ($this->activateOrder->isCanceled($orderId)) {
            $view->addButton('rescueOrder', [
                'label' => __('Restore Order'),
                'onclick' => "confirmSetLocation('Are you sure you want to make changes to this order?', '{$this->getRestoreOrderUrl()}')",
            ]);
        }
    }

    /**
     * Restore order URL getter
     *
     * @return string
     */
    public function getRestoreOrderUrl(): string
    {
        return $this->getUrl('paytrail_payment/order/restore');
    }
}
