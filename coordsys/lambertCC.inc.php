<?php
{/*PhpDoc:
name: lambertCC.inc.php
title: lambertCC.inc.php - définit les CRS utilisant la projection Conique Conforme de Lambert
classes:
doc: |
  Classes implémentant le passage entre coordonnées en projection conique conforme de Lambert
  et coordonnées géographiques (longitude, latitude) en degrés décimaux.
  Auteur: Benoit DAVID
  Code sous licence CECILL V2 (http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html)

  L'algorithme est celui de la note note IGN/SGN/NT/G 71 de janvier 1995
  https://geodesie.ign.fr/contenu/fichiers/documentation/algorithmes/notice/NTG_71.pdf
  
  Projection cartographique conique conforme de Lambert
  Le code est structuré en fonction de la logique de la note en référence.
  Définition de 2 classes différentes pour les cas sécant et tangeant

journal: |
  22-24/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
    - définition de 2 classes différentes pour les cas sécant et tangeant
  20/3/2019:
    restructuration du code de syscoord de juillet 2015
    passage des coordonnées en (lon,lat) en degrés décimaux
includes: [geodcrs.inc.php]
*/}

require_once __DIR__.'/geodcrs.inc.php';

/*PhpDoc: classes
name: Lambert_Conformal_Conic_2SP
title: class Lambert_Conformal_Conic_2SP extends GeodeticCRS implements ProjectedCRS - CRS utilisant la projection Conique Conforme de Lambert
doc: |
  Cette classe implémente le cas tangeant comme le cas sécant.
*/
class Lambert_Conformal_Conic_2SP extends GeodeticCRS implements ProjectedCRS {
  protected $projParams; // paramètres d'initialisation
  protected $limits; // domaine de définition éventuel de la projection comme array
  // les constantes de la projection
  protected $lambdac; // longitude d'origine par rapport au meridien d'origine
  protected $n; // exposant de la projection
  protected $C; // constante de la projection
  protected $Xs; // coordonnees du pole en projection
  protected $Ys; // coordonnees du pole en projection
    
  // $projParams doit être conforme au schéma défini pour PROJECTION pour cette classe dans crsregistre.schema.yaml
  function __construct($geodCrsId, $projParams=[], array $limits=[]) {
    parent::__construct($geodCrsId);
    $this->projParams = $projParams;
    $this->limits = $limits;
    //echo "limits="; print_r($this->limits);
    if (isset($projParams['Latitude of 1st standard parallel']))
      $this->calculConstantesCasSecant(
        self::toRadian($projParams['Longitude of false origin']), self::toRadian($projParams['Latitude of false origin']),
        self::toRadian($projParams['Latitude of 1st standard parallel']),
        self::toRadian($projParams['Latitude of 2nd standard parallel']),
        $projParams['Easting at false origin'], $projParams['Northing at false origin']
      );
    elseif (isset($projParams['Scale factor at natural origin']))
      $this->calculConstantesCasTangent(
        self::toRadian($projParams['Longitude of false origin']), self::toRadian($projParams['Latitude of false origin']),
        $projParams['Scale factor at natural origin'],
        $projParams['Easting at false origin'], $projParams['Northing at false origin']
      );
    //$this->afficheConstantes();
  }
  
  // retourne le domaine de définition de la projection comme array des 2 positions SW et NE
  function limits(): array {
    // Par défaut
    $lon0 = ($this->lambdac + $this->primem) / pi() * 180; // longitude d'origine en degrés
    $limits = [
      [$lon0 - 10, -85],
      [$lon0 + 10, 85]
    ];
    if (isset($this->projParams['Latitude of 1st standard parallel'])) {
      // Dans le cas sécant, je prend comme amplitude max en latitude le double de l'écart entre les 2 latitudes std
      $lat1 = $this->projParams['Latitude of 1st standard parallel'];
      $lat2 = $this->projParams['Latitude of 2nd standard parallel'];
      $latmin = min($lat1, $lat2);
      $latmax = max($lat1, $lat2);
      $limits[0][1] = $latmin - ($latmax - $latmin)/2;
      $limits[1][1] = $latmax + ($latmax - $latmin)/2;
    }
    // Si les limites sont définies alors elles priment sur les valeurs par défaut
    if (isset($this->limits['westlimit']))
      $limits[0][0] = self::toRadian($this->limits['westlimit']) / pi() * 180;
    if (isset($this->limits['southlimit']))
      $limits[0][1] = self::toRadian($this->limits['southlimit']) / pi() * 180;
    if (isset($this->limits['eastlimit']))
      $limits[1][0] = self::toRadian($this->limits['eastlimit']) / pi() * 180;
    if (isset($this->limits['northlimit']))
      $limits[1][1] = self::toRadian($this->limits['northlimit']) / pi() * 180;
    return $limits;
  }
  
  // ALG0054
  // Calcul des constantes de projection dans le cas secant
  function calculConstantesCasSecant(float $lambda0, float $phi0, float $phi1, float $phi2, float $X0, float $Y0): void {
    //echo "calculConstantesCasSecant (lambda0=$lambda0, phi0=$phi0, phi1=$phi1, phi2=$phi2, X0=$X0, Y0=$Y0)\n";
    $this->lambdac = $lambda0;
    $this->n = ( (log( ($this->grande_normale($phi2)*cos($phi2))/($this->grande_normale($phi1)*cos($phi1)) ))
	     / ( $this->latitude_isometrique($phi1) - $this->latitude_isometrique($phi2) ));
    $this->C = $this->grande_normale($phi1) * cos($phi1) / $this->n * exp($this->n * $this->latitude_isometrique($phi1));
    //echo "phi0=",$phi0,"  (PI/2=",pi()/2,"), phi0-PI/2=",$phi0-pi()/2,"\n";
    if (abs($phi0 - pi()/2) < 1.0E-9) {
      $this->Xs = $X0;
      $this->Ys = $Y0;
    } else {
      $this->Xs = $X0;
      $this->Ys = $Y0 + $this->C * exp(-1 * $this->n * $this->latitude_isometrique($phi0));
    }
  }
  
  function test_calculConstantesCasSecant(float $lambda0, float $phi0, float $phi1, float $phi2, float $X0, float $Y0, array $resultats_attendus) {
    echo "test_calculConstantesCasSecant (lambda0=$lambda0, phi0=$phi0, phi1=$phi1, phi2=$phi2, X0=$X0, Y0=$Y0)\n";
    $this->calculConstantesCasSecant ($lambda0, $phi0, $phi1, $phi2, $X0, $Y0);
    $this->afficheConstantes($resultats_attendus);
    echo "\n";
  }

  // ALG0019
  // Calcul des constantes de projection dans le cas tangent
  function calculConstantesCasTangent(float $lambda0, float $phi0, float $k0, float $X0, float $Y0): void {
    $this->lambdac = $lambda0;
    $this->n = sin($phi0);
    $this->C = $k0 * $this->grande_normale($phi0) * (1/tan($phi0)) * exp($this->n * $this->latitude_isometrique($phi0));
    $this->Xs = $X0;
    $this->Ys = $Y0 + $k0 * $this->grande_normale($phi0) / tan($phi0);
  }
    
  function test_calculConstantesCasTangent(float $lambda0, float $phi0, float $k0, float $X0, float $Y0, array $resultats_attendus) {
    echo "test_calculConstantesCasTangent(lambda0=$lambda0, phi0=$phi0, k0=$k0, X0=$X0, Y0=$Y0)\n";
    $this->calculConstantesCasTangent($lambda0, $phi0, $k0, $X0, $Y0);
    $this->afficheConstantes($resultats_attendus);
    echo "\n";
  }

  // Affichage des constantes de la projection
  function afficheConstantes ($res=null) {
    echo "lambdac=",$this->lambdac,($res?" / ".$res['lambdac']:''),"\n";
    echo "n=",$this->n,($res?" / ".$res['n']:''),"\n";
    echo "C=",$this->C,($res?" / ".$res['C']:''),"\n";
    echo "Xs=",$this->Xs,($res?" / ".$res['Xs']:''),"\n";
    echo "Ys=",$this->Ys,($res?" / ".$res['Ys']:''),"\n";
  }
  
  // ALG0003
  // Tranformation de coordonnées géographiques en degrés décimauux en coordonnées en projection Lambert
  function proj(array $lonLatDeg): array {
    list($lon, $lat) = $lonLatDeg;
    $lon = $lon / 180.0 * pi();
    $lat = $lat / 180.0 * pi();
    $Latiso = $this->latitude_isometrique ($lat);
    return [
      $this->Xs + $this->C * exp(-1 * $this->n * $Latiso) * sin($this->n * ($lon - $this->lambdac)),
      $this->Ys - $this->C * exp(-1 * $this->n * $Latiso) * cos($this->n * ($lon - $this->lambdac)),
    ];
  }

  function test_proj(float $n, float $C, float $lambdac, float $Xs, float $Ys, float $lambda, float $phi, string $resultat_attendu) {
    $this->lambdac = $lambdac;
    $this->n = $n;
    $this->C = $C;
    $this->Xs = $Xs;
    $this->Ys = $Ys;
    $proj = $this->proj([$lambda/pi()*180, $phi/pi()*180]);
    echo "proj (n=$this->n, C=$this->C, lambdac=$this->lambdac, Xs=$this->Xs, Ys=$this->Ys, lambda=$lambda, phi=$phi)\n",
         "  -> [$proj[0], $proj[1]} / $resultat_attendu\n";
  }  

  // ALG0004
  // Effectue l'inverse, cad calcul des coord geographiques a partir de coord. en projection Lambert
  public function geo (array $xy): array {
    //$this->afficheConstantes();
    list($X, $Y) = $xy;
    if (!is_numeric($X)) die("Erreur X non numeric ligne ".__LINE__);
    //echo "<pre>this="; print_r($this); echo "</pre>\n";
    //echo "sqrt( pow(($X - $this->Xs),2) + pow(($Y - $this->Ys),2) );<br>\n";
    $R = sqrt( pow(($X - $this->Xs),2) + pow(($Y - $this->Ys),2) );
    $gamma = atan(($X - $this->Xs)/($this->Ys - $Y));
    $lon = $this->lambdac + ($gamma / $this->n); 
    $L = (-1 / $this->n) * log(abs($R/$this->C));
    //echo "L=$L<br>\n";
    $lat = $this->latitude($L);
    return [ $lon / pi() * 180.0, $lat / pi() * 180.0];
  }

  public function test_geo(float $X, float $Y, float $n, float $C, float $Xs, float $Ys, float $lambdac, string $resultat_attendu) {
    $this->lambdac = $lambdac;
    $this->n = $n;
    $this->C = $C;
    $this->Xs = $Xs;
    $this->Ys = $Ys;
    $geo = $this->geo ( [$X, $Y] );
    echo "geo (X=$X, Y=$Y, n=$this->n, C=$this->C, Xs=$this->Xs, Ys=$this->Ys, lambdac=$this->lambdac)\n",
         "  -> [",$geo[0]/180.0*pi(), ', ', $geo[1]/180.0*pi(),"] / $resultat_attendu\n";
  }
};


/*PhpDoc: classes
name: Lambert_Conformal_Conic_2SP
title: class Lambert_Conformal_Conic_1SP extends Lambert_Conformal_Conic_2SP implements ProjectedCRS - CRS utilisant la projection Conique Conforme de Lambert, cas tangeant
*/
class Lambert_Conformal_Conic_1SP extends Lambert_Conformal_Conic_2SP implements ProjectedCRS {
  // $projParams doit être conforme au schéma défini pour PROJECTION pour cette classe dans crsregistre.schema.yaml
  function __construct($geodCrsId, $projParams=[], array $limits=[]) {
    parent::__construct($geodCrsId, $projParams, $limits);
  }
}


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test de la classe


echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>TEST Lambert_Conformal_Conic_2SP</title></head><body>\n";
if (!isset($_GET['TEST'])) {
  die("<ul>
<li><a href='?TEST=NTG71'>Test des jeux d'essai fournis dans la note NT/G 71</a>
<li><a href='?TEST=ERREURS'>Tests de la gestion des erreurs</a>
<li><a href='?TEST=pratique'>Tests d'utilisation pratique</a>
</ul>");
}

// Test des jeux d'essai fournis dans la note NT/G 71
elseif ($_GET['TEST']=='NTG71') {
  echo "<pre>Tests sur les jeux d'essai fournis dans la note NT/G 71\n";
  $projLamb = new Lambert_Conformal_Conic_2SP(['a'=> 6378388, 'e'=> 0.081991890], []);
  echo "** Test de ALG0054 calculConstantesCasSecant\n";
  $projLamb->test_calculConstantesCasSecant(0, 0, -0.575958653, -0.785398163, 0, 0,
    ['lambdac'=>0, 'n'=>'-0.630 496 330 0', 'C'=>'-12 453 174.179 5', 'Xs'=>0, 'Ys'=>'-12 453 174.179 5']);
  $projLamb->test_calculConstantesCasSecant(0.07623554539, 1.570796327, 0.869755744, 0.893026801, 150000, 5400000,
    [ 'lambdac'=>'0.076 235 545 39',
      'n'=>'-0.771 642 186 7',
      'C'=>'11 565 915.829 4',
      'Xs'=>'150 000.000 0',
      'Ys'=>'5 400 000.000 0',
    ]);
  echo "\n";

  echo "** Test de ALG0019 calculConstantesCasTangent\n";
  $projLamb->test_calculConstantesCasTangent(0.18112808800, 0.97738438100, 1.0, 0, 0,
    [ 'lambdac'=>'0.181 128 088 00',
      'e'=>'0.081 991 890', 
      'n'=>'0.829 037 572 5', 
      'C'=>'11 464 828.219 2', 
      'Xs'=>0, 'Ys'=>'4 312 250.971 8'
    ]);
  $projLamb->test_calculConstantesCasTangent(0.04079234433, 0.86393798000, 0.9998773400, 600000.0, 200000.0,
    [ 'lambdac'=>'0.040 792 344 33',
      'n'=>'0.760 405 965 8',
      'C'=>'11 603 796.976 0',
      'Xs'=>'600 000.0', 'Ys'=>'5 657 616.671 2'
    ]);
  echo "\n";

  echo "** Test de ALG0003 proj\n";
  $projLamb = new Lambert_Conformal_Conic_2SP(['e'=> 0.0824832568], []);
  $projLamb->test_proj(0.760405966, 11603796.9767, 0.04079234433, 600000, 5657616.674, 0.145512099, 0.872664626,
                       "[1 029 705.081 8, 272 723.851 0]");
  echo "\n";

  echo "** Test de ALG0004 geo\n";
  $projLamb->test_geo(1029705.083, 272723.849, 0.760405966, 11603796.9767, 600000, 5657616.674, 0.04079234433,
                      "[0.145 512 099 25, 0.872 664 625 67]");
  die("FIN OK\n");
}

// Tests de la gestion des erreurs
// URL de test: http://localhost/syscoord/syscoord.inc.php?TEST_PROJCCLAMB=ERREURS
elseif ($_GET['TEST']=='ERREURS') {
  die("A REVOIR");
  echo "<pre>codeConnu(IGNF:LAMB93): ", ProjConiqueConformeLambert::codeConnu('IGNF:LAMB93'), "\n";
  echo "listeCodes:"; print_r(ProjConiqueConformeLambert::listeCodes());
  die("FIN OK");
}

// Tests d'utilisation pratique
// URL de test: http://localhost/syscoord/syscoord.inc.php?TEST_PROJCCLAMB=LAMB93
elseif ($_GET['TEST']=='pratique') {
  echo "<pre>** Test de l'initialisation\n";
  $projLamb = CRS::IGNF('LAMB93');
  $projLamb->afficheConstantes();
  
  echo "** Test de geo\n";
  echo "Coordonnees Pt Geodesique Paris I (d) Quartier Carnot, src: http://geodesie.ign.fr/fiches/pdf/7505601.pdf\n";
  $clamb = array (658557.55, 6860084.00);
  $projLamb = CRS::IGNF('LAMB93');
  echo "geo ($clamb[0], $clamb[1], IGNF:LAMB93) ->";
  $cgeo = $projLamb->geo($clamb);
  printf ("lon=%s / 2°26'07.3236'', lat=%s / 48°50'22.1016''\n",
    degres_sexa($cgeo[0]/180*pi(),'N'), degres_sexa($cgeo[1]/180*pi(),'E'));
  $cproj = $projLamb->proj($cgeo);
  printf ("Verification du calcul inverse: %.2f / %.2f , %.2f / %.2f\n\n", $cproj[0], $clamb[0], $cproj[1], $clamb[1]);

  echo "Coordonnees Pt Geodesique Paris I (d) Quartier Carnot, src: http://geodesie.ign.fr/fiches/pdf/7505601.pdf\n";
  $clamb = array (607260.92, 2426794.61);
  $projLamb = CRS::IGNF('LAMB2E');
  echo "geo ($clamb[0], $clamb[1], IGNF:LAMB2E) ->";
  $cgeo = $projLamb->geo ($clamb);
  printf ("lon=%s / 0° 5' 55.888'', lat=%s / 48°50'22.349''\n",
    degres_sexa($cgeo[0]/180*pi(),'N'), degres_sexa($cgeo[1]/180*pi(),'E'));
  $cproj = $projLamb->proj($cgeo);
  printf ("Verification du calcul inverse: %.2f / %.2f , %.2f / %.2f\n\n", $cproj[0], $clamb[0], $cproj[1], $clamb[1]);

  echo "Coordonnees Pt Geodesique Paris II (b) Esplanade des Invalides, src: http://geodesie.ign.fr/fiches/pdf/7505602.pdf\n"; 
  $clamb = array (649583.441,  6862707.173);
  $projLamb = CRS::IGNF('LAMB93');
  echo "geo ($clamb[0], $clamb[1], IGNF:LAMB93) ->";
  $cgeo = $projLamb->geo ($clamb);
  printf ("lon=%s / 2°18'46.06062''E, lat=%s / 48°51'44.72301''N\n",
    degres_sexa($cgeo[0]/180*pi(),'N'), degres_sexa($cgeo[1]/180*pi(),'E'));
  $cproj = $projLamb->proj($cgeo);
  printf ("Verification du calcul inverse: %.2f / %.2f , %.2f / %.2f\n\n", $cproj[0], $clamb[0], $cproj[1], $clamb[1]);

  echo "Coordonnees de la Tour Eiffel\n";
  $clamb = array (648231, 6862271);
  echo "geo ($clamb[0], $clamb[1], IGNF:LAMB93) ->";
  $cgeo = $projLamb->geo ($clamb);
  printf ("lon=%s / 2°17'39.891''E lat=%s / 48°51'30.216''N\n",
    degres_sexa($cgeo[0]/180*pi(),'N'), degres_sexa($cgeo[1]/180*pi(),'E'));

  $clamb = array (596908, 2428896);
  echo "geo ($clamb[0], $clamb[1], IGNF:LAMB2E) ->";
  $projLamb = CRS::IGNF('LAMB2E');
  $cgeo = $projLamb->geo ($clamb);
  printf ("lon=%f gr / -0.046 792 gr, lat=%f gr / 54.287 179 gr\n", $cgeo[0]/90*100, $cgeo[1]/90*100);

  echo "Autour de la Vierge de Beaune\n";
  $projLamb = CRS::IGNF('LAMB93');
  $cl93 = array(838105, 6661613);
  $cgeo = $projLamb->geo ($cl93);
  printf ("Vierge de Beaune: L93:%.0f,%.0f -> Geo:%s/4°49'8.459''E, %s/47°2'25.573''N\n",
          $cl93[0], $cl93[1], degres_sexa($cgeo[0]/180*pi(),'N'), degres_sexa($cgeo[1]/180*pi(),'E'));
  $cc = $projLamb->proj ($cgeo);
  printf ("calcul inverse: -> L93:%.0f / %.0f, %.0f / %.0f\n",
          $cc[0], $cl93[0], $cc[1], $cl93[1]);

  die("FIN OK");
}


