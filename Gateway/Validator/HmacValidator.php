<?php

namespace Paytrail\PaymentService\Gateway\Validator;

use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;

class HmacValidator
{
    public const SKIP_HMAC_VALIDATION = 'skip_hmac';

    /**
     * HmacValidator constructor.
     *
     * @param PaytrailLogger $log
     * @param Adapter $paytrailAdapter
     */
    public function __construct(
        private PaytrailLogger $log,
        private Adapter $paytrailAdapter
    ) {
    }

    /**
     * Validate HMAC signature.
     *
     * @param array $params
     * @param string $signature
     * @return bool
     */
    public function validateHmac(array $params, string $signature): bool
    {
        try {
            $this->log->debugLog(
                'request',
                \sprintf(
                    'Validating Hmac for transaction: %s',
                    $params["checkout-transaction-id"]
                )
            );
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $paytrailClient->validateHmac($params, '', $signature);
        } catch (\Exception $e) {
            $this->log->error(sprintf(
                'Paytrail PaymentService error: Hmac validation failed for transaction %s',
                $params["checkout-transaction-id"]
            ));

            return false;
        }
        $this->log->debugLog(
            'response',
            sprintf(
                'Hmac validation successful for transaction: %s',
                $params["checkout-transaction-id"]
            )
        );

        return true;
    }
}
