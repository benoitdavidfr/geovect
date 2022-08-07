<?php
namespace gegeom;
/*PhpDoc:
name:  geomcoll.inc.php
title: geomcoll.inc.php - définition de la classe GeometryCollection
doc: |
  Fichier concu pour être inclus dans gegeom.inc.php
journal: |
  5/8/2022:
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
  30/4/2019:
    - éclatement de gegeom.inc.php
includes: [gegeom.inc.php]
*/
require_once __DIR__.'/gegeom.inc.php';
use \unittest\UnitTest;

/**
 * class GeometryCollection - objet géométrique défini comme un ensemble d'objets homogènes
 */
class GeometryCollection extends Geometry {
  const ErrorSimpleGeoms = 'GeometryCollection::ErrorSimpleGeoms';
  
  /** @var array<int, THomogeneous> */
  protected array $geometries; // liste d'objets géométriques homogènes
  
  /**
   * __construct(array $geometries, array $style=[]) - création à partir d'une liste d'objets homogènes
   *
   * @param array<int, THomogeneous> $geometries
  */
  function __construct(array $geometries) {
    //echo "<pre>GeometryCollection::__construct("; print_r($geometries); echo ")</pre>\n";
    $this->geometries = $geometries;
  }
  
  // récupère le type en supprimant l'espace de nom
  function type(): string { $c = get_called_class(); return substr($c, strrpos($c, '\\')+1); }
  
  /** @return array<int, Homogeneous> */
  function geoms(): array { return $this->geometries; } // liste des objets contenus dans l'objet
  
  function isValid(): bool {
    foreach ($this->geoms() as $geom)
      if (!$geom->isValid())
        return false;
    return true;
  }
  
  /**
   * getErrors(): array - renvoie l'arbre des erreurs ou [] s'il n'y en a pas"
   *
   * La réponse est :
   *   {errors} ::= [ {error} ]
   *   {error} ::= {string} // erreur élémentaire
   *           ::= [ {string}, {errors}] // erreur complexe
   * @return array<mixed>
   */
  function getErrors(): array {
    $errors = [];
    foreach ($this->geoms() as $i => $geom) {
      if ($eltErrors = $geom->getErrors())
        $errors[] = ["Erreur sur l'élément $i", $eltErrors];
    }
    return $errors;
  }
  
  /**
   * asArray(): TGeoJsonCollectionGeometry - génère la représentation GeoJSON structurée comme array Php
   *
   * @return TGeoJsonCollectionGeometry
   */
  function asArray(): array {
    //echo "GeometryCollection::asArray()<br>\n";
    $geoms = [];
    foreach ($this->geometries as $geomObj)
      $geoms[] = $geomObj->asArray();
    return ['type'=>'GeometryCollection', 'geometries'=> $geoms];
  }
  
  function wkt(): string {
    return strtoupper($this->type())
      .'('.implode(',',array_map(function($geom) { return $geom->wkt(); }, $this->geometries)).')';
  }
  
  /**
   * eltTypes(): array<int, string> - retourne la liste des types élémentaires ('Point','LineString','Polygon') contenus dans la géométrie
   *
   * @return array<int, string>
   */
  function eltTypes(): array {
    $allEltTypes = [];
    foreach ($this->geometries as $geom)
      if ($eltTypes = $geom->eltTypes())
        $allEltTypes[$eltTypes[0]] = 1;
    return array_keys($allEltTypes);
  }

  /**
   * center(): TPos - centre d'une géométrie considérée en coord. géo.
   *
   * @return TPos
   */
  function center(): array {
    if (!$this->geometries)
      throw new \Exception("Erreur: GeometryCollection::center() sur une liste vide");
    return LnPos::center(array_map(function($geom) { return $geom->center(); }, $this->geometries), Homogeneous::$precision);
  }
  
  function nbreOfPos(): int {
    return array_sum(array_map(function($g) { return $g->nbreOfPos(); }, $this->geometries));
  }
  
  /**
   * aPos(): TPos - une position.
   *
   * @return TPos
   */
  function aPos(): array {
    if (!$this->geometries)
      throw new \Exception("Erreur: GeometryCollection::aPos() sur une liste vide");
    return $this->geometries[0]->aPos();
  }
  
  function gbox(): GBox {
    return array_reduce($this->geometries, function($carry, $g) { return $carry->union($g->gbox()); }, new GBox);
  }
  
  function nbPoints(): int {
    return array_sum(array_map(function(Geometry $geom) { return $geom->nbPoints(); }, $this->geometries));
  }
  
  function proj2D(): self {
    return new self(array_map(function(Homogeneous $geom) { return $geom->proj2D(); }, $this->geometries));
  }
  
  function length(): float {
    return array_sum(array_map(function(Geometry $geom) { return $geom->length(); }, $this->geometries));
  }
  
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
  function area(array $options=[]): float {
    return array_sum(array_map(function(Geometry $geom) use($options) { return $geom->area($options); }, $this->geometries));
  }
  
  function proj(callable $projPos): Geometry {
    return new GeometryCollection(array_map(function($g) use($projPos){ return $g->proj($projPos); }, $this->geometries));
  }
  
  /**
   * filter(int $prec=9999): ?self - renvoie un nouveau GeometryCollection filtré supprimant les points successifs identiques ou null si la géométrie est trop petite
   */
  function filter(int $prec=9999): ?self {
    // filtre chacun des éléments et supprime ceux réduits à null
    $geoms = array_filter(
      array_map(function($g) use($prec) { return $g->filter($prec); }, $this->geometries),
      function(?Homogeneous $geom): bool { return !is_null($geom); }
    );
    // s'il en reste au moins 1 fabrique un nouveau GeometryCollection sinon renvoie null
    return $geoms ? new GeometryCollection($geoms) : null;
  }
  
  function simplify(float $dTreshold): ?self {
    // simplifie chacun des éléments et supprime ceux réduits à null
    $geoms = array_filter(
      array_map(function($g) use($dTreshold) { return $g->simplify($dTreshold); }, $this->geometries),
      function(?Homogeneous $geom): bool { return !is_null($geom); }
    );
    // s'il en reste au moins 1 fabrique un nouveau GeometryCollection sinon renvoie null
    return $geoms ? new GeometryCollection($geoms) : null;
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
    $simpleGeoms = ['points'=> [], 'lineStrings'=> [], 'polygons'=> []];
    foreach ($this->geometries as $geom) {
      switch($geom->type()) {
        case 'Point': {
          $simpleGeoms['points'][] = $geom;
          break;
        }
        case 'MultiPoint': {
          $simpleGeoms['points'] = array_merge($simpleGeoms['points'], $geom->simpleGeoms()['points']);
          break;
        }
        case 'LineString': {
          $simpleGeoms['lineStrings'][] = $geom;
          break;
        }
        case 'MultiLineString': {
          $simpleGeoms['lineStrings'] = array_merge($simpleGeoms['lineStrings'], $geom->simpleGeoms()['lineStrings']);
          break;
        }
        case 'Polygon': {
          $simpleGeoms['polygons'][] = $geom;
          break;
        }
        case 'MultiPolygon': {
          $simpleGeoms['polygons'] = array_merge($simpleGeoms['polygons'], $geom->simpleGeoms()['polygons']);
          break;
        }
        default:
          throw new \SExcept("Erreur, type ".$geom->type()." non traité dans GeometryCollection::simpleGeoms()",
            self::ErrorSimpleGeoms);
      }
    }
    return $simpleGeoms;
  }

  function draw(Drawing $drawing, array $style=[]): void {}
    
  // fonction globale de test de la classe
  static function test_GeometryCollection(): void {
    $aPoint = new Point([1,1]);
    $aLineString = new LineString([[1, 0],[0, 1],[-1, 0],[0, -1]]);
    $aPolygon = new Polygon([[[1, 0],[0, 1],[-1, 0],[0, -1],[1, 0]]]);
    foreach ([
      'vide'=> [],
      'unPoint'=> [$aPoint],
      'uneLigne'=> [$aLineString],
      'unPolygone'=> [$aPolygon],
      'unPoint+uneLigne+Polygone'=> [$aPoint,$aLineString,$aPolygon],
    ] as $title => $geometries) {
      echo "<h3>$title</h3>\n";
      $gc = new GeometryCollection($geometries);
      echo "<pre>gc="; print_r($gc); echo "</pre>\n";
      echo "gc=$gc<br>\n";
      echo "gc->geoms()=[",implode(',',$gc->geoms()),"]<br>\n";
      try {
        echo "gc->center()=",json_encode($gc->center()),"<br>\n";
      } catch (\Exception $e) {
        echo "\Exception: ",$e->getMessage(),"<br>\n";
      }
      echo "gc->gbox()=",$gc->gbox(),"<br>\n";
      #echo "gc->center()=",json_encode($gc->center()),"<br>\n";
      echo "gc->nbreOfPos()=",$gc->nbreOfPos(),"<br>\n";
      echo "gc->nbPoints()=",$gc->nbPoints(),"<br>\n";
      echo "gc->length()=",$gc->length(),"<br>\n";
      echo "gc->area()=",$gc->area(),"<br>\n";
      echo "gc->proj()=",$gc->proj(function(array $pos) { return [$pos[0]/2, $pos[1]/2]; }),"<br>\n";
      echo "<pre>gc->simpleGeoms() = "; print_r($gc->simpleGeoms()); echo "</pre>\n";
    }
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'GeometryCollection'); // Test unitaire de la classe GeometryCollection
