<?php

namespace Paytrail\PaymentService\Helper;

use GuzzleHttp\Exception\RequestException;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\StoreManagerInterface;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Magento\Tax\Helper\Data as TaxHelper;
use Paytrail\PaymentService\Helper\Data as CheckoutHelper;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Adapter\Adapter;
use Paytrail\SDK\Model\Address;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Model\Item;
use Paytrail\SDK\Request\EmailRefundRequest;
use Paytrail\SDK\Request\PaymentRequest;
use Paytrail\SDK\Request\RefundRequest;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Class ApiData
 */
class ApiData
{
    /**
     * @var CheckoutHelper
     */
    private $helper;

    /**
     * @var PaytrailLogger
     */
    private $log;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var CountryInformationAcquirerInterface
     */
    private $countryInfo;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * @var Adapter
     */
    private $paytrailAdapter;

    /**
     * @var PaymentRequest
     */
    private $paymentRequest;

    /**
     * @var RefundRequest
     */
    private $refundRequest;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var EmailRefundRequest
     */
    private $emailRefundRequest;

    /**
     * @var \Op\Checkout\Model\Payment\DiscountSplitter
     */
    private $discountSplitter;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Tax\Item
     */
    private $taxItems;

    /**
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param Json $json
     * @param CountryInformationAcquirerInterface $countryInformationAcquirer
     * @param TaxHelper $taxHelper
     * @param Data $helper
     * @param Config $resourceConfig
     * @param StoreManagerInterface $storeManager
     * @param Adapter $paytrailAdapter
     * @param PaymentRequest $paymentRequest
     * @param RefundRequest $refundRequest
     * @param EmailRefundRequest $emailRefundRequest
     * @param \Paytrail\PaymentService\Model\Payment\DiscountSplitter $discountSplitter
     * @param \Magento\Sales\Model\ResourceModel\Order\Tax\Item $taxItem
     */
    public function __construct(
        LoggerInterface $log,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Json $json,
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        TaxHelper $taxHelper,
        CheckoutHelper $helper,
        Config $resourceConfig,
        StoreManagerInterface $storeManager,
        Adapter $paytrailAdapter,
        PaymentRequest $paymentRequest,
        RefundRequest $refundRequest,
        EmailRefundRequest $emailRefundRequest,
        \Paytrail\PaymentService\Model\Payment\DiscountSplitter $discountSplitter,
        \Magento\Sales\Model\ResourceModel\Order\Tax\Item $taxItem
    ) {
        $this->log = $log;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->json = $json;
        $this->countryInfo = $countryInformationAcquirer;
        $this->taxHelper = $taxHelper;
        $this->helper = $helper;
        $this->paytrailAdapter = $paytrailAdapter;
        $this->paymentRequest = $paymentRequest;
        $this->refundRequest = $refundRequest;
        $this->storeManager = $storeManager;
        $this->emailRefundRequest = $emailRefundRequest;
        $this->discountSplitter = $discountSplitter;
        $this->taxItems = $taxItem;
    }

    /**
     * Process Api request
     *
     * @param string $requestType
     * @param Order|null $order
     * @param $amount
     * @param $transactionId
     * @param $methodId
     * @return mixed
     */
    public function processApiRequest(
        $requestType,
        $order = null,
        $amount = null,
        $transactionId = null
    ) {
        $response["data"] = null;
        $response["error"] = null;

        try {
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $this->log->debugLog(
                'request',
                \sprintf(
                    'Creating %s request to Paytrail API %s',
                    $requestType,
                    isset($order) ? 'With order id: ' . $order->getId() : ''
                )
            );

            // Handle payment requests
            if ($requestType === 'payment') {
                $paytrailPayment = $this->paymentRequest;
                $this->setPaymentRequestData($paytrailPayment, $order);

                $response["data"] = $paytrailClient->createPayment($paytrailPayment);

                $loggedData = $this->json->serialize([
                    'transactionId' => $response["data"]->getTransactionId(),
                    'href' => $response["data"]->getHref()
                ]);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for order id: %s with data: %s',
                        $order->getId(),
                        $loggedData
                    )
                );

            // Handle refund requests
            } elseif ($requestType === 'refund') {
                $paytrailRefund = $this->refundRequest;
                $this->setRefundRequestData($paytrailRefund, $amount);

                $response["data"] = $paytrailClient->refund($paytrailRefund, $transactionId);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for refund. Transaction Id: %s',
                        $response["data"]->getTransactionId()
                    )
                );

            // Handle email refund requests
            } elseif ($requestType === 'email_refund') {
                $paytrailEmailRefund = $this->emailRefundRequest;
                $this->setEmailRefundRequestData($paytrailEmailRefund, $amount, $order);

                $response["data"] = $paytrailClient->emailRefund($paytrailEmailRefund, $transactionId);

                $this->log->debugLog(
                    'response',
                    sprintf(
                        'Successful response for email refund. Transaction Id: %s',
                        $response["data"]->getTransactionId()
                    )
                );
            } elseif ($requestType === 'payment_providers') {
                $response["data"] = $paytrailClient->getGroupedPaymentProviders(
                    $amount,
                    $this->helper->getStoreLocaleForPaymentProvider()
                );
                $this->log->debugLog(
                    'response',
                    'Successful response for payment providers.'
                );
            }
        } catch (RequestException $e) {
            $this->log->error(\sprintf(
                'Connection error to Paytrail Payment Service API: %s Error Code: %s',
                $e->getMessage(),
                $e->getCode()
            ));

            if ($e->hasResponse()) {
                $response["error"] = $e->getMessage();
                return $response;
            }
        } catch (\Exception $e) {
            $this->log->error(
                \sprintf(
                    'A problem occurred during Paytrail Api connection: %s',
                    $e->getMessage()
                ),
                $e->getTrace()
            );
            $response["error"] = $e->getMessage();
            return $response;
        }

        return $response;
    }

    /**
     * @param PaymentRequest $paytrailPayment
     * @param Order $order
     * @return mixed
     * @throws \Exception
     */
    protected function setPaymentRequestData($paytrailPayment, $order)
    {
        $billingAddress = $order->getBillingAddress() ?? $order->getShippingAddress();
        $shippingAddress = $order->getShippingAddress();

        $paytrailPayment->setStamp(hash('sha256', time() . $order->getIncrementId()));

        $reference = $this->helper->getReference($order);

        $paytrailPayment->setReference($reference);

        $paytrailPayment->setCurrency($order->getOrderCurrencyCode())->setAmount(round($order->getGrandTotal() * 100));

        $customer = $this->createCustomer($billingAddress);
        $paytrailPayment->setCustomer($customer);

        $invoicingAddress = $this->createAddress($order, $billingAddress);
        $paytrailPayment->setInvoicingAddress($invoicingAddress);

        if (!is_null($shippingAddress)) {
            $deliveryAddress = $this->createAddress($order, $shippingAddress);
            $paytrailPayment->setDeliveryAddress($deliveryAddress);
        }

        $paytrailPayment->setLanguage($this->helper->getStoreLocaleForPaymentProvider());

        $items = $this->getOrderItemLines($order);

        $paytrailPayment->setItems($items);

        $paytrailPayment->setRedirectUrls($this->createRedirectUrl());

        $paytrailPayment->setCallbackUrls($this->createCallbackUrl());

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * @param RefundRequest $paytrailRefund
     * @param $amount
     * @throws CheckoutException
     */
    protected function setRefundRequestData($paytrailRefund, $amount)
    {
        if ($amount <= 0) {
            $this->helper->processError('Refund amount must be above 0');
        }

        $paytrailRefund->setAmount(round($amount * 100));

        $callback = $this->createRefundCallback();
        $paytrailRefund->setCallbackUrls($callback);
    }

    /**
     * @param EmailRefundRequest $paytrailEmailRefund
     * @param $amount
     * @param $order
     */
    protected function setEmailRefundRequestData($paytrailEmailRefund, $amount, $order)
    {
        $paytrailEmailRefund->setEmail($order->getBillingAddress()->getEmail());

        $paytrailEmailRefund->setAmount(round($amount * 100));

        $callback = $this->createRefundCallback();
        $paytrailEmailRefund->setCallbackUrls($callback);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
     * @return Customer
     */
    protected function createCustomer($billingAddress)
    {
        $customer = new Customer();

        $customer->setEmail($billingAddress->getEmail())
            ->setFirstName($billingAddress->getFirstName())
            ->setLastName($billingAddress->getLastname())
            ->setPhone($billingAddress->getTelephone());

        return $customer;
    }

    /**
     * @param Order $order
     * @param $address
     * @throws NoSuchEntityException
     * @return Address
     */
    protected function createAddress($order, $address)
    {
        $paytrailAddress = new Address();

        $country = $this->countryInfo->getCountryInfo(
            $address->getCountryId()
        )
            ->getTwoLetterAbbreviation();
        $streetAddressRows = $address->getStreet();
        $streetAddress = $streetAddressRows[0];
        if (mb_strlen($streetAddress, 'utf-8') > 50) {
            $streetAddress = mb_substr($streetAddress, 0, 50, 'utf-8');
        }

        $paytrailAddress->setStreetAddress($streetAddress)
            ->setPostalCode($address->getPostcode())
            ->setCity($address->getCity())
            ->setCountry($country);

        if (!empty($address->getRegion())) {
            $paytrailAddress->setCounty($address->getRegion());
        }

        return $paytrailAddress;
    }

    /**
     * @param Order $order
     * @param string $methodId
     * @return array
     * @throws \Exception
     */
    protected function getOrderItemLines($order)
    {
        $orderItems = $this->itemArgs($order);
        $orderTotal = round($order->getGrandTotal() * 100);

        $items = array_map(
            function ($item) use ($order) {
                return $this->createOrderItems($item);
            },
            $orderItems
        );

        $itemSum = 0;
        $itemQty = 0;

        /** @var Item $orderItem */
        foreach ($items as $orderItem) {
            $itemSum += floatval($orderItem->getUnitPrice() * $orderItem->getUnits());
            $itemQty += $orderItem->getUnits();
        }

        if ($itemSum != $orderTotal) {
            $diffValue = abs($itemSum - $orderTotal);

            if ($diffValue > $itemQty) {
                throw new \Exception(__('Difference in rounding the prices is too big ' . $orderTotal . ' --- ' . $itemSum ));
            }

            $roundingItem = new Item();
            $roundingItem->setDescription(__('Rounding', 'paytrail-for-adobe-commerce'));
            $roundingItem->setDeliveryDate(date('Y-m-d'));
            $roundingItem->setVatPercentage(0);
            $roundingItem->setUnits(($orderTotal - $itemSum > 0) ? 1 : -1);
            $roundingItem->setUnitPrice($diffValue);
            $roundingItem->setProductCode('rounding-row');

            $items[] = $roundingItem;
        }
        return $items;
    }

    /**
     * @param OrderItem $item
     * @return Item
     */
    protected function createOrderItems($item)
    {
        $paytrailItem = new Item();

        $paytrailItem->setUnitPrice(round($item['price'] * 100))
            ->setUnits($item['amount'])
            ->setVatPercentage($item['vat'])
            ->setProductCode($item['code'])
            ->setDeliveryDate(date('Y-m-d'))
            ->setDescription($item['title']);

        return $paytrailItem;
    }

    /**
     * @return CallbackUrl
     */
    protected function createRefundCallback()
    {
        $callback = new CallbackUrl();

        try {
            $storeUrl = $this->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        } catch (NoSuchEntityException $e) {
            $storeUrl = $this->urlBuilder->getBaseUrl();
        }

        $callback->setSuccess($storeUrl);
        $callback->setCancel($storeUrl);

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createRedirectUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('receipt'));
        $callback->setCancel($this->getCallbackUrl('receipt'));

        return $callback;
    }

    /**
     * @return CallbackUrl
     */
    protected function createCallbackUrl()
    {
        $callback = new CallbackUrl();

        $callback->setSuccess($this->getCallbackUrl('callback'));
        $callback->setCancel($this->getCallbackUrl('callback'));

        return $callback;
    }

    /**
     * @param $param
     * @return string
     */
    protected function getCallbackUrl($param)
    {
        $successUrl = $this->urlBuilder->getUrl('paytrail/' . $param, [
            '_secure' => $this->request->isSecure()
        ]);

        return $successUrl;
    }

    /**
     * @param Order $order
     * @param string $methodId
     * @return array|null
     * @throws LocalizedException
     */
    protected function itemArgs($order)
    {
        $items = [];

        # Add line items
        /** @var $item OrderItem */
        foreach ($order->getAllItems() as $item) {
            $discountIncl = 0;
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountIncl += $item->getDiscountAmount();
            }

            if (!$item->getQtyOrdered()) {
                // Prevent division by zero errors
                throw new LocalizedException(\__('Quantity missing for order item: %sku', ['sku' => $item->getSku()]));
            }

            // When in grouped or bundle product price is dynamic (product_calculations = 0)
            // then also the child products has prices so we set
            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => 0,
                    'vat' => 0
                ];
            } else {
                $items[] = [
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => floatval($item->getPriceInclTax()) - round(($discountIncl / $item->getQtyOrdered()), 2),
                    'vat' => round(floatval($item->getTaxPercent()))
                ];
            }
        }

        // Add shipping
        if (!$order->getIsVirtual()) {
            $items[] = $this->getShippingItem($order);
        }

        return $this->discountSplitter->process($items, $order);
    }

    /**
     * @param $params
     * @param $signature
     * @return bool
     */
    public function validateHmac($params, $signature)
    {
        try {
            $this->log->debugLog(
                'request',
                \sprintf(
                    'Validating Hmac for transaction: %s',
                    $params["checkout-transaction-id"]
                )
            );
            $paytrailClient = $this->paytrailAdapter->initPaytrailMerchantClient();

            $paytrailClient->validateHmac($params, '', $signature);
        } catch (\Exception $e) {
            $this->log->error(
                sprintf(
                    'Paytrail PaymentService error: Hmac validation failed for transaction %s',
                    $params["checkout-transaction-id"]
                )
            );
            return false;
        }
        $this->log->debugLog(
            'response',
            sprintf(
                'Hmac validation successful for transaction: %s',
                $params["checkout-transaction-id"]
            )
        );
        return true;
    }

    private function getShippingItem(Order $order)
    {
        $taxDetails = [];
        $price = 0;

        if ($order->getShippingAmount()) {
            foreach ($this->taxItems->getTaxItemsByOrderId($order->getId()) as $detail) {
                if (isset($detail['taxable_item_type']) && $detail['taxable_item_type'] == 'shipping') {
                    $taxDetails = $detail;
                    break;
                }
            }

            $price = $order->getShippingAmount();
            $price -= $order->getShippingDiscountAmount();
            $price += $order->getShippingTaxAmount();
            $price += $order->getShippingDiscountTaxCompensationAmount();
        }

        return [
            'title' => $order->getShippingDescription() ?: __('Shipping'),
            'code' => 'shipping-row',
            'amount' => 1,
            'price' => floatval($price),
            'vat' => $taxDetails['tax_percent'] ?? 0,
        ];
    }
}
