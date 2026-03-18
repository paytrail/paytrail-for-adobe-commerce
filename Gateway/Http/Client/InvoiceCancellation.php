<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Magento\Payment\Gateway\Http\TransferInterface;

class InvoiceCancellation implements ClientInterface
{
    /**
     * InvoiceCancellation constructor.
     *
     * @param Adapter $paytrailAdapter
     * @param PaytrailLogger $log
     */
    public function __construct(
        private readonly Adapter $paytrailAdapter,
        private readonly PaytrailLogger $log
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param TransferInterface $transferObject
     * @return array|void
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        $response = $this->invoiceCancellation(
            $request['transaction_id']
        );
        $error = $response["error"];

        if ($error) {
            $this->log->error(
                'Error occurred during invoice cancellation: '
                . $error
            );
        }

        return $response;
    }

    /**
     * Invoice cancellation method.
     *
     * @param string $transactionId
     * @return array
     */
    public function invoiceCancellation($transactionId): array
    {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API',
                    'invoice cancellation'
                )
            );

            // handle token_request request
            $response["data"] = $paytrailClient->cancelInvoice($transactionId);
            $this->log->debugLog(
                'response',
                'Successful response for invoice cancellation'
            );
        } catch (RequestException $e) {
            $this->log->error(\sprintf(
                'Connection error to Paytrail Payment Service API: %s Error Code: %s',
                $e->getMessage(),
                $e->getCode()
            ));

            if ($e->hasResponse()) {
                $response["error"] = $e->getMessage();
                return $response;
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
}
