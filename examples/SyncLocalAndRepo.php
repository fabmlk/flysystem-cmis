<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Replicate\ReplicateAdapter;

$sourcePath = 'assets/';
$destPath = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/';

$cmisAdapter = new CMISAdapter($session, $destPath);
$localAdapter = new LocalAdapter(__DIR__.'/'.$sourcePath);

$replicateAdapter = new ReplicateAdapter($localAdapter, $cmisAdapter);

$filesystem = new Filesystem($replicateAdapter);

try {
    $ret = $filesystem->write('relevé2.txt', 'Hello Relevé!');
} catch (Exception $e) {
    die($e->getMessage());
}
var_dump($ret);
