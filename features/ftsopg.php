<?php
/*PhpDoc:
name: ftsopg.php
title: ftsopg.php - exposition des données stockées sur PgSql OVH au protocoles API Features
doc: |
  Définition d'une page d'accueil spécifique et
  pour une base PgSql donnée création à la volée d'un fichier doc contenant le path adéquate

  Il serait nécessaire:
    - de stocker des informations annexes dans la base, comme un titre pour la table
    - de réutiliser les docs lorsque la table est documentée
journal: |
  1/3/2022:
    - appel par la fonction fts()
  18/2/2022:
    - création
includes:
  - fts.inc.php
*/
define ('SERVER_URI', 'pgsql://benoit@db207552-001.dbaas.ovh.net:35250');

//require_once __DIR__.'/../../phplib/sqlschema.inc.php';
require_once __DIR__.'/fts.inc.php';

if (!isset($_SERVER['PATH_INFO']) || ($_SERVER['PATH_INFO'] == '/')) { // appel sans paramètre 
  echo "</pre><h2>Bouquet de serveurs OGC API Features</h2>
Ce site expose un bouquet de serveurs conformes
à la <a href='http://docs.opengeospatial.org/is/17-069r3/17-069r3.html' target='_blank'>norme OGC API Features</a>.<br>
Il est en développement et uniquement certains types de serveurs sont conformes à cette norme.<br>
Les sources de données exposées sont les bases stockées sur OVH dans PostgreSql.<p>

Les URI des serveurs sont les suivants :<ul>\n";

  $baseIds = [];
  foreach (\Sql\schema::listOfPgCatalogs(SERVER_URI) as $baseUri) {
    $baseId = substr($baseUri, strlen(SERVER_URI)+1);
    if (in_array($baseId, ['postgres','template1','template0'])) continue;
    $baseIds[$baseId] = 1;
  }
  ksort($baseIds);
  $doc = new Doc;
  foreach (array_keys($baseIds) as $baseId) {
    $url = FeatureServer::selfUrl()."/$baseId";
    $path = str_replace('pgsql://', '/pgsql/', SERVER_URI)."/$baseId/public";
    $title = '';
    if ($dataset = $doc->datasetByPath($path))
      $title = $dataset->title();
    if ($title)
      echo "<li><a href='$url'>$title ($baseId)</a></li>\n";
    else
      echo "<li><a href='$url'>$baseId</a></li>\n";
  }

  echo "</ul>
Ces serveurs peuvent notamment être utilisés avec les dernières versions
de <a href='https://www.qgis.org/fr/site/' target='_blank'>QGis (3.16)</a>
ou être consultés en Html.<br>\n";
  die();
}

if (!preg_match('!^/([^/]+)!', $_SERVER['PATH_INFO'], $matches))
  error("Erreur, chemin '$_SERVER[PATH_INFO]' non interprété", 400);

//echo "matches="; print_r($matches);
$baseId = $matches[1];

$doc = new Doc([
  'title'=> "Doc contenant le chemin pour la base souhaitée",
  '$schema'=> 'doc',
  'datasets'=> [
    $baseId => [
      'title'=> $baseId,
      'path'=> str_replace('pgsql://', '/pgsql/', SERVER_URI)."/$baseId/public",
    ],
  ],
]);

fts($_SERVER['PATH_INFO'], $doc);