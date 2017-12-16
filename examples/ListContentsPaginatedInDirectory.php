<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);
$plugin = new \Tms\Cmis\Flysystem\ListContentsPaginatedPlugin();
$filesystem->addPlugin($plugin);

$path = '/Sites/swsdp';

$contents = $filesystem->listContentsPaginated($path, 0, 4, true);
foreach ($contents as $content) {
    echo $content['path'] . "\n";
}
