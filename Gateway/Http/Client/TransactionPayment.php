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
use Paytrail\SDK\Response\PaymentResponse;

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

            // TODO: remove after testing
            $this->setApplePayCustomProviders($response['data'], $paytrailPayment->getAmount());

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

    // TODO: remove after success testing on staging
    /**
     * @param $responseData
     * @return PaymentResponse
     */
    protected function setApplePayCustomProviders($responseData, $amount): \Paytrail\SDK\Response\PaymentResponse
    {
        $customProvidersData = $this->getCustomProvidersData($responseData->getProviders()[0]->getParameters());
        $applePayProvider = new \Paytrail\SDK\Model\Provider();
        $customProvidersData[] = [
            'name' => 'amount',
            'value' => (string)(float)($amount/100)
        ];
        $customProvidersData[] = [
            'name' => 'label',
            'value' => 'Paytrail Oyj'
        ];
        $customProvidersData[] = [
            'name' => 'currency',
            'value' => 'EUR'
        ];

        $applePayProvider
            ->setId('apple-pay')
            ->setGroup('mobile')
            ->setName('Apple Pay')
            ->setParameters($customProvidersData);

        $allProviders = $responseData->getProviders();
        $allProviders[] = $applePayProvider;
        $responseData->setProviders($allProviders);

        return $responseData;
    }

    // TODO: remove after success testing on staging
    /**
     * @param $parametersData
     * @return array
     */
    protected function getCustomProvidersData($parametersData): array
    {
        $customProvidersArray = [];
        $parametersArray = [
            'checkout-transaction-id',
            'checkout-account',
            'checkout-method',
            'checkout-algorithm',
            'checkout-timestamp',
            'checkout-nonce',
            'signature'
        ];
        $i = 0;

        foreach ($parametersData as $parameter) {
            $customProvidersArray[] = [
                'name' => $parametersArray[$i],
                'value' => get_object_vars($parameter)['value']
            ];
            $i++;
        }

        return $customProvidersArray;
    }
}
