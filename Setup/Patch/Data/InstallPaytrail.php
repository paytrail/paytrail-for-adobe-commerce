<?php

namespace Paytrail\PaymentService\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Setup\SalesSetupFactory;

class InstallPaytrail implements DataPatchInterface
{
    public const ORDER_STATE_CUSTOM_CODE = 'pending_paytrail_state';
    public const ORDER_STATUS_CUSTOM_CODE = 'pending_paytrail';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var SalesSetupFactory
     */
    private $salesSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        SalesSetupFactory        $salesSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->salesSetupFactory = $salesSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->installPaytrailStatus();
        $this->installPaytrailState();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @return void
     */
    private function installPaytrailStatus(): void
    {
        $data = [
            'status' => "pending_paytrail",
            'label' => __('Pending Paytrail Payment Service')
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate(
            $this->moduleDataSetup->getTable('sales_order_status'),
            $data
        );
    }

    /**
     * @return void
     */
    private function installPaytrailState(): void
    {
        $data = [
            'status'            => self::ORDER_STATUS_CUSTOM_CODE,
            'state'             => self::ORDER_STATE_CUSTOM_CODE,
            'is_default'        => 1,
            'visible_on_front'  => 1,
        ];

        $this->moduleDataSetup->getConnection()->insertOnDuplicate(
            $this->moduleDataSetup->getTable('sales_order_status_state'),
            $data
        );
    }
}
