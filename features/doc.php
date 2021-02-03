<?php
/*PhpDoc:
name: doc.php
title: doc.php - utilisation de la doc
doc: |


  Nbses erreurs sur troncon_route
    - 'Erreur .properties.sens="Sens unique" not in enum=["Double sens","Sens direct","Sens inverse"]'
    - 'Erreur .properties.acces="Inconnu" not in enum=["A péage","Libre"]'
    - 'Erreur .properties.acces="Saisonnier" not in enum=["A péage","Libre"]'

journal: |
  3/2/2021:
    - modif lien dataset -> specification par un pointeur JSON
  31/1/2021:
    restructuration de doc.yaml pour distinguer les jeux de données de leur spécification
  19/1/2021:
    création
includes: [../../schema/jsonschema.inc.php]
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../schema/jsonschema.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Michelf\MarkdownExtra;

ini_set('memory_limit', '10G');


class JsonPointer {
  protected $ref; // lien
  protected $cdir; // répertoire courant
  
  function __construct(string $ref, string $cdir) { $this->ref = $ref; $this->cdir = $cdir; }
  
  function __get(string $name) {
    return isset($this->$name) ? $this->$name : null;
  }
};


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
    'pos'=> [
      'type'=> 'array',
      'minItems'=> 2,
      'maxItems'=> 3,
      'items'=> ['type'=> 'number'],
    ],
    'lpos'=> [
      'type'=> 'array',
      'items'=> [
        'type'=> 'array',
        'minItems'=> 2,
        'maxItems'=> 3,
        'items'=> ['type'=> 'number'],
      ],
    ],
    'llpos'=> [
      'type'=> 'array',
      'items'=> [
        'type'=> 'array',
        'items'=> [
          'type'=> 'array',
          'minItems'=> 2,
          'maxItems'=> 3,
          'items'=> ['type'=> 'number'],
        ],
      ],
    ],
    'l3pos'=> [
      'type'=> 'array',
      'items'=> [
        'type'=> 'array',
        'items'=> [
          'type'=> 'array',
          'items'=> [
            'type'=> 'array',
            'minItems'=> 2,
            'maxItems'=> 3,
            'items'=> ['type'=> 'number'],
          ],
        ],
      ],
    ],
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
          'minItems'=> 2,
          'maxItems'=> 2,
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
            'minItems'=> 2,
            'maxItems'=> 2,
            'items'=> ['type'=> 'number'],
          ],
        ],
      ],
    ],
  ];
  const GEOM_TYPE_PROP = [
    'Point'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Point']],
      'coordinates'=> self::COORDS_TYPE['pos'],
    ],
    'Point2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Point']],
      'coordinates'=> self::COORDS_TYPE['pos2'],
    ],
    'MultiPoint2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiPoint']],
      'coordinates'=> self::COORDS_TYPE['lpos2'],
    ],
    'MultiPoint'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiPoint']],
      'coordinates'=> self::COORDS_TYPE['lpos'],
    ],
    'LineString'=> [
      'type' => ['type'=> 'string', 'enum'=> ['LineString']],
      'coordinates'=> self::COORDS_TYPE['lpos'],
    ],
    'LineString2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['LineString']],
      'coordinates'=> self::COORDS_TYPE['lpos2'],
    ],
    'MultiLineString'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiLineString']],
      'coordinates'=> self::COORDS_TYPE['llpos'],
    ],
    'MultiLineString2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiLineString']],
      'coordinates'=> self::COORDS_TYPE['llpos2'],
    ],
    'Polygon'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Polygon']],
      'coordinates'=> self::COORDS_TYPE['llpos'],
    ],
    'Polygon2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['Polygon']],
      'coordinates'=> self::COORDS_TYPE['llpos2'],
    ],
    'MultiPolygon'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiPolygon']],
      'coordinates'=> self::COORDS_TYPE['l3pos'],
    ],
    'MultiPolygon2D'=> [
      'type' => ['type'=> 'string', 'enum'=> ['MultiPolygon']],
      'coordinates'=> self::COORDS_TYPE['l3pos2'],
    ],
  ];
  
  protected string $id;
  protected string $title;
  protected ?string $description=null;
  protected string|array $geometryType;
  protected array $properties=[];
  
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
    if (isset($yaml['description']))
      $this->description = rtrim($yaml['description']); // Il y a souvent un \n à la fin de la chaine
    $this->geometryType = $yaml['geometryType'];
    foreach ($yaml['properties'] ?? [] as $propid => $property) {
      $this->properties[$propid] = new PropertyDoc($propid, $property);
    }
  }
  
  function __get(string $name) { return isset($this->$name) ? $this->$name : null; }

  function asArray(): array {
    $array = ['title'=> $this->title];
    if ($this->description) {
      //$array['description'] = MarkdownExtra::defaultTransform($this->description); // Test utilisation Markdown pas concluant
      $array['description'] = $this->description;
    }
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
             //'additionalProperties'=> false, // dans la doc on ne décrit pas forcément toutes les properties
             'properties'=> $propSchema,
           ],
           'geometry'=> $this->geometrySchema(),
         ],
      ]
    );
  }
};

class SpecDoc { // Spec d'un dataset
  protected string $id;
  protected string $title;
  protected ?string $abstract;
  protected array $collections = [];
  
  function schema() { /* Schema JSON: 
    specification:
      description: |
        Spécification d'un jeu de données.
        C'est un standard (http://purl.org/dc/terms/Standard) pour un jeu de données.
        La définition DublinCore du standard est: A reference point against which other things can be evaluated or compared.
      type: object
      additionalProperties: false
      required: [title]
      properties:
        title:
          description: titre de la spécification
          type: string
        abstract:
          description: résumé du jeu de données
          type: string
        identifier:
          description: URI de référence de la spécification
          type: string
        metadata:
          description: lien vers des MD par ex. ISO 19139
          type: string
          format: uri
        precision:
          description: |
            nbre de chiffres signficatifs dans les coordonnées géographiques
            ex: 4 => résolution de de 1e-4 degrés soit 1e-4° * 40 km / 360° = 11 m
          type: integer
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
    foreach ($yaml['collections'] ?? [] as $collid => $collection) {
      $this->collections[$collid] = new CollectionDoc($collid, $collection);
    }
  }
  
  function __toString(): string { return $this->title; }
  
  function __get(string $name) {
    //echo "SpecDoc::__get($name)\n";
    return isset($this->$name) ? $this->$name : null;
  }
  
  function asArray(): array {
    $array = ['title'=> $this->title];
    foreach ($this->collections as $id => $coll) {
      $array['collections'][$id] = $coll->asArray();
    }
    return $array;
  }
};

class DatasetDoc { // Doc d'un Dataset 
  protected string $id;
  protected string $title;
  protected array $licence;
  protected string $path;
  protected SpecDoc|JsonPointer|null $conformsTo;
  
  function schema() { /* Schema JSON: 
    dataset:
      description: |
        Description d'un jeu de données.
        Le concept de jeu de données est identique à http://www.w3.org/ns/dcat#Dataset
        dont la définition est:
          A collection of data, published or curated by a single agent, and available for access or download in one or more
          representations.
      type: object
      additionalProperties: false
      required: [title, path]
      properties:
        title:
          description: titre du jeu de données
          type: string
        identifier:
          description: URI de référence du jeu de données
          type: string
        licence:
          description: définition de la licence d'utilisation des données
          $ref: '#/definitions/link'
        path:
          description: chemin du jeu de données pour https://features.geoapi.fr/
          type: string
        metadata:
          description: lien vers des MD par ex. ISO 19139
          type: string
          format: uri
        conformsTo:
          description: identifiant de la spécification du jeu de données défini dans le dictionnaire specifications
          type: string
    */
  }
  
  static function deref(JsonPointer $jp): SpecDoc { // Déréférence le pointeur vers la spécification
    if (!preg_match('!^([^#]+)#/specifications/([^/]+)$!', $jp->ref, $matches))
      throw new Exception("Pointeur Json ".$jp->ref." incorrect");
    $filepath = $matches[1];
    $specid = $matches[2];
    $subdoc = new Doc($filepath, $jp->cdir);
    return $subdoc->specifications[$specid];
  }
  
  /*function deref(): SpecDoc {
  }*/
    
  function __construct(string $id, array $yaml) {
    $this->id = $id;
    $this->title = $yaml['title'];
    $this->licence = $yaml['licence'] ?? [];
    $this->path = $yaml['path'];
    $this->conformsTo = $yaml['conformsTo'] ?? null;
  }
  
  function __get(string $name) {
    //echo "DatasetDoc::__get($name)\n";
    if (!in_array($name, ['collections','abstract']))
      return isset($this->$name) ? $this->$name : null;
    elseif (!$this->conformsTo)
      return null;
    elseif (get_class($this->conformsTo)=='SpecDoc')
      return $this->conformsTo->$name;
    else { // JsonPointer
      $this->conformsTo = self::deref($this->conformsTo);
      return $this->conformsTo->$name;
    }
  }
    
  function asArray(): array {
    $array = ['title'=> $this->title, 'path'=> $this->path];
    if ($this->licence)
      $array['licence'] = $this->licence;
    if ($this->conformsTo) {
      if (get_class($this->conformsTo)=='SpecDoc') {
        //echo '$this->conformsTo='; print_r($this->conformsTo);
        $array['conformsTo'] = $this->conformsTo->asArray();
      }
      else {
        $this->conformsTo = self::deref($this->conformsTo);
        $array['conformsTo'] = $this->conformsTo->asArray();
      }
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
  protected array $specifications; // [SpecDoc]
  
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
  
  function __construct(string $path=self::PATH_YAML) {
    $yaml = Yaml::parseFile($path);
    if (self::checkYamlConformity($yaml))
      throw new Exception("Erreur document Yaml non conforme");
    foreach ($yaml['specifications'] ?? [] as $id => $spec) {
      $this->specifications[$id] = new SpecDoc($id, $spec);
    }
    foreach ($yaml['datasets'] ?? [] as $dsid => $dataset) {
      //echo "dataset=$dsid\n";
      if ($specPointer = ($dataset['conformsTo'] ?? null)) {
        if (substr($specPointer['$ref'], 0, 1)=='#') {
          $specid = substr($specPointer['$ref'], strlen('#/specifications/'));
          //echo "specid=$specid\n";
          $dataset['conformsTo'] = $this->specifications[$specid] ?? null;
        }
        else {
          $dataset['conformsTo'] = new JsonPointer($specPointer['$ref'], dirname($path));
        }
      }
      //echo "  conformsTo=",$dataset['conformsTo'] ?? null,"\n";
      $this->datasets[$dsid] = new DatasetDoc($dsid, $dataset);
      //print_r($this->datasets[$dsid]);
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
    foreach ($doc->datasets as $id => $dataset) {
      echo "<a href='?a=schema&amp;ds=$id'>$id</a><br>\n"; 
    }
  }
  elseif (!($coll = $_GET['coll'] ?? null)) {
    foreach ($doc->datasets[$_GET['ds']]->collections as $id => $coll) {
      echo "<a href='?a=schema&amp;ds=$_GET[ds]&amp;coll=$id'>$id</a><br>\n"; 
    }
  }
  else {
    $schema = $doc->datasets[$_GET['ds']]->collections[$_GET['coll']]->featureSchema();
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
  $collSchema = $doc->datasets[$_GET['ds']]->collections[$_GET['coll']]->featureSchema();
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
    /*if (!in_array($collid, ['troncon_route'])) {
      echo "  skipped\n";
      continue;
    }*/
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
  echo Yaml::dump($doc->asArray(), 9, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
}
