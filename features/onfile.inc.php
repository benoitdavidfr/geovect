<?php
/*PhpDoc:
name: onfile.inc.php
title: onfile.inc.php - FeatureServer implémenté pour un répertoire de fichiers GeoJSON
functions:
doc: |
  Un serveur Feature correspond à un répertoire de fichiers dont les collections sont les fichiers geojson 
  Questions :
    - restreindre le chemin à un sous-répertoire data ou geojson de geovect ?
      Cela permettrait de réduire la longueur du chemin.
      http://features.geoapi.fr/file/ne_10m/collections/admin_0_countries/items
    - définir un id ? no de sequence dans le fichier ?
    - définir un format de stockage plus efficace ?
      - éviter de lire tt le fichier pour récupérer les 10 premiers features
      - accéder directement au feature id
    - définir plusieurs formats ?
      - un indexé sur un id ?
    - utiliser pser ? plus efficace que json ?
journal: |
  30/12/2020:
    - création
*/
require_once __DIR__.'/ftrserver.inc.php';

class FeatureServerOnFile extends FeatureServer {
  protected string $path; // chemin du répertoire
  
  function __construct(string $path) {
    $this->path = $path;
  }
  
  function collections(): array { // retourne la liste des collections
    $files = [];
    foreach (new DirectoryIterator(utf8_decode($this->path)) as $fileInfo) {
        if ($fileInfo->isDot()) continue;
        if (!preg_match('!\.geojson$!', $fileInfo->getFilename()))
          continue;
        $name = substr($fileInfo->getFilename(), 0, strlen($fileInfo->getFilename())-8);
        $files[] = ['id'=> $name, 'title'=> $name];
    }
    return $files;
  }
  
  function collection(string $id): array { // retourne la description du FeatureType de la collection
    return ['id'=> $id, 'title'=> $id];
  }
  
  function collDescribedBy(string $collId): array { // retourne la description du FeatureType de la collection
    return ['error'=> 'unknown FeatureType'];
  }
  
  // retourne les items de la collection comme array Php
  function items(string $collId, array $bbox=[], array $pFilter=[], int $count=10, int $startindex=0): array {
    $fc = json_decode(file_get_contents($this->path."/$collId.geojson"), true);
    $features = [];
    for ($i=0; $i < ($startindex+$count); $i++) {
      if ($i >= $startindex)
        $features[] = $fc['features'][$i];
    }
    return $features;
  }
  
  // retourne l'item $id de la collection comme array Php
  function item(string $collId, string $featureId): array {
    
  }
};