<?php

namespace Paytrail\PaymentService\Block\Info;

use Magento\Framework\View\Element\Template;
use Paytrail\PaymentService\Gateway\Config\Config;
use Magento\Store\Model\StoreManagerInterface;

class Paytrail extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Paytrail_PaymentService::info/checkout.phtml';

    /**
     * Checkout block constructor.
     *
     * @param Config $gatewayConfig
     * @param StoreManagerInterface $storeManager
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        private Config $gatewayConfig,
        private StoreManagerInterface $storeManager,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get Paytrail logo.
     *
     * @return mixed
     */
    public function getPaytrailLogo()
    {
        return $this->_scopeConfig->getValue(
            Config::LOGO,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get payment method title.
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentMethodTitle()
    {
        return $this->gatewayConfig->getTitle($this->storeManager->getStore()->getId());
    }
}
