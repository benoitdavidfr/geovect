<?php
/*PhpDoc:
name: index.php
title: index.php - accueil CoordSys
doc: |
journal: |
  24/3/2019:
    - création
*/
?>
<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>CoordSys</title></head><body>
<h2>Accueil CoordSys</h2><ul>
  <li><a href='../schema/?action=check&file=../coordsys/crsregistre.yaml' target='_blank'>vérif registre / schéma</a></li>
  <li><a href='json.php'>export JSON du registre</a><ul>
    <li><a href='json.php/schema'>export du schéma JSON du registre</a></li>
    <li><a href='json.php/EPSG'>export JSON du sous-registre EPSG</a></li>
    <li><a href='json.php/EPSG:2154'>exemple d'export JSON de l'enregistrement EPSG:2154</a></li>
    <li><a href='json.php/EPSG:2154/658557.548,6860084.001/geo'>ex. de calcul de coords géo. d'une pos. Lambert93</a></li>
    <li><a href='json.php/EPSG:2154/658557.548,6860084.001/chg/EPSG:3857'>ex. de calcul de coords WorldMercator d'une pos. Lambert93</a></li>
  </ul></li>
  <li><a href='full.inc.php'>Test full.inc.php</a></li>
  <li><a href='light.inc.php'>Test light.inc.php</a></li>
  <li><a href='draw.php'>Dessin du planisphère dans différentes projections cartographiques</a></li>
  <li><a href='area.php'>Calcul de surfaces</a></li>
  <li><a href='https://github.com/benoitdavidfr/coordsys' target='_blank'>publi sur Github</a></li>
</ul>
