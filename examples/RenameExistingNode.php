<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$sourceFile = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/relevé_1.txt';
$destFile = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/relevé_2.txt';

$sourceFolder = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/';
$destFolder = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2017/';

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);

try {
    $ret = $filesystem->rename($sourceFile, $destFile);
    var_dump($ret);
    $ret = $filesystem->rename($sourceFolder, $destFolder);
    var_dump($ret);
} catch (Exception $e) {
    die($e->getMessage());
}
