<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
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
     * @var ResponseInterface
     */
    private $response;

    /**
     * @param Session $session
     * @param ProcessPayment $processPayment
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function __construct(
        Session $session,
        ProcessPayment $processPayment,
        RequestInterface $request,
        ResponseInterface $response
    ) {
        $this->session = $session;
        $this->request = $request;
        $this->processPayment = $processPayment;
        $this->response = $response;
    }

    /**
     * execute method
     */
    public function execute(): ResponseInterface
    {
        $this->processPayment->process($this->request->getParams(), $this->session);

        return $this->response;
    }
}
