<?php

namespace Paytrail\PaymentService\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\CustomerTokenManagement;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\ApiData as apiData;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\PaymentService\Model\Ui\DataProvider\PaymentProvidersData;
use Psr\Log\LoggerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'paytrail';
    public const VAULT_CODE = 'paytrail_cc_vault';

    /**
     * @var string[]
     */
    protected $methodCodes = [
        self::CODE,
        self::VAULT_CODE
    ];

    /**
     * @var apiData
     */
    protected $apidata;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var Config
     */
    private $gatewayConfig;
    /**
     * @var AssetRepository
     */
    private $assetRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Resolver
     */
    private $localeResolver;
    /**
     * @var Adapter
     */
    private $paytrailAdapter;
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var CustomerTokenManagement
     */
    private $customerTokenManagement;

    /**
     * @var CcConfigProvider
     */
    private $ccConfigProvider;

    /**
     * @var array
     */
    private $paymenticons;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var \Magento\Payment\Model\MethodInterface[]
     */
    private $methods;

    /**
     * ConfigProvider constructor
     *
     * @param paytrailHelper $paytrailHelper
     * @param apiData $apidata
     * @param PaymentHelper $paymentHelper
     * @param Session $checkoutSession
     * @param Config $gatewayConfig
     * @param StoreManagerInterface $storeManager
     * @param Resolver $localeResolver
     * @param Adapter $paytrailAdapter
     * @param LoggerInterface $log
     * @param CustomerTokenManagement $customerTokenManagement
     * @param CcConfigProvider $ccConfigProvider
     * @param UrlInterface $urlBuilder
     * @throws LocalizedException
     */
    public function __construct(
        private paytrailHelper $paytrailHelper,
        apiData $apidata,
        PaymentHelper $paymentHelper,
        Session $checkoutSession,
        Config $gatewayConfig,
        AssetRepository $assetRepository,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        Adapter $paytrailAdapter,
        LoggerInterface $log,
        CustomerTokenManagement $customerTokenManagement,
        CcConfigProvider $ccConfigProvider,
        UrlInterface $urlBuilder,
        private PaymentProvidersData $paymentProvidersData
    ) {
        $this->apidata = $apidata;
        $this->checkoutSession = $checkoutSession;
        $this->gatewayConfig = $gatewayConfig;
        $this->assetRepository = $assetRepository;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->paytrailAdapter = $paytrailAdapter;
        $this->log = $log;
        $this->customerTokenManagement = $customerTokenManagement;
        $this->ccConfigProvider = $ccConfigProvider;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->paymenticons = $this->ccConfigProvider->getIcons();
        $this->urlBuilder = $urlBuilder;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * GetConfig function
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConfig()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $config = [];
        $status = $this->gatewayConfig->isActive($storeId);

        if (!$status) {
            return $config;
        }
        try {
            $groupData = $this->paymentProvidersData->getAllPaymentMethods();
            $scheduledMethod[] = $this->paymentProvidersData
                ->handlePaymentProviderGroupData($groupData['groups'])['creditcard'];

            $config = [
                'payment' => [
                    self::CODE => [
                        'instructions' => $this->gatewayConfig->getInstructions(),
                        'skip_method_selection' => $this->gatewayConfig->getSkipBankSelection(),
                        'payment_redirect_url' => $this->gatewayConfig->getPaymentRedirectUrl(),
                        'payment_template' => $this->gatewayConfig->getPaymentTemplate(),
                        'method_groups' => array_values($this->paymentProvidersData
                            ->handlePaymentProviderGroupData($groupData['groups'])),
                        'scheduled_method_group' => array_values($scheduledMethod),
                        'payment_terms' => $groupData['terms'],
                        'payment_method_styles' => $this->paymentProvidersData->wrapPaymentMethodStyles($storeId),
                        'addcard_redirect_url' => $this->gatewayConfig->getAddCardRedirectUrl(),
                        'token_payment_redirect_url' => $this->gatewayConfig->getTokenPaymentRedirectUrl(),
                        'default_success_page_url' => $this->gatewayConfig->getDefaultSuccessPageUrl()
                    ]
                ]
            ];
            //Get images for payment groups
            foreach ($groupData['groups'] as $group) {
                $groupId = $group['id'];
                $groupImage = $group['svg'];
                $config['payment'][self::CODE]['image'][$groupId] = '';
                if ($groupImage) {
                    $config['payment'][self::CODE]['image'][$groupId] = $groupImage;
                }
            }
        } catch (\Exception $e) {
            $config['payment'][self::CODE]['success'] = 0;

            return $config;
        }
        if ($this->checkoutSession->getData('paytrail_previous_error')) {
            $config['payment'][self::CODE]['previous_error'] = $this->checkoutSession
                ->getData('paytrail_previous_error', 1);
        } elseif ($this->checkoutSession->getData('paytrail_previous_success')) {
            $config['payment'][self::CODE]['previous_success'] = $this->checkoutSession
                ->getData('paytrail_previous_success', 1);
        }
        $config['payment'][self::CODE]['success'] = 1;

        return $config;
    }
}
