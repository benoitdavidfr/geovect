<?php
/*PhpDoc:
name: ftps.php
title: fts.php - exposition de données au protocoles API Features
doc: |
  Proxy exposant des données initialement stocké soit dans un serveur WFS, soit dans une BD, soit dans un fichier GeoJSON.
  
  Définir un fichier stockant des options et des paramètres ?
    - utilisé comme cache pour renseigner certains paramètres pour éviter de les interroger systématiquement ?
    - renseigner des paramètres complémentaire, ex quel id dans une table ?
    - fournir de la doc complémentaire ?
    - reprendre la logique de raccourci ?

  Exemples:
    - https://features.geoapi.fr/wfs/services.data.shom.fr/INSPIRE/wfs
    - http://localhost/geovect/features/fts.php/wfs/services.data.shom.fr/INSPIRE/wfs

  https://features.geoapi.fr/{raccourci}
  https://features.geoapi.fr/pgsql/benoit@db207552-001.dbaas.ovh.net/comhisto/public
  https://features.geoapi.fr/mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_ne_110m/countries
  https://features.geoapi.fr/file/{path}
  https://features.geoapi.fr/wfs/referer=gexplor.fr/wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs
  https://features.geoapi.fr/wfs/services.data.shom.fr/INSPIRE/wfs

  
journal: |
  17/1/2021:
    - A faire
      - voir la gestion des erreurs
      - vérifier que les paramètres non prévus sont testés et qu'une erreur est renvoyée
      - voir comment ajouter de la doc complémentaire !
  30/12/2020:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/onwfs.inc.php';
require_once __DIR__.'/onfile.inc.php';
require_once __DIR__.'/onsql.inc.php';

use Symfony\Component\Yaml\Yaml;

//echo "<pre>"; print_r($_SERVER); die();

ini_set('memory_limit', '1G');

define('RACCOURCIS', [
  'shomwfs'=> '/wfs/services.data.shom.fr/INSPIRE/wfs',
  'igngpwfs'=> '/wfs/wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs?referer=gexplor.fr',
  'test@mysql'=> '/mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_geovect',
  'ne_110m'=> '/mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_ne_110m',
  'ne_10m'=> '/mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_ne_10m',
  'route500@mysql'=> '/mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_route500',
  'localgis'=> '/pgsql/docker@172.17.0.4/gis/public',
  'comhisto'=> '/pgsql/benoit@db207552-001.dbaas.ovh.net:35250/comhisto/public',
]
);

define('EXEMPLES_DAPPELS', [
  'wfs/services.data.shom.fr/INSPIRE/wfs' => [
    'DELMAR_BDD_WFS:au_maritimeboundary_agreedmaritimeboundary',
  ],
  'shomwfs' => [
    'DELMAR_BDD_WFS:au_maritimeboundary_agreedmaritimeboundary',
  ],
  'igngpwfs' => [
    'BDCARTO_BDD_WLD_WGS84G:region',
  ],
  'file/var/www/html/geovect/fcoll/ne_10m' => [
    'admin_0_countries'=> 'count=5&startindex=100',
  ],
  'mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_ne_110m'=> [],
  'ne_110m' => [
    'admin_0_countries' => 'count=10&startindex=5',
  ],
  'ne_10m' => [
    'admin_0_countries' => 'count=10&startindex=5',
  ],
  'route500@mysql' => [
    'troncon_voie_ferree' => 'count=10&startindex=5',
  ],
  'mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_geovect'=>[],
  'test@mysql' => [
    'unchampstretunegeom' => 'count=10',
  ],
  'localgis'=> [
    'departement_carto' => 'count=5&startindex=10&f=json',
  ],
  'comhisto'=> [
    'comhistog3',
  ],
]
);

// génère un affichage en JSON ou Yaml en fonction du paramètre $f
function output(string $f, array $array, int $levels=3) {
  switch ($f) {
    case 'yaml': die(Yaml::dump($array, $levels, 2));
    case 'html': die(Yaml::dump($array, $levels, 2));
    case 'json': die(json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  }
}

if (isset($_GET['f']))
  $f = $_GET['f'];
elseif (in_array('text/html', explode(',', getallheaders()['Accept'] ?? '')))
  $f = 'yaml';
else
  $f = 'json';
switch ($f) {
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

//print_r($_SERVER);
FeatureServer::log($_SERVER['REQUEST_URI']);
  
if (!isset($_SERVER['PATH_INFO']) || ($_SERVER['PATH_INFO'] == '/')) {
  echo "Raccourcis:\n";
  foreach (RACCOURCIS as $raccourci => $url) {
    echo "  - <a href='$_SERVER[SCRIPT_NAME]/$raccourci'>$raccourci</a> => $url\n";
  }
  echo "Exemples:\n";
  foreach (EXEMPLES_DAPPELS as $ex => $colls) {
    echo "  - <a href='fts.php/$ex'>$ex</a>\n";
    echo "    - <a href='fts.php/$ex/collections'>collections</a>,";
    echo " <a href='fts.php/$ex/check'>check</a>\n";
    foreach ($colls as $collid => $params) {
      if (is_int($collid)) {
        $collid = $params;
        $params = '';
      }
      echo "    - <a href='fts.php/$ex/collections/$collid/describedBy'>collections/$collid/describedBy</a>\n";
      $url = "collections/$collid/items".($params ? "?$params" : '');
      echo "      - <a href='fts.php/$ex/$url'>$url</a>\n";
    }
  }
  die();
}

if (preg_match('!^((/[^/]+)+)/(conformance|api|check)!', $_SERVER['PATH_INFO'], $matches)) {
  $fserverId = $matches[1];
  $action = $matches[3];
  $action2 = null;
}

// détermination de la partie $fserverId
// détection de /collections
elseif (preg_match('!^((/[^/]+)+)/collections!', $_SERVER['PATH_INFO'], $matches)) {
  if (!preg_match('!^((/[^/]+)+)/collections(/([^/]+)(/items(/.*)?|/describedBy|/createPrimaryKey)?)?$!',
      $_SERVER['PATH_INFO'], $matches))
    output($f, ['error'=> "no match1 for '$_SERVER[PATH_INFO]'"]);
  //echo 'matches1='; print_r($matches);
  $fserverId = $matches[1];
  $action = 'collections'; // liste des collections demandée
  $collId = $matches[4] ?? null; // collection définie
  $action2 = $matches[5] ?? null; // /items | /describedBy | /createPrimaryKey
  $itemId = isset($matches[6]) ? substr($matches[6], 1) : null;
}
else { // sinon, c'est l'URL ou un raccourci
  $fserverId = $_SERVER['PATH_INFO'];
  $action = null; // aucune action
}

// détection du cas d'utilisation d'un raccourci et dans ce cas transformation dans le path résolu
if (preg_match('!^/([^/]+)/?$!', $fserverId, $matches)) {
  //echo 'matches2='; print_r($matches);
  $raccourci = $matches[1];
  //echo "raccourci $raccourci<br>\n";
  if (!isset(RACCOURCIS[$raccourci]))
    output($f, ['error'=> "$raccourci n'est pas un raccourci enregistré"]);
  $fserverId = RACCOURCIS[$raccourci];
  //echo "fserverId=$fserverId<br>\n";
}

// identification du type de serveur et de son path
if (!preg_match('!^/(wfs|pgsql|mysql|file)(/.*)$!', $fserverId, $matches)) {
  output($f, ['error'=> "no match3 for '$_SERVER[PATH_INFO]'"]);
}
//echo 'matches3='; print_r($matches);
$type = $matches[1];
$path = $matches[2];
switch($type) {
  case 'wfs': {
    $fServer = new FeatureServerOnWfs("https:/$path");
    break;
  }
  case 'file': {
    $fServer = new FeatureServerOnFile($path);
    break;
  }
  case 'mysql':
  case 'pgsql': {
    $fServer = new FeatureServerOnSql("$type:/$path");
    break;
  }
  
  default: output($f, ['error'=> "traitement $type non défini"]);
}

if (!$action) { // /
  output($f, $fServer->landingPage($f));
}
elseif ($action == 'conformance') { // /conformance
  output($f, $fServer->conformance());
}
elseif ($action == 'api') { // /api
  output($f, $fServer->api());
}
elseif ($action == 'check') { // /check
  //output($f, $fServer->checkTables());
  foreach ($fServer->checkTables() as $tableName => $tableProp) {
    //echo Yaml::dump([$tableName => $tableProp]);
    echo "$tableName:\n";
    if ($tableProp['geomColumnNames'])
      echo "  geom ",implode(', ', $tableProp['geomColumnNames'])," ok\n";
    else
      echo "  geom KO\n";
    if ($tableProp['pkColumnName'])
      echo "  pk ok\n";
    else
      echo "  <a href='$_SERVER[SCRIPT_NAME]$fserverId/collections/$tableName/createPrimaryKey'>Créer une clé primaire</a>\n";
  }
  die();
}
elseif (!$collId) { // /collections
  output($f, $fServer->collections(), 4);
}
elseif (!$action2) { // /collections/{collId}
  output($f, $fServer->collection($collId), 4);
}
elseif ($action2 == '/describedBy') { // /collections/{collId}/describedBy
  output($f, $fServer->collDescribedBy($collId), 6);
}
elseif ($action2 == '/createPrimaryKey') { // /collections/{collId}/createPrimaryKey
  $fServer->repairTable('createPrimaryKey', $collId);
}
elseif (!$itemId) { // /collections/{collId}/items
  output($f,
    $fServer->items(
      collId: $collId,
      bbox: isset($_GET['bbox']) ? explode(',',$_GET['bbox']) : [],
      limit: $_GET['limit'] ?? 100,
      startindex: $_GET['startindex'] ?? 0
    ), 6
  );
}
else { // /collections/{collId}/items/{itemId}
  output($f, $fServer->item($collId, $itemId), 6);
}

