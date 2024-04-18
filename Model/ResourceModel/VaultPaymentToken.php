<?php

namespace Paytrail\PaymentService\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;
use Magento\Framework\Exception\LocalizedException;

class VaultPaymentToken extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vault_payment_token', 'entity_id');
    }
}
