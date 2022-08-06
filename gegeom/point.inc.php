<?php
namespace gegeom;
/*PhpDoc:
name:  point.inc.php
title: point.inc.php - définition des classes Point, Segment et MultiPoint
classes:
doc: |
  Fichier concu pour être inclus dans gegeom.inc.php
  La classe Segment est utilisée pour effectuer certains calculs au sein de gegeom
journal: |
  5/8/2022:
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
  9/12/2020:
    - transfert de méthodes Point dans Pos pour passage Php8
  3/5/2019:
    - ajout length(), distance(), distancePointLine(), projPointOnLine()
  30/4/2019:
    - éclatement de gegeom.inc.php
includes: [gegeom.inc.php]
*/
require_once __DIR__.'/gegeom.inc.php';
use \unittest\UnitTest;

/**
 * class Point extends Homogeneous - correspond à une position mais peut aussi être considéré comme un vecteur
 */
class Point extends Homogeneous {
  const ErrorAdd = 'Point::ErrorAdd';
  const ErrorDiff = 'Point::ErrorDiff';
  
  /* @var TPos $coords; */
  protected array $coords; // redéfinition de $coords pour préciser son type pour cette classe
  
  function eltTypes(): array { return ['Point']; }
  function nbPoints(): int { return 1; }

  function isValid(): bool { return Pos::isValid($this->coords); }
  
  function getErrors(): array { return Pos::getErrors($this->coords); }

  /**
   * norm(): float - norme du Point considéré comme vecteur
   */
  function norm(): float { return Pos::norm($this->coords); }
  static function test_norm(): void {
    foreach ([
      [15,20],
      [1,1],
    ] as $pt) {
      $v = new Point($pt);
      echo "norm($v)=",$v->norm(),"<br>\n";
    }
  }

  /**
   * distance(array $pos): float - distance entre $this et $pos
   *
   * @param TPos $pos
   */
  function distance(array $pos): float { return Pos::distance($this->coords, $pos); }
  
  /**
   * add(Point|TPos $v): Point - $this + $v en 2D, $v peut être un Point ou une position
   *
   * @param Point|TPos $v
   */
  function add(Point|array $v): Point {
    if (Pos::is($v))
      return new Point([$this->coords[0] + $v[0], $this->coords[1] + $v[1]]);
    elseif (get_class($v) == __NAMESPACE__.'\Point')
      return new Point([$this->coords[0] + $v->coords[0], $this->coords[1] + $v->coords[1]]);
    else
      throw new \SExcept("Erreur dans Point:add(), paramètre ni position ni Point", self::ErrorAdd);
  }
  
  /**
   * diff(Point|TPos $v): Point - $this - $v en 2D, $v peut être un Point ou une position
   *
   * @param Point|TPos $v
   */
  function diff(Point|array $v): Point {
    if (Pos::is($v))
      return new Point([$this->coords[0] - $v[0], $this->coords[1] - $v[1]]);
    elseif (get_class($v) == __NAMESPACE__.'\Point')
      return new Point([$this->coords[0] - $v->coords[0], $this->coords[1] - $v->coords[1]]);
    else
      throw new \SExcept("Erreur dans Point:diff(), paramètre ni position ni Point", self::ErrorDiff);
  }
  
  /**
   * vectorProduct(Point $v): float - produit vectoriel $this par $v en 2D
  */
  function vectorProduct(Point $v): float { return Pos::vectorProduct($this->coords, $v->coords); }
  
  /**
   * scalarProduct(Point $v): float - produit scalaire $this par $v en 2D
  */
  function scalarProduct(Point $v): float { return Pos::scalarProduct($this->coords, $v->coords); }
  
  /**
   * scalMult(float $scal): Point  - multiplication de $this considéré comme un vecteur par un scalaire
  */
  function scalMult(float $scal): Point { return new Point([$this->coords[0] * $scal, $this->coords[1] * $scal]); }

  /**
   * distancePointLine(TPos $a, TPos $b): float - distance signée du point courant à la droite définie par les 2 pos a et b
   *
   *  La distance est positive si le point est à gauche de la droite AB et négative s'il est à droite
   *
   * @param TPos $a
   * @param TPos $b
   */
  function distancePointLine(array $a, array $b): float { return Pos::distancePosLine($this->coords, $a, $b); }
  static function test_distancePointLine(): void {
    foreach ([
      [[1,0], [0,0], [1,1]],
      [[1,0], [0,0], [0,2]],
    ] as $lpts) {
      $p = new Point($lpts[0]);
      echo '(',$p,")->distancePointLine([",implode(',',$lpts[1]),'],[',implode(',',$lpts[2]),"])->",
        $p->distancePointLine($lpts[1],$lpts[2]),"<br>\n";
    }
  }
    
  /**
   * projPointOnLine(array $a, array $b): float - projection du point sur la droite A,B, renvoie u"
   *
   * Projection P' d'un point P sur une droite A,B
   * Les parametres sont les 3 points P, A, B
   * Renvoit u / P' = A + u * (B-A).
   * Le point projeté est sur le segment ssi u est dans [0 .. 1].
   *
   * @param TPos $a
   * @param TPos $b
   */
  function projPointOnLine(array $a, array $b): float { return Pos::distancePosLine($this->coords, $a, $b); }
  static function test_projPointOnLine(): void {
    foreach ([
      [[1,0], [0,0], [1,1]],
      [[1,0], [0,0], [0,2]],
    ] as $lpts) {
      $p = new Point($lpts[0]);
      echo '(',$p,")->projPointOnLine(",LnPos::toString($lpts[1]),',',LnPos::toString($lpts[2]),")->",
          $p->projPointOnLine($lpts[1], $lpts[2]),"<br>\n";
    }
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
    return ['points'=> [$this], 'lineStrings'=> [], 'polygons'=> []];
  }

  function draw(Drawing $drawing, array $style=[]): void {}
};

UnitTest::class(__NAMESPACE__, __FILE__, 'Point'); // Test unitaire de la classe Point

/**
 * class Segment - Segment composé de 2 positions ; considéré comme orienté de la première vers la seconde
 *
 * On considère le segment comme fermé sur sa première position et ouvert sur la seconde
 * Cela signifie que la première position appartient au segment mais pas la seconde.
*/
class Segment {
  /** @var array<int, TPos> $tab liste de 2 positions */
  protected array $tab;
  
  /**
   * __construct(TPos $pos0, TPos $pos1) - initialise le segment par 2 positions
   *
   * @param TPos $pos0
   * @param TPos $pos1
   */
  function __construct(array $pos0, array $pos1) { $this->tab = [$pos0, $pos1]; }
  
  /**
   * asArray(): array - représentation comme array des 2 positions définissant le segment
   *
   * @return array<string, string|array<int, TPos>>
   */
  function asArray(): array { return ['type'=> 'Segment', 'coordinates'=> $this->tab]; }
  
  /**
   * __toString(): string - représentation comme string des 2 positions définissant le sgement
   */
  function __toString(): string { return json_encode($this->asArray()); }
  
  /**
   * length(): float - longueur du segment définie par la distance euclidienne
  */
  function length(): float { return Pos::distance($this->tab[0], $this->tab[1]); }
  
  /**
   * vector(): Point - vecteur correspondant à $tab[1] - $tab[0] représenté par un Point
  */
  function vector(): Point { return new Point(Pos::diff($this->tab[1], $this->tab[0])); }
  
  /**
   * intersects(Segment $seg): array - intersection entre 2 segments
   *
   * Je considère les segments ferméq sur la première position et ouvert sur la seconde.
   * Cela signifie qu'une intersection ne peut avoir lieu sur la seconde position
   * Si les segments ne s'intersectent pas alors retourne []
   * S'ils s'intersectent en un point alors retourne le dictionnaire
   *   ['point'=> le point d'intersection, 'u'=> l'abscisse sur le premier segment, 'v'=> l'abscisse sur le second]
   * S'ils s'intersectent en un segment alors retourne ['segment'=> segment d'intesection]
   *
   * @return array<mixed>
  */
  function intersects(Segment $seg): array {
    $a = $this->tab;
    $b = $seg->tab;
    if (max($a[0][0],$a[1][0]) < min($b[0][0],$b[1][0])) return [];
    if (max($b[0][0],$b[1][0]) < min($a[0][0],$a[1][0])) return [];
    if (max($a[0][1],$a[1][1]) < min($b[0][1],$b[1][1])) return [];
    if (max($b[0][1],$b[1][1]) < min($a[0][1],$a[1][1])) return [];
    
    $va = $this->vector();
    $vb = $seg->vector();
    $ab = new Segment($a[0], $b[0]);
    $ab = $ab->vector(); // vecteur b0 - a0
    $pvab = $va->vectorProduct($vb);
    if ($pvab == 0) { // utiliser un epsilon ???
      if ((new Point($b[0]))->distancePointLine($a[0], $a[1]) == 0) { // utiliser un epsilon ???
        $b0 = (new Point($b[0]))->projPointOnLine($a[0], $a[1]);
        $b1 = (new Point($b[1]))->projPointOnLine($a[0], $a[1]);
        //echo "b0=$b0, b1=$b1<br>\n";
        if ($b0 < $b1) { // les 2 segs sont dans le même sens
          if (($b0 >= 1) || ($b1 <= 0))
            return []; //segments ne se superposent pas
          else {
            $b0 = $b0 < 0 ? 0 : $b0;
            $c0 = $va->scalMult($b0)->add($a[0]);
            $b1 = $b1 > 1 ? 1 : $b1;
            $c1 = $va->scalMult($b1)->add($a[0]);
            return ['segment'=> new Segment($c0->coords(), $c1->coords())];
          }
        }
        else
          return $this->intersects(new Segment($b[1], $b[0]));
      }
      else
        return []; // droites parallèles non confondues
    }
    $u = $ab->vectorProduct($vb) / $pvab;
    $v = $ab->vectorProduct($va) / $pvab;
    if (($u >= 0) && ($u < 1) && ($v >= 0) && ($v < 1))
      return [ 'point'=> $va->scalMult($u)->add($a[0]),
               //'posb'=> $vb->scalMult($v)->add($b[0]),
               'u'=>$u, 'v'=>$v,
             ];
    else
      return [];
  }
  static function test_intersects(): void {
    $a = new Segment([0,0], [10,0]);
    foreach ([
      ['b'=> new Segment([0,-5],[10,5]), 'result'=> true],
      ['b'=> new Segment([0,0],[10,5]), 'result'=> true],
      ['b'=> new Segment([0,-5],[10,-5]), 'result'=> false],
      ['b'=> new Segment([0,-5],[20,0]), 'result'=> false],
      ['b'=> new Segment([5,0],[20,0]), 'result'=> 'segment'],
      ['b'=> new Segment([20,0],[5,0]), 'result'=> 'segment'],
    ] as $test) {
      $b = $test['b'];
      echo "$a ->intersects($b) -> ",json_encode($a->intersects($b)),
           " / ", ($test['result']==='segment') ? 'segment' : ($test['result'] ? 'point' : 'vide'),"<br>\n";
    }
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'Segment'); // Test unitaire de la classe Segment

/**
 * class MultiPoint extends Homogeneous - Une liste éventuellement vide de positions
 */
class MultiPoint extends Homogeneous {
  /** @var TLPos $coords */
  protected array $coords;

  function eltTypes(): array { return $this->coords ? ['Point'] : []; }
  function nbPoints(): int { return count($this->coords); }
  
  function isValid(): bool {
    foreach ($this->coords as $pos)
      if (!(new Point($pos))->isValid())
        return false;
    return true;
  }
  
  function getErrors(): array { return LPos::getErrors($this->coords); }

  /**
   * simpleGeoms(): array - Retourne une structure standardisée commune à ttes les géométries
   *
   * Retourne un array composé d'exactement 3 champs points, lineStrings et polygons contenant chacun
   * une liste évt. vide d'objets respectivement Point, LineString et Polygon.
   *
   * @return array<string, array<int, Homogeneous>>
   */
  function simpleGeoms(): array {
    $points = [];
    foreach ($this->coords as $pos)
      $points[] = new Point($pos);
    return ['points'=> $points, 'lineStrings'=> [], 'polygons'=> []];
  }
  
  function draw(Drawing $drawing, array $style=[]): void {}
    
  // méthode de test global de la classe
  static function test_MultiPoint(): void {
    $mpt = Geometry::fromGeoJSON(['type'=>'MultiPoint', 'coordinates'=>[]]);
    $mpt = Geometry::fromGeoJSON(['type'=>'MultiPoint', 'coordinates'=>[[0,0],[1,1]]]);
    echo "$mpt ->center() = ",json_encode($mpt->center()),"<br>\n";
    echo "$mpt ->aPos() = ",json_encode($mpt->aPos()),"<br>\n";
    echo "$mpt ->gbox() = ",$mpt->gbox(),"<br>\n";
    echo "$mpt ->proj() = ",$mpt->proj(function(array $pos) { return $pos; }),"<br>\n";
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'MultiPoint'); // Test unitaire de la classe MultiPoint
