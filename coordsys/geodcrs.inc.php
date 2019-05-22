<?php
/*PhpDoc:
name: geodcrs.inc.php
title: geodcrs.inc.php - définit les classes GeodeticCRS et GeodeticCrsLatLon, ainsi que l'interface ProjectedCRS
classes:
functions:
doc: |
journal: |
  22-24/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
    - ajout chgt de système + différents tests
includes: [datum.inc.php, full.inc.php]
*/
require_once __DIR__.'/datum.inc.php';

/*PhpDoc: classes
name: GeodeticCRS
title: class GeodeticCRS extends GeodeticDatum - geodetic coordinate reference system (4.1.18)
methods:
doc: |
  coordinate reference system (4.1.7) based on a geodetic datum(4.1.19)
  [SOURCE: ISO 19111:2007, 4.23]
*/
class GeodeticCRS extends GeodeticDatum {
  protected $geodCrsId;
  protected $primem = 0;
  protected $geodLimits; // domaine de définition éventuel 

  // $params est soit un code définissant un GeodeticCRS dans le registre CRS soit un array avec des paramètres
  // d'initialisation du GeodeticDatum
  function __construct($params) {
    if (is_array($params)) {
      $this->geodCrsId = 'TEST';
      parent::__construct($params);
      return;
    }
    elseif (!is_string($params))
      throw new Exception("Erreur dans GeodeticCRS::__construct() - params incorrect");
    $geodCrsId = $params;
    $this->geodCrsId = $geodCrsId;
    if (!($params = self::registre('CRS', $geodCrsId)))
      throw new Exception("Unknown GeodeticCRS $geodCrsId");
    //echo "GeodeticCRS params="; print_r($params);
    parent::__construct($params['DATUM']);
    if (isset($params['PRIMEM'])) {
      foreach ($params['PRIMEM'] as $key => $value)
        if ($key <> 'AUTHORITY') break;
      $this->primem = self::toRadian($value);
    }
    if (isset($params['limits']))
      $this->geodLimits = $params['limits'];
  }
  
  function __toString(): string { return 'GeodeticCRS::'.$this->geodCrsId; }
  
  // retourne le domaine de définition du CRS comme array des 2 positions SW et NE
  function limits(): array {
    // Par défaut
    $limits = [ [-180, -90], [180, 90] ];
    // Si les limites sont définies alors elles priment sur les valeurs par défaut
    if (isset($this->limits['westlimit']))
      $limits[0][0] = self::toRadian($this->limits['westlimit']) / pi() * 180;
    if (isset($this->limits['southlimit']))
      $limits[0][1] = self::toRadian($this->limits['southlimit']) / pi() * 180;
    if (isset($this->limits['eastlimit']))
      $limits[0][0] = self::toRadian($this->limits['eastlimit']) / pi() * 180;
    if (isset($this->limits['northlimit']))
      $limits[0][1] = self::toRadian($this->limits['northlimit']) / pi() * 180;
    return $limits;
  }

  // Pour un GeodeticCRS proj() et geo() sont formellement définis come l'identité
  function proj(array $lonLatDeg): array { return $lonLatDeg; }
  function geo(array $lonLatDeg): array { return $lonLatDeg; }
  
  // interprète les diféfrentes formes possibles pour définir une coord. géo. et transforme en radians
  static function toRadian($coordGeo): float {
    if (is_numeric($coordGeo))
      return $coordGeo/180 * pi();
    elseif (is_string($coordGeo) && preg_match('!^(-?[\d.]+) gr$!', $coordGeo, $matches))
      return $matches[1] / 200 * pi();
    elseif (is_string($coordGeo) && preg_match("!^(\\d+)[^\\d]+(\\d\\d)'(([\\d.]+)'')?([EWNS])$!", $coordGeo, $matches)) {
      //echo "toRadian($coordGeo)\n"; print_r($matches);
      $val = (in_array($matches[5], ['N','E']) ? 1 : -1 ) * ($matches[1] + $matches[2]/60 + $matches[4]/60/60);
      return $val / 180 * pi();
    }
    else
      throw new Exception("GeodeticCRS::toRadian($coordGeo)");
  }
  
  /*PhpDoc: methods
  name:  geo2wgs84cartesian
  title: "function geo2wgs84cartesian(array $cgeo, float $h_e=0) - tranforme coord. geo. -> coord. cartesiennes WGS84"
  doc: |
    tranforme un couple de coord. geo. (lon,lat) en degrés definies dans le systeme géodésique courant
    en un triplet de coord. cartesiennes WGS84
    algorithme: ALG0009 de la note NT/G 80
  */
  function geo2wgs84cartesian(array $cgeo, float $h_e=0) {
    if (!$this->toWgs84)
      throw new Exception ("toWgs84 non défini pour $this dans GeodeticCRS::geo2wgs84cartesian");

    $towgs84 = $this->toWgs84;
    $lambda = self::toRadian($cgeo[0] + $this->primem);
    $phi = self::toRadian($cgeo[1]);
    $N = $this->grande_normale ($phi);
    return [
      ($N + $h_e) * cos($phi) * cos($lambda)    + $towgs84[0],
      ($N + $h_e) * cos($phi) * sin($lambda)    + $towgs84[1],
      ($N * (1-$this->e2()) + $h_e) * sin($phi) + $towgs84[2],
    ];
  }
  function test_geo2wgs84cartesian(float $phi, float $lambda, float $h_e, string $resultat_attendu) {
    $this->toWgs84 = [0, 0, 0, 0, 0, 0, 0];
    $ccart = $this->geo2wgs84cartesian([$lambda/pi()*180, $phi/pi()*180], $h_e);
    echo "geo2wgs84cartesian(phi=$phi, lambda=$lambda, h_e=$h_e) -> $ccart[0],$ccart[1],$ccart[2] / $resultat_attendu\n";
  }
  
  /*PhpDoc: methods
  name:  wgs84cartesian2geo
  title: "function wgs84cartesian2geo($ccart) - tranforme des coord. cartesiennes WGS84 en coord. geo. lon,lat dd"
  doc: |
    transforme un triplet de coord cartesiennes en WGS84 en un couple (lon,lat) de coord geo. en degrés décimaux
    dans le GeodeticCRS courant
    algorithme: ALG0012 de la note NT/G 80
  */
  function wgs84cartesian2geo(array $ccart): array {
    if (!$this->toWgs84)
      throw new Exception ("toWgs84 non défini pour $this dans GeodeticCRS::wgs84cartesian2geo");

    $toWgs84 = $this->toWgs84;
    $e2 = $this->e2();
    $a = $this->a();
    list($X, $Y, $Z) = array($ccart[0] - $toWgs84[0], $ccart[1] - $toWgs84[1], $ccart[2] - $toWgs84[2]); 
    $lambda = atan($Y / $X);
    $fi = atan($Z / sqrt($X*$X+$Y*$Y) / (1-$a*$e2/sqrt($X*$X+$Y*$Y+$Z*$Z)));
    // echo "fi   = $fi\n";
    for ($j=0; $j < 100; $j++) {
      $fi1 = $fi;
      $fi = atan($Z / sqrt($X*$X+$Y*$Y) / (1 - $a*$e2*cos($fi1)/sqrt($X*$X+$Y*$Y)/sqrt(1-$e2*sin($fi1)*sin($fi1))));
      // echo "fi $j = $fi\n";
      if (abs($fi - $fi1) < self::EPSILON)
        return [
          ($lambda - $this->primem) / pi() * 180.0,
          $fi / pi() * 180,
        ];
    }
    throw new Exception ("Erreur de convergence dans GeodeticCRS::wgs84cartesian2geo");
  }
  public function test_wgs84cartesian2geo(float $X, float $Y, float $Z, string $resultat_attendu) {
    $this->toWgs84 = [0, 0, 0, 0, 0, 0, 0];
    $cgeo = $this->wgs84cartesian2geo([$X, $Y, $Z]);
    $cgeo = [ $cgeo[1] / 180 * pi(), $cgeo[0] / 180 * pi()];
    echo "wgs84cartesian2geo(X=$X, Y=$Y, Z=$Z) -> $cgeo[0],$cgeo[1] / $resultat_attendu\n";
  }
  
  // convertit des coord. géo. dans le CRS courant en coord. WGS84 LonLat
  function wgs84LonLatDd(array $pos): array {
    if (!$this->toWgs84)
      throw new Exception ("toWgs84 non défini pour $this dans GeodeticCRS::wgs84()");
    if ($this->toWgs84isZero())
      return $this->geo($pos);
    else
      return CRS::S('WGS84LonLatDd')->wgs84cartesian2geo($this->geo2wgs84cartesian($this->geo($pos)));
  }
  
  // convertit des coord. géo. WGS84 LonLat en coord. dans le CRS courant
  function fromWgs84LonLatDd(array $wgs84Pos): array {
    if (!$this->toWgs84)
      throw new Exception ("toWgs84 non défini pour $this dans GeodeticCRS::fromWgs84()");
    if ($this->toWgs84isZero())
      return $this->proj($wgs84Pos);
    else
      return $this->proj($this->wgs84cartesian2geo(CRS::S('WGS84LonLatDd')->geo2wgs84cartesian($wgs84Pos)));
  }
  
  // change $pos définie dans le CRS courant vers le CRS défini $destCrs
  // Si les 2 CRS sont défini sur le même GeodeticDatum, on passe par les coord. géo dans ce datum
  // Sinon, on passe entre les 2 systèmes géodésiques par les coord cartésiennes WGS84
  function chg(array $pos, GeodeticCRS $destCrs): array {
    if ($destCrs->datumId == $this->datumId) {
      return $destCrs->proj($this->geo($pos));
    }
    else {
      return $destCrs->proj($destCrs->wgs84cartesian2geo($this->geo2wgs84cartesian($this->geo($pos))));
    }
  }
};


// Agit comme un 
/*PhpDoc: classes
name: GeodeticCrsLatLon
title: class GeodeticCrsLatLon extends GeodeticCRS - système de coordonnées géodésique en (lat,lon)
methods:
doc: |
  se comporte comme un système projeté qui intervetit les coordonnées du système dont il hérite
*/
class GeodeticCrsLatLon extends GeodeticCRS {

  // $params est soit un code définissant un GeodeticCRS dans le registre CRS soit un array avec des paramètres
  // d'initialisation du GeodeticDatum
  function __construct($params) { parent::__construct($params); }
  
  function __toString(): string { return 'GeodeticCRS::'.$this->geodCrsId.'LatLon'; }
  
  // retourne le domaine de définition du CRS comme array des 2 positions SW et NE
  function limits(): array { return parent::limits(); }

  // proj et geo inverse l'ordre des coordonnées
  function proj(array $latLonDeg): array { return [$latLonDeg[1], $latLonDeg[0]]; }
  function geo(array $latLonDeg): array { return [$latLonDeg[1], $latLonDeg[0]]; }
};


/*PhpDoc: classes
name: ProjectedCRS
title: interface ProjectedCRS - interface for projected coordinate reference system
methods:
doc: |
  A projected coordinate reference system (4.1.31) is coordinate reference system (4.1.7) derived from a two-dimensional
  geodetic coordinate reference system (4.1.18) by applying a map projection (4.1.25)
  [SOURCE: ISO 19111:2007, 4.39]
*/
interface ProjectedCRS {
  // $geodCrsId est soit l'identifiant d'un GeodeticCRS dans le registre CRS,
  // soit un array de paramètres permettant d'initialiser un GeodeticCRS
  // $projParams doit être un array conforme au schéma défini pour PROJECTION pour la classe dans crsregistre.schema.yaml
  // sauf pour UTM
  function __construct($geodCrsId, $projParams=[], array $limits=[]);

  function __toString(): string;
  
  // retourne le domaine de définition de la projection comme array des 2 positions SW et NE
  // peut être défini dans les paramètres du CRS, ou dépendre de la classe, ou par défaut être défini pour la Terre entière
  // Renvoie des coordonnées (lon,lat) WGS84
  function limits(): array;
  
  /*PhpDoc: methods
  name:  proj
  title: "public function proj(array $lonLatDeg): array - Projète des coordonnées géographiques en Lon,Lat degrés décimaux"
  */
  public function proj(array $lonLatDeg): array;

  /*PhpDoc: methods
  name:  geo
  title: "function geo(array $xy): array - projection inverse de coord. projetées en coord. géo. en Lon,Lat degrés décimaux"
  */
  public function geo(array $xy): array;
  
  /*PhpDoc: methods
  name:  chg
  title: "function chg(array $pos, Datum $destCrs): array - change $pos définie dans le CRS courant vers le CRS défini par $destCrs"
  */
  function chg(array $pos, GeodeticCRS $destCrs): array;
}


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test de la classe


require_once __DIR__.'/full.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>TEST GeodeticCRS</title></head><body>\n";

if (!isset($_GET['TEST'])) {
  die("<ul>
<li><a href='?TEST=NTG80'>Test des jeux d'essai fournis dans la note NT/G 80</a></li>
<li><a href='?TEST=chg'>Test de GeodeticCRS::chg(), GeodeticCRS::wgs84() et  GeodeticCRS::fromWgs84()</a></li>
<li><a href='?TEST=pratique'>Test pratique</a></li>
</ul>\n");
}
  
echo "<pre>\n";

// Test des jeux d'essai fournis dans la note NT/G 80 
if ($_GET['TEST']=='NTG80') {
  $geodcrs = new GeodeticCRS(['a'=> 6378249.2, 'e'=> 0.08248325679]);
  echo "** Test de ALG0009 Transf. coord. geo -> coord. cart.\n";
  $geodcrs->test_geo2wgs84cartesian(0.02036217457, 0.01745329248, 100, "6 376 064.695 5, 111 294.623 0, 128 984.725 0");
  $geodcrs->test_geo2wgs84cartesian(0.00000000000, 0.00290888212,  10, "6 378 232.214 9, 18 553.578 0, 0");
                
  $geodcrs->test_wgs84cartesian2geo(6376064.695, 111294.623, 128984.725, "[0.020 362 174 57, 0.017 453 292 48]");
  $geodcrs->test_wgs84cartesian2geo(6378232.215, 18553.578, 0, "[0, 0.002 908 882 12]");
  $geodcrs->test_wgs84cartesian2geo(6376897.537, 37099.705, -202730.907, "[-0.031 997 703 01, 0.005 817 764 23]");
  die ("FIN OK ligne ".__LINE__."\n");
}

function fract(float $val): float { return $val - floor($val); }
function showgeo(array $lonlat): string {
  $EW = ($lonlat[0] >= 0) ? 'E' : 'W';
  if ($lonlat[0] < 0)
    $lonlat[0] = - $lonlat[0];
  $lon['d'] = floor($lonlat[0]);
  $lon['m'] = floor(fract($lonlat[0]) * 60);
  $lon['s'] = fract(fract($lonlat[0]) * 60) * 60;
  $NS = ($lonlat[1] >= 0) ? 'N' : 'S';
  if ($lonlat[1] < 0)
    $lonlat[1] = - $lonlat[1];
  $lat['d'] = floor($lonlat[1]);
  $lat['m'] = floor(fract($lonlat[1]) * 60);
  $lat['s'] = fract(fract($lonlat[1]) * 60) * 60;
  return sprintf("%dd%d'%f''%s - %dd%d'%f''%s", $lon['d'], $lon['m'], $lon['s'], $EW, $lat['d'], $lat['m'], $lat['s'], $NS);
}

function str2geo(string $geo): array {
  if (!preg_match("!^(\\d+)d(\\d+)'([\\d.]+)''([EW]) - (\\d+)d(\\d+)'([\\d.]+)''([NS])$!", $geo, $matches))
    throw new Exception("No match on $geo");
  return [
    ($matches[4]=='E' ? 1 : -1) * ($matches[1] + $matches[2]/60) + $matches[3]/60/60,
    ($matches[8]=='N' ? 1 : -1) * ($matches[5] + $matches[6]/60) + $matches[7]/60/60,
  ];
}

if ($_GET['TEST']=='chg') {
  echo "</pre><h2>Test du changement de coordonnées entre systèmes géodésiques distincts</h2><pre>\n";
  $amers = [
    "Pt Geodesique Paris I (d) Quartier Carnot" => [
      'source'=> 'http://geodesie.ign.fr/fiches/pdf/7505601.pdf',
      'LAMB93'=> [658557.55, 6860084.00],
      'RGF93GEO'=> "2d26'07.32390''E - 48d50'22.10171''N",
      'NTF'=>    "0d05'55.888''E - 48°50'22.349''",
      'LAMB2E'=> [607260.92, 2426794.61],
    ],
  ];
  
  foreach ($amers as $title => $amer) {
    echo "</pre><h3>$title</h3><pre>\n";
    echo "RGF93GEO: ",showgeo(CRS::IGNF('LAMB93')->geo($amer['LAMB93']))," / $amer[RGF93GEO]\n";
    echo "NTF="; print_r(CRS::S('NTFLonLatDd'));
    echo "NTF: ",showgeo(CRS::IGNF('LAMB93')->chg($amer['LAMB93'], CRS::S('NTFLonLatDd'))), " / $amer[NTF]\n";
    echo "IGNF:LAMB2E="; print_r(CRS::IGNF('LAMB2E'));
    echo "LAMB2E: ",json_encode(CRS::IGNF('LAMB93')->chg($amer['LAMB93'], CRS::IGNF('LAMB2E'))),
         " / ",json_encode($amer['LAMB2E']),"\n";
    echo "ED50: ",showgeo(CRS::IGNF('LAMB93')->chg($amer['LAMB93'], CRS::S('ED50LonLatDd'))), "\n";
    try {
      echo "NAD27: ",showgeo(CRS::IGNF('LAMB93')->chg($amer['LAMB93'], CRS::S('NAD27LonLatDd'))), "\n";
    }
    catch(Exception $e) {
      echo "Exception OK pour NAD27: ",$e->getMessage(),"\n";
    }
    
    // Tests wgs84() et fromWgs84()
    $wgs84Pos = CRS::IGNF('LAMB93')->wgs84LonLatDd($amer['LAMB93']);
    echo "NTF: ",showgeo(CRS::S('NTFLonLatDd')->fromWgs84LonLatDd($wgs84Pos)), " / $amer[NTF]\n";
    echo "LAMB2E: ",json_encode(CRS::IGNF('LAMB2E')->fromWgs84LonLatDd($wgs84Pos))," / ",json_encode($amer['LAMB2E']),"\n";
    
  }
  die("OK ligne ".__LINE__."<br>\n");
}
  
// Test de la méthode toRadian()
elseif ($_GET['TEST']=='toRadian') {
  echo "<pre>\n** Test de la méthode toRadian()\n";
  echo "radians(2° 20' 14.025''N) -> ",
          radians("2° 20' 14.025''N")," / 0.040792344332",
      " -> degsex -> ",degres_sexa(radians("2° 20' 14.025''N")),"\n";
  echo "radians(2° 20' 14.025''W) -> ",
         radians("2° 20' 14.025''W")," / -0.040792344332",
       " -> degsex -> ",degres_sexa(radians("2° 20' 14.025''W"),'E'),"\n";
  echo "radians(2.56°N) -> ", radians("2.56°N")," / 0.0446804288511\n";
  echo "radians	(2.56 gr W) -> ", radians("2.56 gr W")," / -0.0402123859659\n";
  echo "radians	(46°30'N) -> ", radians("46°30'N")," / 0.811578102177\n";
  die ("FIN OK\n");
}

elseif ($_GET['TEST']=='pratique') {
  $geogCs = CRS::S('NTFLonLatDd');
  echo "S::NTFLonLatDd = "; print_r($geogCs);

  $geogCs = CRS::IGNF('RGF93GEODD');
  echo "IGNF::RGF93GEODD = "; print_r($geogCs);

  $geogCs = CRS::EPSG(4326);
  echo "EPSG(4326) = "; print_r($geogCs);

  die("OK ligne ".__LINE__."<br>\n");
}

die("OK ligne ".__LINE__."<br>\n");
