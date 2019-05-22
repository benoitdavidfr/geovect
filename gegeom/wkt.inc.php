<?php
namespace gegeom;
/*PhpDoc:
name:  wkt.inc.php
title: wkt.inc.php - traitement des WKT
classes:
doc: |
  Implémentation performante de la transformation WKT en GeoJSON utilisée par Geometry::fromWkt()
  Il est préférable de ne pas utiliser cette classe directemrnt.
journal: |
  21/5/2019:
    - correction d'un bug sur MULTIPOLYGON
  3/5/2019:
    - améliorations
    - ajout du traitement de GEOMETRYCOLLECTION
  14/8/2018:
    - ajout du paramètre shift pour générer les objets de l'autre côté de l'anti-méridien
  12/8/2018
    - première version
*/
/*PhpDoc: classes
name:  Wkt
title: "class Wkt - classe statique de gestion des WKT"
methods:
doc: |
  La méthode Wkt::geojson() ne crée pas de copie du $wkt afin d'optimiser la gestion mémoire.
  De plus, elle utilise ni preg_match() ni preg_replace().
  Les fichiers polygon.wkt.txt et multipolygon.wkt.txt sont utilisés pour le test unitaire
*/
class Wkt {
  /*PhpDoc: methods
  name:  geojson
  title: "static function geojson(string $wkt, float $shift=0.0): array - convertit à la volée un WKT en GeoJSON"
  doc: |
    shift vaut 0.0, -360.0 ou +360.0 pour générer les objets de l'autre côté de l'anti-méridien
  */
  static function geojson(string $wkt, float $shift=0.0): array {
    $pos = 0;
    $gj = self::parse($wkt, $pos, $shift);
    if ((strlen($wkt) <> $pos) && trim(substr($wkt, $pos)))
      throw new \Exception("erreur Wkt2GeoJson::convert(), wkt=".substr($wkt,$pos)."$\n");
    return $gj;
  }
  
  private static function parse(string $wkt, int &$pos, float $shift): array {
    //echo "parse(wkt-pos=",substr($wkt, $pos, 20),")<br>\n";
    if (substr($wkt, $pos, 6)=='POINT(') {
      $pos += 6;
      return ['type'=>'Point', 'coordinates'=> self::parseLPos($wkt, $pos, $shift)[0]];
    }
    elseif (substr($wkt, $pos, 11)=='MULTIPOINT(') {
      $pos += 11;
      return ['type'=>'MultiPoint', 'coordinates'=> self::parseLPos($wkt, $pos, $shift)];
    }
    elseif (substr($wkt, $pos, 11)=='LINESTRING(') {
      $pos += 11;
      return ['type'=>'LineString', 'coordinates'=> self::parseLPos($wkt, $pos, $shift)];
    }
    elseif (substr($wkt, $pos, 16)=='MULTILINESTRING(') {
      $pos += 16;
      return ['type'=>'MultiLineString', 'coordinates'=> self::parseLLPos($wkt, $pos, $shift)];
    }
    elseif (substr($wkt, $pos, 8)=='POLYGON(') {
      $pos += 8;
      return ['type'=>'Polygon', 'coordinates'=> self::parseLLPos($wkt, $pos, $shift)];
    }
    elseif (substr($wkt, $pos, 13)=='MULTIPOLYGON(') {
      $pos += 13;
      return ['type'=>'MultiPolygon', 'coordinates'=> self::parseLLLPos($wkt, $pos, $shift)];
    }
    elseif (substr($wkt, $pos, 19)=='GEOMETRYCOLLECTION(') {
      $pos += 19;
      $geometries = [];
      $geometries[] = self::parse($wkt, $pos, $shift);
      while (substr($wkt, $pos, 1)==',') {
        $pos++;
        $geometries[] = self::parse($wkt, $pos, $shift);
      }
      $pos++;
      return ['type'=>'GeometryCollection', 'geometries'=> $geometries];
    }
    else
      throw new \Exception("erreur Wkt2GeoJson::parse(), wkt=".substr($wkt,$pos));
  }
  
  // consomme une liste de LLPos + parenthèse fermante: ((nn nn,nn nn),(nn nn,nn nn)),((nn nn,nn nn),(nn nn,nn nn)))
  private static function parseLLLPos(string $wkt, int &$pos, float $shift): array {
    $pos++;
    $lllpos = [self::parseLLPos($wkt, $pos, $shift)];
    while (substr($wkt, $pos, 1) == ',') {
      $pos = $pos + 2;
      $lllpos[] = self::parseLLPos($wkt, $pos, $shift);
    }
    if (substr($wkt,$pos,1) == ')') {
      $pos++;
      return $lllpos;
    }
    else {
      echo "left parseLLLPos 3=",substr($wkt,$pos),"<br>\n";
      throw new \Exception("Erreur Wkt2GeoJson::parseLLLPos ligne ".__LINE__);
    }
  }
  
  // consomme une liste de LPos + parenthèse fermante mais sans ouvrante (nn nn,nn nn),(nn nn,nn nn))
  private static function parseLLPos(string $wkt, int &$pos, float $shift): array {
    $llpos = [self::parseLPos($wkt, $pos, $shift)];
    while (substr($wkt, $pos, 1) == ',') {
      $pos++;
      $llpos[] = self::parseLPos($wkt, $pos, $shift);
    }
    if (substr($wkt, $pos, 1) == ')') {
      $pos++;
      return $llpos;
    }
    else {
      echo "Erreur left parseLLPos 2 sur '",substr($wkt, $pos),"'<br>\n";
      throw new \Exception("ligne ".__LINE__);
    }
  }
  
  // consomme une liste de positions avec évent '(' + ')': (?nn nn,nn nn)
  private static function parseLPos(string $wkt, int &$pos, float $shift): array {
    if (substr($wkt, $pos, 1) == '(')
      $pos++;
    $lpos = [self::parsePos($wkt, $pos, $shift)];
    while (substr($wkt, $pos, 1) == ',') {
      $pos++;
      $lpos[] = self::parsePos($wkt, $pos, $shift);
    }
    if (substr($wkt, $pos, 1)==')') {
      $pos++;
      return $lpos;
    }
    else
      throw new \Exception("Erreur dans Wkt2GeoJson::parseLPos sur wkt='".substr($wkt, $pos)."'");
  }
  
  // consomme une position, pos pointe à la fin sur le séparateur après la position
  private static function parsePos(string $wkt, int &$pos, float $shift): array {
    $len = strcspn($wkt, ' ),', $pos);
    if (($len < 1) || (substr($wkt, $pos+$len, 1)<>' '))
      throw new \Exception("Erreur dans Wkt2GeoJson::parsePos sur x wkt='".substr($wkt, $pos)."'");
    $x = substr($wkt, $pos, $len);
    //echo "len=$len, wkt='$wkt', pos=$pos, x=$x\n"; die();
    $pos += $len+1;
    $len = strcspn($wkt, ' ),', $pos);
    if ($len < 1)
      throw new \Exception("Erreur dans Wkt2GeoJson::parsePos sur y wkt='".substr($wkt, $pos)."'");
    $y = substr($wkt, $pos, $len);
    $pos += $len;
    //echo "\nsuite='",substr($wkt, $pos),"'<br>\n"; //die();
    if (in_array(substr($wkt, $pos, 1), [',',')'])) {
      //echo "parsePos -> [$x, $y]<br>\n";
      return [(float)$x + $shift, (float)$y];
    }
    elseif (substr($wkt, $pos, 1)==' ') {
      $pos++;
      $len = strcspn($wkt, ' ),', $pos);
      $z = substr($wkt, $pos, $len);        
      $pos += $len;
      //echo "parsePos -> [$x, $y, $z]<br>\n";
      return [(float)$x + $shift, (float)$y, (float)$z];
    }
    else
      throw new \Exception("Erreur dans Wkt2GeoJson::parsePos sur wkt='".substr($wkt, $pos)."'");
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


// Test unitaire de la classe Wkt2GeoJson

$wkts = [
  'MULTIPOLYGON(((6.186 49.464,6.658 49.202,6.186 49.464)),((8.746 42.628,9.39 43.01,9.56 42.152,9.23 41.38,8.776 41.584,8.544 42.257,8.746 42.628)))',
  'MULTIPOLYGON(((6.186 49.464,6.658 49.202,8.099 49.018,7.594 48.333,7.467 47.621,7.192 47.45,6.737 47.542,6.769 47.288,6.037 46.726,6.023 46.273,6.5 46.43,6.844 45.991,6.802 45.709,7.097 45.333,6.75 45.029,7.008 44.255,7.55 44.128,7.435 43.694,6.529 43.129,4.557 43.4,3.1 43.075,2.986 42.473,1.827 42.343,0.702 42.796,0.338 42.58,-1.503 43.034,-1.901 43.423,-1.384 44.023,-1.194 46.015,-2.226 47.064,-2.963 47.57,-4.492 47.955,-4.592 48.684,-3.296 48.902,-1.617 48.644,-1.933 49.776,-0.989 49.347,1.339 50.127,1.639 50.947,2.514 51.149,2.658 50.797,3.123 50.78,3.588 50.379,4.286 49.907,4.799 49.985,5.674 49.529,5.898 49.443,6.186 49.464)),((8.746 42.628,9.39 43.01,9.56 42.152,9.23 41.38,8.776 41.584,8.544 42.257,8.746 42.628)))',
  'POINT(-59.696 -51.966 100)',
  'POINT(-59.696 -51.966 100 50)',
  'LINESTRING(10,141 -2.6 100,142.735 -3.289,10)',
  'LINESTRING(10 ,141 -2.6 100,142.735 -3.289,10)',
  'LINESTRING(141 -2.6 100,142.735 -3.289,144.584 -3.861,145.273 -4.374,145.83 -4.876,145.982 -5.466,147.648 -6.084,147.891 -6.614,146.971 -6.722,147.192 -7.388,148.085 -8.044,148.734 -9.105,149.307 -9.071,149.267 -9.514,150.039 -9.684,149.739 -9.873,150.802 -10.294,150.691 -10.583,150.028 -10.652,149.782 -10.393,148.923 -10.281,147.913 -10.13,147.135 -9.492,146.568 -8.943,146.048 -8.067,144.744 -7.63,143.897 -7.915,143.286 -8.245,143.414 -8.983,142.628 -9.327,142.068 -9.16,141.034 -9.118,140.143 -8.297,139.128 -8.096,138.881 -8.381,137.614 -8.412,138.039 -7.598,138.669 -7.32,138.408 -6.233,137.928 -5.393,135.989 -4.547,135.165 -4.463,133.663 -3.539,133.368 -4.025,132.984 -4.113,132.757 -3.746,132.754 -3.312,131.99 -2.821,133.067 -2.46,133.78 -2.48,133.696 -2.215,132.232 -2.213,131.836 -1.617,130.943 -1.433,130.52 -0.938,131.868 -0.695,132.38 -0.37,133.986 -0.78,134.143 -1.152,134.423 -2.769,135.458 -3.368,136.293 -2.307,137.441 -1.704,138.33 -1.703,139.185 -2.051,139.927 -2.409,141 -2.6)',
  'POLYGON((162.119 -10.483 100,162.399 -10.826,161.7 -10.82,161.32 -10.205,161.917 -10.447,162.119 -10.483))',
  'MULTIPOLYGON(((162.119 -10.483,162.399 -10.826,161.7 -10.82,161.32 -10.205,161.917 -10.447,162.119 -10.483)),((162.119 -10.483,162.399 -10.826,161.7 -10.82,161.32 -10.205,161.917 -10.447,162.119 -10.483)))',
  'GEOMETRYCOLLECTION(POLYGON((153042 6799129,153043 6799174,153063 6799199),(1 1,2 2)),POLYGON((154613 6803109.5,154568 6803119,154538.89999999999 6803145)),LINESTRING(153042 6799129,153043 6799174,153063 6799199),LINESTRING(154613 6803109.5,154568 6803119,154538.89999999999 6803145),POINT(153042 6799129),POINT(153043 6799174),POINT(153063 6799199))',
  'polygon.wkt.txt',
  'multipolygon.wkt.txt',
];
header('Content-type: application/json');
//header('Content-type: text/plain');
echo "[\n";
$nb = 0;
foreach ($wkts as $wkt) {
  if ($nb++<>0)
    echo ",\n";
  if (is_file($wkt))
    $wkt = file_get_contents($wkt);
  try {
    $geojson = Wkt::geojson($wkt);
    echo '  ',json_encode($geojson, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
  catch(\Exception $e) {
    echo '  ',json_encode(
      ['type'=> 'Error', 'message'=> $e->getMessage(), 'source'=> $wkt],
      JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
}
echo "\n]\n";
