<?php

namespace Paytrail\PaymentService\Model\Action;

use GuzzleHttp\Exception\RequestException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Helper\Data as CheckoutHelper;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\PaymentService\Model\RefundCallback;
use Paytrail\SDK\Request\RefundRequest;
use Psr\Log\LoggerInterface;

/**
 * Class Refund
 */
class Refund
{
    /**
     * @param LoggerInterface $log
     * @param CheckoutHelper $helper
     * @param Adapter $paytrailAdapter
     * @param RefundRequest $refundRequest
     * @param RefundCallback $refundCallback
     */
    public function __construct(
        private LoggerInterface       $log,
        private CheckoutHelper        $helper,
        private Adapter               $paytrailAdapter,
        private RefundRequest         $refundRequest,
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
    public function refund(
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
                    'refund',
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle request
            $paytrailRefund = $this->refundRequest;
            $this->setRefundRequestData($paytrailRefund, $amount);

            $response["data"] = $paytrailClient->refund($paytrailRefund, $transactionId);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Successful response for refund. Transaction Id: %s',
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
     * @param RefundRequest $paytrailRefund
     * @param $amount
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
