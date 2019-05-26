<?php
namespace fcoll;
/*PhpDoc:
name: antarctique.php
title: antarctique.php - test d'affichage de l'Antarctique
doc: |
  L'affichage de l'Antarctique en WM pose un problème car le polygone principal comprend des points en dessous de -85°
  de latitude qui génère des erreurs dans le calcul de coord. WM
  Ce script permet de tester ce cas et de le prendre en compte dans gddrawing.inc.php
includes:
  - ../coordsys/light.inc.php
  - ../gegeom/gebox.inc.php
  - ../gegeom/gegeom.inc.php
  - ../gegeom/gddrawing.inc.php
  - fctree.inc.php
*/
require_once __DIR__.'/../coordsys/light.inc.php';
require_once __DIR__.'/../gegeom/gebox.inc.php';
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/../gegeom/gddrawing.inc.php';
require_once __DIR__.'/fctree.inc.php';

use \WebMercator;
use \gegeom\Geometry;
use \gegeom\GBox;
use \gegeom\GdDrawing;

$mapunits = FCTree::create('/geovect/fcoll/databases.yaml/dbServers-myL/ne_110m/admin_0_map_units');
foreach ($mapunits->features(['subunit'=>'Antarctica']) as $feature) {
  $antartica = $feature;
}
//print_r($antartica); die();
$antartica = Geometry::fromGeoJSON($antartica['geometry']);
$world = new GBox([-180,-85,180,85]);
//echo "world=$world<br>\n";
$worldWM = $world->proj(function(array $pos) { return WebMercator::proj($pos); }); // la fenêtre en coord. WM
//echo "worldWM=$worldWM<br>\n";
$gdDrawing = new GdDrawing(600, 800, $worldWM);
if (0) // affichage des coordonnées écran
  echo json_encode(
    $antartica
      ->proj(function(array $pos) { return WebMercator::proj($pos); }) // passage en WM
        ->proj(function(array $pos) use($gdDrawing) { return $gdDrawing->proj($pos); }) // passage en coord. ecran
          ->asArray()
  );
elseif (0) { // dessin polyligne en WM
  foreach ($antartica->proj(function(array $pos) { return WebMercator::proj($pos); }) // passage en WM
      ->asArray()['coordinates'] as $llpos) {
    foreach ($llpos as $lpos) {
      $gdDrawing->polyline($lpos);
    }
  }
  $gdDrawing->flush();
}
else { // dessin polygon en WM
  foreach ($antartica->proj(function(array $pos) { return WebMercator::proj($pos); }) // passage en WM
      ->asArray()['coordinates'] as $llpos) {
    $gdDrawing->polygon($llpos);
  }
  $gdDrawing->flush();
}
