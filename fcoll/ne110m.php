<?php
/*PhpDoc:
name: ne110m.php
title: ne110m.php/ne10m.php - extrait une couche de ne_110m ou ne_10m de MySQL en GeoJSON - existe sous 2 noms
doc: |
  sans paramère affiche la liste des tables
  avec en paramètre 1 le nom d'une table, exporte la table en GeoJSON
  le paramètre 2 peut être utilisé pour définir une clause where
  Le fichier GeoJSON produit a la particularité d'être composé de:
   - 2 lignes d'en-tête
   - puis une ligne par feature
   - puis une ligne de fin terminée par un \n
  Cela permet de lire facilement le fichier feature par feature.
  Le nom du champ géométrique doit être geom.
  La base (ne_110m ou ne_10m) est définie en fonction du nom du script.
includes: [../../phplib/sql.inc.php, secret.inc.php]
*/
require_once __DIR__.'/../../phplib/sql.inc.php';

header('Content-type: application/json');

function passwd(string $params): string {
  if (!is_file(__DIR__.'/secret.inc.php'))
    throw new \Exception("Erreur absence de fichier des mots de passe");
  $passwds = require(__DIR__.'/secret.inc.php');
  if (!isset($passwds[$params]))
    throw new \Exception("Pas de mot de passe pour $params");
  $passwd = $passwds[$params];
  return str_replace('@', ":$passwd@", $params);
} 

//print_r($argv); die();
if (php_sapi_name()=='cli') {
  $db = $argv[0]=='ne10m.php' ? 'ne_10m' : 'ne_110m';
  MySql::open("$sqlparams/$db");
  if ($argc == 1) {
    echo "Liste des tables:\n";
    $query = "select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA='$db'";
    foreach (MySQL::query($query) as $tuple) {
      echo "  - $tuple[TABLE_NAME]\n";
    }
    die();
  }
  elseif ($argc == 2) {
    $table_name = $argv[1];
    $where = '';
  }
  elseif ($argc == 3) {
    $table_name = $argv[1];
    $where = $argv[2];
  }
}
else {
  die("Non prévu");
}

echo "{\"type\": \"FeatureCollection\",\n \"features\": [\n";
Sql::open(passwd('mysql://root@172.17.0.3/sys'));
$query = "select *, ST_AsGeoJSON(geom) geojson from $db.$table_name".($where ? " where $where" : '');
$first = true;
foreach (MySQL::query($query) as $tuple) {
  //print_r($tuple);
  $geom = $tuple['geojson'];
  unset($tuple['geom']);
  unset($tuple['geojson']);
  $feature = [
    'type'=> 'Feature',
    'properties'=> $tuple,
    'geometry'=> json_decode($geom),
  ];
  echo ($first ? '':",\n"),'  ',json_encode($feature);
  $first = false;
}
echo "\n]}\n";
