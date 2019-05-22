<?php
namespace fcoll;
{/*PhpDoc:
name: database.inc.php
title: database.inc.php - accès aux FeatureCollection stockées dans une table MySql
classes:
doc: |
  Ce fichier implémente la possibilité d'accéder à une FeatureCollection stockée dans une table MySql.
  L'accès s'effectue au travers des classes YamFileDbServers, DbServer et DbSchema implémentent la classe FCTree
  et permettant de définir les paramètres de la table (classe Table).
  2 mécanismes d'accès sont prévus:
    - en accédant à un fichier Yaml définissant les serveurs, puis dans un serveur aux schémas définis,
      puis dans un schéma aux tables définies, puis en fin à la table,
    - en définissant dans le fichier Yaml des vues et en y accédant, permettant ainsi de définir des critères de sélection
      dans la table.
  Les tables doivent comporter un attribut geom de type geometry contenant une géométrie GeoJSON en coord. géo.
  Utilise le fichier secret.inc.php qui contient les mots de passe de connexion aux bases de données sous la forme
  d'un dictionnaire associant une chaine de paramètres sans mot de passe au mot de passe correspondant.
  De plus, il est possible de définir des vues qui sont une sélection dans une table définie par un ensemble de critères. 
journal: |
  21-22/5/2019:
    - suppression de $criteria dans FCTree
    - $criteria définit une expression de sélection d'objets dans une FeatureCollection
      il est utilisé dans FeatureCollection::features() et FeatureCollection::bbox()
      il est aussi utilisé pour définir des vues qui sont eds sélections dans une table
    - ajout de la méthode Table::conjunction() qui génère la conjonction de 2 expressions
  20/5/2019:
    - chgt de Table comme Iterator en methode features() génératrice de Feature
  19/5/2019:
    - mise en oeuvre de la méthode Table::bbox()
    - transfert dans le fichier secret.inc.php les mots de passe de connexion
  18/5/2019:
    - transfert de fcoll dans geovect
    - mise en oeuvre des critères de sélection
  14/5/2019:
    - création du fichier par scission de fdataset.inc.php
  13/5/2019:
    - limitation des schema et tables aux tables ayant un champ geometry
  12/5/2019:
    création
includes: [../gegeom/unittest.inc.php, fctree.inc.php, secret.inc.php]
*/}
require_once __DIR__.'/../gegeom/unittest.inc.php';
require_once __DIR__.'/fctree.inc.php';

use Symfony\Component\Yaml\Yaml;
use MySql;
use gegeom\Geometry;
use gegeom\GBox;
use unittest\UnitTest;


{/*PhpDoc: classes
name: Table
title: class Table extends FeatureCollection - Table MySql contenant des Feature
*/}
class Table extends FeatureCollection {
  //protected $path; // chemin Apache de l'élément
  private $params; // paramètres de connexion ss mot de passe
  private $schemaTable; // schema et table
  private $criteria; // critères définissant la vue
  private $key; // clé courante
  
  // utilise le fichier secret.inc.php pour ajouter aux paramètres de connexion MySQL le mot de passe
  static function passwd(string $params): string {
    if (!is_file(__DIR__.'/secret.inc.php'))
      throw new \Exception("Erreur absence de fichier des mots de passe");
    $passwds = require(__DIR__.'/secret.inc.php');
    if (!isset($passwds[$params]))
      throw new \Exception("Pas de mot de passe pour $params");
    $passwd = $passwds[$params];
    return str_replace('@', ":$passwd@", $params);
  } 

  function __construct(string $path, string $params, string $schemaTable='', array $criteria=[]) {
    //echo "Table::__construct($path, params, schemaTable=$schemaTable, criteria=",json_encode($criteria),")<br>\n"; //die();
    $this->path = $path;
    $this->params = $params;
    $this->schemaTable = $schemaTable;
    $this->criteria = $criteria;
    $this->key = 0;
  }
  
  function __toString(): string {
    return 'Table:'.$this->schemaTable()
      .($this->criteria ? ' / '.json_encode($this->criteria, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : '');
  }
  function title(): string { return $this->__toString(); }
  
  /*PhDoc: methods
  name: conjunction
  title: "static function conjunction(array $criteria1, array $criteria2): ?array - conjonction de 2 ens. de critères"
  doc: |
    Permet de calculer la conjunction des critères définis par une vue et ceux définis dans features()
    Retourne null en cas d'incompatibilité, cad que la conjonction correspond à une expression toujours fausse
  */
  static function conjunction(array $criteria1, array $criteria2): ?array {
    $result = $criteria2;
    foreach ($criteria1 as $name => $value) {
      if (!isset($result[$name]))
        $result[$name] = $value;
      else { // $name défini dans les 2 ensembles de critères
        if ($name == 'bbox') {
          $bbox1 = new GBox($result['bbox']);
          $bbox2 = new GBox($value);
          if ($bbox = $bbox1->intersects($bbox2))
            $result['bbox'] = $bbox->asArray();
          else
            return null;
        }
        elseif (is_array($value) && is_array($result[$name])) {
          if ($int = array_intersect($value, $result[$name]))
            $result[$name] = $int;
          else
            return null;
        }
        elseif (is_array($value)) {
          if (!in_array($result[$name], $value))
            return null;
        }
        elseif (is_array($result[$name])) {
          if (in_array($value, $result[$name]))
            $result[$name] = $value;
          else
            return null;
        }
        elseif ($result[$name] <> $value)
          return null;
      }
    }
    return $result;
  }
  static function test_conjunction() {
    echo "<pre>";
    foreach([
      /*[['a'=>5],['b'=>6]],
      [['a'=>5],['a'=>6]],
      [['a'=>[5,6]],['a'=>6]],
      [['a'=>5],['a'=>[5,6]]],
      [['a'=>[5,6,7,8]],['a'=>[3,4,5,6]]],
      [['a'=>[6,7,8]],['a'=>[3,4,5]]],*/
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,180,90]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,0,0]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,-1,-1]]],
    ] as $pair) {
      print_r([
        ['parameters'=> $pair],
        Table::conjunction($pair[0], $pair[1]) ?? 'empty',
      ]);
    }
  }
  
  // génère la partie where de la requête, retourne null ssi les critères sont contradictoires
  function where(array $criteria): ?string {
    $criteria = self::conjunction($this->criteria, $criteria);
    if ($criteria === null)
      return null;
    if (!$criteria)
      return '';
    $where = [];
    foreach ($criteria as $name => $value) {
      if ($name == 'bbox')
        $where[] = "MBRIntersects(geom, ST_LineFromText('LINESTRING($value[0] $value[1],$value[2] $value[3])'))";
      elseif (is_array($value))
        $where[] = "$name in ('".implode("','", $value)."')";
      else
        $where[] = "$name = '$value'";
    }
    return ' where '.implode(' and ', $where);
  }
  static function test_where() {
    echo "<pre>";
    foreach([
      [['a'=>5],['b'=>6]],
      [['a'=>5],['a'=>6]],
      [['a'=>[5,6]],['a'=>6]],
      [['a'=>5],['a'=>[5,6]]],
      [['a'=>[5,6,7,8]],['a'=>[3,4,5,6]]],
      [['a'=>[6,7,8]],['a'=>[3,4,5]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,180,90]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,0,0]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,-1,-1]]],
    ] as $pair) {
      print_r([
        $pair,
        (new Table('', '', '', $pair[0]))->where($pair[1]) ?? 'empty',
      ]);
    }
  }
  
  // calcule le bbox des géométries contenus dans la table
  function bbox(array $criteria): GBox {
    $gbox = new GBox;
    if (null === $where = $this->where($criteria))
      return $gbox;
    $query = "select AsWKT(ST_Envelope(geom)) bbox from ".$this->schemaTable().$where;
    //echo "query=$query<br>\n";
    MySql::open(self::passwd($this->params));
    foreach(MySql::query($query) as $tuple) {
      //echo json_encode(Geometry::fromWkt($tuple['bbox'])->bbox()->asArray()),"<br>\n";
      $gbox->union(Geometry::fromWkt($tuple['bbox'])->bbox());
    }
    MySql::close();
    return $gbox;
  }
  static function test_bbox() {
    $table = new Table(
      '',
      'mysql://bdavid@mysql-bdavid.alwaysdata.net/bdavid_route500',
      'bdavid_route500.limite_administrative',
      [ 'nature'=> ["Limite côtière", "Frontière internationale"] ],
    );
    //$table = new Table('', 'mysql://root@172.17.0.3/sys', [], 'ne_110m.coastline');
    echo $table->bbox([]),"<br>\n";
  }

  function schemaTable(): string {
    if ($this->schemaTable)
      return $this->schemaTable;
    else {
      $path = explode('/', $this->path);
      $tablename = array_pop($path);
      $schemaname = array_pop($path);
      return "$schemaname.$tablename";
    }
  }

  function features(array $criteria): \Generator {
    //echo "Table::rewind()<br>\n";
    if (null === $where = $this->where($criteria))
      return;
    $query = "select *, AsWKT(geom) wkt from ".$this->schemaTable().$where;
    //echo "query=$query<br>\n";
    MySql::open(self::passwd($this->params));
    foreach (MySql::query($query) as $tuple) {
      $wkt = $tuple['wkt'];
      unset($tuple['geom']);
      unset($tuple['wkt']);
      //print_r($tuple);
      yield $this->key++ => [
        'properties'=> $tuple,
        'geometry'=> Geometry::fromWkt($wkt)->asArray(),
      ];
    }
    return;
  }
  static function test_features() {
    //$table = new Table('', 'mysql://root@172.17.0.3/sys', 'ne_110m.coastline');
    $table = new Table(
      '',
      'mysql://bdavid@mysql-bdavid.alwaysdata.net/bdavid_route500',
      'bdavid_route500.limite_administrative',
      [ 'nature'=> ["Limite côtière", "Frontière internationale"] ],
    );
    echo "<pre>\n";
    foreach ($table->features(['bbox'=>[-10,49,0,51]]) as $id => $feature) {
      echo "$id : ",json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
    }
    echo "</pre>\n";
  }
};
UnitTest::class(__NAMESPACE__, __FILE__, 'Table');

{/*PhpDoc: classes
name: DbSchema
title: class DbSchema extends FCTree - Schema MySql constitué de Table
*/}
class DbSchema extends FCTree {
  protected $params;
  
  function __construct(string $path, string $params) { $this->path = $path; $this->params = $params; }
  
  function child(string $subpath) {
    //echo "DbSchema::child('$subpath')<br>\n";
    if (!$subpath)
      return $this;
    $subpath = explode('/', $subpath);
    $childname = array_shift($subpath);
    $child = new Table($this->path.'/'.$childname, $this->params);
    if (!$subpath)
      return $child;
    else
      return $child->child($criteria, implode('/',$subpath));
  }
  
  function getIterator() {
    MySql::open(Table::passwd($this->params));
    $children = [];
    $schemaname = basename($this->path);
    $query = "select distinct table_name from information_schema.columns
              where table_schema='$schemaname' and data_type='geometry'";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $children[$tuple['table_name']] = new Table($this->path.'/'.$tuple['table_name'], $this->params);
    }
    MySql::close();
    return new \ArrayIterator($children);
  }
};

{/*PhpDoc: classes
name: DbServer
title: class DbServer extends FCTree - serveur MySql constitué de DbSchema
*/}
class DbServer extends FCTree {
  protected $title;
  protected $params;

  function __construct(string $path, array $yaml) {
    $this->path = $path;
    $this->title = $yaml['title'];
    $this->params = $yaml['params'];
  }
  
  function title(): string { return $this->title; }
  
  function child(string $subpath) {
    //echo "DbServer::child('$subpath')<br>\n";
    if (!$subpath)
      return $this;
    $subpath = explode('/', $subpath);
    $childname = array_shift($subpath);
    $child = new DbSchema($this->path.'/'.$childname, $this->params);
    if (!$subpath)
      return $child;
    else
      return $child->child(implode('/',$subpath));
  }
  
  function getIterator() {
    MySql::open(Table::passwd($this->params));
    $children = [];
    $query = "select distinct table_schema from information_schema.columns where data_type='geometry'";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $children[$tuple['table_schema']] = new DbSchema(
          $this->path.'/'.$tuple['table_schema'],
          $this->params,
      );
    }
    MySql::close();
    return new \ArrayIterator($children);
  }
};

{/*PhpDoc: classes
name: YamFileDbServers
title: class YamFileDbServers extends FCTree - fichier Yaml décrivant des serveurs MySql et des vues
doc: |
  Le fichier Yaml doit comporter un champ dbServers décrivant des serveurs de bases de données
  et éventuellement un champ views définissant des vues.
*/}
class YamFileDbServers extends FCTree {
  protected $yaml;
  
  function __construct(string $path, array $yaml) { $this->path = $path; $this->yaml = $yaml; }
  
  function child(string $subpath) {
    //echo "YamFileDbServers::child(criteria, '$subpath')<br>\n"; //die();
    if (!$subpath)
      return $this;
    $subpath = explode('/', $subpath);
    $typeChildname = array_shift($subpath);
    $pos = strpos($typeChildname, '-');
    $type = substr($typeChildname, 0, $pos);
    $childname = substr($typeChildname, $pos+1);
    if ($type == 'dbServers')
      $child = new DbServer($this->path.'/dbServers-'.$childname, $this->yaml['dbServers'][$childname]);
    elseif ($type == 'views') {
      //echo "<pre>yaml="; print_r($this->yaml);
      //echo "childname=$childname<br>\n";
      $view = $this->yaml['views'][$childname];
      $child = new Table(
        $this->path."/views-$childname",
        $this->yaml['dbServers'][$view['server']]['params'],
        "$view[schema].$view[table]",
        isset($view['criteria']) ? $view['criteria'] : []
      );
    }
    else
      throw new \Exception("Cas non prévu");
    if (!$subpath)
      return $child;
    else
      return $child->child(implode('/',$subpath));
  }
  
  function getIterator() {
    $children = [];
    foreach($this->yaml['dbServers'] as $name => $dbServer) {
      $children[$name] = new DbServer($this->path."/dbServers-$name", $dbServer);
    }
    if (isset($this->yaml['views']))
    foreach($this->yaml['views'] as $name => $view) {
      //echo "<pre>yaml="; print_r($this->yaml);
      $children[$name] = new Table(
        $this->path."/views-$name",
        $this->yaml['dbServers'][$view['server']]['params'],
        "$view[schema].$view[table]",
        isset($view['criteria']) ? $view['criteria'] : []
      );
    }
    return new \ArrayIterator($children);
  }
  static function test_iterator() {
    $servers = new YamFileDbServers('/geovect/fcoll/databases.yaml', Yaml::parseFile(__DIR__.'/databases.yaml'), []);
    echo "<pre>servers="; print_r($servers); echo "</pre>\n";
    foreach ($servers as $id => $item) {
      echo "$id : ",$item->title(),"<br>\n";
      //echo "<pre>item="; print_r($item); echo "</pre>\n";
    }
  }
};
UnitTest::class(__NAMESPACE__, __FILE__, 'YamFileDbServers');
