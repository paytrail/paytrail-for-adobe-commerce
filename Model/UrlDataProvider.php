<?php

namespace Paytrail\PaymentService\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Paytrail\SDK\Model\CallbackUrl;

class UrlDataProvider
{
    /**
     * UrlDataProvider constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     */
    public function __construct(
        private UrlInterface $urlBuilder,
        private RequestInterface $request
    ) {
    }

    /**
     * CreateRedirectUrl function
     *
     * @return CallbackUrl
     */
    public function createRedirectUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('receipt'));
        $callback->setCancel($this->getCallbackUrl('receipt'));

        return $callback;
    }

    /**
     * CreateCallbackUrl function
     *
     * @return CallbackUrl
     */
    public function createCallbackUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('callback'));
        $callback->setCancel($this->getCallbackUrl('callback'));

        return $callback;
    }

    /**
     * GetCallbackUrl function
     *
     * @param string $param
     * @return string
     */
    protected function getCallbackUrl($param)
    {
        $successUrl = $this->urlBuilder->getUrl('paytrail/' . $param, [
            '_secure' => $this->request->isSecure()
        ]);

        return $successUrl;
    }
}
