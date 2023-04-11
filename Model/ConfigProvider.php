<?php

namespace Paytrail\PaymentService\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\StoreManagerInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Psr\Log\LoggerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'paytrail';
    const CREDITCARD_GROUP_ID = 'creditcard';

    protected $methodCodes = [
        self::CODE,
    ];

    /**
     * ConfigProvider constructor
     *
     * @param paytrailHelper $paytrailHelper
     * @param PaymentHelper $paymentHelper
     * @param Session $checkoutSession
     * @param Config $gatewayConfig
     * @param StoreManagerInterface $storeManager
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param LoggerInterface $log
     * @throws LocalizedException
     */
    public function __construct(
        private paytrailHelper $paytrailHelper,
        private PaymentHelper $paymentHelper,
        private Session $checkoutSession,
        private Config $gatewayConfig,
        private StoreManagerInterface $storeManager,
        private CommandManagerPoolInterface $commandManagerPool,
        private LoggerInterface $log
    ) {
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
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

            $config = [
                'payment' => [
                    self::CODE => [
                        'instructions' => $this->gatewayConfig->getInstructions(),
                        'skip_method_selection' => $this->gatewayConfig->getSkipBankSelection(),
                        'payment_redirect_url' => $this->getPaymentRedirectUrl(),
                        'payment_template' => $this->gatewayConfig->getPaymentTemplate(),
                        'method_groups' => $this->handlePaymentProviderGroupData($groupData['groups']),
                        'payment_terms' => $groupData['terms'],
                        'payment_method_styles' => $this->wrapPaymentMethodStyles($storeId)
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

        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $response = $commandExecutor->executeByCode(
            'method_provider',
            null,
            ['amount' => $orderValue]
        );

        $errorMsg = $response['error'];

        if (isset($errorMsg)) {
            $this->log->error(
                'Error occurred during providing payment methods: '
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
            $allGroups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'icon' => $group['icon']
            ];
            foreach ($group['providers'] as $provider) {
                $allMethods[] = $provider;
            }
        }
        foreach ($allGroups as $key => $group) {
            $allGroups[$key]['providers'] = $this->addProviderDataToGroup($allMethods, $group['id']);
        }
        return array_values($allGroups);
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
        $i = 1;

        foreach ($responseData as $key => $method) {
            if ($method->getGroup() == $groupId) {
                $id = $groupId === self::CREDITCARD_GROUP_ID ? $method->getId() . '-' . $i++ : $method->getId();
                $methods[] = [
                    'checkoutId' => $method->getId(),
                    'id' => $id,
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
