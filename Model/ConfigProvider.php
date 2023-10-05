<?php

namespace Paytrail\PaymentService\Model;

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
use Psr\Log\LoggerInterface;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'paytrail';
    const VAULT_CODE = 'paytrail_cc_vault';
    public const ID_INCREMENT_SEPARATOR = '__';

    protected $methodCodes = [
        self::CODE,
        self::VAULT_CODE
    ];
    protected $paytrailHelper;
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
     * @param AssetRepository $assetRepository
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
        paytrailHelper $paytrailHelper,
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
        UrlInterface $urlBuilder
    ) {
        $this->paytrailHelper = $paytrailHelper;
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
            $groupData = $this->getAllPaymentMethods();
            $scheduledMethod = [];

            if (array_key_exists('creditcard', $this->handlePaymentProviderGroupData($groupData['groups']))) {
                $scheduledMethod[] = $this->handlePaymentProviderGroupData($groupData['groups'])['creditcard'];
            }

            $config = [
                'payment' => [
                    self::CODE => [
                        'instructions' => $this->gatewayConfig->getInstructions(),
                        'skip_method_selection' => $this->gatewayConfig->getSkipBankSelection(),
                        'payment_redirect_url' => $this->getPaymentRedirectUrl(),
                        'payment_template' => $this->gatewayConfig->getPaymentTemplate(),
                        'method_groups' => array_values($this->handlePaymentProviderGroupData($groupData['groups'])),
                        'scheduled_method_group' => array_values($scheduledMethod),
                        'payment_terms' => $groupData['terms'],
                        'payment_method_styles' => $this->wrapPaymentMethodStyles($storeId),
                        'addcard_redirect_url' => $this->getAddCardRedirectUrl(),
                        'token_payment_redirect_url' => $this->getTokenPaymentRedirectUrl(),
                        'default_success_page_url' => $this->getDefaultSuccessPageUrl()
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
            $this->log->error($e->getMessage());

            return $config;
        }
        if ($this->checkoutSession->getData('paytrail_previous_error')) {
            $config['payment'][self::CODE]['previous_error'] = $this->checkoutSession->getData('paytrail_previous_error', 1);
        } elseif ($this->checkoutSession->getData('paytrail_previous_success')) {
            $config['payment'][self::CODE]['previous_success'] = $this->checkoutSession->getData('paytrail_previous_success', 1);
        }
        $config['payment'][self::CODE]['success'] = 1;
        return $config;
    }

    /**
     * Create payment page styles from the values entered in Paytrail configuration.
     *
     * @param $storeId
     * @return string
     */
    protected function wrapPaymentMethodStyles($storeId)
    {
        $styles = '.paytrail-group-collapsible{ background-color:' . $this->gatewayConfig->getPaymentGroupBgColor($storeId) . '; margin-top:1%; margin-bottom:2%;}';
        $styles .= '.paytrail-group-collapsible.active{ background-color:' . $this->gatewayConfig->getPaymentGroupHighlightBgColor($storeId) . ';}';
        $styles .= '.paytrail-group-collapsible span{ color:' . $this->gatewayConfig->getPaymentGroupTextColor($storeId) . ';}';
        $styles .= '.paytrail-group-collapsible li{ color:' . $this->gatewayConfig->getPaymentGroupTextColor($storeId) . '}';
        $styles .= '.paytrail-group-collapsible.active span{ color:' . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . ';}';
        $styles .= '.paytrail-group-collapsible.active li{ color:' . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . '}';
        $styles .= '.paytrail-group-collapsible:hover:not(.active) {background-color:' . $this->gatewayConfig->getPaymentGroupHoverColor() . '}';
        $styles .= '.paytrail-payment-methods .paytrail-payment-method.active{ border-color:' . $this->gatewayConfig->getPaymentMethodHighlightColor($storeId) . ';border-width:2px;}';
        $styles .= '.paytrail-payment-methods .paytrail-stored-token.active{ border-color:' . $this->gatewayConfig->getPaymentMethodHighlightColor($storeId) . ';border-width:2px;}';
        $styles .= '.paytrail-payment-methods .paytrail-payment-method:hover, .paytrail-payment-methods .paytrail-payment-method:not(.active):hover { border-color:' . $this->gatewayConfig->getPaymentMethodHoverHighlight($storeId) . ';}';
        $styles .= $this->gatewayConfig->getAdditionalCss($storeId);
        return $styles;
    }

    /**
     * @return string
     */
    protected function getPaymentRedirectUrl()
    {
        return 'paytrail/redirect';
    }

    protected function getAddCardRedirectUrl()
    {
        return 'paytrail/tokenization/addcard';
    }

    protected function getTokenPaymentRedirectUrl()
    {
        return 'paytrail/redirect/token';
    }

    public function getDefaultSuccessPageUrl()
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success/');
    }

    /**
     * Get all payment methods and groups with order total value
     *
     * @return mixed|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getAllPaymentMethods()
    {
        $orderValue = $this->checkoutSession->getQuote()->getGrandTotal();

        $response = $this->apidata->processApiRequest(
            'payment_providers',
            null,
            round($orderValue * 100)
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->log->error(
                'Error occurred during email refund: '
                . $errorMsg
            );
            $this->paytrailHelper->processError($errorMsg);
        }

        return $response["data"];
    }

    /**
     * Create array for payment providers and groups containing unique method id
     *
     * @param $responseData
     * @return array
     */
    protected function handlePaymentProviderGroupData($responseData)
    {
        $allMethods = [];
        $allGroups = [];
        foreach ($responseData as $group) {
            $allGroups[$group['id']] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'icon' => $group['icon']
            ];

            foreach ($group['providers'] as $provider) {
                $allMethods[] = $provider;
            }
        }
        foreach ($allGroups as $key => $group) {
            if ($group['id'] == 'creditcard') {
                $allGroups[$key]["can_tokenize"] = true;
                $allGroups[$key]["tokens"] = $this->getCustomerTokens();
            } else {
                $allGroups[$key]["can_tokenize"] = false;
                $allGroups[$key]["tokens"] = false;
            }

            $allGroups[$key]['providers'] = $this->addProviderDataToGroup($allMethods, $group['id']);
        }
        return $allGroups;
    }

    /**
     * @param string $type
     * @return array
     */
    protected function getIconUrl($type)
    {
        if (isset($this->paymenticons[$type])) {
            return $this->paymenticons[$type];
        }

        return [
            'url' => '',
            'width' => 0,
            'height' => 0
        ];
    }

    protected function getCustomerTokens()
    {
        $tokens =  $this->customerTokenManagement->getCustomerSessionTokens();
        $t = [];
        foreach($tokens as $token) {
            if($token->getPaymentMethodCode() == self::VAULT_CODE && $token->getIsActive() && $token->getIsVisible()) {
                $cdata = json_decode($token->getTokenDetails(), true);
                $t[$token->getEntityId()]["expires"] = $cdata['expirationDate'];
                $t[$token->getEntityId()]["url"] = $this->getIconUrl($cdata["type"])['url'];
                $t[$token->getEntityId()]["maskedCC"] = $cdata["maskedCC"];
                $t[$token->getEntityId()]["type"] = $cdata["type"];
                $t[$token->getEntityId()]["id"] = $token->getPublicHash();
            }
        }
        return $t;
    }

    /**
     * Add payment method data to group
     *
     * @param $responseData
     * @param $groupId
     * @return array
     */
    protected function addProviderDataToGroup($responseData, $groupId)
    {
        $methods = [];
        $i = 1;

        foreach ($responseData as $key => $method) {
            if ($method->getGroup() == $groupId) {
                $methods[] = [
                    'checkoutId' => $method->getId(),
                    'id' => $method->getId() . self::ID_INCREMENT_SEPARATOR .  $i++,
                    'name' => $method->getName(),
                    'group' => $method->getGroup(),
                    'icon' => $method->getIcon(),
                    'svg' => $method->getSvg()
                ];
            }
        }
        return $methods;
    }
}
