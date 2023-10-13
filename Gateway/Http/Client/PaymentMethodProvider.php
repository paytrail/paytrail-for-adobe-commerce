<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Magento\Payment\Gateway\Http\TransferInterface;

class PaymentMethodProvider implements ClientInterface
{
    /**
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

        $response = $this->getPaymentMethods($request);

        $error = $response["error"];

        if ($error) {
            $this->log->error(
                'Error occurred during providing payment methods: '
                . $error
            );
        }

        return $response;
    }

    /**
     * GetPaymentMethods function
     *
     * @param array $request
     * @return array
     */
    protected function getPaymentMethods($request)
    {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    'payment_providers',
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle request
            $response["data"] = $paytrailClient->getGroupedPaymentProviders(
                $request['amount'],
                $request['locale'],
                $request['groups']
            );
            $this->log->debugLog(
                'response',
                'Successful response for payment providers.'
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
