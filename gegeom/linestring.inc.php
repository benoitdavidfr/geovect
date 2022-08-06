<?php
namespace gegeom;
{/*PhpDoc:
name:  linestring.inc.php
title: linestring.inc.php - définition les classes LineString et MultiLineString
classes:
doc: |
  Fichier concu pour être inclus dans gegeom.inc.php
journal: |
  5/8/2022:
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
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
  /* @var TLPos $coords; */
  protected array $coords; // redéfinition de $coords pour préciser son type pour cette classe

  function eltTypes(): array { return ['LineString']; }

  function isValid(): bool { return LPos::isValid($this->coords) && (count($this->coords) >= 2); }
  
  /**
   * getErrors(): array - renvoie l'arbre des erreurs ou [] s'il n'y en a pas
   *
   * @return array<mixed>
   */
  function getErrors(): array {
    return array_merge(
      LPos::getErrors($this->coords),
      (count($this->coords) < 2) ? ["La ligne devrait comporter au moins 2 points"] : []);
  }
  
  function length(): float {
    //return array_sum(array_map(function(Segment $seg): float { return $seg->length(); }, $this->segs()));
    return LPos::length($this->coords);
  }
  
  /**
  * areaOfRingv(): float - renvoie la surface de l'anneau constitué par la polyligne dans le CRS courant
  *
  *  La surface est positive ssi la géométrie est orientée dans le sens des aiguilles de la montre (sens trigo inverse).
  *  Cette règle est conforme à la définition GeoJSON:
  *    A linear ring MUST follow the right-hand rule with respect to the area it bounds,
  *    i.e., exterior rings are clockwise, and holes are counterclockwise.
  */
  function areaOfRing(): float { return LPos::areaOfRing($this->coords); }
  static function test_areaOfRing(): void {
    foreach ([
      [[0,0],[0,1],[1,0],[0,0]],
      [[0,0],[1,0],[0,1],[0,0]],
      [[0,0],[0,1],[1,1],[1,0],[0,0]],
      //'LINESTRING(0 0,1 0,1 1,0 1,0 0)',
      //'LINESTRING(10 10,11 10,11 11,10 11,10 10)',
    ] as $coords) {
      $ls = new LineString($coords);
      echo "areaOfRing($ls)=",$ls->areaOfRing(),"<br>\n";
    }
  }
  
  /**
   * filter(int $precision=9999): ?LineString - renvoie un nouveau LineString filtré supprimant les points successifs identiques
   *
   * Les coordonnées sont arrondies avec le nbre de chiffres significatifs défini par le paramètre precision
   * ou par la précision par défaut.
   * Un filtre sans arrondi n'a pas de sens.
  */
  function filter(int $precision=9999): ?LineString {
    $cclass = get_called_class();
    $lpos = LPos::filter($this->coords, $precision == 9999 ? self::$precision : $precision);
    if (count($lpos) >= 2)
      return new $cclass($lpos);
    else
      return null;
  }
  
  /**
   * efilter(): LineString - renvoie un nouveau LineString filtré supprimant les points successifs identiques en utilisant ePrecision
   */
  function efilter(): ?LineString { return $this->filter(self::$ePrecision); }
  
  /**
   * isClosed(): bool - teste la fermeture de la polyligne
   */
  function isClosed(): bool { return ($this->coords[0] == $this->coords[count($this->coords)-1]); }
  static function test_isClosed(): void {
    foreach ([
      'LINESTRING(0 0,100 100)',
      'LINESTRING(0 0,100 100,0 0)',
      ] as $lsstr) {
        $ls = Geometry::fromWkt($lsstr);
        echo $ls,($ls->isClosed()?" est fermée":" n'est pas fermée"),"<br>\n";
    }
  }

  /**
   * segs(): array - liste des segments constituant la polyligne
   *
   * @return array<int, Segment>
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
  
  function simplify(float $distTreshold): ?LineString {
    $class = get_called_class();
    return ($lpos = LPos::simplify($this->coords, $distTreshold)) ? new $class($lpos) : null;
  }
  static function test_simplify(): void {
    $ls = new LineString([[0,0],[0.1,0],[0,1],[2,2]]);
    echo "simplify($ls, 1)=",$ls->simplify(1),"<br>\n";
    echo "simplify($ls, 0.5)=",$ls->simplify(0.5),"<br>\n";
    $ls = new LineString([[0,0],[0.1,0],[0,1],[2,2],[0,0]]);
    echo "simplify($ls, 1)=",$ls->simplify(1),"<br>\n";
  }
  
  /**
   * simpleGeoms(): array - Retourne une structure standardisée commune à ttes les géométries
   *
   * Retourne un array composé d'exactement 3 champs points, lineStrings et polygons contenant chacun
   * une liste évt. vide d'objets respectivement Point, LineString et Polygon.
   *
   * @return array<string, array<int, Homogeneous>>
   */
  function simpleGeoms(): array {
    return ['points'=> [], 'lineStrings'=> [$this], 'polygons'=> []];
  }
  
  function draw(Drawing $drawing, array $style=[]): void {
    $drawing->polyline($this->coords, $style);
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'LineString'); // Test unitaire de la classe LineString

/**
 * class MultiLineString extends Geometry - contient une liste de liste de positions, chaque liste de positions en contient au moins 2
*/
class MultiLineString extends Homogeneous {
  /* @var TLLPos $coords; */
  protected array $coords; // redéfinition de $coords pour préciser son type pour cette classe

  function eltTypes(): array { return $this->coords ? ['LineString'] : []; }

  function isValid(): bool {
    foreach ($this->coords as $lpos) {
      if (!(new LineString($lpos))->isValid())
        return false;
    }
    return true;
  }
  
  function getErrors(): array {
    $errors = [];
    foreach ($this->coords as $i => $lpos) {
      if ($lsErrors = (new LineString($lpos))->getErrors())
        $errors[] = ["Erreur sur la ligne $i", $lsErrors];
    }
    return $errors;
  }
  
  function length(): float {
    return array_sum(array_map('gegeom\LPos::length', $this->coords));
  }
  static function test_length(): void {
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
    $coords = [];
    foreach ($this->coords as $lpos) {
      $lpos = LPos::filter($lpos, $precision);
      if (count($lpos) >= 2)
        $coords[] = $lpos;
    }
    if ($coords) {
      $cclass = get_called_class();
      return new $cclass($coords);
    }
    else
      return null;
  }
    
  /**
   * simpleGeoms(): array - Retourne une structure standardisée commune à ttes les géométries
   *
   * Retourne un array composé d'exactement 3 champs points, lineStrings et polygons contenant chacun
   * une liste évt. vide d'objets respectivement Point, LineString et Polygon.
   *
   * @return array<string, array<int, Homogeneous>>
   */
  function simpleGeoms(): array {
    $lineStrings = [];
    foreach ($this->coords as $lpos)
      $lineStrings[] = new LineString($lpos);
    return ['points'=> [], 'lineStrings'=> $lineStrings, 'polygons'=> []];
  }
  
  function draw(Drawing $drawing, array $style=[]): void {
    foreach ($this->coords as $coords)
      $drawing->polyline($coords, $style);
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'MultiLineString'); // Test unitaire de la classe MultiLineString
