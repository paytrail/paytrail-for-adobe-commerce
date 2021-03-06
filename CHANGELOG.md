# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
