<?php

include 'Client.php';

use JsonRPC\Client;

$client = new Client('https://eripapi.dev');
$client->debug = true;
$client->ssl_verify_peer = false;

$client->authentication('tester', '3e065d744dad35bbe181a636');

$time = time();
$hmac = hash_hmac( 'sha512', 0 . $time, '5e5b2cce799b2b2e52ea0c7ad1835d165ccfd5811b9765ed5dce97ade2ddb522df6b64b824cd5092fb2b040f796b64f0a813a2e0f3b4c5a851984e15e6b8dd9a' );
$result = $client->deleteBill( ['billNum' => 0, 'time' => $time, 'hmac' => $hmac] );

var_dump($result);