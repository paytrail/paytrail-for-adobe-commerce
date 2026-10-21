<?php

namespace Paytrail\PaymentService\Notification\Model\Message;

use Magento\AdminNotification\Model\InboxFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Notification\MessageInterface;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;

class VersionNotification implements MessageInterface
{
    private const MESSAGE_IDENTITY = 'Paytrail Payment Service Version Control message';
    private const GITHUB_VERSION_CACHE_KEY = 'paytrail_github_version_data';
    private const GITHUB_VERSION_CACHE_TTL = 86400;

    /**
     * VersionNotification constructor.
     *
     * @param Session $authSession
     * @param InboxFactory $inboxFactory
     * @param Config $gatewayConfig
     * @param PaytrailLogger $paytrailLogger
     * @param CacheInterface $cache
     */
    public function __construct(
        private Session        $authSession,
        private InboxFactory   $inboxFactory,
        private Config         $gatewayConfig,
        private PaytrailLogger $paytrailLogger,
        private CacheInterface $cache
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
            $githubContent = $this->getGithubVersionData();
            $this->setSessionData("PaytrailGithubVersion", $githubContent);

            /*
             * This will compare the currently installed version with the latest available one.
             * A message will appear after the login if the two are not matching.
             */
            if (empty($githubContent)) {
                $this->paytrailLogger->logData(
                    \Monolog\Logger::WARNING,
                    'Github content data not provided.'
                );
            } elseif ('v' . $this->gatewayConfig->getVersion() != $githubContent['tag_name']) {
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
     * Get GitHub release metadata with 24h cache.
     *
     * @return array
     */
    private function getGithubVersionData(): array
    {
        $cacheData = $this->cache->load(self::GITHUB_VERSION_CACHE_KEY);

        if ($cacheData !== false) {
            $content = json_decode($cacheData, true);
            if (is_array($content)) {
                return $content;
            }
        }

        $content = $this->gatewayConfig->getDecodedContentFromGithub();

        if (!empty($content)) {
            $this->cache->save(json_encode($content), self::GITHUB_VERSION_CACHE_KEY, [], self::GITHUB_VERSION_CACHE_TTL);
        }

        return is_array($content) ? $content : [];
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
