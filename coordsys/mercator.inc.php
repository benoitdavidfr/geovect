<?php
{/*PhpDoc:
name:  mercator.inc.php
title: mercator.inc.php - définit les CRS utilisant les projections Mercator sphérique et ellisoidique
includes: [ geodcrs.inc.php ]
classes:
doc: |
  Classes implémentant le passage entre coordonnées en projection Mercator et coordonnées geographiques selon l'algorithme
  du document: "Map projections - A Working Manual, John P. Snyder, USGS PP 1395, 1987".
    http://pubs.er.usgs.gov/djvu/PP/PP_1395.pdf
    Pages 41-47 pour les formules et pages 266-268 pour les jeux de tests
  Deux classes sont definies, la premiere pour la definition sur la Sphere notamment utilisee par Google Maps, ...
  La seconde correspond a la définition sur un ellipsoide.
  Le code est structuré en fonction de la logique du document en référence.
  Ces classes n'implémentent que le cas:
    PARAMETER["central_meridian",0],
    PARAMETER["scale_factor",1],
    PARAMETER["Easting at false origin",0],
    PARAMETER["Northing at false origin",0],
  Des tests unitaires de chaque classe sont réalisés en exécutant ce fichier comme script Php.
  Auteur: Benoit DAVID
  Code sous licence CECILL V2 (http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html)

journal: |
  22-24/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
  20/3/2019
  - intégration dans le package coordsys, changement de l'interface pour respecter celle de coordsys
  5/6/2017
  - ajout de la projection: World Mercator - WGS84 (EPSG:3395)
  26/7/2015
  - documentation avec PhpDoc et adaptation à une version plus récente de Php
  16/7/2012
  - correction d'un bug dans ProjMercatorSpherique::geo()
  15/7/2012
  - modification du code EPSG de la projection Mercator sphérique qui est 3857
  5/1/2011
  - ajout de projre() et geore() pour traiter les rectangles englobants car pour certaines projections la projection
    d'un rectangle englobant n'est pas la projection de ses deux points min max
  2/1/2011
  - la methode unite() peut s'executer soit sans parametre soit avec un code en parametre, dans ce dernier cas
    elle peut s'executer en dehors d'un contexte objet 
  21/12/2010
  - ajout dans proj() et geo() de la possibilite de definir les coord geo. par rapport a Greenwich a la place du meridien d'origine
  - ajout dans listeCodes() la possibilite de se limiter aux systemes definis sur un rectangle englobant defini en CRS:84 
  18/10/2010
  - mise en conformite a ProjCarto
  16/12/2010
  - generation de la liste des codes connus
  11/12/2010
  - gestion de la synonymie des codes
  17/10/2010
  - premiere version
*/}

      
if (isset($_GET['TEST'])) { error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE); }

require_once __DIR__.'/geodcrs.inc.php';

/*PhpDoc: classes
name:  Spheric_Mercator_1SP
title: class Spheric_Mercator_1SP extends GeodeticCRS implements ProjectedCRS - Projection Mercator sphérique
doc: |
  Systeme spherique: s'appui sur Datum pour la definition du rayon de la sphere qui est le demi-grand axe de l'ellipsoide 
  utilisée par le systeme geodesique.
*/
class Spheric_Mercator_1SP extends GeodeticCRS implements ProjectedCRS {
  private $limits; // domaine de définition éventuel de la projection comme array
  
  // le paramètre $projParams peut être [] ou absent
  function __construct($geodCrsId, $projParams=[], array $limits=[]) {
    if ($geodCrsId == 'TEST') return;
    parent::__construct($geodCrsId);
    $this->limits = $limits;
  }
  
  function limits(): array { return [[-180, -85], [180, 85]]; }

  // Transforme des coord. geo. (lon, lat) en degrés décimaux en coord. dans le systeme de projection Mercator spherique
  function proj(array $lonLatDeg): array {
    $lambda = $lonLatDeg[0] / 180.0 * pi();
    $phi = $lonLatDeg[1] / 180.0 * pi();
    return [
      $this->a() * $lambda, // (7-1)
      $this->a() * log(tan(pi()/4 + $phi/2)), // (7-2)
    ];
  }
    
  // Transforme des coordonnées en projection Mercator spherique en coordonnees geographiques (lon, lat) en degrés décimaux
  function geo(array $xy): array {
    list($x, $y) = $xy;
    $phi = pi()/2 - 2*atan(exp(-$y/$this->a())); // (7-4)
    $lambda = $x/$this->a(); // (7-5)
    return [
      $lambda / pi() * 180,
      $phi / pi() *180,
    ];
  }
};


/*PhpDoc: classes
name:  Mercator_1SP
title: class Mercator_1SP extends GeodeticCRS implements ProjectedCRS - Systeme Mercator ellipsoidique
doc: |
  Projection Mercator définie sur un ellipsoide. 
*/
class Mercator_1SP extends GeodeticCRS implements ProjectedCRS {
  private $limits; // domaine de définition éventuel de la projection comme array
  
  // le paramètre $projParams peut être [] ou absent
  function __construct($geodCrsId, $projParams=[], array $limits=[]) {
    if ($geodCrsId == 'TEST') return;
    parent::__construct($geodCrsId);
    $this->limits = $limits;
  }

  function limits(): array { return [[-180, -85], [180, 85]]; }
  
  // Transforme des coord. geo. (lon, lat) en degrés décimaux en coord. dans le systeme de projection Mercator ellipsoidique
  function proj(array $lonLatDeg): array {
    $lambda = $lonLatDeg[0] / 180.0 * pi();
    $phi = $lonLatDeg[1] / 180.0 * pi();
    $e = $this->e();
    return [
      $this->a() * $lambda, // (7-6)
      $this->a() * log(tan(pi()/4 + $phi/2) * pow((1-$e*sin($phi))/(1+$e*sin($phi)),$e/2)), // (7-7)
    ];
  }
  
  // Transforme des coordonnées en projection Mercator ellipsoidique en coordonnees geographiques (lon, lat) en degrés décimaux
  function geo(array $xy): array {
    list($x, $y) = $xy;
    $t = exp(-$y/$this->a()); // (7-10)
    $phi = pi()/2 - 2*atan($t); // (7-11)
    $lambda = $x / $this->a(); // (7-12)
    $e = $this->e();
    for ($nbiter=0; $nbiter<20; $nbiter++) {
      $phi0 = $phi;
      $phi = pi()/2 - 2*atan($t * pow((1-$e*sin($phi))/(1+$e*sin($phi)),$e/2)); // (7-9)
      if (abs($phi-$phi0) < self::EPSILON)
        return [ $lambda / pi() * 180.0, $phi / pi() * 180.0 ];
    }
    throw new Exception("Convergence inachevee dans Mercator_1SP::geo() pour nbiter=$nbiter");
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test des 2 classes


echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>TEST mercator</title></head><body>\n";
  
if (!isset($_GET['TEST'])) {
  die("<ul>
<li><a href='?TEST=Spheric_Mercator_1SP'>Test sur Spheric_Mercator_1SP</a>
<li><a href='?TEST=Mercator_1SP'>Tests sur Mercator_1SP</a>
</ul>");
}

elseif ($_GET['TEST']=='Spheric_Mercator_1SP') {
  echo "Tests de creation de l'objet\n";

  try {
    $proj = new Spheric_Mercator_1SP('nonDefini');
  } catch (Exception $e) {
    echo "Message d'exception ",$e->getMessage()," OK<br><br>\n";
  }

  echo "\nTests sur les jeux d'essai prour la projection de Mercator spherique fournis dans le document USGS<br>\n";
  $proj = new Spheric_Mercator_1SP('sphereUniteOrigine180W');
  $cgeo = [-75 + 180, 35];
  $cproj = $proj->proj($cgeo);
  printf("(lon=%f°, lat=%f°)->(x=%.7f /1.8325957, y=%.7f / 0.6528366)<br>\n", $cgeo[0], $cgeo[1], $cproj[0], $cproj[1]);
  
  $cgeo2 = $proj->geo($cproj);
  echo "lon=$cgeo2[0] / $cgeo[0] ; lat=$cgeo2[1] / $cgeo[1] <br>\n"; 
  die("FIN OK");
}

elseif ($_GET['TEST']=='Mercator_1SP') {
  echo "Tests de creation de l'objet\n";

  try {
    $proj = new Mercator_1SP('malDefini');
  } catch (Exception $e) {
    echo "Message d'exception ",($e->getMessage()),"OK<br><br>\n";
  }

  echo "\nTests sur les jeux d'essai pour la projection de Mercator ellipsoidale fournis dans le document USGS<br>\n";
  $proj = new Mercator_1SP('NAD27origine180W');
  $cgeo = [-75 + 180, 35];
  $cproj = $proj->proj($cgeo);
  printf("(lon=%d, lat=%d°)->(x=%.1f /11 688 673.7, y=%.1f / 4 139 145.6)<br>\n", $cgeo[0], $cgeo[1], $cproj[0], $cproj[1]);
  
  $cgeo2 = $proj->geo($cproj);
  echo "lon=$cgeo2[0] / $cgeo[0], lat=$cgeo2[1] / $cgeo[1]<br>\n"; 
  die("FIN OK");
}
