<?php
/*PhpDoc:
name: full.inc.php
title: full.inc.php - package de conversion de coordonnées y compris entre syst. géodésiques différents
doc: |
  Ce package cherche à être facilement utilisable tout en étant extensible et simple
  Les concepts et les codes sont alignés dans la mesure du possible sur ceux du std OGC CRS WKT (ISO 19162:2015)
  (http://docs.opengeospatial.org/is/12-063r5/12-063r5.html)

  Il s'appuie sur un registre stocké dans le fichier crsregistre.yaml et défini par le schema crsregistre.schema.yaml

  Dans un sousi de simplicité, tous les CRS géodésiques (GeodeticCRS) sont définis en 2D avec des axes en longitude,
  latitude en degrés décimaux. Dans le registre EPSG, ils sont souvent définis en latitude, longitude.
  La correspondance vers le code EPSG est indiquée même si les axes sont définis différemment.
  Dans la définition des codes EPSG, le registre précise si le CRS est défini en latitude, longitude ou en longitude, latitude.

  Une des difficultés est l'identification des CRS.
  Dans ce package, on utilise 3 registres différents pour cela:
    - le registre de codes EPSG en utilisant la méthode CRS::EPSG({code}), ex: CRS::EPSG(2154)
    - le registre de codes IGN-F en utilisant la méthode CRS::IGNF({code}), ex: CRS::IGNF('LAMB93')
    - le registre de codes simples pour les CRS les plus fréquents en utilisant la méthode CRS::S({code}),
      ex: CRS::S('WebMercator')
  
  Une 4ème possibilité pour créer un CRS est d'indiquer un GeodeticCRS, une projection et d'éventuels paramètres,
  exemples:
    - CRS::S('WGS84LonLat', 'Spheric_Mercator_1SP'); // Mercator Sphérique défini sur WGS84
    - CRS::S('WGS84LonLat', 'UTM', '32N'); // UTM-32N sur WGS84
  
  Sur un CRS, 5 méthodes sont proposées:
    - proj(array $lonlatdeg): array; // calcule les coord. en projection à partir des coord. géo. (lon,lat) en dégrés décimaux
    - geo(array $xy): array; // inverse de la méthode précédente
    - wgs84LonLatDd(array $pos): array; // transforme des coord. $pos définies dans le CRS courant en coord. (lon,lat) WGS84
    - fromWgs84LonLatDd(array $wgs84LonLatDd): array; // inverse de la méthode précédente
    - limits(): array; // retourne le domaine de définition du CRS défini par les coord. min. et max. en (lon,lat) WGS84

  Pour utiliser ce package inclure ce fichier full.inc.php
  
  Terminologie:
   - registry = logiciel de gestion des registres
   - register = un registre particulier
  
  A faire:
    - faire une ihm, un web-service ?
     
journal: |
  29-30/3/2019:
    - transfert des registres dans le fichier crsregistre.yaml défini par le schema crsregistre.schema.yaml
  23-24/3/2019:
    - transfert des registres dans le fichier crsregistre.yaml défini par le schema crsregistre.schema.yaml
  22/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
includes: [crs.inc.php, datum.inc.php, geodcrs.inc.php, lambertCC.inc.php, mercator.inc.php, transmerc.inc.php]
*/
require_once __DIR__.'/crs.inc.php';
require_once __DIR__.'/datum.inc.php';
require_once __DIR__.'/geodcrs.inc.php';
require_once __DIR__.'/lambertCC.inc.php';
require_once __DIR__.'/mercator.inc.php';
require_once __DIR__.'/transmerc.inc.php';


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test du package


echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>TEST coordsys.inc.php</title></head><body>\n";
if (!isset($_GET['TEST'])) {
  die("<ul>
<li><a href='crs.inc.php?TEST=crsregcheck'>Vérification du registre</a></li>
<li><a href='datum.inc.php'>Tests de Ellipsoid et Datum</a>
<li><a href='geodcrs.inc.php'>Tests de GeodeticCrs</a>
<li><a href='lambertCC.inc.php'>Tests de lambertCC</a>
<li><a href='mercator.inc.php'>Tests de mercator</a>
<li><a href='transmerc.inc.php'>Tests de transmerc</a>
<li><a href='?TEST=pratique'>Tests d'utilisation pratique</a></li>
</ul>");
}

elseif ($_GET['TEST']=='pratique') {
  // Cas d'usage

  // 1) création d'un objet CRS à partir de ses paramètres et utilisation
  $lambert93Params = [
    'Longitude of false origin'=>3, 'Latitude of false origin'=>46.5, 'Latitude of 1st standard parallel'=>44, 'Latitude of 2nd standard parallel'=>49,
    'Easting at false origin'=>700000, 'Northing at false origin'=>6600000,
  ];
  $lambert93 = new Lambert_Conformal_Conic_2SP('RGF93LonLatDd', $lambert93Params);
    
  $lambert93->geo([111, 456]); // coord. géo. à partir de coord Lambert93
  $lambert93->proj([1.67, 46.75]); // coord. Lambert93 à partir de coord. géo.

  // 2) création d'un objet CRS à partir de son identifiant dans un des 3 registres
  CRS::IGNF('LAMB93')->geo([111, 456]); // coord. géo. à partir de coord Lambert93
  CRS::EPSG('2154')->geo([111, 456]);
  CRS::S('Lambert93')->proj([1.67, 46.75]);
  CRS::S('WebMercator')->geo([111, 456]); // coord. géo. à partir de coord WebMercator
  CRS::S('WorldMercator')->geo([111, 456]); // coord. géo. à partir de coord WorldMercator

  // 3) création objet CRS à partir d'un système géodésique et d'une projection éventuellement paramétrée
  CRS::S('WGS84LonLatDd', 'Spheric_Mercator_1SP')->geo([111, 456]); // coord. géo. à partir de coord WebMercator
  CRS::S('WGS84LonLatDd', 'UTM', '32N')->geo([111, 456]); // coord. géo. à partir de coord UTM-32N WGS84
  CRS::S('RGF93LonLatDd', 'Lambert_Conformal_Conic_2SP', $lambert93Params)->geo([111, 456]); // coord. géo. à partir de coord Lambert_Conformal_Conic_2SP

  // 4) chgt de CRS
  CRS::S('WGS84LonLatDd', 'Spheric_Mercator_1SP')->chg([111, 456], CRS::S('ED50LonLatDd', 'Spheric_Mercator_1SP'));
  CRS::S('WGS84LonLatDd')->chg([1.11, 45.6], CRS::S('ED50LonLatDd', 'Spheric_Mercator_1SP'));
  CRS::IGNF('LAMB93')->chg([658557.55, 6860084.00], CRS::S('ED50LonLatDd')); // passage de Lamb93 en ED50
  
  CRS::S('ED50LonLatDd')->fromWgs84LonLatDd(CRS::IGNF('LAMB93')->wgs84LonLatDd([658557.55, 6860084.00])); // passage de Lamb93 en ED50
  
  # UTM
  CRS::EPSG(2975);

  die("FIN ok ligne ".__LINE__);
}

die("Aucune action ligne ".__LINE__);
