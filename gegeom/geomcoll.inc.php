<?php
namespace gegeom;
{/*PhpDoc:
name:  geomcoll.inc.php
title: geomcoll.inc.php - définition de la classe GeometryCollection
classes:
doc: |
  Fichier concu pour être inclus dans gegeom.inc.php
journal: |
  30/4/2019:
    - éclatement de gegeom.inc.php
includes: [gegeom.inc.php]
*/}
require_once __DIR__.'/gegeom.inc.php';
use \unittest\UnitTest;

{/*PhpDoc: classes
name: GeometryCollection
title: class GeometryCollection - Liste d'objets géométriques
methods:
*/}
class GeometryCollection extends Geometry {
  protected $geometries; // list of Geometry objects
  
  // prend en paramètre une liste d'objets Geometry
  function __construct(array $geometries, array $style=[]) { $this->geometries = $geometries; $this->style = $style; }
  
  // récupère le type en supprimant l'espace de nom
  function type(): string { $c = get_called_class(); return substr($c, strrpos($c, '\\')+1); }

  // récupère les coordonnées, retourne [] pour une GeometryCollection
  //function coords(): array { return []; }
  
  function geoms(): array { return $this->geometries; } // liste des objets contenus dans l'objet
  
  function isValid(): bool {
    foreach ($this->geoms() as $geom)
      if (!$geom->isValid())
        return false;
    return true;
  }
  
  function getErrors(): array {
    $errors = [];
    foreach ($this->geoms() as $i => $geom) {
      if ($eltErrors = $geom->getErrors())
        $errors[] = ["Erreur sur l'élément $i", $eltErrors];
    }
    return $errors;
  }
  
  function __toString(): string {
    return ($this->type())
      .'('.implode(',',array_map(function(Geometry $geom) { return $geom->__toString(); }, $this->geometries)).')';
  }
  
  function proj2D(): Geometry {
    return new self(array_map(function(Geometry $geom) { return $geom->proj2D(); }, $this->geometries));
  }
  
  // génère la représentation Php du GeoJSON
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
  
  // retourne la liste des types élémentaires ('Point','LineString','Polygon') contenus dans la géométrie
  function eltTypes(): array {
    $allEltTypes = [];
    foreach ($this->geometries as $geom)
      if ($eltTypes = $geom->eltTypes())
        $allEltTypes[$eltTypes[0]] = 1;
    return array_keys($allEltTypes);
  }

  function center(): array {
    if (!$this->geometries)
      throw new \Exception("Erreur: GeometryCollection::center() sur une liste vide");
    return LnPos::center(array_map(function($geom) { return $geom->center(); }, $this->geometries), Geometry::$precision);
  }
  
  function nbreOfPos(): int {
    return array_sum(array_map(function($g) { return $g->nbreOfPos(); }, $this->geometries));
  }
  
  function aPos(): array {
    if (!$this->geometries)
      throw new \Exception("Erreur: GeometryCollection::aPos() sur une liste vide");
    return $this->geometries[0]->aPos();
  }
  
  function bbox(): GBox {
    return array_reduce($this->geometries, function($carry, $g) { return $carry->union($g->bbox()); }, new GBox);
  }
  
  function nbPoints(): int {
    return array_sum(array_map(function(Geometry $geom) { return $geom->nbPoints(); }, $this->geometries));
  }
  
  function length(): float {
    return array_sum(array_map(function(Geometry $geom) { return $geom->length(); }, $this->geometries));
  }
  
  function area(array $options=[]): float {
    return array_sum(array_map(function(Geometry $geom) use($options) { return $geom->area($options); }, $this->geometries));
  }
  
  function proj(callable $projPos): Geometry {
    return new GeometryCollection(array_map(function($g) use($projPos){ return $g->proj($projPos); }, $this->geometries));
  }
  
  function filter(int $precision=9999): ?Geometry {
    return new GeometryCollection(array_map(function($g) use($precision){ return $g->filter($precision); }, $this->geometries));
  }
  
  function simplify(float $dTreshold): ?Geometry {
    return new GeometryCollection(array_map(function($g) use($dTreshold){ return $g->simplify($dTreshold); }, $this->geometries));
  }
  
  function dissolveCollection(): array { return $this->geometries; }
  
  // Décompose une géométrie en un array de géométries élémentaires (Point/LineString/Polygon)
  function decompose(): array {
    $elts = [];
    foreach ($this->geometries as $g)
      $elts = array_merge($elts, $g->decompose());
    return $elts;
  }

  function draw(Drawing $drawing, array $style=[]) {}
    
  // fonction globale de test de la classe
  static function test_GeometryCollection() {
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
      echo "gc->bbox()=",$gc->bbox(),"<br>\n";
      #echo "gc->center()=",json_encode($gc->center()),"<br>\n";
      echo "gc->nbreOfPos()=",$gc->nbreOfPos(),"<br>\n";
      echo "gc->nbPoints()=",$gc->nbPoints(),"<br>\n";
      echo "gc->length()=",$gc->length(),"<br>\n";
      echo "gc->area()=",$gc->area(),"<br>\n";
      echo "gc->proj()=",$gc->proj(function(array $pos) { return [$pos[0]/2, $pos[1]/2]; }),"<br>\n";
    }
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'GeometryCollection'); // Test unitaire de la classe GeometryCollection
