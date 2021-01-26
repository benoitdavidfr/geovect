<?php
/*PhpDoc:
name: ftsonsql.inc.php
title: ftsonsql.inc.php - FeatureServer exposant des données d'un serveur Sql (MySql ou PgSql)
classes:
doc: |
  Définition de la classe FeatureServerOnSql qui implémente les opérations de FeatureServer sur des données stockées
  dans un serveur Sql (MySql ou PgSql),
  et de la classe complémentaire CollOnSql qui gère le mapping entre une table Sql et une collection du FeatureServer.

  A faire:
    - mettre en oeuvre la possibilité d'effectuer des requêtes sur un champ particulier
journal: |
  26/1/2021:
    - lors de la création d'une clé primaire, un nom spécifique, défini dans FeatureServerOnSql::IDPKEY_NAME,
      lui est donné et il est exclu à la fois du schéma et des champs fournis dans items et items/{id}
      Cette adaptation n'a ainsi pas d'impact sur la sémantique.
    - exclusion des vues et des tables sans clé primaire
  25/1/2021:
    - utilisation de \Sql\Schema au lieu des requêtes dans information_schema
  22-23/1/2021:
    - paramétrage de l'API avec la liste des collections et leur schéma
  20-21/1/2021:
    - ajout du schema JSON de chaque collection
  15-17/1/2021:
    - finalisation d'une première version utilisable dans QGis pour onsql
    - reste à:
      - écrire le schéma ??? optionnel skippé pour le moment
  30/12/2020:
    - création
includes: [ftrserver.inc.php, ../../phplib/sql.inc.php, ../../phplib/sqlschema.inc.php]
*/
require_once __DIR__.'/ftrserver.inc.php';
require_once __DIR__.'/../../phplib/sql.inc.php';
require_once __DIR__.'/../../phplib/sqlschema.inc.php';

use Symfony\Component\Yaml\Yaml;

/*PhpDoc: classes
name: CollOnSql
title: class CollOnSql - gestion du mapping entre une collection et la table de stockage correspondante
doc: |
  Un objet CollOnSql est initialisé par un \Sql\Schema et un collId.
  Ne gère pas le cas de collision entre le nom généré et un nom existant de table
*/
class CollOnSql {
  const SEP = '__'; // séparateur entre nom de table et nom de colonne pour créer le nom de collection
  protected \Sql\Table $table; // Table correspondant à la collection
  protected ?string $geomColName=null; // nom de la colonne géométrique
  protected array $columns; // [ \Sql\Column ]

  static function collNames(\Sql\Schema $sqlSchema): array { // retourne la liste des noms de collection
    $collNames = [];
    foreach ($sqlSchema->tables as $table_name => $sqlTable) {
      if (!$sqlTable->pkeyCol()) // on ne prend pas les tables qi ne comportent pas de clé primaire
        continue;
      $geomColumns = $sqlTable->listOfColumnOfType('geometry');
      if (count($geomColumns) <= 1) { // la table comporte au plus un champ géométrique
        $collNames[] = $table_name;
      }
      else { // la table comorte plus d'un champ géométrique
        foreach (array_keys($geomColumns) as $geomColumnName)
          $collNames[] = $table_name.self::SEP.$geomColumnName;
      }
    }
    return $collNames;
  }

  // $collId peut être soit le nom d'une table soit la concaténation du nom d'une table et du nom d'un de ses champs géom.
  function __construct(\Sql\Schema $schema, string $collId) {
    //echo "listOfColumnsOfTable="; print_r(SqlSchema::listOfColumnsOfTable($schema, $collId));
    if ($table = $schema->tables[$collId] ?? null) { // cas normal 
      $this->table = $table;
      $geomColumns = $this->table->listOfColumnOfType('geometry');
      if (count($geomColumns) == 1)
        $this->geomColName = array_keys($geomColumns)[0];
    }
    else { // cas où {collId} est la concaténation des noms de table et de la colonne géométrique
      $geomCol = $schema->concatTableGeomNames($collId, self::SEP); 
      $this->geomColName = $geomCol->name;
      $this->table = $geomCol->table;
    }
  }
  
  function __get(string $name) { return isset($this->$name) ? $this->$name : null; }
  function pkeyCol(): \Sql\Column { return $this->table->pkeyCol(); }
  function columns(): array { return $this->table->columns; } // [ {name}=> \Sql\Column]
};

/*PhpDoc: classes
name: FeatureServerOnSql
title: class FeatureServerOnSql - FeatureServer implémenté sur un serveur Sql (MySql ou PgSql)
doc: |
  Un FeatureServerOnSql expose les données soit d'une base MySql, soit d'un schema d'une base PgSql.
  Chaque table ayant une clé primaire définit une collection ou plusieurs si la table comporte plusieurs colonnes géométriques.
  Les vues ne sont pas prises en compte car elles n'ont pas de clé primaire.
  La table doit avoir une clé primaire qui peut être créée si nécessaire ; sinon une erreur sera générée.
  Si ce champ a pour nom FeatureServerOnSql::IDPKEY_NAME alors il n'apparait ni dans le schema, dans items ni dans items{id}
  Si un champ existe déjà avec ce nom alors cela génère une erreur.
  Les champs géométriques doivent être en CRS CRS:84.
  Si la table n'a pas de champ géométrique alors les features seront générés avec une géométrie nulle
  (cad type='GeometryCollection' et geometries=[]).
  Si la table a plus d'un champ géométrique alors une collection par champ géométrique est définie,
  avec pour nom la concaténation du nom de la table, du séparateur CollOnSql::SEP et du nom du champ géométrique.
  En PgSql les champs de type JSON sont traduits en JSON.

  Si les données sont documentées alors cette documentation est reprise:
    1) dans le schema JSON associé à une collection
    2) dans les schémas des réponses /items et /items/{id} exposés dans la définition de l'API
*/
class FeatureServerOnSql extends FeatureServer {
  // URI du schéma toolbox défini par l'OGC
  const OGC_SCHEMA_URI = 'http://schemas.opengis.net/ogcapi/features/part1/1.0/openapi/ogcapi-features-1.yaml';
  const IDPKEY_NAME = '_idpkey'; // nom du champ ajouté pour s'assurer de disposer d'une clé primaire
  //protected ?DatasetDoc $datasetDoc; // Doc éventuelle du jeu de données - déclaré dans la sur-classe
  protected string $path;
  protected \Sql\Schema $sqlSchema; // Le schema Sql de la base Sql dans laquelle sont stockées les données
  //protected ?CollOnSql $collection = null;
  
  function __construct(string $path, ?DatasetDoc $datasetDoc) {
    $this->path = $path;
    //echo "path=$path\n";
    $this->sqlSchema = new \Sql\Schema($path, ['table_types'=> ['BASE TABLE']]);
    //Sql::open($this->path); // déjà ouvert par le new \Sql\Schema()
    $this->datasetDoc = $datasetDoc;
  }
  
  private function geoJsonFeatureSchema(string $collName): array { // schema d'un Feature GeoJSON 
    $collDoc = $this->datasetDoc->collections[$collName] ?? null;
    $fSchema = $this->collDescribedBy($collName);
    if ($collDoc) {
      $fSchema['title'] = "Schema d'un Feature de \"$collDoc->title\" ($collName)";
      if ($collDoc->description)
        $fcSchema['description'] = $collDoc->description;
    }
    unset($fSchema['@id']);
    unset($fSchema['$schema']);
    $fschema['properties'] = array_merge($fSchema['properties'], [
      'links'=> [
        'type'=> 'array',
        'items'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/link' ],
      ],
      'timeStamp'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/timeStamp' ],
      'numberMatched'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/numberMatched' ],
      'numberReturned'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/numberReturned' ],
    ]);
    return $fschema;
    {/*Schema: ogcSchemaUri::#/components/schemas/featureGeoJSON
    featureGeoJSON:
      type: object
      required:
        - type
        - geometry
        - properties
      properties:
        type:
          type: string
          enum:
            - Feature
        geometry:
          $ref: "#/components/schemas/geometryGeoJSON"
        properties:
          type: object
          nullable: true
        id:
          oneOf:
            - type: string
            - type: integer
        links:
          type: array
          items:
            $ref: "#/components/schemas/link"
    */}
  }
  
  private function geoJsonFeatureCollectionSchema(string $collName): array { // schema d'une FeatureCollection GeoJSON 
    $fcSchema = [];
    $collDoc = $this->datasetDoc->collections[$collName] ?? null;
    if ($collDoc) {
      $fcSchema['title'] = "Schema d'une FeatureCollection de \"$collDoc->title\" ($collName)";
      if ($collDoc->description)
        $fcSchema['description'] = $collDoc->description;
    }
    $fSchema = $this->collDescribedBy($collName);
    unset($fSchema['@id']);
    unset($fSchema['$schema']);
    unset($fSchema['title']);
    if ($collDoc)
      $fSchema['description'] = "Schema d'un Feature de \"$collDoc->title\" ($collName)";
    return array_merge($fcSchema, [
      'type'=> 'object',
      'required'=> ['type','features'],
      'properties'=> [
        'type'=> [
          'type'=> 'string',
          'enum'=> ['FeatureCollection'],
        ],
        'features'=> [
          'type'=> 'array',
          'items'=> $fSchema,
        ],
        'links'=> [
          'type'=> 'array',
          'items'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/link' ],
        ],
        'timeStamp'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/timeStamp' ],
        'numberMatched'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/numberMatched' ],
        'numberReturned'=> [ '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/numberReturned' ],
      ],
    ]);
    {/*Schema: OGC_SCHEMA_URI.'#/components/schemas/featureCollectionGeoJSON'
      type: object
      required:
        - type
        - features
      properties:
        type:
          type: string
          enum:
            - FeatureCollection
        features:
          type: array
          items:
            $ref: "#/components/schemas/featureGeoJSON"
        links:
          type: array
          items:
            $ref: "#/components/schemas/link"
        timeStamp:
          $ref: "#/components/schemas/timeStamp"
        numberMatched:
          $ref: "#/components/schemas/numberMatched"
        numberReturned:
          $ref: "#/components/schemas/numberReturned"
    */}
  }
  
  function api(): array { // retourne la définition de l'API
    $urlLandingPage = ($_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http')
          ."://$_SERVER[HTTP_HOST]".dirname($_SERVER['REQUEST_URI']);
    $apidef = Yaml::parse(@file_get_contents(__DIR__.'/apidef.yaml'));
    $title = $this->datasetDoc->title ?? $this->path;
    $apidef['servers'][0] = [
      'description'=> "Service d'accès aux données \"$title\"",
      'url' => $urlLandingPage,
    ];
    $apidef['info']['title'] = "Accès aux données \"$title\" conformément à la norme API Features";
    if ($abstract = $this->datasetDoc->abstract ?? null)
      $apidef['info']['description'] = $abstract;
    else
      unset($apidef['info']['description']);
    
    $collIdPath = $apidef['paths']['/collections/{collectionId}'];
    unset($apidef['paths']['/collections/{collectionId}']);
    // supprime le paramètre {collectionId} qui doit être le premier paramètre dans apidef.yaml
    array_shift($collIdPath['get']['parameters']);
    
    $itemsPath = $apidef['paths']['/collections/{collectionId}/items'];
    unset($apidef['paths']['/collections/{collectionId}/items']);
    // supprime le paramètre {collectionId} qui doit être le premier paramètre dans apidef.yaml
    array_shift($itemsPath['get']['parameters']);

    $itemIdPath = $apidef['paths']['/collections/{collectionId}/items/{featureId}'];
    unset($apidef['paths']['/collections/{collectionId}/items/{featureId}']);
    // supprime le paramètre {collectionId} qui doit être le premier paramètre dans apidef.yaml
    array_shift($itemIdPath['get']['parameters']);

    array_pop($apidef['tags']); // supprime le tag collectionId
    foreach (collOnSql::collNames($this->sqlSchema) as $collName) {
      $collDoc = $this->datasetDoc->collections[$collName] ?? null;
      
      // paths: /collections/{collId}
      $collTitle = $collDoc->title ?? $collName;
      $get = ['summary' => "Get the metadata of the collection \"$collTitle\" ($collName)"];
      if (isset($collDoc->description))
        $get['description'] = "title: $collDoc->title\n"
          .$collDoc->description;
      elseif (isset($collDoc->title))
        $get['description'] = "title: $collDoc->title";
      // modifie la réponse pour 200
      $responses = $collIdPath['get']['responses'];
      {/* OGC_SCHEMA_URI.'#/components/responses/Collection'
        Collection:
          description: |-
            Information about the feature collection with id `collectionId`.

            The response contains a linkto the items in the collection
            (path `/collections/{collectionId}/items`,link relation `items`)
            as well as key information about the collection. This information
            includes:

            * A local identifier for the collection that is unique for the dataset;
            * A list of coordinate reference systems (CRS) in which geometries may be returned by the server.
              The first CRS is the default coordinate reference system (the default is always WGS 84
              with axis order longitude/latitude);
            * An optional title and description for the collection;
            * An optional extent that can be used to provide an indication of the spatial and temporal
              extent of the collection - typically derived from the data;
            * An optional indicator about the type of the items in the collection (the default value, if the indicator
              is not provided, is 'feature').
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/collection'
            text/html:
              schema:
                type: string
      */}
      $responses[200] = [
        'description' => "Information about the feature collection $collName.

The response contains a linkto the items in the collection (path `/collections/$collName/items`,link relation `items`)
as well as key information about the collection. This information includes:

* The local identifier '$collName' for the collection that is unique for the dataset;
* A list of coordinate reference systems (CRS) in which geometries may be returned by the server. The first CRS is the default coordinate reference system (the default is always WGS 84 with axis order longitude/latitude);
* An optional title and description for the collection;
* An optional extent that can be used to provide an indication of the spatial and temporal extent of the collection - typically derived from the data;
* An optional indicator about the type of the items in the collection (the default value, if the indicator is not provided, is         'feature').",
         'content'=> [
           'application/json'=> [
             'schema'=> ['$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/collection'],
           ],
           'text/html'=> ['schema'=> ['type'=> 'string']],
         ],
      ];
      $get = $get + [
        'operationId' => "getMetadataOf$collName",
        'parameters'=> $collIdPath['get']['parameters'],
        'responses'=> $responses,
        'tags'=> ["c_$collName"],
      ];
      $apidef['paths']["/collections/$collName"] = ['get'=> $get];

      // paths: /collections/{collId}/items
      // modifie les réponses 
      $responses = $itemsPath['get']['responses'];
      {/* OGC_SCHEMA_URI.'#/components/responses/Features' 
        Features:
          description: |-
            The response is a document consisting of features in the collection.
            The features included in the response are determined by the server
            based on the query parameters of the request. To support access to
            larger collections without overloading the client, the API supports
            paged access with links to the next page, if more features are selected
            that the page size.

            The `bbox` and `datetime` parameter can be used to select only a
            subset of the features in the collection (the features that are in the
            bounding box or time interval). The `bbox` parameter matches all features
            in the collection that are not associated with a location, too. The
            `datetime` parameter matches all features in the collection that are
            not associated with a time stamp or interval, too.

            The `limit` parameter may be used to control the subset of the
            selected features that should be returned in the response, the page size.
            Each page may include information about the number of selected and
            returned features (`numberMatched` and `numberReturned`) as well as
            links to support paging (link relation `next`).
          content:
            application/geo+json:
              schema:
                $ref: '#/components/schemas/featureCollectionGeoJSON'
              example:
                type: FeatureCollection
                links:
                  - href: 'http://data.example.com/collections/buildings/items.json'
                    rel: self
                    type: application/geo+json
                    title: this document
                  - href: 'http://data.example.com/collections/buildings/items.html'
                    rel: alternate
                    type: text/html
                    title: this document as HTML
                  - href: 'http://data.example.com/collections/buildings/items.json&offset=10&limit=2'
                    rel: next
                    type: application/geo+json
                    title: next page
                timeStamp: '2018-04-03T14:52:23Z'
                numberMatched: 123
                numberReturned: 2
                features:
                  - type: Feature
                    id: '123'
                    geometry:
                      type: Polygon
                      coordinates:
                        - ...
                    properties:
                      function: residential
                      floors: '2'
                      lastUpdate: '2015-08-01T12:34:56Z'
                  - type: Feature
                    id: '132'
                    geometry:
                      type: Polygon
                      coordinates:
                        - ...
                    properties:
                      function: public use
                      floors: '10'
                      lastUpdate: '2013-12-03T10:15:37Z'
            text/html:
              schema:
                type: string
      
      */}
      $responses[200] = [
        'description'=> "The response is a document consisting of features in the \"$collTitle\" collection.
The features included in the response are determined by the server
based on the query parameters of the request. To support access to
larger collections without overloading the client, the API supports
paged access with links to the next page, if more features are selected
that the page size.

The `bbox` and `datetime` parameter can be used to select only a
subset of the features in the collection (the features that are in the
bounding box or time interval). The `bbox` parameter matches all features
in the collection that are not associated with a location, too. The
`datetime` parameter matches all features in the collection that are
not associated with a time stamp or interval, too.

The `limit` parameter may be used to control the subset of the
selected features that should be returned in the response, the page size.
Each page may include information about the number of selected and
returned features (`numberMatched` and `numberReturned`) as well as
links to support paging (link relation `next`).",
        'content'=> [
          'application/geo+json'=> [
            'schema'=> $this->geoJsonFeatureCollectionSchema($collName),
          ],
          'text/html'=> [
            'schema'=> ['type'=> 'string'],
          ],
        ],
      ];
      $apidef['paths']["/collections/$collName/items"] = [
        'get'=> [
          'summary'=> "Get the items of the \"$collName\" Collection",
          'operationId' => "getItemsOf$collName",
          'parameters'=> $itemsPath['get']['parameters'],
          'responses'=> $responses,
          'tags'=> ["c_$collName"],
        ],
      ];

      // paths: /collections/{collId}/items/{featureId}
      // modifie les réponses 
      $responses = $itemIdPath['get']['responses'];
      {/* OGC_SCHEMA_URI.'#/components/responses/Feature'
      Feature:
        description: |-
          fetch the feature with id `featureId` in the feature collection
          with id `collectionId`
        content:
          application/geo+json:
            schema:
              $ref: '#/components/schemas/featureGeoJSON'
            example:
              type: Feature
              links:
                - href: 'http://data.example.com/id/building/123'
                  rel: canonical
                  title: canonical URI of the building
                - href: 'http://data.example.com/collections/buildings/items/123.json'
                  rel: self
                  type: application/geo+json
                  title: this document
                - href: 'http://data.example.com/collections/buildings/items/123.html'
                  rel: alternate
                  type: text/html
                  title: this document as HTML
                - href: 'http://data.example.com/collections/buildings'
                  rel: collection
                  type: application/geo+json
                  title: the collection document
              id: '123'
              geometry:
                type: Polygon
                coordinates:
                  - ...
              properties:
                function: residential
                floors: '2'
                lastUpdate: '2015-08-01T12:34:56Z'
          text/html:
            schema:
              type: string
      */}
      $responses[200] = [
        'description'=> "fetch the feature with id `featureId` in the feature collection \"$collTitle\"",
        'content'=> [
          'application/geo+json'=> [
            'schema'=> $this->geoJsonFeatureSchema($collName),
          ],
          'text/html'=> [
            'schema'=> ['type'=> 'string'],
          ],
        ],
      ];
      $apidef['paths']["/collections/$collName/items/{featureId}"] = [
        'get'=> [
          'summary'=> "Get the {featureId} item of the \"$collName\" Collection",
          'operationId' => "getItemOf$collName",
          'parameters'=> $itemIdPath['get']['parameters'],
          'responses'=> $responses,
          'tags'=> ["c_$collName"],
        ],
      ];
      $apidef['tags'][] = [
        'name'=> "c_$collName",
        'description'=> "operations on the $collName collection",
      ];
    }
    return $apidef;
  }
  
  function checkTables(): array { // liste les tables et leur éligibilité comme collection
    $tables = [];
    foreach ($this->sqlSchema->tables as $tname => $table) {
      $pkeyCol = $table->pkeyCol();
      $tables[$tname] = [
        'geomColumnNames'=> array_keys($table->listOfColumnOfType('geometry')),
        'pkColumnName'=> $pkeyCol ? $pkeyCol->name : null,
      ];
    }
    return $tables;
  }

  function repairTable(string $action, string $tableName): void {
    // permet de créer un id automatique
    if ($action == 'createPrimaryKey') {
      $idpkeyName = self::IDPKEY_NAME;
      $sql = [
        [ 'MySql'=> "alter table $tableName add $idpkeyName int not null auto_increment primary key",
          'PgSql'=> "alter table $tableName add $idpkeyName serial primary key",
        ]
      ];
      Sql::query($sql); // génère une exception encas d'erreur
    }
  }

  // structuration d'une collection pour les réponses à /collections et à /collection/{collId}
  static function collection_structuration(string $collUrl, string $collId, string $f): array {
    // Faut-il les 2 liens ? On pourrait avoir plus d'un lien uniquement pour self et alternate !
    // vérifier ce qui est dit dans la norme
    return [
      'id'=> $collId,
      'title'=> $collId,
      'itemType'=> 'feature', // indicator about the type of the items in the collection (the default value is 'feature').
      'crs'=> ['http://www.opengis.net/def/crs/OGC/1.3/CRS84'],
      'links'=> [
        [
          'href'=> "$collUrl/describedBy".(($f<>'json') ? '?f=json' : ''),
          'rel'=> 'items',
          'type'=> 'application/json',
          'title'=> "The JSON schema of the FeatureCollection in JSON",
        ],
        [
          'href'=> "$collUrl/describedBy".(($f<>'html') ? '?f=html' : ''),
          'rel'=> 'items',
          'type'=> 'text/html',
          'title'=> "The JSON schema of the FeatureCollection in Html",
        ],
        [
          'href'=> "$collUrl/items".(($f<>'json') ? '?f=json' : ''),
          'rel'=> 'items',
          'type'=> 'application/geo+json',
          'title'=> "The items in GeoJSON",
        ],
        [
          'href'=> "$collUrl/items".(($f<>'html') ? '?f=html' : ''),
          'rel'=> 'items',
          'type'=> 'text/html',
          'title'=> "The items in HTML",
        ],
      ],
    ];
    {/*schemas:/collection.yaml:
      type: object
      required:
        - id
        - links
      properties:
        id:
          description: identifier of the collection used, for example, in URIs
          type: string
        title:
          description: human readable title of the collection
          type: string
        description:
          description: a description of the features in the collection
          type: string
        links:
          type: array
          items:
            $ref: http://schemas.opengis.net/ogcapi/features/part1/1.0/openapi/schemas/link.yaml
        extent:
          description: >-
            The extent of the features in the collection. In the Core only spatial and temporal
            extents are specified. Extensions may add additional members to represent other
            extents, for example, thermal or pressure ranges.
          type: object
          properties:
            spatial:
              description: >-
                The spatial extent of the features in the collection.
              type: object
              properties:
                bbox:
                  description: >-
                    One or more bounding boxes that describe the spatial extent of the dataset.
                    In the Core only a single bounding box is supported. Extensions may support
                    additional areas. If multiple areas are provided, the union of the bounding
                    boxes describes the spatial extent.
                  type: array
                  minItems: 1
                  items:
                    description: >-
                      Each bounding box is provided as four or six numbers, depending on
                      whether the coordinate reference system includes a vertical axis
                      (height or depth):

                      * Lower left corner, coordinate axis 1
                      * Lower left corner, coordinate axis 2
                      * Minimum value, coordinate axis 3 (optional)
                      * Upper right corner, coordinate axis 1
                      * Upper right corner, coordinate axis 2
                      * Maximum value, coordinate axis 3 (optional)

                      The coordinate reference system of the values is WGS 84 longitude/latitude
                      (http://www.opengis.net/def/crs/OGC/1.3/CRS84) unless a different coordinate
                      reference system is specified in `crs`.

                      For WGS 84 longitude/latitude the values are in most cases the sequence of
                      minimum longitude, minimum latitude, maximum longitude and maximum latitude.
                      However, in cases where the box spans the antimeridian the first value
                      (west-most box edge) is larger than the third value (east-most box edge).

                      If the vertical axis is included, the third and the sixth number are
                      the bottom and the top of the 3-dimensional bounding box.

                      If a feature has multiple spatial geometry properties, it is the decision of the
                      server whether only a single spatial geometry property is used to determine
                      the extent or all relevant geometries.
                    type: array
                    minItems: 4
                    maxItems: 6
                    items:
                      type: number
                    example:
                      - -180
                      - -90
                      - 180
                      - 90
                crs:
                  description: >-
                    Coordinate reference system of the coordinates in the spatial extent
                    (property `bbox`). The default reference system is WGS 84 longitude/latitude.
                    In the Core this is the only supported coordinate reference system.
                    Extensions may support additional coordinate reference systems and add
                    additional enum values.
                  type: string
                  enum:
                    - 'http://www.opengis.net/def/crs/OGC/1.3/CRS84'
                  default: 'http://www.opengis.net/def/crs/OGC/1.3/CRS84'
            temporal:
              description: >-
                The temporal extent of the features in the collection.
              type: object
              properties:
                interval:
                  description: >-
                    One or more time intervals that describe the temporal extent of the dataset.
                    The value `null` is supported and indicates an open time intervall.
                    In the Core only a single time interval is supported. Extensions may support
                    multiple intervals. If multiple intervals are provided, the union of the
                    intervals describes the temporal extent.
                  type: array
                  minItems: 1
                  items:
                    description: >-
                      Begin and end times of the time interval. The timestamps
                      are in the coordinate reference system specified in `trs`. By default
                      this is the Gregorian calendar.
                    type: array
                    minItems: 2
                    maxItems: 2
                    items:
                      type: string
                      format: date-time
                      nullable: true
                    example:
                      - '2011-11-11T12:22:11Z'
                      - null
                trs:
                  description: >-
                    Coordinate reference system of the coordinates in the temporal extent
                    (property `interval`). The default reference system is the Gregorian calendar.
                    In the Core this is the only supported temporal reference system.
                    Extensions may support additional temporal reference systems and add
                    additional enum values.
                  type: string
                  enum:
                    - 'http://www.opengis.net/def/uom/ISO-8601/0/Gregorian'
                  default: 'http://www.opengis.net/def/uom/ISO-8601/0/Gregorian'
        itemType:
          description: indicator about the type of the items in the collection (the default value is 'feature').
          type: string
          default: feature
        crs:
          description: the list of coordinate reference systems supported by the service
          type: array
          items:
            type: string
          default:
            - http://www.opengis.net/def/crs/OGC/1.3/CRS84
    */}
  }
  
  function collections(string $f): array { // retourne la description des collections
    $selfurl = FeatureServer::selfUrl();
    $colls = [];
    foreach (CollOnSql::collNames($this->sqlSchema) as $collName) {
      $colls[] = self::collection_structuration("$selfurl/${collName}", $collName, $f);
    }
    return [
      'links'=> [
        [
          'f'=> $f,
          'href'=> $selfurl.(($f <> 'json') ? '?f=json' : ''),
          'rel'=> ($f=='json') ? 'self' : 'alternate',
          'type'=> 'application/json',
          'title'=> "this document in JSON",
        ],
        [
          'href'=> $selfurl.(($f <> 'html') ? '?f=html' : ''),
          'rel'=> ($f=='html') ? 'self' : 'alternate',
          'type'=> 'text/html',
          'title'=> "this document in HTML"
        ],
      ],
      'collections'=> $colls,
    ];
    {/*schemas:
      /collections.yaml:
        type: object
        required:
          - links
          - collections
        properties:
          links:
            type: array
            items:
              $ref: http://schemas.opengis.net/ogcapi/features/part1/1.0/openapi/schemas/link.yaml
          collections:
            type: array
            items:
              $ref: http://schemas.opengis.net/ogcapi/features/part1/1.0/openapi/schemas/collection.yaml
    */}
  }
  
  function collection(string $f, string $collId): array { // retourne la description du FeatureType de la collection
    $selfurl = FeatureServer::selfUrl();
    return self::collection_structuration($selfurl, $collId, $f);
  }
  
  function collDescribedBy(string $collId): array { // retourne le schema d'un Feature de la collection
    $collDoc = $this->datasetDoc->collections[$collId] ?? null; // doc de la collection
    $docFSchema = $collDoc ? $collDoc->featureSchema() : []; // schema issu de la doc
    //echo Yaml::dump(['$docFSchema'=> $docFSchema], 5, 2);
    $collOnSql = new CollOnSql($this->sqlSchema, $collId);
    $propertiesSchema = [];
    foreach ($collOnSql->columns() as $column) {
      if (($column->dataType <> 'geometry') && ($column->name <> self::IDPKEY_NAME)) {
        $prop = $docFSchema['properties']['properties']['properties'][$column->name] ?? ['type'=> 'string'];
        $propertiesSchema[$column->name] = $prop;
      }
    }
    $geomSchema = !$collOnSql->geomColName ? [
        'description'=> "no geometry coded as a GeometryCollection with 0 geometries",
        '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/geometrycollectionGeoJSON',
      ]
      : ($docFSchema['properties']['geometry'] ?? [
          'description'=> "geometry of unknown type",
          '$ref'=> self::OGC_SCHEMA_URI.'#/components/schemas/geometryGeoJSON',
        ]);
    $schema = ['@id'=> self::selfUrl()];
    $title = $docFSchema['title'] ?? $collId;
    $schema['title'] = "Schema JSON d'un Feature de la collection \"$title\"";
    if (isset($docFSchema['description']))
      $schema['description'] = $docFSchema['description'];
    return array_merge($schema,
      [
        '$schema'=> 'http://json-schema.org/schema#',
        'type'=> 'object',
        'required'=> ['id','properties','geometry'],
        'properties'=> [
          'type'=> ['type'=> 'string', 'enum'=> ['Feature']],
          'id'=> ['type'=> 'string'],
          'properties'=> [
            'type'=> 'object',
            'required'=> array_keys($propertiesSchema),
            'properties'=> $propertiesSchema,
          ],
          'geometry'=> $geomSchema,
        ],
      ]
    );
    {/* Schema 
      type: object
      required:
        - id
        - properties
        - geometry
      properties:
        id:
          type: string
        properties:
          type: object
          additionalProperties: false
          properties:
            id_rte500:
              description: 'Identifiant de l''objet'
              type: string
            nature:
              description: 'Nature de la limite administrative'
              type: string
              enum:
                - 'Limite côtière'
                - 'Frontière internationale'
                - 'Limite de région'
                - 'Limite de département'
                - 'Limite d''arrondissement'
                - 'Limite de commune'
            id:
              type: string
        geometry:
          type: object
          properties:
            type:
              type: string
              const: LineString
            coordinates:
              type: array
              items:
                type: array
                minItems: 2
                maxItems: 2
                items: { type: number }
    */}
  }
  
  static function checkBbox(array $bbox): void { // vérifie que la bbox est correcte, sinon lève une exception 
    if (count($bbox) <> 4)
      throw new Exception("Erreur sur bbox qui ne correspond pas à 4 coordonnées");
    if (!is_numeric($bbox[0]) || !is_numeric($bbox[1]) || !is_numeric($bbox[2]) || !is_numeric($bbox[3]))
      throw new Exception("Erreur sur bbox qui ne correspond pas à 4 coordonnées");
    if ($bbox[0] >= $bbox[2])
      throw new Exception("Erreur sur bbox bbox[0] >= bbox[2]");
    if ($bbox[1] >= $bbox[3])
      throw new Exception("Erreur sur bbox bbox[1] >= bbox[3]");
    if (($bbox[0] > 180) || ($bbox[0] < -180))
      throw new Exception("Erreur sur bbox[0] > 180 ou < -180");
    if (($bbox[2] > 180) || ($bbox[2] < -180))
      throw new Exception("Erreur sur bbox[2] > 180 ou < -180");
    if (($bbox[1] > 90) || ($bbox[1] < -90))
      throw new Exception("Erreur sur bbox[1] > 90 ou < -90");
    if (($bbox[3] > 90) || ($bbox[3] < -90))
      throw new Exception("Erreur sur bbox[3] > 90 ou < -90");
  }
  
  // retourne les items de la collection comme FeatureCollection en array Php
  // L'usage de pFilter n'est pas implémenté
  function items(string $collId, array $bbox=[], array $pFilter=[], int $limit=10, int $startindex=0): array {
    $jsonColNames = []; // liste des noms des colonnes de type JSON
    $columns = []; // liste des noms des colonnes pour la requête Sql
    $collection = new CollOnSql($this->sqlSchema, $collId);
    foreach ($collection->columns() as $column) {
      if ($column->dataType == 'geometry') {
        if ($column->name == $collection->geomColName)
          $columns[] = "ST_AsGeoJSON($column->name) st_asgeojson";
      }
      elseif ($column->dataType == 'jsonb') {
        $columns[] = $column->name;
        $jsonCols[] = $column->name;
      }
      else
        $columns[] = $column->name;
    }
    
    $sql = "select ".implode(',', $columns)."\nfrom ".$collection->table->name;
    $where = null;
    if ($bbox) {
      self::checkBbox($bbox);
      $geomColName = $collection->geomColName;
      //$where = "$geomColName && ST_MakeEnvelope($bbox[0], $bbox[1], $bbox[2], $bbox[3], 4326)\n"; // Plante sur MySQL
      $polygonWkt = "POLYGON(($bbox[0] $bbox[1],$bbox[0] $bbox[3],$bbox[2] $bbox[3],$bbox[2] $bbox[1],$bbox[0] $bbox[1]))";
      $where = "ST_Intersects($geomColName, ST_GeomFromText('$polygonWkt', 4326))\n";
    }
    if ($where)
      $sql .= "\nwhere $where";
    if ($limit)
      $sql .= "\nlimit $limit";
    if ($startindex)
      $sql .= " offset $startindex";
    //echo "sql=$sql\n";
    $items = [];
    foreach (Sql::query($sql) as $tuple) {
      if (isset($tuple['st_asgeojson'])) {
        $geom = json_decode($tuple['st_asgeojson'], true);
        unset($tuple['st_asgeojson']);
      }
      else {
        $geom = [
          'type'=> 'GeometryCollection',
          'geometries'=> [],
        ];
      }
      foreach ($jsonColNames as $jsonColName)
        $tuple[$jsonColName] = json_decode($tuple[$jsonColName], true);
      // La colonne IDPKEY_NAME n'est pas intégrée dans les données en sortie
      // La valeur correspondante est par contre éventuellement fournie comme id du feature
      // Cela permet de ne pas modifier la sémantique avec les éventuelles adaptations effectuées pour s'assurer
      // de disposer d'une clé primaire
      $id = $tuple[$collection->pkeyCol()->name];
      unset($tuple[self::IDPKEY_NAME]);
      $items[] = [
        'type'=> 'Feature',
        'id'=> $id,
        'properties'=> $tuple,
        'geometry'=> $geom,
      ];
    }
    $selfurl = FeatureServer::selfUrl()
        ."?startindex=$startindex&limit=$limit"
        .($bbox ? "&bbox=".implode(',', $bbox) : '')
        .($pFilter ? "&$filter=".implodeKeyVal(',', $pFilter) : '');
    $nexturl = FeatureServer::selfUrl()
        ."?startindex=".($startindex+$limit)."&limit=$limit"
        .($bbox ? "&bbox=".implode(',', $bbox) : '')
        .($pFilter ? "&$filter=".implodeKeyVal(',', $pFilter) : '');
    $links = [
      [ 'href'=> "$selfurl&f=json", 'rel'=> 'self', 'type'=> 'application/json', 'title'=> "this document in JSON" ],
      [ 'href'=> "$selfurl&f=html", 'rel'=> 'self', 'type'=> 'text/html', 'title'=> "this document in HTML" ],
    ];
    if (count($items) == $limit)
      $links[] = [ 'href'=> "$nexturl&f=json", 'rel'=> 'next', 'type'=> 'application/json', 'title'=> "next set of data" ];
    return [
      'type'=> 'FeatureCollection',
      'features'=> $items,
      'links'=> $links,
      'numberReturned'=> count($items),
    ];
    {/* Schema
    featureCollectionGeoJSON:
      type: object
      required:
        - type
        - features
      properties:
        type:
          type: string
          enum:
            - FeatureCollection
        features:
          type: array
          items:
            $ref: "#/components/schemas/featureGeoJSON"
        links:
          type: array
          items:
            $ref: "#/components/schemas/link"
        timeStamp:
          $ref: "#/components/schemas/timeStamp"
        numberMatched:
          $ref: "#/components/schemas/numberMatched"
        numberReturned:
          $ref: "#/components/schemas/numberReturned"
    */}
  }
  
  // retourne l'item $id de la collection comme Feature en array Php
  function item(string $collId, string $itemId): array {
    $jsonCols = [];
    $columns = [];
    $collection = new CollOnSql($this->sqlSchema, $collId);
    foreach ($collection->columns() as $column) {
      if ($column->dataType == 'geometry') {
        if ($column->name == $collection->geomColName)
          $columns[] = "ST_AsGeoJSON($column->name) st_asgeojson";
      }
      elseif ($column->dataType == 'jsonb') {
        $columns[] = $column->name;
        $jsonCols[] = $column->name;
      }
      else
        $columns[] = $column->name;
    }
    
    $sql = "select ".implode(',', $columns)."\nfrom ".$collection->table->name
      ."\nwhere ".$collection->pkeyCol()->name."='$itemId'";
    //echo "sql=$sql\n";
    $tuples = Sql::getTuples($sql);
    if (!$tuples)
      throw new Exception("Erreur aucun item ne correspond à cet id", 404);
    $tuple = $tuples[0];
    if (isset($tuple['st_asgeojson'])) {
      $geom = json_decode($tuple['st_asgeojson'], true);
      unset($tuple['st_asgeojson']);
    }
    else
      $geom = [
        'type'=> 'GeometryCollection',
        'geometries'=> [],
      ];
    foreach ($jsonCols as $jsonCol)
      $tuple[$jsonCol] = json_decode($tuple[$jsonCol], true);
    // La colonne IDPKEY_NAME n'est pas intégrée dans les données en sortie
    // La valeur correspondante est par contre éventuellement fournie comme id du feature
    // Cela permet de ne pas modifier la sémantique avec les éventuelles adaptations effectuées pour s'assurer
    // de disposer d'une clé primaire
    $id = $tuple[$collection->pkeyCol()->name];
    unset($tuple[self::IDPKEY_NAME]);
    return [
      'type'=> 'Feature',
      'id'=> $id,
      'properties'=> $tuple,
      'geometry'=> $geom,
    ];
  }
};


{/*
Tables de test - MySql
CREATE TABLE `bdavid_geovect`.`unchampstretunegeom` (
  `champstr` VARCHAR(80) NOT NULL ,
  `geom` GEOMETRY NOT NULL )
ENGINE = InnoDB
COMMENT = 'table de tests';

insert into unchampstretunegeom(champstr, geom) values
('une valeur pour le champ', ST_GeomFromText('POINT(1 1)'));

CREATE TABLE `bdavid_geovect`.`deuxchampstret2geom` (
  id int not null auto_increment primary key,
  `champstr` VARCHAR(80) NOT NULL ,
  `geom1` GEOMETRY NOT NULL,
  `geom2` GEOMETRY NOT NULL )
ENGINE = InnoDB
COMMENT = 'table de tests';

insert into deuxchampstret2geom(champstr,geom1,geom2) values
('une valeur pour le champ', ST_GeomFromText('POINT(1 1)'), ST_GeomFromText('POINT(1 1)'));

CREATE TABLE `bdavid_geovect`.`unchampjsonetunegeom` (
  `json` JSON NOT NULL ,
  `geom` GEOMETRY NOT NULL );

insert into unchampjsonetunegeom(json, geom) values
('{"a": "b"}', ST_GeomFromText('POINT(1 1)'));

CREATE TABLE `bdavid_geovect`.`unchampstretpasdegeom` (
  id int not null auto_increment primary key,
  `champstr` VARCHAR(80) NOT NULL);

insert into unchampstretpasdegeom(champstr) values
('une valeur pour le champ');

*/}
