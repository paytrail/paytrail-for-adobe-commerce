<?php
namespace Paytrail\PaymentService\Model;

use Magento\Framework\Model\AbstractModel;
use Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface;

class CustomerPaymentMethodsResult extends AbstractModel implements CustomerPaymentMethodsResultInterface
{
    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->getData('type');
    }

    /**
     * @param $type
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface
     */
    public function setType($type): self
    {
        $this->setData('type', $type);

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethodCode(): string
    {
        return $this->getData('payment_method_code');
    }

    /**
     * @param $code
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface
     */
    public function setPaymentMethodCode($code): self
    {
        $this->setData('payment_method_code', $code);

        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedAt(): string
    {
        return $this->getData('created_at');
    }

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface
     */
    public function setCreatedAt($data): self
    {
        $this->setData('created_at', $data);

        return $this;
    }

    /**
     * @return string
     */
    public function getExpiresAt(): string
    {
        return $this->getData('expires_at');
    }

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface
     */
    public function setExpiresAt($data): self
    {
        $this->setData('expires_at', $data);

        return $this;
    }

    /**
     * @return string
     */
    public function getCardType(): string
    {
        return $this->getData('card_type');
    }

    /**
     * @param $cardType
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface
     */
    public function setCardType($cardType): self
    {
        $this->setData('card_type', $cardType);

        return $this;
    }

    /**
     * @return string
     */
    public function getMaskedCC(): string
    {
        return $this->getData('maskedCC');
    }

    /**
     * @param $maskedCC
     * @return \Paytrail\PaymentService\Api\Data\CustomerPaymentMethodsResultInterface
     */
    public function setMaskedCC($maskedCC): self
    {
        $this->setData('maskedCC', $maskedCC);

        return $this;
    }
}
