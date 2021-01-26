<?php
/*PhpDoc:
name: ftrserver.inc.php
title: ftrserver.inc.php - code générique d'un serveur de Feature conforme au standard API Features
doc: |
  Gère aussi l'aguillage vers les différents types de serveur par la méthode new()
classes:
journal: |
  17-20/12/2020:
    - évolutions
  30/12/2020:
    - création
includes:
  - ftsonwfs.inc.php
  - ftsonfile.inc.php
  - ftsonsql.inc.php
*/
use Symfony\Component\Yaml\Yaml;

/*PhpDoc: classes
name: FeatureServer
title: abstract class FeatureServer - code générique d'un serveur de Feature conforme au standard API Features
methods:
doc: |
*/
abstract class FeatureServer {
  const LOG_FILENAME = __DIR__.'/fts.log.yml'; // chemin du fichier Yaml de log, si vide alors pas de log
  protected ?DatasetDoc $datasetDoc; // Doc éventuelle du jeu de données
  
  static function log(string|array $message): void { // écrit un message dans le fichier Yaml des logs
    if (!self::LOG_FILENAME)
      return;
    $dt = "'".date('Y-m-d').'T'.date('H:i:s')."'";
    file_put_contents(
      self::LOG_FILENAME,
      Yaml::dump([$dt => $message]),
      FILE_APPEND
    )
    or die("Erreur d'ecriture dans le fichier de logs dans FeatureServer");
  }
  
  static function selfUrl(): string { // Url d'appel sans les paramètres GET
    return ($_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http')
          ."://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]$_SERVER[PATH_INFO]";
  }
  
  // création des différents types de FeatureServer
  static function new(string $type, string $path, ?DatasetDoc $datasetDoc): self {
    switch($type) {
      case 'wfs': return new FeatureServerOnWfs("https:/$path", $datasetDoc);
      case 'file': return new FeatureServerOnFile($path, $datasetDoc);
      case 'mysql':
      case 'pgsql': return new FeatureServerOnSql("$type:/$path", $datasetDoc);
      default: output($f, ['error'=> "traitement $type non défini"]);
    }
  }
  
  function landingPage(string $f): array { // retourne l'info de la landing page
    $selfurl = self::selfUrl();
    $dataId = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
    $title = $this->datasetDoc->title ?? null;
    return [
      'title'=> $title ?? "Access to $dataId data using OGC API Features specification",
      'description'=>
        $title ?
          "Accès au jeu de données \"$title\" au travers d'une API conforme à la norme OGC API Features" :
          "Access to $dataId data via a Web API that conforms to the OGC API Features specification.",
      'links'=> [
        [
          'href'=> $selfurl,
          'rel'=> ($f == 'json') ? 'self' : 'alternate',
          'type'=> 'application/json',
          'title'=> "this document in JSON",
        ],
        [
          'href'=> $selfurl,
          'rel'=> ($f == 'html') ? 'self' : 'alternate',
          'type'=> 'text/html',
          'title'=> "this document in HTML",
        ],
        [
          'href'=> $selfurl,
          'rel'=> ($f == 'yaml') ? 'self' : 'alternate',
          'type'=> 'application/x-yaml',
          'title'=> "this document in Yaml",
        ],
        [
          'href'=> "$selfurl/api",
          'rel'=> 'service-desc',
          'type'=> 'application/vnd.oai.openapi+json;version=3.0',
          'title'=> "the API documentation in JSON",
        ],
        [
          'href'=> "$selfurl/api",
          'rel'=> 'service-desc',
          'type'=> 'text/html',
          'title'=> "the API documentation in HTML",
        ],
        [
          'href'=> "$selfurl/conformance",
          'rel'=> 'conformance',
          'type'=> 'application/json',
          'title'=> "OGC API conformance classes implemented by this server in JSON",
        ],
        [
          'href'=> "$selfurl/collections",
          'rel'=> 'data',
          'type'=> 'text/html',
          'title'=> "Information about the feature collections in HTML",
        ],
        [
          'href'=> "$selfurl/collections",
          'rel'=> 'data',
          'type'=> 'application/json',
          'title'=> "Information about the feature collections in JSON",
        ],
      ],
    ];
  }
    
  function conformance(): array { // retourne l'info de conformité
    return [
      'conformsTo'=> [
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/core',
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/oas30',
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/html',
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/geojson'
      ],
    ];
  }

  function api(): array { // retourne la définition de l'API
    $urlLandingPage = ($_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http')
          ."://$_SERVER[HTTP_HOST]".dirname($_SERVER['REQUEST_URI']);
    $apidef = Yaml::parse(@file_get_contents(__DIR__.'/apidef.yaml'));
    $apidef['servers'][0]['url'] = $urlLandingPage;
    return $apidef;
  }
  
  /*PhpDoc: methods
  name: collections
  title: "abstract function collections(): array - retourne la liste des collections"
  doc: |
    sous la forme: [
      'id'=> id,
      'title'=> title,
    ]
  */
  abstract function collections(string $f): array;
  
  abstract function collection(string $f, string $collId): array;

  abstract function collDescribedBy(string $collId): array; // retourne le schéma d'un Feature de la collection
  
  // retourne les items de la collection comme array Php
  abstract function items(string $collId, array $bbox=[], array $pFilter=[], int $count=10, int $startindex=0): array;
  
  // retourne l'item $featureId de la collection comme array Php
  abstract function item(string $collId, string $featureId): array;
};

require_once __DIR__.'/ftsonwfs.inc.php';
require_once __DIR__.'/ftsonfile.inc.php';
require_once __DIR__.'/ftsonsql.inc.php';
