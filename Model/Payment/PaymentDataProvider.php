<?php

namespace Paytrail\PaymentService\Model\Payment;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Tax\Helper\Data as TaxHelper;
use Paytrail\PaymentService\Exceptions\CheckoutException;
use Paytrail\PaymentService\Gateway\Config\Config;
use Paytrail\PaymentService\Logger\PaytrailLogger;
use Paytrail\PaymentService\Model\Company\CompanyRequestData;
use Paytrail\PaymentService\Model\Config\Source\CallbackDelay;
use Paytrail\PaymentService\Model\FinnishReferenceNumber;
use Paytrail\PaymentService\Model\Invoice\Activation\Flag;
use Paytrail\PaymentService\Model\Payment\PaymentDataProvider\OrderItem;
use Paytrail\PaymentService\Model\UrlDataProvider;
use Paytrail\SDK\Exception\ValidationException;
use Paytrail\SDK\Model\Address;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Request\AbstractPaymentRequest;
use Paytrail\SDK\Request\PaymentRequest;

class PaymentDataProvider
{
    /**
     * PaymentDataProvider constructor.
     *
     * @param CompanyRequestData $companyRequestData
     * @param CountryInformationAcquirerInterface $countryInfo
     * @param TaxHelper $taxHelper
     * @param DiscountApply $discountApply
     * @param UrlDataProvider $urlDataProvider
     * @param CallbackDelay $callbackDelay
     * @param FinnishReferenceNumber $referenceNumber
     * @param Config $gatewayConfig
     * @param Flag $flag
     * @param PaytrailLogger $log
     * @param OrderItem $orderItem
     */
    public function __construct(
        private CompanyRequestData                  $companyRequestData,
        private CountryInformationAcquirerInterface $countryInfo,
        private TaxHelper                           $taxHelper,
        private DiscountApply                       $discountApply,
        private UrlDataProvider                     $urlDataProvider,
        private CallbackDelay                       $callbackDelay,
        private FinnishReferenceNumber              $referenceNumber,
        private Config                              $gatewayConfig,
        private Flag                                $flag,
        private PaytrailLogger                      $log,
        private OrderItem                           $orderItem
    ) {
    }

    /**
     * SetPaymentRequestData function
     *
     * @param AbstractPaymentRequest $paytrailPayment
     * @param Order $order
     * @param string $paymentMethod
     *
     * @return AbstractPaymentRequest
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CheckoutException
     * @throws ValidationException
     */
    public function setPaymentRequestData(
        AbstractPaymentRequest $paytrailPayment,
        Order                  $order,
        string                 $paymentMethod
    ): AbstractPaymentRequest {

        $billingAddress   = $order->getBillingAddress() ?? $order->getShippingAddress();
        $shippingAddress  = $order->getShippingAddress();
        $customer         = $this->createCustomer($billingAddress);
        $reference        = $this->referenceNumber->getReference($order);
        $invoicingAddress = $this->createAddress($billingAddress);
        $items            = $this->orderItem->getOrderLines($order);

        $paytrailPayment->setStamp($this->getStamp($order))
            ->setReference($reference)
            ->setCurrency($order->getOrderCurrencyCode())
            ->setAmount(round($order->getGrandTotal() * 100))
            ->setCustomer($customer)
            ->setInvoicingAddress($invoicingAddress)
            ->setLanguage($this->gatewayConfig->getStoreLocaleForPaymentProvider())
            ->setItems($items)
            ->setRedirectUrls($this->urlDataProvider->createRedirectUrl())
            ->setCallbackUrls($this->urlDataProvider->createCallbackUrl())
            ->setCallbackDelay($this->callbackDelay->getCallbackDelay());

        if ($shippingAddress !== null) {
            $deliveryAddress = $this->createAddress($shippingAddress);
            $paytrailPayment->setDeliveryAddress($deliveryAddress);
        }

        // Conditionally set manual invoicing flag if selected payment method supports it.
        $this->flag->setManualInvoiceActivationFlag(
            $paytrailPayment,
            $paymentMethod,
            $order
        );

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * SetPayAndAddCardRequestData function.
     *
     * @param PaymentRequest $paytrailPayment
     * @param Order $order
     *
     * @return PaymentRequest
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CheckoutException
     * @throws ValidationException
     */
    public function setPayAndAddCardRequestData(AbstractPaymentRequest $paytrailPayment, $order): AbstractPaymentRequest
    {
        // Set payment request data - payment method is not needed for pay and add card, so we can set it to new string
        // to mach manual invoicing flag condition
        $this->setPaymentRequestData($paytrailPayment, $order, 'pay_and_add_card');

        $paytrailPayment->setCallbackUrls($this->urlDataProvider->createPayAndAddCardCallbackUrl());

        // Log payment data
        $this->log->debugLog('request', $paytrailPayment);

        return $paytrailPayment;
    }

    /**
     * CreateCustomer function
     *
     * @param OrderAddressInterface $billingAddress
     *
     * @return Customer
     */
    private function createCustomer($billingAddress)
    {
        $customer = new Customer();

        $customer->setEmail($billingAddress->getEmail())
            ->setFirstName($billingAddress->getFirstName())
            ->setLastName($billingAddress->getLastname())
            ->setPhone($billingAddress->getTelephone());

        $this->companyRequestData->setCompanyRequestData($customer, $billingAddress);

        return $customer;
    }

    /**
     * CreateAddress function
     *
     * @param Order\Address $address
     *
     * @return Address
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    private function createAddress($address)
    {
        $paytrailAddress = new Address();

        $country = $this->countryInfo->getCountryInfo($address->getCountryId())
            ->getTwoLetterAbbreviation();

        $streetAddressRows = $address->getStreet();
        $streetAddress     = $streetAddressRows[0];
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
     * Getting stamp for payment request using algorithm defined in config
     *
     * @param Order $order
     *
     * @return string
     */
    public function getStamp(Order $order): string
    {
        return hash($this->gatewayConfig->getCheckoutAlgorithm(), time() . $order->getIncrementId());
    }
}
