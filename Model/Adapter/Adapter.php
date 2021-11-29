<?php

namespace Paytrail\PaymentService\Model\Adapter;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\ModuleListInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\SDK\Client;

class Adapter
{
    /**
     * @var string MODULE_CODE
     */
    const MODULE_CODE = 'Paytrail_PaymentService';
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
     * Create Instance of the Paytrail PHP-SDK API Client
     * @return Client
     * @throws LocalizedException
     */
    public function initPaytrailMerchantClient()
    {
        try {
            if (class_exists('Paytrail\SDK\Client')) {
                $paytrailClient = new Client(
                    $this->merchantId,
                    $this->merchantSecret,
                    'paytrail-for-adobe-commerce-' . $this->getExtensionVersion()
                );
                return $paytrailClient;
            } else {
                throw new LocalizedException(__('Paytrail\SDK\Client does not exist'));
            }
        } catch (\Error $e) {
            throw new LocalizedException(__('An error has occured during checkout process, please contact the store owners'));
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
