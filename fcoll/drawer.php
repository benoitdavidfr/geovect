<?php
namespace fcoll;
{/*PhpDoc:
name:  drawer.php
title: drawer.php - Dessin générique de données GeoJSON
classes:
doc: |
  Utilisé par le viewer dans index.php pour dessiner la carte
  Prend en paramètre la carte à dessiner qui est un export JSON de l'objet Map de index.php
  Le dessin est effectué en WebMercator.
  Lors du dessin d'une couche, la sélection d'objets est restreinte à la fenêtre courante d'affichage.
journal: |
  20/5/2019:
    - adaptation à la nouvelle interface de FeatureCollection
  18/5/2019:
    - passage du dessin en coord. Web Mercator
  18/5/2019:
    - transfert de fcoll dans geovect
  8/5/2019:
    - création
includes:
  - ../coordsys/light.inc.php
  - ../gegeom/gddrawing.inc.php
  - fctree.inc.php
  - database.inc.php
*/}
require_once __DIR__.'/../coordsys/light.inc.php';
require_once __DIR__.'/../gegeom/gddrawing.inc.php';
require_once __DIR__.'/fctree.inc.php';
require_once __DIR__.'/database.inc.php';

use \WebMercator;
use \gegeom\GdDrawing;
use \gegeom\GBox;
use \gegeom\Geometry;

ini_set('memory_limit', '1280M');

$map = json_decode($_GET['map'], true);
//echo json_encode($map, JSON_PRETTY_PRINT);

$projPos = function(array $pos) { return WebMercator::proj($pos); };

//echo "world=",implode(',',$map['world']),"<br>\n";
$drawing = new GdDrawing(
  $map['width'][$_GET['size']], $map['height'][$_GET['size']],
  (new GBox($map['world'] ?? [-180,-85,180,85]))->proj($projPos)
);

$worldCriteria = $map['world'] ? ['bbox'=> $map['world']] : [];
foreach ($map['layers'] as $layer) {
  $lyrCriteria = Table::conjunction($worldCriteria, $layer['criteria'] ?? []);
  foreach (FCTree::create($layer['path'])->features($lyrCriteria) as $feature) {
    try {
      Geometry::fromGeoJSON($feature['geometry'])
        ->proj($projPos)
        ->draw($drawing, isset($layer['style']) ? $layer['style'] : []);
    }
    catch (\Exception $e) {
      echo "Erreur ",$e->getMessage()," sur feature ", json_encode($feature);
    }
  }
}
$drawing->flush('image/png', false);
