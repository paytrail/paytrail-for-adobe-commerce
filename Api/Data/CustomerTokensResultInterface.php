<?php

namespace Paytrail\PaymentService\Api\Data;

interface CustomerTokensResultInterface
{
    /**
     * Get entity.
     *
     * @return string
     */
    public function getEntityId(): string;

    /**
     * Set entity.
     *
     * @param string $entityId
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setEntityId($entityId): self;

    /**
     * Get customer ID.
     *
     * @return string
     */
    public function getCustomerId(): string;

    /**
     * Set customer ID.
     *
     * @param string $customerId
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCustomerId($customerId): self;

    /**
     * Get public hash.
     *
     * @return string
     */
    public function getPublicHash(): string;

    /**
     * Set public hash.
     *
     * @param string $publicHash
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setPublicHash($publicHash): self;

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set type.
     *
     * @param string $type
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setType($type): self;

    /**
     * Get payment method code.
     *
     * @return string
     */
    public function getPaymentMethodCode(): string;

    /**
     * Set payment method code.
     *
     * @param string $code
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setPaymentMethodCode($code): self;

    /**
     * Get created at value.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set created at value.
     *
     * @param string $data
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCreatedAt($data): self;

    /**
     * Get expires at value.
     *
     * @return string
     */
    public function getExpiresAt(): string;

    /**
     * Set expires at value.
     *
     * @param string $data
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setExpiresAt($data): self;

    /**
     * Get card type.
     *
     * @return string
     */
    public function getCardType(): string;

    /**
     * Set card type.
     *
     * @param string $cardType
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCardType($cardType): self;

    /**
     * Get masked cc.
     *
     * @return string
     */
    public function getMaskedCC(): string;

    /**
     * Set masked cc.
     *
     * @param string $maskedCC
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setMaskedCC($maskedCC): self;

    /**
     * Get card icon.
     *
     * @return string
     */
    public function getCardIcon(): string;

    /**
     * Set card icon.
     *
     * @param string $cardIcon
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCardIcon($cardIcon): self;
}
