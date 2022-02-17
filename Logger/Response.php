<?php

namespace Paytrail\PaymentService\Logger;

use Monolog\Logger;

/**
 * Class Response
 */
class Response extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::NOTICE;

    /**
     * File name
     * @var string
     */
    protected $fileName = 'var/log/paytrail_payment_service_response.log';
}
