<?php
namespace Paytrail\PaymentService\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Paytrail\PaymentService\Helper\Data as paytrailHelper;
use Paytrail\PaymentService\Helper\ApiData;
use Paytrail\PaymentService\Gateway\Config\Config;

class ResponseValidator extends AbstractValidator
{

    /**
     * @var paytrailHelper
     */
    private $paytrailHelper;

    /**
     * @var ApiData
     */
    private $apiData;
    /**
     * @var Config
     */
    private $gatewayConfig;

    /**
     * ResponseValidator constructor.
     * @param paytrailHelper $paytrailHelper
     * @param Config $gatewayConfig
     * @param ResultInterfaceFactory $resultFactory
     * @param ApiData $apiData
     */
    public function __construct(
        paytrailHelper $paytrailHelper,
        Config $gatewayConfig,
        ResultInterfaceFactory $resultFactory,
        ApiData $apiData
    ) {
        parent::__construct($resultFactory);
        $this->paytrailHelper = $paytrailHelper;
        $this->apiData = $apiData;
        $this->gatewayConfig = $gatewayConfig;
    }

    /**
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $fails = [];

        if ($this->isRequestMerchantIdEmpty($this->gatewayConfig->getMerchantId())) {
            $fails[] = "Request MerchantId is empty";
        }

        if ($this->isResponseMerchantIdEmpty($validationSubject["checkout-account"])) {
            $fails[] = "Response MerchantId is empty";
        }

        if ($this->isMerchantIdValid($validationSubject["checkout-account"]) == false) {
            $fails[] = "Response and Request merchant ids does not match";
        }

        if ($this->validateResponse($validationSubject) == false) {
            $fails[] = "Invalid response data from Checkout";
        }

        if ($this->validateAlgorithm($validationSubject["checkout-algorithm"]) == false) {
            $fails[] = "Invalid response data from Checkout";
        }

        if (sizeof($fails) > 0) {
            $isValid = false;
        }
        return $this->createResult($isValid, $fails);
    }

    /**
     * @param $responseMerchantId
     * @return bool
     */
    public function isMerchantIdValid($responseMerchantId)
    {
        $requestMerchantId = $this->gatewayConfig->getMerchantId();
        if ($requestMerchantId == $responseMerchantId) {
            return true;
        }

        return false;
    }

    /**
     * @param $requestMerchantId
     * @return bool
     */
    public function isRequestMerchantIdEmpty($requestMerchantId)
    {
        return empty($requestMerchantId);
    }

    /**
     * @param $responseMerchantId
     * @return bool
     */
    public function isResponseMerchantIdEmpty($responseMerchantId)
    {
        return empty($responseMerchantId);
    }

    /**
     * @param $algorithm
     * @return bool
     */
    public function validateAlgorithm($algorithm)
    {
        return in_array($algorithm, $this->paytrailHelper->getValidAlgorithms(), true);
    }

    /**
     * @param $params
     * @return bool
     */
    public function validateResponse($params)
    {
        return $this->apiData->validateHmac($params, $params["signature"]);
    }
}
