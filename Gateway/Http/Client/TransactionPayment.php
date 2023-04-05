<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\SDK\Request\PaymentRequest;
use Psr\Log\LoggerInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionPayment implements ClientInterface
{
    /**
     * TransactionPayment constructor.
     *
     * @param Adapter $paytrailAdapter
     * @param PaymentRequest $paymentRequest
     * @param Json $json
     * @param PaytrailLogger $log
     */
    public function __construct(
        private Adapter $paytrailAdapter,
        private PaymentRequest $paymentRequest,
        private Json $json,
        private PaytrailLogger $log
    ) {
    }

    /**
     * PlaceRequest function
     *
     * @param TransferInterface $transferObject
     * @return array|void
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        $response = $this->payment(
            array_first($request)
        );
        $error = $response["error"];

        if ($error) {
            $this->log->error(
                'Error occurred during refund: '
                . $error
                . ', Falling back to to email refund.'
            );
        }
        return $response["data"];
    }

    /**
     * Payment function
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Paytrail\SDK\Exception\HmacException
     * @throws \Paytrail\SDK\Exception\ValidationException
     */
    public function payment($paytrailPayment)
    {
        $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

        $this->log->debugLog(
            'request',
            \sprintf(
                'Creating %s request to Paytrail API %s',
                'payment',
                isset($order) ? 'With order id: ' . $order->getId() : ''
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
                    $paytrailPayment->getId(),
                    $loggedData
                )
            );

        return $response;
    }
}
