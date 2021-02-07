<?php
namespace dcat;
/*PhpDoc:
name: load.php
title: load.php - représentation en Php
doc: |
  Chargement d'un fichier Yaml représentant un catalogue et vérification des contraintes d'intégrité référentielles
*/

Revoir gestion d'accessService dont la structure a été changée'

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class Agent implements \JsonSerializable {
  protected array $yaml;
  
  function __construct(array $yaml) { $this->yaml = $yaml; }

  function jsonSerialize() { return $this->yaml; }
};

abstract class Resource implements \JsonSerializable {
  protected array $yaml;
  
  function __construct(array $yaml) { $this->yaml = $yaml; }
  
  function jsonSerialize() { return $this->yaml; }

  // Vérifie sur la classe les contraintes définies par $integrityReferences 
  function checkRefIntegrity(DCat $dcat, array $integrityReferences) {
    //echo '$integrityReferences='; print_r($integrityReferences);
    foreach ($integrityReferences as $propName => $key) {
      if (strpos($propName, '.')===false) {
        foreach ($this->yaml[$propName] as $externalId)
          $dcat->checkIsIdentifier($key, $externalId);
      }
      else {
        //echo "propName=$propName\n";
        $propNames = explode('.', $propName);
        foreach ($this->yaml[$propNames[0]] as $subObject) {
          if (isset($subObject[$propNames[1]])) {
            $dcat->checkIsIdentifier($key, $subObject[$propNames[1]]);
          }
        }
      }
    }
  }
};

class Dataset extends Resource {
  
};

class Catalog extends Dataset {
};

class DataService extends Resource {
  
};

class DCat implements \JsonSerializable {
  const PROPERTIES = [
    'Agent'=> 'agents',
    'Catalog'=> 'catalogs',
    'Dataset'=> 'datasets',
    'DataService'=> 'dataServices',
  ];
  protected array $yaml;
  protected array $agents=[]; // [Agent]
  protected array $catalogs=[]; // [Catalog]
  protected array $datasets=[]; // [Dataset]
  protected array $dataServices=[]; // [DataService]
  
  function __construct(string $path) {
    $this->yaml = Yaml::parseFile($path);
    foreach ($this->yaml['Agent'] as $yaml)
      $this->agents[$yaml['identifier']] = new Agent($yaml);
    foreach ($this->yaml['Catalog'] as $yaml)
      $this->catalogs[$yaml['identifier']] = new Catalog($yaml);
    foreach ($this->yaml['Dataset'] as $yaml)
      $this->datasets[$yaml['identifier']] = new Dataset($yaml);
    foreach ($this->yaml['DataService'] as $yaml)
      $this->dataServices[$yaml['identifier']] = new DataService($yaml);
  }
  
  function jsonSerialize(): array {
    return [
      'agents'=> $this->agents,
      'catalogs'=> $this->catalogs,
      'datasets'=> $this->datasets,
    ];
  }
  
  // Vérifie les contraintes d'intégrité référentielles définies dans le catalogue
  function checkRefIntegrity() {
    foreach ($this->yaml['integrityReferences'] as $className => $integrityReferences) {
      $prop = self::PROPERTIES[$className];
      foreach ($this->$prop as $object) {
        $object->checkRefIntegrity($this, $integrityReferences);
      }
    }
  }
  
  // Vérifie que id est un identifiant de $key définie par classe.propriété
  function checkIsIdentifier(string $key, string $id) {
    //echo "checkIsIdentifier($key, $id)\n";
    $keyParts = explode('.', $key);
    //print_r($keyParts);
    $prop = self::PROPERTIES[$keyParts[0]];
    if ($keyParts[1]=='identifier') {
      if (isset($this->$prop[$id]))
        echo "Ok for $id as $key\n";
      else
        echo "KO for $id as $key\n";
    }
    else
      throw new Exception("To be implemented");
  }
};

echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>dcat</title></head><body><pre>\n";
$dcat = new DCat(__DIR__.'/root2.yaml');
//echo json_encode($dcat, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
echo Yaml::dump(
    json_decode(json_encode($dcat, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), true),
    9, 2
  );
//print_r($dcat);
$dcat->checkRefIntegrity();
