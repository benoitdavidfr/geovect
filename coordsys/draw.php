<?php
/*PhpDoc:
name: draw.php
title: draw.php - Dessin du planisphère dans différentes projections cartographiques
includes:
  - ../gegeom/gegeom.inc.php
  - ../gegeom/gddrawing.inc.php
  - ../fcoll/fcoll.inc.php
  - full.inc.php
  - light.inc.php
journal: |
  21/5/2019:
    - adaptation à la nouvelle interface de fcoll
  4-5/5/2019:
    - adaptation à la nouvelle interface de gegeom
*/
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/../gegeom/gddrawing.inc.php';
require_once __DIR__.'/../fcoll/fcoll.inc.php';

use \gegeom\GBox;
use \gegeom\Geometry;
use \gegeom\GdDrawing;
use \fcoll\GeoJFile;

// Itérateur des lignes de latitude et longitude constantes
// Chaque itération génère un Feature ayant une géométrie LineString
// Les lignes doivent être incluses dans le GBox world
class Graticule implements Iterator {
  private $world; // GBox contraignant les lignes
  private $step = 10;
  private $i = 0;
  private $lon = 0;
  private $lat = 0;
  
  function __construct(GBox $world, float $step) { $this->world = $world; $this->step = $step; }
  
  // implémentation du générateur au-dessus de l'itérateur pour éviter de récrire la classe
  function features(array $criteria) {
    $this->rewind();
    while ($this->valid()) {
      yield $this->key() => $this->current();
      $this->next();
    }
  }
    
  function rewind(): void { $this->i = 0; $this->lon = -180; $this->lat = -90; }
  function valid(): bool { return $this->lat <= 80; }
  
  function current(): array {
    // par défaut comprend les 2 segments au nord et à l'est du point courant
    $coordinates = [
      [$this->lon, min($this->lat+$this->step, $this->world->north())],
      [$this->lon, $this->lat],
      [$this->lon+$this->step, $this->lat],
    ];
    // si c'est le dernier carré de la ligne alors ajout du segment à l'Est
    if ($this->lon + $this->step == 180) {
      $coordinates[] = [$this->lon + $this->step, $this->lat+$this->step];
    }
    // Si c'est la première ligne alors uniquement le segment au Nord 
    if ($this->lat == -90) {
      $coordinates = [
        [$this->lon, $this->world->south()],
        [$this->lon, $this->lat+$this->step],
      ];
    }
    return [
      'type'=> 'Feature',
      'properties'=>[
        'i'=> $this->i,
        'lon'=> $this->lon,
        'lat'=> $this->lat,
      ],
      'geometry'=> [
        'type'=> 'LineString',
        'coordinates'=> $coordinates,
      ],
    ];
  }
  
  function key(): int { return $this->i; }
  
  function next(): void {
    $this->lon += $this->step;
    if ($this->lon >= 180) {
      $this->lat += $this->step;
      $this->lon = -180;
    }
    $this->i++;
  }
};

// choix de la projection et de la taille du dessin
if (!isset($_GET['lib']) || !isset($_GET['crs'])) {
  require_once __DIR__.'/full.inc.php';
  $big = isset($_GET['size']) ? 'size=big&amp;' : '';
  echo "light<ul>\n";
  foreach (['geo','WebMercator','WorldMercator','Sinusoidal','Lambert93','Legal'] as $crs)
    echo "<li><a href='?${big}lib=light&amp;crs=$crs'>$crs</a></li>\n";
  echo "</ul>\n";
  echo "full<ul>\n";
  foreach (array_keys(CRS::registre()['SIMPLE']) as $crs)
    echo "<li><a href='?${big}lib=full&amp;crs=$crs'>$crs</a></li>\n";
  echo "</ul>\n";
  if ($big)
    echo "<a href='?'>small</a><br>\n";
  else
    echo "<a href='?size=big'>big</a><br>\n";
  die();
}
// initialisation de la projection de light
elseif ($_GET['lib']=='light') { // utilisation de light
  require_once __DIR__.'/light.inc.php';
  if ($_GET['crs']=='geo') { // en coord. géo.
    $projPos = function(array $pos) { return $pos; };
    $world = new gegeom\GBox([[-180,-90],[180,90]]);
    $projWorld = $world->proj($reprojPos);
  }
  else {
    $crs = $_GET['crs'];
    $projPos = function(array $pos) use($crs) { return $crs::proj($pos); };
    $world = new gegeom\GBox($crs::limits());
    $projWorld = method_exists($crs, 'projectedLimits') ? new gegeom\EBox($crs::projectedLimits()) : $world->proj($projPos);
  }
}
// initialisation de la projection de full
elseif ($_GET['lib']=='full') { // utilisation de full
  require_once __DIR__.'/full.inc.php';
  if ($crs = CRS::S($_GET['crs'])) { // CRS défini dans le registre SIMPLE
    $projPos = function(array $pos) use($crs) { return $crs->proj($pos); };
    $world = new gegeom\GBox($crs->limits());
    $projWorld = method_exists($crs, 'projectedLimits') ? new EBox($crs->projectedLimits()) : $world->proj($projPos);
  }
  else
    die("Erreur interne");
}
else
  die("Erreur interne");

// A ce stade les variables suivantes doivent être définies
// - $reprojPos : la fonction de projection : pos -> pos
// - $world : l'emprise à traiter en coord. géo. définie comme GBox 
// - $projWorld : l'emprise à traiter en coord. projetées définie comme EBox 

// initialiosation de big pour affichage détaillé
$big = isset($_GET['size']) ? true : false;
if ($big)
  ini_set('memory_limit', '1280M');

// définition des couches à afficher sous la forme [{couleur} => {FeatureSet}]
$ne = $big ? 'ne_10m' : 'ne_110m';
$layers = [
  [ 'features' => new Graticule($world, 10), 'style'=> ['stroke'=> 0xE0E0E0]],
  [ 'features' => new Graticule($world, 30), 'style'=> ['stroke'=> 0x808080]],
  [ 'features' => new GeoJFile("/geovect/fcoll/$ne/coastline.geojson"),
    'style'=> ['stroke'=> 0x0000FF]],
  [ 'features' => new GeoJFile("/geovect/fcoll/$ne/admin_0_boundary_lines_land.geojson"),
    'style'=> ['stroke'=> 0xFF0000]],
  [ 'features' => new GeoJFile("/geovect/fcoll/$ne/admin_0_countries.geojson"),
    'criteria' => ['admin'=>'France'],
    'style'=> ['fill'=> gegeom\Drawing::COLORNAMES['DarkOrange']]],
];

// création du dessin
$drawing = new GdDrawing(1150 * ($big?10:1), 800 * ($big?10:1), $projWorld);

foreach ($layers as $layer) {
  foreach ($layer['features']->features($layer['criteria'] ?? []) as $feature) {
    //echo "feature="; print_r($feature);
    $geom = Geometry::fromGeoJSON($feature['geometry']);
    if ($geom->bbox()->inters($world)) {
      $geom->proj($projPos)->draw($drawing, $layer['style']);
    }
  }
}

$drawing->flush('', false);