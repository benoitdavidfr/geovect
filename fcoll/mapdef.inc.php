<?php
namespace fcoll;
{/*PhpDoc:
name: mapdef.inc.php
title: mapdef.inc.php - définition de cartes
classes:
doc: |
journal: |
  14/5/2019:
    - création du fichier par scission de fdataset.inc.php
  13/5/2019:
    - limitation des schema et tables aux tables ayant un champ geometry
  12/5/2019:
    création
*/}
use Symfony\Component\Yaml\Yaml;
use MySql;
use gegeom\Geometry;

{/*PhpDoc: classes
name: YamFileMapDefs
title: class YamFileMapDefs extends FCTree - Fichier Yaml définissant des cartes
*/}
class YamFileMapDefs extends FCTree {
  protected $yaml;
  
  function __construct(string $path, array $yaml) { $this->path = $path; $this->yaml = $yaml; }

  function child(string $subpath) {
    return $subpath ? new MapDef($this->path.'/'.$subpath, $this->yaml['mapDefs'][$subpath]) : $this;
  }
  
  function getIterator() {
    //echo "YamFileMapDefs::getIterator()<br>\n";
    //echo "<pre>yaml="; print_r($this->yaml); echo "</pre>\n";
    $children = [];
    foreach($this->yaml['mapDefs'] as $name => $mapDef) {
      $children[$name] = new MapDef($this->path."/$name", $mapDef);
    }
    //echo "<pre>children="; print_r($children); echo "</pre>\n";
    return new \ArrayIterator($children);
  }
};

{/*PhpDoc: classes
name: YamFileMapDefs
title: class MapDef - Définition d'une carte
*/}
class MapDef {
  protected $path;
  protected $yaml;
  
  function __construct(string $path, array $yaml) { $this->path = $path; $this->yaml = $yaml; }
  function type() { return 'MapDef'; }
  function path(): string { return $this->path; }
  function title() { return $this->yaml['title']; }
  function asArray() { return $this->yaml; }
};
