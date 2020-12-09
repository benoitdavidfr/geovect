<?php
namespace gegeom;

// Ce package ne fonctionne pas avec Php8
/*if (preg_match('!^(\d\.\d).\d$!', phpversion(), $matches) && ($matches[1] >= 8.0))
  die("Ne fonctionne pas avec Php ".phpversion()."\n");*/

{/*PhpDoc:
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
  6/5/2019:
    - ajout Homogeneous::filter()
  5/5/2019:
    - création de la classe LnPos
    - réécriture de plusieurs algoritmes pour les rendre plus génériques
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
*/}
require_once __DIR__.'/gebox.inc.php';
require_once __DIR__.'/point.inc.php';
require_once __DIR__.'/linestring.inc.php';
require_once __DIR__.'/polygon.inc.php';
require_once __DIR__.'/geomcoll.inc.php';
require_once __DIR__.'/wkt.inc.php';
use \unittest\UnitTest;

{/*PhpDoc: functions
name: asArray
title: "function asArray($val) - transforme récursivement une valeur en aray Php pur sans objet, utile pour l'afficher avec json_encode()"
doc: Les objets rencontrés doivent avoir une méthode asArray() qui décompose l'objet en array des propriétés exposées
*/}
function asArray($val) {
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

{/*PhpDoc: functions
name: json_encode
title: "function json_encode($val): string - génère un json en traversant les objets qui savent se décomposer en array par asArray()"
*/}
function json_encode($val): string {
  return \json_encode(asArray($val), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
UnitTest::function(__FILE__, 'json_encode', function() { // Tests unitaires de json_encode
  echo "json_encode=",json_encode(new LineString([[0,0],[1,1]])),"<br>\n";
  echo "\json_encode=",\json_encode(new LineString([[0,0],[1,1]])),"<br>\n";
});

{/*PhpDoc: classes
name: Geometry
title: abstract class Geometry - Geometry GeoJSON et quelques opérations
methods:
doc: |
  Sur-classe des classes concrètes implémentant chaque type GeoJSON.
  On distingue GeometryCollection des autres types appelés homogènes et regroupés sous la super-classe Homogeneous.
  Un style peut être associé à une Geometry inspiré de https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0
  Par ailleurs, cette bibliothèque peut être étendue en préfixant les noms des classes par une chaine possibilité prévue
  dans fromGeoJson() ; cette possibilité EST A VALIDER !!!
  Enfin, cette bibliothèque est indépendante des changements de systèmes de coordonnées qu'il est possible d'effectuer
  en utilisant la méthode proj() qui prend en paramètre une fonction anonyme de projection.
*/}
abstract class Geometry {
  const HOMOGENEOUSTYPES = ['Point','LineString','Polygon','MultiPoint','MultiLineString','MultiPolygon'];
  static $precision = 6; // nbre de chiffres après la virgule à conserver pour les coord. géo.
  static $ePrecision = 1; // nbre de chiffres après la virgule à conserver pour les coord. euclidiennes
  protected $style; // un style peut être associé à une géométrie, toujours un array, par défaut []
  
  static function fromGeoJSON(array $geom, string $prefix=''): Geometry {
    {/*PhpDoc: methods
    name: fromGeoJSON
    title: "static function fromGeoJSON(array $geom, string $prefix=''): Geometry - crée un objet Geometry à partir du json_decode() d'une géométrie GeoJSON"
    doc: |
      L'utilisation du prefix permet de créer des classes préfixées par ce préfix afin de gérer une extension de la bibliothèque.
    */}
    if (isset($geom['type']) && in_array($geom['type'], self::HOMOGENEOUSTYPES) && isset($geom['coordinates'])) {
      $type = __NAMESPACE__.'\\'.$prefix.$geom['type'];
      return new $type($geom['coordinates']);
    }
    elseif (isset($geom['type']) && ($geom['type']=='GeometryCollection') && isset($geom['geometries'])) {
      return new GeometryCollection(array_map('self::fromGeoJSON', $geom['geometries']));
    }
    else
      throw new \Exception("Erreur de Geometry::fromGeoJSON(".json_encode($geom).")");
  }
  
  static function test_fromGeoJSON() {
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
  
  /*PhpDoc: methods
  name:  fromWkt
  title: "static function fromWkt(string $wkt, string $prefix=''): Geometry - crée une géométrie à partir d'un WKT"
  doc: |
    génère une erreur si le WKT ne correspond pas à une géométrie
  */
  static function fromWkt(string $wkt, string $prefix=''): Geometry {
    return self::fromGeoJSON(Wkt::geojson($wkt), $prefix);
  }
  
  static function test_fromWkt() {
    foreach ([
      'POINT(30 30)',
      'MULTIPOINT(30 30,200 200)',
      'LINESTRING(30 30,200 200)',
      'POLYGON((1 0,0 1,-1 0,0 -1,1 0),(1 0,0 1,-1 0,0 -1,1 0))',
      'MULTIPOLYGON(((1 0,0 1,-1 0,0 -1,1 0),(2 0,0 1,-1 0,0 -1,2 0)),((3 0,0 1,-1 0,0 -1,3 0),(4 0,0 1,-1 0,0 -1,4 0)))',
      'GEOMETRYCOLLECTION(POLYGON((153042 6799129,153043 6799174,153063 6799199),(1 1,2 2)),POLYGON((154613 6803109.5,154568 6803119,154538.89999999999 6803145)),LINESTRING(153042 6799129,153043 6799174,153063 6799199),LINESTRING(154613 6803109.5,154568 6803119,154538.89999999999 6803145),POINT(153042 6799129),POINT(153043 6799174),POINT(153063 6799199))',
    ] as $wkt) {
      echo "fromWkt($wkt)=",($g = self::fromWkt($wkt)),"<br>\n";
      //echo "fromWkt($wkt)=",self::fromWkt($wkt,'Draw'),"<br>\n";
      //print_r($g);
      echo "wkt()=",$g->wkt(),"<br>\n";
    }
  }
  
  // récupère le type en supprimant l'espace de nom
  function type(): string { $c = get_called_class(); return substr($c, strrpos($c, '\\')+1); }
  // retourne la liste des types élémentaires ('Point','LineString','Polygon') contenus dans la géométrie
  abstract function eltTypes(): array;
  
  // définit le style associé et le récupère
  function setStyle(array $style=[]): void { $this->style = $style; }
  function getStyle(): array { return $this->style; }
  
  /*PhpDoc: methods
  name: geojson
  title: "function geojson(): string - génère la représentation string du GeoJSON"
  */
  function geojson(): string { return json_encode($this->asArray()); }
  
  /*PhpDoc: methods
  name:  fromWkt
  title: "abstract function isValid(): bool - teste la validité de l'objet"
  */
  abstract function isValid(): bool;
  
  /*PhpDoc: methods
  name:  getErrors
  title: "abstract function getErrors(): array - renvoie l'arbre des erreurs ou [] s'il n'y en a pas"
  doc: |
    La réponse est :
      {errors} ::= [ {error} ]
      {error} ::= {string} // erreur élémentaire
              ::= [ {string}, {errors}] // erreur complexe
  */
  abstract function getErrors(): array;
  static function test_getErrors() {
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
  
  /*PhpDoc: methods
  name: ebox
  title: "function ebox(): EBox - renvoie la EBox de la géométrie considérée comme euclidienne"
  */
  function ebox(): EBox { return new EBox($this->bbox()->asArray()); }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'Geometry'); // Test unitaire de la classe Geometry

{/*PhpDoc: classes
name: Homogeneous
title: abstract class Homogeneous extends Geometry - Geometry GeoJSON homogène
methods:
doc: |
  Sur-classe des classes concrètes implémentant les types GeoJSON homogènes.
  Les coordonnées sont conservées en liste**n de positions comme en GeoJSON, et pas structurées avec des objets.
  Par défaut, la géométrie est en coordonnées géographiques mais les classes peuvent aussi être utilisées
  avec des coordonnées euclidiennes en utilisant parfois des méthodes soécifiques préfixées par e.
*/}
abstract class Homogeneous extends Geometry {
  protected $coords; // coordonnées ou Positions, stockées comme array, array(array), ... en fonction de la sous-classe
  
  /*PhpDoc: methods
  name: __construct
  title: "function __construct(array $coords, array $style=[]) - fonction d'initialisation commune à toutes les géométries homogènes"
  */
  function __construct(array $coords, array $style=[]) { $this->coords = $coords; $this->style = $style; }
  
  // récupère les coordonnées
  function coords(): array { return $this->coords; }
  
  /*PhpDoc: methods
  name: asArray
  title: "function asArray(): array - génère la représentation Php du GeoJSON"
  */
  function asArray(): array { return ['type'=> $this->type(), 'coordinates'=> $this->coords]; }
  
  /*PhpDoc: methods
  name: __toString
  title: "function __toString(): string - génère la réprésentation string WKT"
  */
  //function __toString(): string { return ($this->type()).LnPos::wkt($this->coords); }
  
  /*PhpDoc: methods
  name: wkt
  title: "function wkt(): string - génère la réprésentation WKT"
  */
  function wkt(): string { return strtoupper($this->type()).LnPos::wkt($this->coords); }
  
  /*PhpDoc: methods
  name: geoms
  title: "abstract function geoms(): array - retourne la liste des primitives contenues dans l'objet sous la forme d'objets"
  doc: |
    Point -> [], MutiPoint->[Point], LineString->[Point], MultiLineString->[LineString], Polygon->[LineString],
    MutiPolygon->[Polygon], GeometryCollection->[Elements]  
  */
  abstract function geoms(): array;
  
  /*PhpDoc: methods
  name: proj2D
  title: "abstract function proj2D(): Geometry - projection 2D, supprime l'éventuelle 3ème coordonnée, renvoie un nouveau Geometry"
  */
  function proj2D(): Geometry { return self::proj(function(array $pos) { return [$pos[0], $pos[1]]; }); }
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
  
  /*PhpDoc: methods
  name: center
  title: "function center(): array - centre d'une géométrie considérée en coord. géo."
  */
  function center(): array { return LnPos::center($this->coords, self::$precision); }
    
  /*PhpDoc: methods
  name: center
  title: "function ecenter(): array - centre d'une géométrie cosnidérée en coord. euclidiennes"
  */
  function ecenter(): array { return LnPos::center($this->coords, self::$eprecision); }
  
  /*PhpDoc: methods
  name: nbreOfPos
  title: "function nbreOfPos(): int - retourne le nobre de positions"
  */
  function nbreOfPos(): int { return LnPos::count($this->coords); }
  
  /*PhpDoc: methods
  name: aPos
  title: "function aPos(): array - retourne une position de la géométrie"
  */
  function aPos(): array { return LnPos::aPos($this->coords); }
  
  /*PhpDoc: methods
  name: bbox
  title: "function bbox(): GBox - renvoie la GBox de la géométrie considérée comme géographique"
  */
  function bbox(): GBox { return new GBox($this->coords); }
  
  /*PhpDoc: methods
  name: proj
  title: "function proj(callable $projPos): Geometry - projète une géométrie, prend en paramètre une fonction anonyme de projection d'une position"
  */
  function proj(callable $projPos): Geometry {
    $class = get_called_class();
    return new $class(LnPos::projLn($this->coords, $projPos));
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
  
  /*PhpDoc: methods
  name:  nbPoints
  title: "function nbPoints(): int - nombre de primitives Point contenue dans la géométrie"
  */
  function nbPoints(): int { return 0; }
  
  /*PhpDoc: methods
  name:  length
  title: "function length(): float - longueur dans le système de coordonnées courant"
  */
  function length(): float { return 0; }
  
  /*PhpDoc: methods
  name:  area
  title: "function area($options=[]): float - surface dans le système de coordonnées courant"
  doc: |
    Par défaut, l'extérieur et les intérieurs tournent dans des sens différents.
    La surface est positive ssi l'extérieur tourne dans le sens trigonométrique inverse, <0 sinon.
    Cette règle est conforme à la définition GeoJSON.
    Si l'option 'noDirection' vaut true alors les sens sont ignorés et la surface de l'extérieu est positive
    et celle de chaque intérieur est négative.
  */
  function area(array $options=[]): float { return 0; }
  
  /*PhpDoc: methods
  name:  filter
  title: "function filter(int $precision=9999): ?Homogeneous - renvoie un nouveau Homogeneous filtré supprimant les points successifs identiques sur les lignes brisées ou null si la géométrie est trop petite"
  doc: |
    Les coordonnées sont arrondies avec le nbre de chiffres significatifs défini par le paramètre precision
    ou par la précision par défaut. Un filtre sans arrondi n'a pas de sens.
    La gestion des géométries dégradées par le filtre permet de garder les morceaux corrects d'un objet.
  */
  function filter(int $precision=9999): ?Homogeneous { return $this; }
  
  /*PhpDoc: methods
  name:  simplify
  title: "function simplify(float $distTreshold): ?LineString - simplifie la géométrie de la ligne brisée"
  doc : |
    Algorithme de Douglas & Peucker
    Ne modifie pas l'objet courant
    Retourne un nouvel objet LineString simplifié
    ou null si la ligne est fermée et que la distance max est inférieure au seuil
  */
  function simplify(float $distTreshold): ?Homogeneous { return $this; }
  
  /*PhpDoc: methods
  name:  dissolveCollection
  title: "dissolveCollection(): array - retourne un array de primitives géométriques homogènes"
  */
  function dissolveCollection(): array { return [$this]; }
  
  /*PhpDoc: methods
  name: decompose
  title: "function decompose(): array - Décompose une géométrie en un array de géométries élémentaires (Point/LineString/Polygon)"
  */
  function decompose(): array {
    $transfos = ['MultiPoint'=>'Point', 'MultiLineString'=>'LineString', 'MultiPolygon'=>'Polygon'];
    if (isset($transfos[$this->type()])) {
      $elts = [];
      foreach ($this->coords as $eltcoords) {
        $class = __NAMESPACE__.'\\'.$transfos[$this->type()];
        $elts[] = new $class($eltcoords);
      }
      return $elts;
    }
    else // $this est un élément
      return [$this];
  }
  static function test_decompose() {
    echo "<b>Test de decompose</b><br>\n";
    foreach ([
      [ 'type'=>'MultiPoint', 'coordinates'=>[[0,0], [1,1]]],
      [ 'type'=>'GeometryCollection',
        'geometries'=> [
          ['type'=>'MultiPoint', 'coordinates'=>[[0,0], [1,1]]],
          ['type'=>'LineString', 'coordinates'=>[[0,0], [1,1]]],
        ],
      ]
    ] as $geom) {
      echo json_encode($geom),' -> [',implode(',',Geometry::fromGeoJSON($geom)->decompose()),"]<br>\n";
    }
  }
  
  /* agrège un ensemble de géométries élémentaires en une unique Geometry
  static function aggregate(array $elts): Geometry {
    $bbox = new GBox;
    foreach ($elts as $elt)
      $bbox->union($elt->bbox());
    return new Polygon($bbox->polygon()); // temporaireemnt représente chaque agrégat par son GBox
    $elts = array_merge([new Polygon($bbox->polygon())], $elts);
    if (count($elts) == 1)
      return $elts[0];
    $agg = [];
    foreach ($elts as $elt)
      $agg[$elt->type()][] = $elt;
    if (isset($agg['Point']) && !isset($agg['LineString']) && !isset($agg['Polygon']))
      return MultiPoint::haggregate($agg['Point']);
    elseif (!isset($agg['Point']) && isset($agg['LineString']) && !isset($agg['Polygon']))
      return MultiLineString::haggregate($agg['LineString']);
    elseif (!isset($agg['Point']) && !isset($agg['LineString']) && isset($agg['Polygon']))
      return MultiPolygon::haggregate($agg['Polygon']);
    else 
      return new GeometryCollection(array_merge(
        MultiPoint::haggregate($agg['Point']),
        MultiLineString::haggregate($agg['LineString']),
        MultiPolygon::haggregate($agg['Polygon'])
      ));
  }
  */
  
  /*PhpDoc: methods
  name: draw
  title: "abstract function draw(Drawing $drawing, array $style=[]) - Dessine l'objet dans le dessin avec le style"
  */
  abstract function draw(Drawing $drawing, array $style=[]);
}

UnitTest::class(__NAMESPACE__, __FILE__, 'Homogeneous'); // Test unitaire de la classe Homogeneous
