<?php
{/*PhpDoc:
name:  light.inc.php
title: light.inc.php (v3) - changement simple entre projections définies en WGS84
classes:
functions:
doc: |
  Objectif d'effectuer simplement des changements de projection sur un même ellipsoide.
  Fonctions (long,lat) -> (x,y) et inverse
  Implémente les projections Lambert93, WebMercator, WorldMercator, UTM, Legal et Sinusoidal sur l'ellipsoide IAG_GRS_1980
  par défaut.
  Le Web Mercator est défini dans:
  http://earth-info.nga.mil/GandG/wgs84/web_mercator/(U)%20NGA_SIG_0011_1.0.0_WEBMERC.pdf

  Exemples d'utilisation:
    Lambert93::geo([658557.548, 6860084.001]); // coords géo. (lon,lat) en degrés du point en L93
    Lambert93::proj([2.435368, 48.839473]); // oords Lambert 93 de la position (lon,lat) en degrés
    WebMercator::proj([2.435368, 48.839473]); // coords WebMercator de la position (lon,lat) en degrés
    WorldMercator::proj([2.435368, 48.839473]); // coords WorldMercator de la position (lon,lat) en degrés
    UTM::proj(UTM::zone([2.435368, 48.839473]), [2.435368, 48.839473]); // coords UTM de la position (lon,lat) en degrés
    Legal::proj([2.435368, 48.839473]); // coords légales de la position (lon,lat) en degrés
  
  La projection sinusoidale est unique et équivalente (conserve localement les surfaces) permet de calculer des surfaces; 
journal: |
  22/4/2019:
    Ajout de la projection Sinusoidal
  18/4/2019:
    Ajout de la projection Legal
  20/3/2019:
    Intégration dans le package /coordsys sous le nom crslight.inc.php
  18/3/2019:
    Modification du code pour permettre de calculer les projections sur différents Ellipsoides.
    Cette version ne permet pas d'effectuer un chagt d'ellipsoide.
  3/3/2019:
    fork de ~/html/geometry/coordsys.inc.php, passage en v3
    modification des interfaces pour utiliser systématiquement des positions [X, Y] ou [longitude, latitude] en degrés décimaux
    modification des interfaces d'UTM, la zone est un paramètre supplémentaire, ajout de ma méthode zone()
    La détection de WKT est transférée dans une classe spécifique.
*/}
  
{/*PhpDoc: classes
name:  iEllipsoid
title: interface iEllipsoid - interface de définition d'un ellipsoide
methods:
doc: |
  a : semi-major axis
  b : semi-minor axis
  flattening : f = ( a − b ) / a
  eccentricity : e ** 2 = 1 - (1 - flattening) ** 2
  e ** 2 = 1 - b ** 2 / a ** 2
*/}
interface iEllipsoid {
  static function a(); // semi-major axis
  static function e2(); // eccentricity ** 2
  static function e(); // eccentricity
};
  
{/*PhpDoc: classes
name:  IAG_GRS_1980
title: Class IAG_GRS_1980 - classe statique définissant l'ellipsoide IAG_GRS_1980
methods:
doc: |
*/}
class IAG_GRS_1980 implements iEllipsoid {
  const PARAMS = [
    'title'=> "Ellipsoide GRS (Geodetic Reference System) 1980 défini par l'IAG (Int. Association of Geodesy)",
    'epsg'=> 'EPSG:7019',
    'comment'=> "Ellipsoide international utilisé notamment pour RGF93, Lambert 93, ETRS89, ...",
    'a'=> 6378137.0, // Grand axe de l'ellipsoide - en anglais Equatorial radius - en mètres
    'f' => 1/298.2572221010000, // 1/f: inverse de l'aplatissement = a / (a - b), en: inverse flattening
  ];
    
  static function a() { return self::PARAMS['a']; }
  static function e2() { return 1 - pow(1 - self::PARAMS['f'], 2); }
  static function e() { return sqrt(self::e2()); }
};

/*PhpDoc: classes
name:  Lambert93
title: Class Lambert93 extends IAG_GRS_1980 - définition des fonctions de proj et inverse du Lambert 93
methods:
doc: |
  Lambert93 est un CRS défini sur l'ellipsoide IAG_GRS_1980
*/
class Lambert93 extends IAG_GRS_1980 {
  const c = 11754255.426096; //constante de la projection
  const n = 0.725607765053267; //exposant de la projection
  const xs = 700000; //coordonnées en projection du pole
  const ys = 12655612.049876; //coordonnées en projection du pole
  
  /*PhpDoc: methods
  name:  proj
  title: "static function proj(array $pos): array  - convertit une pos. (longitude, latitude) en degrés déc. en [X, Y]"
  */
  static function proj(array $pos): array {
    list($longitude, $latitude) = $pos;
    // définition des constantes
    $e = self::e(); //première exentricité de l'ellipsoïde

    // pré-calculs
    $lat_rad= $latitude/180*PI(); //latitude en rad
    $lat_iso= atanh(sin($lat_rad))-$e*atanh($e*sin($lat_rad)); //latitude isométrique

    //calcul
    $x = ((self::c * exp(-self::n * $lat_iso)) * sin(self::n * ($longitude-3)/180*pi()) + self::xs);
    $y = (self::ys - (self::c*exp(-self::n*($lat_iso))) * cos(self::n * ($longitude-3)/180*pi()));
    return [$x,$y];
  }
  
  /*PhpDoc: methods
  name:  geo
  title: "static function geo(array $pos): array  - retourne [longitude, latitude] en degrés décimaux"
  */
  static function geo(array $pos): array {
    list($X, $Y) = $pos;
    $e = self::e(); // 0.0818191910428158; //première exentricité de l'ellipsoïde

    // pré-calcul
    $a = (log(self::c/(sqrt(pow(($X-self::xs),2)+pow(($Y-self::ys),2))))/self::n);

    // calcul
    $longitude = ((atan(-($X-self::xs)/($Y-self::ys)))/self::n+3/180*PI())/PI()*180;
    $latitude = asin(tanh(
                  (log(self::c/sqrt(pow(($X-self::xs),2)+pow(($Y-self::ys),2)))/self::n)
                 + $e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*(tanh($a+$e*atanh($e*sin(1))))))))))))))))))))
                 ))/PI()*180;
    return [ $longitude , $latitude ];
  }

  /*PhpDoc: methods
  name:  limits
  title: "static function limits(): GBox - retourne l'espace de définition de la projection en coord. géo."
  */
  static function limits(): array { return [[-7, 41], [13, 51]]; }
};
  
/*PhpDoc: classes
name:  WebMercator
title: Class WebMercator extends IAG_GRS_1980 - définition des fonctions de proj et inverse du Web Mercator
methods:
doc: |
  WebMercator est un CRS défini sur l'ellipsoide IAG_GRS_1980
*/
class WebMercator extends IAG_GRS_1980 {
  /*PhpDoc: methods
  name:  proj
  title: "static function proj(array $pos): array  - convertit une pos. (longitude, latitude) en degrés déc. en [X, Y]"
  */
  static function proj(array $pos): array {
    list($longitude, $latitude) = $pos;
    $lambda = $longitude * pi() / 180.0; // longitude en radians
    $phi = $latitude * pi() / 180.0;  // latitude en radians
	  
    $x = self::a() * $lambda; // (7-1)
    $y = self::a() * log(tan(pi()/4 + $phi/2)); // (7-2)
    return [$x, $y];
  }
    
  /*PhpDoc: methods
  name:  geo
  title: "static function geo(array $pos): array - prend des coordonnées Web Mercator et retourne [longitude, latitude] en degrés"
  */
  static function geo(array $pos): array {
    list($X, $Y) = $pos;
    $phi = pi()/2 - 2*atan(exp(-$Y/self::a())); // (7-4)
    $lambda = $X / self::a(); // (7-5)
    return [ $lambda / pi() * 180.0 , $phi / pi() * 180.0 ];
  }

  /*PhpDoc: methods
  name:  limits
  title: "static function limits(): array - retourne l'espace de définition de la projection en coord. géo."
  */
  static function limits(): array { return [[-180,-85], [180,85]]; }
};

/*PhpDoc: classes
name:  Class Ellipsoid
title: Class Ellipsoid - classe statique permettant de chosir l'ellipsoide pour effcteur les calculs de projection
methods:
doc: |
  La classe porte d'une part les constantes définissant les différents ellipsoides et, d'autre part,
  la définition de l'ellipsoide courant.
  Par défaut utilisation de l'ellipsoide IAG_GRS_1980
  L'ellipsoide de Clarke 1866 peut être sélectionné pour tester l'exemple USGS sur UTM
  D'autres ellipsoides peuvent être ajoutés au besoin.
  https://en.wikipedia.org/wiki/Earth_ellipsoid
*/
class Ellipsoid implements iEllipsoid {
  const DEFAULT = 'IAG_GRS_1980'; // ellipsoide par défaut IAG_GRS_1980
  const PARAMS = [
    'IAG_GRS_1980'=> IAG_GRS_1980::PARAMS,
    'WGS-84'=> [
      'title'=> "Ellipsoide WGS-84 utilisé pour le GPS, quasiment identique à l'IAG_GRS-1980",
      'epsg'=> 'EPSG:4326',
      'a'=> 6378137.0, // Demi grand axe de l'ellipsoide - en anglais Equatorial radius - en mètres
      'f' => 1/298.257223563, // 1/f: inverse de l'aplatissement = a / (a - b), en: inverse flatening
    ],
    'Clarke1866'=> [
      'title'=> "Ellipsoide Clarke 1866",
      'epsg'=> 'EPSG:7008',
      'comment'=> "Ellipsoide utilisé pour le système géodésique North American Datum 1927 (NAD 27) utilisé aux USA",
      'a'=> 6378206.4, // Demi grand axe de l'ellipsoide Clarke 1866
      'b'=> 6356583.8, // Demi petit axe
      'f'=> 1/294.978698214,
    ],
    'UnitSphere'=> [
      'title'=> "Sphère unité",
      'comment'=> "Sphère unité",
      'a'=> 1, // Demi grand axe de l'ellipsoide Clarke 1866
      'b'=> 1, // Demi petit axe
      'f'=> 0, // excentricité = (a - b) / a
    ],
  ];
  
  static $current = self::DEFAULT; // ellipsoide courant, par défaut IAG_GRS_1980
  
  // liste les ellipsoides proposés
  static function available(): array { return self::PARAMS; }
  
  // fournit l'ellipsoide courant
  static function current(): string { return self::$current; }
  
  // Chgt d'ellipsoide
  static function set(string $ellipsoid=self::DEFAULT): void {
    if (isset(self::PARAMS[$ellipsoid]))
      self::$current = $ellipsoid;
    else
      throw new Exception("Erreur dans Ellipsoid::set($ellipsoid): ellipsoide non défini");
  }
  
  // retourne la valeur d'un paramètre stocké pour l'ellipsoide courant
  private static function param(string $name): ?float {
    return isset(self::PARAMS[self::$current][$name]) ? self::PARAMS[self::$current][$name] : null;
  }
  
  static function a() { return self::param('a'); }
  
  static function e2() { return 1 - pow(1 - self::param('f'), 2); }
  
  static function e() { return sqrt(self::e2()); }
};

/*PhpDoc: classes
name:  WorldMercator
title: Class WorldMercator extends Ellipsoid - définition des fonctions de proj et inverse du World Mercator
methods:
doc: |
  La projection WorldMercator peut être définie sur différents ellipsoides.
*/
class WorldMercator extends Ellipsoid {
  const epsilon = 1E-11; // tolerance de convergence du calcul de la latitude
  
  /*PhpDoc: methods
  name:  proj
  title: "static function proj(array $pos): array  - convertit une pos. (longitude, latitude) en degrés déc. en [X, Y]"
  */
  static function proj(array $pos): array {
    list($longitude, $latitude) = $pos;
    $lambda = $longitude * pi() / 180.0; // longitude en radians
    $phi = $latitude * pi() / 180.0;  // latitude en radians
    $e = self::e(); //première exentricité de l'ellipsoïde
    $x = self::a() * $lambda; // (7-6)
    $y = self::a() * log(tan(pi()/4 + $phi/2) * pow((1-$e*sin($phi))/(1+$e*sin($phi)),$e/2)); // (7-7)
    return [$x, $y];
  }
    
  /*PhpDoc: methods
  name:  geo
  title: "static function geo(array $pos): array  - prend des coord. Web Mercator et retourne [longitude, latitude] en degrés"
  */
  static function geo(array $pos): array {
    list($X, $Y) = $pos;
    $t = exp(-$Y/self::a()); // (7-10)
    $phi = pi()/2 - 2 * atan($t); // (7-11)
    $lambda = $X / self::a(); // (7-12)
    $e = self::e();

    $nbiter = 0;
    while (1) {
      $phi0 = $phi;
      $phi = pi()/2 - 2*atan($t * pow((1-$e*sin($phi))/(1+$e*sin($phi)),$e/2)); // (7-9)
      if (abs($phi-$phi0) < self::epsilon)
        return [ $lambda / pi() * 180.0 , $phi / pi() * 180.0 ];
      if ($nbiter++ > 20)
        throw new Exception("Convergence inachevee dans WorldMercator::geo() pour nbiter=$nbiter");
    }
  }

  /*PhpDoc: methods
  name:  limits
  title: "static function limits(): array - retourne l'espace de définition de la projection en coord. géo."
  */
  static function limits(): array { return [[-180,-85], [180,85]]; }
};

/*PhpDoc: classes
name:  UTM
title: Class UTM extends Ellipsoid - définition des fonctions de proj et inverse de l'UTM zone
methods:
doc: |
  La projection UTM est définie par zone correspondant à un fuseau de 6 degrés en séparant l’hémisphère Nord du Sud.
  Soit au total 120 zones (60 pour le Nord et 60 pour le Sud).
  Cette zone est définie sur 3 caractères, les 2 premiers indiquant le no de fuseau et le 3ème N ou S.
  La projection UTM peut être définie sur différents ellipsoides.
  L'exemple USGS utilise l'ellipsoide de Clarke 1866.
*/
class UTM extends Ellipsoid {
  const k0 = 0.9996;
  
  static function lambda0(int $nozone) { return (($nozone-30.5)*6)/180*pi(); } // en radians
  
  static function Xs(): float { return 500000; }
  static function Ys(string $NS): float { return $NS=='S'? 10000000 : 0; }
  
  // distanceAlongMeridianFromTheEquatorToLatitude (3-21)
  static function distanceAlongMeridianFromTheEquatorToLatitude(float $phi): float {
    $e2 = self::e2();
    return (self::a())
         * (   (1 - $e2/4 - 3*$e2*$e2/64 - 5*$e2*$e2*$e2/256)*$phi
             - (3*$e2/8 + 3*$e2*$e2/32 + 45*$e2*$e2*$e2/1024)*sin(2*$phi)
             + (15*$e2*$e2/256 + 45*$e2*$e2*$e2/1024) * sin(4*$phi)
             - (35*$e2*$e2*$e2/3072)*sin(6*$phi)
           );
  }
  
  /*PhpDoc: methods
  name:  zone
  title: "static function zone(array $pos): string  - (longitude, latitude) en degrés -> zone UTM"
  */
  static function zone(array $pos): string {
    return sprintf('%02d',floor($pos[0]/6)+31).($pos[1]>0?'N':'S');
  }
 
  /*PhpDoc: methods
  name:  proj
  title: "static function proj(string $zone, array $pos): array  - (lon, lat) en degrés déc. -> [X, Y] en UTM zone"
  */
  static function proj(string $zone, array $pos): array {
    list($longitude, $latitude) = $pos;
    $nozone = (int)substr($zone, 0, 2);
    $NS = substr($zone, 2);
//    echo "lambda0 = ",$this->lambda0()," rad = ",$this->lambda0()/pi()*180," degres\n";
    $e2 = self::e2();
    $lambda = $longitude * pi() / 180.0; // longitude en radians
    $phi = $latitude * pi() / 180.0;  // latitude en radians
    $ep2 = $e2/(1 - $e2);  // echo "ep2=$ep2 (8-12)\n"; // (8-12)
    $N = self::a() / sqrt(1 - $e2*pow(sin($phi),2)); // echo "N=$N (4-20)\n"; // (4-20)
    $T = pow(tan($phi),2); // echo "T=$T (8-13)\n"; // (8-13)
    $C = $ep2 * pow(cos($phi),2); // echo "C=$C\n"; // (8-14)
    $A = ($lambda - self::lambda0($nozone)) * cos($phi); // echo "A=$A\n"; // (8-15)
    $M = self::distanceAlongMeridianFromTheEquatorToLatitude($phi); // echo "M=$M\n"; // (3-21)
    $M0 = self::distanceAlongMeridianFromTheEquatorToLatitude(0); // echo "M0=$M0\n"; // (3-21)
    $x = (self::k0) * $N * ($A + (1-$T+$C)*pow($A,3)/6 + (5-18*$T+pow($T,2)+72*$C-58*$ep2)*pow($A,5)/120); // (8-9)
//  echo "x = ",($this->k0)," * $N * ($A + (1-$T+$C)*pow($A,3)/6 + (5-18*$T+pow($T,2)+72*$C-58*$ep2)*pow($A,5)/120)\n";
//  echo "x = $x\n";
    $y = (self::k0) * ($M - $M0 + $N * tan($phi) * ($A*$A/2 + (5 - $T + 9*$C +4*$C*$C)
        * pow($A,4)/24 + (61 - 58*$T + $T*$T + 600*$C - 330*$ep2) * pow($A,6)/720));                    // (8-10)
// echo "y = ($this->k0) * ($M - $M0 + $N * tan($phi) * ($A*$A/2 + (5 - $T + 9*$C +4*$C*$C)
//          * pow($A,4)/24 + (61 - 58*$T + $T*$T + 600*$C - 330*$ep2) * pow($A,6)/720))\n";
    $k = (self::k0) * (1 + (1 + $C)*$A*$A/2 + (5 - 4*$T + 42*$C + 13*$C*$C - 28*$ep2)*pow($A,4)/24
         + (61 - 148*$T +16*$T*$T)*pow($A,6)/720);                                                    // (8-11)
    return [$x + self::Xs(), $y + self::Ys($NS)];
  }
    
  /*PhpDoc: methods
  name:  geo
  title: "static function geo(string $zone, array $pos): array  - coord. UTM zone -> [lon, lat] en degrés"
  */
  static function geo(string $zone, array $pos): array {
    list($X, $Y) = $pos;
    $nozone = (int)substr($zone, 0, 2);
    $NS = substr($zone, 2);
    $e2 = self::e2();
    $x = $X - self::Xs();
    $y = $Y - self::Ys($NS);
    $M0 = self::distanceAlongMeridianFromTheEquatorToLatitude(0); // echo "M0=$M0\n"; // (3-21)
    $ep2 = $e2/(1 - $e2); // echo "ep2=$ep2\n"; // (8-12)
    $M = $M0 + $y/self::k0; // echo "M=$M\n"; // (8-20)
    $e1 = (1 - sqrt(1-$e2)) / (1 + sqrt(1-$e2)); // echo "e1=$e1\n"; // (3-24)
    $mu = $M/(self::a() * (1 - $e2/4 - 3*$e2*$e2/64 - 5*$e2*$e2*$e2/256)); // echo "mu=$mu\n"; // (7-19)
    $phi1 = $mu + (3*$e1/2 - 27*pow($e1,3)/32)*sin(2*$mu) + (21*$e1*$e1/16
                - 55*pow($e1,4)/32)*sin(4*$mu) + (151*pow($e1,3)/96)*sin(6*$mu)
                + 1097*pow($e1,4)/512*sin(8*$mu); // echo "phi1=$phi1 radians = ",$phi1*180/pi(),"°\n"; // (3-26)
    $C1 = $ep2*pow(cos($phi1),2); // echo "C1=$C1\n"; // (8-21)
    $T1 = pow(tan($phi1),2); // echo "T1=$T1\n"; // (8-22)
    $N1 = self::a()/sqrt(1-$e2*pow(sin($phi1),2)); // echo "N1=$N1\n"; // (8-23)
    $R1 = self::a()*(1-$e2)/pow(1-$e2*pow(sin($phi1),2),3/2); // echo "R1=$R1\n"; // (8-24)
    $D = $x/($N1*self::k0); // echo "D=$D\n"; 
    $phi = $phi1 - ($N1 * tan($phi1)/$R1) * ($D*$D/2 - (5 + 3*$T1 + 10*$C1 - 4*$C1*$C1 -9*$ep2)*pow($D,4)/24
         + (61 + 90*$T1 + 298*$C1 + 45*$T1*$T1 - 252*$ep2 - 3*$C1*$C1)*pow($D,6)/720); // (8-17)
    $lambda = self::lambda0($nozone) + ($D - (1 + 2*$T1 + $C1)*pow($D,3)/6 + (5 - 2*$C1 + 28*$T1
               - 3*$C1*$C1 + 8*$ep2 + 24*$T1*$T1)*pow($D,5)/120)/cos($phi1); // (8-18)
    return [ $lambda / pi() * 180.0, $phi / pi() * 180.0 ];
  }
  
  /*PhpDoc: methods
  name:  test
  title: "static function test(): void  - test unitaire de la classe en utilisant l'exemple défini dans le rapport USGS"
  */
  static function test(): void {
    echo "Exemple du rapport USGS pp 269-270 utilisant l'Ellipsoide de Clarke\n";
    Ellipsoid::set('Clarke1866');
    $pt = [-73.5, 40.5];
    echo "phi=",radians2degresSexa($pt[1]/180*PI(),'N'),", lambda=", radians2degresSexa($pt[0]/180*PI(),'E'),"\n";
    $utm = UTM::proj('18N', $pt);
    echo "UTM: X=$utm[0] / 127106.5, Y=$utm[1] / 4,484,124.4\n";

    $verif = UTM::geo('18N', $utm);
    echo "phi=",radians2degresSexa($verif[1]/180*PI(),'N')," / ",radians2degresSexa($pt[1]/180*PI(),'N'),
         ", lambda=", radians2degresSexa($verif[0]/180*PI(),'E')," / ", radians2degresSexa($pt[0]/180*PI(),'E'),"\n";
    //die("FIN ligne ".__LINE__);
    Ellipsoid::set();
  }
};

/*PhpDoc: classes
name:  Legal
title: class Legal - projection légale en métropole et dans les DROM
methods:
doc: |
  Partant du constat que les projections légales en métropole et DROM ont des espaces de coordonnées projetées
  ne s'intersectant pas, il est possible de localiser un point par ses coord. en projection légale.
  Cette classe permet donc de passer de projection légale en coord. géo. et vice-versa
  Les Y dissocient les espaces (en Km):
    GF: 400 -> 1000
    GP,MQ: 1500 -> 2100
    FXX: 6000 -> 7200
    RE: 7200 -> 8000
    MYT: 8300 -> 8800
*/
class Legal {
  // espace des coordonnées projetées par zone et par projection sous la forme [west, south, east, north]
  const ProjBoundingRect = [
    'GF'=> [
      'UTM-22N'=> [ 123000, 425000,  678000, 978000 ], // projection légale
      'UTM-21N'=> [ 789000, 427000, 1342000, 980000 ], // autre projection susceptible d'être utilisée
    ],
    'GPMQ'=> [
      'UTM-20N'=> [ 520000, 1556000, 1085000, 2057000 ],
    ],
    'FXX'=> [
      'L93'=> [ -275343, 6022960, 1302694, 7162654 ],
    ],
    'RE'=> [
      'UTM-40S'=> [ -38318, 7262645, 625986, 7979567 ],
    ],
    'YT'=> [
      'UTM-38S'=> [ 504550, 8562124, 532608, 8603057 ],
    ],
  ];
  // espace des coordonnées géographiques par zone sous la forme [west, south, east, north]
  const GeoBoundingRect = [
    'GF'=> [ -54.61, 2.11, -49.41, 8.84 ],
    'GPMQ'=> [ -62.82, 14.11, -57.53, 18.57 ],
    'FXX'=> [ -10.10, 41.24, 10.22, 51.56 ],
    'RE'=> [ 51.79, -24.74, 58.23, -18.28 ],
    'YT'=> [ 43.48, -14.53, 46.69, -11.13 ],
  ];
  
  // retourne la projection en fonction des coord. projetées
  static function projectionOfProjected(array $pos): string {
    foreach (self::ProjBoundingRect as $zone => $projbr) {
      foreach ($projbr as $proj => $br) {
        if (($pos[1] >= $br[1]) && ($pos[1] <= $br[3])) {
          if ($zone <> 'GF')
            return $proj;
          elseif ($pos[0] > self::BoundingRect['GF']['UTM-21N'][0])
            return 'UTM-21N';
          else
            return 'UTM-22N';
        }
      }
    }
    return '';
  }
  
  // retourne la zone en fonction des coord. géo.
  static function zone(array $pos): string {
    foreach (self::GeoBoundingRect as $zone => $geobr) {
      if (($pos[0] >= $geobr[0]) && ($pos[1] >= $geobr[1]) && ($pos[0] <= $geobr[2]) && ($pos[1] <= $geobr[3]))
        return $zone;
    }
    return '';
  }
  
  /*PhpDoc: methods
  name:  proj
  title: "static function proj(array $pos): array  - convertit une pos. (longitude, latitude) en degrés déc. en [X, Y]"
  */
  static function proj(array $pos): array {
    //echo "Legal::proj($pos[0], $pos[1])";
    if (!($zone = self::zone($pos)))
      throw new Exception("position ($pos[0], $pos[1]) hors cadre");
    $projection = array_keys(self::ProjBoundingRect[$zone])[0];
    if ($projection == 'L93')
      return Lambert93::proj($pos);
    $utmZone = substr($projection, 4);
    return UTM::proj($utmZone, $pos);
  }
  
  
  /*PhpDoc: methods
  name:  geo
  title: "static function geo(array $pos): array  - retourne [longitude, latitude] en degrés décimaux"
  */
  static function geo(array $pos): array {
    if (!($projection = self::projectionOfProjected($pos)))
      throw new Exception("position ($pos[0], $pos[1]) hors cadre");
    if ($projection == 'L93')
      return Lambert93::geo($pos);
    $utmZone = substr($projection, 4);
    return UTM::geo($utmZone, $pos);
  }
};


/*PhpDoc: classes
name:  Sinusoidal
title: class Sinusoidal extends IAG_GRS_1980 - projection Sinusoidale
methods:
doc: |
  Le calcul de la projection sinusoidale est très simple dans sa version sphérique.
  C'est une projection valable sur la Terre entière et équivalente.
  Elle est donc bien adaptée pour calculer des surfaces à partir de coordonnée géographiques.
*/
class Sinusoidal extends Ellipsoid {
  static $longitude0 = 0;
  
  static function setLongitude0(float $longitude0=0): void { self::$longitude0 = $longitude0; }
  
  /*PhpDoc: methods
  name:  proj
  title: "static function proj(array $pos): array  - convertit une pos. (longitude, latitude) en degrés déc. en [X, Y]"
  */
  static function proj(array $pos): array {
    list($longitude, $latitude) = $pos;
    $lambda = ($longitude - self::$longitude0) * pi() / 180.0; // longitude en radians
    $phi = $latitude * pi() / 180.0;  // latitude en radians
    $x = self::a() * $lambda * cos($phi); // (30-1)
    $y = self::a() * $phi; // (30-2)
    //echo "Sinusoidal::proj([$pos[0],$pos[1]]) <- [$x, $y]<br>\n";
    return [$x, $y];
  }
  
  /*PhpDoc: methods
  name:  geo
  title: "static function geo(array $pos): array  - prend des coord. Sinusoidales et retourne [longitude, latitude] en degrés"
  */
  static function geo(array $pos): array {
    list($X, $Y) = $pos;
    $phi = $Y / self::a(); // (30-6)
    $lambda = $X / (self::a() * cos($phi)); // (30-7)
    return [ $lambda / pi() * 180.0 + self::$longitude0 , $phi / pi() * 180.0 ];
  }

  /*PhpDoc: methods
  name:  limits
  title: "static function limits(): array - retourne l'espace de définition de la projection en coord. géo."
  */
  static function limits(): array { return [[-180,-90], [180,90]]; }

  /*PhpDoc: methods
  name:  limits
  title: "static function projectedLimits(): array - retourne l'espace de définition de la projection en coord. projetées"
  */
  static function projectedLimits(): array {
    $w = self::proj([-180,0]);
    $e = self::proj([180,0]);
    $s = self::proj([0,-90]);
    $n = self::proj([0,90]);
    return [[$w[0],$s[1]], [$e[0],$n[1]]];
  }
  
  /*PhpDoc: methods
  name:  test
  title: "static function test(): void  - test unitaire de la classe en utilisant l'exemple défini dans le rapport USGS"
  */
  static function test(): void {
    echo "Exemple du rapport USGS p. 365\n";
    Ellipsoid::set('UnitSphere');
    self::setLongitude0(-90);
    $cgeo = [-75, -50];
    $psin = self::proj($cgeo);
    echo "Sinusoidal::proj = "; print_r($psin); echo "/ [0.1682814, -0.8726646]\n";
    $cgeo2 = self::geo($psin);
    echo "Sinusoidal::geo = "; print_r($cgeo2); echo "/"; print_r($cgeo);
    self::setLongitude0(0);
    Ellipsoid::set();
    
    foreach ([[0,0], [-180,-90], [-180,-85], [180,90], [180,85]] as $pos)
      echo "proj([",implode(',', $pos),"]) = ",implode(',', self::proj($pos)),"\n";
    echo "\n";
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return;


/*PhpDoc: functions
name: radians2degresSexa
title: function radians2degresSexa(float $r, string $ptcardinal='', float $dr=0)
doc: |
  Transformation d'une valeur en radians en une chaine en degres sexagesimaux
  si ptcardinal est fourni alors le retour respecte la notation avec point cardinal
  sinon c'est la notation signee qui est utilisee
  dr est la precision de r
*/
function radians2degresSexa(float $r, string $ptcardinal='', float $dr=0) {
  $signe = '';
  if ($r < 0) {
    if ($ptcardinal) {
      if ($ptcardinal == 'N')
        $ptcardinal = 'S';
      elseif ($ptcardinal == 'E')
        $ptcardinal = 'W';
      elseif ($ptcardinal == 'S')
        $ptcardinal = 'N';
      else
        $ptcardinal = 'E';
    } else
      $signe = '-';
    $r = - $r;
  }
  $deg = $r / pi() * 180;
  $min = ($deg - floor($deg)) * 60;
  $sec = ($min - floor($min)) * 60;
  if ($dr == 0) {
    return $signe.sprintf("%d°%d'%.3f''%s", floor($deg), floor($min), $sec, $ptcardinal);
  } else {
    $dr = abs($dr);
    $ddeg = $dr / pi() * 180;
    $dmin = ($ddeg - floor($ddeg)) * 60;
    $dsec = ($dmin - floor($dmin)) * 60;
    $ret = $signe.sprintf("%d",floor($deg));
    if ($ddeg > 0.5) {
      $ret .= sprintf(" +/- %d ° %s", round($ddeg), $ptcardinal);
      return $ret;
    }
    $ret .= sprintf("°%d",floor($min));
    if ($dmin > 0.5) {
      $ret .= sprintf(" +/- %d ' %s", round($dmin), $ptcardinal);
      return $ret;
    }
    $f = floor(log($dsec,10));
    $fmt = '%.'.($f<0 ? -$f : 0).'f';
    return $ret.sprintf("'$fmt +/- $fmt'' %s", $sec, $dsec, $ptcardinal);
  }
};

echo "<html><head><meta charset='UTF-8'><title>coordsys</title></head><body><pre>";

//echo "x",UTM::zone([-179,0]),"x\n";

if (1) {
  Sinusoidal::test();
}

if (1) {
  UTM::test();
}

$refs = [
  'Paris I (d) Quartier Carnot'=>[
    'src'=> 'http://geodesie.ign.fr/fiches/pdf/7505601.pdf',
    'L93'=> [658557.548, 6860084.001],
    'LatLong'=> [48.839473, 2.435368],
    'dms'=> ["48°50'22.1016''N", "2°26'07.3236''E"],
    'WebMercator'=> [271103.889193, 6247667.030696],
    'UTM-31N'=> [458568.90, 5409764.67],
  ],
  'FORT-DE-FRANCE V (c)' =>[
    'src'=>'http://geodesie.ign.fr/fiches/pdf/9720905.pdf',
    'UTM'=> ['20N'=> [708544.10, 1616982.70]],
    'dms'=> ["14° 37' 05.3667''N", "61° 03' 50.0647''W" ],
  ],
  'SAINT-DENIS C (a)' =>[
    'src'=>'http://geodesie.ign.fr/fiches/pdf/97411C.pdf',
    'UTM'=> ['40S'=> [338599.03, 7690489.04]],
    'dms'=> ["20° 52' 43.6074'' S", "55° 26' 54.2273'' E" ],
  ],
];

Lambert93::geo([658557.548, 6860084.001]);
Lambert93::proj([2.435368, 48.839473]);
WebMercator::proj([2.435368, 48.839473]);
WorldMercator::proj([2.435368, 48.839473]);
UTM::proj(UTM::zone([2.435368, 48.839473]), [2.435368, 48.839473]);

foreach ($refs as $name => $ref) {
  echo "\nCoordonnees Pt Geodesique <a href='$ref[src]'>$name</a>\n";
  if (isset($ref['L93'])) {
    $clamb = $ref['L93'];
    echo "geo ($clamb[0], $clamb[1], L93) ->";
    $cgeo = Lambert93::geo ($clamb);
    printf ("phi=%s / %s lambda=%s / %s\n",
      radians2degresSexa($cgeo[1]/180*PI(),'N', 1/180*PI()/60/60/10000), $ref['dms'][0],
      radians2degresSexa($cgeo[0]/180*PI(),'E', 1/180*PI()/60/60/10000), $ref['dms'][1]);
    $cproj = Lambert93::proj($cgeo);
    printf ("Verification du calcul inverse: %.2f / %.2f , %.2f / %.2f\n\n",
              $cproj[0], $clamb[0], $cproj[1], $clamb[1]);

    $cwm = WebMercator::proj($cgeo);
    printf ("Coordonnées en WebMercator: %.2f / %.2f, %.2f / %.2f\n",
              $cwm[0], $ref['WebMercator'][0], $cwm[1], $ref['WebMercator'][1]);
  
    $cgeo = Legal::geo($clamb);
    printf ("Legal/ phi=%s / %s lambda=%s / %s\n",
      radians2degresSexa($cgeo[1]/180*PI(),'N', 1/180*PI()/60/60/10000), $ref['dms'][0],
      radians2degresSexa($cgeo[0]/180*PI(),'E', 1/180*PI()/60/60/10000), $ref['dms'][1]);
    $cproj = Lambert93::proj($cgeo);
    printf ("Verification du calcul inverse: %.2f / %.2f , %.2f / %.2f\n\n",
              $cproj[0], $clamb[0], $cproj[1], $clamb[1]);
    
// UTM
    $zone = UTM::zone($cgeo);
    echo "\nUTM:\nzone=$zone\n";
    $cutm = UTM::proj($zone, $cgeo);
    printf ("Coordonnées en UTM-$zone: %.2f / %.2f, %.2f / %.2f\n", $cutm[0], $ref['UTM-31N'][0], $cutm[1], $ref['UTM-31N'][1]);
    $verif = UTM::geo($zone, $cutm);
    echo "Verification du calcul inverse:\n";
    printf ("phi=%s / %s lambda=%s / %s\n",
      radians2degresSexa($verif[1]/180*PI(),'N', 1/180*PI()/60/60/10000), $ref['dms'][0],
      radians2degresSexa($verif[0]/180*PI(),'E', 1/180*PI()/60/60/10000), $ref['dms'][1]);
  }
  elseif (isset($ref['UTM'])) {
    $zone = array_keys($ref['UTM'])[0];
    $cutm0 = $ref['UTM'][$zone];
    $cgeo = UTM::geo($zone, $cutm0);
    printf ("phi=%s / %s lambda=%s / %s\n",
      radians2degresSexa($cgeo[1]/180*PI(),'N'), $ref['dms'][0],
      radians2degresSexa($cgeo[0]/180*PI(),'E'), $ref['dms'][1]);
    $cutm = UTM::proj($zone, $cgeo);
    printf ("Coordonnées en UTM-%s: %.2f / %.2f, %.2f / %.2f\n", $zone, $cutm[0], $cutm0[0], $cutm[1], $cutm0[1]);

    $cgeo = Legal::geo($cutm0);
    printf ("Legal/ phi=%s / %s lambda=%s / %s\n",
      radians2degresSexa($cgeo[1]/180*PI(),'N'), $ref['dms'][0],
      radians2degresSexa($cgeo[0]/180*PI(),'E'), $ref['dms'][1]);
    $cutm = Legal::proj($cgeo);
    printf ("Coordonnées en UTM-%s: %.2f / %.2f, %.2f / %.2f\n", $zone, $cutm[0], $cutm0[0], $cutm[1], $cutm0[1]);
  }
}
