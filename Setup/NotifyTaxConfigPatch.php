<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Paytrail\PaymentService\Setup\Patch\Data;


use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Activate data collection mechanism
 */
class NotifyTaxConfigPatch implements DataPatchInterface
{
    /**
     * @var NotifierInterface
     */
    private $notifier;

    public function __construct(
        NotifierInterface $notifier,
        UrlInterface $url
    ) {
        $this->notifier = $notifier;
        $this->url = $url;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        $this->notifier->addMajor(
            'Paytrail Discount calculation tax settings need attention',
            'Paytrail module discount calculation has been updated. New update includes rounding correction row tax percent, please check that the setting is correct for your webstore'
        );
    }
}