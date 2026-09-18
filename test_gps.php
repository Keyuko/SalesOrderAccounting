<?php
require 'vendor/autoload.php';
$client = new \GuzzleHttp\Client();
$response = $client->request('POST', 'https://portal.gps.id/backend/seen/public/login', ['json' => ['username'=>'wongps1','password'=>'354712']]);
$token = json_decode($response->getBody(), true)['message']['data']['token'];
echo "Token: $token\n";
try {
    $res = $client->request('POST', 'https://portal.gps.id/backend/seen/public/share_location', ['headers'=>['Authorization'=>'Bearer '.$token, 'Accept'=>'application/json'], 'json'=>['imei'=>'test','expiration'=>60]]);
    echo $res->getBody();
} catch (Exception $e) {
    echo $e->getMessage();
}
