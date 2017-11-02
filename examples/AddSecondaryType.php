<?php
/**
 * IMPORTANT: secondaryType must exist beforehand in the repo.
 */
require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);

$path = '/TMS/Clients/Parc/44-000001 Garage Champion/2017';

$filesystem->write(
    $path.'/'.'facture_01.txt',
    'toto.txt',
    [
        CMISAdapter::OPTION_PROPERTIES => [
            'cmis:secondaryObjectTypeIds' => ['P:tms:facture'],
            // Alfresco tip: do not use d:float because of bad rounding, use d:double instead
            'tms:montant' => 102.55,
            'tms:codetms' => '44-000001',
        ],
    ]
);
