<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use \Paytrail\PaymentService\Logger\PaytrailLogger;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;
use Paytrail\SDK\Request\RefundRequest;

class TransactionRefund implements ClientInterface
{
    /**
     * TransactionRefund constructor.
     *
     * @param PaytrailLogger $log
     * @param CommandManagerPoolInterface $commandManagerPool
     * @param Adapter $paytrailAdapter
     */
    public function __construct(
        private readonly PaytrailLogger              $log,
        private readonly CommandManagerPoolInterface $commandManagerPool,
        private readonly Adapter                     $paytrailAdapter,
    ) {
    }

    /**
     * PlaceRequest function
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $response = $this->refund(
            $request['refund_request'],
            $request['order'],
            $request['transaction_id'] === $request['parent_transaction_id']
                ? $request['transaction_id'] : $request['parent_transaction_id']
        );

        if (isset($response['error'])) {
            $this->log->error(
                'Error occurred during refund: '
                . $response['error']
                . ', Falling back to to email refund.'
            );

            try {
                $commandExecutor = $this->commandManagerPool->get($request['payment']->getMethodInstance()->getCode());
                $commandExecutor->executeByCode(
                    'email_refund',
                    $request['payment'],
                    [
                        'amount' => $request['refund_request']->getAmount(),
                    ]
                );
            } catch (\Exception $e) {
                $this->log->error(
                    'Error occurred during email refund: '
                    . $e->getMessage()
                );
            }
        }

        return $response;
    }

    /**
     * Refund function
     *
     * @param RefundRequest $refundRequest
     * @param OrderAdapterInterface|null $order
     * @param string|null $transactionId
     *
     * @return array
     */
    public function refund(
        RefundRequest         $refundRequest,
        OrderAdapterInterface $order = null,
        string                $transactionId = null
    ): array {
        $response = [];

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'refund',
                    $order ? 'With order id: ' . $order->getId() : ''
                )
            );

            $response['data'] = $paytrailClient->refund($refundRequest, $transactionId);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Successful response for refund. Transaction Id: %s',
                    $response['data']->getTransactionId()
                )
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->log->error(\sprintf(
                    'Connection error to Paytrail Payment Service API: %s Error Code: %s',
                    $e->getMessage(),
                    $e->getCode()
                ));
                $response["error"] = $e->getMessage();
            }
        } catch (\Exception $e) {
            $this->log->error(
                \sprintf(
                    'A problem occurred during Paytrail Api connection: %s',
                    $e->getMessage()
                ),
                $e->getTrace()
            );
            $response["error"] = $e->getMessage();
        }

        return $response;
    }
}
