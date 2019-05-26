<?php
/*PhpDoc:
name: datum.inc.php
title: datum.inc.php - définit les classes Ellipsoid et GeodeticDatum
classes:
functions:
doc: |
  Mise en oeuvre d'algorithmes définis dans la note NT/G 71
  https://geodesie.ign.fr/contenu/fichiers/documentation/algorithmes/notice/NTG_71.pdf
journal: |
  22-24/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
    - ajout différents tests pour chgt de système
includes: [crs.inc.php]
*/
require_once __DIR__.'/crs.inc.php';

/*PhpDoc: classes
name: Ellipsoid
title: class Ellipsoid extends CRS - Classe des ellipsoides
methods:
doc: |
*/
class Ellipsoid extends CRS {
  protected $ellisoidId;
  protected $a=null;
  protected $e2=null;
  
  // $params est soit un code définissant un Ellipsoid dans le registre ELLIPSOID
  // soit un array avec des paramètres d'initialisation de l'Ellipsoid
  function __construct($params) {
    if (is_array($params)) {
      $this->ellisoidId = 'TEST';
      if (isset($params['a']))
        $this->a = $params['a'];
      if (isset($params['e2']))
        $this->e2 = $params['e2'];
      if (isset($params['e']))
        $this->e2 = $params['e'] * $params['e'];
      return;
    }
    elseif (!is_string($params))
      throw new Exception("Erreur dans Ellipsoid::__construct() - params incorrect");
    $ellisoidId = $params;
    $this->ellisoidId = $ellisoidId;
    if (!($params = self::registre('ELLIPSOID', $ellisoidId)))
      throw new Exception("Unknown Ellipsoid $ellisoidId");
    //echo "Ellipsoid params="; print_r($params);
    $this->a = $params['a'];
    $f = ($params['1/f']==='inf') ? 0 : 1 / $params['1/f'];
    $this->e2 = $f * (2 - $f);
  }
  
  function __toString(): string { return 'Ellipsoid::'.$this->ellisoidId; }
  function a() { return $this->a; }
  function e2() { return $this->e2; }
  function e() { return sqrt(self::e2()); }
};

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // TEST de la classe 
  if (!isset($_GET['TEST']))
    echo "<a href='?TEST=Ellipsoid'>Test de la classe Ellipsoid</a><br>\n";
  elseif ($_GET['TEST'] == 'Ellipsoid') {
    $s = new Ellipsoid('GRS_1980');
    echo "<pre>spheroid="; print_r($s);
    die("OK ligne ".__LINE__);
  }
}

/*PhpDoc: classes
name: GeodeticDatum
title: class GeodeticDatum extends Ellipsoid - Classe des systèmes géodésiques
methods:
doc: |
*/
class GeodeticDatum extends Ellipsoid {
  const EPSILON = 1E-11; // tolerance de convergence du calcul de la latitude
  protected $datumId = null;
  protected $toWgs84 = null;
  
  // $params est soit un code définissant un GeodeticDatum dans le registre DATUM
  // soit un array avec des paramètres d'initialisation de l'Ellipsoid et un éventuel toWgs84
  function __construct($params) {
    if (is_array($params)) {
      $this->datumId = 'TEST';
      if (isset($params['toWgs84']))
        $this->toWgs84 = $params['toWgs84'];
      parent::__construct($params);
      return;
    }
    elseif (!is_string($params))
      throw new Exception("Erreur dans GeodeticDatum::__construct() - params incorrect");
    $datumId = $params;
    $this->datumId = $datumId;
    if (!($params = self::registre('DATUM', $datumId)))
      throw new Exception("Unknown GeodeticDatum $datumId");
    //echo "GeodeticDatum params="; print_r($params);
    if (isset($params['TOWGS84']))
      $this->toWgs84 = $params['TOWGS84'];
    parent::__construct($params['ELLIPSOID']);
  }
  
  function __toString(): string { return 'GeodeticDatum::'.$this->datumId; }
  
  // la transfo en WGS84 est-elle nulle ?
  function toWgs84isZero(): bool {
    if (!$this->toWgs84)
      return false;
    foreach ($this->toWgs84 as $val)
      if ($val !== 0)
        return false;
    return true;
  }
    
  // Algorithmes utilises par le calcul de la projection en Lambert conique conforme (note NT/G 71)
  // ALG0001
  // Calcul de la latitude isometrique sur un ellipsoide de premiere excentricite $e
  // au point de latitude $phi
  protected function latitude_isometrique(float $phi): float {
    $temp = ( 1 - ( $this->e() * sin( $phi ) ) ) / ( 1 + ( $this->e() * sin( $phi ) ) );
    return  log ( tan ( (pi()/4) + ($phi/2) ) * pow ($temp, $this->e()/2));
  }
  public function test_latitude_isometrique(float $phi, string $resultat_attendu) {
    echo "latitude_isometrique (phi=$phi) -> ",$this->latitude_isometrique($phi)," / $resultat_attendu\n";
  }

  // ALG0002
  // Calcul de la latitude a partir de la latitude isometrique $L
  // pour un ellipsoide de premiere excentricite $e
  // et pour une tolerance de convergence EPSILON definie comme constante de la classe
  protected function latitude(float $L): float {
    $phi1 = 2 * atan(exp($L)) - (pi()/2);
    for ($i=0; $i < 100; $i++) {
      $phi0 = $phi1;
      $temp = ( 1 + ( $this->e() * sin( $phi0 ) ) ) / ( 1 - ( $this->e() * sin( $phi0 ) ) );
      $phi1 = 2 * atan ( pow ($temp, $this->e()/2) * exp ($L) ) - pi()/2;
      if (abs($phi1 - $phi0) < self::EPSILON)
        return $phi1;
    }
    throw new Exception ("Erreur de convergence dans GeodeticDatum::latitude()");
  }
  public function test_latitude (float $L, string $resultat_attendu) {
    echo "latitude (L=$L) -> ",$this->latitude($L)," / $resultat_attendu\n";
  }
  
  // Algorithmes pour le changement de systeme geodesique
  // Source: Note NT/G 80 du SGN (Janvier 1995)
  // http://geodesie.ign.fr/contenu/fichiers/documentation/algorithmes/notice/NTG_80.pdf
  // ALG0021
  // Calcul de la grande normale de l'ellipsoide
  protected function grande_normale(float $phi): float {
    return $this->a() / sqrt( 1 - $this->e2() * sin($phi) * sin($phi) );
  }
  public function test_grande_normale(float $phi, string $resultat_attendu): void {
    echo "grande_normale (phi=$phi) ->",$this->grande_normale ($phi)," / $resultat_attendu\n";
  }
};


if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // TEST de la classe 
  if (!isset($_GET['TEST'])) {
    echo "<a href='?TEST=GeodeticDatum'>Test de la classe GeodeticDatum</a><br>\n";
    echo "<a href='?TEST=NTG71'>Test des jeux d'essai fournis dans la note NT/G 71</a><br>\n";
  }
  
  elseif ($_GET['TEST'] == 'GeodeticDatum') {
    $d = new GeodeticDatum('WGS_1984');
    echo "<pre>datum="; print_r($d);
    die("OK ligne ".__LINE__);
  }

  // Test des jeux d'essai fournis dans la note NT/G 71
  elseif ($_GET['TEST']=='NTG71') {
    echo "<pre>\n** Test de ALG0001 latitude_isometrique\n";
    $datum = new GeodeticDatum(['e'=> 0.08199188998]);
    $datum->test_latitude_isometrique(0.872664626, "1.005 526 536 49");
    $datum->test_latitude_isometrique(-0.3, "-0.302 616 900 63");
    $datum->test_latitude_isometrique(0.19998903370, "0.200 000 000 009");
    echo "\n";

    echo "\n** Test de ALG0002 latitude\n";
    $datum = new GeodeticDatum(['e'=> 0.08199188998]);
    $datum->test_latitude (1.00552653648, "0.872 664 626 00");
    $datum->test_latitude (-0.30261690060, "-0.299 999 999 97");
    $datum->test_latitude (0.2, "0.199 989 033 69");

    echo "\n** Test de ALG0021 grande_normale\n";
    $datum = new GeodeticDatum(['a'=> 6378388, 'e'=> 0.08199188998]);
    $datum->test_grande_normale(0.977384381, "6 393 174.975 5");
    echo "\n";
    die ("FIN OK ligne ".__LINE__."\n");
  }
}
