<?php

namespace Paytrail\PaymentService\Block\Redirect;

use Magento\Framework\Data\Form;
use Magento\Framework\View\Element\Context;

/**
 * Class Paytrail
 */
class Paytrail extends \Magento\Framework\View\Element\AbstractBlock
{
    private $form;
    private $params;
    private $url;
    private $formId = 'checkout_form';

    /**
     * Paytrail constructor.
     * @param Form $form
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Form $form,
        Context $context,
        array $data = []
    ) {
        $this->form = $form;
        parent::__construct($context, $data);
    }

    /**
     * @param $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $this->form->setAction($this->url)
            ->setId($this->formId)
            ->setName($this->formId)
            ->setMethod('POST')
            ->setUseContainer(true);

        foreach ($this->params as $key => $value) {
            $this->form->addField($key, 'text', [
                'name' => $key,
                'value' => $value,
            ]);
        }

        return $this->form->toHtml() . $this->_jsSubmit();
    }

    /**
     * @return string
     */
    private function _jsSubmit()
    {
        return '<script type="text/javascript">document.getElementById("' . $this->formId . '").submit();</script>';
    }
}
