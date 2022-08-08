<?php
/*PhpDoc:
name: displayjson.inc.php
title: displayjson.inc.php - affiche une valeur encodée en JSON, Yaml ou Html sans la construire en mémoire
functions:
doc: |
  Les fonctions json_encode() et Yaml::dump() nécessitent de construire en mémoire la valeur à afficher
  ce qui fait exploser la mémoire quand il y a trop d'objets.
  Pour éviter cette explosion, les fonctions display_json() et display_fmt() génèrent une sortie JSON/Yaml/Html sur stdout
  de manière similaire à json_encode() et Yaml::dump() mais sans avoir à construire en mémoire la totalité de la valeur
  à afficher.
  Cette valeur est construite en itérant un itérable (cad un objet dont la classe respecte l'interface Iterator)
  dont chaque valeur peut être ensuite filtrée par un filtre.
  De plus, la valeur à afficher est enveloppée dans une enveloppe dans laquelle le tableau issu de l'itération
  est repéré par un token identifié par 'iterable' et éventuellement le nbre d'iérations par le token 'nb_returned'.
  Enfin, display_json() peut générer une sortie gzippée si le paramètre fout vaut 'compress.zlib://'.

journal: |
  7/8/2022:
    - corrections suite à PhpStan level 6 et mise en PhpDocumentor
  6/2/2021:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;


/**
 * display_json() - affichage JSON d'un flux itérable sans en constituer une structure en mémoire
 *
 * @param array<mixed> $enveloppe // la valeur à afficher qui contient le(s) token(s)
 * @param array<string, string> $tokens // dictionnaire du (ou des) tokens
 * @param Iterator<int, array<mixed>> $iterable // objet itérable dont chaque itération sera incluse dans la sortie,
 * @param string $fout='' // si vaut 'compress.zlib://' alors le flux en sortie est gzippé, par défaut pas de zippage,
 * @param ?callable $filter=null // une éventuelle fonction de filtre de l'itération,
 * @param int $flags=0 // options éventuelles pour json_encode()
 * @return void
 */
function display_json(array $enveloppe, array $tokens, iterable $iterable, string $fout='', ?callable $filter=null, int $flags=0): void {
  if ($fout) {
    // utilisation d'un fichier temporaire dans lequel la sortie gzippée sera enregistrée
    $tmpfname = tempnam(__DIR__.'/tmp', 'tmpfile');
    $fout .= $tmpfname;
    $fout = fopen($fout, 'w');
  }
  
  $json = json_encode($enveloppe, $flags);
  $pos = strpos($json, $tokens['iterable']);
  if (!$fout)
    echo substr($json, 0, $pos-1),'[';
  else
    fwrite($fout, substr($json, 0, $pos-1).'[');
  $nb = 0;
  foreach ($iterable as $tuple) {
    $json = json_encode($filter ? $filter($tuple) : $tuple, $flags);
    if (!$fout)
      echo ($nb++ == 0 ? '':','),$json;
    else
      fwrite($fout, ($nb++ == 0 ? '':',').$json);
  }
  $json = substr($json, $pos+strlen($tokens['iterable'])+1);
  if (isset($tokens['nb_returned'])) // si le token nb_returned est spécifié
    $json = str_replace("\"$tokens[nb_returned]\"", (string)$nb, $json);
  if (!$fout) {
    echo "]$json\n";
  }
  else {
    fwrite($fout, "]$json\n");
    fclose($fout);
    readfile($tmpfname); // @phpstan-ignore-line // envoi sur la sortie du contenu du fichier temporaire
    unlink($tmpfname); // @phpstan-ignore-line // suppression du fichier temporaire
  }
}

/**
 * display_fmt(): void - Sortie en Yaml ou Html, en Html les URL sont remplacés par un lien
 *
 * @param string $fmt, // 'yaml' ou 'html'
 * @param array<mixed> $enveloppe // la valeur à afficher qui contient le(s) token(s)
 * @param array<string, string> $tokens // dictionnaire du (ou des) tokens
 * @param Iterator<int, array<mixed>> $iterable // objet itérable dont chaque itération sera incluse dans la sortie,
 * @param ?callable $filter=null // une éventuelle fonction de filtre de l'itération,
 * @return void
*/
function display_fmt(string $fmt, array $enveloppe, array $tokens, iterable $iterable, ?callable $filter=null): void {
  $yaml = Yaml::dump($enveloppe, 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  //echo "$yaml--\n";
  $lines = explode("\n", $yaml);
  //print_r($lines);
  $nb = 0;
  foreach ($lines as $line) {
    if (preg_match("!^( *)([^:]+): $tokens[iterable]!", $line, $matches)) {
      $spaces = $matches[1];
      $key = $matches[2];
      echo "$spaces$key:\n";
      foreach ($iterable as $item) {
        if ($filter)
          $item = $filter($item);
        echo "$spaces  - ",str_replace("\n", "\n$spaces    ", Yaml::dump($item, 1)),"\n";
        $nb++;
      }
    }
    elseif (isset($tokens['nb_returned']) && preg_match("!^( *)([^:]+): $tokens[nb_returned]!", $line, $matches)) {
      $spaces = $matches[1];
      $key = $matches[2];
      echo "$spaces$key: $nb\n";
    }
    elseif ($fmt == 'html')
      echo preg_replace("!(https?://[^' ]+)!", "<a href='$1'>$1</a>", $line),"\n";
    else
      echo "$line\n";
  }
}

if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__))) return;
// Test des 2 fonctions


// Test de display_json() pour afficher les routes de ne10m_cultural (56601)

require_once __DIR__.'/../../phplib/sql.inc.php';

echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>test-displayjson</title></head><body><pre>\n"; 
//header('Content-type: application/json; charset="utf8"');

Sql::open('pgsql://benoit@db207552-001.dbaas.ovh.net:35250/ne10m_cultural/public');

if (0) { // @phpstan-ignore-line // ss display_json() -> nb=10000, memory_get_peak_usage=113.6 MB
  $sql = "select *, ST_AsGeoJSON(geom) geom from roads limit 10000";
  $tuples = [];
  $nb = 0;
  foreach (Sql::query($sql, ['jsonColumns'=> ['geom']]) as $tuple) {
    $tuples[] = $tuple;
    printf("nb=%d, memory_get_peak_usage=%.1f MB\n", ++$nb, memory_get_peak_usage()/1024/1024);
  }
  die(json_encode([
    'links'=> ['link1','link2'],
    'items'=> $tuples,
    'nbreturned'=> count($tuples)
  ], JSON_PRETTY_PRINT));
}

// utilisation de display_json()
elseif (0) { // @phpstan-ignore-line // display_json -> nb=10000 -> 0,9 Mb stable
  display_json(
    enveloppe: ['links'=> ['link1','link2'], 'items'=> 'JSON_ITERABLE', 'nbreturned'=> 'NB_RETURNED'], // l'enveloppe
    tokens: ['iterable'=>'JSON_ITERABLE','nb_returned'=>'NB_RETURNED'], // les tokens
    iterable: Sql::query("select *, ST_AsGeoJSON(geom) geom from roads limit 10000"), // l'objet iterable
    //fout: 'compress.zlib://',
    filter: function(array $tuple): array {
      static $no = 0;
      $tuple['geom'] = json_decode($tuple['geom'], true);
      $tuple['memory_get_peak_usage'] = sprintf('%d -> %.1f MB', $no++, memory_get_peak_usage()/1024/1024);
      return $tuple;
    },
    flags: JSON_PRETTY_PRINT
  );
}

else { // display_fmt -> nb=10000 -> 1,2 Mb stable 
  display_fmt(
    fmt: 'html',
    enveloppe: ['links'=> ['link1','link2'], 'items'=> 'JSON_ITERABLE', 'nbreturned'=> 'NB_RETURNED'], // l'enveloppe
    tokens: ['iterable'=>'JSON_ITERABLE','nb_returned'=>'NB_RETURNED'], // les tokens
    iterable: Sql::query("select *, ST_AsGeoJSON(geom) geom from roads limit 10000"), // l'objet iterable
    filter: function(array $tuple): array {
      static $no = 0;
      $tuple['memory_get_peak_usage'] = sprintf('%d -> %.1f MB', $no++, memory_get_peak_usage()/1024/1024);
      return $tuple;
    }
  );
}
