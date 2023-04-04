<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\PaymentService\Model\RefundCallback;
use Paytrail\SDK\Request\RefundRequest;
use \Paytrail\PaymentService\Logger\PaytrailLogger;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;

class TransactionRefund implements ClientInterface
{
    /**
     * Constructor
     * 
     * @param \Paytrail\PaymentService\Logger\PaytrailLogger               $log
     * @param \Magento\Payment\Gateway\Command\CommandManagerPoolInterface $commandManagerPool
     * @param \Paytrail\PaymentService\Model\Adapter\Adapter               $paytrailAdapter
     * @param \Paytrail\SDK\Request\RefundRequest                          $refundRequest
     * @param \Paytrail\PaymentService\Model\RefundCallback                $refundCallback
     */
    public function __construct(
        private readonly PaytrailLogger $log,
        private readonly CommandManagerPoolInterface $commandManagerPool,
        private readonly Adapter $paytrailAdapter,
        private readonly RefundRequest $refundRequest,
        private readonly RefundCallback $refundCallback
    ) {
    }

    /**
     * PlaceRequest function
     *
     * @param TransferInterface $transferObject
     *
     * @return array|false
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request  = $transferObject->getBody();
        $response = $this->refund(
            $request['order'],
            $request['amount'],
            $request['parent_transaction_id']
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
                        'amount' => $request['amount'],
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
     * @param $order
     * @param $amount
     * @param $transactionId
     *
     * @return array
     */
    public function refund(
        $order = null,
        $amount = null,
        $transactionId = null
    ) {
        $response= [];

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'refund',
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle request
            $paytrailRefund = $this->refundRequest;
            $this->setRefundRequestData($paytrailRefund, $amount);

            $response = $paytrailClient->refund($paytrailRefund, $transactionId);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Successful response for refund. Transaction Id: %s',
                    $response->getTransactionId()
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

    /**
     * SetRefundRequestData function
     *
     * @param RefundRequest $paytrailRefund
     * @param               $amount
     *
     * @throws CheckoutException
     */
    protected function setRefundRequestData($paytrailRefund, $amount)
    {
        if ($amount <= 0) {
            $this->helper->processError('Refund amount must be above 0');
        }

        $paytrailRefund->setAmount(round($amount * 100));

        $callback = $this->refundCallback->createRefundCallback();
        $paytrailRefund->setCallbackUrls($callback);
    }
}
