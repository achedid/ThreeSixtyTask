<?php
require 'vendor/autoload.php';

require 'modules/main.php';
require 'modules/user.php';
require 'modules/server.php';

$f3 = \Base::instance();


$f3->set('db', new DB\SQL(
    'mysql:host=localhost;port=3306;dbname=360vuz',
    'root',
    'root'
));

$f3->route('GET /', 'MainModule->home');

$f3->route('GET /server/validateUnSubscription', 'ServerModule->unSubscribe');
$f3->route('GET /server/validateSubscription', 'ServerModule->validateSubscription');
$f3->route('GET /user/generateJWT/@msisdn', 'UserModule->generateJWTTestingData');
$f3->route('POST /user/subscribe/@subscriptionToken', 'UserModule->subscribe');
$f3->route('POST /user/unsubscribe/@unsubscriptionToken', 'UserModule->unsubscribe');

$f3->run();