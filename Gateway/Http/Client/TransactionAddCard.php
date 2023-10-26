<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Magento\Payment\Gateway\Http\TransferInterface;
use Paytrail\SDK\Request\AddCardFormRequest;

class TransactionAddCard implements ClientInterface
{
    /**
     * TransactionAddCard constructor.
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

        $response = $this->getAddCardData(
            $request['add_card_form']
        );
        $error = $response["error"];

        if ($error) {
            $this->log->error(
                'Error occurred during getting payment data: '
                . $error
            );
        }

        return $response;
    }

    /**
     * Get add card data.
     *
     * @param AddCardFormRequest $addCardFormRequest
     * @return array
     */
    public function getAddCardData($addCardFormRequest): array
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
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // handle add_card request
            $response['data'] = $paytrailClient->createAddCardFormRequest($addCardFormRequest);
            $this->log->debugLog(
                'response',
                'Successful response for adding card form.'
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
