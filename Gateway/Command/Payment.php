<?php

namespace Paytrail\PaymentService\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class Payment implements CommandInterface
{
    /**
     * @param TransferFactoryInterface $transferFactory
     * @param BuilderInterface $requestBuilder
     * @param ClientInterface $client
     * @param HandlerInterface $handler
     */
    public function __construct(
        private TransferFactoryInterface $transferFactory,
        private BuilderInterface         $requestBuilder,
        private ClientInterface          $client,
        private HandlerInterface         $handler
    ) {
    }

    /**
     * Executes command basing on business object
     *
     * @param array $commandSubject
     * @return array|\Magento\Payment\Gateway\Command\ResultInterface|null
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject)
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
