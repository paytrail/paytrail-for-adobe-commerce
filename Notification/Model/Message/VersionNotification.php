<?php

namespace Paytrail\PaymentService\Notification\Model\Message;

class VersionNotification implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $authSession;
    /**
     * @var \Magento\AdminNotification\Model\InboxFactory
     */
    private $inboxFactory;
    /**
     * @var \Magento\Framework\Component\ComponentRegistrarInterface
     */
    private $componentRegistrar;
    /**
     * @var \Magento\Framework\Notification\NotifierInterface
     */
    private $notifierPool;
    /**
     * @var \Paytrail\PaymentService\Helper\Version
     */
    private $versionHelper;

    public function __construct(
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\AdminNotification\Model\InboxFactory $inboxFactory,
        \Magento\Framework\Component\ComponentRegistrarInterface $componentRegistrar,
        \Magento\Framework\Notification\NotifierInterface $notifierPool,
        \Paytrail\PaymentService\Helper\Version $versionHelper
    ) {
        $this->authSession = $authSession;
        $this->inboxFactory = $inboxFactory;
        $this->componentRegistrar = $componentRegistrar;
        $this->notifierPool = $notifierPool;
        $this->versionHelper = $versionHelper;
    }

    const MESSAGE_IDENTITY = 'Paytrail Payment Service Version Control message';

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
            $githubContent = $this->versionHelper->getDecodedContentFromGithub();
            $this->setSessionData("PaytrailGithubVersion", $githubContent);

            /*
             * This will compare the currently installed version with the latest available one.
             * A message will appear after the login if the two are not matching.
             */
            if ('v' . $this->versionHelper->getVersion() != $githubContent['tag_name']) {
                $versionData[] = [
                    'severity' => self::SEVERITY_CRITICAL,
                    'date_added' => date('Y-m-d H:i:s'),
                    'title' => __("Paytrail Payment Service extension version %1 available!", $githubContent['tag_name']),
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
        $message .= __(" You are running the v%1 version. We advise to update your extension.",
            $this->versionHelper->getVersion());
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
     * @param $key
     * @param $value
     * @return mixed
     */
    private function setSessionData($key, $value)
    {
        return $this->authSession->setData($key, $value);
    }

    /**
     * Retrieve the session value
     * @param $key
     * @param bool $remove
     * @return mixed
     */
    private function getSessionData($key, $remove = false)
    {
        return $this->authSession->getData($key, $remove);
    }
}
