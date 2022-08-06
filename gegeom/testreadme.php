<?php
namespace gegeom;
/*PhpDoc:
name: testreadme.php
title: testreadme.php - Vérifie que les méthodes listées dans le README sont appelables sur des différents types d'objets concrets
doc: |
  La documentation README est une doc d'utilisation de la bibliothèque et ne présente pas les méthodes effectivement définies
  mais celles utilisables.
  Il est donc utile de vérifier qu'elles le sont bien.
includes: [gegeom.inc.php, drawing.inc.php]
*/
require_once __DIR__.'/gegeom.inc.php';
require_once __DIR__.'/drawing.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>testreadme</title></head><body>\n";

echo "<h2>La classe abstraite BBox</h2>\n";
foreach (['GBox','EBox'] as $class) {
  $class = __NAMESPACE__.'\\'.$class;
  $bbox = new $class([0,0]);
  "bbox=$bbox<br>\n";
  $bbox->empty();
  $bbox->posInBBox([0,0]);
  $bbox->bound([1,1]);
  $bbox->center();
  $bbox->polygon();
  $bbox->union(new $class);
  $bbox->intersects(new $class([1,1]));
  $bbox->size();
  $bbox->dist(new $class([1,1]));
  $bbox->distance(new $class([1,1]));
  //$bbox->isIncludedIn(new $class([1,1]));
}

$gbox = new GBox([0,0]);
$gbox->proj(function($pos) { return $pos; });

$ebox = new EBox([0,0]);
$ebox->area();
$ebox->covers(new EBox([1,1]));
$ebox->geo(function($pos) { return $pos; });


echo "<h2>La classe abstraite Geometry</h2>\n";
Geometry::fromGeoJSON(['type'=>'Point','coordinates'=>[0,0]]);
Geometry::fromWkt('POINT(0 0)');
foreach ([
    'Point'=>[0,0],'MultiPoint'=>[[0,0]],
    'LineString'=>[[0,0]],'MultiLineString'=>[[[0,0]]],
    'Polygon'=>[[[0,0]]],'MultiPolygon'=>[[[[0,0]]]],
    'GeometryCollection'=>[new Point([0,0])],
  ] as $class=> $value) {
    $class = __NAMESPACE__.'\\'.$class;
    $geom = new $class($value);
    $geom->coords();
    $geom->asArray();
    "geom=$geom<br>\n";
    $geom->wkt();
    $geom->isValid();
    $geom->getErrors();
    $geom->proj2D();
    $geom->center();
    $geom->aPos();
    $geom->gbox();
    $geom->ebox();
    $geom->proj(function($pos) { return $pos; });
    $geom->nbPoints();
    $geom->length();
    $geom->area();
    $geom->filter();
    $geom->simplify(0);
    $geom->draw(new DumbDrawing(0, 0, new EBox), []);
}

echo "<h2>La classe Point</h2>\n";
$pt = new Point([0,0]);
$pt->distance([0,0]);
$pt->add([0,0]);
$pt->diff([0,0]);
$pt->norm();
$pt->vectorProduct($pt);
$pt->scalarProduct($pt);
$pt->scalMult(0);
$pt->distancePointLine([0,0], [0,1]);
$pt->projPointOnLine([0,0], [0,1]);

echo "<h2>La classe LineString</h2>\n";
$ls = new LineString([[0,0],[0,1]]);
$ls->isClosed();

echo "<h2>La classe Polygon</h2>\n";
$pol = new Polygon([[[0,0],[0,1],[1,0],[0,0]]]);
$pol->posInPolygon([0,0]);
$pol->inters($pol);

echo "<h2>La classe MultiPolygon</h2>\n";
$mpol = new MultiPolygon([[[[0,0],[0,1],[1,0],[0,0]]]]);
$mpol->inters($mpol);

die("Fin Ok<br>\n");