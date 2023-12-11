<?php

namespace Paytrail\PaymentService\Tests\Gateway\Validator;

use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Gateway\Validator\HmacValidator;
use Paytrail\PaymentService\Gateway\Validator\ResponseValidator;
use PHPUnit\Framework\TestCase;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class ResponseValidatorUnitTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $gatewayConfigMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $resultInterfaceFactoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $hmacValidatorMock;

    /** @var  ResponseValidator */
    private $responseValidator;

    /**
     * @var array
     */
    private $shouldFail = [
        'checkout-account'   => 'test-merchant-id',
        'checkout-reference' => 123123123123123,
        'checkout-algorithm' => 'sha256',
        'signature'          => 'test-signature'
    ];

    /**
     * @var array
     */
    private $shouldPass = [
        'checkout-account'   => 'invalid-checkout-accountid',
        'checkout-reference' => 'invalid-checkout-reference',
        'checkout-algorithm' => 'sha257',
        'signature'          => 'test-signature'
    ];

    private function getSimpleMock($originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function setUp(): void
    {
        $this->gatewayConfigMock = $this->getSimpleMock(Config::class);
        // Create a mock for the factory class
        $this->resultInterfaceFactoryMock = $this->getMockBuilder(
            \Magento\Payment\Gateway\Validator\ResultInterfaceFactory::class
        )
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();


        // Create a mock for the result interface
        $this->resultMock = $this->getMockBuilder(ResultInterface::class)
            ->getMock();


        $this->hmacValidatorMock = $this->getSimpleMock(HmacValidator::class);

        $this->responseValidator = new ResponseValidator(
            $this->gatewayConfigMock,
            $this->resultInterfaceFactoryMock,
            $this->hmacValidatorMock
        );
    }

    /**
     * @return void
     */
    public function testValidateCreatesValidResult()
    {
        $this->resultInterfaceFactoryMock->expects($this->once())->method('create')->with([
                                                                                              'isValid'          => false,
                                                                                              'failsDescription' => [
                                                                                                  0 => 'Request MerchantId is empty',
                                                                                                  1 => 'Response and Request merchant ids does not match',
                                                                                                  2 => 'Invalid response data from Paytrail',
                                                                                                  3 => 'Invalid response data from Paytrail'
                                                                                              ],
                                                                                              'errorCodes'       => []
                                                                                          ]);

        $this->responseValidator->validate($this->shouldPass);
    }

    /**
     * @return void
     */
    public function testValidateCreatesInValidResult()
    {
        $this->resultInterfaceFactoryMock->expects($this->once())->method('create')->with([
                                                                                              'isValid'          => false,
                                                                                              'failsDescription' => [
                                                                                                  0 => 'Request MerchantId is empty',
                                                                                                  1 => 'Response and Request merchant ids does not match',
                                                                                                  2 => 'Invalid response data from Paytrail',
                                                                                                  3 => 'Invalid response data from Paytrail'],
                                                                                              'errorCodes'       => []]
        );

        $this->responseValidator->validate($this->shouldFail);
    }

    /**
     * @return void
     */
    public function testIsRequestMerchantIdEmpty()
    {
        $trueResult = $this->responseValidator->isRequestMerchantIdEmpty(null);

        self::assertTrue($trueResult);

        $trueResult = $this->responseValidator->isRequestMerchantIdEmpty('');

        self::assertTrue($trueResult);

        $falseResult = $this->responseValidator->isRequestMerchantIdEmpty(123);

        self::assertFalse($falseResult);
    }

    /**
     * @return void
     */
    public function testIsResponseMerchantIdEmpty()
    {
        $trueResult = $this->responseValidator->isResponseMerchantIdEmpty(null);

        self::assertTrue($trueResult);

        $falseResult = $this->responseValidator->isResponseMerchantIdEmpty($this->shouldPass['checkout-account']);

        self::assertFalse($falseResult);
    }

    /**
     * @return void
     */
    public function testMerchantIdDoesNotMatch()
    {
        $falseResult = $this->responseValidator->isMerchantIdValid($this->shouldFail['checkout-account']);

        self::assertFalse($falseResult);
    }

    /**
     * @return void
     */
    public function testValidateAlgorithm()
    {
        $falseResult = $this->responseValidator->validateAlgorithm($this->shouldFail['checkout-algorithm']);

        self::assertFalse($falseResult);
    }

    /**
     * @return void
     */
    public function testValidateResponse()
    {
        $trueResult = $this->responseValidator->validateResponse($this->shouldPass);

        self::assertFalse($trueResult);

        $falseResult = $this->responseValidator->validateResponse($this->shouldFail);

        self::assertFalse($falseResult);
    }
}
