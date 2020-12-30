<?php
/*PhpDoc:
name: ftps.php
title: fts.php - exposition de données au protocoles API Features
doc: |
  Exemples:
    - https://features.geoapi.fr/wfs/services.data.shom.fr/INSPIRE/wfs
    - http://localhost/geovect/features/features.php/wfs/services.data.shom.fr/INSPIRE/wfs
journal: |
  30/12/2020:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/wfsserver.inc.php';

use Symfony\Component\Yaml\Yaml;

ini_set('memory_limit', '1G');

static $raccourcis = [
  'shomwfs'=> '/wfs/services.data.shom.fr/INSPIRE/wfs',
  'igngpwfs'=> '/wfs,referer=gexplor.fr/wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs',
];

define('EXEMPLES_DAPPELS', [
  'features.php/wfs/services.data.shom.fr/INSPIRE/wfs',
  'features.php/wfs/services.data.shom.fr/INSPIRE/wfs/collections',
  'features.php/wfs/services.data.shom.fr/INSPIRE/wfs/collections/DELMAR_BDD_WFS:au_maritimeboundary_agreedmaritimeboundary',
  'features.php/shomwfs',
  'features.php/shomwfs/collections',
  'features.php/shomwfs/collections/DELMAR_BDD_WFS:au_maritimeboundary_agreedmaritimeboundary',
  'features.php/igngpwfs',
  'features.php/igngpwfs/collections',
  'features.php/igngpwfs/collections/BDCARTO_BDD_WLD_WGS84G:region',
  'features.php/igngpwfs/collections/BDCARTO_BDD_WLD_WGS84G:region/items',
  
]);

// génère un affichage en JSON ou Yaml en fonction du paramètre $f
function output(string $f, array $array, int $levels=3) {
  switch ($f) {
    case 'yaml': die(Yaml::dump($array, $levels, 2));
    case 'json': die(json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  }
}

switch ($f = $_GET['f'] ?? 'yaml') {
  case 'yaml': {
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>shomwfs</title></head><body><pre>\n";
    break;
  }
  case 'json':
  case 'geojson': {
    header('Content-type: application/json; charset="utf8"');
    //header('Content-type: text/plain; charset="utf8"');
    $f = 'json';
    break;
  }
  default: {
    $f = 'yaml';
  }
}

if (!isset($_SERVER['PATH_INFO'])) {
  echo "Raccourcis:\n";
  foreach ($raccourcis as $raccourci => $url) {
    echo "  - <a href='features.php/$url'>$raccourci</a>\n";
  }
  echo "Exemples:\n";
  foreach (EXEMPLES_DAPPELS as $ex) {
    echo "  - <a href='$ex'>$ex</a>\n";
  }
  die();
}

if (preg_match('!^((/[^/]+)+)/collections(/([^/]+)(/items)?)?$!', $_SERVER['PATH_INFO'], $matches)) { // test avec /collections
  //echo 'matches='; print_r($matches);
  $fserverId = $matches[1];
  $colls = true;
  $collId = $matches[4] ?? null;
  $items = $matches[3] ?? null;
}
else { // sinon, c'est l'URL ou un raccourci
  $fserverId = $_SERVER['PATH_INFO'];
  $colls = false;
}

if (preg_match('!^/([^/]+)$!', $fserverId, $matches)) { // si raccourci
  $raccourci = $matches[1];
  //echo "raccourci $raccourci<br>\n";
  if (!isset($raccourcis[$raccourci]))
    output($f, ['error'=> "$raccourci n'est pas un raccourci enregistré"]);
  $fserverId = $raccourcis[$raccourci];
  echo "fserverId=$fserverId<br>\n";
}
if (!preg_match('!^/(wfs|wfs(,[^/]*)|pgsql|mysql|file)(/.*)$!', $fserverId, $matches)) {
  output($f, ['error'=> "no match for '$_SERVER[PATH_INFO]'"]);
}
echo 'matches='; print_r($matches);
$type = $matches[1];
$wfsOptions = $matches[2];
$path = $matches[3];
$httpOptions = [];
if ($wfsOptions && preg_match('!^,referer=(.*)$!', $wfsOptions, $matches)) {
  echo 'matches='; print_r($matches);
  $httpOptions = ['referer'=> "http://$matches[1]/"];
  $type = 'wfs';
}
switch($type) {
  case 'wfs': {
    $fServer = new FeaturesProxyForWfs("https:/$path", $httpOptions);
    break;
  }
}
if (!$colls) {
  output($f, $fServer->home());
}
elseif (!$collId) {
  output($f, $fServer->collections(), 4);
}
else { // /collections/{collId}/items
  output($f,
    $fServer->items(
      collId: $collId,
      bbox: $_GET['bbox'] ?? [],
      count: $_GET['count'] ?? 100,
      startindex: $_GET['startindex'] ?? 0
    ), 6
  );
}

/*
https://features.geoapi.fr/{raccourci}
https://features.geoapi.fr/pgsql/benoit@db207552-001.dbaas.ovh.net/comhisto/public
https://features.geoapi.fr/mysql/{user}@{host}/{database}
https://features.geoapi.fr/file/{path}
https://features.geoapi.fr/wfs/referer=gexplor.fr/wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs
https://features.geoapi.fr/wfs/services.data.shom.fr/INSPIRE/wfs
*/