<?php
/*PhpDoc:
title: spec.inc.php - interface d'accès aux specs
name: spec.inc.php
doc: |
  Le principe est d'accéder aux specs depuis l'extérieur de ce module
  au travers de new Spec($uri)
  puis des méthodes publiques sur les différentes classes.
journal: |
  25/2/2022:
    - ajout mise des specs en cache dans fichier pser
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/iterator.php';

use Symfony\Component\Yaml\Yaml;

class JsonRef { // Déréférencement d'une référence JSON ()
  static function deref(array $ref, string $cdirpath): array { // déréférence une référence JSON
    $filepath = self::filepath($ref, $cdirpath);
    $yaml = Yaml::parseFile($filepath);
    $ref = $ref['$ref'];
    //echo "ref=$ref<br>\n";
    $pos = strpos($ref, '#');
    $jsonPtr = explode('/', substr($ref, $pos+2));
    foreach ($jsonPtr as $elt) {
      if (!isset($yaml[$elt]))
        throw new Exception("Erreur dans JsonRef::deref() sur $elt");
      $yaml = $yaml[$elt];
    }
    return $yaml;
  }
  
  static function filepath(array $ref, string $cdirpath): string {
    $ref = $ref['$ref'];
    $pos = strpos($ref, '#');
    $filepath = substr($ref, 0, $pos);
    if (substr($filepath, 0, 2) == './')
      $filepath = $cdirpath.substr($filepath, 1);
    return $filepath;
  }
};

if (0) {
  echo '<pre>',Yaml::dump(JsonRef::deref(['$ref'=> './ne.yaml#/specifications/ne_110m_cultural']));
  die();
}

class Property { // Propriété d'une collection 
  protected string $id;
  protected string $title;
  protected string $description;
  protected string $type;
  protected array $enum;

  private function schemaJSON() { /* Schema JSON
    property:
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
          description: description plus détaillée de la propriété
          type: string
        comment:
          description: commentaire éditorial non diffusé avec le jeu de données
          type: string
        mandatory:
          description: Une valeur est-elle obligatoire pour cette propriété ? Par défaut yes
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
            - date
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
              items:
                description: signification de la valeur
                type: string
    */
  }

  function __construct(string $id, array $yaml) {
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
    + ($this->description ? ['description'=> $this->description] : [])
    + ($this->type ? ['type'=> $this->type] : [])
    + ($this->enum ? ['enum'=> $this->enum] : []);
  }

  function schema(): array { // construit le schéma JSON de la propriété
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

class Collection { // Collection dédinie dans une spécification 
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
  protected array $temporalExtent = [];
  protected array $properties=[];
    
  function __construct(string $id, array $yaml) {
    $this->id = $id;
    $this->title = $yaml['title'];
    if (isset($yaml['description']))
      $this->description = rtrim($yaml['description']); // Il y a souvent un \n à la fin de la chaine
    $this->geometryType = $yaml['geometryType'];
    $this->temporalExtent = $yaml['temporalExtent'] ?? [];
    foreach ($yaml['properties'] ?? [] as $propid => $property) {
      $this->properties[$propid] = new Property($propid, $property);
    }
  }
  
  function __toString(): string { return $this->title; }
  function title(): string { return $this->title; }
  function description(): ?string { return $this->description; }
  function temporalExtent(): array { return $this->temporalExtent; }
  
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

class Spec { // Spécification 
  const YAML_FILE = __DIR__.'/specs.yaml';

  protected string $uri;
  protected array $yaml;
  protected array $collections=[];  // [Collection]
  
  function schema() { /* Schema JSON
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
        issued:
          description: date de publication de la spécification
          type: string
        abstract:
          description: résumé du jeu de données
          type: string
        identifier:
          description: URI de référence de la spécification
          type: string
        source:
          description: Document source de la spécification, au cas où identifier n'est pas adapté
          type: string
        metadata:
          description: lien vers des MD génériques par ex. ISO 19139
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
  
  static function list(): array {
    $yaml = Yaml::parseFile(self::YAML_FILE);
    return array_keys($yaml['specifications']);
  }
  
  function __construct(string $uri) {
    $this->uri = $uri;
    $specid = basename($uri);
    $yaml = Yaml::parseFile(self::YAML_FILE);
    if (!($spec = $yaml['specifications'][$specid] ?? null))
      throw new Exception("Spécification '$uri' non définie");
    if (is_file(__DIR__."/$specid.pser")
      && (filemtime(__DIR__."/$specid.pser") > filemtime(JsonRef::filepath($spec, dirname(self::YAML_FILE))))) {
        $yaml = unserialize(file_get_contents(__DIR__."/$specid.pser"));
    }
    else {
      $yaml = JsonRef::deref($spec, dirname(self::YAML_FILE));
      $yaml = iterator($yaml); // Si les specs contiennent un mécanisme d'itération alors il est activé
      file_put_contents(__DIR__."/$specid.pser", serialize($yaml));
    }
    foreach ($yaml['collections'] ?? [] as $collId => $collection) {
      $this->collections[$collId] = new Collection($collId, $collection);
    }
    unset($yaml['collections']);
    $this->yaml = $yaml;
  }
  
  function __toString(): string { return $this->title; }
  function uri(): string { return $this->uri; }
  function title(): string { return $this->yaml['title']; }
  function abstract(): ?string { return $this->yaml['abstract'] ?? null; }
  function collections(): array { return $this->collections; }
  
  function asArray(): array {
    return 
      $this->yaml
      + ($this->collections ?
          ['collections'=> array_map(function(Collection $coll): array { return $coll->asArray(); }, $this->collections)]
          : []
        )
    ;
  }
};


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;

if (0) {
  $ne110m_physical = new Spec('https://specs.georef.eu/ne110m_physical');
  //echo '<pre>'; print_r($ne110m_physical); echo "</pre>\n";
  echo '<pre>',Yaml::dump($ne110m_physical->asArray(), 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
}
elseif (0) {
  new Spec('https://specs.georef.eu/ne110m_physical');
}
else {
  $ne110m_physical = new Spec('https://specs.georef.eu/ne110m_physical');
  echo '<pre>',Yaml::dump($ne110m_physical->asArray(), 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
  $coastline = $ne110m_physical->collections()['coastline'];
  echo '<pre>',Yaml::dump($coastline->asArray(), 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
  echo '<pre>',Yaml::dump($coastline->featureSchema(), 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
}