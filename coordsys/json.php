<?php
/*PhpDoc:
name: json.php
title: json.php - API JSON d'export du registre des CRS et de calcul de chgt de coords
includes: [ full.inc.php ]
doc: |
  Sans paramètre: exporte le registre
  /schema - exporte le schéma JSON du registre
  /EPSG - exporte le sous-registre EPSG
  /EPSG:xxx - exporte la définition d'un code EPSG
  /EPSG:xxx/ddd,ddd/geo - calcule les coords. géo. des coord. projetées fournies
  /EPSG:xxx/ddd,ddd/proj - calcule les coords. projetées des coord. géo. fournies
  /EPSG:xxx/ddd,ddd/chg/EPSG:yyyy - calcule les coords dans le CRS yyyy
journal: |
  24/3/2019:
    - création
*/
require_once __DIR__.'/full.inc.php';

header('Content-type: application/json');
//echo json_encode($_SERVER);
if (!isset($_SERVER['PATH_INFO']))
  die(json_encode(CRS::registre(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");

if ($_SERVER['PATH_INFO'] == '/schema')
  die(json_encode(CRS::schema(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");

function datum(string $id): array {
  return array_merge(['id'=> $id], CRS::registre('DATUM', $id));
}

function spheroid(string $id): array {
  return array_merge(['id'=> $id], CRS::registre('ELLIPSOID', $id));
}

// sous-registre EPSG
if ($_SERVER['PATH_INFO'] == '/EPSG') {
  $result = [
    'title'=> "Sous-registre EPSG du registre des CRS",
    'epsg'=> [],
  ];
  foreach (CRS::registre('EPSG') as $epsg => $crsId) {
    $crsId2 = is_array($crsId) ? $crsId['latLon'] : $crsId;
    $crsRec = CRS::registre('CRS', $crsId2);
    if (isset($crsRec['BASEGEODCRS'])) {
      $crsRec['BASEGEODCRS'] = CRS::registre('CRS', $crsRec['BASEGEODCRS']);
      $crsRec['BASEGEODCRS']['DATUM'] = datum($crsRec['BASEGEODCRS']['DATUM']);
      $crsRec['BASEGEODCRS']['DATUM']['ELLIPSOID'] = spheroid($crsRec['BASEGEODCRS']['DATUM']['ELLIPSOID']);
    }
    else {
      $crsRec['DATUM'] = datum($crsRec['DATUM']);
      $crsRec['DATUM']['ELLIPSOID'] = spheroid($crsRec['DATUM']['ELLIPSOID']);
    }
    $result['epsg'][$epsg] = is_array($crsId) ? ['latLon'=> $crsRec] : $crsRec;
  }
  die(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
}

// un code EPSG
if (preg_match('!^/EPSG:(\d+)$!', $_SERVER['PATH_INFO'], $matches)) {
  $epsg = (int)$matches[1];
  $crs = CRS::registre('EPSG', $epsg);
  $crs = CRS::registre('CRS', $crs);
  if (isset($crs['BASEGEODCRS'])) {
    $crs['BASEGEODCRS'] = CRS::registre('CRS', $crs['BASEGEODCRS']);
    $crs['BASEGEODCRS']['DATUM'] = datum($crs['BASEGEODCRS']['DATUM']);
    $crs['BASEGEODCRS']['DATUM']['ELLIPSOID'] = spheroid($crs['BASEGEODCRS']['DATUM']['ELLIPSOID']);
  }
  else {
    $crs['DATUM'] = datum($crs['DATUM']);
    $crs['DATUM']['ELLIPSOID'] = spheroid($crs['DATUM']['ELLIPSOID']);
  }
  die(json_encode($crs, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
}

// proj() ou geo()
if (preg_match('!^/EPSG:(\d+)/([\d.,]+)/(proj|geo)$!', $_SERVER['PATH_INFO'], $matches)) {
  $epsg = (int)$matches[1];
  $pos = explode(',', $matches[2]);
  $op = $matches[3];
  $crs = CRS::EPSG($epsg);
  die(json_encode($crs->$op($pos), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
}

// chg()
if (preg_match('!^/EPSG:(\d+)/([\d.,]+)/chg/EPSG:([^/]+)$!', $_SERVER['PATH_INFO'], $matches)) {
  $crs1 = CRS::EPSG($matches[1]);
  $pos = explode(',', $matches[2]);
  $crs2 = CRS::EPSG($matches[3]);
  die(json_encode($crs1->chg($pos, $crs2), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
}

die(json_encode('ERREUR, paramètres non reconnus', JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
