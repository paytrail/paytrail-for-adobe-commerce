<?php

namespace Paytrail\PaymentService\Model\Action;

use GuzzleHttp\Exception\RequestException;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\PaymentService\Model\RefundCallback;
use Paytrail\SDK\Request\EmailRefundRequest;
use Psr\Log\LoggerInterface;

/**
 * Class EmailRefund
 */
class EmailRefund
{
    /**
     * @param LoggerInterface $log
     * @param Adapter $paytrailAdapter
     * @param EmailRefundRequest $emailRefundRequest
     * @param RefundCallback $refundCallback
     */
    public function __construct(
        private LoggerInterface       $log,
        private Adapter               $paytrailAdapter,
        private EmailRefundRequest    $emailRefundRequest,
        private RefundCallback        $refundCallback
    )
    {
    }

    /**
     * @param $order
     * @param $amount
     * @param $transactionId
     * @return array
     */
    public function emailRefund(
        $order = null,
        $amount = null,
        $transactionId = null
    ) {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'email_refund',
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle request
            $paytrailEmailRefund = $this->emailRefundRequest;
            $this->setEmailRefundRequestData($paytrailEmailRefund, $amount, $order);

            $response["data"] = $paytrailClient->emailRefund($paytrailEmailRefund, $transactionId);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Successful response for email refund. Transaction Id: %s',
                    $response["data"]->getTransactionId()
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

            return $response;
        }

        return $response;
    }

    /**
     * @param EmailRefundRequest $paytrailEmailRefund
     * @param $amount
     * @param $order
     */
    protected function setEmailRefundRequestData($paytrailEmailRefund, $amount, $order)
    {
        $paytrailEmailRefund->setEmail($order->getBillingAddress()->getEmail());

        $paytrailEmailRefund->setAmount(round($amount * 100));

        $callback = $this->refundCallback->createRefundCallback();
        $paytrailEmailRefund->setCallbackUrls($callback);
    }
}
