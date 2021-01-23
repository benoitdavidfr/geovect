<?php
// gestion des définitions OAS

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../schema/jsonschema.inc.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// Correspond à une déclaration OAS 3.0
class Oas {
  const DOC_PATH_YAML = __DIR__.'/apidef.yaml'; // chemin du fichier stockant le doc en Yaml
  const SCHEMA_PATH_YAML = __DIR__.'/oas30.schema.yaml'; // chemin du fichier stockant le schema en Yaml
  
  
};

echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>oas</title></head><body><pre>\n";

// Test de conformité du schéma OAS 3.0
try {
  $check = JsonSchema::autoCheck(Oas::SCHEMA_PATH_YAML);
}
catch (ParseException $e) {
  echo Yaml::dump(['schema'=> $e->getMessage()]);
}
if (!$check->ok())
  echo Yaml::dump(['checkErrors'=> $check->errors()]);
else
  echo "Schema OAS conforme au méta-schéma JSON\n";


// Test de conformité d'une déclaration OAS
$schema = Yaml::parseFile(Oas::SCHEMA_PATH_YAML);
$schema = new JsonSchema($schema);
  
try {
  //$yaml = Yaml::parseFile(Oas::DOC_PATH_YAML);
  $path = 'http://localhost/geovect/features/fts.php/ignf-route500/api?f=yaml'; // ignf-route500 
  $text = file_get_contents($path);
  //printf("ord= X%x X%x X%x\n", ord($text[0]), ord($text[1]), ord($text[2]));
  //echo 'XX',$text,'YY',"\n";
  $yaml = Yaml::parse($text);
}
catch (ParseException $e) {
  echo Yaml::dump(['yamlParseError'=> $e->getMessage()]);
}
//print_r($doc);
$check = $schema->check($yaml);
if (!$check->ok())
  echo Yaml::dump(['checkErrors'=> $check->errors()]);
else
  echo "Doc conforme au schéma OAS\n";

