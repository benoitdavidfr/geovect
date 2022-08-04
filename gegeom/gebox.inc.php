<?php
namespace gegeom;
{/*PhpDoc:
name:  gebox.inc.php
title: gebox.inc.php - BBox avec des coord. géographiques ou euclidiennes
functions:
classes:
doc: |
  Attention, la classe GBox ne gère pas convenablement les bbox d'objets à proximité de l'anti-méridien.
  Une nouvelle classe a été développée pour cela, voir /geoapi/shomgt/cat2/gjbox.inc.php
  
  Une BBox (bounding box) est un rectangle englobant défini par ses coins SW et NE.
  La classe abstraite BBox implémente des fonctionnalités génériques valables en coord. géo. comme euclidiennes
  Des classes héritées concrètes GBox et EBox implémentent les fonctionnalités spécifiques respt. aux coord. géo.
  et euclidiennes.
  Comme dans GeoJSON, on distingue la notion de Point, qui est une primitive géométrique, de celle de position
  qui est une structure de données élémentaire pour construire les primitives géométriques.
  Une position est définie comme un array de 2 ou 3 nombres.
  On gère aussi une liste de positions (Lpos) comme array de positions et une liste de listes de positions (LLpos)
  comme array d'array de positions.
journal: |
  4/8/2022:
   - transfert méthodes de shomgt3
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
  18/12/2020:
    - ajout alerte sur la mauvaise gestion des objets aux alentours de l'anti-méridien
  5/5/2019:
    - extension de BBox::__construct() et BBox::bound() pour traiter les LnPos
  30/4/2019:
    - amélioration de la doc
    - modification du résultat de BBox::asArray() pour qu'il soit interprété comme paramètre de BBox::__construct()
  27/4/2019:
    - ajout fonctionnaités, synchro /coordsys/draw avec /gegeom
  7/4/2019:
    - structuration en package indépendant
    - modif de GBox::proj() et EBox::geo() pour améliorer l'indépendance avec coordsys
  9/3/2019:
  - scission de gegeom.inc.php
  7/3/2019:
  - création
includes: [ position.inc.php, ../coordsys/light.inc.php, zoom.inc.php]
*/}
require_once __DIR__.'/position.inc.php';
use \unittest\UnitTest;

/**
 * abstract class BBox - BBox en coord. géo. ou euclidiennes, chaque position définie comme [lon, lat] ou [x, y]
 *
 * Cette classe est abstraite.
 * 2 classes concrètes en héritent, l'une avec des coord. géographiques, l'autre des coord. euclidiennes
 * Il existe une BBox particulière qui peut être interprétée comme vide.
 * A sa création sans paramètre une BBox est vide
*/
abstract class BBox {
  const ErrorParamForNew = 'BBox::ErrorParamForNew';
  const ErrorEmptyBBox = 'BBox::ErrorEmptyBBox';
  
  /** @var TPos $min */
  protected array $min=[]; // coord. min. ou [] pour une bbox vide
  /** @var TPos $max */
  protected array $max=[]; // coord. max. ou [] pour une bbox vide
  
  /**
   * __construct($params=null) - initialise la bbox
   *
   * Soit ne prend pas de paramètre et alors créée une BBox vide,
   * soit prend en paramètre une position,
   * soit prend en paramètre un string dont l'explode donne 2 ou 3 nombres alors interprété comme une position,
   * soit un array de 4 ou 6 nombres, soit un string dont l'explode donne 4 ou 6 nombres, alors interprétés comme 2 pos.
   * soit un LnPos,
   *
   * @param string|TPos|TLPos|TLLPos $param
  */
  function __construct(array|string $param=null) {
    $this->min = [];
    $this->max = [];
    if ($param === null)
      return;
    if (is_array($param) && in_array(count($param), [2,3]) && is_numeric($param[0])) // 1 pos
      $this->bound($param);
    elseif (is_array($param) && (count($param)==4) && is_numeric($param[0])) { // 2 pos
      $this->bound([$param[0], $param[1]]);
      $this->bound([$param[2], $param[3]]);
    }
    elseif (is_array($param) && (count($param)==6) && is_numeric($param[0])) { // 2 pos
      $this->bound([$param[0], $param[1]]);
      $this->bound([$param[3], $param[4]]);
    }
    elseif (is_string($param)) {
      $params = explode(',', $param);
      if (in_array(count($params), [2,3]))
        $this->bound([(float)$params[0], (float)$params[1]]);
      elseif (count($params)==4) {
        $this->bound([(float)$params[0], (float)$params[1]]);
        $this->bound([(float)$params[2], (float)$params[3]]);
      }
      elseif (count($params)==6) {
        $this->bound([(float)$params[0], (float)$params[1]]);
        $this->bound([(float)$params[3], (float)$params[4]]);
      }
      else
       throw new \Exception("Erreur de BBox::__construct(".json_encode($param).")");
    }
    elseif (is_array($param) && !is_numeric($param[0])) { // $param est une liste**n de positions 
      $this->bound($param);
    }
    else
      throw new \SExcept("Erreur de BBox::__construct(".json_encode($param).")", self::ErrorParamForNew);
  }
  
  /**
    * __toString(): string - représentation comme string avec des coord. arrondies
  */
  function __toString(): string {
    if (!$this->min)
      return '[]';
    else {
      $called_class = get_called_class();
      return json_encode([
        round($this->min[0], $called_class::$precision), round($this->min[1], $called_class::$precision),
        round($this->max[0], $called_class::$precision), round($this->max[1], $called_class::$precision)
      ]);
    }
  }
  
  /**
   * empty(): bool - indique si la boite est vide ou non
   */
  function empty(): bool { return (count($this->min) == 0); }
  
  /**
   *
   * posInBBox(TPos $pos): bool - Teste si une position est dans la bbox considérée comme fermée à gauche et ouverte à droite
   *
   * @param TPos $pos
  */
  function posInBBox(array $pos): bool {
    if (!$this->min)
      return true;
    return (($pos[0] >= $this->min[0]) && ($pos[0] < $this->max[0]) && ($pos[1] >= $this->min[1]) && ($pos[1] < $this->max[1]));
  }
  
  /**
   * bound(TLnPos $lnpos): BBox - intègre une liste**n de positions à la BBox et renvoie la BBox modifiée
   *
   * @param TLnPos $lnpos
  */
  function bound(array $lnpos): BBox {
    if (!$lnpos)
      return $this;
    if (!is_numeric($lnpos[0])) { // pos n'est PAS une position mais une liste
      foreach ($lnpos as $ln1pos) {
        $this->bound($ln1pos); // appel récursif sur les éléments de la liste
      }
    }
    elseif (!$this->min) { // $this est indéterminé
      $this->min = $lnpos;
      $this->max = $lnpos;
    } else { // $this est déterminé
      $this->min = [ min($this->min[0], $lnpos[0]), min($this->min[1], $lnpos[1])];
      $this->max = [ max($this->max[0], $lnpos[0]), max($this->max[1], $lnpos[1])];
    }
    return $this;
  }

  /**
   * asArray(): array - représentation comme array Php utilisable dans __construct()
   *
   * @return array<int, float>
  */
  function asArray(): array { return $this->min ? [$this->min[0], $this->min[1], $this->max[0], $this->max[1]] : []; }
  
  function west(): ?float  { return $this->min ? $this->min[0] : null; }
  function south(): ?float { return $this->min ? $this->min[1] : null; }
  function east(): ?float  { return $this->min ? $this->max[0] : null; }
  function north(): ?float { return $this->min ? $this->max[1] : null; }
  
  function setWest(float $val): void  { $this->min[0] = $val; }
  function setSouth(float $val): void { $this->min[1] = $val; }
  function setEast(float $val): void  { $this->max[0] = $val; }
  function setNorth(float $val): void { $this->max[1] = $val; }
  
  /**
   * southWest() - position Sud-Ouest
   *
   * @return TPos
   */
  function southWest(): array { return $this->min ? $this->min : []; }

  /**
   * northEast() - position Nord-Est
   *
   * @return TPos
   */
  function northEast(): array { return $this->min ? $this->max : []; }

  /**
   * northWest() - position Nord-Ouest
   *
   * @return TPos
   */
  function northWest(): array { return $this->min ? [$this->min[0], $this->max[1]] : []; }

  /**
   * southEast() - position Sud-Est
   *
   * @return TPos
   */
  function southEast(): array { return $this->min ? [$this->max[0], $this->min[1]] : []; }
  
  /**
   * center(): TPos - retourne le centre de la BBox ou [] si elle est vide
   *
   * @return TPos
   */
  function center(): array {
    return $this->min ? [($this->min[0]+$this->max[0])/2, ($this->min[1]+$this->max[1])/2] : [];
  }
  
  /**
   * polygon(): TLLPos - retourne un LLpos avec les 5 pos. du polygone de la BBox ou [] si elle est vide
   *
   * @return TLLPos
  */
  function polygon(): array {
    if (!$this->min)
      return [];
    else
      return [[
        [$this->min[0], $this->min[1]],
        [$this->max[0], $this->min[1]],
        [$this->max[0], $this->max[1]],
        [$this->min[0], $this->max[1]],
        [$this->min[0], $this->min[1]],
      ]];
  }
  
  /**
   * union(BBox $b2): BBox - modifie $this pour qu'il soit l'union de $this et de $b2, renvoie $this
   *
   * la BBox vide est un élément neutre pour l'union
  */
  function union(BBox $b2): BBox {
    if (!$b2->min)
      return $this;
    elseif (!$this->min) {
      $this->min = $b2->min;
      $this->max = $b2->max;
      return $this;
    }
    else {
      $this->min[0] = min($this->min[0], $b2->min[0]);
      $this->min[1] = min($this->min[1], $b2->min[1]);
      $this->max[0] = max($this->max[0], $b2->max[0]);
      $this->max[1] = max($this->max[1], $b2->max[1]);
      return $this;
    }
  }
  function unionVerbose(BBox $b2): BBox {
    $u = $this->union($b2);
    echo "BBox::union(b2=$b2)@$this -> $u<br>\n";
    return $u;
  }
  
  /**
   * intersects(BBox $b2): ?BBox - retourne l'intersection des 2 bbox si elles s'intersectent, sinon null
   *
   * génère une erreur si une des 2 BBox est indéfinie. Ne modifie pas $this
  */
  function intersects(BBox $b2): ?BBox {
    if (!$this->min || !$b2->min)
      throw new \SExcept("Erreur intersection avec une des BBox vide", self::ErrorEmptyBBox);
    $xmin = max($b2->min[0], $this->min[0]);
    $ymin = max($b2->min[1], $this->min[1]);
    $xmax = min($b2->max[0], $this->max[0]);
    $ymax = min($b2->max[1], $this->max[1]);
    $called_class = get_called_class();
    if (($xmax >= $xmin) && ($ymax >= $ymin))
      return new $called_class([$xmin, $ymin, $xmax, $ymax]); // @phpstan-ignore-line
    else
      return null;
  }
  function intersectsVerbose(BBox $b2): ?BBox {
    $i = $this->intersects($b2);
    echo "BBox::intersects(b2=$b2)@$this -> ",$i ? 'true' : 'false',"<br>\n";
    return $i;
  }
  
  /**
   * inters(BBox $b2): bool - teste si les 2 bbox s'intersectent
   *
   * génère une erreur si une des 2 BBox est vide.
  */
  function inters(BBox $b2): bool { return $this->intersects($b2) ? true : false; }
  
  /**
   * size(): float - taille max ou lève une exception si la BBox est vide
  */
  abstract function size(): float;
};

//UnitTest::class(__NAMESPACE__, __FILE__, 'BBox'); // Test unitaire de la classe BBox

/**
 * class GBox extends BBox - BBox en coord. géo., chaque position définie comme [lon, lat] en degrés décimaux
 *
 * Par convention, on cherche à respecter:
 *   (-180 <= lon <= 180) && (-90 <= lat <= 90)
 * sauf pour les boites à cheval sur l'antiméridien où (-180 <= lonmin <= 180) && (lonmin <= lonmax <= 180+360 )
 * Cette convention est différente de celle utilisée par GeoJSON.
 * Toutefois, uGeoJSON génère des bbox avec des coord. qqc, y compris lonmin < -180
*/  
class GBox extends BBox {
  const ErrorParamInFromGeoDMd = 'GBox::ErrorParamInFromGeoDMd';
  const ErrorEmptyGBox = 'GBox::ErrorEmptyGBox';
  
  /** nbre de chiffres après la virgule à conserver pour les positions, corresp. approx. à 0.1 mètre */
  static int $precision = 6;
  
  function dLon(): ?float  { return $this->min ? $this->max[0] - $this->min[0] : null; }
  function dLat(): ?float  { return $this->min ? $this->max[1] - $this->min[1] : null; }
  
  /**
   * Crée un GBox à partir d'un rect tel que défini par 2 les positions SW et NE définies en degrés, minutes décimales
   *
   * @param array<string, string> $rect
   */
  static function fromGeoDMd(array $rect): self {
    foreach(['SW','NE'] as $c) {
      if (!isset($rect[$c]))
        throw new \SExcept("Paramètre $c non défini dans GBox::fromGeoDMd()", self::ErrorParamInFromGeoDMd);
      if (is_string($rect[$c])) {
        $rect[$c] = Pos::fromGeoDMd($rect[$c]);
      }
      elseif (!is_array($rect[$c]))
        throw new \SExcept("Paramètre $c mal défini dans GBox::fromGeoDMd()", self::ErrorParamInFromGeoDMd);
    }
    // cas d'un rectangle intersectant l'anti-méridien
    if ($rect['NE'][0] < $rect['SW'][0]) // la longitude Est < longitude West
      $rect['NE'][0] += 360; // la longitude Est est augmentée de 360° et devient comprise entre 180° et 360+180°
    return new GBox([$rect['SW'][0], $rect['SW'][1], $rect['NE'][0], $rect['NE'][1]]);
  }
  
  static function test_fromGeoDMd(): void {
    echo "<table border=1><th></th><th>paramètre</th><th>résultat</th>";
    foreach ([
      ['SW'=> "42°39,93'N - 9°00,93'E", 'NE'=> "43°08,95'N - 9°28,64'E", 'descr'=> "ok DM"],
      ['SW'=> "42°39,93'N - 9°00'E", 'NE'=> "43°08,95'N - 9°28,64'E", 'descr'=> "ok DM ss partie décimale"],
      ['SW'=> "30°S - 153°E", 'NE'=> "8°S - 174°W", 'descr'=> "ok DM ss minute"],
      ['SW'=> [9.0155,42.6655], 'NE'=> [9.477333,43.149167], 'descr'=> "ok dd"],
      ['descr'=> "error"],
      ['SW'=> "42°39,93'N - 9°00,93'", 'NE'=> "43°08,95'N - 9°28,64'E", 'descr'=> "error, no match"],
      ['SW'=> 9],
    ] as $rect) {
      echo "<tr><td>";
      try {
        $gbox = self::fromGeoDMd($rect);
      }
      catch(\SExcept $e) {
        echo "SExcept: {c: ",$e->getSCode(),", m: '",$e->getMessage(),"'}";
        $gbox = null;
      }
      echo "</td><td>"; print_r($rect); echo "</td>";
      echo "<td>$gbox</td></tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Teste si le GBox intersecte l'anti-méridien */
  function intersectsAntiMeridian(): bool { return ($this->east() > 180); }

  static function test_intersectsAntiMeridian(): void {
    foreach ([
      ['SW'=> "42°39,93'N - 9°00,93'E", 'NE'=> "43°08,95'N - 9°28,64'E"],
      ['SW'=> "30°S - 153°E", 'NE'=> "8°S - 174°W"],
    ] as $rect) {
      print_r($rect); echo " -> ",self::fromGeoDMd($rect)->intersectsAntiMeridian() ? 'T' : 'F',"<br>\n";
      echo self::fromGeoDMd($rect)->translate360West(),"<br>\n";
    }
  }
  
  /** taille de la GBox */
  function size(): float {
    if (!$this->min)
      throw new \SExcept("Erreur de GBox::size()  sur une GBox vide", self::ErrorEmptyGBox);
    $cos = cos(($this->max[1] + $this->min[1])/2 / 180 * pi()); // cosinus de la latitude moyenne
    return max($this->dlon() * $cos, $this->dlat());
  }
  
  /** retourne le GBox translaté de 360° vers l'ouest */
  function translate360West(): self {
    return new GBox([$this->west()-360, $this->south(), $this->east()-360, $this->north()]);
  }
  
  static function test___construct(): void {
    foreach([
      null,
      [1,2],
      [1,2,3],
      [1,2,3,4],
      [1,2,3,4,5,6],
      "1,2,3,4",
      "1,2,3,4,5,6",
      [[1,2],[3,4],[5,6]], // LPos
      [[[1,2],[3,4],[5,6]]], // LLPos
      [[[[1,2],[3,4],[5,6]]]], // LLLPos
    ] as $param) {
      $gbox = new GBox($param);
      echo "new GBox(",json_encode($param),")=",$gbox,"<br>\n";
    }
  }
  
  /** Test unitaire de la méthode intersects */
  static function test_intersects(): void {
    // cas d'intersection d'un point avec un rectangle
    $b1 = new GBox([[0, 0], [1, 1]]);
    $b2 = new GBox([0, 0]);
    $b1->intersectsVerbose($b2);
    
    // cas de non intersection entre 2 rectangles
    $b1 = new GBox([[0, 0], [1, 1]]);
    $b2 = new GBox([[2, 2], [3, 3]]);
    $b1->intersectsVerbose($b2);
    
    // cas de non intersection entre 2 rectangles
    $b1 = new GBox([[0, 0], [2, 2]]);
    $b2 = new GBox([[1, 1], [3, 3]]);
    $b1->intersectsVerbose($b2);
  }
  
  /**
   * dist(GBox $b2): float - distance la plus courte entre les positions des 2 GBox
   *
   * génère une erreur si une des 2 est indéterminée"
   * N'est pas une réelle distance entre GBox puisqu'elle peut être nulle sans que les GBox soient identiques.
   * L'unité de distance est le degré de latitude soit approx. 111 km
   * La distance entre 2 positions est définie par le max des écarts des coordonnées en multipliant l'écart en longitude
   * par le cosinus de la latitude moyenne de $this.
  */
  function dist(GBox $b2): float {
    if (!$this->min || !$b2->min)
      throw new \Exception("Erreur de GBox::dist() avec une des GBox indéterminée");
    $xmin = max($b2->min[0],$this->min[0]);
    $ymin = max($b2->min[1],$this->min[1]);
    $xmax = min($b2->max[0],$this->max[0]);
    $ymax = min($b2->max[1],$this->max[1]);
    if (($xmax >= $xmin) && ($ymax >= $ymin))
      return 0;
    else {
      $cos = cos(($this->max[1] + $this->min[1])/2 / 180 * pi()); // cosinus de la latitude moyenne
      return max(($xmin-$xmax),0)*$cos + max(($ymin-$ymax), 0);
    }
  }
  function distVerbose(GBox $b2): float {
    $d = $this->dist($b2);
    echo "GBox::dist(b2=$b2)@$this -> ",$d,"<br>\n";
    return $d;
  }
  static function test_dist(): void {
    $b1 = new GBox([[0,0], [2,2]]);
    $b2 = new GBox([[1,1], [3,3]]);
    $b1->distVerbose($b2);
  }
  
  /**
   * distance(GBox $b2): float - distance entre 2 GBox, nulle ssi les 2 GBox sont identiques"
   */
  function distance(GBox $b2): float {
    if (!$this->min || !$b2->min)
      throw new \Exception("Erreur de GBox::distance() avec une des GBox indéterminée");
    $cos = cos(($this->max[1] + $this->min[1] + $b2->max[1] + $b2->min[1])/2 / 180 * pi()); // cos de la lat. moyenne
    return max(
      abs($b2->min[0] - $this->min[0]),
      abs($b2->min[1] - $this->min[1]) * $cos,
      abs($b2->max[0] - $this->max[0]),
      abs($b2->max[1] - $this->max[1]) * $cos
    );
  }

  /**
   * proj(callable $projPos): EBox - projection d'un GBox prenant en paramètre une fonction de projection d'une position en coord. géo. en coords. projetées
   *
   * La fonction de test est définie dans la classe EBox
  */
  function proj(callable $projPos): EBox {
    return new EBox([
      $projPos($this->min),
      $projPos($this->max)
    ]);
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'GBox'); // Test unitaire de la classe GBox

/**
 * class EBox extends BBox - BBox en coord. projetées euclidiennes, chaque position définie comme [x, y]
 *
 * On fait l'hypothèse que la projection fait correspondre l'axe X à la direction Ouest->Est
 * et l'axe Y à la direction Sud->Nord
*/
class EBox extends BBox {
  /** nbre de chiffres après la virgule à conserver dans les arrondis des coordonnées */
  static int $precision = 1;
  
  function dx(): float  { return $this->min ? $this->max[0] - $this->min[0] : 0; }
  function dy(): float  { return $this->min ? $this->max[1] - $this->min[1] : 0; }
   
  function size(): float {
    if (!$this->min)
      throw new \Exception("Erreur de EBox::size()  sur une EBox indéterminée");
    return max($this->dx(), $this->dy());
  }
  
  /**
   * dist(EBox $b2): float - distance la plus courte entre les positions des 2 EBox
   *
   * génère une erreur si une des 2 est vide
   * N'est pas une réelle distance entre EBox puisqu'elle peut être nulle sans que les EBox soient identiques.
   * L'unité de distance est l'unité du système de coordonnées.
   * La distance entre 2 positions est définie par le max des écarts des coordonnées.
  */
  function dist(EBox $b2): float {
    if (!$this->min || !$b2->min)
      throw new \Exception("Erreur de EBox::dist() avec une des EBox indéterminée");
    $xmin = max($b2->min[0],$this->min[0]);
    $ymin = max($b2->min[1],$this->min[1]);
    $xmax = min($b2->max[0],$this->max[0]);
    $ymax = min($b2->max[1],$this->max[1]);
    if (($xmax >= $xmin) && ($ymax >= $ymin))
      return 0;
    else
      return max(($xmin-$xmax),0) + max(($ymin-$ymax), 0);
  }
  function distVerbose(EBox $b2): float {
    $d = $this->dist($b2);
    echo "EBox::dist(b2=$b2)@$this -> ",$d,"<br>\n";
    return $d;
  }
  static function test_dist(): void {
    $b1 = new EBox([[0,0], [2,2]]);
    $b2 = new EBox([[1,1], [3,3]]);
    $b1->distVerbose($b2);
  }
  
  /**
   * distance(EBox $b2): float - distance entre 2 EBox, nulle ssi les 2 GBox sont identiques"
   */
  function distance(EBox $b2): float {
    if (!$this->min || !$b2->min)
      throw new \Exception("Erreur de EBox::distance() avec une des EBox indéterminée");
    return max(
      abs($b2->min[0] - $this->min[0]),
      abs($b2->min[1] - $this->min[1]),
      abs($b2->max[0] - $this->max[0]),
      abs($b2->max[1] - $this->max[1])
    );
  }
  
  /**
   * area(): float - surface de la EBox en unité du syst. de coord. au carré
   */
  function area(): float { return $this->dx() * $this->dy(); }

  /**
   * covers(EBox $b2): float - taux de couverture de $b2 par $this
  */
  function covers(EBox $b2): float {
    if (!($int = $this->intersects($b2)))
      return 0;
    else
      return $int->area()/$b2->area(); // @phpstan-ignore-line
  }

  /**
   * geo(callable $geoPos): GBox - calcule les coord. géo. d'un EBox en utilisant la fonction anonyme en paramètre
   */
  function geo(callable $geoPos): GBox {
    return new GBox([
      $geoPos($this->min),
      $geoPos($this->max)
    ]);
  }
  static function test_geo(): void {
    require_once __DIR__.'/../coordsys/light.inc.php';
    require_once __DIR__.'/zoom.inc.php';
    $ebox = Zoom::tileEBox(9, [253, 176]);
    echo "$ebox ->geo(WebMercator) = ", $ebox->geo(function(array $pos) { return \WorldMercator::geo($pos); }),"<br>\n";
  }
  static function test_proj(): void {
    echo "<h3>Test de GBox::proj() et non EBox:proj()</h3>\n";
    require_once __DIR__.'/../coordsys/light.inc.php';
    $gbox = new GBox([-2,48, -1,49]);
    echo "$gbox ->proj(WebMercator) = ", $gbox->proj(function(array $pos) { return \WebMercator::proj($pos); }),"<br>\n";
    echo "$gbox ->proj(WorldMercator) = ", $gbox->proj(function(array $pos) { return \WorldMercator::proj($pos); }),"<br>\n";
    echo "$gbox ->proj(Lambert93) = ", $gbox->proj(function(array $pos) { return \Lambert93::proj($pos); }),"<br>\n";
    
    echo "$gbox ->center() = ", json_encode($gbox->center()),"<br>\n";
    echo "UTM::zone($gbox ->center()) = ", $zone = \UTM::zone($gbox->center()),"<br>\n";
    echo "$gbox ->proj(UTM-$zone) = ",
         $eboxutm = $gbox->proj(function(array $pos) use($zone) { return \UTM::proj($zone, $pos); }),"<br>\n";
    echo "$eboxutm ->geo(UTM-$zone) = ",
         $eboxutm->geo(function(array $pos) use($zone) { return \UTM::geo($zone, $pos); }),"<br>\n";
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'EBox'); // Test unitaire de la classe EBox
