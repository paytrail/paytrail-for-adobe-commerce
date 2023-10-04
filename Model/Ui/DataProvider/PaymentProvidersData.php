<?php

namespace Paytrail\PaymentService\Model\Ui\DataProvider;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Psr\Log\LoggerInterface;

class PaymentProvidersData
{
    private const CREDITCARD_GROUP_ID = 'creditcard';
    public const ID_INCREMENT_SEPARATOR = '__';

    /**
     * PaymentProvidersData constructor.
     *
     * @param Session $checkoutSession
     * @param LoggerInterface $log
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param Config $gatewayConfig
     * @param PaytrailLogger $paytrailLogger
     */
    public function __construct(
        private Session $checkoutSession,
        private LoggerInterface $log,
        private CommandManagerPoolInterface $commandManagerPool,
        private Config $gatewayConfig,
        private PaytrailLogger $paytrailLogger
    ) {
    }

    /**
     * Get all payment methods and groups with order total value
     *
     * @return mixed|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAllPaymentMethods()
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
            $this->paytrailLogger->logData(\Monolog\Logger::ERROR, $errorMsg);
            throw new CheckoutException(__($errorMsg));
        }

        return $response["data"];
    }

    /**
     * Create payment page styles from the values entered in Paytrail configuration.
     *
     * @param string $storeId
     * @return string
     */
    public function wrapPaymentMethodStyles($storeId)
    {
        $styles = '.paytrail-group-collapsible{ background-color:'
            . $this->gatewayConfig->getPaymentGroupBgColor($storeId) . '; margin-top:1%; margin-bottom:2%;}';
        $styles .= '.paytrail-group-collapsible.active{ background-color:'
            . $this->gatewayConfig->getPaymentGroupHighlightBgColor($storeId) . ';}';
        $styles .= '.paytrail-group-collapsible span{ color:'
            . $this->gatewayConfig->getPaymentGroupTextColor($storeId) . ';}';
        $styles .= '.paytrail-group-collapsible li{ color:'
            . $this->gatewayConfig->getPaymentGroupTextColor($storeId) . '}';
        $styles .= '.paytrail-group-collapsible.active span{ color:'
            . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . ';}';
        $styles .= '.paytrail-group-collapsible.active li{ color:'
            . $this->gatewayConfig->getPaymentGroupHighlightTextColor($storeId) . '}';
        $styles .= '.paytrail-group-collapsible:hover:not(.active) {background-color:'
            . $this->gatewayConfig->getPaymentGroupHoverColor() . '}';
        $styles .= '.paytrail-payment-methods .paytrail-payment-method.active{ border-color:'
            . $this->gatewayConfig->getPaymentMethodHighlightColor($storeId) . ';border-width:2px;}';
        $styles .= '.paytrail-payment-methods .paytrail-stored-token.active{ border-color:'
            . $this->gatewayConfig->getPaymentMethodHighlightColor($storeId) . ';border-width:2px;}';
        $styles .= '.paytrail-payment-methods .paytrail-payment-method:hover, 
        .paytrail-payment-methods .paytrail-payment-method:not(.active):hover { border-color:'
            . $this->gatewayConfig->getPaymentMethodHoverHighlight($storeId) . ';}';
        $styles .= $this->gatewayConfig->getAdditionalCss($storeId);

        return $styles;
    }

    /**
     * Create array for payment providers and groups containing unique method id
     *
     * @param array $responseData
     * @return array
     */
    public function handlePaymentProviderGroupData($responseData)
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
                $allGroups[$key]["tokens"] = $this->gatewayConfig->getCustomerTokens();
            } else {
                $allGroups[$key]["can_tokenize"] = false;
                $allGroups[$key]["tokens"] = false;
            }

            $allGroups[$key]['providers'] = $this->addProviderDataToGroup($allMethods, $group['id']);
        }
        return $allGroups;
    }

    /**
     * Add payment method data to group
     *
     * @param array $responseData
     * @param string $groupId
     * @return array
     */
    protected function addProviderDataToGroup($responseData, $groupId)
    {
        $methods = [];
        $i = 1;

        foreach ($responseData as $key => $method) {
            if ($method->getGroup() == $groupId) {
                $id = $groupId === self::CREDITCARD_GROUP_ID ? $method->getId() . '-' . ($i++) : $method->getId();
                $methods[] = [
                    'checkoutId' => $method->getId(),
                    'id' => $method->getId() . self::ID_INCREMENT_SEPARATOR .  ($i++),
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
