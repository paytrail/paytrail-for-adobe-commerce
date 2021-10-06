<?php

namespace Paytrail\PaymentService\Model\Adapter;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\ModuleListInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use OpMerchantServices\SDK\Client;

class Adapter
{
    /**
     * @var string MODULE_CODE
     */
    const MODULE_CODE = 'Paytrail_Checkout';
    /**
     * @var int
     */
    protected $merchantId;
    /**
     * @var string
     */
    protected $merchantSecret;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * Adapter constructor.
     *
     * @param Config $gatewayConfig
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        Config $gatewayConfig,
        ModuleListInterface $moduleList
    ) {
        $this->gatewayConfig = $gatewayConfig;
        $this->moduleList = $moduleList;
        $this->merchantId = $gatewayConfig->getMerchantId();
        $this->merchantSecret = $gatewayConfig->getMerchantSecret();
    }

    /**
     * Create Instance of the Op Merchant Services SDK Api Client
     * @return Client
     * @throws LocalizedException
     */
    public function initPaytrailMerchantClient()
    {
        if (class_exists('OpMerchantServices\SDK\Client')) {
            $paytrailClient = new Client(
                $this->merchantId,
                $this->merchantSecret,
                'paytrail-for-adobe-commerce-' . $this->getExtensionVersion()
            );
            return $paytrailClient;
        } else {
            throw new LocalizedException(__('OpMerchantServices\SDK\Client does not exist'));
        }
    }

    /**
     * @return string module version in format x.x.x
     */
    protected function getExtensionVersion()
    {
        return $this->moduleList->getOne(self::MODULE_CODE)['setup_version'];
    }
}
