<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Plugin\ListFiles;

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);
$filesystem->addPlugin(new ListFiles());

$path = '/TMS/Clients/Parc/44-000001 Garage Champion/2017';

$files = $filesystem->listFiles($path);
var_dump($files);
