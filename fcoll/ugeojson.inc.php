<?php
namespace fcoll;
{/*PhpDoc:
name: ugeojson.inc.php
title: ugeojson.inc.php - accès aux FeatureCollection exposées selon le protocole UGeoJSON
classes:
doc: |
  La lecture UGeoJSON peut être améliorée sur les points suivants:
    - possibilité d'explorer le contenu du service depuis le viewer
    - filtrage de l'en-tête dans GeoJFile::readGeoJFile() pour remettre les en-têtes dans ../ugeojson.php
journal:
  26/5/2019:
    création
*/}

{/*PhpDoc: classes
name: UGeoJSON
title: class UGeoJSON extends FeatureCollection - référence vers un service UGeoJSON
*/}
class UGeoJSON extends GeoJFile {
  //protected $path; // chemin Apache de l'élément
  protected $url; // url HTTP du service UGeoJSON
  
  function __construct(string $path, string $url) { $this->path = $path; $this->url = $url; }

  function __toString(): string { return 'UGeoJSON:'.$this->url; }
  function title(): string { return $this->__toString(); }
  
  // extrait de l'URL les critères de sélection
  function criteria(): array {
    //echo "url=$this->url<br>\n";
    if (FALSE === $pos = strpos($this->url, '?'))
      return [];
    //echo "pos=$pos<br>\n";
    $args = substr($this->url, $pos+1);
    //echo "args=$args<br>\n";
    $args = explode('&', $args);
    $criteria = [];
    foreach ($args as $arg) {
      $pos = strpos($arg, '=');
      $key = substr($arg, 0, $pos);
      $val = substr($arg, $pos+1);
      if ($key == 'bbox')
        $criteria['bbox'] = json_decode($val);
      else
        $criteria[$key] = rawurldecode($val);
    }
    //print_r($criteria); echo "<br>\n";
    return $criteria;
  }
  
  // fabrique une nouvelle URL en remplacant les criteres par les nouveaux
  function url(array $criteria): string {
    $url = (FALSE === $pos = strpos($this->url, '?')) ? $this->url : substr($this->url, 0, $pos);
    //echo "url=$url<br>\n";
    if (!$criteria)
      return $url;
    $url .= '?';
    $first = true;
    foreach ($criteria as $key => $val) {
      if (!$first)
        $url .= '&';
      else
        $first = false;
      if ($key == 'bbox')
        $url .= 'bbox='.json_encode($val);
      else
        $url .= rawurlencode($val);
    }
    //echo "url=$url<br>\n";
    return $url;
  }
  
  function features(array $criteria): \Generator {
    //echo $this->url;
    $criteria = Criteria::conjunction($this->criteria(), $criteria);
    if ($criteria === null)
      return;
    $key = 0;
    foreach (self::readGeoJFile($this->url($criteria)) as $feature) {
      //echo "objet lu<br>\n";
      yield $key++ => $feature;
    }
  }
};