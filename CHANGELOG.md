# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## SemVer public API

The [public API](https://semver.org/spec/v2.0.0.html#spec-item-1) of this library consists of all public or
protected methods, properties and constants belonging to the `src` folder and of all files in the `scripts` folder.

---

## [1.5.0](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.5.0) - 2025-01-10

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v1.4.0...v1.5.0)

### Changed

- Do not count a "processed" usage metrics when the IP is not bounced at all due to business rules (i.e. when `shouldBounceCurrentIp` returns false).

---

## [1.4.0](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.4.0) - 2025-01-09

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v1.3.1...v1.4.0)

### Added

- Add `push-usage-metrics.php` script

---

## [1.3.1](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.3.1) - 2024-12-12

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v1.3.0...v1.3.1)

### Fixed

- Fix Captcha deprecated warning in PHP 8.4

---

## [1.3.0](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.3.0) - 2024-11-05

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v1.2.0...v1.3.0)

### Added

- Add multipart request support for AppSec
- Add `appsec_max_body_size_kb` and `appsec_body_size_exceeded_action` settings

---

## [1.2.0](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.2.0) - 2024-10-04

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v1.1.0...v1.2.0)

### Added

- Add AppSec support
- Add `use_appsec`, `appsec_url`, `appsec_timeout_ms`, `appsec_connect_timeout_ms` and `appsec_fallback_remediation` settings

---

## [1.1.0](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.1.0) - 2023-12-14

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v1.0.0...v1.1.0)

### Added

- Add `api_connect_timeout` setting

---

## [1.0.0](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v1.0.0) - 2023-04-27

[_Compare with previous release_](https://github.com/crowdsecurity/cs-standalone-php-bouncer/compare/v0.0.1...v1.0.0)

### Changed

- Change version to `1.0.0`: first stable release

---

## [0.0.1](https://github.com/crowdsecurity/cs-standalone-php-bouncer/releases/tag/v0.0.1) - 2023-04-27

- Initial release
