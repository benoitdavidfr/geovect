<?php
namespace fcoll;
{/*PhpDoc:
name:  fcoll.inc.php
title: fcoll.inc.php - définition des classes FeatureCollection et GeoJFile
classes:
functions:
doc: |
  namespace fcoll;
  Attention, GeoJFile::features() ne marche pas si dans un objet GeoJSON le champ geometry est avant properties.
  Voir /cartoenedis/geojfile.inc.php pour une solution plus générique

journal: |
  23-25/2/2020:
    - détection d'un cas non traité et redév. d'une solution dans /cartoenedis/geojfile.inc.php
  25/5/2019:
    - lecture plus générique des fichiers GeoJSON, y c. distants
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
includes: [ ../gegeom/gegeom.inc.php, criteria.inc.php ]
*/}
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/criteria.inc.php';
   
use \gegeom\GBox;
use \gegeom\Geometry;

{/*PhpDoc: classes
name:  FeatureCollection
title: abstract class FeatureCollection - ensemble de Feature GeoJSON
methods:
doc: |
  Une FeatureCollection expose la méthode features(array $criteria), générateur de Feature GeoJSON
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
  Implémentation de FeatureCollection par un fichier GeoJSON.
  Plusieurs limites sur les fichiers GeoJSON.
*/}
class GeoJFile extends FeatureCollection {
  //const LENGTH = 10; // taille du buffer utilisée en tests
  const LENGTH = 1000*1000; // taille du buffer de lecture
  //protected $path; // chemin Apache de l'élément

  /*PhpDoc: methods
  name:  __construct
  title: "function __construct(string $path) - initialisation du GeoJFile déterminé par son chemin Apache"
  */
  function __construct(string $path) {
    if ((strncmp($path, 'http://', 7)==0) || is_file(self::ROOT.$path))
      $this->path = $path;
    else
      throw new \Exception("Fichier $path inexistant");
  }

  function __toString(): string { return 'GeoJFile:'.$this->path; }

  /*PhpDoc: methods
  name:  features0
  title: "function features0(array $criteria): \\Generator - génère les Feature respectant les critères"
  doc: |
    Dans cete version, le fichier doit être structuré avec:
      - 2 lignes d'en-tête,
      - 1 ligne par Feature et
      - 1 ligne de fin composée de ']}'
  */
  function features0(array $criteria): \Generator {
    $file = fopen(self::ROOT.$this->path, 'r');
    fgets($file);
    fgets($file);
    $key = 0;
    while (true) {
      $buff = trim(fgets($file));
      if (strcmp($buff, "]}") == 0)
        return;
      if (substr($buff, -1)==',')
        $buff = substr($buff, 0, strlen($buff)-1);
      $current = json_decode($buff, true);
      if (Criteria::meetCriteria($criteria, $current))
        yield $key++ => $current;
    }
  }

  /*PhpDoc: methods
  name:  readGeoJFile
  title: "function readGeoJFile(string $path): \\Generator - lit un fichier GeoJSON et génère les Features"
  doc: |
    Plusieurs limites:
      - des espaces autorisés en JSON ne sont pas permis
      - GeometryCollection n'est pas traité
      - les propriétés de la FeatureCollection autres que type et features doivent être simples, cad:
        - une chaine simple de caractères, ex: "title" : "nom du jeu de données"
        - un array simple, ex: "bbox" : [0, 0, 100, 100]
  */
  static function readGeoJFile(string $path): \Generator {
    $file = fopen($path, 'r');
    $jsonValPattern = '("[^"]*"|\[[^]]*\])'; // motif des valeurs JSON simples acceptées dans les métadonnées
    $mdPattern = '\s*"[^"]+"\s*:\s*'.$jsonValPattern.'\s*,'; // motif des métadonnées éventuelles
    // motif du début du fichier
    $startPattern = '\s*{\s*"type"\s*:\s*"FeatureCollection"\s*,('.$mdPattern.')*\s*"features"\s*:\s*\[';
    $buff = trim(fgets($file, self::LENGTH));
    while (!preg_match("!^$startPattern!", $buff, $matches)) { // lecture des paquets nécessaires
      $buff2 = fgets($file, self::LENGTH);
      if ($buff2 === FALSE)
        throw new \Exception("Erreur dans GeoJFile::readGeoJFile() sur buff='$buff'");
      $buff .= trim($buff2);
    }
    //echo "buff=!$buff!<br>\n";
    $buff = substr($buff, strlen($matches[0]));
    //echo "En-tête lue, reste $buff"; die();
    $geomPattern = '{"type":"(Point|MultiPoint|LineString|MultiLineString|Polygon|MultiPolygon)","coordinates":[^}]*}';
    $featPattern = '{"type":"Feature","properties":{[^}]*},"geometry":'.$geomPattern.'}';
    while (true) { // itération sur les Feature
      while (!preg_match("!^,?($featPattern)!", $buff, $matches)) { // lecture des paquets nécessaires
        $buff2 = fgets($file, self::LENGTH);
        if ($buff2 === FALSE) {
          if ($buff == ']}')
            return;
          else
            throw new \Exception("Erreur dans GeoJFile::readGeoJFile() sur '$buff'");
        }
        $buff .= trim($buff2);
      }
      $buff = substr($buff, strlen($matches[0]));
      //echo "objet lu: $matches[1]<br>\n";
      $current = json_decode($matches[1], true);
      if (json_last_error() != JSON_ERROR_NONE)
        throw new \Exception("Erreur json_decode() dans GeoJFile::readGeoJFile() sur $matches[1]");
      yield $current;
    }
  }
  
  /*PhpDoc: methods
  name:  features
  title: "function features(array $criteria): \\Generator - génère les Feature respectant les critères"
  doc: |
    Version plus générique de génération des Features avec encore cependant plusieurs limites:
      - des espaces autorisés en JSON ne sont pas permis
      - GeometryCollection n'est pas traité
      - les propriétés de la FeatureCollection autres que type et features doivent être simples, cad:
        - une chaine simple de caractères, ex: "title" : "nom du jeu de données"
        - un array simple, ex: "bbox" : [0, 0, 100, 100]
  */
  function features(array $criteria): \Generator {
    $path = ((strncmp($this->path, 'http://', 7)==0)) ? $this->path : self::ROOT.$this->path;
    $key = 0;
    foreach (self::readGeoJFile($path) as $feature) {
      if (Criteria::meetCriteria($criteria, $feature)) {
        //echo "objet lu<br>\n";
        yield $key++ => $feature;
      }
    }
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test unitaire des classes FeatureCollection et GeoJFile

if (0) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>fc</title></head><body><pre>\n";
  $fc = new GeoJFile('http://localhost/geovect/fcoll/ne_110m/coastline.geojson');
  echo "fc="; print_r($fc);
  foreach ($fc->features([]) as $id => $feature)
    echo "$id : ",json_encode($feature),"\n";
  
}

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
