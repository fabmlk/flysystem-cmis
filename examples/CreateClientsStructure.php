<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$clients = [
    [
        'CodeTMS' => '44-000001',
        'RaisonSociale' => 'TMS SOFTWARE',
    ],
    [
        'CodeTMS' => '55-000593',
        'RaisonSociale' => 'Garage Champion',
    ],
    [
        'CodeTMS' => '88-000069',
        'RaisonSociale' => 'PEUGEOT S.A.R.L.',
    ],
];

$path = '/TMS/Clients/Parc/{CodeTMS} {RaisonSociale}/';

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);

foreach ($clients as $client) {
    $effectiveDir = str_replace(['{CodeTMS}', '{RaisonSociale}'], [$client['CodeTMS'], $client['RaisonSociale']], $path);
    try {
        $ret = $filesystem->createDir($effectiveDir);
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    var_dump($ret);
}
