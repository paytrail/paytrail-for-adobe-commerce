<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use GuzzleHttp\Exception\RequestException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Magento\Payment\Gateway\Http\TransferInterface;
use Paytrail\SDK\Request\GetTokenRequest;
use Paytrail\SDK\Request\PaymentStatusRequest;

class TokenRequest implements ClientInterface
{
    /**
     * TokenRequest constructor.
     *
     * @param PaymentStatusRequest $paymentStatusRequest
     * @param Adapter $paytrailAdapter
     * @param Json $json
     * @param PaytrailLogger $log
     */
    public function __construct(
        private readonly PaymentStatusRequest $paymentStatusRequest,
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

        $response = $this->placeTokenRequest(
            $request['get_token_request']
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
     * Place token_request request.
     *
     * @param GetTokenRequest $getTokenRequest
     * @return array
     */
    public function placeTokenRequest($getTokenRequest): array
    {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API',
                    'payment'
                )
            );

            // handle token_request request
            $response['data'] = $paytrailClient->createGetTokenRequest($getTokenRequest);
            $this->log->debugLog(
                'response',
                'Successful response for getting token request.'
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
