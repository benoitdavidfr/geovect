<?php
/*PhpDoc:
name: ugeojson.php
title: ugeojson.php - accès à une FeatureCollection selon le protocole UGeoJSON étendu
doc: |
  Accès aux données GeoJSON de la base MySql paramétrée en utilisant un protocole de type UGeoJSON
  dans lequel l'URL d'appel définit:
    - le schema, ex ne_110m
    - la collection/table, ex: coastline, admin_0_map_units
    - les critères de sélection, ex: bbox=[0,0,180,90], su_a3=FXX, su_a3(=[FXX,BEL]
  Le protocole s'inspire aussi de WFS3.
  exemples:
    - http://localhost/geovect/ugeojson.php/ne_110m/collections/coastline/items?bbox=[0,0,180,90]
    - http://localhost/geovect/ugeojson.php/ne_110m/collections/admin_0_map_units/items?su_a3=FXX
  Points complémentaires:
    - http://localhost/geovect/ugeojson.php/{schema}/collections/{collname}/items fournit le contenu complet de la table
    - http://localhost/geovect/ugeojson.php/{schema}/collections/{collname}/schema fournit le schema de la table
    - http://localhost/geovect/ugeojson.php/{schema}/collections/{collname} fournit les MD de la table, au minimum
      une référence vers son schema et une autre vers son contenu
    - http://localhost/geovect/ugeojson.php/{schema}/collections fournit en JSON la liste des tables du schema
    - http://localhost/geovect/ugeojson.php/{schema} fournit en JSON la liste des tables du schema
    - http://localhost/geovect/ugeojson.php/api fournit la documentation de l'API
    - http://localhost/geovect/ugeojson.php fournit en JSON les MD du service et la liste des schemas
  Questions:
    - http://localhost/geovect/ugeojson.php/{schema} == http://localhost/geovect/ugeojson.php/{schema}/collections ?
    - quelle MD ? où les stocker ?
journal:
  25/5/2019:
    - première version
*/
require_once __DIR__.'/fcoll/database.inc.php';

use Symfony\Component\Yaml\Yaml;

// paramètres de BD / host
$dbParamsByHost = [
  'localhost'=> 'mysql://root@172.17.0.3/',
  //'localhost'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
  'bdavid.alwaysdata.net'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
  //'bdavid.alwaysdata.net'=> 'pgsql://bdavid@postgresql-bdavid.alwaysdata.net/',
];
//die(json_encode($_SERVER));

if (null == $dbParams = $dbParamsByHost[$_SERVER['HTTP_HOST']] ?? null)
  die("Erreur aucun serveur de BD paramétré pour le host $_SERVER[HTTP_HOST]");
  
if (0) { // log
  file_put_contents(__DIR__.'/ugeojson.log.yaml',Yaml::dump([[
    'date'=> date( DATE_ATOM),
    'path'=> "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]?".($_SERVER['QUERY_STRING'] ?? ''),
    //'$_SERVER'=> $_SERVER,
  ]]), FILE_APPEND);
}

MySql::open($dbParams);

header('Content-type: application/json');

$path = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
//die($path);

if (!isset($_SERVER['PATH_INFO'])) { // racine = MD du serveur + liste des schemas + exemples pour tests
  $schemas = [];
  $query = "select distinct table_schema from information_schema.columns where data_type='geometry'";
  foreach(MySql::query($query) as $tuple) {
    //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
    $schemas[$tuple['table_schema']] = [
      'title'=> $tuple['table_schema'],
      'href'=> "$path/$tuple[table_schema]",
    ];
  }
  MySql::close();
  echo json_encode([
    'title'=> "Serveur UGeoJSON des FeatureCollection contenues dans la base MySql locale",
    'self'=> $path,
    'api'=> ['title'=> "documentation de l'API", 'href'=> "$path/api"],
    'schemas'=> $schemas,
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
function getIterator() {
  $children = [];
  Sql::open($this->params);
  return new \ArrayIterator($children);
}

// "/server" pour visualiser la variable $_SERVER
if ($_SERVER['PATH_INFO'] == '/server') {
  die(json_encode($_SERVER));
}

// "/api" - doc API à faire, utilité ?
if ($_SERVER['PATH_INFO'] == '/api') {
  die(json_encode("API definition to be done"));
}

// "/{schema}" | "/{schema}/collections" - liste des coillections d'un schema
if (preg_match('!^/([^/]+)(/collections)?$!', $_SERVER['PATH_INFO'], $matches)) {
  $schemaname = $matches[1];
  $collections = [];
  $query = "select distinct table_name from information_schema.columns "
    ."where table_schema='$schemaname' and data_type='geometry'";
  foreach(MySql::query($query) as $tuple) {
    //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
    $collections[$tuple['table_name']] = [
      'title'=> "$tuple[table_name]",
      'href'=> "$path/$schemaname/collections/$tuple[table_name]",
    ];
  }
  MySql::close();
  die(json_encode([
    'title'=> $schemaname,
    'self'=> "$path/$schemaname",
    'collections'=> $collections,
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

// conversion de type MySql -> JSON Schema
function mysql2jsonDataType(string $mysqlDatatype) {
  switch ($mysqlDatatype) {
    case 'varchar': return 'string';
    case 'decimal': return 'number';
    case 'bigint': return 'integer';
    case 'geometry': return ['$ref'=> 'http://json-schema.org/geojson/geometry.json#'];
    default : return $mysqlDatatype;
  }
}

// "/{schema}/collections/{collname}/schema"
if (preg_match('!^/([^/]+)/collections/([^/]+)/schema$!', $_SERVER['PATH_INFO'], $matches)) {
  $schemaname = $matches[1];
  $collname = $matches[2];
  $query = "select ordinal_position, column_name, data_type from information_schema.columns "
    ."where table_schema='$schemaname' and table_name='$collname' "
    ."order by ordinal_position";
  $properties = [];
  foreach(MySql::query($query) as $tuple) {
    //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
    $properties[$tuple['column_name']] = [
      'type'=> mysql2jsonDataType($tuple['data_type']),
    ];
  }
  MySql::close();
  die(json_encode([
    '$schema'=> 'http://json-schema.org/draft-04/schema#',
    'id'=> "$path/$schemaname/collections/$collname/schema",
    'title'=> "Schema de la collection $schemaname.$collname déduit du schema de la table dans MySql",
    'type'=> 'object',
    'required'=> array_keys($properties),
    'properties'=> $properties,
  ]));
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
  //echo '"query":',json_encode($query),",\n";
  echo '"features":[',"\n";
  $first = true;
  foreach ($table->features($criteria) as $feature) {
    echo ($first ? '':",\n"),json_encode($feature);
    $first = false;
  }
  die("\n]}\n");
}

die(json_encode("No match"));