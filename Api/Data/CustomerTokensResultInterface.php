<?php

namespace Paytrail\PaymentService\Api\Data;

interface CustomerTokensResultInterface
{
    /**
     * @return string
     */
    public function getEntityId(): string;

    /**
     * @param $entityId
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setEntityId($entityId): self;

    /**
     * @return string
     */
    public function getCustomerId(): string;

    /**
     * @param $customerId
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCustomerId($customerId): self;

    /**
     * @return string
     */
    public function getPublicHash(): string;

    /**
     * @param $publicHash
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setPublicHash($publicHash): self;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param $type
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setType($type): self;

    /**
     * @return string
     */
    public function getPaymentMethodCode(): string;

    /**
     * @param $code
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setPaymentMethodCode($code): self;

    /**
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCreatedAt($data): self;

    /**
     * @return string
     */
    public function getExpiresAt(): string;

    /**
     * @param $data
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setExpiresAt($data): self;

    /**
     * @return string
     */
    public function getCardType(): string;

    /**
     * @param $cardType
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCardType($cardType): self;

    /**
     * @return string
     */
    public function getMaskedCC(): string;

    /**
     * @param $maskedCC
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setMaskedCC($maskedCC): self;

    /**
     * @return string
     */
    public function getCardIcon(): string;

    /**
     * @param $cardIcon
     * @return \Paytrail\PaymentService\Api\Data\CustomerTokensResultInterface
     */
    public function setCardIcon($cardIcon): self;
}
