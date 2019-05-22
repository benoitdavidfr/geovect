<?php
namespace gegeom;
{/*PhpDoc:
name:  point.inc.php
title: point.inc.php - définition des classes Point, Segment et MultiPoint
classes:
doc: |
  Fichier concu pour être inclus dans gegeom.inc.php
  La classe Segment est utilisée pour effectuer certains calculs au sein de gegeom
journal: |
  3/5/2019:
    - ajout length(), distance(), distancePointLine(), projPointOnLine()
  30/4/2019:
    - éclatement de gegeom.inc.php
includes: [gegeom.inc.php]
*/}
require_once __DIR__.'/gegeom.inc.php';
use \unittest\UnitTest;

{/*PhpDoc: classes
name: Point
methods:
title: class Point extends Homogeneous - correspond à une position mais peut aussi être considéré comme un vecteur
*/}
class Point extends Homogeneous {
  // $coords contient une position (Pos)
  function eltTypes(): array { return ['Point']; }
  function __toString(): string { return 'Point('.implode(' ',$this->coords).')'; }
  function wkt(): string { return 'POINT('.implode(' ',$this->coords).')'; }
  function geoms(): array { return []; }
  function nbPoints(): int { return 1; }

  function isValid(): bool { return Pos::isValid($this->coords); }
  
  function getErrors(): array { return Pos::getErrors($this->coords); }

  /*PhpDoc: methods
  name:  norm
  title: "function norm(): float - renvoie la norme du vecteur"
  */
  function norm(): float { return sqrt($this->scalarProduct($this)); }
  static function test_norm() {
    foreach ([
      [15,20],
      [1,1],
    ] as $pt) {
      $v = new Point($pt);
      echo "norm($v)=",$v->norm(),"<br>\n";
    }
  }

  /*PhpDoc: methods
  name:  distance
  title: "function distance(array $pos): float - distance entre $this et $pos"
  */
  function distance(array $pos): float { return $this->diff($pos)->norm(); }
  
  /*PhpDoc: methods
  name:  add
  title: "function add($v): Point - $this + $v en 2D, $v peut être un Point ou une position"
  */
  function add($v): Point {
    if (Pos::is($v))
      return new Point([$this->coords[0] + $v[0], $this->coords[1] + $v[1]]);
    elseif (get_class($v) == __NAMESPACE__.'\Point')
      return new Point([$this->coords[0] + $v->coords[0], $this->coords[1] + $v->coords[1]]);
    else
      throw new \Exception("Erreur dans Point:add(), paramètre ni position ni Point");
  }
  
  /*PhpDoc: methods
  name:  diff
  title: "function diff($v): Point - $this - $v en 2D, $v peut être un Point ou une position"
  */
  function diff($v): Point {
    if (Pos::is($v))
      return new Point([$this->coords[0] - $v[0], $this->coords[1] - $v[1]]);
    elseif (get_class($v) == __NAMESPACE__.'\Point')
      return new Point([$this->coords[0] - $v->coords[0], $this->coords[1] - $v->coords[1]]);
    else
      throw new \Exception("Erreur dans Point:diff(), paramètre ni position ni Point");
  }
    
  /*PhpDoc: methods
  name:  vectorProduct
  title: "function vectorProduct(Point $v): float - produit vectoriel $this par $v en 2D"
  */
  function vectorProduct(Point $v): float {
    return $this->coords[0] * $v->coords[1] - $this->coords[1] * $v->coords[0];
  }
  
  /*PhpDoc: methods
  name:  scalarProduct
  title: "function scalarProduct(Point $v): float - produit scalaire $this par $v en 2D"
  */
  function scalarProduct(Point $v): float {
    return $this->coords[0] * $v->coords[0] + $this->coords[1] * $v->coords[1];
  }
  static function test_scalarProduct() {
    foreach ([
      [[15,20], [20,15]],
      [[1,0], [0,1]],
      [[4,0], [0,3]],
      [[1,0], [1,0]],
    ] as $lpts) {
      $v0 = new Point($lpts[0]);
      $v1 = new Point($lpts[1]);
      echo "($v0)->vectorProduct($v1)=",$v0->vectorProduct($v1),"<br>\n";
      echo "($v0)->scalarProduct($v1)=",$v0->scalarProduct($v1),"<br>\n";
    }
  }

  /*PhpDoc: methods
  name:  scalMult
  title: "function scalMult(float $scal): Point  - multiplication de $this considéré comme un vecteur par un scalaire"
  */
  function scalMult(float $scal): Point { return new Point([$this->coords[0] * $scal, $this->coords[1] * $scal]); }

  /*PhpDoc: methods
  name:  distancePointLine
  title: "function distancePointLine(array $a, array $b): float - distance signée du point courant à la droite définie par les 2 positions a et b"
  doc: |
    La distance est positive si le point est à gauche de la droite AB et négative s'il est à droite
    # Distance signee d'un point P a une droite orientee definie par 2 points A et B
    # la distance est positive si P est a gauche de la droite AB et negative si le point est a droite
    # Les parametres sont les 3 points P, A, B
    # La fonction retourne cette distance.
    # --------------------
    sub DistancePointDroite
    # --------------------
    { my @ab = (@_[4] - @_[2], @_[5] - @_[3]); # vecteur B-A
      my @ap = (@_[0] - @_[2], @_[1] - @_[3]); # vecteur P-A
      return pvect (@ab, @ap) / Norme(@ab);
    }
  */
  function distancePointLine(array $a, array $b): float {
    $ab = (new Point($b))->diff($a);
    $ap = $this->diff($a);
    if ($ab->norm() == 0)
      throw new \Exception("Points A et B confondus et donc droite non définie");
    return $ab->vectorProduct($ap) / $ab->norm();
  }
  static function test_distancePointLine() {
    foreach ([
      [[1,0], [0,0], [1,1]],
      [[1,0], [0,0], [0,2]],
    ] as $lpts) {
      $p = new Point($lpts[0]);
      echo '(',$p,")->distancePointLine([",implode(',',$lpts[1]),'],[',implode(',',$lpts[2]),"])->",
        $p->distancePointLine($lpts[1],$lpts[2]),"<br>\n";
    }
  }
  
  /*PhpDoc: methods
  name:  projPointOnLine
  title: "function projPointOnLine(array $a, array $b): float - projection du point sur la droite A,B, renvoie u"
  doc: |
    # Projection P' d'un point P sur une droite A,B
    # Les parametres sont les 3 points P, A, B
    # Renvoit u / P' = A + u * (B-A).
    # Le point projete est sur le segment ssi u est dans [0 .. 1].
    # -----------------------
    sub ProjectionPointDroite
    # -----------------------
    { my @ab = (@_[4] - @_[2], @_[5] - @_[3]); # vecteur B-A
      my @ap = (@_[0] - @_[2], @_[1] - @_[3]); # vecteur P-A
      return pscal(@ab, @ap)/(@ab[0]**2 + @ab[1]**2);
    }
  */
  function projPointOnLine(array $a, array $b): float {
    $ab = (new Point($b))->diff($a);
    $ap = $this->diff($a);
    return $ab->scalarProduct($ap) / $ab->scalarProduct($ab);
  }
  static function test_projPointOnLine() {
    foreach ([
      [[1,0], [0,0], [1,1]],
      [[1,0], [0,0], [0,2]],
    ] as $lpts) {
      $p = new Point($lpts[0]);
      echo '(',$p,")->projPointOnLine(",LnPos::toString($lpts[1]),',',LnPos::toString($lpts[2]),")->",
          $p->projPointOnLine($lpts[1], $lpts[2]),"<br>\n";
    }
  }

  function draw(Drawing $drawing, array $style=[]) {}
};

UnitTest::class(__NAMESPACE__, __FILE__, 'Point'); // Test unitaire de la classe Point

{/*PhpDoc: classes
name: Segment
title: class Segment - Segment composé de 2 positions ; considéré comme orienté de la première vers la seconde
methods:
doc: |
  On considère le segment comme fermé sur sa première position et ouvert sur la seconde
  Cela signifie que la première position appartient au segment mais pas la seconde.
*/}
class Segment {
  private $tab; // 2 positions: [[number]]
  
  /*PhpDoc: methods
  name:  __construct
  title: "function __construct(array $pos0, array $pos1) - initialise un segment par 2 positions"
  */
  function __construct(array $pos0, array $pos1) { $this->tab = [$pos0, $pos1]; }
  
  /*PhpDoc: methods
  name:  asArray
  title: "function asArray(): array - représentation comme array des 2 positions définissant le sgement"
  */
  function asArray(): array { return $this->tab; }
  
  /*PhpDoc: methods
  name:  __toString
  title: "function __toString(): string - représentation comme string des 2 positions définissant le sgement"
  */
  function __toString(): string { return json_encode($this->tab); }
  
  /*PhpDoc: methods
  name:  length
  title: "function length(): float - longueur du segment définie par la distance euclidienne"
  */
  function length(): float {
    $dx = $this->tab[1][0] - $this->tab[0][0];
    $dy = $this->tab[1][1] - $this->tab[0][1];
    return sqrt($dx*$dx + $dy*$dy);
  }
  
  /*PhpDoc: methods
  name:  vector
  title: "function vector(): Point - vecteur correspondant à $tab[1] - $tab[0] représenté par un Point"
  */
  function vector(): Point {
    return new Point([$this->tab[1][0] - $this->tab[0][0], $this->tab[1][1] - $this->tab[0][1]]);
  }
  
  /*PhpDoc: methods
  name:  intersects
  title: "function intersects(Segment $seg): array - intersection entre 2 segments"
  doc: |
    Je considère les segments fermé sur la première position et ouvert sur la seconde.
    Cela signifie qu'une intersection ne peut avoir lieu sur la seconde position
    Si les segments ne s'intersectent pas alors retourne []
    S'ils s'intersectent en un point alors retourne le dictionnaire
      ['point'=> le point d'intersection, 'u'=> l'abscisse sur le premier segment, 'v'=> l'abscisse sur le second]
    S'ils s'intersectent en un segment alors retourne ['segment'=> intesection]
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

{/*PhpDoc: classes
name: MultiPoint
title: class MultiPoint extends Homogeneous - Une liste éventuellement vide de positions
*/}
class MultiPoint extends Homogeneous {
  // $coords contient une liste de positions (LPos)
  function eltTypes(): array { return $this->coords ? ['Point'] : []; }
  function geoms(): array { return array_map(function(array $pos) { return new Point($pos); }, $this->coords); }
  function nbPoints(): int { return count($this->coords); }
  
  function isValid(): bool {
    foreach ($this->geoms() as $pt)
      if (!$pt->isValid())
        return false;
    return true;
  }
  
  function getErrors(): array { return LPos::getErrors($this->coords); }
  
  /*static function haggregate(array $elts) - NON UTILISE {
    $coords = [];
    foreach ($elts as $elt)
      $coords[] = $elt->coords;
    return new MultiPoint($coords);
  }*/

  function draw(Drawing $drawing, array $style=[]) {}
    
  // méthode de test global de la classe
  static function test_MultiPoint() {
    $mpt = Geometry::fromGeoJSON(['type'=>'MultiPoint', 'coordinates'=>[]]);
    $mpt = Geometry::fromGeoJSON(['type'=>'MultiPoint', 'coordinates'=>[[0,0],[1,1]]]);
    echo "$mpt ->center() = ",json_encode($mpt->center()),"<br>\n";
    echo "$mpt ->aPos() = ",json_encode($mpt->aPos()),"<br>\n";
    echo "$mpt ->bbox() = ",$mpt->bbox(),"<br>\n";
    echo "$mpt ->proj() = ",$mpt->proj(function(array $pos) { return $pos; }),"<br>\n";
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'MultiPoint'); // Test unitaire de la classe MultiPoint
