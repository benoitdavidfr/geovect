<?php
namespace gegeom;
/**
 * position.inc.php - définition de différentes classes statiques de gestion de positions ou de liste**n de position
 *
 * journal:
 *  4/8/2022:
 *   - transfert méthodes de shomgt3
 *   - corrections suite à analyse PhpStan level 6
 *   - structuration de la doc conformément à phpDocumentor
 *  9/12/2020:
 *   - transfert de méthodes Point dans Pos pour passage Php8
 *  8/5/2019:
 *   - modif de la méthode de tests unitaires
 *  5/5/2019:
 *   - création par scission de gegeom.inc.php
 */
require_once __DIR__.'/unittest.inc.php';
require_once __DIR__.'/../lib/sexcept.inc.php';
use \unittest\UnitTest;


/**
 * flatten(array<mixed>): array<int, mixed> - applatit une liste pour retourner une liste d'éléments non liste
 *
 * ignore les clés éventuelles
 *
 * @param array<mixed> $array
 * @return array<int, mixed>
 */
function flatten(array $array) {
  $return = [];
  array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
  return $return;
}

UnitTest::function(__FILE__, 'flatten', function() {
  foreach([
    [['a','b'],['c','d']],
    [['a','b',['x','y']],['c','d']],
    [['k'=>'a','b',['k2'=>'x','y']],['c','d']],
  ] as $list) {
    echo "flatten(",json_encode($list),") -> ",json_encode(flatten($list)),"<br>\n";
  }
});

/**
 * class Pos - fonctions s'appliquant à une position
 *
 * La classe est une classe statique regroupant les fonctions
 * une position est une liste de 2 ou 3 nombres
 * Peut correspondre à un point ou à un vecteur.
*/
class Pos {
  // pattern du format GeoDMd
  const GEODMD_PATTERN = '!^(\d+)°((\d\d)(,(\d+))?\')?(N|S) - (\d+)°((\d\d)(,(\d+))?\')?(E|W)$!';
  const EXAMPLES = [
    "une position avec 2 coordonnées géographiques"=> [ -59.572094692612, -80.040178725096 ],
    "une position à 3 coordonnées en coordonnées projetées avec une altitude"=> [ 176456.1, 6457879.7, 123 ],
    "une position comprenant des infos complémentaires"=> [ -59.572094692612, -80.040178725096, 123, 'precision'=> 1e-6 ],
  ];
  const COUNTEREXAMPLES = [
    "Représentation standard d'une position indéfinie qui n'est pas valide"=> [],
    "non définie par une liste"=> ['x'=>123, 'y'=>456],
  ];
  const ErrorParamInFromGeoDMd = 'Pos::ErrorParamInFromGeoDMd';
  const ErrorInDistancePosLine = 'Pos::ErrorInDistancePosLine';
  
  /**
   * is() - teste si $pos est une position
   *
   * is() permet notament de distinguer Pos, LPos, LLPos et LLLPos ; ne vérifie pas la validité de $pos.
   */
  static function is(mixed $pos): bool { return is_array($pos) && isset($pos[0]) && is_numeric($pos[0]); }
  
  /**
   * isValid() - vérifie la validité de $pos comme position
   *
   * définition la moins contraignante possible
   */
  static function isValid(mixed $pos): bool {
    return is_array($pos) && isset($pos[0]) && is_numeric($pos[0]) && isset($pos[1]) && is_numeric($pos[1])
        && (!isset($pos[2]) || is_numeric($pos[2]));
  }
  
  /**
   * getErrors() - renvoie les raisons pour lesquelles $pos n'est pas une position
   *
   * retourne une liste de string
   *
   * @return array<int, string>
  */
  static function getErrors(mixed $pos): array {
    $errors = [];
    if (!is_array($pos))
      return ["La position doit être un array"];
    if (!$pos)
      return ["[] n'est pas une position valide"];
    if (!isset($pos[0]) || !is_numeric($pos[0]))
      $errors[] = "La première coordonnée de la position doit être un nombre";
    if (!isset($pos[1]) || !is_numeric($pos[1]))
      $errors[] = "La deuxième coordonnée de la position doit être un nombre";
    if (isset($pos[2]) && !is_numeric($pos[2]))
      $errors[] = "Quand elle existe la troisième coordonnée de la position doit être un nombre";
    return $errors;
  }
  static function test_getErrors(): void {
    foreach (array_merge(self::EXAMPLES, self::COUNTEREXAMPLES) as $title => $ex) {
      echo "$title - getErrors()=",json_encode(self::getErrors($ex)),"<br>\n";
    }
  }
  
  /**
   * fromGeoDMd() - décode une position en coords géographiques en degré minutes décimales
   *
   * @return TPos
   */
  static function fromGeoDMd(string $geoDMd): array {
    if (!preg_match(self::GEODMD_PATTERN, $geoDMd, $matches))
      throw new \SExcept("No match in Pos::fromGeoDMd($geoDMd)", self::ErrorParamInFromGeoDMd);
    //echo "<pre>matches="; print_r($matches); echo "</pre>\n";
    $lat = ($matches[6]=='N' ? 1 : -1) * 
      ($matches[1] + (($matches[3] ? $matches[3] : 0) + ($matches[5] ? ".$matches[5]" : 0))/60);
    //echo "lat=$lat";
    $lon = ($matches[12]=='E' ? 1 : -1) * 
      ($matches[7] + (($matches[9] ? $matches[9] : 0) + ($matches[11] ? ".$matches[11]" : 0))/60);
    //echo ", lon=$lon";
    return [$lon, $lat];
  }
  
  // Formatte une coord. lat ou lon
  static function formatCoordInDMd(float $coord, int $nbposMin): string {
    $min = number_format(($coord-floor($coord))*60, $nbposMin, ','); // minutes formattées
    //echo "min=$min<br>\n";
    if ($nbposMin <> 0) {
      if (preg_match('!^\d,!', $min)) // si il n'y a qu'un seul chiffre avant la virgule
        $min = '0'.$min; // alors je rajoute un zéro avant
    }
    elseif (preg_match('!^\d$!', $min)) // si il n'y a qu'un seul chiffre avant la virgule
      $min = '0'.$min; // alors je rajoute un zéro avant

    $string = sprintf("%d°%s'", floor($coord), $min);
    return $string;
  }
  
  /**
   * Formate une position (lon,lat) en lat,lon degrés, minutes décimales
   *
   * $resolution est la résolution de la position en degrés à conserver
   *
   * @param TPos $pos
   */
  static function formatInGeoDMd(array $pos, float $resolution): string {
    //return sprintf("[%f, %f]",$pos[0], $pos[1]);
    $lat = $pos[1];
    $lon = $pos[0];
    if ($lon > 180)
      $lon -= 360;
    
    $resolution *= 60;
    //echo "resolution=$resolution<br>\n";
    //echo "log10=",log($resolution,10),"<br>\n";
    $nbposMin = ceil(-log($resolution,10));
    if ($nbposMin < 0)
      $nbposMin = 0;
    //echo "nbposMin=$nbposMin<br>\n";
    
    return self::formatCoordInDMd(abs($lat), $nbposMin).(($lat >= 0) ? 'N' : 'S')
      .' - '.self::formatCoordInDMd(abs($lon), $nbposMin).(($lon >= 0) ? 'E' : 'W');
  }

  /**
   * diff() - $pos - $v en 2D où $v est une position
   *
   * @param TPos $pos
   * @param TPos $v
   * @return TPos
   */
  static function diff(array $pos, array $v): array {
    if (!self::is($pos))
      throw new \Exception("Erreur dans Pos:diff(), paramètre pos pas une position");
    elseif (!self::is($v))
      throw new \Exception("Erreur dans Pos:diff(), paramètre v pas une position");
    else
      return [$pos[0] - $v[0], $pos[1] - $v[1]];
  }
  
  /**
   * vectorProduct() - produit vectoriel $u par $v en 2D
   *
   * @param TPos $u
   * @param TPos $v
   * @return float
   */
  static function vectorProduct(array $u, array $v): float { return $u[0] * $v[1] - $u[1] * $v[0]; }
  
  /**
   * scalarProduct() - produit scalaire $u par $v en 2D
   *
   * @param TPos $u
   * @param TPos $v
   * @return float
   */
  static function scalarProduct(array $u, array $v): float { return $u[0] * $v[0] + $u[1] * $v[1]; }
  static function test_scalarProduct(): void {
    foreach ([
      [[15,20], [20,15]],
      [[1,0], [0,1]],
      [[4,0], [0,3]],
      [[1,0], [1,0]],
    ] as $lpts) {
      $v0 = $lpts[0];
      $v1 = $lpts[1];
      echo "vectorProduct(",implode(',',$v0),",",implode(',',$v1),")=",self::vectorProduct($v0, $v1),"<br>\n";
      echo "scalarProduct(",implode(',',$v0),",",implode(',',$v1),")=",self::scalarProduct($v0, $v1),"<br>\n";
    }
  }
  
  /**
   * norm() - norme de $u en 2D
   *
   * @param TPos $u
   * @return float
   */
  static function norm(array $u): float { return sqrt(self::scalarProduct($u, $u)); }
    
  /**
   * distance() -  distance entre les positions $a et $b
   *
   * @param TPos $a
   * @param TPos $b
   * @return float
   */
  static function distance(array $a, array $b): float { return self::norm(self::diff($a, $b)); }
  
  /**
   * distancePosLine() - distance signée de la position $pos à la droite définie par les 2 positions $a et $b
   *
   * La distance est positive si le point est à gauche de la droite AB et négative s'il est à droite
   *
   * @param TPos $pos
   * @param TPos $a
   * @param TPos $b
   * @return float
   */
  static function distancePosLine(array $pos, array $a, array $b): float {
    $ab = self::diff($b, $a);
    $ap = self::diff($pos, $a);
    if (self::norm($ab) == 0)
      throw new \SExcept("Erreur dans distancePosLine : Points A et B confondus et donc droite non définie",
          self::ErrorInDistancePosLine);
    return self::vectorProduct($ab, $ap) / self::norm($ab);
  }
  static function test_distancePosLine(): void {
    foreach ([
      [[1,0], [0,0], [1,1]],
      [[1,0], [0,0], [0,2]],
    ] as $lpts) {
      echo "distancePosLine([",implode(',', $lpts[0]),"],[",implode(',',$lpts[1]),'],[',implode(',',$lpts[2]),"])->",
        self::distancePosLine($lpts[0], $lpts[1],$lpts[2]),"<br>\n";
    }
  }
  
  /**
   * posInPolygon() -teste si la Pos $p est dans la LPos fermée définie par $cs
   *
   * @param TPos $p
   * @param TLPos $cs
   * @return bool
   */
  static function posInPolygon(array $p, array $cs): bool {
    {/*  Code de référence en C:
    int pnpoly(int npol, float *xp, float *yp, float x, float y)
    { int i, j, c = 0;
      for (i = 0, j = npol-1; i < npol; j = i++) {
        if ((((yp[i]<=y) && (y<yp[j])) ||
             ((yp[j]<=y) && (y<yp[i]))) &&
            ((x - xp[i]) < (xp[j] - xp[i]) * (y - yp[i]) / (yp[j] - yp[i])))
          c = !c;
      }
      return c;
    }*/}
    $c = false;
    $j = count($cs) - 1;
    for($i=0; $i<count($cs); $i++) {
      if (((($cs[$i][1] <= $p[1]) && ($p[1] < $cs[$j][1])) || (($cs[$j][1] <= $p[1]) && ($p[1] < $cs[$i][1])))
        && (($p[0] - $cs[$i][0]) < ($cs[$j][0] - $cs[$i][0]) * ($p[1] - $cs[$i][1]) / ($cs[$j][1] - $cs[$i][1]))) {
        $c = !$c;
      }
      $j = $i;
    }
    return $c;
  }
  static function test_posInPolygon(): void {
    $p0 = [0, 0];
    foreach ([ // liste de polyligne non fermées
      ['lpos'=> [[1, 0],[0, 1],[-1, 0],[0, -1]], 'result'=> true],
      ['lpos'=> [[1, 1],[-1, 1],[-1, -1],[1, -1]], 'result'=> true],
      ['lpos'=> [[1, 1],[-1, 1],[-1, -1],[1, -1],[1, 1]], 'result'=> true],
      ['lpos'=> [[1, 1],[2, 1],[2, 2],[1, 2]], 'result'=> false],
    ] as $test) {
      $lpos = $test['lpos'];
      $lpos[] = $lpos[0]; // fermeture de la polyligne
      echo "posInPolygon(",json_encode($lpos),',',json_encode($p0),")=",
          (self::posInPolygon($p0, $lpos)?'true':'false')," / ",($test['result']?'true':'false'),"<br>\n";
    }
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'Pos'); // Test unitaire de la classe Pos

/**
 * class LPos - fonctions s'appliquant à une liste de positions contenant au moins une position
 *
 * Les méthodes is(), isValid() et getErrors() jouent le même rôle que pour la classe Pos
*/
class LPos {
  const EXAMPLES = [
    "cas réel"=> [[ -59.572094692612, -80.040178725096 ], [ -59.865849371975, -80.549656671062 ], [ -60.15965572777, -81.000326837079 ], [ -62.255393439367, -80.863177585777 ], [ -64.48812537297, -80.921933689293 ], [ -65.74166642929, -80.588827406739 ], [ -65.74166642929, -80.549656671062 ], [ -66.290030890555, -80.255772800618 ], [ -64.037687750898, -80.294943536295 ], [ -61.883245612217, -80.392870375488 ], [ -61.138975796133, -79.981370945148 ], [ -60.610119188058, -79.628679294756 ], [ -59.572094692612, -80.040178725096 ]]
  ];

  /**
   * is() - teste si $lpos est une liste de positions
   */
  static function is(mixed $lpos): bool { return is_array($lpos) && isset($lpos[0]) && Pos::is($lpos[0]); }

  /**
   * isValid() - vérifie la validité de $lpos comme liste de positions
   */
  static function isValid(mixed $lpos): bool {
    if (!is_array($lpos))
      return false;
    if (!$lpos)
      return false;
    foreach ($lpos as $pos)
      if (!Pos::isValid($pos))
        return false;
    return true;
  }

  /**
   * getErrors() - renvoie les raisons pour lesquelles $lpos n'est pas une liste de positions
   *
   * retourne une liste de string
   *
   * @return array<mixed>
  */
  static function getErrors(mixed $lpos): array {
    $errors = [];
    if (!is_array($lpos))
      return ["La LPos doit être un array"];
    if (!$lpos)
      return ["La LPos doit contenir au moins une position"];
    foreach ($lpos as $i => $pos)
      if ($posErrors = Pos::getErrors($pos))
        $errors[] = ["Erreur sur la position $i :", $posErrors];
    return $errors;
  }
  static function test_getErrors(): void {
    foreach (self::EXAMPLES as $title => $ex) {
      echo "$title - getErrors()=",json_encode(self::getErrors($ex)),"<br>\n";
    }
  }
  
  /**
   * length() - longueur d'une ligne brisée définie par une liste de positions
   *
   * @param TLPos $lpos
   * @return float
   */
  static function length(array $lpos): float {
    $length = 0;
    $posPrec = null;
    foreach ($lpos as $pos) {
      if ($posPrec)
        $length += Pos::distance($posPrec, $pos);
      $posPrec = $pos;
    }
    return $length;
  }
  
  /**
   * areaOfRing() - surface de l'anneau constitué par la liste de positions
   *
   * La surface est positive ssi la géométrie est orientée dans le sens des aiguilles d'une montre (sens trigo inverse).
   * Cette règle est conforme à la définition GeoJSON:
   *   A linear ring MUST follow the right-hand rule with respect to the area it bounds,
   *   i.e., exterior rings are clockwise, and holes are counterclockwise.
   *
   * @param TLPos $lpos
   * @return float
   */
  static function areaOfRing(array $lpos): float {
    $area = 0.0;
    $pos0 = $lpos[0];
    for ($i=1; $i<count($lpos)-1; $i++) {
      $area += Pos::vectorProduct(Pos::diff($lpos[$i], $pos0), Pos::diff($lpos[$i+1], $pos0));
    }
    return -$area/2;
  }
  static function test_areaOfRing(): void {
    foreach ([
      [[0,0],[0,1],[1,0],[0,0]],
      [[0,0],[1,0],[0,1],[0,0]],
      [[0,0],[0,1],[1,1],[1,0],[0,0]],
    ] as $lpos) {
      echo "areaOfRing(".LnPos::wkt($lpos).")=",self::areaOfRing($lpos),"<br>\n";
    }
  }
  
  /**
   * filter() - renvoie une LPos filtrée supprimant les points successifs identiques
   *
   * Les coordonnées sont arrondies avec $precision chiffres significatifs. Un filtre sans arrondi n'a pas de sens.
   *
   * @param TLPos $lpos
   * @param int $precision
   * @return TLPos
   */
  static function filter(array $lpos, int $precision): array {
    //echo "Lpos::filter(",json_encode($lpos),", $precision)=<br>\n";
    $filtered = [];
    $posprec = null;
    foreach ($lpos as $pos) {
      $rounded = [round($pos[0], $precision), round($pos[1], $precision)];
      //echo "rounded(",json_encode($pos),")=",json_encode($rounded),"<br>\n";
      if (isset($pos[2]))
        $rounded[] = $pos[2];
      if (!$posprec || ($rounded <> $posprec)) {
        $filtered[] = $rounded;
      }
      $posprec = $rounded;
    }
    //echo json_encode($filtered),"<br>\n";
    return $filtered;
  }
  static function test_filter(): void {
    $ls = [[0,0],[0.1,0],[0,1],[2,2]];
    echo "filter(",json_encode($ls),", 1)=",json_encode(self::filter($ls, 1)),"<br>\n";
    echo "filter(",json_encode($ls),", 0)=",json_encode(self::filter($ls, 0)),"<br>\n";
    $ls = [[0,0],[0.1,0],[0,1],[2,2],[0,0]];
    echo "filter(",json_encode($ls),", 1)=",json_encode(self::filter($ls, 1)),"<br>\n";
    echo "filter(",json_encode($ls),", 0)=",json_encode(self::filter($ls, 0)),"<br>\n";
    foreach (self::EXAMPLES as $title => $ex) {
      echo "filter(",json_encode($ex),", 3)=",json_encode(self::filter($ex, 0)),"<br>\n";
    }
  }
  
  /**
   * simplify() - simplifie la géométrie de la ligne brisée
   *
   * Algorithme de Douglas & Peucker
   * Retourne un LPos simplifiée ou [] si la ligne est fermée et que la distance max est inférieure au seuil
   *
   * @param TLPos $lpos
   * @param float $distTreshold
   * @return TLPos
   */
  static function simplify(array $lpos, float $distTreshold): array {
    //echo "simplify($this, $distTreshold)<br>\n";
    if (count($lpos) < 3)
      return $lpos;
    $pos0 = $lpos[0];
    $posn = $lpos[count($lpos)-1];
    if ($pos0 <> $posn) { // cas d'une ligne ouverte
      $distmax = 0; // distance max
      $nptmax = -1; // num du point pour la distance max
      foreach($lpos as $n => $pos) {
        $dist = abs(Pos::distancePosLine($pos, $pos0, $posn));
        if ($dist > $distmax) {
          $distmax = $dist;
          $nptmax = $n;
        }
      }
      //echo "distmax=$distmax, nptmax=$nptmax<br>\n";
      if ($distmax < $distTreshold)
        return [$pos0, $posn];
      $ls1 = array_slice($lpos, 0, $nptmax+1);
      $ls1 = self::simplify($ls1, $distTreshold);
      $ls2 = array_slice($lpos, $nptmax);
      $ls2 = self::simplify($ls2, $distTreshold);
      return array_merge($ls1, array_slice($ls2, 1));
    }
    else { // cas d'une ligne fermée
      $distmax = 0; // distance max
      $nptmax = -1; // num du point pour la distance max
      foreach($lpos as $n => $pos) {
        $dist = Pos::distance($pos0, $pos);
        if ($dist > $distmax) {
          $distmax = $dist;
          $nptmax = $n;
        }
      }
      if ($distmax < $distTreshold)
        return [];
      $ls1 = array_slice($lpos, 0, $nptmax+1);
      $ls1 = self::simplify($ls1, $distTreshold);
      $ls2 = array_slice($lpos, $nptmax);
      $ls2 = self::simplify($ls2, $distTreshold);
      return array_merge($ls1, array_slice($ls2, 1));
    }
  }
  static function test_simplify(): void {
    $ls = [[0,0],[0.1,0],[0,1],[2,2]];
    echo "simplify(",json_encode($ls),", 1)=",json_encode(self::simplify($ls, 1)),"<br>\n";
    echo "simplify(",json_encode($ls),", 0.5)=",json_encode(self::simplify($ls, 0.5)),"<br>\n";
    $ls = [[0,0],[0.1,0],[0,1],[2,2],[0,0]];
    echo "simplify(",json_encode($ls),", 1)=",json_encode(self::simplify($ls, 1)),"<br>\n";
    foreach (self::EXAMPLES as $title => $ex) {
      echo "filter(",json_encode($ex),", 3)=",json_encode(self::simplify($ex, 0.1)),"<br>\n";
    }
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'LPos'); // Test unitaire de la classe LPos

/**
 * class LLPos - fonctions s'appliquant à une liste de listes de positions contenant au moins une position
 *
 * Les méthodes is(), isValid() et getErrors() jouent le même rôle que pour la classe Pos
*/
class LLPos {
  const EXAMPLES = [
    "triangle unité" => [[[0,0],[0,1],[1,0],[0,0]]],
    "carré unité"=> [[[0,0],[0,1],[1,1],[1,0],[0,0]]],
    "carré troué bien orienté"=>[
      [[0,0],[0,10],[10,10],[10,0],[0,0]],
      [[2,2],[8,2],[8,8],[2,8],[2,2]]
    ],
    "carré troué mal orienté"=> [
      [[0,0],[0,10],[10,10],[10,0],[0,0]],
      [[2,2],[2,8],[8,8],[8,2],[2,2]]
    ],
  ];
  
  /**
   * is() - teste si $lpos est une liste de positions
   */
  static function is(mixed $llpos): bool { return is_array($llpos) && isset($llpos[0]) && LPos::is($llpos[0]); }

  /**
   * isValid() - vérifie la validité de $lpos comme liste de positions
   */
  static function isValid(mixed $llpos): bool {
    if (!is_array($llpos))
      return false;
    foreach ($llpos as $lpos)
      if (!LPos::isValid($lpos))
        return false;
    return true;
  }

  /**
   * getErrors() - renvoie les raisons pour lesquelles $lpos n'est pas une liste de positions
   *
   * retourne une liste de string
   *
   * @return array<mixed>
  */
  static function getErrors(mixed $llpos): array {
    $errors = [];
    if (!is_array($llpos))
      return ["Le LLPos doit être un array"];
    foreach ($llpos as $i => $lpos)
      if ($lposErrors = LPos::getErrors($lpos))
        $errors[] = ["Erreur sur le LPos $i :", $lposErrors];
    return $errors;
  }
};

/**
 * class LnPos - fonctions s'appliquant à une liste**n de positions
 *
 * Une LnPos est une liste puissance n de positions avec n >= 0
 * Pour n==0 c'est une position (Pos)
 * Pour n==1 c'est une liste de positions (LPos)
 * Pour n==2 c'est une liste de listes de positions (LLPos)
 * ...
*/
class LnPos {
  const ErrorOnEmptyLPos = 'LnPos::ErrorOnEmptyLPos';
  
  /**
   * power(TLnPos $lnpos): int - renvoie la puissance de la LnPos ou -1 pour une liste vide
   *
   * génère une exception si un array non liste est rencontré
   *
   * @param TLnPos $lnpos
   */
  static function power(array $lnpos): int {
    if (!$lnpos)
      return -1; // par extension renvoie -1 pour une liste vide
    if (!isset($lnpos[0]))
      throw new \Exception("Erreur d'appel de LnPos::power() sur un array qui n'est pas une liste");
    if (is_numeric($lnpos[0])) // c'est une position
      return 0; // Pos
    else
      return 1 + self::power($lnpos[0]); // appel récursif
  }
  static function test_power(): void {
    foreach(array_merge(Pos::EXAMPLES, LPos::EXAMPLES) as $lnpos)
    echo "LnPos::power(",json_encode($lnpos),")=", LnPos::power($lnpos),"<br>\n";
  }
  
  /**
   * toString(TLnPos $lnpos): string - génère une chaine de caractères représentant la LnPos
   *
   * @param TLnPos $lnpos
   * @return string
  */
  static function toString(array $lnpos): string { return json_encode($lnpos); }
  
  /**
   * wkt(TLnPos $lnpos): string - génère une chaine de caractères représentant la LnPos pour WKT
   *
   * @param TLnPos $lnpos
   * @return string
  */
  static function wkt(array $lnpos): string {
    //echo "Appel de LnPos::wkt(",json_encode($lnpos),"<br>\n";
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return implode(' ', $lnpos); // @phpstan-ignore-line
    else
      return '('.implode(',',array_map(function(array $ln1pos): string { return self::wkt($ln1pos); }, $lnpos)).')';
  }
  static function test_wkt(): void {
    foreach([
      "liste vide"=> [],
      "Pos"=> [0,1],
      "LPos"=> [[0,1],[2,3]], // LPos
      "LLPos"=> [[[0,1],[2,3]],[[3,4]]], // LLPos
      "LLLPos"=> [[[[0,1],[2,3]],[[3,4]]],[[[5,6]]]], // LLLPos
    ] as $label => $lnpos) {
      echo "$label -> ",self::wkt($lnpos),"<br>\n";
    }
  }
  
  /**
   * aPos(TLnPos $lnpos): TPos - retourne la première position
   *
   * @param TLnPos $lnpos
   * @return TPos
  */
  static function aPos(array $lnpos): array {
    if (!$lnpos) // $lnpos est la liste vide
      throw new \Exception("Erreur d'appel de LnPos::aPos() sur une liste de positions vide");
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return $lnpos;
    else
      return self::aPos($lnpos[0]);
  }
  
  /**
   * count(TLnPos $lnpos): int - calcul du nbre de positions
   *
   * @param TLnPos $lnpos
   * @return int
  */
  static function count(array $lnpos): int {
    if (!$lnpos) // $lnpos est la liste vide
      return 0;
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return 1;
    else
      return array_sum(array_map(function(array $ln1pos): int { return self::count($ln1pos); }, $lnpos));
  }
  
  /**
   * sumncoord(TLnPos $lnpos, int $i): int - calcul de la somme de la i-ème coordonnée de chaque position
   *
   * @param TLnPos $lnpos
   * @return float
  */
  static function sumncoord(array $lnpos, int $i): float {
    if (!$lnpos)
      return 0;
    elseif (Pos::is($lnpos)) // $lnpos est une Pos
      return $lnpos[$i];
    else
      return array_sum(array_map(function(array $ln1pos) use($i): float { return self::sumncoord($ln1pos, $i); }, $lnpos));
  }
  
  /**
   * center(TLnPos $lnpos, int $precision): TPos - calcule le centre d'une liste**n de positions
   *
   * génère une erreur si la liste est vide
   *
   * @param TLnPos $lnpos
   * @return TPos
  */
  static function center(array $lnpos, int $precision): array {
    if (!$lnpos)
      throw new \SExcept("Erreur d'appel de LnPos::center() sur une liste de positions vide", self::ErrorOnEmptyLPos);
    $nbre = self::count($lnpos);
    return [round(self::sumncoord($lnpos, 0)/$nbre, $precision), round(self::sumncoord($lnpos, 1)/$nbre, $precision)];
  }
  static function test_center(): void {
    $lpos = [[1,2],[3,7,5]];
    echo "<pre>LnPos::count(",LnPos::toString($lpos),")="; print_r(LnPos::count($lpos));
    echo "<pre>LnPos::sumncoord(",LnPos::toString($lpos),", 0)="; print_r(LnPos::sumncoord($lpos, 0));
    echo "<pre>LnPos::sumncoord(",LnPos::toString($lpos),", 1)="; print_r(LnPos::sumncoord($lpos, 1));
    echo "<pre>LnPos::center(",LnPos::toString($lpos),",1)=",json_encode(LnPos::center($lpos,1)),"<br>\n";
  }
  
  /**
   * projLn(TLnPos $lnpos, callable $projPos): TLnPos- projette chaque Pos de la LnPos avec la fonction $projPos et retourne la LnPos reconstruite
   *
   * @param TLnPos $lnpos
   * @return TLnPos
  */
  static function projLn(array $lnpos, callable $projPos): array {
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return $projPos($lnpos);
    else
      return array_map(function(array $ln1pos) use($projPos) { return self::projLn($ln1pos, $projPos); }, $lnpos);
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'LnPos'); // Test unitaire de la classe LnPos
