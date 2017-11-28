<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$path = '/TMS/Clients/Groupes/SOLWARE/Relevés mensuels/2016/relevé_2.txt';

$cmisAdapter = new CMISAdapter($session);
$filesysytem = new Filesystem($cmisAdapter);

$ret = $filesysytem->write($path, 'Hello Relevé!');

var_dump($ret);
