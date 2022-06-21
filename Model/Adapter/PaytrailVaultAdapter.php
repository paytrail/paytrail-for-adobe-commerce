<?php
namespace Paytrail\PaymentService\Model\Adapter;

use Magento\Payment\Helper\Formatter;
use Paytrail\PaymentService\Model\Adapter\Adapter as OpAdapter;


class OpVaultAdapter extends OpAdapter
{
    use Formatter;

    const CC_DETAILS = 'cc_details';
    const ID = 'id';
    const CC_VAULT_CODE = 'paytrailpaymentservice_cc_vault';
    const CODE = 'paytrailpaymentservice_cc_vault';
    const METHOD_CODE = 'paytrailpaymentservice_cc_vault';

    /**
     * Result code of account verification transaction request.
     */
    private const RESULT_CODE = 'result_code';

    /**
     * Fraud Management Filters config setting.
     */
    private const CONFIG_FMF = 'fmf';

    public function getId()
    {
        return $this->getData(self::ID);
    }

    public function test() {
        return self::CC_VAULT_CODE;
    }

    public function getSavedCards() {

    }
}
