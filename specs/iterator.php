<?php
// proto d'itérateur
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

// itère le body du forEach avec les variables dans $vars
function iterate(array $vars, array $body): array {
  $expanded = [];
  $varKeys = array_map(function(string $s): string { return '{'.$s.'}'; }, array_keys($vars));
  //echo '<pre>'; print_r($vars); print_r($varKeys); die;
  foreach ($body as $key => $value) {
    $key = str_replace($varKeys, array_values($vars), $key);
    if (is_string($value))
      $value = str_replace($varKeys, array_values($vars), $value);
    else
      $value = iterate($vars, $value);
    $expanded[$key] = $value;
  }
  return $expanded;
}

// recherche la clé forEach dans l'objet pour itérer son body avec la liste de variables
function iterator(array $array): array {
  $keys = array_keys($array);
  if ((count($keys)<>1) || ($keys[0]<>'forEach')) { // pas ForEach
    $expanded = [];
    foreach($array as $key => $value) {
      if (is_array($value))
        $expanded[$key] = iterator($value);
      else
        $expanded[$key] = $value;
    }
    return $expanded;
  }
  else { // ForEach détecté
    $expanded = [];
    foreach ($array['forEach']['iterables'] as $vars) {
      $expanded = array_merge($expanded, iterate($vars, $array['forEach']['body']));
    }
    return $expanded;
  }
}


if (basename(__FILE__)<>basename($_SERVER['PHP_SELF'])) return;


$array = Yaml::parseFile(__DIR__.'/adminexpress.yaml');
$expanded = iterator($array);
$expanded['title'] = "$expanded[title] (généré automatiquement par iterator.php le ".date('Y-m-d\TH:i').")";
echo '<pre>',Yaml::dump($expanded, 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
//file_put_contents(__DIR__.'/admgenerated.yaml', $yaml);
