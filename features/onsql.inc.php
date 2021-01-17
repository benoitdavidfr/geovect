<?php
/*PhpDoc:
name: onsql.inc.php
title: onsql.inc.php - FeatureServer implémenté sur un serveur Sql (MySql ou PgSql)
classes:
doc: |
  Un FeatureServer correspond soit à une base MySql sur un serveur, soit à un schema d'une base PgSql sur un serveur.
  Une collection est une table qui doit avoir une clé primaire qui peut être créé si nécessaire.
  Les champs géométriques doivent être en CRS CRS:84.
  Si la table n'a pas de champ géométrique alors les feature seront créés avec une géométrie null.
  Si la table a plus d'un champ géométrique alors plusieurs collections seront définies avec chacun des champs.
  En PgSql les champs de type JSON sont traduits en JSON

  Un premier objectif serait d'utiliser un featureserver avec QGis
journal: |
  15-17/1/2021:
    - améliorations
    - reste
      - écrire /api
      - écrire le schéma ???
      - affiner le formattage
  30/12/2020:
    - création
includes: [ftrserver.inc.php, ../../phplib/sql.inc.php]
*/
require_once __DIR__.'/ftrserver.inc.php';
require_once __DIR__.'/../../phplib/sql.inc.php';

/*PhpDoc: classes
name: Collection
title: class Collection - Un objet Collection est initialisé par un collId et gère le mapping avec la table et ses colonnes
doc: |
  On ne gère pas le cas de collision entre le nom généré et l'existence de ce nom comme table
  La création de l'objet génère 2 requêtes Sql dans les cas stds et 3 dans les cas particuliers
*/
class Collection {
  protected string $schema;
  protected string $table_name;
  protected array $columns; // [[properties]]
  protected string $idColName; // nom de la colonne clé primaire
  protected string $geomColName; // nom de la colonne géométrique
  
  // liste des colonnes de la table $table_name
  static function listOfColumnsOfTable(string $schema, string $table_name): array { 
    $sql = [
      "select column_name, data_type, character_maximum_length, column_default, is_nullable",
      [
        'MySql'=> "\n",
        'PgSql'=> ", udt_catalog, udt_schema, udt_name\n",
      ],
        "from INFORMATION_SCHEMA.COLUMNS where table_schema='$schema' and table_name='$table_name'\n",
    ];
    return Sql::getTuples($sql);
  }
  
  static function isGeometryColumn(array $column): bool {
    return ($column['data_type']=='geometry') // MySql
      // PgSql
      || (($column['data_type']=='USER-DEFINED') && ($column['udt_schema']=='public') && ($column['udt_name']=='geometry'));
  }
  
  // $collId peut être soit le nom d'une table soit la concaténation du nom d'une table et du nom d'un de ses champs géom.
  function __construct(string $schema, string $collId) {
    $this->schema = $schema;
    //echo "listOfColumnsOfTable="; print_r(self::listOfColumnsOfTable($schema, $collId));
    if ($columns = self::listOfColumnsOfTable($schema, $collId)) { // cas normal 
      $this->table_name = $collId;
      $this->columns = $columns;
      foreach ($columns as $column) {
        if (self::isGeometryColumn($column))
          $this->geomColName = $column['column_name'];
      }
    }
    else { // cas où {collId} est la concaténation des noms de table et de la colonne géométrique
      $sql = "select table_name, column_name
        from INFORMATION_SCHEMA.COLUMNS
        where table_schema='$schema'
          and concat(table_name,'_',column_name)='$collId'";
      //echo "sql=$sql\n";
      $tuples = Sql::getTuples($sql);
      if (count($tuples) <> 1)
        throw new Exception("Erreur sur la détection de table_name pour collId='$collId'");
      $this->table_name = $tuples[0]['table_name'];
      $this->geomColName = $tuples[0]['column_name'];
      $this->columns = self::listOfColumnsOfTable($schema, $this->table_name);
      if (!$this->columns)
        throw new Exception("Erreur aucune colonne collId='$collId'");
    }
    
    $table_name = $this->table_name;
    $sql = [
      "select column_name, constraint_name from INFORMATION_SCHEMA.key_column_usage\n",
      "where table_schema='$schema' and table_name='$table_name'",
      [
        'MySql'=> " and constraint_name='PRIMARY'",
      ],
    ];
    $tuples = Sql::getTuples($sql);
    if (!$tuples)
      throw new Exception("erreur sur $schema.$collId et $schema.$table_name aucune clé définie");
    $this->idColName = $tuples[0]['column_name'];
    //"collection="; print_r($this);
  }
  
  function schema(): string { return $this->schema; }
  function table_name(): string { return $this->table_name; }
  function columns(): array { return $this->columns; }
  function idColName(): string { return $this->idColName; }
  function geomColName(): string { return $this->geomColName; }
};

class FeatureServerOnSql extends FeatureServer {
  protected string $path;
  //protected ?Collection $collection = null;
  
  function __construct(string $path) {
    $this->path = $path;
    //echo "path=$path\n";
    Sql::open($this->path);
  }
  
  function landingPage(): array { // retourne l'info de la landing page
    return ['path'=> $this->path];
  }

  function checkTables(): array { // liste les tables et leur éligibilité comme collection
    $schema = basename($this->path);
    //echo "schema=$schema\n";
    Sql::open($this->path);
    $sql = [
      "select t.table_name, g.column_name geom_column_name, pk.column_name pk_column_name\n",
      "from INFORMATION_SCHEMA.TABLES t\n",
      "  left join INFORMATION_SCHEMA.columns g\n",
      "    on g.table_schema=t.table_schema and g.table_name=t.table_name ",
      [
        'MySql'=> "and g.data_type='geometry'\n",
        'PgSql'=> "and g.data_type='USER-DEFINED' and g.udt_schema='public' and g.udt_name='geometry'\n",
      ],
      "  left join INFORMATION_SCHEMA.key_column_usage pk\n",
      "    on pk.table_schema=t.table_schema and pk.table_name=t.table_name\n",
      [
        'MySql'=> " and pk.constraint_name='PRIMARY'",
      ],
      "where t.table_schema='$schema'\n",
    ];
    echo "sql=",Sql::toString($sql),"\n";
    $tables = [];
    foreach (Sql::query($sql) as $tuple) {
      print_r($tuple);
      if (!isset($tables[$tuple['table_name']])) {
        $tables[$tuple['table_name']] = [
          'geomColumnNames'=> $tuple['geom_column_name'] ? [$tuple['geom_column_name']] : [],
          'pkColumnName'=> $tuple['pk_column_name'],
        ];
      }
      elseif ($tuple['geom_column_name']) {
        $tables[$tuple['table_name']]['geomColumnNames'][] = $tuple['geom_column_name'];
      }
    }
    print_r($tables);
    return $tables;
  }

  function repairTable(string $action, string $tableName): void {
    // permet de créer un id automatique
    if ($action == 'createPrimaryKey') {
      $sql = [
        [ 'MySql'=> "alter table $tableName add id int not null auto_increment primary key",
          'PgSql'=> "alter table $tableName add id serial primary key",
        ]
      ];
      Sql::query($sql); // génère une exception encas d'erreur
    }
  }

  function collections(): array { // retourne la liste des collections
    $schema = basename($this->path);
    // sélectionne les tables 
    $sql = [
      "select t.table_name, g.column_name geom_column_name, pk.column_name pk_column_name\n",
      "from INFORMATION_SCHEMA.TABLES t\n",
      "  left join INFORMATION_SCHEMA.columns g\n",
      "    on g.table_schema=t.table_schema and g.table_name=t.table_name ",
      [
        'MySql'=> "and g.data_type='geometry'\n",
        'PgSql'=> "and g.data_type='USER-DEFINED' and g.udt_schema='public' and g.udt_name='geometry'\n",
      ],
      "  left join INFORMATION_SCHEMA.key_column_usage pk\n",
      "    on pk.table_schema=t.table_schema and pk.table_name=t.table_name\n",
      [
        'MySql'=> " and pk.constraint_name='PRIMARY'",
      ],
      "where t.table_schema='$schema'\n",
    ];
    $sql = [
      "select t.table_name, g.column_name geom_column_name, pk.column_name pk_column_name\n",
      "from INFORMATION_SCHEMA.TABLES t\n",
      "  left join INFORMATION_SCHEMA.columns g\n",
      "    on g.table_schema=t.table_schema and g.table_name=t.table_name ",
      [
        'MySql'=> "and g.data_type='geometry'\n",
        'PgSql'=> "and g.data_type='USER-DEFINED' and g.udt_schema='public' and g.udt_name='geometry'\n",
      ],
      "  join INFORMATION_SCHEMA.key_column_usage pk\n",
      "    on pk.table_schema=t.table_schema and pk.table_name=t.table_name\n",
      "where t.table_schema='$schema' ",
      [
        'MySql'=> " and pk.constraint_name='PRIMARY'\n",
      ]
    ];
    echo "sql=",Sql::toString($sql),"\n";
    $tables = [];
    foreach (Sql::query($sql) as $tuple) {
      //print_r($tuple);
      $table_name = $tuple['TABLE_NAME'] ?? $tuple['table_name'] ?? null;
      $tables[$table_name][] = $tuple['geom_column_name'];
    }
    //print_r($tables);
    
    $colls = [];
    foreach ($tables as $table_name => $geomColumnNames) {
      if (count($geomColumnNames) <= 1)
        $colls[] = ['id'=> $table_name, 'title'=> $table_name];
      else {
        foreach ($geomColumnNames as $geomColumnName)
          $colls[] = ['id'=> $table_name.'_'.$geomColumnName, 'title'=> $table_name.'_'.$geomColumnName];
      }
    }
    return $colls;
  }
  
  function collection(string $id): array { // retourne la description du FeatureType de la collection
    return ['id'=> $id, 'title'=> $id];
  }
  
  function collDescribedBy(string $collId): array { // retourne la description du FeatureType de la collection
    return (new Collection(basename($this->path), $collId))->columns();
  }
  
  // ne fonctionne qu'en PgSql, je ne sais pas le faire en MySql
  function isJsonColumn(array $column): bool { return ($column['data_type']=='jsonb'); }
  
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
  
  // retourne les items de la collection comme array Php
  // L'usage de pFilter n'est pas implémenté
  function items(string $collId, array $bbox=[], array $pFilter=[], int $count=10, int $startindex=0): array {
    $jsonCols = [];
    $columns = [];
    $collection = new Collection(basename($this->path), $collId);
    foreach ($collection->columns() as $column) {
      if (Collection::isGeometryColumn($column)) {
        if ($column['column_name'] == $collection->geomColName())
          $columns[] = "ST_AsGeoJSON($column[column_name]) st_asgeojson";
      }
      elseif ($this->isJsonColumn($column)) {
        $columns[] = $column['column_name'];
        $jsonCols[] = $column['column_name'];
      }
      else
        $columns[] = $column['column_name'];
    }
    
    $sql = "select ".implode(',', $columns)."\nfrom ".$collection->table_name();
    if ($bbox) {
      self::checkBbox($bbox);
      $geomColName = $collection->geomColName();
      $where = "$geomColName && ST_MakeEnvelope($bbox[0], $bbox[1], $bbox[2], $bbox[3], 4326)\n";
    }
    if ($where)
      $sql .= "\nwhere $where";
    if ($count)
      $sql .= "limit $count";
    if ($startindex)
      $sql .= " offset $startindex";
    //echo "sql=$sql\n";
    $items = [];
    foreach (Sql::query($sql) as $tuple) {
      if (isset($tuple['st_asgeojson'])) {
        $geom = json_decode($tuple['st_asgeojson'], true);
        unset($tuple['st_asgeojson']);
      }
      else
        $geom = null;
      foreach ($jsonCols as $jsonCol)
        $tuple[$jsonCol] = json_decode($tuple[$jsonCol], true);
      $items[] = ['id'=> $tuple[$collection->idColName()], 'properties'=> $tuple, 'geometry'=> $geom];
    }
    return $items;
  }
    
  // retourne l'item $id de la collection comme array Php
  function item(string $collId, string $itemId): array {
    $jsonCols = [];
    $columns = [];
    $collection = new Collection(basename($this->path), $collId);
    foreach ($collection->columns() as $column) {
      if (Collection::isGeometryColumn($column)) {
        if ($column['column_name'] == $collection->geomColName())
          $columns[] = "ST_AsGeoJSON($column[column_name]) st_asgeojson";
      }
      elseif ($this->isJsonColumn($column)) {
        $columns[] = $column['column_name'];
        $jsonCols[] = $column['column_name'];
      }
      else
        $columns[] = $column['column_name'];
    }
    
    $sql = "select ".implode(',', $columns)."\nfrom ".$collection->table_name()
      ."\nwhere ".$collection->idColName()."='$itemId'";
    echo "sql=$sql\n";
    $tuples = Sql::getTuples($sql);
    if (!$tuples)
      throw new Exception("Erreur aucun item ne correspond à cet id", 404);
    $tuple = $tuples[0];
    if (isset($tuple['st_asgeojson'])) {
      $geom = json_decode($tuple['st_asgeojson'], true);
      unset($tuple['st_asgeojson']);
    }
    else
      $geom = null;
    foreach ($jsonCols as $jsonCol)
      $tuple[$jsonCol] = json_decode($tuple[$jsonCol], true);
    return ['id'=> $tuple[$collection->idColName()], 'properties'=> $tuple, 'geometry'=> $geom];
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
