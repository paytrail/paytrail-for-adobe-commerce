<?php

namespace Paytrail\PaymentService\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Paytrail\PaymentService\Model\Action\Refund;
use Paytrail\SDK\Response\RefundResponse;
use \Paytrail\PaymentService\Logger\PaytrailLogger;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Command\CommandManagerPoolInterface;

class TransactionRefund implements ClientInterface
{
    /**
     * TransactionRefund constructor.
     *
     * @param Refund                      $refund
     * @param PaytrailLogger              $log
     * @param CommandManagerPoolInterface $commandManagerPool
     */
    public function __construct(
        private readonly Refund $refund,
        private readonly PaytrailLogger $log,
        private readonly CommandManagerPoolInterface $commandManagerPool,
    ) {
    }

    /**
     * PlaceRequest function
     *
     * @param TransferInterface $transferObject
     *
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();

        $data = [
            'status' => false
        ];

        /** @var RefundResponse $response */
        $response = $this->postRefundRequest($request);

        if (is_bool($response)) {
            $data['status'] = $response ? 'ok' : 'error';
        } else {
            $data['status'] = $response->getStatus();
        }

        return $data;
    }

    /**
     * PostRefundRequest function
     *
     * @param $request
     *
     * @return bool
     */
    protected function postRefundRequest($request): bool
    {
        $response = $this->refund->refund(
            $request['order'],
            $request['amount'],
            $request['parent_transaction_id']
        );
        
        $error = $response["error"];
        
        if ($error) {
            $this->log->error(
                'Error occurred during refund: '
                . 'test'
                . ', Falling back to to email refund.'
            );

            try {
                $commandExecutor = $this->commandManagerPool->get($request['payment']->getMethodInstance()->getCode());
                $commandExecutor->executeByCode(
                    'email_refund',
                    $request['payment'],
                    [
                        'amount' => $request['amount'],
                    ]
                );
            } catch (\Exception $e) {
                $this->log->error(
                    'Error occurred during email refund: '
                    . $e->getMessage()
                );

                return false;
            }

            return true;
        }
    }
}
