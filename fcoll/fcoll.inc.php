<?php
namespace fcoll;
{/*PhpDoc:
name:  fcoll.inc.php
title: fcoll.inc.php - définition des classes FeatureCollection et GeoJFile
classes:
functions:
doc: |
journal: |
  21-22/5/2019:
    - suppression de $criteria dans FCTree et utilisation dans FeatureCollection::features() et FeatureCollection::bbox()
  20/5/2019:
    - chgt de FeatureCollection comme Iterator en methode features() génératrice de Feature
  18/5/2019:
    - transfert de fcoll dans geovect
    - mise en oeuvre des critères de sélection
  14/5/2019:
    - chgt du nom du fichier et de l'espace de noms
  12/5/2019:
    - restructuration du nommage
  8/5/2019:
    - chgt de principe
  30/4/2019:
    - création
includes: [ ../gegeom/gegeom.inc.php ]
*/}
require_once __DIR__.'/../gegeom/gegeom.inc.php';
   
use \gegeom\GBox;
use \gegeom\Geometry;

{/*PhpDoc: classes
name:  FeatureCollection
title: abstract class FeatureCollection - ensemble de Feature GeoJSON
methods:
doc: |
  Une FeatureCollection expose la méthode features(array $criteria), générateur de Feature GeoJSON
  $criteria définit une expression de sélection dans un FeatureCollection, il est structuré comme un array contenant:
    - soit la clé bbox et une valeur valide pour créer un GBox, la sélection correspond à l'intersection des GBox
    - soit comme clé un nom de propriété et comme valeur
      - soit une valeur atomique pour sélectionner les objets pour lesquels cette propriété correspond à cette valeur
      - soit une liste de valeurs pour sélectionner les objets pour lesquels cette propriété correspond à un des éléments
  L'expression de sélection est la conjonction des critères élémentaires.
*/}
abstract class FeatureCollection {
  const ROOT = __DIR__.'/../..'; // chemin Unix de la racine de l'arbre Apache
  protected $path; // chemin Apache de l'élément
  
  function type(): string { return 'FeatureCollection'; }
  function path(): string { return $this->path; }
  function title(): string { return basename($this->path); }
  
  /*PhpDoc: methods
  name:  features
  title: "function bbox(array $criteria): GBox - calcul de la BBox des objets respectant les critères de sélection"
  */
  function bbox(array $criteria): GBox {
    $gbox = new GBox;
    foreach ($this->features($criteria) as $feature) {
      $gbox->union(Geometry::fromGeoJSON($feature['geometry'])->bbox());
    }
    return $gbox;
  }
  
  // teste si l'objet satisfait chacun des critères
  static function meetCriteria(array $criteria, array $current): bool {
    foreach ($criteria as $name => $value) {
      if ($name == 'bbox') {
        $bbox = new GBox($value);
        $geom = Geometry::fromGeoJSON($current['geometry']);
        if (!$bbox->inters($geom->bbox()))
          return false;
      }
      elseif (isset($current['properties'][$name])) {
        if (is_array($value)) {
          if (!in_array($current['properties'][$name], $value))
            return false;
        }
        elseif ($current['properties'][$name] <> $value)
          return false;
      }
    }
    return true;
  }
  
  /*PhpDoc: methods
  name:  features
  title: "abstract function features(array $criteria): \\Generator - générateur de Feature satisfaisant aux critères"
  doc: |
    $criteria est un array contenant:
      - soit la clé bbox et une valeur valide pour créer un GBox, la sélection correspond à l'intersection des GBox
      - soit comme clé un nom de propriété et comme valeur
        - soit une valeur atomique pour sélectionner les objets pour lesquels cette propriété correspond à cette valeur
        - soit une liste de valeurs pour sélectionner les objets pour lesquels cette propriété correspond à un des éléments
    Un objet généré doit vérifier tous les critères de $criteria 
  */
  abstract function features(array $criteria): \Generator;
};

{/*PhpDoc: classes
name:  GeoJFile
title: class GeoJFile extends FeatureCollection - FeatureCollection comme fichier GeoJSON
methods:
doc: |
  Implémentation de FeatureCollection par un fichier GeoJSON
  Dans cete version, le fichier doit être structuré avec:
    - 2 lignes d'en-tête,
    - 1 ligne par Feature et
    - 1 ligne de fin composée de ']}'
  Une version ultérieure pourra prendre en charge une structuration plus souple.
*/}
class GeoJFile extends FeatureCollection {
  //protected $path; // chemin Apache de l'élément
  private $file; // descripteur du fichier
  private $key; // numéro d'objet
  
  /*PhpDoc: methods
  name:  __construct
  title: "function __construct(string $path) - initialisation du GeoJFile déterminé par son chemin Apache"
  */
  function __construct(string $path) {
    if (!is_file(self::ROOT.$path))
      throw new \Exception("Fichier $path inexistant");
    $this->path = $path;
    $this->file = fopen(self::ROOT.$this->path, 'r');
    fgets($this->file);
    fgets($this->file);
    $this->key = 0;
  }
  
  function __toString(): string { return 'GeoJFile:'.$this->path; }
  
  // génère les Feature respectant les critères
  function features(array $criteria): \Generator {
    while (true) {
      $buff = trim(fgets($this->file));
      if (strcmp($buff, "]}") == 0)
        return;
      if (substr($buff, -1)==',')
        $buff = substr($buff, 0, strlen($buff)-1);
      $current = json_decode($buff, true);
      if (self::meetCriteria($criteria, $current))
        yield $this->key++ => $current;
    }
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test unitaire des classes FeatureCollection et GeoJFile


$criteria = ['scalerank'=> 0];
$criteria = ['bbox'=> [0, 0, 180, 90]];
//$criteria = ['scalerank'=> 0, 'bbox'=> [0, 0, 180, 90]];
//$criteria = ['admin'=> 'France'];
//$criteria = ['admin'=> ['France', 'Belgium']];

  
{/*PhpDoc: functions
name:  showdir
title: "function showdir(string $dirpath=''): void - affiche les entrées d'un répertoire sous la forme d'un menu HTML"
doc: |
  $dirpath est le chemin / à la racine Apache commencant par / sauf pour la racine qui correspond à la chaine vide
*/}
function showdir(string $dirpath=''): void {
  if (!$dirpath)
    $dirpath = '/geovect/fcoll';
  if ($dh = opendir(FeatureCollection::ROOT.$dirpath)) {
    echo "<ul>\n";
    while (($file = readdir($dh)) !== false) {
      //echo $file,extension($file);
      if (strtoupper(strrchr($file,'.'))=='.GEOJSON')
        echo "<li><a href='?file=$dirpath/$file'>$file</a></li>\n";
      elseif (is_dir(FeatureCollection::ROOT."$dirpath/$file") && ($file <> '.'))
        echo "<li><a href='?file=$dirpath/$file'><b>$file</b></a></li>\n";
    }
    echo "</ul>\n";
    closedir($dh);
  }
  echo "<a href='?'><i>Retour</i></a><br>\n";
}

if (!isset($_GET['file'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>FeatureStream</title></head><body>\n";
  echo "Accès à un GeoJFile - Choisir un fichier .geojson<br>\n";
  showdir();
  die();
}

// navigation dans les répertoires
elseif (is_dir(FeatureCollection::ROOT.$_GET['file'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>fs $_GET[file]</title></head><body>\n";
  showdir($_GET['file']);
  die();
}

// $_GET[file] référence un fichier GeoJSON
else {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>fc $_GET[file]</title></head><body><pre>\n";
  $fc = new GeoJFile($_GET['file']);
  echo "fc="; print_r($fc);
  echo "criteria="; print_r($criteria);
  foreach ($fc->features($criteria) as $id => $feature)
    echo "$id : ",json_encode($feature),"\n";
}
