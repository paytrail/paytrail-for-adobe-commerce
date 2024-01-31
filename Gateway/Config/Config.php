<?php

namespace Paytrail\PaymentService\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Model\CcConfigProvider;
use Magento\Vault\Model\CustomerTokenManagement;
use Psr\Log\LoggerInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const DEFAULT_PATH_PATTERN                  = 'payment/%s/%s';
    public const KEY_TITLE                             = 'title';
    public const CODE                                  = 'paytrail';
    public const CC_VAULT_CODE                         = 'paytrail_cc_vault';
    public const SAVE_CARD_URL                         = 'tokenization/savecard';
    public const KEY_CHECKOUT_ALGORITHM                = 'checkout_algorithm';
    public const KEY_MERCHANT_SECRET                   = 'merchant_secret';
    public const KEY_MERCHANT_ID                       = 'merchant_id';
    public const KEY_ACTIVE                            = 'active';
    public const KEY_SKIP_BANK_SELECTION               = 'skip_bank_selection';
    public const BYPASS_PATH                           = 'Paytrail_PaymentService/payment/checkout-bypass';
    public const CHECKOUT_PATH                         = 'Paytrail_PaymentService/payment/checkout';
    public const KEY_GENERATE_REFERENCE                = 'generate_reference';
    public const KEY_RECOMMENDED_TAX_ALGORITHM         = 'recommended_tax_algorithm';
    public const KEY_PAYMENTGROUP_BG_COLOR             = 'paytrail_personalization/payment_group_bg';
    public const KEY_PAYMENTGROUP_HIGHLIGHT_BG_COLOR   = 'paytrail_personalization/payment_group_highlight_bg';
    public const KEY_PAYMENTGROUP_TEXT_COLOR           = 'paytrail_personalization/payment_group_text';
    public const KEY_PAYMENTGROUP_HIGHLIGHT_TEXT_COLOR = 'paytrail_personalization/payment_group_highlight_text';
    public const KEY_PAYMENTGROUP_HOVER_COLOR          = 'paytrail_personalization/payment_group_hover';
    public const KEY_PAYMENTMETHOD_HIGHLIGHT_COLOR     = 'paytrail_personalization/payment_method_highlight';
    public const KEY_PAYMENTMETHOD_HIGHLIGHT_HOVER     = 'paytrail_personalization/payment_method_hover';
    public const KEY_PAYMENTMETHOD_ADDITIONAL          =
        'paytrail_personalization/advanced_paytrail_personalization/additional_css';
    public const KEY_RESPONSE_LOG                      = 'response_log';
    public const KEY_REQUEST_LOG                       = 'request_log';
    public const KEY_DEFAULT_ORDER_STATUS              = 'order_status';
    public const KEY_NOTIFICATION_EMAIL                = 'recipient_email';
    public const KEY_CANCEL_ORDER_ON_FAILED_PAYMENT    = 'failed_payment_cancel';
    public const VAULT_CODE                            = 'paytrail_cc_vault';
    public const LOGO                                  = 'payment/paytrail/logo';
    public const KEY_MANUAL_INVOICE                    = 'manual_invoice';

    public const APPLE_PAY_CONFIG = 'paytrail_apple_pay';
    public const KEY_ACTIVATE_WITH_SHIPMENT            = 'shipment_activates_invoice';

    public const GIT_URL = 'https://api.github.com/repos/paytrail/paytrail-for-adobe-commerce/releases/latest';

    public const RECEIPT_PROCESSING_CACHE_PREFIX     = "receipt_processing_";
    public const PAYTRAIL_API_PAYMENT_STATUS_OK      = 'ok';
    public const PAYTRAIL_API_PAYMENT_STATUS_PENDING = 'pending';
    public const PAYTRAIL_API_PAYMENT_STATUS_DELAYED = 'delayed';
    public const PAYTRAIL_API_PAYMENT_STATUS_FAIL    = 'fail';

    /**
     * @var array
     */
    private $paymenticons;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param UrlInterface $urlBuilder
     * @param CustomerTokenManagement $customerTokenManagement
     * @param CcConfigProvider $ccConfigProvider
     * @param Resolver $localeResolver
     * @param ModuleListInterface $moduleList
     * @param Curl $curlClient
     * @param ComponentRegistrar $componentRegistrar
     * @param ReadFactory $readFactory
     * @param LoggerInterface $logger
     * @param string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface            $scopeConfig,
        private EncryptorInterface      $encryptor,
        private UrlInterface            $urlBuilder,
        private CustomerTokenManagement $customerTokenManagement,
        private CcConfigProvider        $ccConfigProvider,
        private Resolver                $localeResolver,
        private ModuleListInterface     $moduleList,
        private Curl                    $curlClient,
        private ComponentRegistrar      $componentRegistrar,
        private ReadFactory             $readFactory,
        private LoggerInterface         $logger,
        $methodCode = self::CODE,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->paymenticons = $this->ccConfigProvider->getIcons();
    }

    /**
     * Gets Merchant Id.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function getMerchantId($storeId = null)
    {
        return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
    }

    /**
     * Gets Merchant secret.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function getMerchantSecret($storeId = null)
    {
        $merchantSecret = $this->getValue(self::KEY_MERCHANT_SECRET, $storeId);
        return $this->encryptor->decrypt($merchantSecret);
    }

    /**
     * Gets Payment configuration status.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Get payment method title
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getTitle($storeId = null)
    {
        return $this->getValue(self::KEY_TITLE, $storeId);
    }

    /**
     * Get skip bank selection value.
     *
     * @param string $storeId
     *
     * @return bool
     */
    public function getSkipBankSelection($storeId = null)
    {
        return $this->getValue(self::KEY_SKIP_BANK_SELECTION, $storeId);
    }

    /**
     * Get payment group bg color value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentGroupBgColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_BG_COLOR, $storeId);
    }

    /**
     * Get payment group highlight bg color value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentGroupHighlightBgColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HIGHLIGHT_BG_COLOR, $storeId);
    }

    /**
     * Get payment group text color value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentGroupTextColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_TEXT_COLOR, $storeId);
    }

    /**
     * Get payment group highlight text color value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentGroupHighlightTextColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HIGHLIGHT_TEXT_COLOR, $storeId);
    }

    /**
     * Get payment group hover color value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentGroupHoverColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HOVER_COLOR, $storeId);
    }

    /**
     * Get payment method highlight color value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentMethodHighlightColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_HIGHLIGHT_COLOR, $storeId);
    }

    /**
     * Get payment method hover highlight value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getPaymentMethodHoverHighlight($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_HIGHLIGHT_HOVER, $storeId);
    }

    /**
     * Get additional css value.
     *
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getAdditionalCss($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_ADDITIONAL, $storeId);
    }

    /**
     * Get generate reference for order value.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function getGenerateReferenceForOrder($storeId = null)
    {
        return $this->getValue(self::KEY_GENERATE_REFERENCE, $storeId);
    }

    /**
     * Get use recommended tax algorithm value.
     *
     * @param int|null $storeId
     *
     * @return bool
     */
    public function getUseRecommendedTaxAlgorithm($storeId = null)
    {
        return $this->getValue(self::KEY_RECOMMENDED_TAX_ALGORITHM, $storeId);
    }

    /**
     * Get instructions.
     *
     * @return null|string
     */
    public function getInstructions()
    {
        if ($this->getSkipBankSelection()) {
            return __("You will be redirected to Paytrail payment service.");
        }
        return null;
    }

    /**
     * Get payment template.
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function getPaymentTemplate($storeId = null)
    {
        if ($this->getSkipBankSelection($storeId)) {
            return self::CHECKOUT_PATH;
        }
        return self::BYPASS_PATH;
    }

    /**
     * Get response log value.
     *
     * @param string $storeId
     *
     * @return mixed|null
     */
    public function getResponseLog($storeId = null)
    {
        return $this->getValue(self::KEY_RESPONSE_LOG, $storeId);
    }

    /**
     * Get request log value.
     *
     * @param string $storeId
     *
     * @return mixed|null
     */
    public function getRequestLog($storeId = null)
    {
        return $this->getValue(self::KEY_REQUEST_LOG, $storeId);
    }

    /**
     * Get default order status value.
     *
     * @param string $storeId
     *
     * @return mixed
     */
    public function getDefaultOrderStatus($storeId = null)
    {
        return $this->getValue(self::KEY_DEFAULT_ORDER_STATUS, $storeId);
    }

    /**
     * Get notification email value.
     *
     * @param string $storeId
     *
     * @return mixed
     */
    public function getNotificationEmail($storeId = null)
    {
        return $this->getValue(self::KEY_NOTIFICATION_EMAIL, $storeId);
    }

    /**
     * Get cc vault code.
     *
     * @return string
     */
    public function getCcVaultCode()
    {
        return self::CC_VAULT_CODE;
    }

    /**
     * Get checkout algorithm.
     *
     * @param string $storeId
     *
     * @return mixed|null
     */
    public function getCheckoutAlgorithm($storeId = null)
    {
        return $this->getValue(self::KEY_CHECKOUT_ALGORITHM, $storeId);
    }

    /**
     * Get save card url.
     *
     * @return string
     */
    public function getSaveCardUrl()
    {
        return self::SAVE_CARD_URL;
    }

    /**
     * Get cancel order failed payment value.
     *
     * @param string $storeId
     *
     * @return int
     */
    public function getCancelOrderOnFailedPayment($storeId = null)
    {
        return $this->getValue(self::KEY_CANCEL_ORDER_ON_FAILED_PAYMENT, $storeId);
    }

    /**
     * Get payment request redirect url.
     *
     * @return string
     */
    public function getPaymentRedirectUrl()
    {
        return 'paytrail/redirect';
    }

    /**
     * Get add_card request redirect url.
     *
     * @return string
     */
    public function getAddCardRedirectUrl()
    {
        return 'paytrail/tokenization/addcard';
    }

    /**
     * Get pay_and_add_card request redirect url.
     *
     * @return string
     */
    public function getPayAndAddCardRedirectUrl()
    {
        return 'paytrail/redirect/payandaddcard';
    }

    /**
     * Get token_payment request redirect url.
     *
     * @return string
     */
    public function getTokenPaymentRedirectUrl()
    {
        return 'paytrail/redirect/token';
    }

    /**
     * Get default success page url.
     *
     * @return string
     */
    public function getDefaultSuccessPageUrl()
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success/');
    }

    /**
     * Get icon url.
     *
     * @param string $type
     *
     * @return array
     */
    private function getIconUrl($type)
    {
        if (isset($this->paymenticons[$type])) {
            return $this->paymenticons[$type];
        }

        return [
            'url'    => '',
            'width'  => 0,
            'height' => 0
        ];
    }

    /**
     * Get customer tokens.
     *
     * @return array
     */
    public function getCustomerTokens()
    {
        $tokens = $this->customerTokenManagement->getCustomerSessionTokens();
        $t      = [];

        foreach ($tokens as $token) {
            if ($token->getPaymentMethodCode() == self::VAULT_CODE && $token->getIsActive() && $token->getIsVisible()) {
                $cdata                                = json_decode($token->getTokenDetails(), true);
                $t[$token->getEntityId()]["expires"]  = $cdata['expirationDate'];
                $t[$token->getEntityId()]["url"]      = $this->getIconUrl($cdata["type"])['url'];
                $t[$token->getEntityId()]["maskedCC"] = $cdata["maskedCC"];
                $t[$token->getEntityId()]["type"]     = $cdata["type"];
                $t[$token->getEntityId()]["id"]       = $token->getPublicHash();
            }
        }

        return $t;
    }

    /**
     * Get valid algorithms.
     *
     * @return array
     */
    public function getValidAlgorithms(): array
    {
        return ["sha256", "sha512"];
    }

    /**
     * Get Store locale for payment provider.
     *
     * @return string
     */
    public function getStoreLocaleForPaymentProvider(): string
    {
        $locale = 'EN';
        if ($this->localeResolver->getLocale() === 'fi_FI') {
            $locale = 'FI';
        }
        if ($this->localeResolver->getLocale() === 'sv_SE') {
            $locale = 'SV';
        }

        return $locale;
    }

    /**
     * Get order increment id from checkout reference number
     *
     * @param string $reference
     *
     * @return string|null
     */
    public function getIdFromOrderReferenceNumber($reference)
    {
        return preg_replace('/\s+/', '', substr($reference, 1, -1));
    }

    /**
     * Get module version.
     *
     * @return string
     */
    public function getVersion()
    {
        $setupVersion = 0;
        $composerVersion = $this->getComposerVersion('Paytrail_PaymentService');
        if ($moduleInfo = $this->moduleList->getOne('Paytrail_PaymentService')) {
            $setupVersion = $moduleInfo['setup_version'];
        }

        if ($setupVersion && $composerVersion != $setupVersion) {
            $this->logger->warning(
                'Paytrail_PaymentService: Composer version (' . $composerVersion
                . ') and setup version (' . $setupVersion . ') do not match.'
            );
        }

        $newest = max($composerVersion, $setupVersion);

        return $newest ?: __('Unknown');
    }

    /**
     * Get module composer version
     *
     * @param string $moduleName
     *
     * @return Phrase|string|void
     * @throws FileSystemException
     * @throws ValidatorException
     */
    public function getComposerVersion(string $moduleName)
    {
        $path             = $this->componentRegistrar->getPath(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $moduleName
        );
        $directoryRead    = $this->readFactory->create($path);
        $composerJsonData = $directoryRead->readFile('composer.json');
        $data             = json_decode($composerJsonData);

        return !empty($data->version) ? $data->version : __('Read error!');
    }

    /**
     * Get decoded content from GitHub.
     *
     * @return mixed
     */
    public function getDecodedContentFromGithub()
    {
        $options = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_USERAGENT      => 'magento'
        ];
        $this->curlClient->setOptions($options);
        $this->curlClient->get(self::GIT_URL);
        return json_decode($this->curlClient->getBody(), true);
    }

    /**
     * Are manual invoice activations in use
     *
     * @param null|int|string $storeId
     *
     * @return bool
     */
    public function isManualInvoiceEnabled($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_MANUAL_INVOICE, $storeId);
    }

    /**
     * Will creating a shipment to an order activate the order's invoice.
     *
     * @param null|int|string $storeId
     *
     * @return bool
     */
    public function isShipmentActivateInvoice($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_ACTIVATE_WITH_SHIPMENT, $storeId);
    }

    public function isApplePayEnabled($storeId = null): bool
    {
        return (bool)$this->getValue(self::APPLE_PAY_CONFIG, $storeId);
    }
}
