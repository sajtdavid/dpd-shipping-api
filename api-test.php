<?php

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

$api = new DPD\API([
    'username' => 'demo',
    'password' => 'o2Ijwe2',
    'log_dir'  => dirname(__FILE__).'/'
]);

// Parcel generation test
$parcel_generate = DPD\Form\ParcelGeneration::newInstance()
    ->setName1('Alex')
    ->setStreet('Kesmark u 4')
    ->setCity('Budapest')
    ->setCountry('HU')
    ->setPcode('1158')
    ->setWeight('1')
    ->setNumOfParcel(1)
    ->setParcelType('BC')
//    ->setOrderNumber('1234')
    ->setCodAmount('')
    ->setCodPurpose('')
    ->setEmail('test@test.de')
    ->setPhone('0636516516')
    ->setSMSNumber('0636516516')
    ->setRemark('Hasdsadsaihlnwfwe');

$parcel_number = $api->generateParcel($parcel_generate);

echo 'Number is: ' . $parcel_number . '<br>';

// Transfer parcel data test
//$number_parcels_sent = $api->transferData();
//echo $number_parcels_sent . ' Parcels are transfered to internal DPD DB<br>';

// Parcel status test
echo 'Status is: ' . $api->getParcelStatus($parcel_number).'<br>';


// Parcel delete test
//$parcel_delete_result = $api->deleteParcel(DPD\Form\ParcelDelete::newInstance()->setParcels($numbers[0]));
//
//echo "delete result: " . print_r(var_dump($parcel_delete_result), true);

