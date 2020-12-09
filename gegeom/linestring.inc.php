<?php
namespace gegeom;
{/*PhpDoc:
name:  linestring.inc.php
title: linestring.inc.php - définition les classes LineString et MultiLineString
classes:
doc: |
  Fichier concu pour être inclus dans gegeom.inc.php
journal: |
  3/5/2019:
    - ajout: filter(), isClosed(), simplify()
  30/4/2019:
    - éclatement de gegeom.inc.php
includes: [gegeom.inc.php]
*/}
require_once __DIR__.'/gegeom.inc.php';
use \unittest\UnitTest;

{/*PhpDoc: classes
name: LineString
title: class LineString extends Homogeneous - contient au moins 2 positions
methods:
*/}
class LineString extends Homogeneous {
  // $coords contient une liste de positions (LPos)
  function eltTypes(): array { return ['LineString']; }
  
  function geoms(): array { return array_map(function(array $pos) { return new Point($pos); }, $this->coords); }
  
  /*PhpDoc: methods
  name: __toString
  title: "function __toString(): string - génère la réprésentation string WKT"
  */
  function __toString(): string { return ($this->type()).LnPos::wkt($this->coords); }

  function isValid(): bool { return LPos::isValid($this->coords) && (count($this->coords) >= 2); }
  
  function getErrors(): array {
    return array_merge(
      LPos::getErrors($this->coords),
      (count($this->coords) < 2) ? ["La ligne devrait comporter au moins 2 points"] : []);
  }
  
  function length(): float {
    return array_sum(array_map(function(Segment $seg): float { return $seg->length(); }, $this->segs()));
  }
  
  /*PhpDoc: methods
  name:  areaOfRing
  title: "function areaOfRingv(): float - renvoie la surface de l'anneau constitué par la polyligne dans le CRS courant"
  doc: |
    La surface est positive ssi la géométrie est orientée dans le sens des aiguilles de la montre (sens trigo inverse).
    Cette règle est conforme à la définition GeoJSON:
      A linear ring MUST follow the right-hand rule with respect to the area it bounds,
      i.e., exterior rings are clockwise, and holes are counterclockwise.
  */
  function areaOfRing(): float {
    $area = 0.0;
    $geoms = $this->geoms();
    $n = count($geoms);
    $pt0 = $geoms[0];
    for ($i=1; $i<$n-1; $i++) {
      $area += $geoms[$i]->diff($pt0)->vectorProduct($geoms[$i+1]->diff($pt0));
    }
    return -$area/2;
  }
  static function test_areaOfRing() {
    foreach ([
      [[0,0],[0,1],[1,0],[0,0]],
      //'LINESTRING(0 0,1 0,1 1,0 1,0 0)',
      //'LINESTRING(10 10,11 10,11 11,10 11,10 10)',
    ] as $coords) {
      $ls = new LineString($coords);
      echo "areaOfRing($ls)=",$ls->areaOfRing(),"<br>\n";
    }
  }
  
  /*PhpDoc: methods
  name:  filter
  title: "function filter(int $precision=9999): ?Homogeneous - renvoie un nouveau LineString filtré supprimant les points successifs identiques"
  doc: |
    Les coordonnées sont arrondies avec le nbre de chiffres significatifs défini par le paramètre precision
    ou par la précision par défaut.
    Un filtre sans arrondi n'a pas de sens.
  */
  function filter(int $precision=9999): ?Homogeneous {
    $cclass = get_called_class();
    $lpos = LPos::filter($this->coords, $precision == 9999 ? self::$precision : $precision);
    if (count($lpos) >= 2)
      return new $cclass($lpos);
    else
      return null;
  }
  
  /*PhpDoc: methods
  name:  filter
  title: "function efilter(): Homogeneous - renvoie un nouveau LineString filtré supprimant les points successifs identiques en utilisant eprecision (A REVOIR)"
  */
  function efilter(): Homogeneous { $cclass = get_called_class(); return new $cclass(LPos::filter($this->coords, self::$eprecision)); }
  
  /* Dév interrompu
  function clip(GBox $window): MultiLineString {
    throw new \Exception("Dév interrompu");
  }*/
    
  /*PhpDoc: methods
  name:  isClosed
  title: "function isClosed(): bool - teste la fermeture de la polyligne"
  */
  function isClosed(): bool { return ($this->coords[0] == $this->coords[count($this->coords)-1]); }
  static function test_isClosed() {
    foreach ([
      'LINESTRING(0 0,100 100)',
      'LINESTRING(0 0,100 100,0 0)',
      ] as $lsstr) {
        $ls = Geometry::fromWkt($lsstr);
        echo $ls,($ls->isClosed()?" est fermée":" n'est pas fermée"),"<br>\n";
    }
  }

  /*PhpDoc: methods
  name:  segs
  title: "segs(): array - liste des segments constituant la polyligne"
  */
  function segs(): array {
    $segs = [];
    $posPrec = null;
    foreach ($this->coords as $pos) {
      if (!$posPrec)
        $posPrec = $pos;
      else {
        $segs[] = new Segment($posPrec, $pos);
        $posPrec = $pos;
      }
    }
    return $segs;
  }
  
  function simplify(float $distTreshold): ?Homogeneous {
    $class = get_called_class();
    return ($lpos = LPos::simplify($this->coords, $distTreshold)) ? new $class($lpos) : null;
  }
  static function test_simplify() {
    $ls = new LineString([[0,0],[0.1,0],[0,1],[2,2]]);
    echo "simplify($ls, 1)=",$ls->simplify(1),"<br>\n";
    echo "simplify($ls, 0.5)=",$ls->simplify(0.5),"<br>\n";
    $ls = new LineString([[0,0],[0.1,0],[0,1],[2,2],[0,0]]);
    echo "simplify($ls, 1)=",$ls->simplify(1),"<br>\n";
  }
  
  function draw(Drawing $drawing, array $style=[]) {
    $drawing->polyline($this->coords, $style);
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'LineString'); // Test unitaire de la classe LineString

{/*PhpDoc: classes
name: MultiLineString
title: class MultiLineString extends Geometry - contient une liste de liste de positions, chaque liste de positions en contient au moins 2
*/}
class MultiLineString extends Homogeneous {
  // $coords contient une liste de listes de positions (LLPos)
  function eltTypes(): array { return $this->coords ? ['LineString'] : []; }
  
  function geoms(): array {
    return array_map(function(array $lpos) { return new LineString($lpos); }, $this->coords);
  }
  static function test_geoms() {
    foreach ([
      [[[0,0],[0,1],[1,0],[0,0]]],
      [[[0,0],[0,1],[1,0],[0,0]],[[0,0],[0,1],[1,0],[0,0]]],
    ] as $coords) {
      $mls = new MultiLineString($coords);
      echo "geoms($mls)=[",implode(',',$mls->geoms()),"]<br>\n";
    }
  }
  
  /*PhpDoc: methods
  name: __toString
  title: "function __toString(): string - génère la réprésentation string WKT"
  */
  function __toString(): string { return ($this->type()).LnPos::wkt($this->coords); }

  function isValid(): bool {
    foreach ($this->geoms() as $ls)
      if (!$ls->isValid())
        return false;
    return true;
  }
  
  function getErrors(): array {
    $errors = [];
    foreach ($this->geoms() as $i => $ls) {
      if ($lsErrors = $ls->getErrors())
        $errors[] = ["Erreur sur la ligne $i", $lsErrors];
    }
    return $errors;
  }
  
  function length(): float {
    return array_sum(array_map(function(array $lpos) { return (new LineString($lpos))->length(); }, $this->coords));
  }
  static function test_length() {
    foreach ([
      [[[0,0],[0,1],[1,0],[0,0]]],
    ] as $coords) {
      $mls = new MultiLineString($coords);
      echo "length($mls)=",$mls->length(),"<br>\n";
    }
  }
  
  function filter(int $precision=9999): ?Homogeneous {
    if ($precision == 9999)
      $precision == self::$precision;
    $cclass = get_called_class();
    $coords = [];
    foreach ($this->coords as $lpos) {
      $lpos = LPos::filter($lpos, $precision);
      if (count($lpos) >= 2)
        $coords[] = $lpos;
    }
    if ($coords)
      return new $cclass($coords);
    else
      return null;
  }
  
  // Dév interrompu
  /*function clip(GBox $window): MultiLineString {
    $llpos = [];
    foreach ($this->coords as $lpos) {
      $ls = new LineString($lpos);
      $mls = $ls->clip($window);
      if ($mls)
        $llpos = array_merge($llpos, $mls->coords());
    }
    return new MultiLineString($llpos);
  }*/
    
  /*static function haggregate(array $elts) - NON UTILISE {
    $coords = [];
    foreach ($elts as $elt)
      $coords[] = $elt->coords;
    return new MultiLineString($coords);
  }*/

  function draw(Drawing $drawing, array $style=[]) {
    foreach ($this->coords as $coords)
      $drawing->polyline($coords, $style);
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'MultiLineString'); // Test unitaire de la classe MultiLineString
