# Changelog

## 1.96.145

* Added Laravel 10–13 support (existing Laravel 9 installs resolve exactly as before):
  * `illuminate/auth`, `illuminate/console`, `illuminate/database`, `illuminate/http` now `^9.0|^10.0|^11.0|^12.0|^13.0`
  * `barryvdh/laravel-dompdf` now `^2.0|^3.1`
* Made implicitly nullable parameters explicit (PHP 8.4 deprecations), no behaviour change:
  * `Middleware\Tenant\ClientAuthPassportConnection::handle()`
  * `Middleware\ShekelAuthMiddleware::handle()`
  * `Utils\CacheRegistry::versionKey()`, `tag()`, `register()`, `forget()`, `remember()`
* Added vendor-api client:
  * New `Services\v3\VendorService`, registered in `ShekelServiceProvider`
  * New `vendor` and `vendor-secret` config keys (`VENDOR_SERVICE`, `VENDOR_SERVICE_SECRET`)
  * New `vendors` case in `v3\ShekelFactory::getService()`
  * `shekel:generate-secret` now also generates `VENDOR_SERVICE_SECRET`

## 1.96.143

* Added new method to MessagingService class:
  * sendPurchaseOrderLink

## 1.96.65

* Added new method ::filesExist to UploadService:

## 1.96.2

* Fixed wrong endpoint used:
  * Updated endpoint in sendSellSuggestion method in MessageService

## 1.96.1

* Updated CarService class getCarsList method:
  * Request Headers can now be sent with the request

## 1.96.0

* Added new Methods to MessagingService class:
  * sendLoanRequestConfirmation
  * sendKycSubmissionReminder
  * sendFoundersWelcome
  * sendSellSuggestionNotification
  * sendRestructureSuggestionNotification
