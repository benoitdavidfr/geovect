## coordsys - conversion de coordonnées entre systèmes de référence (CRS)

Comprend 2 packages à utiliser indépendamment:

  - un package complet full.inc.php qui permet notamment les conversions entre systèmes géodésiques distincts,
  - un package simple light.inc.php limité aux conversions entre projections sur WGS84.

Le package simple s'utilise en incluant le fichier light.inc.php  
Exemples d'utilisation:

  `Lambert93::geo([658557.548, 6860084.001]); // coords géo. (lon,lat) en degrés de la position en L93`  
  `Lambert93::proj([2.435368, 48.839473]); // coords Lambert 93 de la position (lon,lat) en degrés`  
  `WebMercator::proj([2.435368, 48.839473]); // coords WebMercator de la position (lon,lat) en degrés`  
  `WorldMercator::proj([2.435368, 48.839473]); // coords WorldMercator de la position (lon,lat) en degrés`  
  `UTM::proj('31N', [2.435368, 48.839473]); // coords UTM zone 31N de la pos. (lon,lat) en degrés`  
  `UTM::zone([2.435368, 48.839473]); // fournit la zone UTM de la pos. (lon,lat) en degrés`    
  `Legal::proj([2.435368, 48.839473]); // coords légales en métropole ou DROM de la pos. (lon,lat) en degrés`  

Le package complet s'utilise en incluant le fichier full.inc.php, exemples d'utilisation:

  1. création d'un objet CRS à partir de son identifiant dans un des 3 registres  
  exemples d'utilisation:
  
    `CRS::IGNF('LAMB93')->geo([111, 456]); // coord. géo. à partir de coord Lambert93`  
    `CRS::EPSG('2154')->geo([111, 456]); // utilisation du registre EPSG`  
    `CRS::S('Lambert93')->proj([1.67, 46.75]); // utilisation du registre SIMPLE`  
    `CRS::S('WebMercator')->geo([111, 456]); // coord. géo. à partir de coord WebMercator`  
    `CRS::S('WorldMercator')->geo([111, 456]); // coord. géo. à partir de coord WorldMercator`

  2. création objet CRS à partir d'un système géodésique et d'une projection éventuellement paramétrée  
  exemples d'utilisation:
  
    `CRS::S('WGS84LonLatDd', 'Spheric_Mercator_1SP')->geo([111, 456]); // coord. géo. à partir de coord WebMercator`  
    `CRS::S('WGS84LonLatDd', 'UTM', '32N')->geo([111, 456]); // coord. géo. à partir de coord UTM-32N WGS84`  

  3. chgt de CRS dans des systèmes géodésiques différents en utilisant les coordonnées WGS84 comme intermédiaire,
  exemples d'utilisation:
 
    `CRS::S('ED50LonLatDd')->fromWgs84LonLatDd(CRS::IGNF('LAMB93')->wgs84LonLatDd([658557.55, 6860084.00])); // passage de Lamb93 en ED50`  

Description des fichiers:

  - index.php - page d'accueil proposant l'appel des tests et du script json.php
  - json.php - export JSON du registre + webservice simple de conversion de coordonnées
  - light.inc.php - package light + tests
  - full.inc. php - fichier à inclure pour le package full + doc + tests + utilisation pratique + vérif registre
  - crsregistre.yaml - registre des CRS
  - crsregistre.schema.yaml - schéma JSON du registre des CRS
  - crs.inc.php - définit la classe abtraite CRS, l'interface ProjectedCRS et la fonction degres_sexa()
  - datum.inc.php - définit les classes Ellipsoid et GeodeticDatum
  - geodcrs.inc.php - définit la classe GeodeticCRS
  - lambertCC.inc.php - définit les CRS utilisant la projection Conique Conforme de Lambert
  - mercator.inc.php - définit les CRS utilisant les projections Mercator sphérique et ellisoidique
  - transmerc.inc.php - définit les CRS utilisant la projection Mercator Transverse et UTM
  - draw - contient un script de dessin du trait de côte dans différents systèmes de coordonnées
  - ne110m - contient un extrait de la base Natural Earth au 1/110M
    

<h3>Bibliographie</h3><ul>
  <li><a href='http://pubs.er.usgs.gov/djvu/PP/PP_1395.pdf' target='_blank'>
    Map projections - A Working Manual, John P. Snyder, USGS</a></li>
  <li><a href='https://geodesie.ign.fr/index.php?page=algorithmes ' target='_blank'>Algorithmes Géodésiques (IGN)</a><ul>
    <li><a href='https://geodesie.ign.fr/contenu/fichiers/documentation/algorithmes/notice/NTG_71.pdf' target='_blank'>
      NT/G 71 Projection cartographique de Lambert</a></li>
    <li><a href='https://geodesie.ign.fr/contenu/fichiers/documentation/algorithmes/notice/NTG_80.pdf' target='_blank'>
      NT/G 80 Changement de système géodésique</a></li>
  </ul></li>
  <li><a href='http://earth-info.nga.mil/GandG/wgs84/web_mercator/(U)%20NGA_SIG_0011_1.0.0_WEBMERC.pdf' target='_blank'>
    Web Mercator Map Projection, Implementation Practice, 2014-02-18, Version 1.0.0,<br>
    National Geospatial-intelligence Agency (NGA) standardization document</a></li>
  <li><a href='http://docs.opengeospatial.org/is/12-063r5/12-063r5.html' target='_blank'>
    Standard OGC CRS WKT (ISO 19162:2015)</a></li>
  <li><a href='https://www.epsg-registry.org/' target='_blank'>registre des codes EPSG</a></li>
  <li><a href='https://registre.ign.fr/ign/IGNF/IGNF/' target='_blank'>registre des codes IGN-F</a></li>
  <li><a href='http://geodesie.ign.fr/contenu/fichiers/documentation/srtom/SystemeCOM.pdf' target='_blank'>
    Systèmes de Référence Géodésique des Communautés d'Outre-Mer (IGN)</a></li>
  <li><a href='https://dittt.gouv.nc/geodesie-et-nivellement/les-referentiels-de-nouvelle-caledonie' target='_blank'>
    Définition des référentiels de Nouvelle-Calédonie (DITT)</a></li>
  <li><a href='http://www.shom.fr/les-activites/activites-scientifiques/reseau-geodesique-de-polynesie-francaise-rgpf/' target='_blank'>
    Définition du Réseau Géodésique de Polynésie Française (RGPF) (Shom)</a></li>
  <li><a href='http://spatialreference.org/' target='_blank'>registre contributif spatialreference.org</a></li>
</ul>
