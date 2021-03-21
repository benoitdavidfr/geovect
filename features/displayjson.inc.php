<?php
/*PhpDoc:
name: displayjson.inc.php
title: displayfmt.inc.php - affiche une valeur encodée en JSON, Yaml ou Html sans la construire en mémoire
doc: |
  Les fonctions json_encode() et Yaml::dump() nécessitent de construire en mémoire la valeur à afficher ce qui peut être
  bloquant.
  Pour éviter ce blocage, les fonctions display_json() et display_fmt() génèrent une sortie JSON/Yaml/Html sur stdout
  de manière similaire à json_encode() et Yaml::dump() mais sans avoir à construire en mémoire la totalité de la valeur
  à afficher.
  Cette valeur est construite en itérant un itérable (cad un objet dont la classe respecte l'interface Iterator)
  dont chaque valeur peut être ensuite filtrée par un filtre.
  De plus, la valeur à afficher est enveloppée dans une enveloppe dans laquelle le tableau issu de l'itération
  est repéré par un token identifié par 'iterable' et éventuellement le nbre d'iérations par le token 'nb_returned'.
  Enfin, display_json() peut générer une sortie gzippée si le paramètre fout vaut 'compress.zlib://'.
  
  Déclarations:
    function display_json(
      array $enveloppe, // la valeur à afficher qui contient le(s) token(s)
      array $tokens, // dictionnaire du (ou des) tokens
      array $iterable, // objet itérable dont chaque itération sera incluse dans la sortie,
      string $fout='', // si vaut 'compress.zlib://' alors le flux en sortie est gzippé, par défaut pas de zippage,
      ?callable $filter=null, // une éventuelle fonction de filtre de l'itération,
      int $flags=0 // options éventuelles pour json_encode()
    ): void
    function display_fmt(
      string $fmt, // Yaml ou Html, en Html les URL sont remplacés par un lien
      array $enveloppe, // la valeur à afficher qui contient le(s) token(s)
      array $tokens, // dictionnaire du (ou des) tokens
      array $iterable, // objet itérable dont chaque itération sera incluse dans la sortie,
      ?callable $filter=null // une éventuelle fonction de filtre de l'itération,
    ): void

journal: |
  6/2/2021:
    - création
*/
require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

function display_json(array $enveloppe, array $tokens, Object $iterable, string $fout='', ?callable $filter=null, int $flags=0): void {
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
    if (!$fout)
      echo ($nb++ == 0 ? '':','),json_encode($filter ? $filter($tuple) : $tuple, $flags);
    else
      fwrite($fout, ($nb++ == 0 ? '':',').json_encode($filter ? $filter($tuple) : $tuple, $flags));
  }
  $json = substr($json, $pos+strlen($tokens['iterable'])+1);
  if (isset($tokens['nb_returned'])) // si le token nb_returned est spécifié
    $json = str_replace("\"$tokens[nb_returned]\"", $nb, $json);
  if (!$fout) {
    echo ']',$json,"\n";
  }
  else {
    fwrite($fout, "]$json\n");
    fclose($fout);
    readfile($tmpfname); // envoi sur la sortie du contenu du fichier temporaire
    unlink($tmpfname); // suppression du fichier temporaire
  }
}

function display_fmt(string $fmt, array $enveloppe, array $tokens, Object $iterable, ?callable $filter=null): void {
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


// Test de display_json() pour afficher 10.000 communes

require_once __DIR__.'/../../phplib/sql.inc.php';

echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>testseria</title></head><body><pre>\n"; 
//header('Content-type: application/json; charset="utf8"');

if (0) { // ss display_json() -> nb=1432, memory_get_peak_usage=126.3 MB
  Sql::open('pgsql://docker@pgsqlserver/gis/public');
  $sql = "select ogc_fid, id, nom_com, nom_com_m, statut, insee_can, insee_arr, insee_dep, insee_reg, code_epci, population,
      type, ST_AsGeoJSON(wkb_geometry) wkb_geometry
  from commune_carto
  limit 10000";
  $tuples = [];
  $nb = 0;
  foreach (Sql::query($sql, ['jsonColumns'=> ['wkb_geometry']]) as $tuple) {
    $tuples[] = $tuple;
    printf("nb=%d, memory_get_peak_usage=%.1f MB\n", ++$no, memory_get_peak_usage()/1024/1024);
  }
  die(json_encode([
    'links'=> ['link1','link2'],
    'items'=> $tuples,
    'nbreturned'=> count($tuples)
  ]));
}
elseif (1) { // utilisation de display_json()
  Sql::open('pgsql://docker@pgsqlserver/gis/public');
  $sql = "select ogc_fid, id, nom_com, nom_com_m, statut, insee_can, insee_arr, insee_dep, insee_reg, code_epci, population,
      type, ST_AsGeoJSON(wkb_geometry) wkb_geometry
  from commune_carto
  limit 3";
  $iterable = Sql::query($sql, ['jsonColumns'=> ['wkb_geometry']]);
}
else {
  Sql::open('mysql://bdavid@mysql-bdavid.alwaysdata.net/bdavid_route500_2020');
  $sql = "select id_rte500, insee_comm, nom_chf, statut, nom_comm, superficie, population, id_nd_rte, ST_AsGeoJSON(geom) geom
  from noeud_commune
  limit 3";
  $iterable = Sql::query($sql, ['jsonColumns'=> ['geom']]);
}

if (0) {
  display_json(
    enveloppe: ['links'=> ['link1','link2'], 'items'=> 'JSON_ITERABLE', 'nbreturned'=> 'NB_RETURNED'], // l'enveloppe
    tokens: ['iterable'=>'JSON_ITERABLE','nb_returned'=>'NB_RETURNED'], // les tokens
    iterable: $iterable, // l'objet iterable
    //fout: 'compress.zlib://',
    filter: function(array $tuple): array {
      static $no = 0;
      //static $tuples = [];
      //$tuples[] = $tuple;
      //$tuple['wkb_geometry'] = json_decode($tuple['wkb_geometry'], true);
      $tuple['memory_get_peak_usage'] = sprintf('%d -> %.1f MB', $no++, memory_get_peak_usage()/1024/1024);
      return $tuple;
    },
    flags: JSON_PRETTY_PRINT
  );
}
else {
  display_fmt(
    fmt: 'html',
    enveloppe: ['links'=> ['link1','link2'], 'items'=> 'JSON_ITERABLE', 'nbreturned'=> 'NB_RETURNED'], // l'enveloppe
    tokens: ['iterable'=>'JSON_ITERABLE','nb_returned'=>'NB_RETURNED'], // les tokens
    iterable: $iterable, // l'objet iterable
    filter: function(array $tuple): array {
      static $no = 0;
      //static $tuples = [];
      //$tuples[] = $tuple;
      //$tuple['wkb_geometry'] = json_decode($tuple['wkb_geometry'], true);
      $tuple['memory_get_peak_usage'] = sprintf('%d -> %.1f MB', $no++, memory_get_peak_usage()/1024/1024);
      return $tuple;
    }
  );
}
