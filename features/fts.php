<?php
/*PhpDoc:
name: fts.php
title: fts.php - exposition de données au protocoles API Features
doc: |
  Proxy exposant en API Features des données initialement stockées soit dans une BD MySql ou PgSql, soit dans un serveur WFS2,
  soit dans répertoire de fichiers GeoJSON.

  Permet soit d'utiliser un serveur non enregistré en utilisant par exemple pour un serveur WFS l'url:
    https://features.geoapi.fr/wfs/services.data.shom.fr/INSPIRE/wfs
     ou en local:
      http://localhost/geovect/features/fts.php/wfs/services.data.shom.fr/INSPIRE/wfs
  soit d'utiliser des serveurs biens connus et documentés comme dans l'url:
   https://features.geoapi.fr/shomwfs
    ou en local:
      http://localhost/geovect/features/fts.php/shomwfs
  
  Un appel sans paramètre liste les serveurs bien connus et des exemples d'appel.

  Ce fichier peut être inclus dans un script pour utiliser le code du serveur OGC API Features

  Utilisation avec QGis:
    - les dernières versions de QGis (3.16) peuvent utiliser les serveurs OGC API Features
    - a priori elles n'exploitent pas ni n'affichent la doc, notamment les schemas
    - QGis utilise titre et description fournis dans /collections
    - lors d'une requête QGis demande des données zippées ou deflate
    - a priori QGis n'utilise pas les possibilités de filtre définies dans l'API

  Perf:
    - 3'05" pour troncon_hydro R500 sur FX depuis Alwaysdata
    - 47' même données gzippées et !JSON_PRETTY_PRINT soit 1/4

  Utilisation avec curl:
    curl -X GET "https://features.geoapi.fr/ignf-route500/collections/aerodrome/items?f=json&limit=10&startindex=0" -H  "accept: application/geo+json"
    curl -X GET "http://localhost/geovect/features/fts.php/ignf-route500/collections/aerodrome/items?f=json&limit=10&startindex=0" -H  "accept: application/geo+json"
    curl -X GET "http://localhost/geovect/features/fts.php/route500it/collections/aerodrome/items?f=json&limit=10&startindex=0" -H  "accept: application/geo+json"

  A faire (court-terme):
    - revoir les describedBy dans WFS pour fabriquer des schémas JSON prenant si possible en compte les specs
    - rajouter dans les liens au niveau de chaque collection,
      un lien {type: text/html, rel: canonical, title: information, href= ...}
      vers la doc quand il y a au moins soit une description, soit la définition de propriétés
    - gérer correctement les types non string dans les données comme les nombres
  Réflexions (à mûrir):
    - rajouter dans les liens au niveau de chaque collection, un lien de téléchargement simple quand j'en dispose d'un,
      ex: { "href": "http://download.example.org/buildings.gpkg",
            "rel": "enclosure", "type": "application/geopackage+sqlite3",
            "title": "Bulk download (GeoPackage)", "length": 472546
          }
    - distinguer un outil d'admin différent de l'outil fts.php de consultation
      - y transférer l'opération check de vérif. de clé primaire et de création éventuelle
      - ajouter une fonction de test de cohérence doc / service déjà écrite dans doc
  Idées (plus long terme):
    - mettre en oeuvre le mécanisme i18n défini pour OGC API Features
    - remplacer l'appel sans paramètre par l'exposition d'un catalogue DCAT
    - renommer geovect en gdata pour green data
    - étendre features aux autres OGC API ?
journal: |
  1/3/2022:
    - définition de la fonction fts pour clarifier la réutilisation du code
  28/2/2022:
    - gestion du bbox en POST pour satisfaire aux besoins de L.UGeoJSONLayer dans Leaflet
    - gestion properties et filters dans l'appel de items()
  27/2/2022:
    - amélioration de la gestion des erreurs, utilisation de SExcept
  25/2/2022:
    - bug corrigé sur Alwaysdata
    - synchro sur alwaysdata
  18/2/2022:
    - adaptation à la mise en oeuvre de ftsopg.php
  6/2/2021:
    - réduction de l'empreinte mémoire dans items par l'utilisation de display_json() et display_fmt()
  27/1/2021:
    - détection des paramètres non prévus
    - test CITE ok pour /ignf-route500
  20-23/1/2021:
    - intégration de la doc
  17/1/2021:
    - onsql fonctionne avec QGis
  30/12/2020:
    - création
includes:
  - fts.inc.php
*/
//require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/fts.inc.php';

ini_set('memory_limit', '10G');

// Définit le fuseau horaire par défaut à utiliser
date_default_timezone_set('UTC');

if (1)
FeatureServer::log([
  'REQUEST_URI'=> $_SERVER['REQUEST_URI'],
  'Headers'=> getallheaders(),
]
); // log de la requête pour deboggage, a supprimer en production

$doc = new Doc; // documentation des serveurs biens connus 

if (in_array($_SERVER['PATH_INFO'] ?? '', ['', '/'])) { // appel sans paramètre 
  echo "</pre><h2>Bouquet de serveurs OGC API Features</h2>
Ce site expose un bouquet de serveurs conformes
à la <a href='http://docs.opengeospatial.org/is/17-069r3/17-069r3.html' target='_blank'>norme OGC API Features</a>.<br>
Il est en développement et uniquement certains types de serveurs sont conformes à cette norme.<br>
Les 3 types de sources de données exposées sont:<ul>
<li>des données d'une base MySql ou PgSql/PostGis (en béta),</li>
<li>des données exposées par un serveur WFS (en cours),</li>
<li>des données stockées dans des fichiers GeoJSON (en cours).</li>
</ul>

Les sources exposées sont les suivantes :<ul>\n";
  foreach ($doc->datasets as $dsid => $dsDoc) {
    if (($_SERVER['HTTP_HOST']=='localhost') || !preg_match('!@172\.17\.0\.!', $dsDoc->path()))
    echo "<li><a href='$_SERVER[SCRIPT_NAME]/$dsid'>",$dsDoc->title(),"</a></li>\n";
  }

  echo "</ul>
Ces serveurs peuvent notamment être utilisés avec les dernières versions
de <a href='https://www.qgis.org/fr/site/' target='_blank'>QGis (3.16)</a>
ou être consultés en Html.<br>\n";
  die();
}
else
  fts($_SERVER['PATH_INFO'], $doc);
