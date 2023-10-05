<?php

namespace Paytrail\PaymentService\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const DEFAULT_PATH_PATTERN = 'payment/%s/%s';
    public const KEY_TITLE = 'title';
    public const CODE = 'paytrail';
    public const KEY_MERCHANT_SECRET = 'merchant_secret';
    public const KEY_MERCHANT_ID = 'merchant_id';
    public const KEY_ACTIVE = 'active';
    public const CC_VAULT_CODE = 'paytrail_cc_vault';
    public const KEY_SKIP_BANK_SELECTION = 'skip_bank_selection';
    public const BYPASS_PATH = 'Paytrail_PaymentService/payment/checkout-bypass';
    public const CHECKOUT_PATH = 'Paytrail_PaymentService/payment/checkout';
    public const KEY_GENERATE_REFERENCE = 'generate_reference';
    public const KEY_RECOMMENDED_TAX_ALGORITHM = 'recommended_tax_algorithm';
    public const KEY_PAYMENTGROUP_BG_COLOR = 'paytrail_personalization/payment_group_bg';
    public const KEY_PAYMENTGROUP_HIGHLIGHT_BG_COLOR = 'paytrail_personalization/payment_group_highlight_bg';
    public const KEY_PAYMENTGROUP_TEXT_COLOR = 'paytrail_personalization/payment_group_text';
    public const KEY_PAYMENTGROUP_HIGHLIGHT_TEXT_COLOR = 'paytrail_personalization/payment_group_highlight_text';
    public const KEY_PAYMENTGROUP_HOVER_COLOR = 'paytrail_personalization/payment_group_hover';
    public const KEY_PAYMENTMETHOD_HIGHLIGHT_COLOR = 'paytrail_personalization/payment_method_highlight';
    public const KEY_PAYMENTMETHOD_HIGHLIGHT_HOVER = 'paytrail_personalization/payment_method_hover';
    public const KEY_PAYMENTMETHOD_ADDITIONAL = 'paytrail_personalization/advanced_paytrail_personalization/additional_css';
    public const KEY_RESPONSE_LOG = 'response_log';
    public const KEY_REQUEST_LOG = 'request_log';
    public const KEY_DEFAULT_ORDER_STATUS = 'order_status';
    public const KEY_NOTIFICATION_EMAIL = 'recipient_email';
    public const KEY_CANCEL_ORDER_ON_FAILED_PAYMENT = 'failed_payment_cancel';
    public const KEY_MANUAL_INVOICE = 'manual_invoice';
    public const KEY_ACTIVATE_WITH_SHIPMENT = 'shipment_activates_invoice';
    public const SAVE_CARD_URL = 'tokenization/savecard';
    public const KEY_CHECKOUT_ALGORITHM = 'checkout_algorithm';

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        $methodCode = self::CODE,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        $this->encryptor = $encryptor;
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
    }

    /**
     * Gets Merchant Id.
     *
     * @param null|int|string $storeId
     * @return bool
     */
    public function getMerchantId($storeId = null)
    {
        return $this->getValue(self::KEY_MERCHANT_ID, $storeId);
    }

    /**
     * Gets Merchant secret.
     *
     * @param null|int|string $storeId
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
     * @param null|int|string $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool) $this->getValue(self::KEY_ACTIVE, $storeId);
    }

    /**
     * Get payment method title
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getTitle($storeId = null)
    {
        return $this->getValue(self::KEY_TITLE, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return bool
     */
    public function getSkipBankSelection($storeId = null)
    {
        return $this->getValue(self::KEY_SKIP_BANK_SELECTION, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPaymentGroupBgColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_BG_COLOR, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPaymentGroupHighlightBgColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HIGHLIGHT_BG_COLOR, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPaymentGroupTextColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_TEXT_COLOR, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPaymentGroupHighlightTextColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HIGHLIGHT_TEXT_COLOR, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return mixed
     */
    public function getPaymentGroupHoverColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTGROUP_HOVER_COLOR, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPaymentMethodHighlightColor($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_HIGHLIGHT_COLOR, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getPaymentMethodHoverHighlight($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_HIGHLIGHT_HOVER, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAdditionalCss($storeId = null)
    {
        return $this->getValue(self::KEY_PAYMENTMETHOD_ADDITIONAL, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return bool
     */
    public function getGenerateReferenceForOrder($storeId = null)
    {
        return $this->getValue(self::KEY_GENERATE_REFERENCE, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return bool
     */
    public function getUseRecommendedTaxAlgorithm($storeId = null)
    {
        return $this->getValue(self::KEY_RECOMMENDED_TAX_ALGORITHM, $storeId);
    }

    /**
     * @return null|\Magento\Framework\Phrase
     */
    public function getInstructions()
    {
        if ($this->getSkipBankSelection()) {
            return __("You will be redirected to Paytrail payment service.");
        }
        return null;
    }

    /**
     * @param null|int|string $storeId
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
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getResponseLog($storeId = null)
    {
        return $this->getValue(self::KEY_RESPONSE_LOG, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getRequestLog($storeId = null)
    {
        return $this->getValue(self::KEY_REQUEST_LOG, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getDefaultOrderStatus($storeId = null)
    {
        return $this->getValue(self::KEY_DEFAULT_ORDER_STATUS, $storeId);
    }

    /**
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getNotificationEmail($storeId = null)
    {
        return $this->getValue(self::KEY_NOTIFICATION_EMAIL, $storeId);
    }

    /**
     * @return string
     */
    public function getCcVaultCode()
    {
        return self::CC_VAULT_CODE;
    }

    /**
     * @param null $storeId
     * @return mixed|null
     */
    public function getCheckoutAlgorithm($storeId = null)
    {
        return $this->getValue(self::KEY_CHECKOUT_ALGORITHM, $storeId);
    }

    /**
     * @return string
     */
    public function getSaveCardUrl()
    {
        return self::SAVE_CARD_URL;
    }

    /**
     * @param null|int|string $storeId
     * @return int
     */
    public function getCancelOrderOnFailedPayment($storeId = null)
    {
        return $this->getValue(self::KEY_CANCEL_ORDER_ON_FAILED_PAYMENT, $storeId);
    }

    /**
     * Are manual invoice activations in use
     *
     * @param null|int|string $storeId
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
     * @return bool
     */
    public function isShipmentActivateInvoice($storeId = null)
    {
        return (bool)$this->getValue(self::KEY_ACTIVATE_WITH_SHIPMENT, $storeId);
    }
}
