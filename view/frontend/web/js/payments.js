define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/modal',
    'mage/url',
    'Magento_Customer/js/customer-data',
], function ($, $t, confirmation, modal, mageUrlBuilder, customerData) {
    'use strict';

    function addErrorMessage(msg) {
        customerData.set('messages', {
            messages: [{
                text: $t(msg), type: 'error'
            }]
        });
    }

    function addSuccessMessage(msg) {
        customerData.set('messages', {
            messages: [{
                text: msg, type: 'success'
            }]
        });
    }

    return (config) => {
        $(document).ready(() => {
            const url = new URL(window.location.href);
            if (url.searchParams.get('open-change-card-modal') !== null && url.searchParams.get('subscription') !== null && !config.previousError) {
                $(`.show-payment-methods[data-id='${url.searchParams.get('subscription')}']`).first().trigger('click');
            }
        })

        $('#add-new-payment-card').click(() => {
            if (!config.addCardRedirectUrl) {
                return;
            }

            let subscriptionId = $('#payment-change-card-subscription-input').val();
            $.ajax({
                url: mageUrlBuilder.build(config.addCardRedirectUrl), type: 'post', context: this, data: {
                    'custom_redirect_url': mageUrlBuilder.build(`paytrail/order/payments?open-change-card-modal=1&subscription=${subscriptionId}`),
                    'is_ajax': true
                }
            }).done(function (response) {
                if ($.type(response) === 'object' && response.success && response.data && response.redirect) {
                    window.location.href = response.redirect;
                } else {
                    addErrorMessage(response.message);
                }

            }).fail(function (response) {
                addErrorMessage(response.message);
            })
        });

        $('.show-payment-methods').click((event) => {
            event.preventDefault();
            let modalContainer = $('#payment-methods-modal');
            let subscriptionInput = $('#payment-change-card-subscription-input')
            let options = {
                type: 'popup', responsive: true, innerScroll: true, title: 'Change card', buttons: [{
                    text: $.mage.__('Close'), class: '', click: function () {
                        subscriptionInput.val(null);
                        this.closeModal();
                    }
                }]
            };

            subscriptionInput.val($(event.currentTarget).attr('data-id'));

            let popup = modal(options, modalContainer);
            modalContainer.modal('openModal');
        })

        $('.stop-payment-confirmation').click(function (event) {
            event.preventDefault();

            var url = event.currentTarget.href;
            confirmation({
                title: 'Stop Recurring Payment',
                content: 'Do you wish to stop this payment?',
                actions: {
                    confirm: function () {
                        window.location.href = url;
                    },
                    cancel: function () {},
                    always: function () {}
                }
            });
            return false;
        });
    }
});
