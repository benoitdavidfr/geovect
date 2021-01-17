<?php
/*PhpDoc:
name: ftrserver.inc.php
title: ftrserver.inc.php - interface d'un serveur de Feature conforme au standard API Features
doc: |
  Est-ce une abstract class ou une interface ?
classes:
journal: |
  30/12/2020:
    - création
*/
/*PhpDoc: classes
name: FeatureServer
title: abstract class FeatureServer - interface d'un serveur de Feature conforme au standard API Features
methods:
doc: |
*/
abstract class FeatureServer {
  abstract function landingPage(): array; // retourne l'info de la landing page
  
  /*PhpDoc: methods
  name: collections
  title: "abstract function collections(): array - retourne la liste des collections"
  doc: |
    sous la forme: [
      'id'=> id,
      'title'=> title,
    ]
  */
  abstract function collections(): array;
  
  abstract function collection(string $collId): array;

  abstract function collDescribedBy(string $collId): array; // retourne la description du FeatureType de la collection
  
  // retourne les items de la collection comme array Php
  abstract function items(string $collId, array $bbox=[], array $pFilter=[], int $count=10, int $startindex=0): array;
  
  // retourne l'item $id de la collection comme array Php
  abstract function item(string $collId, string $featureId): array;
};
