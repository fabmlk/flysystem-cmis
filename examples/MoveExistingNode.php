<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$sourceFile1 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/relevé_2.txt';
$destFile = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2017/relevé_3.txt';

$sourceFile2 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2017/relevé_3.txt';
$destFolder1 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2018/';

$sourceFolder1 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/Impayés';
$destFolder2 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2018/New Impayés';

$sourceFolder2 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2018/New Impayés';
$destFolder3 = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2017/';

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);

try {
    $ret = $filesystem->rename($sourceFile1, $destFile);
    var_dump($ret);
    // we have to use the adapter directly as we support folder rename/move but not Filesystem: it first asserts that destination does not exist
    // (can be disabled in config param on construct through)
    $ret = $cmisAdapter->rename($sourceFile2, $destFolder1);
    var_dump($ret);
    $ret = $cmisAdapter->rename($sourceFolder1, $destFolder2);
    var_dump($ret);
    $ret = $cmisAdapter->rename($sourceFolder2, $destFolder3);
    var_dump($ret);
} catch (Exception $e) {
    die($e->getMessage());
}
