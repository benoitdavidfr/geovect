<?php
/*PhpDoc:
title: affichage des specs
name: index.php
doc: |
  Accessible à l'URL https://specs.georef.eu/
  Affiche soit:
    - la liste des specs disponibles,
    - le contenu d'une spec particulière
    - la spec d'une collection
    - le schema JSON d'une collection
  Affiche soit en Html soit en JSON
*/
require_once __DIR__.'/spec.inc.php';

use Symfony\Component\Yaml\Yaml;

// Labels des erreurs Http
define('HTTP_ERROR_LABELS', [
  400 => 'Bad Request', // La syntaxe de la requête est erronée.
  404 => 'Not Found', // Ressource non trouvée. 
  500 => 'Internal Server Error', // Erreur interne du serveur. 
  501 => 'Not Implemented', // Fonctionnalité réclamée non supportée par le serveur.
]
);

//echo '<pre>$_SERVER='; print_r($_SERVER); echo "</pre>\n";

$baseUrl = 'https://specs.georef.eu';

// format de sortie demandé
$f = $_GET['f'] ?? (in_array('text/html', explode(',', getallheaders()['Accept'] ?? '')) ? 'html' : 'json');

// Génère une erreur Http avec le code $code et affiche le message d'erreur
function error(string $message, int $code, string $f): void {
  $exception = [
    'Exception'=> [
      'message'=> $message,
      'code'=> $code,
    ]
  ];
  header("HTTP/1.1 $code ".(HTTP_ERROR_LABELS[$code] ?? "Undefined httpCode $code"));
  if ($f == 'json') {
    header('Content-type: application/json; charset="utf8"');
    die(json_encode($exception));
  }
  else {
    header('Content-type: text/plain');
    die (Yaml::dump($exception));
  }
}

// génère la sortie en JSON ou en Yaml
function output(string $f, array $data, int $level=2): void {
  if ($f == 'json') {
    header('Content-type: application/json; charset="utf8"');
    die(json_encode($data));
  }
  else {
    echo '<pre>',Yaml::dump($data, $level, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
    die();
  }
}

function specSchema(): array { // fabrique le schéma JSON d'une spécification
  $schema = Yaml::parseFile(__DIR__.'/specs.schema.yaml');
  return [
    'title'=> "Schéma JSON d'une spécification",
    'abstract'=> "Ce schéma spécifie la spécification d'un jeu de données vecteur.\nIl s'inspire des formalismes du standard OGC API Features et du schema JSON.\n\nOn distingue les concepts de:\n  - jeu de données (dataset) (réf. dcat:Dataset ), ex: 'Route 500 éd. 2020', 'Route 500, éd. 2019'\n  - spécification d'un jeu de données (réf. dct:Standard), ex: 'Route 500 v3, 2020''\n\nUne spécification est généralement partagée entre plusieurs jeux de données.\nDe plus le cycle de vie d'une spécification est différent de celui d'un jeu de données.\n\nUne spécification décrit la structuration des données, notamment:\n  - la définition des collections, de leurs propriétés, des valeurs des types énumérés,\n  - la définition du type de géométrie de chaque collection,\n  - l'éventuelle définition des propriétés définissant l'extension temporelle.\nUne spécification peut être partielle.\n\nUne spécification est identifié par un URI de la forme:\n  https://specs.georef.eu/{idSpec} où {idSpec} est un identifiant de la spec.\nExemple:\n  https://specs.georef.eu/ignf-route500v3 pour les spécification V3 de ROUTE 500 d'IGN",
    '$id'=> 'https://specs.georef.eu/spec.schema',
    '$schema'=> 'http://json-schema.org/draft-06/schema#',
    'definitions'=> $schema['definitions'],
    '$ref'=> '#/definitions/specification',
  ];
}

if (in_array($_SERVER['PATH_INFO'] ?? null, [null, '/'])) { // page d'accueil
  $baseUrl2 = ($_SERVER['HTTP_HOST']=='localhost') ? "http://localhost$_SERVER[SCRIPT_NAME]" : $baseUrl;
  if ($f == 'json') {
    header('Content-type: application/json; charset="utf8"');
    die(json_encode([
      'specifications'=> array_map(
        function(string $id) use($baseUrl, $baseUrl2): array {
          $spec = new Spec("$baseUrl/$id");
          return [
            'uri'=> "$baseUrl2/$id",
            'title'=> $spec->title(),
          ];
        },
        Spec::list()
      ),
      'documentation'=> [
        'accessPoints'=> [
          $baseUrl => "page d'accueil, donne la liste des specs disponibles, une doc succinte"
            ." et le schema JSON d'une spécification",
          "$baseUrl/{specId}" => "affiche la spec. ayant {specId} comme id</li>",
          "$baseUrl/{specId}/{collId}" => "affiche la spec. de la collection {collId} de la spec. {specId}",
          "$baseUrl/{specId}/{collId}/schema" => "affiche le schéma JSON de la coll. {collId} de la spec. {specId}",
        ],
        'format'=> "La sortie peut être formattée en Html ou en JSON ; utiliser le paramètre f=html pour obtenir le HTML",
      ],
      'schema'=> specSchema(),
    ]));
  }
  else {
    echo "<h2>Liste des specifications définie</h2><ul>\n";
    foreach (Spec::list() as $id) {
      $spec = new Spec("$baseUrl/$id");
      echo "<li><a href='$baseUrl2/$id'>",$spec->title(),"</a></li>\n";
    }
    echo "</ul>\n";
    echo "<h2>Documentation</h2><ul>\n";
    echo "<li>$baseUrl - page d'accueil, donne la liste des specs disponibles, une doc succinte",
      " et le lien vers le schéma JSON d'une spécification</li>\n";
    echo "<li>$baseUrl/{specId} - affiche la spec. ayant {specId} comme id</li>\n";
    echo "<li>$baseUrl/{specId}/{collId} - affiche la spec. de la collection {collId} de la spec. {specId}</li>\n";
    echo "<li>$baseUrl/{specId}/{collId}/schema - affiche le schéma JSON de la coll. {collId} de la spec. {specId}</li>\n";
    echo "</ul>\n";
    echo "La sortie peut être formattée en Html ou en JSON ; utiliser le paramètre f=json pour obtenir le JSON<br>\n";
    echo "<h2>Schéma JSON des specifications</h2>\n";
    echo "Voir <a href='$baseUrl2/spec.schema'>$baseUrl/spec.schema</a>",
      " ou en JSON <a href='$baseUrl2/spec.schema?f=json'>$baseUrl/spec.schema?f=json</a>";
    die();
  }
}

elseif ($_SERVER['PATH_INFO'] == '/spec.schema') { // affichage du schéma JSON d'une spec
  if ($f == 'json') {
    header('Content-type: application/json; charset="utf8"');
    die(json_encode(specSchema()));
  }
  else {
    echo '<pre>',Yaml::dump(specSchema(), 9, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),"</pre>\n";
    die();
  }
}

elseif (!preg_match('!^/([^/]+)(/([^/]+)(/schema)?)?$!', $_SERVER['PATH_INFO'], $matches)) {
  error("Erreur, url incorrecte", 400, $f);
}

$specId = $matches[1];
$collId = $matches[3] ?? null;
$schema = $matches[4] ?? null;

try {
  $spec = new Spec("$baseUrl/$specId");
}
catch (Exception $e) {
  error("Erreur, specId \"$specId\" inconnue", 404, $f);
}

if (!$collId) { // /{specId}
  output($f, $spec->asArray(), 6);
}

if (!($coll = $spec->collections()[$collId] ?? null)) {
  error("Erreur, collection \"$collId\" inconnue", 404, $f);
}

if (!$schema) { // /{specId}/{collId}
  output($f, $coll->asArray(), 6);
}

else { // /{specId}/{collId}/schema
  output($f, $coll->featureSchema(), 6);
}
