# Changelog

All Notable changes to `rexpay` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## 2.3.0 - 2026-09-30

### Fixed
- Fixed fatal TypeError in `Response.php` on non-JSON gateway error responses in PHP 8.x
- Removed stray `var_dump(1234)` in `Transaction::initialize()`
- Added missing Basic `Authorization` header when using Guzzle transport
- Ensured production URL rewriting applies consistently across all transports

### Added
- Added optional constructor authentication (`new Rexpay($username, $secretKey, $mode)`) with automatic header & environment injection
- Added response status helpers (`Rexpay::isSuccessful($response)` and `Rexpay::isPending($response)`)
- Added `Transaction::makePayment` route for direct payment processing
- Standalone test suite (`tests/run_suite.php`) with 31 tests
- PHP 8.x compatibility

## 2.2.0 - 2020-05-04

### Added
- Ability to load custom routes

## 2.1.22 - 2018-05-15

### Added
- Invoice route

## 2.1.3 - 2017-01-30

### Changes
- Spread logic into several classes for improved Unit Testing

### Added
- Event
- Fees
- Subaccount Route

## 2.0.3 - 2016-12-11

### Changes
- To do a direct register_autoload, include autoload.php
