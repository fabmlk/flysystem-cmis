<?php

require '../vendor/autoload.php';

use Dkd\PhpCmis\SessionFactory;
use Dkd\PhpCmis\SessionParameter;
use Dkd\PhpCmis\Enum\BindingType;
use GuzzleHttp\Client;

$httpInvoker = new Client(
    [
        'auth' => [
                'admin',
                'admin',
            ],
    ]
);

$sessionFactory = new SessionFactory();
$session = $sessionFactory->createSession(
    [
    SessionParameter::BINDING_TYPE => BindingType::BROWSER,
    SessionParameter::BROWSER_URL => 'http://192.168.33.10:8080/alfresco/api/-default-/public/cmis/versions/1.1/browser',
    SessionParameter::BROWSER_SUCCINCT => false,
    SessionParameter::HTTP_INVOKER_OBJECT => $httpInvoker,
    SessionParameter::REPOSITORY_ID => '-default-',
    ]
);
