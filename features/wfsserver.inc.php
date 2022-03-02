<?php
/*PhpDoc:
name: wfsserver.inc.php
title: wfsserver.inc.php - classes pour interroger un serveur WFS générant du GeoJSON
functions:
doc: |
  Définition de la classe abstraite WfsServer et la classe concrète WfsGeoJson pour interroger un serveur WFS générant
  du GeoJSON.
journal: |
  2/3/2022:
    - correction d'un bug sur Lambert93
  28/2/2022:
    - ajout paramètre properties dans getFeature()
    - chgt encodage CQL_FILTER
  27/2/2022:
    - récriture de WfsServer::query()
  2/2/2021:
    - création par extraction de ftsonwfs.inc.php
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../gegeom/gegeom.inc.php';
require_once __DIR__.'/../coordsys/light.inc.php';

use Symfony\Component\Yaml\Yaml;
use gegeom\GBox;
use gegeom\Polygon;

abstract class WfsServer {
  const ERROR_WFS_QUERY = 'WfsServer::ERROR_WFS_QUERY';
  const ERROR_CACHE = 'WfsServer::ERROR_CACHE';
  const ERROR_BAD_CRS = 'WfsServer::ERROR_BAD_CRS';
  const LOG = __DIR__.'/wfsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  const CAP_CACHE = __DIR__.'/wfscapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML
                                            // de capacités ainsi que les DescribeFeatureType en json
  
  protected string $serverUrl; // URL du serveur
  protected array $options; // sous la forme ['option'=> valeur] avec option valant referer et/ou proxy
  
  function __construct(string $serverUrl, array $options=[]) {
    $this->serverUrl = $serverUrl;
    $this->options = $options;
  }
  
  // construit l'URL de la requête à partir des paramètres
  private function url(array $params): string {
    if (self::LOG) { // log
      file_put_contents(
          self::LOG,
          Yaml::dump([
            date(DateTime::ATOM) => [
              'appel'=> 'WfsServer::url',
              'params'=> $params,
            ]
          ]),
          FILE_APPEND
      );
    }
    $url = $this->serverUrl;
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE=WFS';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    if (self::LOG) { // log
      file_put_contents(self::LOG, Yaml::dump([date(DateTime::ATOM) => ['url'=> $url]]), FILE_APPEND);
    }
    return $url;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte - réécriture 27/2/2022
  // Certaines requêtes du WFS IGN nécessitent un User-Agent pour ne pas générer une erreur
  protected function query(array $params): string {
    $url = $this->url($params);
    $referer = $this->options['referer'] ?? null;
    $httpOptions = [
      'method'=> 'GET',
      'ignore_errors'=> true,
      'timeout'=> 2*60, // timeout de 2 min.
      'header'=>"Accept-language: en\r\n"
               .($referer ? "referer: $referer\r\n" : '')
               ."User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0\r\n",
    ];
    if ($proxy = ($this->options['proxy'] ?? null)) {
      $httpOptions['proxy'] = $proxy;
    }
    if (self::LOG) { // log
      file_put_contents(
          self::LOG,
          Yaml::dump([
            'appel'=> 'WfsServer::query',
            'httpOptions'=> $httpOptions,
          ]),
          FILE_APPEND
      );
    }
    $context = stream_context_create(['http'=> $httpOptions]);
    $data = @file_get_contents($url, false, $context);
    if (($data === false) || !isset($http_response_header))
      throw new SExcept ("Erreur dans WfsServer::query() : erreur de file_get_contents() sur url=$url",
        self::ERROR_WFS_QUERY); // probablement erreur de timeout
    $errorCode = substr($http_response_header[0], 9, 3);
    if ($errorCode == 200)
      return $data;
    
    elseif (preg_match('!<ExceptionReport><[^>]*>([^<]*)!', $data, $matches)) {
      throw new SExcept ("Erreur dans WfsServer::query() : $matches[1], erreur http=$errorCode", self::ERROR_WFS_QUERY);
    }
    elseif (preg_match('!<ows:ExceptionText>([^<]*)!', $data, $matches)) {
      throw new SExcept ("Erreur dans WfsServer::query() : $matches[1], erreur http=$errorCode", self::ERROR_WFS_QUERY);
    }
    else {
      echo $data;
      throw new SExcept("Erreur dans WfsServer::query() : erreur Http=$errorCode, message d'erreur non interprété",
        self::ERROR_WFS_QUERY);
    }
  }
  
  // effectue un GetCapabities et retourne le XML. Utilise le cache sauf si force=true
  function getCapabilities(bool $force=false): string {
    if (!is_dir(self::CAP_CACHE) && !mkdir(self::CAP_CACHE))
      throw new SExcept("Erreur de création du répertoire ".self::CAP_CACHE, self::ERROR_CACHE);
    $wfsVersion = $this->options['version'] ?? '2.0.0';
    $filepath = self::CAP_CACHE.'/wfs'.md5($this->serverUrl.$wfsVersion).'-cap.xml';
    if (!$force && file_exists($filepath))
      return file_get_contents($filepath);
    else {
      $cap = $this->query(['request'=> 'GetCapabilities','VERSION'=> $wfsVersion]);
      file_put_contents($filepath, $cap);
      return $cap;
    }
  }
 
  // retourne un polygon WKT dans le CRS crs à partir d'un bbox [lngMin, latMin, lngMax, latMax]
  static function bboxWktCrs(array $bbox, string $crs): string {
    // Dictionnaire [code EPSG => nom de la classe correspondante dans ../coordsys/light.inc.php]
    static $epsg = [
      'EPSG:2154' => 'Lambert93',
      'EPSG:3857' => 'WebMercator',
      'EPSG:3395' => 'WorldMercator',
    ];
    if (!$bbox)
      return '';
    if ($crs == 'CRS:84')
      return "POLYGON(($bbox[0] $bbox[1],$bbox[2] $bbox[1],$bbox[2] $bbox[3],$bbox[0] $bbox[3],$bbox[0] $bbox[1]))";
    elseif ($crs == 'EPSG:4326')
      return "POLYGON(($bbox[1] $bbox[0],$bbox[1] $bbox[2],$bbox[3] $bbox[2],$bbox[3] $bbox[0],$bbox[1] $bbox[0]))";
    elseif (!isset($epsg[$crs]))
      throw new SExcept("Erreur dans WfsServer::bboxWktCrs(), CRS $crs inconnu", self::ERROR_BAD_CRS);
    //echo Yaml::dump($bbox);
    $gbox = new GBox($bbox);
    $proj = $epsg[$crs].'::proj';
    $ebox = $gbox->proj($proj);
    return (new Polygon($ebox->polygon()))->wkt();
  }

  static function crsUrnToStr(string $urn): string {
    if (preg_match('!^urn:ogc:def:crs:EPSG::(\d+)$!', $urn, $matches))
      return "EPSG:$matches[1]";
    else
      return $urn;
  }
  
  // liste les couches exposées evt filtré par l'URL des MD
  function featureTypeList(string $metadataUrl=null): array {
    //echo "WfsServer::featureTypeList()<br>\n";
    $cap = $this->getCapabilities();
    $cap = str_replace(['xlink:href','ows:'], ['xlink_href','ows_'], $cap);
    $featureTypeList = [];
    $cap = new SimpleXMLElement($cap);
    foreach ($cap->FeatureTypeList->FeatureType as $featureType) {
      $name = (string)$featureType->Name;
      if (!$metadataUrl || ((string)$featureType->MetadataURL['xlink_href'] == $metadataUrl)) {
        $ft = ['Title'=> (string)$featureType->Title];
        if ($featureType->MetadataURL['xlink_href'])
          $ft['MetadataURL'] = (string)$featureType->MetadataURL['xlink_href'];
        $ft['DefaultCRS'] = self::crsUrnToStr($featureType->DefaultCRS);
        $ft['OtherCRS'] = [];
        foreach($featureType->OtherCRS as $crs) {
          $ft['OtherCRS'][] = self::crsUrnToStr($crs);
        }
        $lc = explode(' ', $featureType->ows_WGS84BoundingBox->ows_LowerCorner);
        $uc = explode(' ', $featureType->ows_WGS84BoundingBox->ows_UpperCorner);
        $ft['LonLatBoundingBox'] = [(float)$lc[1], (float)$lc[0], (float)$uc[1], (float)$uc[0]];
        $featureTypeList[$name] = $ft;
      }
    }
    //echo '<pre>$featureTypeList = '; print_r($featureTypeList);
    return $featureTypeList;
  }
  
  abstract function describeFeatureType(string $typeName): array;
  
  abstract function geomPropertyName(string $typeName): ?string;
  
  //abstract function getNumberMatched(string $typename, array $bbox=[], string $where=''): int;
  
  abstract function getFeature(
    string $typename, array $properties=[], array $bbox=[], string $where='',
    int $count=100, int $startindex=0): string;

  //abstract function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void;
};

class WfsGeoJson extends WfsServer { // gère les fonctionnalités d'un serveur WFS retournant du GeoJSON
  const ERROR_CACHE = 'WfsGeoJson::ERROR_CACHE';
  //const ERROR_BAD_NUM_MATCHED = 'WfsGeoJson::ERROR_BAD_NUM_MATCHED';

  function describeFeatureType(string $typeName): array { // retourne le JSON comme array
    $filepath = self::CAP_CACHE.'/wfs'.md5($this->serverUrl."/$typeName").'-ft.json';
    if (is_file($filepath)) {
      $featureType = file_get_contents($filepath);
    }
    else {
      $featureType = $this->query([
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'DescribeFeatureType',
        'OUTPUTFORMAT'=> 'application/json',
        'TYPENAME'=> $typeName,
      ]);
      if (!is_dir(self::CAP_CACHE) && !mkdir(self::CAP_CACHE))
        throw new SExcept("Erreur de création du répertoire ".self::CAP_CACHE, self::ERROR_CACHE);
      file_put_contents($filepath, $featureType);
    }
    return json_decode($featureType, true);
  }
  
  // nom de la propriété géométrique du featureType
  function geomPropertyName(string $typeName): ?string {
    $featureType = $this->describeFeatureType($typeName);
    //var_dump($featureType);
    foreach($featureType['featureTypes'] as $featureType) {
      foreach ($featureType['properties'] as $property) {
        if (preg_match('!^gml:!', $property['type']))
          return $property['name'];
      }
    }
    return null;
  }
    
  // retourne le nbre d'objets correspondant au résultat de la requête
  /*function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    echo "getNumberMatched\n";
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'RESULTTYPE'=> 'hits',
    ];
    $cql_filter = '';
    if ($bbox) {
      $featureTypeList = $this->featureTypeList();
      $geomPropertyName = $this->geomPropertyName($typename);
      $defaultCrs = $featureTypeList[$typename]['DefaultCRS'];
      $bboxwkt = self::bboxWktCrs($bbox, $defaultCrs);
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new SExcept("Erreur dans WfsServerJson::getNumberMatched() : no match on result $result",
        self::ERROR_BAD_NUM_MATCHED);
    }
    return (int)$matches[1];
  }*/
  
  // retourne le résultat de la requête comme string GeoJSON
  function getFeature(string $typename, array $properties=[], array $bbox=[], string $where='',
                      int $count=100, int $startindex=0): string {
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'OUTPUTFORMAT'=> 'application/json',
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'COUNT'=> $count,
      'STARTINDEX'=> $startindex,
    ];
    if ($properties)
      $request['PROPERTYNAME'] = implode(',', $properties);
    $cql_filter = '';
    if ($bbox) {
      $featureTypeList = $this->featureTypeList();
      $defaultCrs = $featureTypeList[$typename]['DefaultCRS'];
      $bboxwkt = self::bboxWktCrs($bbox, $defaultCrs);
      $geomPropertyName = $this->geomPropertyName($typename);
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      //$where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      // Le 28/2/2022 - l'encodage UTF-8 fonctionne
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter) {
      //echo "cql_filter=$cql_filter\n";
      $request['CQL_FILTER'] = urlencode($cql_filter);
      //echo "CQL_FILTER=$request[CQL_FILTER]\n";
    }
    return $this->query($request);
  }
  
  // retourne le résultat de la requête en GeoJSON encodé en array Php
  function getFeatureAsArray(string $typename, array $properties=[], array $bbox=[], string $where='',
                             int $count=100, int $startindex=0): array {
    $result = $this->getFeature($typename, $properties, $bbox, $where, $count, $startindex);
    return json_decode($result, true);
  }
  
  // affiche le résultat de la requête en GeoJSON
  /*function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void {
    echo "WfsServerJson::printAllFeatures()<br>\n";
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      echo $this->getFeature($typename, $bbox, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $fc = $this->getFeature($typename, $bbox, $where, $count, $startindex);
      $fc = json_decode($fc, true);
      foreach ($fc['features'] as $nof => $feature) {
        if (($startindex <> 0) || ($nof <> 0))
          echo ",\n";
        echo json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      }
      $startindex += $count;
    }
    echo "\n]}\n";
  }*/
  
  // retourne le résultat de la requête comme string GeoJSON
  function getFeatureById(string $typename, string $id): string {
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'OUTPUTFORMAT'=> 'application/json',
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'featureID'=> $id,
    ];
    return $this->query($request);
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__))) return;
// Tests unitaires de la classe WfsGeoJson

$wfs = new WfsGeoJson('https://services.data.shom.fr/INSPIRE/wfs');

if (!isset($_GET['action'])) {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  echo "<a href='?action=GetCapabilities'>GetCapabilities</a>\n";
  echo "<a href='?action=featureTypeList'>featureTypeList</a>\n";
  echo "<a href='?action=describeFeatureType'>describeFeatureType</a>\n";
  echo "<a href='?action=geomPropertyName'>geomPropertyName</a>\n";
  echo "<a href='?action=getNumberMatched'>getNumberMatched</a>\n";
  echo "<a href='?action=getNumberMatchedProp'>getNumberMatchedProp</a>\n";
  echo "<a href='?action=getNumberMatchedGeom'>getNumberMatchedGeom</a>\n";
  echo "<a href='?action=getFeatureAsArray'>getFeatureAsArray ss restriction</a>\n";
  echo "<a href='?action=getFeatureAsArrayProp'>getFeatureAsArray avec filtre sur une propriété</a>\n";
  echo "<a href='?action=getFeatureAsArrayGeom'>getFeatureAsArray avec filtre géométrique</a>\n";
  echo "<a href='?action=getFeatureById'>getFeatureById</a>\n";
}

elseif ($_GET['action'] == 'GetCapabilities') { // GetCapabilities
  header('Content-type: text/xml; charset="utf8"');
  die($wfs->getCapabilities());
}

elseif ($_GET['action'] == 'featureTypeList') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  die(Yaml::dump($wfs->featureTypeList()));
  //die (Yaml::dump($wfs->featureTypeList('https://services.data.shom.fr/geonetwork/INSPIRE?service=CSW&version=2.0.2&request=GetRecordById&Id=BDML_RAP.xml')));
}

elseif ($_GET['action'] == 'describeFeatureType') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  die(Yaml::dump($wfs->describeFeatureType('LIMITES_SALURE_EAUX_WFS:lse_3857_arc'), 9, 2));
}

elseif ($_GET['action'] == 'geomPropertyName') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  die(Yaml::dump($wfs->geomPropertyName('LIMITES_SALURE_EAUX_WFS:lse_3857_arc'), 9, 2));
}

elseif ($_GET['action'] == 'getNumberMatched') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  $numberMatched = $wfs->getNumberMatched('LIMITES_SALURE_EAUX_WFS:lse_3857_arc');
  die("numberMatched=$numberMatched");
}

elseif ($_GET['action'] == 'getNumberMatchedProp') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  $numberMatched = $wfs->getNumberMatched('LIMITES_SALURE_EAUX_WFS:lse_3857_arc', [], 'numdep=59');
  die("numberMatched=$numberMatched");
}

elseif ($_GET['action'] == 'getNumberMatchedGeom') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  $numberMatched = $wfs->getNumberMatched('LIMITES_SALURE_EAUX_WFS:lse_3857_arc', [2, 50, 3, 51]);
  die("numberMatched=$numberMatched");
}

elseif ($_GET['action'] == 'getFeatureAsArray') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  // sans restriction
  die(Yaml::dump($wfs->getFeatureAsArray('LIMITES_SALURE_EAUX_WFS:lse_3857_arc'), 9, 2));
}

elseif ($_GET['action'] == 'getFeatureAsArrayProp') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  // avec restriction de type property=value
  die(Yaml::dump($wfs->getFeatureAsArray('LIMITES_SALURE_EAUX_WFS:lse_3857_arc', [], 'numdep=59'), 9, 2));
}

elseif ($_GET['action'] == 'getFeatureAsArrayGeom') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  // avec restriction géométrique
  die(Yaml::dump($wfs->getFeatureAsArray('LIMITES_SALURE_EAUX_WFS:lse_3857_arc', [2, 50, 3, 51]), 9, 2));
}

elseif ($_GET['action'] == 'getFeatureById') {
  echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>wfsserver.inc.php</title></head><body><pre>\n";
  // avec restriction géométrique
  die(Yaml::dump(json_decode($wfs->getFeatureById('LIMITES_SALURE_EAUX_WFS:lse_3857_arc', 'lse_3857_arc.1'), true), 9, 2));
}
