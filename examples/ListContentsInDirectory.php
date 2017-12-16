<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);

$path = '/';

$contents = $filesystem->listContents($path, true);
foreach ($contents as $content) {
    echo $content['path'] . "\n";
}
