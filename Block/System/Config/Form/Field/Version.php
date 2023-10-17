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
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Get element HTML.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $version = 'v' . $this->gatewayConfig->getVersion();
        try {
            $githubContent = $this->gatewayConfig->getDecodedContentFromGithub();

            if ($version != $githubContent['tag_name']) {
                $html =
                    '<strong style="color: red">'
                    . $version
                    . __(" - Newer version (%1) available. ", $githubContent['tag_name'])
                    .
                    "<a href= \"" . $githubContent['html_url']
                    . "\" target='_blank'> "
                    .
                    __("More details")
                    . "</a></strong>";
            } else {
                $html = '<strong style="color: green">' . __("%1 - Latest version", $version) . '</strong>';

            }
        } catch (\Exception $e) {
            return '<strong>' . __("%1 - Can't check for updates now", $version) . '</strong>';
        }
        return $html;
    }
}
