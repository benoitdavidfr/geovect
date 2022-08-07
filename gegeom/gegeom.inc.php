<?php
namespace gegeom;
/*PhpDoc:
name:  gegeom.inc.php
title: gegeom.inc.php - primitives géométriques utilisant des coordonnées géographiques ou euclidiennes
functions:
classes:
doc: |
  Ce fichier définit la classe abstraite Geometry sur-classe des 7 classes par type de géométrie GeoJSON
  (https://tools.ietf.org/html/rfc7946).
  Une géométrie GeoJSON peut être facilement créée en décodant le JSON en Php par json_decode()
  puis en apppelant la méthode Geometry::fromGeoJSON().
  Le fichier définit aussi:
    - les fonctions générales asArray() et json_encode()
      
  A FAIRE:
    - améliorer asArray pour qu'il fonctionne sur une classe pour laquelle la méthode asArray() n'est pas définie
  
journal: |
  5/8/2022:
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
  9/12/2020:
    - passage en Php 8
  6/5/2019:
    - ajout Homogeneous::filter()
  5/5/2019:
    - création de la classe LnPos
    - réécriture de plusieurs algorithmes pour les rendre plus génériques
    - définition d'un espace de noms
    - définition de la classe Homogeneous pour éviter que GeometryCollection porte un champ coords
  4/5/2019:
    - rédaction du README
    - révision de la logique du dessin
  3/5/2019:
    - ajout Geometry::fromWkt() et Geometry::wkt()
  30/4/2019:
    - éclatement en 5 fichiers
    - amélioration de la doc
    - ajout qqs fonctionnalités
    - utilisation de array_map() pour gérer les listes
  9/3/2019:
    - ajout de nombreuses fonctionnalités
  7/3/2019:
    - création
includes:
  - gebox.inc.php
  - point.inc.php
  - linestring.inc.php
  - polygon.inc.php
  - geomcoll.inc.php
  - wkt.inc.php
*/
require_once __DIR__.'/gebox.inc.php';
require_once __DIR__.'/wkt.inc.php';

use \unittest\UnitTest;

{/* fonction asArray() ne semble pas nécessaire 
 * asArray($val) - transforme récursivement une valeur en aray Php pur sans objet, utile pour l'afficher avec json_encode()
 *
 * Les objets rencontrés doivent avoir une méthode asArray() qui décompose l'objet en array des propriétés exposées
 *
 * @param mixed $val
 * @return array<mixed>|string|int|float 
 *
function asArray(mixed $val): array|string|int|float {
  //echo "AsArray(",json_encode($val),")<br>\n";
  if (is_array($val)) {
    foreach ($val as $k => $v) {
      $val[$k] = asArray($v);
    }
    return $val;
  }
  elseif (is_object($val)) {
    return asArray($val->asArray());
  }
  else
    return $val;
}
*/}
{/* fonction json_encode() ne semble pas nécessaire 
 * json_encode($val): string - génère un json en traversant les objets qui savent se décomposer en array par asArray()
*
function json_encode(mixed $val): string {
  return \json_encode(asArray($val), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
UnitTest::function(__FILE__, 'json_encode', function(): void { // Tests unitaires de json_encode
  echo "json_encode=",json_encode(new LineString([[0,0],[1,1]])),"<br>\n";
  echo "\json_encode=",\json_encode(new LineString([[0,0],[1,1]])),"<br>\n";
});
*/}

/**
 * abstract class Geometry - Geometry GeoJSON et quelques opérations
 *
 * Sur-classe des classes concrètes implémentant chaque type GeoJSON.
 * On distingue GeometryCollection des autres types appelés homogènes et regroupés sous la super-classe Homogeneous.
 * Un style peut être associé à une Geometry inspiré de https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0
 * Par ailleurs, cette bibliothèque peut être étendue en préfixant les noms des classes par une chaine possibilité prévue
 * dans fromGeoJson() ; cette possibilité EST A VALIDER !!!
 * Enfin, cette bibliothèque est indépendante des changements de systèmes de coordonnées qu'il est possible d'effectuer
 * en utilisant la méthode proj() qui prend en paramètre une fonction anonyme de TPos vers TPos.
*/
abstract class Geometry {
  const ErrorFromGeoJSON = 'Geometry::ErrorFromGeoJSON';
  
  /*
   * Construction de l'objet
   */
  
  /**
   * fromGeoJSON(TGeoJsonGeometry $geom, string $prefix=''): Geometry - construit une géométrie à partir de sa représentation GeoJSON décodée comme array Php
   *
   * L'utilisation du prefix permet de créer des classes préfixées par ce préfix afin de gérer une extension
   * de la bibliothèque.
   *
   * @param TGeoJsonGeometry $geom
   * @return THomogeneous|GeometryCollection
   */
  static function fromGeoJSON(array $geom, string $prefix=''): Geometry {
    if (in_array($geom['type'] ?? null, Homogeneous::TYPES) && isset($geom['coordinates'])) {
      $type = __NAMESPACE__.'\\'.$prefix.$geom['type'];
      return new $type($geom['coordinates']);
    }
    elseif ((($geom['type'] ?? null)=='GeometryCollection') && isset($geom['geometries'])) {
      return new GeometryCollection(array_map('self::fromGeoJSON', $geom['geometries']));
    }
    else
      throw new \SExcept("Erreur de Geometry::fromGeoJSON(".json_encode($geom).")", self::ErrorFromGeoJSON);
  }
  // Méthode de test dans la classe TestsOfGeometry
  
  /**
   * fromWkt(string $wkt, string $prefix=''): Geometry - crée une géométrie à partir d'un WKT
   *
   * génère une erreur si le WKT ne correspond pas à une géométrie
   *
   * @return THomogeneous|GeometryCollection
   */
  static function fromWkt(string $wkt, string $prefix=''): Geometry {
    return self::fromGeoJSON(Wkt::geojson($wkt), $prefix);
  }
  // Méthode de test dans la classe TestsOfGeometry
  
  /*** vérification de la validité de l'objet ***/
  
  /**
   * isValid(): bool - teste la validité de l'objet
   */
  abstract function isValid(): bool;
  
  /**
   * getErrors(): array - renvoie l'arbre des erreurs ou [] s'il n'y en a pas"
   *
   * La réponse est :
   *   {errors} ::= [ {error} ]
   *   {error} ::= {string} // erreur élémentaire
   *           ::= [ {string}, {errors}] // erreur complexe
   * @return array<mixed>
   */
  abstract function getErrors(): array;
  // Méthode de test dans la classe TestsOfGeometry
  
  /*** Exposition du contenu de l'objet ***/
  
  /**
   * asArray(): array - génère la représentation array Php du GeoJSON
   *
   * @return TGeoJsonGeometry
   */
  abstract function asArray(): array;
  
  /**
   * __toString(): string - génère la représentation string GeoJSON
   */
  function __toString(): string { return json_encode($this->asArray()); }
  
  // récupère le type en supprimant l'espace de nom
  function type(): string { $c = get_called_class(); return substr($c, strrpos($c, '\\')+1); }
  
  /**
   * retourne la liste des types élémentaires ('Point','LineString','Polygon') contenus dans la géométrie
   *
   * @return array<int, string>
   */
  abstract function eltTypes(): array;
  
  /**
   * wkt(): string - génère la réprésentation WKT
   */
  abstract function wkt(): string;
  
  /** @return TLnPos */
  function coords(): array {
    $array = $this->asArray();
    if ($array['type']=='GeometryCollection')
      return [];
    else
      return $array['coordinates'];
  }
  
  /*** Traitements sur l'objet ***/
  
  /**
   * gbox(): GBox - renvoie la GBox de la géométrie considérée comme géographique
   */
  abstract function gbox(): GBox;
  
  /**
   * ebox(): EBox - renvoie la EBox de la géométrie considérée comme euclidienne
   */
  function ebox(): EBox { return new EBox($this->gbox()->asArray()); }
  
  /**
   * proj(callable $projPos): Geometry - projète chaque position d'une géométrie en utilisant la fonction anonyme
   *
   * @return self
   */
  abstract function proj(callable $projPos): self;
  
  /**
   * center(): TPos - centre d'une géométrie considérée en coord. géo.
   *
   * @return TPos
   */
  abstract function center(): array;
  
  /**
   * simpleGeoms(): array - Retourne une structure standardisée commune à ttes les géométries
   *
   * Retourne un array composé d'exactement 3 champs points, lineStrings et polygons contenant chacun
   * une liste évt. vide d'objets respectivement Point, LineString et Polygon.
   *
   * @return array<string, array<int, Point|LineString|Polygon>>
   */
  abstract function simpleGeoms(): array;

  /**
   * draw(Drawing $drawing, array $style=[]): void - Dessine l'objet dans le dessin avec le style
   *
   * @param TStyle $style
   */
  abstract function draw(Drawing $drawing, array $style=[]): void;
};

// UnitTest::class(__NAMESPACE__, __FILE__, 'Geometry'); // RIEN à tester dans la classe Geometry

/**
 * class Homogeneous extends Geometry - Geometry GeoJSON homogène
 *
 * Sur-classe des classes concrètes implémentant les types GeoJSON homogènes.
 * Les coordonnées sont conservées en liste**n de positions comme en GeoJSON, et pas structurées avec des objets.
 * Par défaut, la géométrie est en coordonnées géographiques mais les classes peuvent aussi être utilisées
 * avec des coordonnées euclidiennes en utilisant parfois des méthodes soécifiques préfixées par e.
 */
abstract class Homogeneous extends Geometry {
  const TYPES = ['Point','LineString','Polygon','MultiPoint','MultiLineString','MultiPolygon'];
  
  static int $precision = 6; // nbre de chiffres après la virgule à conserver pour les coord. géo.
  static int $ePrecision = 1; // nbre de chiffres après la virgule à conserver pour les coord. euclidiennes

  /**
   * coordonnées ou Positions, stockées comme TPos, TLPos, ... en fonction de la sous-classe
   *
   * @var TLnPos $coords
   */
  protected array $coords;
  
  /**
   * __construct(TLnPos $coords, array $style=[]) - fonction d'initialisation commune à toutes les géométries homogènes
   *
   * @param TLnPos $coords
   */
  function __construct(array $coords) { $this->coords = $coords; }
  
  /**
   * récupère les coordonnées
   *
   * @return TLnPos
   */
  function coords(): array { return $this->coords; }
  
  /**
   * asArray(): array - génère la représentation array Php GeoJSON
   *
   * @return TGeoJsonGeometry
   */
  function asArray(): array { return ['type'=> $this->type(), 'coordinates'=> $this->coords]; }
  
  /**
   * wkt(): string - génère la réprésentation WKT de la géométrie
   */
  function wkt(): string { return strtoupper($this->type()).LnPos::wkt($this->coords); }
  
  /**
   * proj2D(): self - projection 2D, supprime l'éventuelle 3ème coordonnée, renvoie un nouvel objet de même type
   *
   * @return THomogeneous
  */
  function proj2D(): self { return self::proj(function(array $pos) { return [$pos[0], $pos[1]]; }); }
  
  /**
   * center(): TPos - centre d'une géométrie considérée en coord. géo.
   *
   * @return TPos
   */
  function center(): array { return LnPos::center($this->coords, self::$precision); }
    
  /**
   * ecenter(): TPos - centre d'une géométrie cosnidérée en coord. euclidiennes
   *
   * @return TPos
  */
  function ecenter(): array { return LnPos::center($this->coords, self::$ePrecision); }
  
  /**
   * nbreOfPos(): int - retourne le nobre de positions
   */
  function nbreOfPos(): int { return LnPos::count($this->coords); }
  
  /**
   * aPos(): array - retourne une position de la géométrie
   *
   * @return TPos
   */
  function aPos(): array { return LnPos::aPos($this->coords); }
  
  /**
   * gbox(): GBox - renvoie la GBox de la géométrie considérée comme géographique
   */
  function gbox(): GBox { return new GBox($this->coords); }
  
  /**
   * proj(callable $projPos): Homogeneous - projète les positions de la géométrie par une fonction anonyme TPos -> TPos
   *
   * crée un nouvel objet
   *
   * @return THomogeneous
   */
  function proj(callable $projPos): self {
    $class = get_called_class();
    return new $class(LnPos::projLn($this->coords, $projPos)); // @phpstan-ignore-line
  }
  
  /**
   * nbPoints(): int - nombre d'objets Point contenus dans la géométrie
   */
  function nbPoints(): int { return 0; }
  
  /**
   * length(): float - longueur dans le système de coordonnées courant
   */
  function length(): float { return 0; }
  
  /**
   * area($options=[]): float - surface dans le système de coordonnées courant
   *
   * Par défaut, l'extérieur et les intérieurs tournent dans des sens différents.
   * La surface est positive ssi l'extérieur tourne dans le sens trigonométrique inverse, <0 sinon.
   * Cette règle est conforme à la définition GeoJSON.
   * Si l'option 'noDirection' vaut true alors les sens sont ignorés et la surface de l'extérieu est positive
   * et celle de chaque intérieur est négative.
   *
   * @param array<string, mixed> $options
  */
  function area(array $options=[]): float { return 0; }
  
  /**
   * filter(int $precision=9999): ?self - renvoie un nouvel objet de même type filtré supprimant les points successifs identiques ou null si la géométrie est trop petite
   *
   * Les coordonnées sont arrondies avec le nbre de chiffres significatifs défini par le paramètre precision
   * ou par la précision par défaut. Un filtre sans arrondi n'a pas de sens.
   * La gestion des géométries dégradées par le filtre permet de garder les morceaux corrects d'un objet.
   *
   * Pour un Point ou un MultiPoint renvoie null
   *
   * @return THomogeneous|null
  */
  function filter(int $precision=9999): ?self { return null; }
  
  /**
   * simplify(float $distTreshold): ?self - simplifie la géométrie de la ligne brisée"
   *
   * Simplification de la géométrie utilisant l'algorithme de Douglas & Peucker. Ne modifie pas l'objet courant.
   * Retourne un nouvel objet simplifié ou null si le diamètre de l'objet est inférieur au seuil
   *
   * @return THomogeneous|null
   */
  function simplify(float $distTreshold): ?self { return null; }
  
  /**
   * draw(Drawing $drawing, array $style=[]) - Dessine l'objet dans le dessin avec le style
   *
   * @param TStyle $style
   */
  abstract function draw(Drawing $drawing, array $style=[]): void;
}

// UnitTest::class(__NAMESPACE__, __FILE__, 'Homogeneous'); // RIEN à tester dans la classe Homogeneous

require_once __DIR__.'/point.inc.php';
require_once __DIR__.'/linestring.inc.php';
require_once __DIR__.'/polygon.inc.php';
require_once __DIR__.'/geomcoll.inc.php';

/**
 * Classe portant les méthodes de test de Geometry et Homogneous une fois les classes filles définies
 *
 * Sinon, cela génère des erreurs à l'exécution des dites méthodes
*/
class TestsOfGeometry {
  static function test_fromGeoJSON(): void {
    echo "<b>Test de Geometry::fromGeoJSON</b></p>\n";
    $pt = Geometry::fromGeoJSON(['type'=>'Point', 'coordinates'=> [0,0]]);
    echo "pt=$pt<br>\n";
    echo "proj(pt)=",$pt->proj(function(array $pos) { return $pos; }),"<br>\n";
    $ls = Geometry::fromGeoJSON(['type'=>'LineString', 'coordinates'=> [[0,0],[1,1]]]);
    echo "ls=$ls<br>\n";
    echo "ls->center()=",json_encode($ls->center()),"<br>\n";
    $mls = Geometry::fromGeoJSON(['type'=>'MultiLineString', 'coordinates'=> [[[0,0],[1,1]]]]);
    echo "mls=$mls<br>\n";
    echo "mls->center()=",json_encode($mls->center()),"<br>\n";
    $pol = Geometry::fromGeoJSON(['type'=>'Polygon', 'coordinates'=> [[[0,0],[1,0],[1,1],[0,0]]]]);
    echo "pol=$pol<br>\n";
    echo "pol->center()=",json_encode($pol->center()),"<br>\n";
    $mpol = Geometry::fromGeoJSON(['type'=>'MultiPolygon', 'coordinates'=> [[[[0,0],[1,0],[1,1],[0,0]]]]]);
    echo "mpol=$mpol<br>\n";
    echo "mpol->center()=",json_encode($mpol->center()),"<br>\n";
    $gc = Geometry::fromGeoJSON([
      'type'=>'GeometryCollection',
      'geometries'=> [$ls->asArray(), $mls->asArray(), $mpol->asArray()]
    ]);
    echo "gc=$gc<br>\n";
    echo "gc->center()=",json_encode($gc->center()),"<br>\n";
    echo "gc->proj()=",$gc->proj(function(array $pos) { return $pos; }),"<br>\n";
  }
  
  static function test_fromWkt(): void {
    echo "<b>Test de Geometry::fromWkt</b></p>\n";
    foreach ([
      'POINT(30 30)',
      'MULTIPOINT(30 30,200 200)',
      'LINESTRING(30 30,200 200)',
      'POLYGON((1 0,0 1,-1 0,0 -1,1 0),(1 0,0 1,-1 0,0 -1,1 0))',
      'MULTIPOLYGON(((1 0,0 1,-1 0,0 -1,1 0),(2 0,0 1,-1 0,0 -1,2 0)),((3 0,0 1,-1 0,0 -1,3 0),(4 0,0 1,-1 0,0 -1,4 0)))',
      'GEOMETRYCOLLECTION(POLYGON((153042 6799129,153043 6799174,153063 6799199),(1 1,2 2)),POLYGON((154613 6803109.5,154568 6803119,154538.89999999999 6803145)),LINESTRING(153042 6799129,153043 6799174,153063 6799199),LINESTRING(154613 6803109.5,154568 6803119,154538.89999999999 6803145),POINT(153042 6799129),POINT(153043 6799174),POINT(153063 6799199))',
    ] as $wkt) {
      echo "fromWkt($wkt)=",($g = Geometry::fromWkt($wkt)),"<br>\n";
      //echo "fromWkt($wkt)=",self::fromWkt($wkt,'Draw'),"<br>\n";
      //print_r($g);
      echo "wkt()=",$g->wkt(),"<br>\n";
    }
  }

  static function test_getErrors(): void {
    $geojsons = [];
    foreach ([
      "un point ok"=> ['type'=>'Point', 'coordinates'=> [0, 10]],
      "un point ok avec z"=> ['type'=>'Point', 'coordinates'=> [0, 10, 10]],
      "un point sans y"=> ['type'=>'Point', 'coordinates'=> [0]],
      "un point avec une liste"=> ['type'=>'Point', 'coordinates'=> [[0]]],
      "un multi-point ok"=> ['type'=>'MultiPoint', 'coordinates'=> [[0, 10]]],
      "une ligne ok"=> ['type'=>'LineString', 'coordinates'=> [[0,1],[2,3]]],
      "une ligne avec 1 pt"=> ['type'=>'LineString', 'coordinates'=> [[0,1]]],
      "une ligne avec 1 pt ko"=> ['type'=>'LineString', 'coordinates'=> [[0,1],[2,3,4,5]]],
      "un polygone ok"=> ['type'=>'Polygon', 'coordinates'=> [[[0,0],[0,1],[1,1],[0,0]]]],
      "un polygone avec trou ok"=> ['type'=>'Polygon', 'coordinates'=> [[[0,0],[0,1],[1,1],[0,0]],[[0,0],[0,1],[1,1],[0,0]]]],
      "un polygone avec un anneau non fermé"=> ['type'=>'Polygon', 'coordinates'=> [[[0,0],[0,1],[1,1],[1,0]]]],
      "un polygone avec un anneau non fermé et comportant 3 positions"=>
         ['type'=>'Polygon', 'coordinates'=> [[[0,0],[0,1],[1,1]]]],
      "un multi-polygone avec un anneau non fermé et comportant 3 positions"=>
         ['type'=>'MultiPolygon', 'coordinates'=> [[[[0,0],[0,1],[1,1]]]]],
    ] as $title => $geojson) {
      $geom = Geometry::fromGeoJSON($geojson);
      echo "<pre>$title - getErrors()="; print_r($geom->getErrors()); echo "<pre>\n";
      $geojsons[] = $geojson;
    }
    $geom = Geometry::fromGeoJSON(['type'=>'GeometryCollection', 'geometries'=> $geojsons]);
    echo "<pre>GeometryCollection - getErrors()="; print_r($geom->getErrors()); echo "<pre>\n";
  }

  static function test_proj2D(): void {
    $gc = []; // liste des geom
    foreach ([
      ['class'=> 'Point', 'coords'=> [1,2,3]],
      ['class'=> 'MultiPoint', 'coords'=> [[1,2,3]]],
      ['class'=> 'LineString', 'coords'=> [[1,2,3]]],
      ['class'=> 'MultiLineString', 'coords'=> [[[1,2,3]]]],
      ['class'=> 'Polygon', 'coords'=> [[[1,2,3]]]],
      ['class'=> 'MultiPolygon', 'coords'=> [[[[1,2,3]]]]],
    ] as $test) {
      $class = __NAMESPACE__.'\\'.$test['class'];
      $geom = new $class($test['coords']);
      echo "proj2D($geom)=",$geom->proj2D(),"<br>\n";
      $gc[] = $geom;
    }
    $gc = new GeometryCollection($gc);
    echo "proj2D($gc)=",$gc->proj2D(),"<br>\n";
  }

  static function test_proj(): void {
    //$projPos = function(array $pos): array { return $pos; };
    $projPos = function(array $pos): array { return [$pos[0]*2, $pos[1]*2]; };
    $gc = []; // liste des geom
    foreach ([
      ['class'=> 'Point', 'coords'=> [1,2,3]],
      ['class'=> 'MultiPoint', 'coords'=> [[1,2,3]]],
      ['class'=> 'LineString', 'coords'=> [[1,2,3]]],
      ['class'=> 'MultiLineString', 'coords'=> [[[1,2,3]]]],
      ['class'=> 'Polygon', 'coords'=> [[[1,2,3]]]],
      ['class'=> 'MultiPolygon', 'coords'=> [[[[1,2,3]]]]],
    ] as $test) {
      $class = __NAMESPACE__.'\\'.$test['class'];
      $geom = new $class($test['coords']);
      echo "proj($geom)=",$geom->proj($projPos),"<br>\n";
      $gc[] = $geom;
    }
    $gc = new GeometryCollection($gc);
    echo "proj($gc)=",$gc->proj($projPos),"<br>\n";
  }

  static function test_simpleGeoms(): void {
    echo "<b>Test de simpleGeoms</b><br>\n";
    foreach ([
      [ 'type'=>'MultiPoint', 'coordinates'=>[[0,0], [1,1]]],
      [ 'type'=>'GeometryCollection',
        'geometries'=> [
          ['type'=>'MultiPoint', 'coordinates'=>[[0,0], [1,1]]],
          ['type'=>'LineString', 'coordinates'=>[[0,0], [1,1]]],
        ],
      ]
    ] as $geom) {
      echo '<pre>',json_encode($geom)," -> "; print_r(Geometry::fromGeoJSON($geom)->simpleGeoms()); echo "<pre>\n";
    }
  }
};
UnitTest::class(__NAMESPACE__, __FILE__, 'TestsOfGeometry');
