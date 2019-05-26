<?php
namespace fcoll;
{/*PhpDoc:
name:  index.php
title: index.php - Consultation générique de données GeoJSON stockées dans une FeatureCollection
classes:
doc: |
  Permet:
    - de construire interactivement une carte définie par:
      - une liste de couches, chacune corr. à une FeatureCollection, un style d'affichage et une évent. expr. de sélection
      - une fenêtre d'affichage définie en coord. géographiques, appelée world
      - les tailles du dessin dans 2 cas small et big
    - de dessiner la carte au fur et à mesure de sa construction
    - de modifier le style d'affichage d'une couche
    - de zoomer/dézoomer interactivement
    - d'ajouter une couche en sélectionnant un nouveau FeatureCollection dans FCTree
    - d'importer une carte définie dans un fichier MapDef
    - d'exporter la carte en Yaml et de l'éditer
    - de consulter les Feature de chacune des couches intersectant la fenêtre courante de la carte
  Utilise le script drawer.php qui dessine la carte courante en WebMercator.
  La fenêtre d'affichage doit être inclue dans [-180, -85, 180, 85] pour que le dessin soit possible.
journal: |
  21/5/2019:
    - transfert du paramètre $criteria dans les FCTree dans la méthode FeatureCollection::features()
    - ajout des possibilités d'exporter la carte en Yaml, de l'éditer et de consulter les objets affichés
  20/5/2019:
    - adaptation à la nouvelle interface de FeatureCollection
  19/5/2019:
    - ajout du ZoomIn
  13/5/2019:
    - ajout de la gestion des définitions de cartes
  12/5/2019:
    - transfert dans geojson
    - extension aux FCTree
  8/5/2019:
    - création
hrefs:
  - drawer.php
includes:
  - ../coordsys/light.inc.php
  - ../gegeom/gegeom.inc.php
  - ../gegeom/gddrawing.inc.php
  - fctree.inc.php
  - database.inc.php
  - mapdef.inc.php
*/}
//require_once __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/../coordsys/light.inc.php';
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/../gegeom/gddrawing.inc.php';
require_once __DIR__.'/fctree.inc.php';
require_once __DIR__.'/database.inc.php';
require_once __DIR__.'/mapdef.inc.php';

use Symfony\Component\Yaml\Yaml;
use \WebMercator;
use \gegeom\GBox;
use \gegeom\EBox;
use \gegeom\Geometry;
use \gegeom\GdDrawing;

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
function chooseFC(FCTree $parent, array $newparamvalues=[]): string {
  //echo "chooseFC(",$parent->path(),", ",json_encode($newparamvalues),")<br>\n";
  $dirpath = $parent->path();
  
  $params = []; // liste des params à intégrer dans les URL
  foreach ($_GET as $k => $v) {
    if ($k <> 'path') {
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
  }
  foreach ($newparamvalues as $k => $v)
    if ($v)
      $params[$k] = $v;
  
  $str = "<ul>\n";
  foreach ($parent as $name => $child) {
    //echo "$name<br>\n";
    $title = $name == '..' ? '..' : $child->title();
    if (in_array($child->type(), ['FeatureCollection', 'MapDef']))
      $str .= "<li><a href='?path=".$child->path().genparams($params)."'>$title</a></li>\n";
    elseif ($child->type()=='FCTree') {
      $str .= "<li><a href='?path=".$child->path().genparams($params)."'><b>$title</b></a></li>\n";
    }
  }
  $str .= "</ul>\n";
  return $str;
}

// classe implémentant le concept de carte aussi utilisé dans drawer.php
class Map {
  const DEFAULTPARAMS = [
    'world'=> null,
    'width'=> ['small'=> 600, 'big'=> 1600],
    'height'=> ['small'=> 800, 'big'=> 800],
    'layers'=>[]
  ];
  const XWMMAX = 20037508.3;
  const YWMMAX = 19971868.9;
  protected $world; // GBox ou null
  protected $width; // [{size}=> entier]
  protected $height; // [{size}=> entier]
  protected $layers; // [['path'=> {filepath}, 'style'=>{style}]]
  
  static function decode(string $str, string $format) {
    //echo "Map::decode($str, $format)<br>\n";
    return new Map($format == 'yaml' ? Yaml::parse($str) : json_decode($str, true));
  }
  
  static function path() {
    return (FCTree::create($_GET['path'])->type()=='FeatureCollection') ? dirname($_GET['path']) : $_GET['path'];
    //echo "Map::path() avec path='$_GET[path]' -> '$path'<br>\n";
    //echo "type=",FCTree::create($_GET['path'])->type(),"<br>\n";
    //return $path;
  }
  
  function __construct(array $params=self::DEFAULTPARAMS) {
    $this->world = isset($params['world']) ? new GBox($params['world']) : null;
    $this->width = $params['width'];
    $this->height = $params['height'];
    $this->layers = $params['layers'];
  }
  
  function world() { return $this->world; }
  function layers() { return $this->layers; }
  
  function asArray() {
    return [
      'world'=> $this->world ? $this->world->asArray() : null,
      'width'=> $this->width,
      'height'=> $this->height,
      'layers'=> $this->layers,
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
    //echo "path=$_GET[path]<br>\n";
    return html_button([
        'map'=> $this->encode(),
        'path'=> self::path(),
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
    $val = $this->layers[$ilayer]['style'][$strfill] ?? '';
    // ($strfill=='stroke' ? 0x000000 : 0x808080)
    // $str = "<form><input type='text' name='color' size=6 value='".($val ? sprintf("%06x", $val) : '')."'></form>";
    $str = html_textInput([
      'map'=> $this->encode(),
      'path'=> self::path(),
      'layer'=> $ilayer,
      'action'=> 'set'.$strfill,
    ], 'color', 6, $val !== '' ? sprintf("%06x", $val) : '');
    return $str;
  }
  
  // affecte la fill-color de la couche $ilayer avec $color
  function setFillColor(int $ilayer, string $color) {
    echo "setFillColor($ilayer, $color)<br>\n";
    if ($color == '')
      unset($this->layers[$ilayer]['style']['fill']);
    else
      $this->layers[$ilayer]['style']['fill'] = hexdec($color);
  }
  
  // affecte la stroke-color de la couche $ilayer avec $color
  function setStrokeColor(int $ilayer, string $color) {
    echo "setStrokeColor($ilayer, $color)<br>\n";
    if ($color == '')
      unset($this->layers[$ilayer]['style']['stroke']);
    else
      $this->layers[$ilayer]['style']['stroke'] = hexdec($color);
  }
  
  // éditeur d'opacité
  function opacityEditor(string $strfill, int $ilayer): string {
    return '';
  }
  
  // génère le formulaire d'édition d'une carte
  function form(): string {
    //return '';
    $str = "<a href='?action=editMap&amp;map=".rawurlencode($this->encode())."&amp;path=".urlencode($_GET['path'])."'><b>Carte</b></a> :<br>\n"
      ."<table border=1>\n"
      ."<tr><td colspan=6>world = ".$this->world."</td></tr>\n"
      ."<tr><td colspan=6><b>Couches</b></td></tr>\n";
    foreach($this->layers as $i => $layer) {
      $str .= "<tr><td>$layer[path]</td>"
            ."<td>".$this->colorEditor('stroke', $i)."</td>"
            ."<td>".$this->opacityEditor('stroke', $i)."</td>"
            ."<td>".$this->colorEditor('fill', $i)."</td>"
            ."<td>".$this->opacityEditor('fill', $i)."</td>"
            ."<td>".$this->delButton($i)."</td></tr>\n";
    }
    $str .= "</table>\n";
    $str .= "\n";
    return $str;
  }
  
  function setWorld(GBox $world): void { $this->world = $world->intersects(new GBox([-180, -85, 180, 85])); }
  
  function setDefs(array $params) {
    //echo "Map::setDefs(",json_encode($params, JSON_PRETTY_PRINT),")<br>\n";
    if (isset($params['world']))
      $this->world = new GBox($params['world']);
    if (isset($params['width']))
      $this->width = $params['width'];
    if (isset($params['height']))
      $this->height = $params['height'];
    if (isset($params['layers']))
      $this->layers = $params['layers'];
  }

  // $x, $y sont les coordonnées cliquées dans l'image small
  // ZoomIn en WM
  function zoomIn(int $x, int $y) {
    //echo "zoomIn($x, $y)<br>\n";
    $worldWM = $this->world->proj(function(array $pos) { return WebMercator::proj($pos); }); // la fenêtre en coord. WM
    //echo "worldWM=$worldWM<br>\n";
    $gdDrawing = new GdDrawing($this->width['small'], $this->height['small'], $worldWM);
    $pos = $gdDrawing->userCoord([$x, $y]); // passage en coord utilisateurs, cad WM
    
    $dx = $worldWM->dx();
    $dy = $worldWM->dy();
    $west = $pos[0] - $dx/4;
    $east = $pos[0] + $dx/4;
    if ($west < - self::XWMMAX) {
      $west = - self::XWMMAX;
      $east = $west + $dx/2;
    }
    if ($east > self::XWMMAX) {
      $east = self::XWMMAX;
      $west = $east - $dx/2;
    }
    $south = $pos[1] - $dy/4;
    $north = $pos[1] + $dy/4;
    if ($south < - self::YWMMAX) {
      $south = - self::YWMMAX;
      $north = $south + $dy/2;
    }
    if ($north > self::YWMMAX) {
      $north = self::YWMMAX;
      $south = $north - $dy/2;
    }
    $worldWM = new EBox([$west, $south, $east, $north]);
    //echo "worldWM=$worldWM<br>\n";
    $this->world = $worldWM->geo(function(array $pos) { return WebMercator::geo($pos); });
    //echo "world=",$this->world,"<br>\n";
  }
  
  // ZoomOut en WM
  function zoomOut() {
    if (!$this->world)
      return;
    $worldWM = $this->world->proj(function(array $pos) { return WebMercator::proj($pos); }); // la fenêtre en coord. WM
    $dx = $worldWM->dx();
    $dy = $worldWM->dy();
    if (($dx > self::XWMMAX) || ($dy > self::YWMMAX)) {
      $this->world = new GBox([-180, -85, 180, 85]);
      return;
    }
    $west = $worldWM->west() - $dx/2;
    $east = $worldWM->east() + $dx/2;
    if ($west < - self::XWMMAX) {
      $west = - self::XWMMAX;
      $east = $west + 2 * $dx;
    }
    if ($east > self::XWMMAX) {
      $east = self::XWMMAX;
      $west = $east - 2 * $dx;
    }
    $south = $worldWM->south() - $dx/2;
    $north = $worldWM->north() + $dx/2;
    if ($south < - self::YWMMAX) {
      $south = - self::YWMMAX;
      $north = $south + 2 * $dy;
    }

    if ($north > self::YWMMAX) {
      $north = self::YWMMAX;
      $south = $north - 2 * $dy;
    }
    $worldWM = new EBox([$west, $south, $east, $north]);
    $this->world = $worldWM->geo(function(array $pos) { return WebMercator::geo($pos); });
  }
};

// permet d'éditer la carte en Yaml
if (isset($_GET['action']) && ($_GET['action']=='editMap')) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Map</title></head><body><pre>\n";
  //print_r($_GET['map']);
  $map = json_decode($_GET['map'], true);
  echo "<form>",
      "<textarea name='map' rows='20' cols='133'>",Yaml::dump($map, 999),"</textarea><br>",
      "<input type='hidden' name='mapformat' value='yaml'>",
      "<input type='hidden' name='path' value='",$_GET['path'],"'>",
      "<input type='submit' value='ok'>",
      "</form>\n";
  $map = Map::decode($_GET['map'], 'json');
  echo "</pre><h2>Couches</h2><ul>\n";
  foreach ($map->layers() as $i => $layer) {
    echo "<li><a href='?action=showLayer&amp;layer=$i&amp;map=",rawurlencode($_GET['map']),"'>$layer[path]</a></li>\n";
  }
  die();
}

// affichage des objets d'une couche intersectant le bbox world courant
if (isset($_GET['action']) && ($_GET['action']=='showLayer')) {
  $map = Map::decode($_GET['map'], 'json');
  $world = $map->world();
  $layer = $map->layers()[$_GET['layer']];
  //print_r($layer);
  $lyrpath = $layer['path'];
  $table = FCTree::create($lyrpath);
  //print_r($table);
  echo "<pre>\n";
  foreach($table->features(['bbox'=>$world->asArray()]) as $feature) {
    echo json_encode($feature),"\n";
  }
  die();
}

if (!isset($_GET['path'])) { // choix de la première FeatureCollection
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Viewer</title></head><body>\n";
  echo "Viewer - Choisir un fichier .geojson ou .yaml ou naviguer dans l'arbre<br>\n";
  
  if (isset($_GET['map']))
    echo $_GET['map'];
  $node = FCTree::create();
  echo chooseFC($node);
  echo "<a href='test.php'>Ou effectuer les tests de fcoll</a><br>\n";
  die();
}

$node = FCTree::create($_GET['path']);
//echo "type=",$node->type(),"<br>\n";

// choix de la première FeatureCollection/MapDef avant que la carte ne soit créée
if (!isset($_GET['map']) && ($node->type()=='FCTree')) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Viewer $_GET[path]</title></head><body>\n";
  if (isset($_GET['map']))
    echo $_GET['map'];
  echo chooseFC(FCTree::create($_GET['path']));
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


// affichage de la carte
echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>Viewer $_GET[path]</title></head><body>\n";
$map = isset($_GET['map']) ? Map::decode($_GET['map'], $_GET['mapformat'] ?? 'json') : new Map();

//echo "type=",$node->type(),"<br>\n";
// ajout d'une couche
if ($node->type()=='FeatureCollection') {
  if (!$map->layers()) {
    $map->setWorld($node->bbox([]));
  }
  $map->addLayer([
    'path'=> $_GET['path']
  ]);
  $path = dirname($_GET['path']);
}
elseif ($node->type()=='MapDef') {
  $map->setDefs($node->asArray());
  $path = dirname(dirname($_GET['path']));
}
else {
  $path = $_GET['path'];
}
// $path est maintenant le chemin d'un FCTree
$parentfc = FCTree::create($path);

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
  elseif ($_GET['action']=='zoomOut') {
    $map->zoomOut();
  }
  elseif ($_GET['action']=='zoomIn') {
    $map->zoomIn($_GET['x'], $_GET['y']);
  }
}

$drawer = 'drawer.php?map='.rawurlencode($map->encode());
echo "<table border=1><tr>\n",
  "<td><table border=1>",
    //"<tr><td><pre>map = ",$map->encode(JSON_PRETTY_PRINT),"</pre></td></tr>",
    "<tr><td>",$map->form(),"</td></tr>",
    "<tr><td>",chooseFC($parentfc, ['map'=> $map->encode(),'mapformat'=>null,'action'=>null,'layer'=>null]),"</td></tr>",
  "</table></td>\n",
  //"<td><a href='$drawer&amp;size=big'><img src='$drawer&amp;size=small'></a></td>",
  "<td valign='top'><map name='htmlmap'>\n";
$params = genParams(['map'=>$map->encode(),'path'=>$path]);
for ($i=0; $i<60; $i++) {
  for ($j=0; $j<80; $j++) {
    echo "<area shape='rect' coords='",$i*10,',',$j*10,',',($i+1)*10,',',($j+1)*10,
         "' href='?action=zoomIn&amp;x=",$i*10+5,"&amp;y=",$j*10+5,"$params'/>\n";
  }
}
//echo "<area shape='rect' coords='0,0,10,10' href='?action=zoomIn&amp;x=5&amp;y=5$params'/>",
echo "<area shape='default' />",
    "</map>",
    "<img usemap='#htmlmap' src='$drawer&amp;size=small'>",
    "<br><a href='$drawer&amp;size=big'>big</a>",
    " / <a href='?action=zoomOut",genParams(['map'=>$map->encode(),'path'=>$path]),"'>zoomOut</a>",
  "</td>",
  "</tr></table>\n";
echo "<pre>GET=",json_encode($_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"</pre>\n";
echo "<a href='?map=",rawurlencode($map->encode()),"&amp;path=/geovect/fcoll'><i>Home</i></a> \n";
echo "<a href='?'><i>Retour</i></a><br>\n";
