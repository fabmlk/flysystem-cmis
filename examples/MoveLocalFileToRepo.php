<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\MountManager;

$sourcePath = 'assets/relevé.txt';
$destPath = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/relevé_1.txt';

$cmisAdapter = new CMISAdapter($session);
$localAdapter = new LocalAdapter(__DIR__);

$local = new Filesystem($localAdapter);
$cmis = new Filesystem($cmisAdapter);

$manager = new MountManager(
    [
    'local' => $local,
    'cmis' => $cmis,
    ]
);

try {
    $ret = $manager->move('local://'.$sourcePath, 'cmis://'.$destPath);
} catch (Exception $e) {
    die($e->getMessage());
}
var_dump($ret);
