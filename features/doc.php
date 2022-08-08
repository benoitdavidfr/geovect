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
  7/8/2022:
    - corrections suite à PhpStan level 6 et mise en PhpDocumentor
  22/2/2022:
    - transfert des classes specs vers ../specs
    - modif. de DatasetDoc
  18/2/2022:
    - extension pour ftsopg.php
  21/3/2021:
    - ajout temporalExtent dans CollectionDoc
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
require_once __DIR__.'/../specs/spec.inc.php';
require_once __DIR__.'/../lib/sexcept.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Michelf\MarkdownExtra;

ini_set('memory_limit', '10G');

/**
 * class DatasetDoc - Doc d'un Dataset
 */
class DatasetDoc {
  protected string $id;
  protected string $title;
  protected ?string $abstract;
  /** @var array<mixed> $licence */
  protected array $licence = [];
  protected string $path;
  protected ?Spec $conformsTo = null;
  
  function schema(): void { /* Schema JSON d'un dataset: 
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
  
  /** @param array<mixed> $yaml */
  function __construct(string $id, array $yaml) {
    $this->id = $id;
    $this->title = $yaml['title'];
    $this->abstract = $yaml['abstract'] ?? null;
    $this->licence = $yaml['licence'] ?? [];
    $this->path = $yaml['path'];
    if (isset($yaml['conformsTo']))
      $this->conformsTo = new Spec($yaml['conformsTo']);
  }
  
  function title(): string { return $this->title; }
  function path(): string { return $this->path; }
  
  function abstract(): ?string {
    if ($this->abstract)
      return $this->abstract;
    elseif ($this->conformsTo)
      return $this->conformsTo->abstract();
    else
      return null;
  }
  
  /** @return array<string, string> */
  function collections(): array {
    if ($this->conformsTo)
      return $this->conformsTo->collections();
    else
      return [];
  }
  
  /** @return array<string, mixed> */
  function asArray(): array {
    return [
      'title'=> $this->title,
      'path'=> $this->path
    ]
    + ($this->licence ? ['licence'=> $this->licence] : [])
    + ($this->conformsTo ? ['conformsTo'=> $this->conformsTo->uri()] : []);
  }
};

/**
 * class Doc - Doc globale 
 */
class Doc { // Doc globale 
  const ERROR_ON_DOC = 'Doc::ERROR_ON_DOC';
  const PATH = __DIR__.'/doc.'; // chemin des fichiers stockant la doc en pser ou en yaml, lui ajouter l'extension
  const PATH_PSER = self::PATH.'pser'; // chemin du fichier stockant la doc en pser
  const PATH_YAML = self::PATH.'yaml'; // chemin du fichier stockant la doc en Yaml
  const SCHEMA_PATH_YAML = __DIR__.'/doc.schema.yaml'; // chemin du fichier stockant le schema en Yaml

  /** @var array<int, DatasetDoc> */
  protected array $datasets; // [DatasetDoc]
  
  /** checkYamlConformity(array $yaml=[]): array - vérifie la conformité du document Yaml notamment à son schéma. En cas d'erreurs les retourne, sinon retourne []
   *
   * @param array<mixed> $yaml
   * @return array<string, mixed>
   */
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
  
  /** charge la doc définie par défaut stockée dans le fichier self::PATH_YAML
   * __construct(string|array $srce=self::PATH_YAML)
   * @param string|array<mixed> $srce
   */
  function __construct(string|array $srce=self::PATH_YAML) {
    if (is_string($srce)) {
      $yaml = Yaml::parseFile($srce);
      $dirpath = dirname($srce);
    }
    else {
      $yaml = $srce;
      $dirpath = __DIR__;
    }
    if ($errors = self::checkYamlConformity($yaml)) {
      print_r($errors);
      throw new SExcept("Erreur document Yaml non conforme", self::ERROR_ON_DOC);
    }
    foreach ($yaml['datasets'] ?? [] as $dsid => $dataset) {
      //echo "dataset=$dsid\n";
      //echo "  conformsTo=",$dataset['conformsTo'] ?? null,"\n";
      $this->datasets[$dsid] = new DatasetDoc($dsid, $dataset);
      //print_r($this->datasets[$dsid]);
    }
  }
  
  /** @return array<string, mixed> */
  function asArray(): array {
    $array = [];
    foreach ($this->datasets as $id => $ds) {
      $array['datasets'][$id] = $ds->asArray();
    }
    return $array;
  }
  
  function __get(string $name): mixed { return isset($this->$name) ? $this->$name : null; }
  
  function datasetByPath(string $path): ?DatasetDoc {
    foreach ($this->datasets as $dataset) {
      if ($dataset->path() == $path)
        return $dataset;
    }
    return null;
  }
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

if (!$a) {
  if (php_sapi_name() <> 'cli') {
    echo "doc.php - Actions proposées:<ul>\n";
    echo "<li><a href='?a=checkYaml'>Vérifie le Yaml</a></li>\n";
    echo "<li>Affiche la doc <a href='?a=display&amp;f=yaml'>en Yaml</a>, ";
    echo "<a href='?a=display&amp;f=geojson'>en JSON</a>, ";
    echo "<a href='?a=display&amp;f=html'>en Html</a></li>\n";
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
    foreach ($doc->datasets[$_GET['ds']]->collections() as $id => $coll) {
      echo "<a href='?a=schema&amp;ds=$_GET[ds]&amp;coll=$id'>$id</a><br>\n"; 
    }
  }
  else {
    $schema = $doc->datasets[$_GET['ds']]->collections()[$_GET['coll']]->featureSchema();
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

if ($a == 'display') {
  if ($f == 'html') { // affichage html
    if (php_sapi_name() <> 'cli')
      echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>doc</title></head><body><pre>\n";
    $doc = new Doc;
    //print_r($doc);
    echo preg_replace("!(https?://[^' ]+)!", "<a href='$1'>$1</a>",
      Yaml::dump($doc->asArray(), 9, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
  }

  if ($f == 'yaml') {
    if (php_sapi_name() <> 'cli')
      echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>doc</title></head><body><pre>\n";
    $doc = new Doc;
    echo Yaml::dump($doc->asArray(), 9, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  }
}
