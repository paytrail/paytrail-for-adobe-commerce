# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
## [1.3.2] - 2023-02-02
- New Feature: Post company name to Paytrail Api while making a payment.
- Fix: Callback controller now returns correct http code (200) if callback processing was successful.
  - Fix reduces the amount of callbacks posted from Paytrail Api to Magento.
  - Fix reduces the amount of order transaction comments saved in admin view.

## [1.3.1] - 2022-11-18
- Fix undefined constant error during failed payment requests

## [1.3.0] - 2022-10-27
- Summary: Update contains a lot of smaller code quality fixes as highlighted by upgrade compatibility tools by Magento. 1.3.0 addresses over 20 different minor issues in the module
- New Feature: Improve logging and exception handling in controllers
- New Feature: Update php-sdk dependency to 2.3.*
- Fix: Added Database Exceptions catching during order cancellation when customer interrupts a payment to an order that has sales rule coupon in it while Magento background processes are not running. 
  - Issue hapens due to Magento using asynchronous logic to increment coupon usage, while coupons are decremented synchronously. The disparity can cause Magento to throw exceptions in rare cases if coupon was not marked as used before the payment cancellation happens
  - Changes do not fix the underlying problem in Magento, changes only catch and log the error. While preventing the uncaught exception from reaching end users 
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
- Refactored order loading in payment callback controllers to use factory - load implementation over direct model instantiation
- Added new restore order controller. It is used then the "Restore order" button is clicked in the admin view. This implementation replaces previous non-compliant implementation that which restored the order during page reload of the same page.

## [1.2.0] - 2022-05-20
- Added support for PHP 8
- Added dependency to Paytrail PHP SDK to version 2.0

## [1.1.2] - 2022-04-25
Fixes:
- When an already paid order receives a "Fail" callback from API, it is no longer cancelled.
- "Delayed" status from API no longer causes the order to be cancelled.
- Invoices are only created for "Ok" api responses. Mitigating an issue where invoices are missing transaction ids.

New features:
- You can now disable automatic order cancelling, if you are having issues with cancelled orders receiving payments. You can find the setting from Payment method's configuration.

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
