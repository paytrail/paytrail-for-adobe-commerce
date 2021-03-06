<?php

namespace Paytrail\PaymentService\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class RefundResponseValidator extends AbstractValidator
{
    /**
     * @var SubjectReader
     */
    private $subjectReader;

    public function __construct(
        ResultInterfaceFactory $resultFactory,
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject)
    {
        $response = $this->subjectReader->readResponse($validationSubject);
        $errorMessages = [];

        if (isset($response['status']) && $response['status'] === 'ok') {
            return $this->createResult(
                true,
                ['status' => $response['status']]
            );
        }
        $errorMessages[] = 'Response status is not ok.';

        return $this->createResult(false, $errorMessages);
    }
}
