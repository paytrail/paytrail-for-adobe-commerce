<?php
namespace Paytrail\PaymentService\Block\Info;

use Magento\Framework\View\Element\Template;
use Paytrail\PaymentService\Helper\Data;
use Paytrail\PaymentService\Gateway\Config\Config;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Checkout
 */
class Checkout extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Paytrail_PaymentService::info/checkout.phtml';
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * Checkout block constructor
     *
     * @param Config $gatewayConfig
     * @param StoreManagerInterface $storeManager
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct
    (
        Config $gatewayConfig,
        StoreManagerInterface $storeManager,
        Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->gatewayConfig = $gatewayConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @return mixed
     */
    public function getPaytrailLogo()
    {
        return $this->_scopeConfig->getValue(
            Data::LOGO,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentMethodTitle()
    {
        return $this->gatewayConfig->getTitle($this->storeManager->getStore()->getId());
    }
}
