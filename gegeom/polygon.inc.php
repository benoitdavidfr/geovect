<?php
namespace gegeom;
{/*PhpDoc:
name:  polygon.inc.php
title: polygon.inc.php - définition des classes Polygon et MultiPolygon
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
name: Polygon
title: class Polygon extends Homogeneous - Polygon
methods:
doc: |
  Défini par une liste d'anneaux (ring), chacun liste d'au moins 4 positions, la première et la dernière étant équivalentes.
  Le premier anneau est obligatoire est est l'extérieur du polygone, les autres facultatifs sont chacun un intérieur.
  Chaque intérieur doit être inclus dans l'extérieur et les différents anneaux ne doivent pas s'intersecter.
  Chaque anneau doit respecter la règle de la main droite (right-hand rule) par rapport à la surface bordée,
  cad que l'anneau extérieur est défini dans sens des auguilles d'une montre et les intéreiurs dans le sens inverse.
*/}
class Polygon extends Homogeneous {
  // $coords contient une liste d'anneaux (ring), chacun est une listes d'au moins 4 positions (LLPos)
  const EXAMPLES = [
    [ "type" => "Polygon", "coordinates" => LLPos::EXAMPLES["triangle unité"] ],
  ];

  function eltTypes(): array { return ['Polygon']; }
  
  static function test_wkt() {
    foreach (self::EXAMPLES as $ex) {
      $pol = Geometry::fromGeoJSON($ex);
      $pol = $pol->filter(0);
      echo "wkt=",$pol->wkt(),"<br>\n";
    }
  }
    
  function geoms(): array {
    return array_map(function(array $lpos) { return new LineString($lpos); }, $this->coords);
  }
  
  function isValid(): bool {
    if (!LLPos::isValid($this->coords))
      return false; // Les coordonnées du Polygon doivent être un LLPos
    foreach ($this->coords as $ring) {
      if (count($ring) < 4)
        return false; // Dans un Polygone chaque anneau doit comporter au moins 4 positions
      if ($ring[0] <> $ring[count($ring)-1])
        return false; // Dans un polygone chaque anneau doit être fermé
    }
    return true;
  }
  
  function getErrors(): array {
    $errors = [];
    if ($llposErrors = LLPos::getErrors($this->coords))
      return ["Les coordonnées du Polygon doivent être un LLPos", $llposErrors];
    foreach ($this->coords as $i => $ring) {
      $ringErrors = [];
      if (count($ring) < 4)
        $ringErrors[] = "L'anneau doit comporter au moins 4 positions";
      if ($ring[0] <> $ring[count($ring)-1])
        $ringErrors[] = "L'anneau doit être fermé";
      if ($ringErrors)
        $errors[] = ["Erreur sur l'anneau $i", $ringErrors];
    }
    return $errors;
  }
  
  function area(array $options=[]): float {
    $noDirection = (isset($options['noDirection']) && ($options['noDirection']));
    $area = null;
    foreach ($this->coords as $lpos) {
      $areaOfRing = (new LineString($lpos))->areaOfRing();
      if ($area === null)
        $area = $noDirection ? abs($areaOfRing) : $areaOfRing;
      else
        $area += $noDirection ? -abs($areaOfRing) : $areaOfRing;
    }
    return $area;
  }
  static function test_area() {
    foreach (LLPos::EXAMPLES as $title => $coords) {
      echo "<h3>$title</h3>";
      $pol = new Polygon($coords);
      echo "area($pol)=",$pol->area();
      echo ", noDirection->",$pol->area(['noDirection'=>true]),"\n";
    }
  }
  
  function filter(int $precision=9999): ?Homogeneous {
    if ($precision == 9999)
      $precision == self::$precision;
    $cclass = get_called_class();
    $coords = [];
    foreach ($this->coords as $lpos) {
      $lpos = LPos::filter($lpos, $precision);
      if (count($lpos) < 4) {
        if (!$coords)
          return null; // Si l'extérieur fait moins de 4 positions alors retour null
        // sinon c'est un trou et on le saute simplement
      }
      else
        $coords[] = $lpos;
    }
    return new $cclass($coords);
  }
  static function test_filter() {
    $pol = Geometry::fromGeoJSON(self::EXAMPLE);
    echo $pol,"->filter(3)=",json_encode($pol->filter(3)),"<br>\n";
  }
  
  /*PhpDoc: methods
  name:  posInPolygon
  title: "posInPolygon(array $pos): bool - teste si une position est dans le polygone"
  */
  function posInPolygon(array $pos): bool {
    $c = false;
    foreach ($this->coords as $ring)
      if (Pos::posInPolygon($pos, $ring))
        $c = !$c;
    return $c;
  }
  static function test_posInPolygon() {
    $p0 = [0, 0];
    foreach ([ // liste de polyligne non fermées
      ['coords'=> [[1, 0],[0, 1],[-1, 0],[0, -1]], 'result'=> true],
      ['coords'=> [[1, 1],[-1, 1],[-1, -1],[1, -1]], 'result'=> true],
      ['coords'=> [[1, 1],[-1, 1],[-1, -1],[1, -1],[1, 1]], 'result'=> true],
      ['coords'=> [[1, 1],[2, 1],[2, 2],[1, 2]], 'result'=> false],
    ] as $test) {
      $coords = $test['coords'];
      $coords[] = $coords[0]; // fermeture de la polyligne
      $pol = new Polygon([$coords]);
      echo "${pol}->posInPolygon([$p0[0],$p0[1]])=",($pol->posInPolygon($p0)?'true':'false'),
           " / ",($test['result']?'true':'false'),"<br>\n";
    }
  }

  /*PhpDoc: methods
  name:  segs
  title: "segs(): array - liste des segments constituant le polygone"
  */
  function segs(): array {
    $segs = [];
    foreach ($this->geoms() as $ls)
      $segs = array_merge($segs, $ls->segs());
    return $segs;
  }
  
  /*PhpDoc: methods
  name:  inters
  title: "function intersPol(Polygon $pol, bool $verbose=false): bool - teste l'intersection entre 2 polygones"
  */
  function intersPol(Polygon $pol, bool $verbose=false): bool {
    // Si les boites ne s'intersectent pas alors les polygones non plus
    if (!$this->bbox()->inters($pol->bbox()))
      return false;
    
    // si un point de $pol est dans $this alors il y a intersection
    foreach($pol->geoms() as $i => $ls) {
      //echo "ls=$ls<br>\n";
      foreach ($ls->coords() as $j=> $pos) {
        if ($this->posInPolygon($pos)) {
          if ($verbose)
            echo "Point $i/$j de pol dans this<br>\n";
          return true;
        }
      }
    }
    // Si un point de $this est dans $pol alors il y a intersection
    foreach ($this->geoms() as $i => $ls) {
      foreach ($ls->coords() as $j=> $pos) {
        if ($pol->posInPolygon($pos)) {
          if ($verbose)
            echo "Point $i/$j de this dans pol<br>\n";
          return true;
        }
      }
    }
    // Si 2 segments s'intersectent alors il y a intersection
    foreach ($this->segs() as $i => $seg0) {
      foreach($pol->segs() as $j => $seg1) {
        if ($seg0->intersects($seg1)) {
          if ($verbose)
            echo "Segment $i de this intersecte le segment $j de geom<br>\n";
          return true;
        }
      }
    }
    // Sinon il n'y a pas intersection
    return false;
  }
  
  /*PhpDoc: methods
  name:  inters
  title: "function inters(Geometry $geom): bool - teste l'intersection entre les 2 polygones ou multi-polygones"
  */
  function inters(Geometry $geom, bool $verbose=false): bool {
    if (get_class($geom) == __NAMESPACE__.'\Polygon') {
      return $geom->intersPol($geom);
    }
    elseif (get_class($geom) == __NAMESPACE__.'\MultiPolygon') {
      return $geom->inters($this);
    }
    else
      throw new \Exception("Erreur d'appel de Polygon::inters() avec en paramètre un objet de ".get_class($geom));
  }
  static function test_inters() {
    foreach([
      [
        'title'=> "cas d'un pol2 inclus dans pol1 ss que les rings ne s'intersectent",
        'pol1'=> new Polygon([[[0,0],[10,0],[10,10],[0,10],[0,0]]]), // carré 10x10 
        'pol2'=> new Polygon([[[1,1],[9,1],[9,9],[1,9],[1,1]]]),
        'result'=> true,
      ],
      [
        'title'=> "cas d'un pol2 intersectant pol1 ss qu'aucun point de l'un ne soit dans l'autre mais que les rings s'intersectent",
        'pol1'=> new Polygon([[[0,0],[10,0],[10,10],[0,10],[0,0]]]), // carré 10x10 
        'pol2'=> new Polygon([[[-1,1],[11,1],[11,9],[-1,9],[-1,1]]]),
        'result'=> true,
      ],
    ] as $test) {
      echo "<b>$test[title]</b><br>\n";
      echo "$test[pol1]->inters($test[pol2]):<br>\n",
          "-> ", ($test['pol1']->inters($test['pol2'])?'true':'false'),
           " / ", ($test['result']?'true':'false'),"<br>\n";
    }
  }

  function draw(Drawing $drawing, array $style=[]) { $drawing->polygon($this->coords, $style); }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'Polygon'); // Test unitaire de la classe Polygon

{/*PhpDoc: classes
name: MultiPolygon
title: class MultiPolygon extends Homogeneous - Chaque polygone respecte les contraintes du Polygon
methods:
*/}
class MultiPolygon extends Homogeneous {
  const EXAMPLES = [
    "triangle unité"=> [
      "type" => "MultiPolygon",
      "coordinates" => [
        LLPos::EXAMPLES["triangle unité"]
      ],
    ]
  ];
  
  // $coords contient une liste de listes de listes de listes de 2 ou 3 nombres (LLLPos)
  function eltTypes(): array { return $this->coords ? ['Polygon'] : []; }
  
  function geoms(): array { // liste des primitives contenues dans l'objet sous la forme d'une liste d'objets
    return array_map(function(array $llpos) { return new Polygon($llpos); }, $this->coords);
  }
  
  function isValid(): bool {
    foreach ($this->geoms() as $pol)
      if (!$pol->isValid())
        return false;
    return true;
  }
  
  function getErrors(): array {
    $errors = [];
    foreach ($this->geoms() as $i => $pol) {
      if ($polErrors = $pol->getErrors())
        $errors[] = ["Erreur sur le polygone $i", $polErrors];
    }
    return $errors;
  }
  
  function area(array $options=[]): float {
    return array_sum(array_map(function(array $llpos) { return (new Polygon($llpos))->area(); }, $this->coords));
  }
  
  function filter(int $precision=9999): ?Homogeneous {
    if ($precision == 9999)
      $precision == self::$precision;
    $cclass = get_called_class();
    $coords = [];
    foreach ($this->geoms() as $pol) {
      if ($pol = $pol->filter($precision))
        $coords[] = $pol->coords();
    }
    if ($coords)
      return new $cclass($coords);
    else
      return null;
  }
  static function test_filter() {
    foreach(self::EXAMPLES as $ex) {
      $mpol = Geometry::fromGeoJSON($ex);
      echo $mpol,"->filter(3)=",json_encode($mpol->filter(3)),"<br>\n";
    }
  }
  
  /*PhpDoc: methods
  name:  inters
  title: "function inters(Geometry $geom): bool - teste l'intersection avec un polygone ou un multi-polygones"
  */
  function inters(Geometry $geom): bool {
    if (get_class($geom) == __NAMESPACE__.'\Polygon') {
      foreach($this->geoms() as $polygon) {
        if ($polygon->intersPol($geom)) // intersection entre 2 polygones
          return true;
      }
      return false;
    }
    elseif (get_class($geom) == __NAMESPACE__.'\MultiPolygon') {
      foreach($this->geoms() as $pol0) {
        foreach ($geom->geoms() as $pol1) {
          if ($pol0->intersPol($pol1)) // intersection entre 2 polygones
            return true;
        }
      }
      return false;
    }
    else
      throw new \Exception("Erreur d'appel de MultiPolygon::inters() avec un objet de ".get_class($geom));
  }
  static function test_inters() {
    foreach([
      [
        'title'=> "cas d'un pol2 inclus dans pol1 ss que les rings ne s'intersectent",
        'pol1'=> new Polygon([[[0,0],[10,0],[10,10],[0,10],[0,0]]]), // carré 10x10 
        'pol2'=> new Polygon([[[1,1],[9,1],[9,9],[1,9],[1,1]]]),
        'result'=> true,
      ],
      [
        'title'=> "cas d'un pol2 intersectant pol1 ss qu'aucun point de l'un ne soit dans l'autre mais que les rings s'intersectent",
        'pol1'=> new Polygon([[[0,0],[10,0],[10,10],[0,10],[0,0]]]), // carré 10x10 
        'pol2'=> new Polygon([[[-1,1],[11,1],[11,9],[-1,9],[-1,1]]]),
        'result'=> true,
      ],
    ] as $test) {
      echo "<b>$test[title]</b><br>\n";
      echo "$test[pol1]->inters($test[pol2]):<br>\n",
          "-> ", ($test['pol1']->inters($test['pol2']) ? 'true':'false'),
           " / ", ($test['result']?'true':'false'),"<br>\n";
      $mpol1 = new MultiPolygon([$test['pol1']->coords]);
      echo $mpol1,"->inters($test[pol2]):<br>\n",
          "-> ", ($mpol1->inters($test['pol2']) ? 'true':'false'),
           " / ", ($test['result']?'true':'false'),"<br>\n";
      $mpol2 = new MultiPolygon([$test['pol2']->coords]);
      echo $mpol1,"->inters($mpol2):<br>\n",
         "-> ", ($mpol1->inters($mpol2) ? 'true':'false'),
          " / ", ($test['result']?'true':'false'),"<br>\n";
      echo $test['pol1'],"->inters($mpol2):<br>\n",
         "-> ", ($test['pol1']->inters($mpol2) ? 'true':'false'),
          " / ", ($test['result']?'true':'false'),"<br>\n";
    }
  }
  
  /*static function haggregate(array $elts) - NON UTILISE {
    $coords = [];
    foreach ($elts as $elt)
      $coords[] = $elt->coords;
    return new MultiPolygon($coords);
  }*/

  function draw(Drawing $drawing, array $style=[]) {
    foreach ($this->coords as $coords)
      $drawing->polygon($coords, $style);
  }
}

UnitTest::class(__NAMESPACE__, __FILE__, 'MultiPolygon'); // Test unitaire de la classe MultiPolygonv
