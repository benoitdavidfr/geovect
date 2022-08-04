<?php
/*PhpDoc:
name: area.php
title: area.php - calcul de surface dans différentes projections
includes: [../gegeom/gebox.inc.php, ../gegeom/gegeom.inc.php, light.inc.php]
*/
require_once __DIR__.'/light.inc.php';
require_once __DIR__.'/../gegeom/gebox.inc.php';
require_once __DIR__.'/../gegeom/gegeom.inc.php';

// lit le fichier geojson défini par $filename et retourne un array de Feature défini comme array Php
/** @return array<int, GeoJsonFeatures */
function readFeatureCollection(string $filename): array {
  if (($geojson = @file_get_contents($filename)) === FALSE)
    die("Erreur: lecture de $filename impossible");
  $featureCollection = json_decode($geojson, true);
  if (json_last_error())
    die("Erreur dans le JSON de $filename : ".json_last_error_msg());
  if (!isset($featureCollection['features']))
    die("Erreur dans le JSON de $filename : features non défini");
  return $featureCollection['features'];
}

$fxx = readFeatureCollection(__DIR__.'/../gegeom/ne10m/admin_0_map_units_FXX.geojson')[0];
$geom = Geometry::fromGeoJSON($fxx['geometry']);
//print_r($geom);
foreach(['Lambert93','Sinusoidal','WorldMercator'] as $crs)
  echo "area $crs=",$geom->proj(function(array $pos) use($crs) { return $crs::proj($pos); })->area()/1e6," km2<br>\n";

/*
area Lambert93=547 551 km2
area Sinusoidal=547 658 km2
La France métropolitaine s'étend sur 551 500 km2 (surface terrestre et eaux intérieures),
547 030 km2 (somme totale des surfaces cadastrales) ou
551 695 km2 (surface géodésique selon l'IGN).

Calcul PgSql:
SELECT gu_a3, ST_Area(geom), ST_Area(geom, false) FROM admin_0_map_units WHERE gu_a3='FXX' 
sur l'ellipsoide:
  547 839 km2
Sur la sphère:
  546 435
*/
