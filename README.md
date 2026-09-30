# rexpay


A PHP API wrapper for [Rexpay](https://www.myrexpay.com/).

[![Rexpay](img/rexpay.svg "Rexpay")](https://www.myrexpay.com/)

## What's New in this Update (v2.3.0)

This update modernizes the library for PHP 8.x and resolves several issues from the original release:

- **PHP 8.4+ Compatibility**: Fixed fatal `TypeError` when handling non-JSON responses or gateway errors.
- **Removed Debug Leak**: Cleaned out the stray `var_dump()` in `Transaction::initialize()` that leaked to stdout.
- **Fixed Guzzle Auth**: Fixed an issue where the Basic `Authorization` header was omitted when using Guzzle.
- **Auto-Auth Injection**: You can now pass credentials directly to `new Rexpay($username, $secretKey, $mode)` so you don't have to base64-encode tokens manually on every call.
- **Response Helpers**: Added `Rexpay::isSuccessful($res)` and `Rexpay::isPending($res)` for cleaner status checks.
- **Direct Payment Support**: Added `Transaction::makePayment` for `POST /payment/v1/makePayment`.
- **Standalone Test Suite**: Added `php tests/run_suite.php` covering all routes and transports (31/31 passing).

## Requirements
- Curl 7.34.0 or more recent (Unless using Guzzle)
- PHP 7.4.0 or more recent (Supports PHP 8.x)

## Install

### Via Composer

``` bash
    $ composer require pils36/rexpay
```

### Via download

Download a release version from the [releases page](https://github.com/Pils36/rexpay/releases).
Extract, then:
``` php
    require 'path/to/src/autoload.php';
```

## Usage

Do a redirect to the authorization URL received from calling the /transaction endpoint. This URL is valid for one time use, so ensure that you generate a new URL per transaction.

When the payment is successful, we will call your callback URL (as setup in your dashboard or while initializing the transaction) and return the reference sent in the first step as a query parameter.

If you use a test secret key, we will call your test callback url, otherwise, we'll call your live callback url.

### 0. Prerequisites
Confirm that your server can conclude a TLSv1.2 connection to Rexpay's servers. Most up-to-date software have this capability. Contact your service provider for guidance if you have any SSL errors.
*Don't disable SSL peer verification!*

### 1. Prepare your parameters
`email`, `userId`, `amount`, `description`, `reference` and `authToken` are the most common compulsory parameters.

### 2. Initialize a transaction
Initialize a transaction by calling our API.

```php

    require_once('./vendor/autoload.php');

    // Pass credentials once in the constructor (recommended):
    $rexpay = new \Pils36\Rexpay($username, $secretKey, 'test'); // 'test' or 'production'

    // Or use the classic empty constructor if passing $authtoken manually:
    // $rexpay = new \Pils36\Rexpay();
    
    try
    {

      $tranx = $rexpay->transaction->initialize([
        'reference'   => "sm23oyr1122",
        'amount'      => 200,
        'currency'    => "NGN",
        'userId'      => "awoyeyetimilehin@gmail.com",
        'callbackUrl' => "https://yourdomain.com/callback",
        'metadata'    => ['email' => "awoyeyetimilehin@gmail.com", 'customerName' => "Victor Musa"]
        // 'authToken' => $authtoken, // Optional if set in constructor
        // 'mode'      => 'test'       // Optional if set in constructor
      ]);

      if (isset($tranx->paymentUrl)) {
          header('Location: ' . $tranx->paymentUrl);
          exit;
      }

    } catch(\Pils36\Rexpay\Exception\ApiException $e){
      print_r($e->getResponseObject());
      die($e->getMessage());
    }

    // store transaction reference so we can query in case user never comes back
    // perhaps due to network issue
    saveLastTransactionId($tranx);

```

When the user enters their card details, Rexpay will validate and charge the card. It will do all the below:

Redirect back to a callback_url set when initializing the transaction or on your dashboard. Customers see a Transaction was successful message.


Before you give value to the customer, please make a server-side call to our verification endpoint to confirm the status and properties of the transaction.


### 3. Verify Transaction
After we redirect to your callback url, please verify the transaction before giving value.

```php
    // initiate the Library's Rexpay Object
    $rexpay = new \Pils36\Rexpay($username, $secretKey, 'test');
    try
    {
      // verify using the library
      $tranx = $rexpay->transaction->verify([
        'transactionReference' => $reference
      ]);
    } catch(\Pils36\Rexpay\Exception\ApiException $e){
      print_r($e->getResponseObject());
      die($e->getMessage());
    }

    if (\Pils36\Rexpay::isSuccessful($tranx)) {
      // transaction was successful (responseCode === "00")
      // Please check other things like whether you already gave value for this transaction
      // Save your transaction information here
    }
```


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
    $ composer test
```

Or run the test suite directly:

``` bash
    $ php tests/run_suite.php
```

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CONDUCT.md) for details. Check our [todo list](TODO.md) for features already intended.

## Security

If you discover any security related issues, please email adenugaadebambo41@gmail.com instead of using the issue tracker.

## Credits

- [Pils36][link-author]
- [All Contributors][link-contributors]


[link-author]: https://github.com/Pils36
[link-contributors]: ../../contributors
