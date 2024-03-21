<?php

namespace Paytrail\PaymentService\Block\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Paytrail\PaymentService\Gateway\Config\Config;

class Version extends Field
{
    /**
     * Version block constructor.
     *
     * @param Config $gatewayConfig
     * @param Context $context
     */
    public function __construct(
        private Config $gatewayConfig,
        Context        $context
    ) {
        parent::__construct($context);
    }

    /**
     * Get element HTML.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        try {
            $currentVersion = 'v' . $this->gatewayConfig->getVersion();
            $githubContent = $this->gatewayConfig->getDecodedContentFromGithub();

            if ($currentVersion < $githubContent['tag_name']) {
                $html =
                    '<strong style="color: red">'
                    . $currentVersion
                    . __(" - Newer version (%1) available. ", $githubContent['tag_name'])
                    .
                    "<a href= \"" . $githubContent['html_url']
                    . "\" target='_blank'> "
                    .
                    __("More details")
                    . "</a></strong>";
            } elseif ($currentVersion == $githubContent['tag_name']) {
                $html = '<strong style="color: green">' . __("%1 - Latest version", $currentVersion) . '</strong>';
            } else {
                $html = '<strong style="color: darkorange">' . __("%1 - Custom version", $currentVersion)
                    . '<br>'
                    . __(
                        "Your version is higher than latest official version (%1)",
                        $githubContent['tag_name']
                    )
                    . __("please make sure that you have installed the module from verified source.")
                    . "<a href= \"" . $githubContent['html_url']
                    . "\" target='_blank'> "
                    .
                    __("More details")
                    . "</a></strong>";
            }
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
            return '<strong>' . __("%1 - Can't check for updates now", $currentVersion ?? '') . '</strong>';
        }
        return $html;
    }
}
