<?php
/*PhpDoc:
name: ftps.php
title: fts.php - exposition de données au protocoles API Features
doc: |
  Proxy exposant en API Features des données initialement stockées soit dans une BD MySql ou PgSql, soit dans un serveur WFS2,
  soit dans répertoire de fichiers GeoJSON.

  Permet soit d'utiliser un serveur non enregistré en utilisant par exemple pour un serveur WFS l'url:
    https://features.geoapi.fr/wfs/services.data.shom.fr/INSPIRE/wfs
     ou en local:
      http://localhost/geovect/features/fts.php/wfs/services.data.shom.fr/INSPIRE/wfs
  soit d'utiliser des serveurs biens connus et documentés comme dans l'url:
   https://features.geoapi.fr/shomwfs
    ou en local:
      http://localhost/geovect/features/fts.php/shomwfs
  
  Un appel sans paramètre liste les serveurs bien connus et des exemples d'appel.

  Utilisation avec QGis:
    - les dernières versions de QGis (3.16) peuvent utiliser les serveurs OGC API Features
    - a priori elles n'exploitent pas ni n'affichent la doc, notamment les schemas
    - lors d'une requête QGis demande des données zippées ou deflate

  Perf:
    - 3'05" pour troncon_hydro R500 sur FX depuis Alwaysdata
    - 47' même données gzippées et !JSON_PRETTY_PRINT soit 1/4

  A faire (court-terme):
    - gérer correctement les types non string dans les données comme les nombres
    - satisfaire au test CITE
  Réflexions (à mûrir):
    - distinguer un outil d'admin différent de l'outil fts.php de consultation
      - y transférer l'opération check de vérif. de clé primaire et de création éventuelle
      - ajouter une fonction de test de cohérence doc / service déjà écrite dans doc
  Idées (plus long terme):
    - mettre en oeuvre le mécanisme i18n défini pour OGC API Features
    - remplacer l'appel sans paramètre par l'exposition d'un catalogue DCAT
    - renommer geovect en gdata pour green data
    - étendre features aux autres OGC API ?
journal: |
  27/1/2021:
    - détection des paramètres non prévus
  20-23/1/2021:
    - intégration de la doc
  17/1/2021:
    - onsql fonctionne avec QGis
  30/12/2020:
    - création
includes:
  - doc.php
  - ftrserver.inc.php
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/doc.php';
require_once __DIR__.'/ftrserver.inc.php';

use Symfony\Component\Yaml\Yaml;

//echo "<pre>"; print_r($_SERVER); die();

ini_set('memory_limit', '1G');

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
    'admin_0_countries'=> 'limit=5&startindex=100',
  ],
  'mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_ne_110m'=> [],
  'ne_110m' => [
    'admin_0_countries' => 'limit=10&startindex=5',
  ],
  'ne_10m' => [
    'admin_0_countries' => 'limit=10&startindex=5',
  ],
  'ignf-route500' => [
    'troncon_voie_ferree' => 'limit=10&startindex=5',
  ],
  'mysql/bdavid@mysql-bdavid.alwaysdata.net/bdavid_geovect'=>[],
  'test@mysql' => [
    'unchampstretunegeom' => 'limit=10',
  ],
  'localgis'=> [
    'departement_carto' => 'limit=5&startindex=10&f=json',
  ],
  'comhisto'=> [
    'comhistog3',
  ],
]
);

define('HTTP_ERROR_LABELS', [
  400 => 'Bad Request', // La syntaxe de la requête est erronée.
  404 => 'Not Found', // Ressource non trouvée. 
  500 => 'Internal Server Error', // Erreur interne du serveur. 
  501 => 'Not Implemented', // Fonctionnalité réclamée non supportée par le serveur.
]);

FeatureServer::log([
  'REQUEST_URI'=> $_SERVER['REQUEST_URI'],
  'Hedaders'=> getallheaders(),
]
); // log de la requête pour deboggage, a supprimer en production

// affiche $array en JSON, GeoJSON, Html ou Yaml en fonction du paramètre $f
// JSON ou GeoJSON sont utilisés dans les échanges entre programmes, je pourrais ultérieurement supprimer les options
// Html ou Yaml sont utilisés pour affichage aux humains, levels est le niveau de développement en Yaml
function output(string $f, array $array, int $levels=3) {
  switch ($f) {
    case 'json': {
      header('Content-type: application/json; charset="utf8"');
      //header('Content-type: text/plain; charset="utf8"');
      die(json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
    case 'geojson': {
      if (in_array('gzip', explode(',', getallheaders()['Accept-Encoding'] ?? ''))) {
        header('Content-type: application/geo+json; charset="utf8"');
        header('Content-Encoding: gzip');
        die(gzencode(json_encode($array, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)));
      }
      else {
        header('Content-type: application/geo+json; charset="utf8"');
        //header('Content-type: text/plain; charset="utf8"');
        //die(json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        die(json_encode($array, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
    }
    case 'yaml': die(Yaml::dump($array, $levels, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    case 'html': {
      $yaml = Yaml::dump($array, $levels, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
      // remplace les URL par des liens HTML
      $html = preg_replace("!(https?://[^' ]+)!", "<a href='$1'>$1</a>", $yaml);
      die($html);
    }
  }
}

// Génère une erreur Http avec le code $code si <>0 ou sinon 500 
// et affiche le message d'erreur
function error(string $message, int $code=0) {
  if ($code == 0)
    $code = 500;
  header("HTTP/1.1 $code ".(HTTP_ERROR_LABELS[$code] ?? "Undefined httpCode $code"));
  header('Content-type: text/plain');
  die (Yaml::dump(['Exception'=> [
    'message'=> $message,
    'code'=> $code,
  ]]));
}  
  
// si _GET[f] est défini alors il est utilisé, sinon si appel navigateur (header Accept) alors 'html' sinon 'json'
// si ni 'yaml', ni 'json', ni 'geojson' alors 'html'
switch ($f = $_GET['f'] ?? (in_array('text/html', explode(',', getallheaders()['Accept'] ?? '')) ? 'html' : 'json')) {
  case 'html': echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>fts</title></head><body><pre>\n"; break;
  case 'yaml': header('Content-type: text/plain; charset="utf8"'); break;
  case 'json':
  // si $f vaut geojson alors  transformation en 'json'. A l'affichage si geo alors geojson
  case 'geojson': $f = 'json'; break;
  // $f doit valoir 'html', 'yaml' ou 'json' sinon 'html'
  default: {
    $f = 'html';
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>fts</title></head><body><pre>\n";
    break;
  }
}

//print_r($_SERVER);
  
$doc = new Doc; // documentation des serveurs biens connus 

if (!isset($_SERVER['PATH_INFO']) || ($_SERVER['PATH_INFO'] == '/')) { // appel sans paramètre 
  echo "Serveurs biens connus:\n";
  foreach ($doc->datasets as $dsid => $dsDoc) {
    echo "  - <a href='$_SERVER[SCRIPT_NAME]/$dsid'>$dsDoc->title</a> => $dsDoc->path\n";
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

if (preg_match('!^((/[^/]+)+)/(conformance|api|check)$!', $_SERVER['PATH_INFO'], $matches)) { // cmde 1er niveau sur fserver
  $fserverId = $matches[1];
  $action = $matches[3];
  $action2 = null;
}

// détermination de la partie $fserverId
// détection de /collections
elseif (preg_match('!^((/[^/]+)+)/collections!', $_SERVER['PATH_INFO'], $matches)) {
  if (!preg_match('!^((/[^/]+)+)/collections(/([^/]+)(/items(/.*)?|/describedBy|/createPrimaryKey)?)?$!',
      $_SERVER['PATH_INFO'], $matches))
    error("Erreur, chemin '$_SERVER[PATH_INFO]' non interprété", 400);
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
$datasetDoc = null; // la doc du dataset si elle est définie
if (preg_match('!^/([^/]+)/?$!', $fserverId, $matches)) {
  //echo 'matches2='; print_r($matches);
  $datasetId = $matches[1];
  //echo "raccourci $raccourci<br>\n";
  if (!isset($doc->datasets[$datasetId]))
    error("Erreur, $datasetId n'est pas l'identifiant d'un serveur prédéfini", 400);
  $datasetDoc = $doc->datasets[$datasetId];
  $fserverId = $datasetDoc->path;
  //echo "fserverId=$fserverId<br>\n";
}

// identification du type de serveur et de son path
if (!preg_match('!^/(wfs|pgsql|mysql|file)(/.*)$!', $fserverId, $matches)) {
  error("Erreur, type de serveur non détecté dans '$_SERVER[PATH_INFO]'", 400);
}
//echo 'matches3='; print_r($matches);
$type = $matches[1];
$path = $matches[2];
$fServer = FeatureServer::new($type, $path, $datasetDoc);

try {
  if (!$action) { // /
    $fServer->checkParams('/');
    output($f, $fServer->landingPage($f));
  }
  elseif ($action == 'conformance') { // /conformance
    $fServer->checkParams("/$action");
    output($f, $fServer->conformance());
  }
  elseif ($action == 'api') { // /api
    $fServer->checkParams("/$action");
    output($f, $fServer->api(), 999);
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
    $fServer->checkParams("/$action");
    output($f, $fServer->collections($f), 4);
  }
  elseif (!$action2) { // /collections/{collectionId}
    $fServer->checkParams("/$action/{collectionId}");
    output($f, $fServer->collection($f, $collId), 4);
  }
  elseif ($action2 == '/describedBy') { // /collections/{collectionId}/describedBy
    $fServer->checkParams("/$action/{collectionId}/describedBy");
    output($f, $fServer->collDescribedBy($collId), 6);
  }
  elseif ($action2 == '/createPrimaryKey') { // /collections/{collectionId}/createPrimaryKey
    $fServer->repairTable('createPrimaryKey', $collId);
  }
  elseif (!$itemId) { // /collections/{collectionId}/items
    $fServer->checkParams("/$action/$collId/items");
    output(($f == 'json' ? 'geojson' : $f),
      $fServer->items(
        f: $f,
        collId: $collId,
        bbox: isset($_GET['bbox']) ? explode(',',$_GET['bbox']) : [],
        limit: $_GET['limit'] ?? 10,
        startindex: $_GET['startindex'] ?? 0
      ), 6
    );
  }
  else { // /collections/{collectionId}/items/{featureId}
    $fServer->checkParams("/$action/{collectionId}/items/{featureId}");
    output(($f == 'json' ? 'geojson' : $f), $fServer->item($f, $collId, $itemId), 6);
  }
} catch (Exception|TypeError $e) {
  error($e->getMessage(), $e->getCode());
}
