# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.11] - 2025-07-01

- Fix Adobe App Assurance Program issues.

## [2.2.10] - 2025-05-17

- Fix refund for Restored orders.

## [2.2.9] - 2025-05-16

- Added php 8.4 support.
- Added support for Nesbot/Carbon 3.0.

## [2.2.8] - 2025-04-28

- Upgrade Apple Pay payment method.
- Update Manual Invoice Activation functionality.

## [2.2.7] - 2025-03-24

- improve callback parameters logging.
- use Paytrail checkout-status in admin order grid.

## [2.2.6] - 2025-03-10

- fix selected payment method visibility on old orders.

## [2.2.5] - 2025-02-19

- add selected payment method visibility on order admin view and order customer view.

## [2.2.4] - 2024-12-04

- fix partial refund.
- add translations for 'Add card' & 'Login to save cards' buttons.
- update block to escaper in the payment template.

## [2.2.3] - 2024-10-22

- 'Restore order' action improvement.

## [2.2.2] - 2024-10-08

- Add data anonymization for order items.
- Fix 'Restore order' action on order.

## [2.2.1] - 2024-09-24

- Update composer dependencies.

## [2.2.0] - 2024-09-24

- Added Apple Pay payment method.
- Added new UI for payment page.
- Improve WCAG 2.1 accessibility.
- Added negative rows for discount splitter.

## [2.1.5] - 2024-08-02

- Added ACL configuration.
- Support the new VAT in Finland.
- Update CSP whitelist.

## [2.1.4] - 2024-06-11

- Fix issue with submit shipment for custom order status.
- Add toggle to enable or disable 'Add card' & 'Login to add card' button on checkout page.

## [2.1.3] - 2024-05-27

- Fix issue with loading orders from reference in callbacks.

## [2.1.2] - 2024-05-10

- Fix for DI configuration

## [2.1.1] - 2024-04-25

- New MFTF test cases
- Update CSP

## [2.1.0] - 2024-04-18

- Fix for recurring payments 
- Refactoring related to Adobe App Assurance Program
- Hide pay and add card buttons when payment method is not available
- fix for issue https://github.com/paytrail/paytrail-for-adobe-commerce/issues/81
- Added module MFTF tests

## [2.0.4] - 2024-03-14

- Fix empty array issue when credit cards payments are disabled on merchant panel
- Update CSP

## [2.0.3] - 2024-01-11

- Add support for paytrail/paytrail-for-adobe-commerce-graphql module
- Fix for version validation in composer and GitHub
- Fix for cc payment method, to not show separately

## [2.0.2] - 2023-12-07

- Add CSP whitelist for Paytrail payment providers form submission
- Fixing issue with setting error on response in callback controller

## [2.0.1] - 2023-11-27

- Fix issue with invoice credit memos
- Fix deprecated dynamic properties for PHP 8.2
- Exchange array_first method
- Improve discount splitter

## [2.0.0] - 2023-10-26

- Refactored codebase
- Remove Helpers classes and split them to smaller classes
- Remove ApiData class with all the request and split it to GatewayCommandPool interface 
- Refactor Model/Ui/ConfigProvider to provide only data to Ui and data getters moved to other classes. 
- Create Receipt classes under Model/Receipt which contains services and process classes (process payment, transaction, order). 
- Most configuration data is implemented in Gateway/Config/Config class 
- Add Pay and add card functionality 
- Add Manual Invoice Activation functionality


## [1.4.5] - 2023-10-10

- Fix: Rounding issue with shipping tax and int conversion


## [1.4.4] - 2023-09-26

- Fix: Fix PHP 7 compatibility issues
- Improvements for GraphQl module compatibility
- Add translations
- Enable/Disable switcher for recurring-payment


## [1.4.3] - 2023-09-05

- Fix: Fix PHP compatibility issues
- Fix: Restore quote after click 'back button' on payment site.

## [1.4.2] - 2023-08-08

- Fix: Fix payment method selection related issue
- Fix: Fix PHP compatibility issue
- Fix: Fixing issue with setting floats as Paytrail Item UnitPrice

## [1.4.1] - 2023-06-14

- Fix: Fix issues listed in Adobe's Code Sniffer results

## [1.4.0] - 2023-06-12

- New Feature: Paytrail Recurring payment offers merchants the ability to create and sell a products as a service in
  Magento. By assigning a recurring payment schedule to any existing Magento product you'll convert it to a recurring
  payment product which only logged in customers may purchase. Any order that contains one of these products will be
  recreated and billed automatically with the same product in it while products without recurring schedule are removed..

## [1.3.2] - 2023-02-02

- New Feature: Post company name to Paytrail Api while making a payment.
- Fix: Callback controller now returns correct http code (200) if callback processing was successful.
    - Fix reduces the amount of callbacks posted from Paytrail Api to Magento.
    - Fix reduces the amount of order transaction comments saved in admin view.

## [1.3.1] - 2022-11-18

- Fix undefined constant error during failed payment requests

## [1.3.0] - 2022-10-27

- Summary: Update contains a lot of smaller code quality fixes as highlighted by upgrade compatibility tools by Magento.
  1.3.0 addresses over 20 different minor issues in the module
- New Feature: Improve logging and exception handling in controllers
- New Feature: Update php-sdk dependency to 2.3.*
- Fix: Added Database Exceptions catching during order cancellation when customer interrupts a payment to an order that
  has sales rule coupon in it while Magento background processes are not running.
    - Issue hapens due to Magento using asynchronous logic to increment coupon usage, while coupons are decremented
      synchronously. The disparity can cause Magento to throw exceptions in rare cases if coupon was not marked as used
      before the payment cancellation happens
    - Changes do not fix the underlying problem in Magento, changes only catch and log the error. While preventing the
      uncaught exception from reaching end users
- Fix: Refactored frontend controllers to implement actionInterface instead of extending a deprecated controller
- Fix: Strip a significant number of unused dependencies across the module
- Fix: Refactored controllers use resultFactories instead of a specific result injected via dependency.
- Fix: Strip/refactor discouraged functions
- Fix: Replace direct resourceModel dependencies with repository dependencies during order restoration from admin.
- Fix: Some unnecessary extend calls have been removed as recommended by Magento "composition over inheritance" rule
- Fix: Remove invalid extends from plugin
- Fix: Trim excess use statements
- Fix: Code formatting fixes to tax plugin
- Fix: Refactor recurring setup script with invalid constructor arguments into a patch
- Fix: Improve phpDoc notation
- Fix: Replace incorrect usage of "$this" with "$block" in templates and improve phpDoc notation in templates.
- Fix: Incorrect variable usage in order restoration email template.

## [1.2.1] - 2022-09-23

- Refactored order loading in payment callback controllers to use factory - load implementation over direct model
  instantiation
- Added new restore order controller. It is used then the "Restore order" button is clicked in the admin view. This
  implementation replaces previous non-compliant implementation that which restored the order during page reload of the
  same page.

## [1.2.0] - 2022-05-20

- Added support for PHP 8
- Added dependency to Paytrail PHP SDK to version 2.0

## [1.1.2] - 2022-04-25

Fixes:

- When an already paid order receives a "Fail" callback from API, it is no longer cancelled.
- "Delayed" status from API no longer causes the order to be cancelled.
- Invoices are only created for "Ok" api responses. Mitigating an issue where invoices are missing transaction ids.

New features:

- You can now disable automatic order cancelling, if you are having issues with cancelled orders receiving payments. You
  can find the setting from Payment method's configuration.

## [1.1.0] - 2022-02-21

- Refactor logging to a separate class.
- Improve error logging beyond Api errors
- **Add support for giftcards and cart discounts.**
    - Gift card and Cart total discounts are now split between all products in the payment when posted to paytrail.
    - Custom total discounts can now be implemented by implementing DiscountGetterInterface and injecting the
      implementation to DiscountSplitter class.
    - Rounding correction of the split discounts is now placed on a separate row. The tax percent of it is configurable
      from admin. Please check that the tax configuration is valid before deploying the update to production.
- Remove unnecessary constructor arguments in ApiData Helper
- Remove discount implementation from Data Helper

## [1.0.4] - 2022-01-03

- Added default value for "skip method selection" config. Fixing missing javascript validations in frontend.

## [1.0.3] - 2021-12-08

### Added

- Added conflict information with Markup/Paytrail

## [1.0.2] - 2021-11-17

### Added

- Added upgrade instruction to README

## [1.0.1] - 2021-10-19

### Added

- Error handling for empty API credentials

## [1.0] - 2021-10-11

### Added

- All initial module functionalities
