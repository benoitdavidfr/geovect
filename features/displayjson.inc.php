<?php
/*PhpDoc:
name: displayjson.inc.php
title: displayjson.inc.php - affiche une valeur encodée en JSON sans la construire en mémoire
doc: |
  La fonction json_encode() nécessite de construire en mémoire la valeur à afficher ce qui peut être bloquant.
  Pour éviter ce blocage, la fonction display_json() génère une sortie JSON sur stdout de manière similaire à json_encode()
  mais sans avoir à construire en mémoire la totalité de la valeur à afficher.
  Cette valeur est construite en itérant un itérable (cad un objet dont la classe respecte l'interface Iterator)
  dont chaque valeur peut être ensuite filtrée par un filtre.
  De plus, la valeur à afficher est enveloppée dans une enveloppe dans laquelle le tableau issu de l'itération
  est repéré par un token identifié par 'iterable' et éventuellement le nbre d'iérations par le token 'nb_returned'.
  Enfin, la sortie peut être gzippée si le paramètre fout vaut 'compress.zlib://'.
  
  Déclaration:
  function display_json(
    array $enveloppe, // la valeur à afficher qui contient le(s) token(s)
    array $tokens, // dictionnaire du (ou des) tokens
    $iterable, // objet itérable dont chaque itération sera incluse dans la sortie,
    string $fout='', // si vaut 'compress.zlib://' alors le flux en sortie est gzippé, par défaut pas de zippage,
    ?callable $filter=null, // une éventuelle fonction de filtre de l'itération,
    int $flags=0 // options éventuelles pour json_encode()
  ): void
journal: |
  6/2/2021:
    - création
*/
function display_json(array $enveloppe, array $tokens, $iterable, string $fout='', ?callable $filter=null, int $flags=0): void {
  if ($fout) {
    // utilisation d'un fichier temporaire dans lequel la sortie gzippée sera enregistrée
    $tmpfname = tempnam(__DIR__, 'tmpfile');
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


if ((__FILE__ <> realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) && (($argv[0] ?? '') <> basename(__FILE__))) return;
// Utilisation de la fonction display_json_encoditer


// Test de display_json() pour afficher 10.000 communes

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../phplib/sql.inc.php';
use Symfony\Component\Yaml\Yaml;

//echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>testseria</title></head><body><pre>\n"; 
header('Content-type: application/json; charset="utf8"');

if (0) { // ss display_json() -> nb=1432, memory_get_peak_usage=126.3 MB
  Sql::open('pgsql://docker@172.17.0.4/gis/public');
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
  Sql::open('pgsql://docker@172.17.0.4/gis/public');
  $sql = "select ogc_fid, id, nom_com, nom_com_m, statut, insee_can, insee_arr, insee_dep, insee_reg, code_epci, population,
      type, ST_AsGeoJSON(wkb_geometry) wkb_geometry
  from commune_carto
  limit 10000";
  $iterable = Sql::query($sql, ['jsonColumns'=> ['wkb_geometry']]);
}
else {
  Sql::open('mysql://bdavid@mysql-bdavid.alwaysdata.net/bdavid_route500_2020');
  $sql = "select id_rte500, insee_comm, nom_chf, statut, nom_comm, superficie, population, id_nd_rte, ST_AsGeoJSON(geom) geom
  from noeud_commune
  limit 10000";
  $iterable = Sql::query($sql, ['jsonColumns'=> ['geom']]);
}

display_json(
  enveloppe: ['links'=> ['link1','link2'], 'items'=> 'JSON_ITERABLE', 'nbreturned'=> 'NB_RETURNED'], // l'enveloppe
  tokens: ['iterable'=>'JSON_ITERABLE','nb_returned'=>'NB_RETURNED'], // les tokens
  iterable: $iterable, // l'objet iterable
  fout: 'compress.zlib://',
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
