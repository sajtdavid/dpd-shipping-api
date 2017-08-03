dpd-shipping-api
================

DPD shipping API for Hungarian DPD Weblabel webservice interface via POST requests

## Installing

The easiest way to install the API is using Composer:

```
composer require schiggi/dpd-shipping-api
```

Then use your framework's autoload, or simply add:

```php
<?php
  require 'vendor/autoload.php';
```

## Getting started

You can start making requests to the Billingo API just by creating a new `API` instance

```php
<?php

  $api = new DPD\API([
      'username' => 'demo',
      'password' => 'o2Ijwe2',
  ]);
```

The `API` class takes care of the communication between your app and the DPD servers via POST requests.

#### Using Monolog to log requests and responses to a log file

Monolog is used optionally to log request/responses with DPD server.

You need to specific a log dir and optionally a message format when creating a new `API` instance. Log files while have the name "api-dpd-consumer-{date}.log" 

```php
<?php

  $api = new DPD\API([
      'username' => 'demo',
      'password' => 'o2Ijwe2',
      'log_dir'  => dirname(__FILE__).'/',
      'log_msg_format' => [
        '{method} {uri} HTTP/{version} {req_body}',
        'RESPONSE: {code} - {res_body}',
      ]
  ]);
 
```

## General usage

### Send parcel data to DPD

```php
<?php
// Parcel generation. Data will be validated before sending.
$parcel_generate = DPD\Form\ParcelGeneration::newInstance()
    ->setName1('Alex')
    ->setStreet('Kesmark u 4')
    ->setCity('Budapest')
    ->setCountry('HU')
    ->setPcode('1158')
    ->setWeight('1')
    ->setNumOfParcel(1)
    ->setParcelType('BC')
    ->setOrderNumber('1234')
    ->setCodAmount('')
    ->setCodPurpose('')
    ->setEmail('test@test.de')
    ->setPhone('0636516516')
    ->setSMSNumber('0636516516')
    ->setRemark('Customer comments'); // Max length 100 chars. Will be normalized through the API

// Will return the parcel number from DPD or error message
$parcel_number = $api->generateParcel($parcel_generate);
```

### Print labels for saved parcels

TBD

### Delete parcel

```php
<?php
// Array of parcel numbers. Can also be a string with one number
$parcel_array = (DPD\Form\ParcelDelete::newInstance()->setParcels($numbers));

// Returns array with status message
$parcel_delete_status = $api->deleteParcel($parcel_array);

```

### Transfer saved parcel data to internal database after labels have been printed

```php
<?php
// returns the numbers of parcels, which have been succesfully transfered
$number_parcels_sent = $api->transferData();

```

### Retrieve parcel status

```php
<?php
// returns status message as string for parcel_number. One number at a time.
$status_msg = $api->getParcelStatus($parcel_number);

```

## Changelog

@ 2016.04.21.
+ add: Get Parcel Label
+ mod: GET to POST
+ mod: Tracking to hungarian, new url

@ 2017.02.15.
+ add: Parcel delete support

@ 2017.05.22.
+ add: Support for idm sms number

@ 2017.08.03.
+ mod: changed http client from buzz to Guzzle v6
+ mod: rewrote request and error handling 