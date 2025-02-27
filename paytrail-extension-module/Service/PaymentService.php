<?php
/*
 *  Copyright © 2024 GoodAhead. All rights reserved.
 */

namespace Goodahead\Paytrail\Service;

use Magento\Checkout\Model\Session as SessionCheckout;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\SDK\Interfaces\ResponseInterface;
use Psr\Log\LoggerInterface;

class PaymentService
{

    /**
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param SessionCheckout $sessionCheckout
     * @param Config $config
     * @param UrlInterface $url
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected CommandManagerPoolInterface $commandManagerPool,
        protected SessionCheckout       $sessionCheckout,
        protected Config                $config,
        protected UrlInterface          $url,
        protected LoggerInterface       $logger
    ) {}

    /**
     * @return ResponseInterface
     * @throws CommandException
     * @throws NotFoundException
     */
    public function execute(): ResponseInterface
    {
        $commandExecutor = $this->commandManagerPool->get('paytrail');
        $order           = $this->sessionCheckout->getLastRealOrder();

        /**
         * @See \Paytrail\PaymentService\Gateway\Command\Payment::execute
         * @var array $response
         */
        $response = $commandExecutor->executeByCode(
            'payment',
            null,
            [
                'order' => $order,
                'payment_method' => 'paytrail'
            ]
        );

        if (empty($response['data'] ?? null)) {
            $this->logger->error('Paytrail Error: Empty response');
        }

        if (!empty($response['error'])) {
            $this->logger->error('Paytrail Error: ' . $response['error']);
        }

        return $response["data"];
    }
}
