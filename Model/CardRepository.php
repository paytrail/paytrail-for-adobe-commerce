<?php

namespace Paytrail\PaymentService\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Paytrail\PaymentService\Api\CardRepositoryInterface;
use Paytrail\PaymentService\Helper\ApiData;

class CardRepository implements CardRepositoryInterface
{
    /**
     * @var ApiData
     */
    private ApiData $apiData;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private PaymentTokenRepositoryInterface $paymentTokenRepository;

    /**
     * @var UserContextInterface
     */
    private UserContextInterface $userContext;

    /**
     * @param ApiData $apiData
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param UserContextInterface $userContext
     */
    public function __construct(
        ApiData $apiData,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        UserContextInterface $userContext
    ) {
        $this->apiData = $apiData;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->userContext = $userContext;
    }

    /**
     * @inheritdoc
     */
    public function save(): string
    {
        $response = $this->apiData->processApiRequest('add_card');

        if (isset($response['error'])) {
            throw new LocalizedException(__($response['error']), null, 403);
        }

        return $response['data']->getHeader('Location')[0];
    }

    /**
     * @inheritdoc
     */
    public function delete(string $cardId): bool
    {
        $paymentToken = $this->paymentTokenRepository->getById((int) $cardId);

        if (!$paymentToken || (int) $paymentToken->getCustomerId() !== $this->userContext->getUserId()) {
            throw new LocalizedException(__('Card not found'));
        }

        $this->paymentTokenRepository->delete($paymentToken);

        return true;
    }
}
