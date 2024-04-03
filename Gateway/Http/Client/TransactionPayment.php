<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Magento\Payment\Gateway\Http\TransferInterface;
use Paytrail\SDK\Request\PaymentRequest;

class TransactionPayment implements ClientInterface
{
    /**
     * TransactionPayment constructor.
     *
     * @param Adapter $paytrailAdapter
     * @param Json $json
     * @param PaytrailLogger $log
     */
    public function __construct(
        private readonly Adapter $paytrailAdapter,
        private readonly Json $json,
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

        $response = $this->payment(
            $request['request_data'],
            $request['order']
        );
        $error = $response["error"];

        if ($error) {
            $this->log->error(
                'Error occurred during refund: '
                . $error
                . ', Falling back to to email refund.'
            );
        }

        return $response;
    }

    /**
     * Payment function
     *
     * @param PaymentRequest $paytrailPayment
     * @param Order $order
     * @return array
     */
    public function payment($paytrailPayment, $order): array
    {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'payment',
                    'With order id: ' . $order->getId()
                )
            );

            // Handle payment requests
            $response["data"] = $paytrailClient->createPayment($paytrailPayment);

            $loggedData = $this->json->serialize([
                'transactionId' => $response["data"]->getTransactionId(),
                'href' => $response["data"]->getHref()
            ]);

            $this->log->debugLog(
                'response',
                sprintf(
                    'Successful response for order id: %s with data: %s',
                    $order->getId(),
                    $loggedData
                )
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
