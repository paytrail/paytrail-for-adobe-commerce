<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Model\RefundCallback;
use Paytrail\SDK\Request\EmailRefundRequest;
use \Paytrail\PaymentService\Logger\PaytrailLogger;
use Magento\Payment\Gateway\Http\TransferInterface;
use \Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\SDK\Response\EmailRefundResponse;

class TransactionEmailRefund implements ClientInterface
{
    /**
     * TransactionEmailRefund constructor.
     *
     * @param PaytrailLogger     $log
     * @param Adapter            $paytrailAdapter
     * @param EmailRefundRequest $emailRefundRequest
     * @param RefundCallback     $refundCallback
     */
    public function __construct(
        private readonly PaytrailLogger $log,
        private readonly Adapter $paytrailAdapter,
        private readonly EmailRefundRequest $emailRefundRequest,
        private readonly RefundCallback $refundCallback
    ) {
    }

    /**
     * PlaceRequest function
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request       = $transferObject->getBody();

        return $this->emailRefund(
            $request['order'],
            $request['refund_request']->getAmount(),
            $request['parent_transaction_id']
        );
    }

    /**
     * EmailRefund function
     *
     * @param OrderAdapterInterface|null $order
     * @param float|null                 $amount
     * @param string|null                $transactionId
     *
     * @return array
     */
    public function emailRefund(
        ?OrderAdapterInterface $order = null,
        ?float $amount = null,
        ?string $transactionId = null
    ):array {
        $response= [];

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'email_refund',
                    $order ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle request
            $this->setEmailRefundRequestData($this->emailRefundRequest, $amount, $order);

            $emailRefundResponse = $paytrailClient->emailRefund($this->emailRefundRequest, $transactionId);
            $response = $this->formatResponse($emailRefundResponse);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Response for email_refund. Transaction Id: %s',
                    $emailRefundResponse->getTransactionId()
                )
            );
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->log->error(\sprintf(
                    'Connection error to Paytrail Payment Service API: %s Error Code: %s',
                    $e->getMessage(),
                    $e->getCode()
                ));
                $response["error"]  = $e->getMessage();
            }
        } catch (\Exception $e) {
            $this->log->error(
                \sprintf(
                    'A problem occurred during Paytrail Api connection: %s',
                    $e->getMessage()
                ),
                $e->getTrace()
            );
            $response["error"]  = $e->getMessage();
        }

        return $response;
    }

    /**
     * SetEmailRefundRequestData function
     *
     * @param EmailRefundRequest    $paytrailEmailRefund
     * @param int                   $amount
     * @param OrderAdapterInterface $order
     */
    private function setEmailRefundRequestData(
        EmailRefundRequest $paytrailEmailRefund,
        int $amount,
        OrderAdapterInterface $order
    ) {
        $paytrailEmailRefund->setEmail($order->getBillingAddress()->getEmail());

        $paytrailEmailRefund->setAmount($amount);

        $callback = $this->refundCallback->createRefundCallback();
        $paytrailEmailRefund->setCallbackUrls($callback);
    }

    /**
     * Format Response for Validator
     *
     * @param \Paytrail\SDK\Response\EmailRefundResponse $response
     *
     * @return array
     */
    public function formatResponse(EmailRefundResponse $response): array
    {
        return [
            'status'         => $response->getStatus(),
            'provider'       => $response->getProvider(),
            'transaction_id' => $response->getTransactionId()
        ];
    }
}
