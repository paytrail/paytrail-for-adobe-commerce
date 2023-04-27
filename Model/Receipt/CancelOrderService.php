<?php

namespace Paytrail\PaymentService\Model\Receipt;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Paytrail\PaymentService\Gateway\Config\Config;

class CancelOrderService
{
    /**
     * @param Config $gatewayConfig
     * @param UrlInterface $backendUrl
     * @param ScopeConfigInterface $scopeConfig
     * @param TransportBuilder $transportBuilder
     */
    public function __construct(
        private Config $gatewayConfig,
        private UrlInterface $backendUrl,
        private ScopeConfigInterface $scopeConfig,
        private TransportBuilder $transportBuilder
    ) {
    }

    /**
     * NotifyCanceledOrder
     * 
     * @param $currentOrder
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    public function notifyCanceledOrder($currentOrder)
    {
        if (filter_var($this->gatewayConfig->getNotificationEmail(), FILTER_VALIDATE_EMAIL)) {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('restore_order_notification')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID
                ])
                ->setTemplateVars([
                    'order' => [
                        'increment' => $currentOrder->getIncrementId(),
                        'url' => $this->backendUrl->getUrl(
                            'sales/order/view',
                            ['order_id' => $currentOrder->getId()]
                        )
                    ]
                ])
                ->setFrom([
                    'name' => $this->scopeConfig->getValue('general/store_information/name') . ' - Magento',
                    'email' => $this->scopeConfig->getValue('trans_email/ident_general/email'),
                ])->addTo([
                    $this->gatewayConfig->getNotificationEmail()
                ])->getTransport();
            $transport->sendMessage();
        }
    }
}
