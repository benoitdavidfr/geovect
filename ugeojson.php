<?php
/*PhpDoc:
name: ugeojson.php
title: ugeojson.php - accès à une FeatureCollection selon le protocole UGeoJSON étendu
doc: |
  Accès aux données GeoJSON de la base locale MySql en utilisant un protocole de type UGeoJSON
  dans lequel l'URL d'appel définit:
    - le schema, ex ne_110m
    - la collection/table, ex: coastline, admin_0_map_units
    - les critères de sélection, ex: bbox=[0,0,180,90], su_a3=FXX, su_a3(=[FXX,BEL]
  exemples:
    - http://localhost/geovect/ugeojson.php/ne_110m/collections/coastline/items?bbox=[0,0,180,90]
    - http://localhost/geovect/ugeojson.php/ne_110m/collections/admin_0_map_units/items?su_a3=FXX
  Points complémentaires:
    - http://localhost/geovect/ugeojson.php/{schema}/collections/{table}/items fournit le contenu complet de la table
    - http://localhost/geovect/ugeojson.php/{schema}/collections/{table}/schema fournit le schema de la table
    - http://localhost/geovect/ugeojson.php/{schema}/collections/{table} fournit les MD de la table, au minimum une référence
      vers son schema et une autre vers son contenu
    - http://localhost/geovect/ugeojson.php/{schema}/collections fournit en JSON la liste des tables du schema
    - http://localhost/geovect/ugeojson.php/{schema} fournit en JSON la liste des tables du schema
    - http://localhost/geovect/ugeojson.php/api fournit la documentation de l'API
    - http://localhost/geovect/ugeojson.php fournit en JSON les MD du service et la liste des schemas
  Questions:
    - http://localhost/geovect/ugeojson.php/{schema} == http://localhost/geovect/ugeojson.php/{schema}/collections ?
journal:
  25/5/2019:
    - première version
*/
require_once __DIR__.'/fcoll/database.inc.php';

$dbParams = 'mysql://root@172.17.0.3/';

header('Content-type: application/json');
//die(json_encode($_SERVER));

$path = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
//die($path);

if (!isset($_SERVER['PATH_INFO'])) { // racine = MD du serveur + exemples pour tests
  echo json_encode([
    'title'=> "Serveur UGeoJSON des FeatureCollection contenues dans la base MySql locale",
    'self'=> $path,
    'api'=> ['title'=> "documentation de l'API", 'href'=> "$path/api"],
    'schemas'=> [
      'ne_110m'=> ['title'=> "Base Natural Earth 1/110M", 'href'=> "$path/ne_110m"],
    ],
    'examples'=> [
      'coastline'=> [
        'title'=> "coastline",
        'href'=> "$path/ne_110m/collections/coastline"],
      'coastline+bbox'=> [
        'title'=> "coastline avec bbox",
        'href'=> "$path/ne_110m/collections/coastline/items?bbox=[0,0,180,90]"],
      'ne_110m/admin_0_map_units?su_a3=FXX'=> [
        'title'=> "ne_110m admin_0_map_units / su_a3=FXX",
        'href'=> "$path/ne_110m/collections/admin_0_map_units/items?su_a3=FXX"
      ],
      'ne_110m/admin_0_map_units?adm0_a3=FRA'=> [
        'title'=> "ne_110m admin_0_map_units / adm0_a3=FRA",
        'href'=> "$path/ne_110m/collections/admin_0_map_units/items?adm0_a3=FRA"
      ],
      'ne_10m/admin_0_map_units?adm0_a3=FRA'=> [
        'title'=> "ne_10m admin_0_map_units / adm0_a3=FRA",
        'href'=> "$path/ne_10m/collections/admin_0_map_units/items?adm0_a3=FRA"
      ],
    ],
  ]);
  die();
}

// "/api"
if ($_SERVER['PATH_INFO'] == '/api') {
  die(json_encode("API definition to be done"));
}

// "/{schema}" | "/{schema}/collections"
if (preg_match('!^/([^/]+)(/collections)?$!', $_SERVER['PATH_INFO'], $matches)) {
  $schemaname = $matches[1];
  die(json_encode([
    'title'=> $schemaname,
    'self'=> "$path/$schemaname",
    'collections'=> [
      'coastline'=> ['title'=> "coastline", 'href'=> "$path/$schemaname/collections/coastline"],
      'admin_0_map_units'=> ['title'=> "admin_0_map_units", 'href'=> "$path/$schemaname/collections/admin_0_map_units"],
    ],
  ]));
}

// "/{schema}/collections/{collname}"
if (preg_match('!^/([^/]+)/collections/([^/]+)$!', $_SERVER['PATH_INFO'], $matches)) {
  $schemaname = $matches[1];
  $collname = $matches[2];
  echo json_encode([
    'title'=> $collname,
    'self'=> "$path/$schemaname/collections/$collname",
    'schema'=> "$path/$schemaname/collections/$collname/schema",
    'items'=> "$path/$schemaname/collections/$collname/items",
  ]);
  die();
}

// "/{schema}/collections/{collname}/schema"
if (preg_match('!^/([^/]+)/collections/([^/]+)/schema$!', $_SERVER['PATH_INFO'], $matches)) {
  $schemaname = $matches[1];
  $collname = $matches[2];
  die(json_encode("$schemaname.$collname's schema TO BE DONE"));
}

if (preg_match('!^/([^/]+)/collections/([^/]+)/items$!', $_SERVER['PATH_INFO'], $matches)) { // "/{base}/{table}/items"
  $schemaname = $matches[1];
  $collname = $matches[2];
  $criteria = $_GET;
  if (isset($_POST) && $_POST)
    $criteria = array_merge($criteria, $_POST);
  foreach ($criteria as $name => $value) {
    if ($name == 'bbox')
      $criteria['bbox'] = json_decode($criteria['bbox']);
  }
  $table = new \fcoll\Table('', $dbParams, "$schemaname.$collname");
  echo '{"type":"FeatureCollection",',"\n";
  $query = [
    'schema'=> "$path/$schemaname",
    'collection'=> "$path/$schemaname/collections/$collname",
  ];
  if ($criteria)
    $query['criteria'] = $criteria;
  echo '"query":',json_encode($query),",\n";
  echo '"features":[',"\n";
  $first = true;
  foreach ($table->features($criteria) as $feature) {
    echo ($first ? '':",\n"),json_encode($feature);
    $first = false;
  }
  die("\n]}\n");
}

die(json_encode("No match"));