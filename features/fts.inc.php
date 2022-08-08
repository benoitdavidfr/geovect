<?php
/*PhpDoc:
name: fts.inc.php
title: fts.inc.php - définition de fonctions pour l'exposition de données au protocoles API Features
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

  Ce fichier peut être inclus dans un script pour utiliser le code du serveur OGC API Features

  Utilisation avec QGis:
    - les dernières versions de QGis (3.16) peuvent utiliser les serveurs OGC API Features
    - a priori elles n'exploitent pas ni n'affichent la doc, notamment les schemas
    - QGis utilise titre et description fournis dans /collections
    - lors d'une requête QGis demande des données zippées ou deflate
    - a priori QGis n'utilise pas les possibilités de filtre définies dans l'API

  Perf:
    - 3'05" pour troncon_hydro R500 sur FX depuis Alwaysdata
    - 47' même données gzippées et !JSON_PRETTY_PRINT soit 1/4

  Utilisation avec curl:
    curl -X GET "https://features.geoapi.fr/ignf-route500/collections/aerodrome/items?f=json&limit=10&startindex=0" -H  "accept: application/geo+json"
    curl -X GET "http://localhost/geovect/features/fts.php/ignf-route500/collections/aerodrome/items?f=json&limit=10&startindex=0" -H  "accept: application/geo+json"
    curl -X GET "http://localhost/geovect/features/fts.php/route500it/collections/aerodrome/items?f=json&limit=10&startindex=0" -H  "accept: application/geo+json"

  A faire (court-terme):
    - rajouter dans les liens au niveau de chaque collection,
      un lien {type: text/html, rel: canonical, title: information, href= ...}
      vers la doc quand il y a au moins soit une description, soit la définition de propriétés
    - rajouter dans les liens au niveau de chaque collection, un lien de téléchargement simple quand j'en dispose d'un,
      ex: { "href": "http://download.example.org/buildings.gpkg",
            "rel": "enclosure", "type": "application/geopackage+sqlite3",
            "title": "Bulk download (GeoPackage)", "length": 472546
          }
    - gérer correctement les types non string dans les données comme les nombres
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
  7/8/2022:
    - corrections suite à PhpStan level 6
  1/3/2022:
    - définition de la fonction fts pour clarifier la réutilisation du code
  28/2/2022:
    - gestion du bbox en POST pour satisfaire aux besoins de L.UGeoJSONLayer dans Leaflet
    - gestion properties et filters dans l'appel de items()
  27/2/2022:
    - amélioration de la gestion des erreurs, utilisation de SExcept
  25/2/2022:
    - bug corrigé sur Alwaysdata
    - synchro sur alwaysdata
  18/2/2022:
    - adaptation à la mise en oeuvre de ftsopg.php
  6/2/2021:
    - réduction de l'empreinte mémoire dans items par l'utilisation de display_json() et display_fmt()
  27/1/2021:
    - détection des paramètres non prévus
    - test CITE ok pour /ignf-route500
  20-23/1/2021:
    - intégration de la doc
  17/1/2021:
    - onsql fonctionne avec QGis
  30/12/2020:
    - création
includes:
  - sexcept.inc.php
  - doc.php
  - ftrserver.inc.php
  - displayjson.inc.php
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../lib/sexcept.inc.php';
require_once __DIR__.'/doc.php';
require_once __DIR__.'/ftrserver.inc.php';
require_once __DIR__.'/displayjson.inc.php';

use Symfony\Component\Yaml\Yaml;

define('SHOW_WFS_QUERIES', false); // si true et en Html alors affiche les reqêtes WFS effectuées pour traiter la requête
 
// Table utilisée par la fonction error()
define('HTTP_ERROR_LABELS', [
  400 => 'Bad Request', // La syntaxe de la requête est erronée.
  404 => 'Not Found', // Ressource non trouvée. 
  500 => 'Internal Server Error', // Erreur interne du serveur. 
  501 => 'Not Implemented', // Fonctionnalité réclamée non supportée par le serveur.
]
);

// affiche $array en JSON, GeoJSON, Html ou Yaml en fonction du paramètre $f
// JSON ou GeoJSON sont utilisés dans les échanges entre programmes, je pourrais ultérieurement supprimer les options
// Html ou Yaml sont utilisés pour affichage aux humains, levels est le niveau de développement en Yaml
function output(string $f, array $array, int $levels=3) {
  switch ($f) {
    case 'json': {
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/json; charset="utf8"');
      //header('Content-type: text/plain; charset="utf8"');
      die(json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
    case 'geojson': {
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/geo+json; charset="utf8"');
      if (in_array('gzip', explode(',', getallheaders()['Accept-Encoding'] ?? ''))) {
        header('Content-Encoding: gzip');
        die(gzencode(json_encode($array, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)));
      }
      else {
        //die(json_encode($array, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        die(json_encode($array, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
    }
    case 'yaml': {
      header('Content-type: text/plain; charset="utf8"');
      die(Yaml::dump($array, $levels, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }
    case 'html': {
      echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>fts</title></head><body><pre>\n";
      $yaml = Yaml::dump($array, $levels, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
      // remplace les URL par des liens HTML
      $html = preg_replace("!(https?://[^' ]+)!", "<a href='$1'>$1</a>", $yaml);
      echo $html;
      // affiche en plus les requêtes WFS effectuées pour répondre à la requête
      if (SHOW_WFS_QUERIES && ($lastQueryUrls = WfsServer::lastQueryUrls())) {
        echo preg_replace("!(https?://[^' ]+)!", "<a href='$1'>$1</a>", Yaml::dump(['lastQueryUrls'=> $lastQueryUrls]));
      }
      die();
    }
  }
}

// affiche $iterable en GeoJSON, Html ou Yaml en fonction du paramètre $f
function outputIterable(string $f, array $iterable) {
  switch ($f) {
    case 'geojson': {
      $flags = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
      header('Access-Control-Allow-Origin: *');
      header('Content-type: application/geo+json; charset="utf8"');
      $fout = '';
      if (in_array('gzip', explode(',', getallheaders()['Accept-Encoding'] ?? ''))) {
        header('Content-Encoding: gzip');
        $fout = 'compress.zlib://';
      }
      die (display_json(
        enveloppe: $iterable['enveloppe'],
        tokens: $iterable['tokens'],
        iterable: $iterable['iterable'],
        fout: $fout,
        filter: $iterable['filter'] ?? null,
        flags: $flags
      ));
    }
    case 'yaml': header('Content-type: text/plain; charset="utf8"');
    case 'html': {
      echo "outputIterable\n";
      die(display_fmt(
        fmt: $f,
        enveloppe: $iterable['enveloppe'],
        tokens: $iterable['tokens'],
        iterable: $iterable['iterable'],
        filter: $iterable['filter'] ?? null
      ));
    }
  }
}

// Génère une erreur Http avec le code $code si <>0 ou sinon 500 et affiche le message d'erreur
// effectue aussi un log des erreurs
function error(string $message, int $code=0, string $sCode='', int $originalHttpCode=0) {
  $error = [
    'message'=> $message
  ]
  + ($code ? ['code'=> $code] : [])
  + ($sCode ? ['sCode'=> $sCode] : [])
  + ($originalHttpCode ? ['originalHttpCode'=> $originalHttpCode]: []);
  $lastQueryUrls = WfsServer::lastQueryUrls();
  // log les erreurs
  FeatureServer::log(
    [
      'REQUEST_URI'=> $_SERVER['REQUEST_URI'],
      'Headers'=> getallheaders(),
    ]
    + ($lastQueryUrls ? ['lastQueryUrls'=> $lastQueryUrls] : [])
    + ['error'=> $error]
  );
  if ($code == 0)
    $code = 500;
  header("HTTP/1.1 $code ".(HTTP_ERROR_LABELS[$code] ?? "Undefined httpCode $code"));
  header('Content-type: text/plain');
  echo Yaml::dump(['Exception'=> $error]);
  // affiche en plus les requêtes WFS effectuées pour répondre à la requête
  if (SHOW_WFS_QUERIES && $lastQueryUrls) {
    echo Yaml::dump(['lastQueryUrls'=> $lastQueryUrls]);
  }
  die();
}  

// Fonction globale de mis en oeuvre du Feature Service
function fts(string $pathInfo, Doc $doc=null): void {
  // si _GET[f] est défini alors il est utilisé, sinon essaie d'utiliser le header Accept
  // si ni 'yaml', ni 'json', ni 'geojson' alors 'html'
  switch ($f = $_GET['f'] ?? (in_array('text/html', explode(',', getallheaders()['Accept'] ?? '')) ? 'html' : 'json')) {
    case 'yaml': break;
    case 'json':
    // si $f vaut geojson alors  transformation en 'json'. A l'affichage si geo alors geojson
    case 'geojson': $f = 'json'; break;
    case 'html':
    // $f doit valoir 'html', 'yaml' ou 'json' sinon 'html'
    default: {
      $f = 'html';
      break;
    }
  }

  if (preg_match('!^((/[^/]+)+)/(conformance|api|check)$!', $pathInfo, $matches)) { // cmde 1er niveau sur fserver
    $fserverId = $matches[1];
    $action = $matches[3];
    $action2 = null;
  }

  // détermination de la partie $fserverId
  // détection de /collections
  elseif (preg_match('!^((/[^/]+)+)/collections!', $pathInfo, $matches)) {
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
    $fserverId = $pathInfo;
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
    $fserverId = $datasetDoc->path();
    //echo "fserverId=$fserverId<br>\n";
  }

  // identification du type de serveur et de son path
  if (!preg_match('!^/(wfs|pgsql|mysql|mysqlIt|file)(/.*)$!', $fserverId, $matches)) {
    error("Erreur, type de serveur non détecté dans '$fserverId'", 400);
  }
  //echo 'matches3='; print_r($matches);
  $type = $matches[1];
  $path = $matches[2];
  $fServer = FeatureServer::new($type, $path, $f, $datasetDoc);

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
        if ($tableProp['geoColumnNames'])
          echo "  geo ",implode(', ', $tableProp['geoColumnNames'])," ok\n";
        else
          echo "  geo KO\n";
        if ($tableProp['pkColumnName'])
          echo "  pk ok\n";
        else
          echo "  <a href='$_SERVER[SCRIPT_NAME]$fserverId/collections/$tableName/createPrimaryKey'>",
               "Créer une clé primaire</a>\n";
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
    elseif ($itemId === null) { // /collections/{collectionId}/items
      //$fServer->checkParams("/$action/$collId/items");
      // dans ftsOnSql, le paramètre limit vaut au max 10000 et le résultat n'est pas construit en mémoire
      $filters = [];
      foreach ($_GET as $k => $v) {
        if (!in_array($k, ['f','bbox','properties','limit','startindex']))
          $filters[$k] = $v;
      }
      $bbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : '');
      $bbox = $bbox ? explode(',', $bbox) : [];
      if (in_array($type, ['mysqlIt','pgsqlIt'])) {
        outputIterable(
          ($f == 'json' ? 'geojson' : $f),
          $fServer->itemsIterable(
            f: $f,
            collId: $collId,
            bbox: $bbox,
            filters: $filters,
            properties: isset($_GET['properties']) ? explode(',', $_GET['properties']) : [], // liste des prop. à retourner
            limit: $_GET['limit'] ?? 10,
            startindex: $_GET['startindex'] ?? 0
          )
        );
      }
      // dans les autres drivers, le max de limit vaut 1000 et le résultat peut être construit en mémoire
      else {
        output(
          ($f == 'json' ? 'geojson' : $f),
          $fServer->items(
            f: $f,
            collId: $collId,
            bbox: $bbox,
            filters: $filters,
            properties: isset($_GET['properties']) ? explode(',', $_GET['properties']) : [], // liste des prop. à retourner
            limit: $_GET['limit'] ?? 10,
            startindex: $_GET['startindex'] ?? 0
          )
        );
      }
    }
    else { // /collections/{collectionId}/items/{featureId}
      $fServer->checkParams("/$action/{collectionId}/items/{featureId}");
      output(($f == 'json' ? 'geojson' : $f), $fServer->item($f, $collId, $itemId), 6);
    }
    //} catch(XX $e) { // Permet de rien attraper en changeant le code un minimum 
  } catch (SExcept $e) { // Transformation des codes d'erreur SExcept en code d'erreur Http
    switch ($e->getSCode()) {
      case FeatureServer::ERROR_BAD_BBOX:
      case FeatureServer::ERROR_BAD_PARAMS: {
        error($e->getMessage(), 400, $e->getSCode(), $e->getCode());
      }
      case CollOnSql::ERROR_COLL_NOT_FOUND:
      case FeatureServerOnWfs::ERROR_COLL_NOT_FOUND:
      case FeatureServerOnSql::ERROR_ITEM_NOT_FOUND:
      case FeatureServerOnWfs::ERROR_ITEM_NOT_FOUND: {
        error($e->getMessage(), 404, $e->getSCode(), $e->getCode());
      }
      case WfsServer::ERROR_WFS_QUERY: {
        if ($e->getCode()==404)
          error($e->getMessage(), 404, $e->getSCode(), $e->getCode());
        else
          error($e->getMessage(), 500, $e->getSCode(), $e->getCode());
      }
      default: {
        error($e->getMessage(), 500, $e->getSCode(), $e->getCode());
      }
    }
  } catch (Exception|TypeError $e) {
    error($e->getMessage(), 500);
  }
}
