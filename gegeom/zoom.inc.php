<?php
namespace gegeom;
{/*PhpDoc:
name:  zoom.inc.php
title: zoom.inc.php - définition de la classe Zoom regroupant l'intelligence autour des niveaux de zoom
classes:
journal: |
  7/4/2019:
    - structuration en package indépendant
  9/3/2019:
  - scission depuis gegeom.inc.php
  7/3/2019:
  - création
includes: [gebox.inc.php, ../coordsys/light.inc.php]
*/}
require_once __DIR__.'/gebox.inc.php';

{/*PhpDoc: classes
name: Zoom
title: class Zoom - classe regroupant l'intelligence autour des niveaux de zoom
*/}
class Zoom {
  static $maxZoom = 18; // zoom max utilisé notamment pour les points
  // $size0 est la circumférence de la Terre en mètres
  // correspond à 2 * PI * a où a = 6 378 137.0 est le demi-axe majeur de l'ellipsoide WGS 84
  static $size0 = 20037508.3427892476320267 * 2;
  
  // taille du pixel en mètres en fonction du zoom
  static function pixelSize(int $zoom) { return self::$size0 / 256 / pow(2, $zoom); }
  
  // niveau de zoom adapté à la visualisation d'une géométrie définie par la taille de son GBox
  static function zoomForGBoxSize(float $size): int {
    if ($size) {
      $z = log(360.0 / $size, 2);
      //echo "z=$z<br>\n";
      return min(round($z), self::$maxZoom);
    }
    else
      return self::$maxZoom;
  }
  
  // taille d'un degré en mètres
  static function sizeOfADegreeInMeters() { return self::$size0 / 360.0; }
  
  // calcule la EBox en coord. WebMercator. de la tuile (z,x,y), ixy = [x,y]
  static function tileEBox(int $z, array $ixy): EBox {
    $base = self::$size0 / 2;
    $x0 = - $base;
    $y0 =   $base;
    $size = self::$size0 / pow(2, $z);
    return new EBox([
      $x0 + $size * $ixy[0], $y0 - $size * ($ixy[1]+1),
      $x0 + $size * ($ixy[0]+1), $y0 - $size * $ixy[1]
    ]);
  }
  
  // no de tuile contenant un point pour un zoom
  static function tileNum(int $z, array $pos): array {
    $size = self::$size0 / pow(2, $z);
    //echo "size=$size<br>\n";
    $base = self::$size0 / 2;
    $x0 = - $base;
    $y0 =   $base;
    $ix = floor(($pos[0] - $x0) / $size);
    $iy = floor(($y0 - $pos[1]) / $size);
    return [$ix, $iy];
  }
};

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de la classe Zoom
  require_once __DIR__.'/../coordsys/light.inc.php';
  if (!isset($_GET['test']))
    echo "<a href='?test=Zoom'>Test unitaire de la classe Zoom</a><br>\n";
  elseif ($_GET['test']=='Zoom') {
    for($zoom=0; $zoom <= 21; $zoom++)
      printf("zoom=%d pixelSize=%.2f m<br>\n", $zoom, Zoom::pixelSize($zoom));
    printf("sizeOfADegree=%.3f km<br>\n", Zoom::sizeOfADegreeInMeters()/1000);
    $webMercatorGeo = function(array $pos) { return \WebMercator::geo($pos); };
    echo "Zoom::tileEBox(0,[0,0])=", Zoom::tileEBox(0,[0,0]),
         "<br>\n ->geo('WebMercator') -> ", Zoom::tileEBox(0, [0, 0])->geo($webMercatorGeo),"<br>\n";
    echo "Zoom::tileEBox(9, [253, 176])=",Zoom::tileEBox(9, [253, 176]),
         "<br>\n ->geo('WebMercator') -> ",Zoom::tileEBox(9, [253, 176])->geo($webMercatorGeo),"<br>\n";
  }
}
