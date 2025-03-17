<?php

namespace Paytrail\PaymentService\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class PaytrailCheckoutStatus extends Column
{
    /**
     * PaytrailCheckoutStatus constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface                 $context,
        UiComponentFactory               $uiComponentFactory,
        private OrderRepositoryInterface $orderRepository,
        array                            $components = [],
        array                            $data = []
    )
    {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Theme Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item['paytrail_checkout_status'] = $this->orderRepository->get($item['entity_id'])->getPaytrailCheckoutStatus();
            }
        }

        return $dataSource;
    }
}
