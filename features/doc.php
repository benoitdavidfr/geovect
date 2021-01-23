<?php
/*PhpDoc:
name: doc.php
title: utilisation de la doc
doc: |
  Nbses erreurs sur troncon_route
    - 'Erreur .properties.sens="Sens unique" not in enum=["Double sens","Sens direct","Sens inverse"]'
    - 'Erreur .properties.acces="Inconnu" not in enum=["A péage","Libre"]'
    - 'Erreur .properties.acces="Saisonnier" not in enum=["A péage","Libre"]'

journal: |
  19/1/2021:
    création
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../schema/jsonschema.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;


class PropertyDoc {
  protected string $id;
  protected string $title;
  protected string $description;
  protected string $type;
  protected array $enum;
  
  function __construct(string $id, array $yaml) {
    {/*property:
      description: |
        description d'une propriété d'une collection.
        Contrairement aux schema JSON, si enum est utilisé le type n'est pas nécessaire.
      type: object
      additionalProperties: false
      required: [title]
      properties:
        title:
          description: titre de la propriété destiné à un humain
          type: string
        description:
          description: descriptions plus détaillée de la propriété
          type: string
        mandatory:
          description: Une valeur est-elle obligatoire pour cette propriété ?
          type: string
          enum: [yes, no]
        identifier:
          description: Propriété identifiant l'objet (clé primaire)
          type: string
          enum: [yes, no]
        type:
          description: type de la propriété
          type: string
          enum:
            - string
            - integer
            - number
        enum:
          $ref: '#/definitions/enumType'
        unit:
          description: si la propriété décrit une mesure alors indique l'unité de cette mesure
          enum:
            - tonne
            - meter
        specificValues:
          description: |
            si certaines valeurs ont une signification particulière alors liste de ces valeurs et de leur signification
            sous la forme d'un dictionnaire indiquant pour chaque valeur sa signification.
          oneOf:
            - type: object
              patternProperties:
                ^[-a-zA-Z0-9_\.]*$:
                  description: signification de la valeur
                  type: string
            - description: cas particulier où les valeurs sont les premiers entiers positifs ou nuls
              type: array
    */} 
    $this->id = $id;
    $this->title = $yaml['title'];
    $this->description = $yaml['description'] ?? '';
    $this->type = $yaml['type'] ?? '';
    $this->enum = $yaml['enum'] ?? [];
  }
  
  function asArray(): array {
    return [
      'title'=> $this->title,
    ]
    + ($this->type ? ['type'=> $this->type] : [])
    + ($this->enum ? ['enum'=> $this->enum] : []);
  }
  
  function schema(): array {
    $enum = $this->enum;
    if ($enum && (array_keys($enum)[0] <> 0)) {
      $vals = [];
      foreach ($enum as $val => $desc) {
        $vals[] = $val;
      }
      $enum = $vals;
    }
    return [
      'description'=> $this->title,
    ]
    + ($this->type ? ['type'=> /*$this->type*/'string'] : [])
    + ($enum ? ['type'=> 'string', 'enum'=> $enum] : []);
  }
};

class CollectionDoc {
  const COORDS_TYPE = [
    'pos2'=> [
      'type'=> 'array',
      'minItems'=> 2,
      'maxItems'=> 2,
      'items'=> ['type'=> 'number'],
    ],
    'lpos2'=> [
      'type'=> 'array',
      'items'=> [
        'type'=> 'array',
        'minItems'=> 2,
        'maxItems'=> 2,
        'items'=> ['type'=> 'number'],
      ],
    ],
    'llpos2'=> [
      'type'=> 'array',
      'items'=> [
        'type'=> 'array',
        'items'=> [
          'type'=> 'array',
          'items'=> ['type'=> 'number'],
        ],
      ],
    ],
    'l3pos2'=> [
      'type'=> 'array',
      'items'=> [
        'type'=> 'array',
        'items'=> [
          'type'=> 'array',
          'items'=> [
            'type'=> 'array',
            'items'=> ['type'=> 'number'],
          ],
        ],
      ],
    ],
  ];
  const GEOM_TYPE_PROP = [
    'Point2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Point']],
      'coordinates'=> self::COORDS_TYPE['pos2'],
    ],
    'MultiPoint2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiPoint']],
      'coordinates'=> self::COORDS_TYPE['lpos2'],
    ],
    'LineString2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['LineString']],
      'coordinates'=> self::COORDS_TYPE['lpos2'],
    ],
    'MultiLineString2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Polygon']],
      'coordinates'=> self::COORDS_TYPE['llpos2'],
    ],
    'Polygon2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Polygon']],
      'coordinates'=> self::COORDS_TYPE['llpos2'],
    ],
    'MultiPolygon2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiPolygon']],
      'coordinates'=> self::COORDS_TYPE['l3pos2'],
    ],
  ];
  
  protected string $id;
  protected string $title;
  protected ?string $description;
  protected string|array $geometryType;
  protected array $properties;
  
  function schema() { /* Schema JSON 
    collection:
      description: description d'une collection d'un jeu de données
      type: object
      additionalProperties: false
      required: [title, geometryType]
      properties:
        title:
          description: titre de la collection destiné à un humain
          type: string
        description:
          description: |
            description plus détaillée, comprend la définition, les critères de sélection, ...
            Possibilité d'utiliser du markdown.
          type: string
        geometryType:
          description: |
            Type(s) de géométrie des objets de la classe.
            Construit à partir du type GeoJSON en y ajoutant éventuellement 2D/3D ainsi que le type none
            indiquant que les objets n'ont pas de géométrie.
            Peut être soit un type ou une liste de types possibles.
          oneOf:
            - $ref: '#/definitions/elementaryGeometryTypeType'
            - type: array
              items:
                $ref: '#/definitions/elementaryGeometryTypeType'
          enum:
            - Point
            - Point2D
            - Point3D
            - MultiPoint
            - MultiPoint2D
            - MultiPoint3D
            - LineString
            - LineString2D
            - LineString3D
            - MultiLineString
            - MultiLineString2D
            - MultiLineString3D
            - Polygon
            - Polygon2D
            - Polygon3D
            - MultiPolygon
            - MultiPolygon2D
            - MultiPolygon3D
            - GeometryCollection
            - none
        properties:
          description: |
            Dictionnaire des propriétés des items de la collection indexé sur le nom de la propriété.
            La description est optionelle.
          type: object
          patternProperties:
            ^[-a-zA-Z0-9_]*$:
              $ref: '#/definitions/property'
    */
  }
    
  function __construct(string $id, array $yaml) {
    $this->id = $id;
    $this->title = $yaml['title'];
    $this->description = $yaml['description'] ?? null;
    $this->geometryType = $yaml['geometryType'];
    foreach ($yaml['properties'] as $propid => $property) {
      $this->properties[$propid] = new PropertyDoc($propid, $property);
    }
  }
  
  function __get(string $name) { return isset($this->$name) ? $this->$name : null; }

  function asArray(): array {
    $array = ['title'=> $this->title];
    if ($this->description)
      $array['description'] = $this->description;
    $array['geometryType'] = $this->geometryType;
    foreach ($this->properties as $id => $prop) {
      $array['properties'][$id] = $prop->asArray();
    }
    return $array;
  }

  private function geometrySchema() { // Schema de la géométrie 
    if (is_string($this->geometryType))
      return [
        'type'=> 'object',
        'properties'=> self::GEOM_TYPE_PROP[$this->geometryType],
      ];
    $schemaAlternatives = [];
    foreach ($this->geometryType as $gt)
      $schemaAlternatives[] = [ 'type'=> 'object', 'properties'=> self::GEOM_TYPE_PROP[$gt] ];
    return ['oneOf'=> $schemaAlternatives];
  }
  
  function featureSchema(): array { // schema d'un Feature 
    $propSchema = [];
    foreach ($this->properties as $propId => $prop) {
      $propSchema[$propId] = $prop->schema(); 
    }
    $propSchema['id'] = ['type'=> 'string'];
    
    $fschema = ['title'=> $this->title];
    if ($this->description)
      $fschema['description'] = $this->description;
    return array_merge($fschema,
      [ '$schema'=> 'http://json-schema.org/schema#',
         'type'=> 'object',
         'required'=> ['id','properties','geometry'],
         'properties'=> [
           'id'=> ['type'=> 'string'],
           'properties'=> [
             'type'=> 'object',
             'additionalProperties'=> false,
             'properties'=> $propSchema,
           ],
           'geometry'=> $this->geometrySchema(),
         ],
      ]
    );
  }
};

class DatasetDoc { // Doc d'un Dataset 
  protected string $id;
  protected string $title;
  protected ?string $abstract;
  protected array $licence;
  protected string $path;
  protected array $collections = [];
  
  function schema() { /* Schema JSON: 
    dataset:
      type: object
      additionalProperties: false
      required: [title, path]
      properties:
        title:
          description: titre du jeu de données
          type: string
        abstract:
          description: résumé du jeu de données
          type: string
        source:
          description: URL de référence du jeu de données
          type: string
        licence:
          description: définition de la licence d'utilisation des données
          $ref: '#/definitions/link'
        path:
          description: chemin du jeu de données pour https://features.geoapi.fr/
          type: string
        doc_url:
          description: lien vers une documentation plus complète
          type: string
          format: uri
        metadata:
          description: lien vers des MD par ex. ISO 19139
          type: string
          format: uri
        precision:
          description: |
            nbre de chiffres signficatifs dans les coordonnées géographiques
            ex: 4 => résolution de de 1e-4 degrés soit 1e-4° * 40 km / 360° = 11 m
        collections:
          description: dictionnaire des collections indexées sur l'id de la collection
          type: object
          patternProperties:
            ^[-a-zA-Z0-9_]*$:
              $ref: '#/definitions/collection'
    */
  }
  
  function __construct(string $id, array $yaml) {
    $this->id = $id;
    $this->title = $yaml['title'];
    $this->abstract = $yaml['abstract'] ?? null;
    $this->licence = $yaml['licence'] ?? [];
    $this->path = $yaml['path'];
    foreach ($yaml['collections'] ?? [] as $collid => $collection) {
      $this->collections[$collid] = new CollectionDoc($collid, $collection);
    }
  }
  
  function __get(string $name) { return isset($this->$name) ? $this->$name : null; }
  
  function asArray(): array {
    $array = [];
    foreach ($this->collections as $id => $coll) {
      $array['collections'][$id] = $coll->asArray();
    }
    return $array;
  }
};

class Doc { // Doc globale 
  const PATH = __DIR__.'/doc.'; // chemin des fichiers stockant la doc en pser ou en yaml, lui ajouter l'extension
  const PATH_PSER = self::PATH.'pser'; // chemin du fichier stockant la doc en pser
  const PATH_YAML = self::PATH.'yaml'; // chemin du fichier stockant la doc en Yaml
  const SCHEMA_PATH_YAML = __DIR__.'/doc.schema.yaml'; // chemin du fichier stockant le schema en Yaml

  protected array $datasets; // [DatasetDoc]
  
  // vérifie la conformité du document Yaml notamment à son schéma. En cas d'erreurs les retourne, sinon retourne []
  static function checkYamlConformity(array $yaml=[]): array {
    if (!$yaml) {
      if (!is_file(self::PATH_YAML))
        return ['yamlNotFound'=> "le fichier Yaml est absent"];
      try {
        $yaml = Yaml::parseFile(self::PATH_YAML);
      }
      catch (ParseException $e) {
        return ['yamlParseError'=> $e->getMessage()];
      }
    }
    // vérification de la conformité du fichier chargé au schéma
    try {
      $schema = new JsonSchema(Yaml::parseFile(self::SCHEMA_PATH_YAML));
    }
    catch (ParseException $e) {
      return ['yamlSchemaParseError'=> $e->getMessage()];
    }
    $check = $schema->check($yaml);
    if (!$check->ok())
      return ['checkErrors'=> $check->errors()];
    else
      return [];
  }
  
  function __construct() {
    $yaml = Yaml::parseFile(self::PATH_YAML);
    if (self::checkYamlConformity($yaml))
      throw new Exception("Erreur document Yaml non conforme");
    foreach ($yaml['datasets'] as $dsid => $dataset) {
      $this->datasets[$dsid] = new DatasetDoc($dsid, $dataset);
    }
  }
  
  function asArray(): array {
    $array = [];
    foreach ($this->datasets as $id => $ds) {
      $array['datasets'][$id] = $ds->asArray();
    }
    return $array;
  }
  
  function __get(string $name) { return isset($this->$name) ? $this->$name : null; }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__))) return;
// Utilisation de la classe Doc


if (php_sapi_name() == 'cli') {
  if ($argc == 1) {
    echo "usage: doc.php {action}\n";
    echo "{action}\n";
    echo "  - checkYaml - vérifie le fichier Yaml\n";
    echo "  - checkDataset - vérifie les features des collections du jeu de données\n";
    die();
  }
  else
    $a = $argv[1];
}
else { // sapi <> cli
  $id = isset($_SERVER['PATH_INFO']) ? substr($_SERVER['PATH_INFO'], 1) : ($_GET['id'] ?? null); // id
  $f = $_GET['f'] ?? 'html'; // format, html par défaut
  $a = $_GET['a'] ?? null; // action
  if ($a) {
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>doc</title></head><body>\n";
  }
}

if ($a == 'menu') {
  if (php_sapi_name() <> 'cli') {
    echo "doc.php - Actions proposées:<ul>\n";
    echo "<li><a href='?a=checkYaml'>Vérifie le Yaml</a></li>\n";
    echo "<li>Affiche la doc <a href='?f=yaml'>en Yaml</a>, ";
    echo "<a href='?f=geojson'>en JSON</a>, ";
    echo "<a href='?f=html'>en Html</a></li>\n";
    echo "<li><a href='?a=schema&amp;f=yaml'>Génère un schéma</a></li>\n";
    echo "</ul>\n";
  }
  die();
}

if ($a == 'checkYaml') { // charge le fichier yaml 
  if (!($errors = Doc::checkYamlConformity())) {
    echo "checkYaml ok<br>\n";
  }
  else {
    switch ($errorCode = array_keys($errors)[0]) {
      case 'yamlNotFound': {
        echo "Erreur, le fichier Yaml n'existe pas<br>\n";
        break;
      }
      /*case 'pserIsMoreRecent': {
        echo "<b>Erreur, le fichier pser est plus récent que le fichier Yaml !<br>\n",
          "Soit modifier le fichier Yaml pour le charger, ",
          "soit l'<a href='?a=rewriteYaml'>écraser à partir de la dernière version valide</a> !</b><br>\n";
        break;
      }*/
        case 'yamlSchemaParseError': {
        echo "<b>Erreur, le schema Yaml n'est pas conforme à la syntaxe Yaml</b><br>\n";
        echo '<pre>',Yaml::dump($errors),"</pre>\n";
        break;
      }
      case 'yamlParseError': {
        echo "<b>Erreur, le fichier Yaml n'est pas conforme à la syntaxe Yaml</b><br>\n";
        echo '<pre>',Yaml::dump($errors),"</pre>\n";
        break;
      }
      case 'checkErrors': {
        echo "<b>Erreur, le fichier Yaml n'est pas conforme à son schéma</b><br>\n";
        echo '<pre>',Yaml::dump($errors),"</pre>\n";
        break;
      }
      /*case 'checkWarnings': {
        echo "<b>Alerte de conformité du fichier Yaml par rapport à son schéma</b><br>\n";
        echo Yaml::dump($errors);
        break;
      }
      case 'errorInNew': {
        echo "<b>$result[errorInNew]</b><br>\n";
        echo "<b>Corriger le fichier Yaml ",
             "ou l'<a href='?a=rewriteYaml'>écraser à partir de la dernière version valide</a>.</b><br>\n";
        break;
      }*/
      default: {
        echo '<pre>',Yaml::dump($errors),"</pre>\n";
        throw new Exception("cas imprévu en retour de Doc::loadYaml(): $errorCode");
      }
    }
  }
  die();
}

if ($a == 'schema') {
  $doc = new Doc;
  if (!($ds = $_GET['ds'] ?? null)) {
    foreach ($doc->datasets() as $id => $dataset) {
      echo "<a href='?a=schema&amp;ds=$id'>$id</a><br>\n"; 
    }
  }
  elseif (!($coll = $_GET['coll'] ?? null)) {
    foreach ($doc->dataset($_GET['ds'])->collections() as $id => $coll) {
      echo "<a href='?a=schema&amp;ds=$_GET[ds]&amp;coll=$id'>$id</a><br>\n"; 
    }
  }
  else {
    $schema = $doc->dataset($_GET['ds'])->collection($_GET['coll'])->featureSchema();
    echo '<pre>',Yaml::dump($schema, 7, 2);
    $check = JsonSchema::autoCheck($schema);
    if (!($ok = $check->ok()))
      echo Yaml::dump(['checkErrors'=> $check->errors()]);
    else
      echo "schéma conforme\n";
    echo "<a href='?a=checkData&amp;coll=$_GET[coll]&amp;ds=$_GET[ds]'>checkData</a>\n";
  }
  die();
}

if ($a == 'checkData') {
  $doc = new Doc;
  $collSchema = $doc->dataset($_GET['ds'])->collection($_GET['coll'])->featureSchema();
  echo '<pre>',Yaml::dump($collSchema, 7, 2);
  $schema = new JsonSchema($collSchema, false);
  $url = "http://localhost/geovect/features/fts.php/$_GET[ds]/collections/$_GET[coll]/items?limit=10&f=json";
  $json = file_get_contents($url);
  echo "json=$json\n";
  $fc = json_decode($json, true);
  foreach ($fc['features'] as $feature) {
    $check = $schema->check($feature);
    if (!($ok = $check->ok())) {
      echo Yaml::dump(['properties'=> $feature['properties']], 7, 2);
      echo Yaml::dump(['checkErrors'=> $check->errors()]);
    }
    else
      echo "document id=$feature[id] conforme\n";
  }
  die();
}

function nextUrl(array $response): string { // extrait dans une réponse le lien next 
  foreach ($response['links'] as $link) {
    if ($link['rel'] == 'next')
      return $link['href'];
  }
  return '';
}

if ($a == 'checkDataset') {
  $doc = new Doc;
  if ($argc <= 2) {
    echo "Choisir un dataset:\n";
    foreach ($doc->datasets as $id => $dataset) {
      echo "  - $id\n";
    }
    die();
  }
  $dsid = $argv[2];
  foreach ($doc->datasets[$dsid]->collections as $collid => $coll) {
    echo "\ncollection **$collid**\n";
    if (!in_array($collid, ['troncon_route'])) {
      echo "  skipped\n";
      continue;
    }
    $collSchema = $coll->featureSchema();
    //echo Yaml::dump($collSchema, 7, 2);
    $check = JsonSchema::autoCheck($collSchema);
    if (!($ok = $check->ok())) {
      echo Yaml::dump(['checkErrors'=> $check->errors()]);
      die();
    }
    //echo "schéma conforme\n";
    $schema = new JsonSchema($collSchema, false);
    $url = "http://localhost/geovect/features/fts.php/$dsid/collections/$collid/items?limit=100&startindex=0&f=json";
    while (true) {
      echo "url=$url\n";
      $json = file_get_contents($url);
      //echo "json=$json\n";
      $fc = json_decode($json, true);
      foreach ($fc['features'] as $feature) {
        $check = $schema->check($feature);
        if (!($ok = $check->ok())) {
          //echo Yaml::dump(['properties'=> $feature['properties']], 7, 2);
          //echo "Erreurs détectées sur le feature $feature[id]\n";
          echo Yaml::dump(["Erreurs sur le feature $feature[id]"=> $check->errors()]);
        }
        //else
          //echo "document id=$feature[id] conforme\n";
      }
      if (!($url = nextUrl($fc)))
        break;
    }
  }
  die();
}

if ($f == 'html') { // affichage html
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>doc</title></head><body><pre>\n";
  $doc = new Doc;
  print_r($doc);
}

if ($f == 'yaml') {
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>doc</title></head><body><pre>\n";
  $doc = new Doc;
  echo Yaml::dump($doc->asArray(), 9, 2);
}
/*

if ($a == 'rewriteYaml') { // réécrit le Yaml à partir du pser, et récérit le pser pour que le Yaml ne soit plus plus récent
  MapCat::storeAsYaml(['force'=> true]);
  MapCat::storeAsPser();
  echo "rewriteYaml ok<br>\n";
}




if ($f == 'html') { // affichage html
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>mapcat</title></head><body>\n";
  if ($id) { // une carte
    if ($map = MapCat::mapById($id)) {
      echo "<table><tr>";
      $request_scheme = $_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http';
      $shomgturl = "$request_scheme://$_SERVER[HTTP_HOST]".dirname(dirname($_SERVER['SCRIPT_NAME']));
      $num = substr($id, 2);
      $imgurl = "$shomgturl/ws/dl.php/$num.png";
      echo "<td><img src='$imgurl'></td>\n";
      echo "<td valign='top'><pre>id: $id\n";
      echo Yaml::dump($map->asArray(), 4, 2);
      echo "</tr></table>\n";
    }
    else
      echo "'$id' ne correspond pas à l'id d'une carte de MapCat\n";
  }
  else { // tout le catalogue
    try {
      $maps = MapCat::maps();
      echo "<h2>Catalogue des cartes</h2>\n";
      echo "En <a href='?f=yaml'>Yaml</a>, en <a href='?f=geojson'>GeoJSON</a>, comme <a href='?f=map'>carte LL</a>, ",
        "<a href='?a=menu'>autres actions</a>.<br>\n";
      echo "<table border=1><th>",implode('</th><th>', ['id/yml','title/map','scaleDen','edition','mapsFrance']),"</th>\n";
      foreach ($maps as $mapid => $map) {
        $mapa = $map->asArray();
        $llp = llmapParams($map);
        $llmapurl = sprintf('llmap.php?lat=%.2f&amp;lon=%.2f&amp;zoom=%d&amp;mapid=%s', $llp['lat'], $llp['lon'], $llp['zoom'], $mapid);
        $br = (strlen($mapa['groupTitle'] ?? '') + strlen($mapa['title']) > 90) ? '<br>' : ' - ';
        //echo "<tr><td colspan=5><pre>"; print_r($mapa); echo "</td></tr>\n";
        echo "<tr><td><a href='$_SERVER[SCRIPT_NAME]/$mapid'>$mapid</a></td>",
          "<td>",isset($mapa['groupTitle']) ? "$mapa[groupTitle]$br" : '',"<a href='$llmapurl'>$mapa[title]</a></td>",
          //"<td>",strlen($mapa['groupTitle'] ?? '')+strlen($mapa['title']),"</td>",
          "<td align='right'>",$mapa['scaleDenominator'] ?? '<i>'.$mapa['insetMaps'][0]['scaleDenominator'].'</i>',"</td>",
          "<td>",$mapa['edition'] ?? 'non définie',"</td>",
          "<td>",implode(', ', $mapa['mapsFrance']),"</td>",
          "</tr>\n";
      }
      echo "</table>\n";
    } catch (Exception $e) {
      echo $e->getMessage(),"<br>\n";
      echo "Soit <a href='?a=loadYaml'>charger le fichier Yaml pour écraser le pser</a>, ",
           "soit l'<a href='rewriteYaml'>écraser à partir du fichier pser</a> !<br>\n";
    }
  }
  die();
}

if ($f == 'yaml') { // affichage en yaml 
  if (php_sapi_name() <> 'cli')
    echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>mapcat</title></head><body>",
      "<a href='?a=menu'>retour</a><pre>\n";
  if ($id) { // une carte particulière
    echo "id: $id\n";
    echo Yaml::dump(MapCat::mapById($id)->asArray(), 4, 2);
  }
  else // tout le catalogue
    echo Yaml::dump(MapCat::allAsArray(), 5, 2);
  die();
}

if ($f == 'geojson') { // affichage en GeoJSON 
  header('Access-Control-Allow-Origin: *');
  header('Content-type: application/json; charset="utf8"');
  //header('Content-type: text/plain; charset="utf8"');
  $nbre = 0;
  echo '{"type":"FeatureCollection","features":[',"\n";

  if ($id) {
    echo json_encode(MapCat::maps()[$id]->geojson(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); 
  }
  else {
    $sdmin = $_GET['sdmin'] ?? (((php_sapi_name()=='cli') && ($argc > 1)) ? $argv[1] : null);
    $sdmax = $_GET['sdmax'] ?? (((php_sapi_name()=='cli') && ($argc > 2)) ? $argv[2] : null);
    
    foreach (MapCat::maps() as $id => $map) {
      $scaleD = $map->scaleDenAsInt();
      if ($sdmax && ($scaleD > $sdmax))
        continue;
      if ($sdmin && ($scaleD <= $sdmin))
        continue;
    
      echo $nbre++ ? ",\n" : '',
          json_encode($map->geojson(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); 
    }
  }

  echo "\n]}\n";
  die();
}

if ($f == 'map') { // affichage de la carte LL 
  if ($id) { // pour une carte
    $llp = llmapParams(MapCat::mapById($id));
    $_GET = ['lat'=> $llp['lat'], 'lon'=> $llp['lon'], 'zoom'=> $llp['zoom'], 'mapid'=> $id];
  }
  require __DIR__.'/llmap.php';
  die();
}

die("Action non prévue\n");
*/
