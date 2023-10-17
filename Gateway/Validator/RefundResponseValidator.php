<?php

namespace Paytrail\PaymentService\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Paytrail\PaymentService\Block\Form\Paytrail;
use Paytrail\PaymentService\Logger\PaytrailLogger;

class RefundResponseValidator extends AbstractValidator
{
    /**
     * Constructor
     *
     * @param \Paytrail\PaymentService\Logger\PaytrailLogger            $log
     * @param \Magento\Payment\Gateway\Validator\ResultInterfaceFactory $resultFactory
     * @param \Magento\Payment\Gateway\Helper\SubjectReader             $subjectReader
     */
    public function __construct(
        private readonly PaytrailLogger $log,
        private readonly ResultInterfaceFactory $resultFactory,
        private readonly SubjectReader $subjectReader
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * Validate response
     *
     * @param array $validationSubject
     *
     * @return false|\Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface|bool
    {
        $response      = $this->subjectReader->readResponse($validationSubject);
        $errorMessages = [];

        if (isset($response['status']) && $response['status'] === 'ok') {
            return $this->createResult(
                true,
                ['status' => $response['status']]
            );
        }

        if (isset($response['error'])) {
            $errorMessages[] = $response['error'];
            $this->log->error(
                'Error occurred email refund: ' . $response["error"]
            );
        }

        return $this->createResult(false, $errorMessages);
    }
}
