<?php
/*PhpDoc:
name: ftrserver.inc.php
title: ftrserver.inc.php - code générique d'un serveur de Feature conforme au standard API Features
doc: |
  Gère aussi l'aiguillage vers les différents types de serveur par la méthode new()

  Gestion des erreurs:
    - chaque classe définit la liste des codes d'erreurs correspondant aux cas d'erreurs possibles
    - lorsqu'une erreur est détectée une Exception SExcept est levée avec un message et le code d'erreur ci-dessus
    - les exceptions doivent être traitées dans le script appelant
    - pour assurer l'unicité des codes d'erreur, ils sont définis comme constante chaine de la forme
      '{classe}::{nom_constante}'
classes:
journal: |
  27/2/2022:
    - amélioration de la gestion des erreurs, utilisation de SExcept
  27/1/2021:
    - ajout FeatureServer::checkParams() pour détecter les paramètres non prévus
    - test CITE ok pour /ignf-route500
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
  const ERROR_BAD_BBOX = 'FeatureServer::ERROR_BAD_BBOX';
  const ERROR_BAD_PARAMS = 'FeatureServer::ERROR_BAD_PARAMS';
  const LOG_FILENAME = __DIR__.'/fts.log.yml'; // chemin du fichier Yaml de log, si vide alors pas de log
  const MAX_LIMIT = 1000; // valeur max de limit par défaut, redéfinie evt. par le driver
                          // constante utilisée dans la définition de l'API et dans la vérification du paramètre

  protected ?DatasetDoc $datasetDoc; // Doc éventuelle du jeu de données
  
  static function log(string|array $message): void { // écrit un message dans le fichier Yaml des logs
    if (!self::LOG_FILENAME)
      return;
    if (file_put_contents(
      self::LOG_FILENAME,
      Yaml::dump([date('Y-m-d\TH:i:s\Z') => $message]),
      FILE_APPEND
    ) === false) {
      header('HTTP/1.1 500 Internal Server Error');
      header('Content-type: text/plain');
      die("Erreur d'ecriture dans le fichier de logs dans FeatureServer");
    }
  }
  
  static function selfUrl(): string { // Url d'appel sans les paramètres GET
    $url = ($_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http')
          ."://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]"
          .($_SERVER['PATH_INFO'] ?? '');
    //echo "selfUrl=$url\n";
    return $url;
  }
  
  // création d'un des différents types de FeatureServer
  static function new(string $type, string $path, string $f, ?DatasetDoc $datasetDoc): self {
    switch($type) {
      case 'wfs': return new FeatureServerOnWfs("https:/$path", $datasetDoc);
      case 'file': return new FeatureServerOnFile($path, $datasetDoc);
      case 'mysqlIt': $type = 'mysql';
      case 'mysql':
      case 'pgsql': return new FeatureServerOnSql("$type:/$path", $datasetDoc);
      default: output($f, ['error'=> "traitement $type non défini"]);
    }
  }
  
  function landingPage(string $f): array { // retourne l'info de la landing page
    $selfurl = self::selfUrl();
    $dataId = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
    $title = $this->datasetDoc->title();
    //echo "title=$title\n";
    $abstract = $this->datasetDoc->abstract();
    //echo "abstract=$abstract\n";
    return [
      'title'=> $title ?? "Access to $dataId data using OGC API Features specification",
      'description'=>
        $title ?
          "Accès au jeu de données \"$title\" au travers d'une API conforme à la norme OGC API Features"
            .($abstract ? "\n\n$abstract": '')
        : "Access to $dataId data via a Web API that conforms to the OGC API Features specification.",
      'links'=> [
        [
          'href'=> $selfurl.(($f<>'json') ? '?f=json' : ''),
          'rel'=> ($f == 'json') ? 'self' : 'alternate',
          'type'=> 'application/json',
          'title'=> "this document in JSON",
        ],
        [
          'href'=> $selfurl.(($f<>'html') ? '?f=html' : ''),
          'rel'=> ($f == 'html') ? 'self' : 'alternate',
          'type'=> 'text/html',
          'title'=> "this document in HTML",
        ],
        [
          'href'=> $selfurl.(($f<>'yaml') ? '?f=yaml' : ''),
          'rel'=> ($f == 'yaml') ? 'self' : 'alternate',
          'type'=> 'application/x-yaml',
          'title'=> "this document in Yaml",
        ],
        [
          'href'=> "$selfurl/api".(($f<>'json') ? '?f=json' : ''),
          'rel'=> 'service-desc',
          'type'=> 'application/vnd.oai.openapi+json;version=3.0',
          'title'=> "the API documentation in JSON",
        ],
        [
          'href'=> "$selfurl/api".(($f<>'html') ? '?f=html' : ''),
          'rel'=> 'service-doc',
          'type'=> 'text/html',
          'title'=> "the API documentation in HTML",
        ],
        [
          'href'=> "$selfurl/api".(($f<>'yaml') ? '?f=yaml' : ''),
          'rel'=> 'service-desc',
          'type'=> 'application/x-yaml',
          'title'=> "the API documentation in Yaml",
        ],
        [
          'href'=> "$selfurl/conformance".(($f<>'json') ? '?f=json' : ''),
          'rel'=> 'conformance',
          'type'=> 'application/json',
          'title'=> "OGC API conformance classes implemented by this server in JSON",
        ],
        [
          'href'=> "$selfurl/conformance".(($f<>'html') ? '?f=html' : ''),
          'rel'=> 'conformance',
          'type'=> 'text/html',
          'title'=> "OGC API conformance classes implemented by this server in Html",
        ],
        [
          'href'=> "$selfurl/conformance".(($f<>'yaml') ? '?f=yaml' : ''),
          'rel'=> 'conformance',
          'type'=> 'application/x-yaml',
          'title'=> "OGC API conformance classes implemented by this server in Yaml",
        ],
        [
          'href'=> "$selfurl/collections".(($f<>'json') ? '?f=json' : ''),
          'rel'=> 'data',
          'type'=> 'application/json',
          'title'=> "Information about the feature collections in JSON",
        ],
        [
          'href'=> "$selfurl/collections".(($f<>'html') ? '?f=html' : ''),
          'rel'=> 'data',
          'type'=> 'text/html',
          'title'=> "Information about the feature collections in Html",
        ],
        [
          'href'=> "$selfurl/collections".(($f<>'yaml') ? '?f=yaml' : ''),
          'rel'=> 'data',
          'type'=> 'application/x-yaml',
          'title'=> "Information about the feature collections in Yaml",
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

  function api(): array { // retourne la définition de l'API par défaut 
    $urlLandingPage = ($_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http')
          ."://$_SERVER[HTTP_HOST]".dirname($_SERVER['REQUEST_URI']);
    $apidef = Yaml::parse(@file_get_contents(__DIR__.'/apidef.yaml'));
    
    // définition de la LandingPage
    $apidef['servers'][0]['url'] = $urlLandingPage;

    // intégration dans la déf. de l'API de la valeur max de limit
    $apidef['components']['parameters']['limit']['schema']['maximum'] = self::MAX_LIMIT;
    return $apidef;
  }
  
  // vérifie que la bbox est correcte, sinon lève une SExcept self::ERROR_BAD_BBOX
  static function checkBbox(array $bbox): void {
    if (count($bbox) <> 4)
      throw new SExcept("Erreur sur bbox qui ne correspond pas à 4 coordonnées", self::ERROR_BAD_BBOX);
    if (!is_numeric($bbox[0]) || !is_numeric($bbox[1]) || !is_numeric($bbox[2]) || !is_numeric($bbox[3]))
      throw new SExcept("Erreur sur bbox qui ne correspond pas à 4 coordonnées", self::ERROR_BAD_BBOX);
    if ($bbox[0] >= $bbox[2])
      throw new SExcept("Erreur sur bbox bbox[0] >= bbox[2]", self::ERROR_BAD_BBOX);
    if ($bbox[1] >= $bbox[3])
      throw new SExcept("Erreur sur bbox bbox[1] >= bbox[3]", self::ERROR_BAD_BBOX);
    if (($bbox[0] > 180) || ($bbox[0] < -180))
      throw new SExcept("Erreur sur bbox[0] > 180 ou < -180", self::ERROR_BAD_BBOX);
    if (($bbox[2] > 180) || ($bbox[2] < -180))
      throw new SExcept("Erreur sur bbox[2] > 180 ou < -180", self::ERROR_BAD_BBOX);
    if (($bbox[1] > 90) || ($bbox[1] < -90))
      throw new SExcept("Erreur sur bbox[1] > 90 ou < -90", self::ERROR_BAD_BBOX);
    if (($bbox[3] > 90) || ($bbox[3] < -90))
      throw new SExcept("Erreur sur bbox[3] > 90 ou < -90", self::ERROR_BAD_BBOX);
  }
  
  function checkParams(string $path): void { // détecte les paramètres non prévus et lève alors une exception 
    static $params = [ // liste des paramètres autorisés et des valeurs autorisées
      '/'=> ['f'=> ['json','html','yaml']],
      '/conformance'=> ['f'=> ['json','html','yaml']],
      '/api'=> ['f'=> ['json','html','yaml']],
      '/collections'=> ['f'=> ['json','html','yaml']],
      '/collections/{collectionId}'=> ['f'=> ['json','html','yaml']],
      '/collections/{collectionId}/describedBy'=> ['f'=> ['json','html','yaml']],
      '/collections/{collectionId}/items/{featureId}'=> ['f'=> ['json','html','yaml']],
    ];
    if (isset($params[$path])) {
      if ($adiff = array_diff(array_keys($_GET), array_keys($params[$path])))
        throw new SExcept("Paramètre(s) ".implode(',', $adiff)." interdit(s) pour $path", self::ERROR_BAD_PARAMS);
      foreach ($_GET as $k => $v) {
        if (!in_array($v, $params[$path][$k]))
          throw new SExcept("Valeur '$v' interdite pour le paramètre '$k'", self::ERROR_BAD_PARAMS);
      }
    }
    elseif (preg_match('!^/collections/[^/]+/items$!', $path)) {
      $params = ['f','limit','startindex','bbox','datetime'];
      if (isset($_GET['f']) && !in_array($_GET['f'], ['json','html','yaml']))
        throw new SExcept("Valeur '$_GET[f]' interdite pour le paramètre 'f'", self::ERROR_BAD_PARAMS);
      if (isset($_GET['limit'])) {
        if (!ctype_digit($_GET['limit']))
          throw new SExcept("Valeur '$_GET[limit]' non entière interdite pour le paramètre 'limit'", self::ERROR_BAD_PARAMS);
        if (((int)$_GET['limit'] > get_class($this)::MAX_LIMIT) || ((int)$_GET['limit'] < 1))
          throw new SExcept("Valeur du paramètre 'limit'='$_GET[limit]' hors intervalle [1, ".get_class($this)::MAX_LIMIT."]", 400);
      }
      if (isset($_GET['startindex'])) {
        if (!ctype_digit($_GET['startindex']))
          throw new SExcept("Valeur '$_GET[startindex]' non entière interdite pour le paramètre 'startindex'", self::ERROR_BAD_PARAMS);
        if ((int)$_GET['startindex'] < 0)
          throw new SExcept("Valeur '$_GET[startindex]' < 0 pour le paramètre 'startindex'", self::ERROR_BAD_PARAMS);
      }
      $apidef = $this->api();
      $apidef = $apidef['paths'][$path]['get']['parameters'] ?? [];
      foreach ($apidef as $parameterdef)
        if (isset($parameterdef['name']))
          $params[] = $parameterdef['name'];
      if ($adiff = array_diff(array_keys($_GET), $params))
        throw new SExcept("Paramètre(s) ".implode(',', $adiff)." interdit(s) pour $path", self::ERROR_BAD_PARAMS);
    }
    else {
      throw new SExcept("path $path non prévu dans FeatureServer::checkParams()", self::ERROR_BAD_PARAMS);
    }
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
  
  // retourne les items de la collection comme array Php, soit sous la forme itérable soit comme array
  //abstract function itemsIterable(string $f, string $collId, array $bbox=[], int $limit=10, int $startindex=0): array;
  //abstract function items(string $f, string $collId, array $bbox=[], int $limit=10, int $startindex=0): array;
  
  // retourne l'item $featureId de la collection comme array Php
  abstract function item(string $f, string $collId, string $featureId): array;
};

require_once __DIR__.'/ftsonsql.inc.php';
require_once __DIR__.'/ftsonwfs.inc.php';
//require_once __DIR__.'/ftsonfile.inc.php';
