<?php

namespace Paytrail\PaymentService\Notification\Model\Message;

use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Notification\MessageInterface;
use Paytrail\PaymentService\Gateway\Config\Config;

class VersionNotification implements MessageInterface
{
    private const MESSAGE_IDENTITY = 'Paytrail Payment Service Version Control message';

    /**
     * VersionNotification constructor.
     *
     * @param Session $authSession
     * @param InboxFactory $inboxFactory
     * @param Config $gatewayConfig
     */
    public function __construct(
        private Session $authSession,
        private InboxFactory $inboxFactory,
        private Config $gatewayConfig,
    ) {
    }

    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        try {
            $githubContent = $this->gatewayConfig->getDecodedContentFromGithub();
            $this->setSessionData("PaytrailGithubVersion", $githubContent);

            /*
             * This will compare the currently installed version with the latest available one.
             * A message will appear after the login if the two are not matching.
             */
            if ('v' . $this->gatewayConfig->getVersion() != $githubContent['tag_name']) {
                $versionData[] = [
                    'severity' => self::SEVERITY_CRITICAL,
                    'date_added' => date('Y-m-d H:i:s'),
                    'title' => __(
                        "Paytrail Payment Service extension version %1 available!",
                        $githubContent['tag_name']
                    ),
                    'description' => $githubContent['body'],
                    'url' => $githubContent['html_url'],
                ];

                /*
                 * The parse function checks if the $versionData message exists in the inbox,
                 * otherwise it will create it and add it to the inbox.
                 */
                $this->inboxFactory->create()->parse(array_reverse($versionData));

                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        $githubContent = $this->getSessionData("PaytrailGithubVersion");
        $message = __('A new Paytrail Payment Service extension version is now available: ');
        $message .= __(
            "<a href= \"" . $githubContent['html_url'] . "\" target='_blank'> " . $githubContent['tag_name'] . "!</a>"
        );
        $message .= __(
            " You are running the v%1 version. We advise to update your extension.",
            $this->gatewayConfig->getVersion()
        );
        return __($message);
    }

    /**
     * Retrieve system message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    /**
     * Set the current value for the backend session
     *
     * @param string $key
     * @param array $value
     * @return mixed
     */
    private function setSessionData($key, $value)
    {
        return $this->authSession->setData($key, $value);
    }

    /**
     * Retrieve the session value
     *
     * @param string $key
     * @param bool $remove
     * @return mixed
     */
    private function getSessionData($key, $remove = false)
    {
        return $this->authSession->getData($key, $remove);
    }
}
