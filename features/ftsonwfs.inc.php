<?php
/*PhpDoc:
name: ftsonwfs.inc.php
title: ftsonwfs.inc.php - simule un service API Features au dessus d'un serveur WFS
functions:
doc: |
  Définition de la classe FeatureServerOnWfs - interface Feature API d'un serveur WfsGeoJson
journal: |
  2/2/2021:
    - première version lisible dans QGis
  30/12/2020:
    - reprise de shomgt
includes: [ftrserver.inc.php]
*/
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/ftrserver.inc.php';
require_once __DIR__.'/wfsserver.inc.php';

use Symfony\Component\Yaml\Yaml;

class FeatureServerOnWfs extends FeatureServer { // simule un serveur API Features d'un serveur WFS
  protected WfsGeoJson $wfsServer;
  protected string $prefix; // chaine filtrant $fTypeId
  
  function __construct(string $serverUrl, ?DatasetDoc $datasetDoc) {
    $options = [];
    while (preg_match('![?&](referer|proxy|prefix)=([^&]+)!', $serverUrl, $matches)) {
      $options[$matches[1]] = $matches[2];
      $serverUrl = preg_replace('![?&](referer|proxy|prefix)=([^&]+)!', '', $serverUrl, 1);
    }
    //print_r($options);
    //echo "url=$serverUrl\n";
    $this->prefix = $options['prefix'] ?? '';
    unset($options['prefix']);
    $this->wfsServer = new WfsGeoJson($serverUrl, $options);
    $this->datasetDoc = $datasetDoc;
  }
  
  // structuration d'une collection utilisée pour les réponses à /collections et à /collections/{collId}
  private function collection_structuration(string $collUrl, string $collId, string $f): array {
    //echo get_class($this),"::collection_structuration($collUrl, $collId, $f)<br>\n";
    $collDoc = $this->datasetDoc->collections()[$collId] ?? null; // doc de la collection
    //echo 'collDoc='; if ($collDoc) print_r($collDoc); else echo "null\n";
    //$spatialExtentBboxes = (new CollOnSql($this->sqlSchema, $collId))->spatialExtentBboxes();
    $spatialExtentBboxes = $this->wfsServer->featureTypeList()[$this->prefix.$collId]['LonLatBoundingBox'];
    $temporalExtent = null;
    return [
      'id'=> $collId,
      'title'=> $collDoc ? $collDoc->title() : $collId,
    ]
    + ($collDoc && $collDoc->description() ? ['description'=> $collDoc->description()] : [])
    + ($spatialExtentBboxes || $temporalExtent ?
      ['extent'=>
        ($spatialExtentBboxes ? ['spatial'=> ['bbox'=> $spatialExtentBboxes]] : [])
      + ($temporalExtent ? ['temporal'=> $temporalExtent] : [])
      ]
      : []
    )
    + [
      'itemType'=> 'feature', // indicator about the type of the items in the collection (the default value is 'feature').
      'crs'=> ['http://www.opengis.net/def/crs/OGC/1.3/CRS84'],
      'links'=> [
        [
          'href'=> $collUrl.(($f<>'json') ? '?f=json' : ''),
          'rel'=> $f=='json' ? 'self' : 'alternate',
          'type'=> 'application/json',
          'title'=> "This document in JSON",
        ],
        [
          'href'=> $collUrl.(($f<>'html') ? '?f=html' : ''),
          'rel'=> $f=='html' ? 'self' : 'alternate',
          'type'=> 'text/html',
          'title'=> "This document in Html",
        ],
        [
          'href'=> "$collUrl/describedBy".(($f<>'json') ? '?f=json' : ''),
          'rel'=> 'describedBy',
          'type'=> 'application/json',
          'title'=> "The JSON schema of a Feature of the FeatureCollection in JSON",
        ],
        [
          'href'=> "$collUrl/describedBy".(($f<>'html') ? '?f=html' : ''),
          'rel'=> 'describedBy',
          'type'=> 'text/html',
          'title'=> "The JSON schema of a Feature of the FeatureCollection in Html",
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
    //echo get_class($this),"::collections()<br>\n";
    $selfurl = self::selfUrl();
    $colls = [];
    foreach ($this->wfsServer->featureTypeList() as $fTypeId => $fType) {
      if (!$this->prefix || (substr($fTypeId, 0, strlen($this->prefix)) == $this->prefix)) {
        if ($this->prefix)
          $fTypeId = substr($fTypeId, strlen($this->prefix));
        $colls[] = $this->collection_structuration("$selfurl/$fTypeId", $fTypeId, $f);
      }
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
  
  function collection(string $f, string $id): array { // retourne la description de la collection
    return $this->collection_structuration(self::selfUrl(), $id, $f);
  }
  
  function collDescribedBy(string $collId): array { // retourne la description du FeatureType de la collection
    return $this->wfsServer->describeFeatureType($this->prefix.$collId);
  }
  
  // retourne les items de la collection comme array Php
  function items(string $f, string $collId, array $bbox=[], int $limit=10, int $startindex=0): array {
    //echo "FeatureServerOnWfs::items()\n";
    if ($bbox)
      self::checkBbox($bbox);
    $properties = isset($_GET['properties']) ? explode(',', $_GET['properties']) : null; // liste des prop. à retourner
    $filters = []; // filtres sous la forme [{columnName} => {value}]
    $items = $this->wfsServer->getFeatureAsArray(
      typename: $this->prefix.$collId,
      bbox: $bbox,
      count: $limit,
      startindex: $startindex
    );
    $selfurl = self::selfUrl()."?limit=$limit"
        .($bbox ? "&bbox=".implode(',', $bbox) : '')
        .($properties ? "&properties=".implode(',', $properties) : '');
    foreach ($filters as $key => $val)
      $selfurl .= "&$key=".urlencode($val);
    $nexturl = $selfurl."&startindex=".($startindex+$limit);
    $selfurl .= "&startindex=$startindex";
    $links = [
      [
        'href'=> $selfurl.($f<>'json' ? '&f=json' : ''),
        'rel'=> ($f=='json') ? 'self' : 'alternate',
        'type'=> 'application/geo+json',
        'title'=> "this document in GeoJSON",
      ],
      [
        'href'=> $selfurl.($f<>'html' ? '&f=html' : ''),
        'rel'=> ($f=='html') ? 'self' : 'alternate',
        'type'=> 'text/html',
        'title'=> "this document in HTML",
      ],
      [
        'href'=> $selfurl.($f<>'yaml' ? '&f=yaml' : ''),
        'rel'=> ($f=='yaml') ? 'self' : 'alternate',
        'type'=> 'application/x-yaml',
        'title'=> "this document in Yaml",
      ],
    ];
    if (count($items['features']) == $limit) {
      $links[] = [
        'href'=> $nexturl.($f<>'json' ? '&f=json' : ''),
        'rel'=> 'next',
        'type'=> 'application/geo+json',
        'title'=> "next set of data in GeoJSON",
      ];
      $links[] = [
        'href'=> $nexturl.($f<>'html' ? '&f=html' : ''),
        'rel'=> 'next',
        'type'=> 'text/html',
        'title'=> "next set of data in Html",
      ];
      $links[] = [
        'href'=> $nexturl.($f<>'yaml' ? '&f=yaml' : ''),
        'rel'=> 'next',
        'type'=> 'application/x-yaml',
        'title'=> "next set of data in Yaml",
      ];
    }
    
    return [
      'type'=> 'FeatureCollection',
      'features'=> $items['features'],
      'links'=> $links,
      'timeStamp'=> date(DATE_ATOM),
      'numberMatched'=> $items['numberMatched'],
      'numberReturned'=> count($items['features']),
    ];
  }
  
  // retourne l'item $id de la collection comme array Php
  function item(string $f, string $collId, string $featureId): array {
    $item = $this->wfsServer->getFeatureById($this->prefix.$collId, $featureId);
    $item = json_decode($item, true);
    $item = $item['features'][0];
    return [
      'type'=> 'Feature',
      'id'=> $featureId,
      'properties'=> $item['properties'],
      'geometry'=> $item['geometry'],
      'links'=> [
        [
          'href'=> self::selfUrl().($f<>'json' ? '?f=json' : ''),
          'rel'=> ($f=='json') ? 'self' : 'alternate',
          'type'=> 'application/geo+json',
          'title'=> "this document in GeoJSON",
        ],
        [
          'href'=> self::selfUrl().($f<>'html' ? '?f=html' : ''),
          'rel'=> ($f=='html') ? 'self' : 'alternate',
          'type'=> 'text/html',
          'title'=> "this document in HTML",
        ],
        [
          'href'=> self::selfUrl().($f<>'yaml' ? '?f=yaml' : ''),
          'rel'=> ($f=='yaml') ? 'self' : 'alternate',
          'type'=> 'application/x-yaml',
          'title'=> "this document in Yaml",
        ],
        [
          'href'=> dirname(self::selfUrl(), 2),
          'rel'=> 'collection',
          'type'=> 'application/json',
          'title'=> "definition of the collection in JSON",
        ],
      ],
      'timeStamp'=> date(DATE_ATOM),
    ];
  }
};
