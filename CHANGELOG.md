# Changelog

## 1.97.0

* Added ServiceAuth (e-signature spec §10.1, §10.2): locally verified OAuth service-token auth:
  * OAuthServiceTokenAuthentication middleware, alias service.oauth:
    `service.oauth:<required scope>,<accepted service>...`. RS256 only, kid, iss, exp/nbf (60s),
    client_kind=service, namespace + endpoint scope, no `*`, caller policy, known tenant,
    client_id = tenant_id. Selects the tenant and sets service_* request attributes
  * JwksKeySet: fixed URL, cached, single-flight refresh at most once a minute, unknown kids
    negatively cached for 5 minutes, 2s timeout, bounded
  * ServiceTokenProvider (client_credentials per scope set and tenant, cached until exp - 60s,
    one refresh on 401) and RedactsAuthorization for logs and Sentry
  * EligibilitySnapshotVerifier (ES256 eligibility+jwt), ProjectionApplier with the revision
    rules and the EligibilityProjectionStore interface
  * HintReceiver, SnapshotFetcher and EligibilityGuard (the §10.2 outage matrix)
* Config under shekel.service_auth (resources/config/shekel-service-auth.php)
* Requires firebase/php-jwt ^6.10|^7.0
* Added a PHPUnit + Testbench test suite (`composer test`)

## 1.96.158

* `InternationalLoanEnum::isInternationalLoan` now accepts a nullable string (defaults to `null`)

## 1.96.157

* Added `InternationalLoanEnum` with constants for international loan classification:
  * `IMPORT`
  * `PROFIT_SHARE_IMPORT`
  * `SHIPPING`

## 1.96.156

* Added `InternationalLoanEnum` with constants for international loan classification

## 1.96.155

* Added methods to VendorService for vendor-api's service routes (s2s/ dropped):
  * loanStatuses: /vendor/loan-statuses
  * assignedLoanIds: /vendor/assigned-loan-ids

## 1.96.154

* Added ShekelServiceSecretMiddleware: lets a request through only with the calling service's
  secret (s2s and s2sName), otherwise 401 "Service authentication failed.". Register it as
  s2s.required in place of a service's own copy
* LoanService::lookupLoans calls loan-api's /vendor/loans/lookup (s2s/ dropped)

## 1.96.153

* AuthService calls authentication-api's vendor lookups on their new paths (s2s/ dropped):
  * getVendorUser: /vendor/users/{id}
  * lookupFieldAgents: /vendor/field-agents/lookup

## 1.96.152

* ShekelServiceRoleProtectionMiddleware (s2s_or_role.required):
  * A route with roles and no signed-in user now answers 401 (it failed with a TypeError, 500)
  * A route without roles is unchanged: it still lets the request through
* The secret is compared with hash_equals, and an unset secret never matches
* A secret that does not match is logged (s2sName and path, never the secret)

## 1.96.151

* Added new methods to AuthService class:
  * getVendorUser
  * lookupFieldAgents
* Added new methods to LoanService class:
  * lookupLoans
  * listShippingLines
  * inferShippingLine
  * startShipmentTracking

## 1.96.150

* Widened activity_logs.url, ip and user_agent from varchar(255) to text:
  * Long request URLs (e.g. pagination cursors) no longer fail the audit insert
* ActivityLogMiddleware (v1 and v3) now catches activity log write failures:
  * Logs a fixed message without the SQL, so request data stays out of logs

## 1.96.149

* Added new method to LoanService class:
  * createShippingOrder

## 1.96.147

* Added new method to MessagingService class:
  * sendVendorNotification

## 1.96.146

* Added new method to BaseRepository class:
  * transaction

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
## 1.96.144

* Added new method to LoanService class:
  * markDisbursed

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
