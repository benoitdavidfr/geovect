<?php
namespace fcoll;
{/*PhpDoc:
name:  geojfileviewer.php
title: geojfileviewer.php - Visualisation de fichiers GeoJSON (ancienne version)
classes:
doc: |
  Version ancienne du viewer limitée aux GeoJFile n'utilisant pas FCTree à l'exception de FCTree::ROOT
journal: |
  18/5/2019:
    - transfert de fcoll dans geovect
  12/5/2019:
  - transfert dans geojson
  8/5/2019:
  - création
includes:
  - ../gegeom/gegeom.inc.php
  - fcoll.inc.php
  - fctree.inc.php
*/}
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/fcoll.inc.php';
require_once __DIR__.'/fctree.inc.php';

use \gegeom\GBox;
use \gegeom\Geometry;

/* Paramètres:
 - map: la carte en cours d'édition encodée en JSON
 - file: si fichier alors couche à ajouter sinon répertoire courant
 - action: action à effectuer hors ajout de couche
 - layer: no de couche à supprimer pour action=delLayer
*/

// génération d'une chaine des paramètres pour URL avec encodage des valeurs
function genparams(array $params): string {
  $str = '';
  foreach($params as $k => $v)
    $str .= '&amp;'.$k.'='.rawurlencode($v);
  return $str;
}

// edite le paramètre file et conserve les autres sauf ceux modifiés par $newparamvalues
// lorsque $newparamvalues[k] est null alors le paramètre est supprimé
function choosefile(string $dirpath='', $newparamvalues=[]): string {
  echo "choosefile($dirpath, ",json_encode($newparamvalues),")<br>\n";
  if (!$dirpath)
    $dirpath = '/geovect/fcoll';
  $params = []; // liste des params à intégrer dans les URL
  foreach ($_GET as $k => $v) {
    if ($k <> 'file') {
      if (array_key_exists($k, $newparamvalues)) {
        //echo "clé $k existe dans newparamvalues<br>\n";
        if ($newparamvalues[$k]) {
          //echo "newparamvalues[$k]=$newparamvalues[$k]<br>\n";
          $params[$k] = $newparamvalues[$k];
        }
        //else { echo "newparamvalues[$k] est faux<br>\n"; }
        unset($newparamvalues[$k]);
      }
      else
        $params[$k] = $v;
    }
    elseif (!$dirpath)
      $dirpath = $v;
  }
  foreach ($newparamvalues as $k => $v)
    if ($v)
      $params[$k] = $v;
  if ($dh = opendir(FCTree::ROOT.$dirpath)) {
    $str = "<ul>\n";
    while (($file = readdir($dh)) !== false) {
      //echo $file,extension($file);
      if (strtoupper(strrchr($file,'.'))=='.GEOJSON')
        $str .= "<li><a href='?file=$dirpath/$file".genparams($params)."'>$file</a></li>\n";
      elseif (is_dir(FCTree::ROOT."$dirpath/$file")){
        if ($file == '..') {
          $parent = dirname($dirpath);
          $str .= "<li><a href='?file=$parent".genparams($params)."'><b>$file</b></a></li>\n";
        }
        elseif ($file <> '.')
          $str .= "<li><a href='?file=$dirpath/$file".genparams($params)."'><b>$file</b></a></li>\n";
      }
    }
    $str .= "</ul>\n";
    closedir($dh);
    return $str;
  }
}

if (!isset($_GET['file'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Viewer</title></head><body>\n";
  echo "Viewer - Choisir un fichier .geojson<br>\n";
  
  if (isset($_GET['map']))
    echo $_GET['map'];
  echo choosefile();
  echo "<a href='test.php'>Ou effectuer les tests de gegeom</a><br>\n";
  die();
}

// navigation dans les répertoires avant que la carte ne soit créée
elseif (!isset($_GET['map']) && isset($_GET['file']) && is_dir(__DIR__."/..$_GET[file]")) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Viewer $_GET[file]</title></head><body>\n";
  if (isset($_GET['map']))
    echo $_GET['map'];
  echo choosefile();
  echo "<a href='?'><i>Retour</i></a><br>\n";
  die();
}

// Fabrique un bouton Html
function html_button(array $hiddens, $label) {
  $html = '<form>';
  foreach ($hiddens as $name => $value)
    $html .= "<input type='hidden' name='$name' value=\"".htmlspecialchars($value)."\"/>";
  $html .= "<input type='submit' value=\"".htmlspecialchars($label)."\"></form>\n";
  return $html;
}

function html_textInput(array $hiddens, string $name, int $size, string $value) {
  $html = '<form>';
  foreach ($hiddens as $hname => $hvalue)
    $html .= "<input type='hidden' name='$hname' value=\"".htmlspecialchars($hvalue)."\"/>";
  $html .= "<input type='text' name='$name' size=$size value='".htmlspecialchars($value)."'></form>\n";
  return $html;
}

// Fabrique un selecteur
function html_select($name, $options) {
  $select_options = "<select name='$name'>";
  $no=0;
  foreach ($options as $code => $label) {
    if (is_int($code) and ($code==$no))
      $code = $label;
    $selected = ((isset($_GET[$name]) and ($_GET[$name]==$code)) ? ' selected' :'');
    $select_options .= "<option value='$code'$selected>$label</option>";
    $no++;
  }
  return $select_options.'</select>';
}

class Map {
  const DEFAULTPARAMS = [
    'world'=> null,
    'width'=> ['small'=> 600, 'big'=> 1600],
    'height'=> ['small'=> 800, 'big'=> 800],
    'layers'=>[]
  ];
  protected $world; // GBox ou null
  protected $width; // [{size}=> entier]
  protected $height; // [{size}=> entier]
  protected $layers; // [['file'=> {filepath}, 'style'=>{style}]]
  
  static function decode(string $str) { return new Map(json_decode($str, true)); }
  
  function __construct(array $params=self::DEFAULTPARAMS) {
    $this->world = $params['world'];
    $this->width = $params['width'];
    $this->height = $params['height'];
    $this->layers = $params['layers'];
  }
  
  function layers() { return $this->layers; }
  
  function asArray() {
    return [
      'world'=> $this->world ? $this->world->asArray() : null,
      'width'=> $this->width,
      'height'=> $this->height,
      'layers'=> $this->layers
    ];
  }
  
  function encode(int $options=0) {
    return \json_encode($this->asArray(), $options|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  }
  
  // ajout d'une layer
  function addLayer(array $layer) { $this->layers[] = $layer; }
  
  // génération d'un bouton de suppression de la couche $ilayer
  function delButton(string $ilayer) {
    //return '';
    echo "file=$_GET[file]<br>\n";
    return html_button([
        'map'=> $this->encode(),
        'file'=> is_file(__DIR__."/..$_GET[file]") ? dirname($_GET['file']) : $_GET['file'],
        'action'=> 'delLayer',
        'layer'=> $ilayer,
      ],
      '-');
  }
  
  // suppression de la couche $ilayer
  function delLayer(string $ilayer) {
    $layers = [];
    foreach ($this->layers as $i => $layer) {
      if ($i <> $ilayer)
        $layers[] = $layer;
    }
    $this->layers = $layers;
  }
  
  // éditeur de couleur, génère un textInput avec la valeur de la couleur stroke/fill
  // la modification de cette valeur génère une action setstroke/setfill avec la nouvelle couleur
  function colorEditor(string $strfill, int $ilayer): string {
    $val = isset($this->layers[$ilayer]['style'][$strfill]) ?
      $this->layers[$ilayer]['style'][$strfill]
        : ($strfill=='stroke' ? 0x000000 : 0x808080);
    $str = "<form><input type='text' name='color' size=6 value='".sprintf("%06x", $val)."'></form>";
    $str = html_textInput([
      'map'=> $this->encode(),
      'file'=> is_file(__DIR__."/..$_GET[file]") ? dirname($_GET['file']) : $_GET['file'],
      'layer'=> $ilayer,
      'action'=> 'set'.$strfill,
    ], 'color', 6, sprintf("%06x", $val));
    return $str;
  }
  
  // affecte la fill-color de la couche $ilayer avec $color
  function setFillColor(int $ilayer, string $color) {
    echo "setFillColor($ilayer, $color)<br>\n";
    $this->layers[$ilayer]['style']['fill'] = hexdec($color);
  }
  
  // affecte la stroke-color de la couche $ilayer avec $color
  function setStrokeColor(int $ilayer, string $color) {
    echo "setStrokeColor($ilayer, $color)<br>\n";
    $this->layers[$ilayer]['style']['stroke'] = hexdec($color);
  }
  
  // éditeur d'opacité
  function opacityEditor(string $strfill, int $ilayer): string {
    return '';
  }
  
  // génère le formulaire d'édition d'une carte
  function form(): string {
    //return '';
    $str = "<table border=1>\n";
    foreach($this->layers as $i => $layer) {
      $str .= "<tr><td>$layer[path]</td>"
            ."<td>".$this->colorEditor('stroke', $i)."</td>"
            ."<td>".$this->opacityEditor('stroke', $i)."</td>"
            ."<td>".$this->colorEditor('fill', $i)."</td>"
            ."<td>".$this->opacityEditor('fill', $i)."</td>"
            ."<td>".$this->delButton($i)."</td></tr>\n";
    }
    $str .= "</table>\n";
    return $str;
  }
  
  function setWorld(GBox $world): void { $this->world = $world; }
};


// affichage de la carte
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Viewer $_GET[file]</title></head><body>\n";
echo "<pre>GET=",json_encode($_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"</pre>\n";
$map = isset($_GET['map']) ? Map::decode($_GET['map']) : new Map();

// ajout d'une couche
if (isset($_GET['file']) && is_file(FCTree::ROOT.$_GET['file'])) {
  if (!$map->layers()) {
    $world = new GBox;
    foreach (new GeoJFile($_GET['file']) as $feature)
      $world->union(Geometry::fromGeoJSON($feature['geometry'])->bbox());
    $map->setWorld($world);
  }
  $map->addLayer([
    'path'=> $_GET['file']
  ]);
  $file = dirname($_GET['file']);
}
else {
  $file = $_GET['file'];
}

if (isset($_GET['action'])) { // autre action éventuelle
  if ($_GET['action']=='delLayer') {
    $map->delLayer($_GET['layer']);
  }
  elseif (($_GET['action']=='setfill') && isset($_GET['color'])) {
    $map->setFillColor($_GET['layer'], $_GET['color']);
  }
  elseif (($_GET['action']=='setstroke') && isset($_GET['color'])) {
    $map->setStrokeColor($_GET['layer'], $_GET['color']);
  }
}

$drawer = 'drawer.php?map='.rawurlencode($map->encode());
echo "<table border=1><tr>\n",
  "<td><table border=1>",
    "<tr><td><pre>map = ",$map->encode(JSON_PRETTY_PRINT),"</pre></td></tr>",
    "<tr><td>",$map->form(),"</td></tr>",
    "<tr><td>",choosefile($file, ['map'=> $map->encode(),'action'=>null,'layer'=>null]),"</td></tr>",
  "</table></td>",
  //"<td><a href='$drawer&amp;size=big'><img src='$drawer&amp;size=small'></a></td>",
  "<td><map name='htmlmap'>",
      "<area shape='rect' coords='0,0,200,230' href='?action=zoomNW",genParams(['map'=>$map->encode(),'file'=>$file]),"'/>",
      "<area shape='default' />",
    "</map>",
    "<img usemap='#htmlmap' src='$drawer&amp;size=small'>",
    "<br><a href='$drawer&amp;size=big'>big</a>",
    " / <a href='?action=zoomOut",genParams(['map'=>$map->encode(),'file'=>$file]),"'>zoomOut</a>",
  "</td>",
  "</tr></table>\n";
echo "<a href='?'><i>Retour</i></a><br>\n";
