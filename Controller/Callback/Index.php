<?php

namespace Paytrail\PaymentService\Controller\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
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
     * Index constructor.
     * @param Context $context
     * @param Session $session
     * @param ProcessPayment $processPayment
     */
    public function __construct(
        Session $session,
        ProcessPayment $processPayment,
        RequestInterface $request
    ) {
        $this->session = $session;
        $this->request = $request;
        $this->processPayment = $processPayment;
    }

    /**
     * execute method
     */
    public function execute()
    {
        $this->processPayment->process($this->request->getParams(), $this->session);

        return;
    }
}
