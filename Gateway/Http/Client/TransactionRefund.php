<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Model\Action\EmailRefund;
use Paytrail\PaymentService\Model\Action\Refund;
use Paytrail\SDK\Response\RefundResponse;
use Psr\Log\LoggerInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionRefund implements ClientInterface
{
    /**
     * TransactionRefund constructor.
     *
     * @param Refund $refund
     * @param EmailRefund $emailRefund
     * @param LoggerInterface $log
     */
    public function __construct(
        private Refund          $refund,
        private EmailRefund     $emailRefund,
        private LoggerInterface $log
    )
    {
    }

    /**
     * @param TransferInterface $transferObject
     * @return array|void
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();

        $data = [
            'status' => false
        ];

        /** @var RefundResponse $response */
        $response = $this->postRefundRequest($request);

        if ($response) {
            $data['status'] = $response->getStatus();
        }
        return $data;
    }

    /**
     * @param $request
     * @return bool
     */
    protected function postRefundRequest($request)
    {
        $response = $this->refund->refund(
            $request['order'],
            $request['amount'],
            $request['parent_transaction_id']);
        $error = $response["error"];

        if (isset($error)) {
            $this->log->error(
                'Error occurred during refund: '
                . $error
                . ', Falling back to to email refund.'
            );
            $emailResponse = $this->emailRefund->emailRefund(
                $request['order'],
                $request['amount'],
                $request['parent_transaction_id']
            );
            $emailError = $emailResponse["error"];
            if (isset($emailError)) {
                $this->log->error(
                    'Error occurred during email refund: '
                    . $emailError
                );
                return false;
            }
            return $emailResponse["data"];
        }
        return $response["data"];
    }
}
