<?php

namespace Paytrail\PaymentService\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentData implements CommandInterface
{
    /**
     * @param TransferFactoryInterface $transferFactory
     * @param BuilderInterface $requestBuilder
     * @param ClientInterface $client
     * @param HandlerInterface $handler
     */
    public function __construct(
        private readonly TransferFactoryInterface $transferFactory,
        private readonly BuilderInterface $requestBuilder,
        private readonly ClientInterface $client,
        private readonly HandlerInterface $handler
    ) {
    }

    /**
     * Executes command basing on business object
     *
     * @param array $commandSubject
     * @return array
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject): array
    {
        $transferO = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );

        $response = $this->client->placeRequest($transferO);

        if ($this->handler) {
            $this->handler->handle(
                $commandSubject,
                $response
            );
        }
        return $response;
    }
}
