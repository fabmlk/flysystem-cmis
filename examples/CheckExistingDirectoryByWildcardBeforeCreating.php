<?php

require 'init.php';

use Tms\Cmis\Flysystem\CMISAdapter;
use League\Flysystem\Filesystem;

$codetms = '44-000002';
$raisonSociale = 'Garage Champion';

$result = getMatchingDirs($session, $codetms);

if (1 === count($result)) {
    $clientDir = $result[0]->getPropertyValueByQueryName('cmis:name');
} elseif (count(0 === $result)) {
    $clientDir = $codetms.' '.$raisonSociale;
} else {
    throw new Exception(sprintf('Ambiguité détectée: le code TMS %s est associé à plusieurs répertoires', $codetms));
}

$cmisAdapter = new CMISAdapter($session);
$filesystem = new Filesystem($cmisAdapter);

try {
    $ret = $filesystem->write($destination.'/'.$clientDir.'/hello.txt', 'Hello World!');
} catch (Exception $e) {
    echo $e->getMessage();
}
var_dump($ret);

/**
 * @param Dkd\PhpCmis\Session $session
 * @param string              $codetms
 *
 * @return mixed
 */
function getMatchingDirs($session, $codetms)
{
    $destination = '/TMS/Clients/Parc';
    $searchPath = 'cm:'.str_replace('/', '/cm:', trim($destination, '/'));
    $query = "SELECT * FROM cmis:folder WHERE cmis:name LIKE '$codetms%' AND CONTAINS('PATH:\"app:company_home/$searchPath/*\"')";

    return $session->query($query);
}
