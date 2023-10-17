/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'underscore',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/get-totals',
        'Magento_Checkout/js/model/url-builder',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/totals',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
    ],
    function (ko, $, _, storage, Component, placeOrderAction, selectPaymentMethodAction, additionalValidators, quote, getTotalsAction, urlBuilder, mageUrlBuilder, fullScreenLoader, customer, checkoutData, totals, messageList, $t) {
        'use strict';
        var self;
        var checkoutConfig = window.checkoutConfig.payment;

        return Component.extend(
            {
                defaults: {
                    template: checkoutConfig.paytrail.payment_template
                },
                payMethod: 'paytrail',
                redirectAfterPlaceOrder: false,
                selectedPaymentMethodId: ko.observable(0),
                selectedToken: ko.observable(0),
                tokencheck: ko.observable(true),
                selectedMethodGroup: ko.observable(0),

                initialize: function () {
                    self = this;
                    this._super();

                    if (!self.getIsSuccess()) {
                        self.addErrorMessage($t('Paytrail Payment Service API credentials are incorrect. Please contact support.'));
                    }
                    if (self.getPreviousError()) {
                        self.addErrorMessage(self.getPreviousError());
                    }
                    if (self.getPreviousSuccess()) {
                        self.addSuccessMessage(self.getPreviousSuccess());
                    }
                    if (self.getSkipMethodSelection() == true) {
                        self.selectedPaymentMethodId(self.payMethod);
                    } else {
                        $("<style type='text/css'>" + self.getPaymentMethodStyles() + "</style>").appendTo("head");
                    }
                },
                isLoggedIn: function () {
                    return customer.isLoggedIn();
                },
                isLoginButtonEnabled: function (methodGroup) {
                    if (methodGroup.id === 'creditcard') {
                        return true;
                    }
                    return false;
                },
                isPlaceOrderActionAllowed: function () {
                    if (self.selectedToken() != 0 || self.selectedPaymentMethodId() != 0) {
                        return true;
                    }
                    return false;
                },
                isRecurringPaymentAllowed: function () {
                    if ((window.checkoutConfig.isRecurringScheduled === true && self.selectedToken() != 0)
                        || window.checkoutConfig.isRecurringScheduled === false) {
                        return true;
                    }
                    return false;
                },
                setPaymentMethodId: function (paymentMethod) {
                    self.selectedToken(0);
                    self.selectedPaymentMethodId(paymentMethod.id);
                    $.cookie('checkoutSelectedPaymentMethodId', paymentMethod.id);

                    return true;
                },
                setToken: function (token) {
                    self.selectedToken(token.id);
                    self.selectedPaymentMethodId(0);

                    return true;
                },
                setPaymentGroup: function (paymentGroup) {
                    self.selectedMethodGroup(paymentGroup.id);
                    return true;
                },
                getDefaultSuccessUrl: function () {
                    return checkoutConfig[self.payMethod].default_success_page_url;
                },
                getSelectedToken: function () {
                    return self.selectedToken();
                },
                getInstructions: function () {
                    return checkoutConfig[self.payMethod].instructions;
                },
                getIsSuccess: function () {
                    return checkoutConfig[self.payMethod].success;
                },
                getPreviousError: function () {
                    return checkoutConfig[self.payMethod].previous_error;
                },
                getPreviousSuccess: function () {
                    return checkoutConfig[self.payMethod].previous_success;
                },
                //Get icon for payment group by group id
                getGroupIcon: function (group) {
                    return checkoutConfig[self.payMethod].image[group];
                },
                getTokenizable: function (tokenizable) {
                    return tokenizable;
                },
                getSkipMethodSelection: function () {
                    return checkoutConfig[self.payMethod].skip_method_selection;
                },
                getPaymentMethodStyles: function () {
                    return checkoutConfig[self.payMethod].payment_method_styles;
                },
                getMethodGroups: function () {
                    if (window.checkoutConfig.isRecurringScheduled === true) {
                        return checkoutConfig[self.payMethod].scheduled_method_group;
                    }
                    return checkoutConfig[self.payMethod].method_groups;
                },
                getTerms: function () {
                    return checkoutConfig[self.payMethod].payment_terms;
                },
                addCard: function () {
                    return true;
                },
                getTokensData: function (tokens) {
                    return Object.values(tokens);
                },
                getTokens: function () {
                    var data = {
                        'method': this.getCode(),
                        'additional_data': {
                            'saved_methods': 'checkoutConfig[self.payMethod].tokens'
                        }
                    };

                    data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
                    return data;
                },
                selectPaymentMethod: function () {
                    selectPaymentMethodAction(self.getData());
                    checkoutData.setSelectedPaymentMethod(self.item.method);

                    return true;
                },
                addErrorMessage: function (msg) {
                    messageList.addErrorMessage(
                        {
                            message: msg
                        }
                    );
                    self.scrollTo();
                },
                addSuccessMessage: function (msg) {
                    messageList.addSuccessMessage(
                        {
                            message: msg
                        }
                    );
                    self.scrollTo();
                },
                getBypassPaymentRedirectUrl: function () {
                    return checkoutConfig[self.payMethod].payment_redirect_url;
                },
                enablePayAndAddCardButton: function () {
                    const foundElements =
                        checkoutConfig[self.payMethod].credit_card_providers_ids.filter(element => element['id'] === self.selectedPaymentMethodId());
                    if (foundElements.length && self.isLoggedIn()) {
                        document.getElementById('pay_and_add_card_button').style.display = 'block';

                        return true;
                    }
                    document.getElementById('pay_and_add_card_button').style.display = 'none';

                    return false;
                },
                getAddCardRedirectUrl: function () {
                    return checkoutConfig[self.payMethod].addcard_redirect_url;
                },
                getPayAndAddCardRedirectUrl: function () {
                    return checkoutConfig[self.payMethod].pay_and_addcard_redirect_url;
                },
                getTokenPaymentRedirectUrl: function () {
                    return checkoutConfig[self.payMethod].token_payment_redirect_url;
                },
                scrollTo: function () {
                    var errorElement_offset;
                    var scroll_top;
                    var scrollElement = $('html, body'),
                        windowHeight = $(window).height(),
                        errorElement_offset = $('.message').offset().top,
                        scroll_top = errorElement_offset - windowHeight / 3;

                    scrollElement.animate(
                        {
                            scrollTop: scroll_top
                        }
                    );
                },
                // Redirect to Paytrail
                placeOrder: function () {
                    if (self.isPlaceOrderActionAllowed() && additionalValidators.validate()) {
                        if (self.isRecurringPaymentAllowed()) {
                            return self.placeOrderBypass();
                        } else if (self.getSkipMethodSelection() == false || self.getSkipMethodSelection() === null) {
                            self.addErrorMessage($t('Recurring payment purchases require using a saved card.'));
                            //a self.scrollTo();
                            return false;
                        }
                    } else if (self.getSkipMethodSelection() == false || self.getSkipMethodSelection() === null) {
                        self.addErrorMessage($t('No payment method selected. Please select one.'));
                        //a self.scrollTo();
                        return false;
                    }
                    return self.placeOrderBypass();
                },
                placeAndAddCard: function () {
                    if (window.checkoutConfig.isRecurringScheduled) {
                        self.addErrorMessage($t('Recurring payment purchases require using a saved card.'));
                        self.scrollTo();
                        return false;
                    }

                    if (self.isPlaceOrderActionAllowed()
                        && additionalValidators.validate()
                        && self.enablePayAndAddCardButton()) {
                        return self.placeAndAddCardBypass();
                    }

                    return false;
                },
                addNewCard: function () {
                    if (self.isLoggedIn()) {
                        fullScreenLoader.startLoader();
                        /** start here */

                        $.ajax(
                            {
                                url: mageUrlBuilder.build(self.getAddCardRedirectUrl()),
                                type: 'post',
                                context: this,
                                data: {
                                    'is_ajax': true
                                }
                            }
                        ).done(
                            function (response) {
                                if ($.type(response) === 'object' && response.success && response.data) {
                                    if (response.redirect) {
                                        window.location.href = response.redirect;
                                    }
                                    $('#paytrail-form-wrapper').append(response.data);
                                    return false;
                                }
                                fullScreenLoader.stopLoader();
                                self.addErrorMessage(response.message);
                            }
                        ).fail(
                            function (response) {
                                fullScreenLoader.stopLoader();
                                self.addErrorMessage(response.message);
                            }
                        ).always(
                            function () {
                                //a self.scrollTo();
                            }
                        );

                        /** end here */
                        if (self.getSkipMethodSelection() == false) {
                            if (!self.validate()) {
                                self.addErrorMessage($t('No payment method selected. Please select one.'));
                                self.scrollTo();
                                return false;
                            }
                            if (!additionalValidators.validate()) {
                                self.addErrorMessage($t(
                                    'First, agree conditions, then try placing your order again.'
                                ));
                                self.scrollTo();
                                return false;
                            }
                            return self.placeOrderBypass();
                        } else {
                            return self.placeOrderBypass();
                        }
                    }
                },
                getPaymentUrl: function () {
                    if (self.selectedToken() != 0) {
                        return self.getTokenPaymentRedirectUrl();
                    }

                    return self.getBypassPaymentRedirectUrl();
                },
                placeOrderBypass: function () {
                    placeOrderAction(self.getData(), self.messageContainer).done(
                        function () {
                            fullScreenLoader.startLoader();
                            $.ajax({
                                url: mageUrlBuilder.build(self.getPaymentUrl()),
                                type: 'post',
                                context: this,
                                data: {
                                    'is_ajax': true,
                                    'preselected_payment_method_id': self.selectedPaymentMethodId(),
                                    'selected_token': self.selectedToken()
                                }
                            }).done(
                                function (response) {
                                    if ($.type(response) === 'object' && response.success && response.data) {
                                        if (response.reference) {
                                            window.location.href = self.getDefaultSuccessUrl();
                                        }
                                        if (response.redirect) {
                                            window.location.href = response.redirect;
                                        }

                                        $('#paytrail-form-wrapper').append(response.data);
                                        return false;
                                    }
                                    fullScreenLoader.stopLoader();
                                    self.addErrorMessage(response.message);
                                }
                            ).fail(
                                function (response) {
                                    fullScreenLoader.stopLoader();
                                    self.addErrorMessage(response.message);
                                }
                            ).always(
                                function () {
                                    //a self.scrollTo();
                                }
                            );
                        }
                    );
                },
                placeAndAddCardBypass: function () {
                    placeOrderAction(self.getData(), self.messageContainer).done(
                        function () {
                            fullScreenLoader.startLoader();
                            $.ajax({
                                url: mageUrlBuilder.build(self.getPayAndAddCardRedirectUrl()),
                                type: 'post',
                                context: this,
                                data: {
                                    'is_ajax': true,
                                    'preselected_payment_method_id': self.selectedPaymentMethodId(),
                                    'selected_token': self.selectedToken()
                                }
                            }).done(
                                function (response) {
                                    if ($.type(response) === 'object' && response.success && response.data) {
                                        if (response.reference) {
                                            window.location.href = self.getDefaultSuccessUrl();
                                        }
                                        if (response.redirect) {
                                            window.location.href = response.redirect;
                                        }

                                        $('#paytrail-form-wrapper').append(response.data);
                                        return false;
                                    }
                                    fullScreenLoader.stopLoader();
                                    self.addErrorMessage(response.message);
                                }
                            ).fail(
                                function (response) {
                                    fullScreenLoader.stopLoader();
                                    self.addErrorMessage(response.message);
                                }
                            ).always(
                                function () {
                                    //a self.scrollTo();
                                }
                            );
                        }
                    );
                }
            }
        );
    }
);
