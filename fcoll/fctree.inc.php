<?php
namespace fcoll;
{/*PhpDoc:
name: fctree.inc.php
title: fctree.inc.php - définition des classes FCTree, FileDir et YamlFile
classes:
doc: |
  La classe abstraite FCTree spécifie les noeuds non finaux de l'arbre des FeatureCollection.
  Elle est implantée par:
    - FileDir - répertoire de fichiers (fichiers GeoJSON ou OGR + sous-répertoires + fichiers Yaml d'extension)
    - YamFileDbServers - fichier Yaml décrivant des serveurs MySql (DbServer)
    - DbServer - serveur MySql contitué de DbSchema
    - DbSchema - Schema MySql contitué de Table
  Elle défini 3 mécanismes :
    - obtenir un objet dont on connait le path, par FCTree::create()
    - itérer un objet pour connaitre ses enfants dont le type() est:
      - soit 'FCTree'
      - soit 'FeatureCollection'
      - soit 'MapDef'
    - descendre récursivement dans l'arbre avec child()

journal: |
  15/2/2022:
    - passage en Php 8.1
  21-22/5/2019:
    - suppression du paramètre $criteria dans la gestion eds FCTree
  18/5/2019:
    - transfert de fcoll dans geovect
  14/5/2019:
    - scission du fichier en 3 pour céer database.inc.php et mapdef.inc.php
    - changement de l'espace de noms
  13/5/2019:
    - limitation des schema et tables aux tables ayant un champ geometry
  12/5/2019:
    création
includes:
  - ../gegeom/gegeom.inc.php
  - fcoll.inc.php
  - database.inc.php
  - mapdef.inc.php
*/}
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/fcoll.inc.php';

use Symfony\Component\Yaml\Yaml;
use gegeom\Geometry;

{/*PhpDoc: classes
name: FCTree
title: abstract class FCTree implements \IteratorAggregate - spécifie les noeuds non finaux de l'arbre des FeatureCollection
doc: |
*/}
abstract class FCTree implements \IteratorAggregate {
  const ROOT = __DIR__.'/../..'; // chemin absolu de la racine Apache
  const DEFAUTLTPATH = '/geovect/fcoll'; // chemin par défaut
  protected $path; // chemin Apache du répertoire ou '' pour la racine
  
  /*PhDoc: methods
  name: create
  title: static function create(?string $path=null, string $subpath='') - renvoie un objet en fonction de son path
  doc: |
    Retourne:
      - un objet FCTree, FeatureCollection ou MapDef si un tel élémént existe pour le path
      - null si aucun objet n'existe pour le path
  */
  static function create(?string $path=null, string $subpath='') {
    //echo "FCTree::create(path=$path, subpath=$subpath)<br>\n";
    if ($path === null) // valeur par défaut => chemin par défaut
      $path = self::DEFAUTLTPATH;
    //echo is_dir(self::ROOT.$path) ? "$path is dir" : "$path is NOT dir","<br>\n";
    if (is_dir(self::ROOT.$path))
      return $subpath ? null : new FileDir($path);
    elseif (is_file(self::ROOT.$path)) {
      if (strtoupper(strrchr($path,'.'))=='.GEOJSON')
          return $subpath ? null : new GeoJFile($path);
      elseif (strtoupper(strrchr($path,'.'))=='.YAML')
        return YamlFile::create($path, $subpath);
      else
        return null;
    }
    elseif (!$path || ($path=='.'))
      return null;
    else {
      //echo "LayerTree::create($path)<br>\n";
      //echo "dirname=",dirname($path),"<br>\n";
      //echo "basename=",basename($path),"<br>\n";
      return self::create(dirname($path), $subpath ? basename($path).'/'.$subpath : basename($path));
    }
  }
  
  /*PhDoc: methods
  name: create
  title: static function chooseHttp(string $path): FeatureCollection - renvoie un objet en fonction de son path http
  doc: |
    Retourne le bon type d'objet en focntion de son path http
  */
  static function chooseHttp(string $path): FeatureCollection {
    return new UGeoJSON($path);
  }
  
  /*PhDoc: methods
  name: child
  title: abstract function child(string $subpath) - demande à un objet un de ses enfants
  doc: |
    Retourne:
      - un objet FCTree, FeatureCollection ou MapDef si un tel élémént existe pour le subpath
      - null si aucun objet n'existe pour le subpath
  */
  abstract function child(string $subpath);
  
  function type(): string { return 'FCTree'; }
  function path(): string { return $this->path; }
  function title(): string { return basename($this->path); }
};

require_once __DIR__.'/database.inc.php';
require_once __DIR__.'/mapdef.inc.php';

{/*PhpDoc: classes
name: FileDir
title: class FileDir extends FCTree - FCTree correspondant à un répertoire de fichiers
*/}
class FileDir extends FCTree {
  //protected $path; // chemin Apache du répertoire ou '' pour la racine
  
  function __construct(string $path) { $this->path = $path; }
  
  // supprime l'élément /.. à la fin du path
  static function realpath(string $path): string {
    if (!$path) return '';
    $path = explode('/', $path);
    array_shift($path);
    if ((count($path) >= 2) && ($path[count($path)-1]=='..') && ($path[count($path)-2]<>'..')) {
      array_pop($path);
      array_pop($path);
    }
    //print_r($path);
    return $path ? '/'.implode('/', $path) : '';
  }
  
  // child() n'est pas utilisée pour un FileDir
  function child(string $subpath) {}
  
  function getIterator(): \Iterator {
    $children = [];
    $dirpath = (self::ROOT.$this->path);
    if ($dh = opendir($dirpath)) {
      while (($name = readdir($dh)) !== false) {
        //echo "name=$name<br>\n";
        if (is_file("$dirpath/$name")) {
          if ($child = self::create($this->path."/$name"))
            $children[$name] = $child;
        }
        elseif (is_dir("$dirpath/$name")) {
          if ($name == '..') {
            //echo "Répertoire ",$this->path,"/$name<br>\n";
            //echo "realpath='",self::realpath($this->path."/$name"),"'\n";
            $children[$name] = new self(self::realpath($this->path."/$name"));
          }
          elseif ($name <> '.')
            $children[$name] = new self($this->path."/$name");
        }
      }
      closedir($dh);
      return new \ArrayIterator($children);
    } 
  }
};

{/*PhpDoc: classes
name: YamlFile
title: class YamlFile - Fichier Yaml, sait créer l'objet en fonction du $schema
*/}
class YamlFile {
  static function create(string $path, string $subpath) {
    //echo "YamlFile::create('$path', '$subpath')<br>\n";
    $yaml = Yaml::parseFile(FCTree::ROOT.$path);
    //print_r($yaml);
    //echo '$schema=',$yaml['$schema'],"<br>\n";
    switch(isset($yaml['$schema']) ? $yaml['$schema'] : null) {
      case 'http://id.georef.eu/fcoll/DbServers': return (new YamFileDbServers($path, $yaml))->child($subpath);
      case 'http://id.georef.eu/fcoll/MapDefs': return (new YamFileMapDefs($path, $yaml))->child($subpath);
      default: return null;
    }
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test unitaire de la classe FCTree


header('Content-type: '.( true ? 'application/json' : 'text/plain'));

$parent = FCTree::create(isset($_GET['path']) ? $_GET['path'] : null);
if ($parent->type()=='FCTree') { // si l'objet est un FCTree je propose de naviguer dans ses enfants
  echo "{\"type\": \"FCTree\",\n \"children\":{\n";
  $first = true;
  foreach ($parent as $localid => $child) {
    //echo "child="; print_r($child);
    if ($first)
      $first = false;
    else
      echo ",\n";
    echo '  "',($localid == '..' ? '..' : $child->title()),'": ',
      json_encode([
        //'_SERVER'=> $_SERVER,
        'type'=> $child->type(),
        'href'=> 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?path='.$child->path(),
        'title'=> $localid == '..' ? '..' : $child->title(),
        //'html'=> "<li><a href='?path=".$child->path()."'>".($localid == '..' ? '..' : $child->title())."</a></li>\n"
      ]);
  }
  echo "}}\n";
}
elseif ($parent->type()=='FeatureCollection') {
  echo "{\"type\": \"FeatureCollection\",\n \"features\":[\n";
  $first = true;
  foreach ($parent->features([]) as $feature) {
    if ($first)
      $first = false;
    else
      echo ",\n";
    echo '  ',json_encode($feature);
  }
  echo "]}\n";
}
elseif ($parent->type()=='MapDef') {
  echo json_encode($parent->asArray());
}
else
  echo json_encode(['error'=> "type inconnu"]);