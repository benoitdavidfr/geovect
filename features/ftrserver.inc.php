<?php
/*PhpDoc:
name: ftrserver.inc.php
title: ftrserver.inc.php - interface d'un serveur de Feature conforme au standard API Features
doc: |
  Est-ce une abstract class ou une interface ?
classes:
journal: |
  30/12/2020:
    - création
*/
use Symfony\Component\Yaml\Yaml;

/*PhpDoc: classes
name: FeatureServer
title: abstract class FeatureServer - interface d'un serveur de Feature conforme au standard API Features
methods:
doc: |
*/
abstract class FeatureServer {
  const LOG_FILENAME = __DIR__.'/fts_logfile.yaml';
  
  // écrit un message dans le fichier des logs
  static function log(string $message): void {
    if (self::LOG_FILENAME)
      file_put_contents(
        self::LOG_FILENAME,
        "'".date('Y-m-d').'T'.date('H:i:s')."': $message\n",
        FILE_APPEND
      )
      or die("Erreur d'ecriture dans le fichier de logs dans FeatureServer");
  }
  
  static function selfUrl(): string { // Url d'appel sans les paramètres GET
    return ($_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http')
          ."://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]$_SERVER[PATH_INFO]";
  }
  
  function landingPage(string $f): array { // retourne l'info de la landing page
    $selfurl = self::selfUrl();
    $dataId = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
    return [
      'title'=> "Access to $dataId data using OGC API Features specification",
      'description'=> "Access to $dataId data via a Web API that conforms to the OGC API Features"
        ." specification.",
      'links'=> [
        [ 'href'=> $selfurl, 'rel'=> 'self', 'type'=> 'application/json', 'title'=> "this document in JSON" ],
        [ 'href'=> $selfurl, 'rel'=> 'self', 'type'=> 'text/html', 'title'=> "this document in HTML" ],
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
  abstract function collections(): array;
  
  abstract function collection(string $collId): array;

  abstract function collDescribedBy(string $collId): array; // retourne la description du FeatureType de la collection
  
  // retourne les items de la collection comme array Php
  abstract function items(string $collId, array $bbox=[], array $pFilter=[], int $count=10, int $startindex=0): array;
  
  // retourne l'item $id de la collection comme array Php
  abstract function item(string $collId, string $featureId): array;
};
