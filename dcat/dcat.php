<?php
namespace dcat;
/*PhpDoc:
name: dcat.php
title: dcat.php - représentation en Php
doc: |
journal: |
  16/2/2021:
    - changement de nom
    - changement de structuration de cswCats() et cswCatTree()
  14/2/2021:
    - refonte
*/

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;


function jsonLdContext() { // Génère le contexte JSON-LD avec les espaces de nom 
  /*return [
    '@version'=> '1.1', // génère une erreur dans Fuseki
  ]
  + Yaml::parseFile(__DIR__.'/def.yaml')['namespaces'];*/
  return Yaml::parseFile(__DIR__.'/def.yaml')['namespaces'];
}

// stocke l'array transmis à la création et le rend à la demande
abstract class YamlObject implements \JsonSerializable {
  protected array $yaml=[];

  function __construct(array $yaml) { $this->yaml = $yaml; }

  function __get(string $name) { return $this->yaml[$name] ?? null; }

  function jsonSerialize() { return $this->yaml; }
};

class Agent extends YamlObject {
};

// dcat:Resource
abstract class Resource extends YamlObject {
  protected DCat $dcat;
  protected string $id;
  
  function __construct(DCat $dcat, string $id, array $yaml) {
    $this->dcat = $dcat;
    $this->id = $id;
    $this->yaml = $yaml;
  }
  
  abstract function uri(): string;
  //abstract function turtle(): string;
  
  function jsonld(): array { // la définition JSON-LD de la ressource et de son appartenance au catalogue racine
    $propHasPart = match(get_called_class()) {
      'dcat\\Dataset'=> 'dcat:dataset',
      'dcat\\Catalog'=> 'dcat:catalog',
      'dcat\\DataService'=> 'dcat:service',
    };
    return [
      '@context' => jsonLdContext(),
      '@id'=> 'https://geocat.fr/',
      '@type'=> 'dcat:Catalog',
      $propHasPart => [
        [
          '@id'=> $this->uri(),
          '@type'=> 'dcat:'.substr(get_called_class(), strlen(__NAMESPACE__)+1),
          'dct:title'=> [
            '@value'=> $this->{'title@fr'},
            '@language'=> 'fr',
          ],
        ],
      ],
    ];
  }
};

class Dataset extends Resource {
  function uri(): string {}
  //function turtle(): string {}
};

// un objet Catalog est un sous-catalogue d'un DCat
class Catalog extends Dataset {
  // liste des services d'accès comme [id => DataService]
  function accessService(): array {
    $accessServices = [];
    foreach ($this->distribution as $distrib) {
      //echo "distrib="; print_r($distrib);
      if (isset($distrib['accessService']) && isset($distrib['accessService']['$ref'])) {
        $ref = $distrib['accessService']['$ref'];
        //echo "  \$ref=$ref\n";
        if (substr($ref,0,10)=='#/service/') {
          $servId = substr($ref, 10);
          //echo "  servId=$servId\n";
          $service = $this->dcat->service[$servId];
          $accessServices[$servId] = $service;
        }
      }
    }
    return $accessServices;
  }

  function uri(): string { return "https://geocat.fr/catalog/$this->id"; }

  /*function turtle(): string {
    //print_r($this);
    $ttl = [];
    $def = file_get_contents(__DIR__.'/def.yaml');
    $def = Yaml::parse($def);
    foreach ($def['namespaces'] as $prefix => $uri) {
      $ttl[] = "@prefix $prefix: <$uri>";
    }
    $ttl[] = '';
    $uri = $this->uri();
    $ttl[] = "<$uri>";
    $ttl[] = "  a dcat:Catalog ;";
    $ttl[] = "  dct:title \""
      .$this->{'title@fr'}
      ."\"@fr .";
    return implode("\n", $ttl);
    {/* Turtle example
      @base <http://example.org/> .
      @prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
      @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
      @prefix foaf: <http://xmlns.com/foaf/0.1/> .
      @prefix rel: <http://www.perceive.net/schemas/relationship/> .

      <#green-goblin>
          rel:enemyOf <#spiderman> ;
          a foaf:Person ;    # in the context of the Marvel universe
          foaf:name "Green Goblin" .

      <#spiderman>
          rel:enemyOf <#green-goblin> ;
          a foaf:Person ;
          foaf:name "Spiderman" .

    *}
  }*/
};

class DataService extends Resource {
  function isCsw(): bool {
    //echo Yaml::dump(['isCsw?'=> json_decode(json_encode($this), true)]);
    return ($this->conformsTo == 'http://www.opengis.net/def/serviceType/ogc/csw');
  }

  function uri(): string {}
  function turtle(): string {}
};

// un DCat est un catalogue correspondant à un fichier
class DCat implements \JsonSerializable {
  protected string $path;
  protected array $yaml;
  protected array $agent=[]; // [id => Agent]
  protected array $catalog=[]; // [id => Catalog]
  protected array $dataset=[]; // [id => Dataset]
  protected array $service=[]; // [id => DataService]
  
  function __construct(string $path) {
    $this->path = $path;
    $this->yaml = Yaml::parseFile($path);
    foreach ($this->yaml['agent'] ?? [] as $id => $agent)
      $this->agent[$id] = new Agent($agent);
    unset($this->yaml['agent']);
    foreach ($this->yaml['catalog'] ?? [] as $id => $catalog)
      $this->catalog[$id] = new Catalog($this, $id, $catalog);
    unset($this->yaml['catalog']);
    foreach ($this->yaml['dataset'] ?? [] as $id => $dataset)
      $this->dataset[$id] = new Dataset($this, $id, $dataset);
    unset($this->yaml['dataset']);
    foreach ($this->yaml['service'] ?? [] as $id => $service)
      $this->service[$id] = new DataService($this, $id, $service);
    unset($this->yaml['service']);
  }
  
  function __get(string $name) { return $this->$name ?? $this->yaml[$name] ?? null; }
  
  function jsonSerialize(): array {
    return [
      'agent'=> $this->agent,
      'catalog'=> $this->catalog,
      'dataset'=> $this->dataset,
      'service'=> $this->service,
    ];
  }

  // retourne comme objets DCat les sous-catalogues DCat définis dans le même répertoire
  function subcats(): array {
    $subcats = [];
    foreach ($this->catalog as $catid => $catalog) {
      //print_r($catalog);
      //echo "$catid:\n  title@fr: ",$catalog->{'title@fr'},"\n";
      foreach ($catalog->accessService() as $accessService) {
        if (isset($accessService->endpointURL['$ref'])) {
          $endpointURL = $accessService->endpointURL['$ref'];
          $subcats[$catid] = new DCat(__DIR__.'/'.$endpointURL);;
        }
      }
    }
    return $subcats;
  }
  
  // retourne la liste des catalogues CSW définis dans le DCat comme [catid => ['cswAccessService'=> DataService]]
  function cswCats(): array {
    $cswCats = [];
    foreach ($this->catalog as $catid => $catalog) {
      //echo "\n$catid:\n  title@fr: ",$catalog->{'title@fr'},"\n";
      foreach ($catalog->accessService() as $accessService) {
        //echo Yaml::dump([$catid => json_decode(json_encode($accessService), true)]);
        if ($accessService->isCsw()) {
          $cswCats[$catid] = ['catalog'=> $catalog, 'cswAccessService'=> $accessService];
        }
      }
    }
    return $cswCats;
  }

  // retourne un array à 2 niveaux des catalogues CSW sous la forme [ pcatid => [ catid => DataService ] ]
  function cswCatTree(): array {
    $cswCatTree = [];
    
    foreach ($this->cswCats() as $catId => $service) {
      $cswCatTree[$catId] = $service;
    }
    foreach ($this->subcats() as $catId => $subcat) {
      $cswCatTree[$catId] = $subcat->cswCatTree();
    }
    return $cswCatTree;
  }
};


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__)))
  return new DCat(__DIR__.'/root.yaml');
// Test de la classe DCat


echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>dcat</title></head><body><pre>\n";
$dcat = new DCat(__DIR__.'/root.yaml');
echo Yaml::dump(json_decode(json_encode($dcat->cswCatTree()), true), 9, 2);
