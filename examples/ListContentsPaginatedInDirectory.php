<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);
$plugin = new \Tms\Cmis\Flysystem\ListContentsPaginatedPlugin();
$filesystem->addPlugin($plugin);

$path = '/';
$config = (object)['offset' => 2, 'limit' => 5, 'orderByName' => true];

$contents = $filesystem->listContentsPaginated($path, true, $config);

printContents($contents, $config->total);

$config->offset = 7;
$config->limit = 5;
$contents = $filesystem->listContentsPaginated($path, true, $config);
printContents($contents, $config->total);

function printContents($contents, $total)
{
    echo 'total: ' .  $total . "\n";
    foreach ($contents as $content) {
        echo $content['path'] . "\n";
    }
}