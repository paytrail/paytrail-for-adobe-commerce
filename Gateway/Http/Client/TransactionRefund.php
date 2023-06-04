<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Sales\Model\Order;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\SDK\Response\RefundResponse;
use Psr\Log\LoggerInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransactionRefund implements ClientInterface
{
    /**
     * @var ApiData
     */
    private $apiData;

    /**
     * @var Order 
     */
    private $order;
    
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * TransactionRefund constructor.
     *
     * @param ApiData $apiData
     * @param Order $order
     * @param LoggerInterface $log
     */
    public function __construct(
        ApiData $apiData,
        Order $order,
        LoggerInterface $log
    ) {
        $this->apiData = $apiData;
        $this->log = $log;
        $this->order = $order;
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
        $orderAdapter = $request['order'];
        $order = $this->order->loadByIncrementId($orderAdapter->getOrderIncrementId());
        
        $response = $this->apiData->processApiRequest(
            'refund',
            $order,
            $request['amount'],
            $request['parent_transaction_id']
        );
        $error = $response["error"];

        if (isset($error)) {
            $this->log->error(
                'Error occurred during refund: '
                . $error
                . ', Falling back to to email refund.'
            );
            $emailResponse = $this->apiData->processApiRequest(
                'email_refund',
                $order,
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
