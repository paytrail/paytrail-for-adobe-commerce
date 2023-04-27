<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Paytrail\PaymentService\Helper\ProcessPayment;

/**
 * Class Index
 */
class Index implements \Magento\Framework\App\ActionInterface
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ProcessPayment
     */
    private $processPayment;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Session $session,
        ProcessPayment $processPayment,
        RequestInterface $request,
        ResultFactory $resultFactory
    ) {
        $this->session = $session;
        $this->request = $request;
        $this->processPayment = $processPayment;
        $this->resultFactory = $resultFactory;
    }

    /**
     * execute method
     */
    public function execute(): ResultInterface
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $this->processPayment->process($this->request->getParams(), $this->session);

        return $response;
    }
}
