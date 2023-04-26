<?php
namespace Paytrail\PaymentService\Model\Token;

use Magento\Framework\Model\AbstractModel;
use Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface;

class CustomerTokensResult extends AbstractModel implements CustomerTokensResultInterface
{
    /**
     * @return string
     */
    public function getEntityId(): string
    {
        return $this->getData('entity_id');
    }

    /**
     * @param $entityId
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setEntityId($entityId): self
    {
        $this->setData('entity_id', $entityId);

        return $this;
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->getData('customer_id');
    }

    /**
     * @param $customerId
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCustomerId($customerId): self
    {
        $this->setData('customer_id', $customerId);

        return $this;
    }

    /**
     * @return string
     */
    public function getPublicHash(): string
    {
        return $this->getData('public_hash');
    }

    /**
     * @param $publicHash
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setPublicHash($publicHash): self
    {
        $this->setData('public_hash', $publicHash);

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->getData('type');
    }

    /**
     * @param $type
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
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
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
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
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
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
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
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
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
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
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setMaskedCC($maskedCC): self
    {
        $this->setData('maskedCC', $maskedCC);

        return $this;
    }

    /**
     * @return string
     */
    public function getCardIcon(): string
    {
        return $this->getData('card_icon');
    }

    /**
     * @param $cardIcon
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCardIcon($cardIcon): self
    {
        $this->setData('card_icon', $cardIcon);

        return $this;
    }
}
