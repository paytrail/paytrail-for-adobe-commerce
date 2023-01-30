<?php

namespace Paytrail\PaymentService\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Paytrail\SDK\Exception\ClientException;
use Paytrail\SDK\Exception\HmacException;
use Paytrail\SDK\Exception\RequestException;
use Paytrail\SDK\Exception\ValidationException;

class Activate extends Action implements HttpGetActionInterface
{
    /**
     * @var \Paytrail\PaymentService\Model\Invoice\Activation\ManualActivation
     */
    private \Paytrail\PaymentService\Model\Invoice\Activation\ManualActivation $manualActivation;

    /**
     * @param Context $context
     * @param \Paytrail\PaymentService\Model\Invoice\Activation\ManualActivation $manualActivation
     */
    public function __construct(
        Context $context,
        \Paytrail\PaymentService\Model\Invoice\Activation\ManualActivation $manualActivation
    ) {
        parent::__construct($context);
        $this->manualActivation = $manualActivation;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $orderId = (int)$this->_request->getParam('order_id');
        $this->activateByOrderId($orderId);
        $result->setPath('sales/order/view', ['order_id' => $orderId]);

        return $result;
    }

    /**
     * @param int $orderId
     */
    private function activateByOrderId(int $orderId)
    {
        try {
            $this->manualActivation->activateInvoice($orderId);
            $this->messageManager->addSuccessMessage(__('Invoice activated successfully'));
        } catch (ClientException|HmacException|RequestException|ValidationException $e) {
            $this->messageManager->addErrorMessage(sprintf(
                'Error while activating invoice: %s',
                $e->getMessage()
            ));
        }
    }
}
