<?php
/*PhpDoc:
name:  transmerc.inc.php
title: transmerc.inc.php - définit les CRS utilisant la projection Mercator Transverse et UTM
includes: [ geodcrs.inc.php ]
classes:
doc: |
  La classe ProjMercatorTransverse implemente la transformation de coordonnées entre des coordonnées géographiques et des 
  coordonnées en projection Mercator Transverse selon l'algorithme du document:
  "Map projections - A Working Manual, John P. Snyder, USGS PP 1395, 1987".
  http://pubs.er.usgs.gov/djvu/PP/PP_1395.pdf
  Pages 60-64 pour les formules et pages 269-271 pour les jeux de tests
  Le code est structuré en fonction de la logique du document en référence.

  Auteur: Benoit DAVID
  Code sous licence CECILL V2 (http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html)

journal: |
  22/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
  20-21/3/2019
  - intégration dans le package coordsys, changement de l'interface pour respecter celle de coordsys
  26/7/2015
  - documentation avec PhpDoc et adaptation à une version plus récente de Php
  4/10/2013
  - nettoyage du code
  16/9/2013
  - ajout des projections UTM ETRS89
  14/8/2011
  - ajout de la possibilite de designer une projection UTM par son code EPSG
  5/1/2011
  - ajout de projre() et geore() pour traiter les rectangles englobants car pour certaines projections la projection
    d'un rectangle englobant n'est pas la projection de ses deux points min max
  2/1/2011
  - la methode unite() peut s'executer soit sans parametre soit avec un code en parametre, dans ce dernier cas
    elle peut s'executer en dehors d'un contexte objet 
  21/12/2010
  - ajout dans proj() et geo() de la possibilite de definir les coord geo. par rapport a Greenwich a la place du meridien d'origine
  - ajout dans listeCodes() la possibilite de se limiter aux systemes definis sur un rectangle englobant defini en CRS:84 
  18-20/10/2010
  - mise en conformite a ProjCarto
  16/12/2010
  - generation de la liste des codes connus
  11/12/2010
  - gestion de la synonymie des codes
  10/10/2010
  - controle des erreurs E_NOTICE
  - corrections de plusieurs formules
  9/10/2010
  - gestion de la projection d'une liste de points
  2-5/10/2010
  - ajout de la capacite a fournir l'unite utilisee
  - premiere version
*/

if (isset($_GET['TEST'])) { error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE); }

require_once __DIR__.'/geodcrs.inc.php';

/*PhpDoc: classes
name:  Transverse_Mercator
title: class Transverse_Mercator extends Datum implements ProjectedCRS - projections Mercator transverse
doc: |
  Classe implémentant la transformation de coordonnees entre des coordonnees geographiques et des coordonnees en projection 
  Mercator Transverse.
*/
class Transverse_Mercator extends GeodeticCRS implements ProjectedCRS {
  protected $limits; // domaine de définition éventuel de la projection comme array
  // les constantes de la projection
  protected $lambda0;
  protected $k0;
  protected $Xs;
  protected $Ys;
  
  // $projParams doit être conforme au schéma défini pour PROJECTION pour cette classe dans crsregistre.schema.yaml
  function __construct($geodCrsId, $projParams=[], array $limits=[]) {
    if ($geodCrsId == 'TEST') return;
    parent::__construct($geodCrsId);
    $this->limits = $limits;
    
    $this->lambda0 = self::toRadian($projParams['central_meridian']);
    $this->k0 = $projParams['scale_factor'];
    $this->Xs = $projParams['Easting at false origin'];
    $this->Ys = $projParams['Northing at false origin'];
  }
  
  // retourne le domaine de définition de la projection comme array des 2 positions SW et NE
  function limits(): array {
    // Par défaut
    $lon0 = $this->lambda0 / pi() * 180; // longitude d'origine en degrés
    $limits = [
      [$lon0 - 10, -90],
      [$lon0 + 10, 90]
    ];
    // Si les limites sont définies alors elles priment sur les valeurs par défaut
    //echo "<pre>limits="; print_r($this->limits); echo "</pre>\n";
    if (isset($this->limits['westlimit']))
      $limits[0][0] = self::toRadian($this->limits['westlimit']) / pi() * 180;
    if (isset($this->limits['southlimit']))
      $limits[0][1] = self::toRadian($this->limits['southlimit']) / pi() * 180;
    if (isset($this->limits['eastlimit']))
      $limits[1][0] = self::toRadian($this->limits['eastlimit']) / pi() * 180;
    if (isset($this->limits['northlimit']))
      $limits[1][1] = self::toRadian($this->limits['northlimit']) / pi() * 180;
    //echo "<pre>limits="; print_r($limits); echo "</pre>\n";
    return $limits;
  }

  private function initConstantes(float $a, float $e2, float $lambda0, float $k0, float $Xs, float $Ys) {
    $this->a = $a;
    $this->e2 = $e2;
    $this->lambda0 = $lambda0;
    $this->k0 = $k0;
    $this->Xs = $Xs;
    $this->Ys = $Ys;
  }
  
  // distanceAlongMeridianFromTheEquatorToLatitude (3-21)
  private function distanceAlongMeridianFromTheEquatorToLatitude($phi) {
    $e2 = ($this->e2());
    return ($this->a())
         * (   (1 - $e2/4 - 3*$e2*$e2/64 - 5*$e2*$e2*$e2/256)*$phi
             - (3*$e2/8 + 3*$e2*$e2/32 + 45*$e2*$e2*$e2/1024)*sin(2*$phi)
             + (15*$e2*$e2/256 + 45*$e2*$e2*$e2/1024) * sin(4*$phi)
             - (35*$e2*$e2*$e2/3072)*sin(6*$phi)
		       );
  }

  // Transforme des coord. géographiques (lon,lat) en degrés en coord. dans le systeme de projection Mercator transverse
  function proj(array $lonLatDeg, bool $greenwich=false): array {
    $lon0 = ($greenwich ? $this->primeMeridian() : 0);
    $lambda = ($lonLatDeg[0] - $lon0) / 180 * pi();
    $phi = $lonLatDeg[1] / 180 * pi();
    $e2 = $this->e2();
    $ep2 = $e2/(1 - $e2); // echo "ep2=$ep2\n"; // (8-12)
    $N = ($this->a()) / sqrt(1 - $e2*pow(sin($phi),2)); // echo "N=$N\n"; // (4-20)
    $T = pow(tan($phi),2); // echo "T=$T\n"; // (8-13)
    $C = $ep2 * pow(cos($phi),2); // echo "C=$C\n"; // (8-14)
    $A = ($lambda - $this->lambda0) * cos($phi); // echo "A=$A\n"; // (8-15)
    $M = $this->distanceAlongMeridianFromTheEquatorToLatitude($phi); // echo "M=$M\n"; // (3-21)
    $M0 = $this->distanceAlongMeridianFromTheEquatorToLatitude(0); // echo "M0=$M0\n"; // (3-21)
    $x = ($this->k0) * $N * ($A + (1-$T+$C)*pow($A,3)/6 + (5-18*$T+pow($T,2)+72*$C-58*$ep2)*pow($A,5)/120); // (8-9)
    //echo "x = ",($this->k0)," * $N * ($A + (1-$T+$C)*pow($A,3)/6 + (5-18*$T+pow($T,2)+72*$C-58*$ep2)*pow($A,5)/120)\n";
    //echo "x = $x\n";
    $y = ($this->k0) * ($M - $M0 + $N * tan($phi) * ($A*$A/2 + (5 - $T + 9*$C +4*$C*$C)
        * pow($A,4)/24 + (61 - 58*$T + $T*$T + 600*$C - 330*$ep2) * pow($A,6)/720));                    // (8-10)
    //echo "y = ($this->k0) * ($M - $M0 + $N * tan($phi) * ($A*$A/2 + (5 - $T + 9*$C +4*$C*$C)
    //   * pow($A,4)/24 + (61 - 58*$T + $T*$T + 600*$C - 330*$ep2) * pow($A,6)/720))\n";
    $k = ($this->k0) * (1 + (1 + $C)*$A*$A/2 + (5 - 4*$T + 42*$C + 13*$C*$C - 28*$ep2)*pow($A,4)/24
         + (61 - 148*$T +16*$T*$T)*pow($A,6)/720);                                                    // (8-11)
    return [ $x + $this->Xs, $y + $this->Ys ];
  }
  
  function test_proj($a, $e, $lon0, $k0, $Xs, $Ys, $lon, $lat, $resultat_attendu) {
    $this->initConstantes ($a, $e*$e, $lon0/180*pi(), $k0, $Xs, $Ys);
    $proj = $this->proj([$lon, $lat]);
    echo "proj (a=",$this->a(),", e=",$this->e(),", ",
	     "lambda0=$this->lambda0, k0=$this->k0, Xs=$this->Xs, Ys=$this->Ys, lon=$lon, lat=$lat)\n",
         "  -> [$proj[0], $proj[1]] / $resultat_attendu\n";
  }

  function geo(array $xy, bool $greenwich=false): array {
    $lon0 = ($greenwich ? $this->primeMeridian() : 0);
    $x = $xy[0] - $this->Xs;
    $y = $xy[1] - $this->Ys;
    $M0 = $this->distanceAlongMeridianFromTheEquatorToLatitude(0); // echo "M0=$M0\n"; // (3-21)
    $e2 = $this->e2();
    $ep2 = $e2/(1 - $e2); // echo "ep2=$ep2\n"; // (8-12)
    $M = $M0 + $y/($this->k0); // echo "M=$M\n"; // (8-20)
    $e1 = (1 - sqrt(1-$e2)) / (1 + sqrt(1-$e2)); // echo "e1=$e1\n"; // (3-24)
    $mu = $M/($this->a()*(1 - $e2/4 - 3*$e2*$e2/64 - 5*$e2*$e2*$e2/256)); // echo "mu=$mu\n"; // (7-19)
    $phi1 = $mu + (3*$e1/2 - 27*pow($e1,3)/32)*sin(2*$mu) + (21*$e1*$e1/16
                - 55*pow($e1,4)/32)*sin(4*$mu) + (151*pow($e1,3)/96)*sin(6*$mu)
                + 1097*pow($e1,4)/512*sin(8*$mu); // echo "phi1=$phi1 radians = ",$phi1*180/pi(),"°\n"; // (3-26)
    $C1 = $ep2*pow(cos($phi1),2); // echo "C1=$C1\n"; // (8-21)
    $T1 = pow(tan($phi1),2); // echo "T1=$T1\n"; // (8-22)
    $N1 = ($this->a())/sqrt(1-$e2*pow(sin($phi1),2)); // echo "N1=$N1\n"; // (8-23)
    $R1 = ($this->a())*(1-$e2)/pow(1-$e2*pow(sin($phi1),2),3/2); // echo "R1=$R1\n"; // (8-24)
    $D = $x/($N1*($this->k0)); // echo "D=$D\n"; 
    $phi = $phi1 - ($N1 * tan($phi1)/$R1) * ($D*$D/2 - (5 + 3*$T1 + 10*$C1 - 4*$C1*$C1 -9*$ep2)*pow($D,4)/24
         + (61 + 90*$T1 + 298*$C1 + 45*$T1*$T1 - 252*$ep2 - 3*$C1*$C1)*pow($D,6)/720); // (8-17)
    $lambda = $this->lambda0 + ($D - (1 + 2*$T1 + $C1)*pow($D,3)/6 + (5 - 2*$C1 + 28*$T1
               - 3*$C1*$C1 + 8*$ep2 + 24*$T1*$T1)*pow($D,5)/120)/cos($phi1); // (8-18)
    return [ ($lambda + $lon0)/pi() * 180, $phi / pi() * 180 ];
  }

  function test_geo($a, $e2, $lon0, $k0, $X, $Y, $resultat_attendu) {
    $this->e2 = $e2;
    $this->lambda0 = $lon0 / 180 * pi();
    $this->k0 = $k0;
    $geo = $this->geo ( [$X, $Y] );
    echo "geo (lambda0=$this->lambda0, e=",$this->e(),", X=$X, Y=$Y)\n",
         "  -> [",$geo[0],", ",$geo[1],"] / $resultat_attendu\n";
  }

  // Affichage des constantes de la projection
  public function afficheConstantes () {
    echo "e=",$this->e(),"\n";
    echo "lambda0=",$this->lambda0,"\n";
    echo "k0=",$this->k0,"\n";
    echo "Xs=",$this->Xs,"\n";
    echo "Ys=",$this->Ys,"\n";
  }
};


/*PhpDoc: classes
name:  UTM
title: class UTM extends Transverse_Mercator implements ProjectedCRS- projections UTM
doc: |
  Classe implémentant la transformation de coordonnees entre des coordonnees geographiques et des coord. en projection UTM.
  Les projections UTM sont définies par 120 zones numérotées par un numéro de 1 à 60 et soit N soit S.
  Chaque zone correspond à une bande de 6° de longitude centrée sur la longitude ({zoneNum} - 30.5) * 6.
*/
class UTM extends Transverse_Mercator implements ProjectedCRS {
  private $zoneNum; // no de zone de 1 à 60
  private $zoneNS; // N ou S
  
  // $projParams doit être conforme au schéma défini pour PROJECTION pour cette classe dans crsregistre.schema.yaml
  // ou $projParams doit être la chaine définissant la zone UTM
  function __construct($geodCrsId, $projParams=[], array $limits=[]) {
    if ((!(is_string($projParams) && preg_match('/^(\d+)([NS])$/', $projParams, $matches)))
      && (!(is_array($projParams) && isset($projParams['zone'])
         && preg_match('/^(\d+)([NS])$/', $projParams['zone'], $matches))))
      throw new Exception ("params incorrect dans UTM::__construct()");
    $this->zoneNum = $matches[1];
    $lon0 = ($this->zoneNum - 30.5) * 6;
    $this->zoneNS = $matches[2];
    parent::__construct(
      $geodCrsId,
      [ 'central_meridian' => $lon0,
        'scale_factor' => 0.9996,
        'Easting at false origin' => 500000,
        'Northing at false origin' => ($this->zoneNS == 'S'? 10000000 : 0),
      ],
      // je prends une bande de 9° centrée sur la longitude centrale 
      [ 'westlimit'=> $lon0 - 4.5,
        'southlimit'=> $this->zoneNS == 'S'? -85 : -2,
        'eastlimit'=> $lon0 + 4.5,
        'northlimit'=> $this->zoneNS == 'S'?  2 :  85,
      ]
    );
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test de la classe


echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>TEST Transverse_Mercator</title></head><body>\n";

if (!isset($_GET['TEST'])) {
  die("<ul>
 <li><a href='?TEST=USGS'>Test des jeux d'essai fournis dans le rapport USGS</a>
 <li><a href='?TEST=pratique'>Tests d'utilisation pratique</a>
 </ul>");
}

// Test des jeux d'essai fournis dans le rapport USGS
elseif ($_GET['TEST']=='USGS') {
  echo "<pre>Tests sur les jeux d'essai fournis dans le document USGS\n";

  $projMT = new Transverse_Mercator('TEST', null);
  echo "** Test de proj\n";
  //function test_proj ($a, $e, $lambda0, $k0, $Xs, $Ys, $phi, $lambda, $resultat_attendu)
  //$projMercatorTransverse->test_proj(6378206.4, sqrt(0.00676866), radians("75°W"), 0.9996, 0, 0, radians("40°30'N"), radians("73°30'W"), "[127 106.5, 4 484 124.4]");
  //function test_proj($a, $e, $lon0, $k0, $Xs, $Ys, $lon, $lat, $resultat_attendu) {
  $projMT->test_proj(6378206.4, sqrt(0.00676866), -75, 0.9996, 0, 0, -73.5, 40.5, "[127 106.5, 4 484 124.4]");
  echo "\n";

  echo "** Test de geo\n";

  //function test_geo($a, $e2, $lon0, $k0, $X, $Y, $resultat_attendu) {
  $projMT->test_geo(6378206.4, 0.00676866, -75, 0.9996, 127106.5, 4484124.4, "[73°30'W, 40°30'N]");
  die("FIN OK\n");
}
 
// Tests d'utilisation pratique
elseif ($_GET['TEST']=='pratique') {
  echo "<pre>** Exemple Phare Pointe des dames de Noirmoutier\n";
  $projUTM = new UTM('RGF93LonLatDd', '30N');
  $projUTM->afficheConstantes();
  $cgeo0 = [ -(2 + (13 + 16/60) / 60) /* 2°13'16''W */, 47 + 40/60/60 /* 47°00'40''N */];
  $cproj = $projUTM->proj($cgeo0);
  echo "cproj=",json_encode($cproj),"<br>\n";
  $cgeo = $projUTM->geo($cproj);
  echo "cgeo=",json_encode($cgeo),'/',json_encode($cgeo0),"<br>\n";
  
  echo json_encode(CRS::S('RGF93LonLatDd', 'UTM', '30N')->proj($cgeo)),"<br>\n";
    
  die("FIN OK\n");
}
