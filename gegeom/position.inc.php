<?php
namespace gegeom;
{/*PhpDoc:
name:  position.inc.php
title: position.inc.php - définition de différentes classes statiques de gestion de positions ou de liste**n de position
functions:
classes:
doc: |
journal: |
  9/12/2020:
    - transfert de méthodes Point dans Pos pour passage Php8
  8/5/2019:
    - modif de la méthode de tests unitaires
  5/5/2019:
    - création par scission de gegeom.inc.php
includes: [unittest.inc.php]
*/}
require_once __DIR__.'/unittest.inc.php';
use \unittest\UnitTest;

{/*PhpDoc: classes
name: Pos
title: class Pos - classe statique de gestion d'une position
doc: |
  une position est une liste de 2 ou 3 nombres
methods:
*/}
class Pos {
  const EXAMPLES = [
    "une position avec 2 coordonnées géographiques"=> [ -59.572094692612, -80.040178725096 ],
    "une position à 3 coordonnées en coordonnées projetées avec une altitude"=> [ 176456.1, 6457879.7, 123 ],
    "une position comprenant des infos complémentaires"=> [ -59.572094692612, -80.040178725096, 123, 'precision'=> 1e-6 ],
  ];
  const COUNTEREXAMPLES = [
    "Représentation standard d'une position indéfinie qui n'est pas valide"=> [],
    "non définie par une liste"=> ['x'=>123, 'y'=>456],
  ];
  
  /*PhpDoc: methods
  name:  is
  title: "static function is(array $pos): bool - teste si $pos est une position"
  doc: is() permet notament de distinguer Pos, LPos, LLPos et LLLPos ; ne vérifie pas la validité de $pos.
  */
  static function is($pos): bool { return is_array($pos) && isset($pos[0]) && is_numeric($pos[0]); }
  
  /*PhpDoc: methods
  name:  isValid
  title: "static function isValid($pos): bool - vérifie la validité de $pos comme position"
  doc: définition la moins contraingnante possible
  */
  static function isValid($pos): bool {
    return is_array($pos) && isset($pos[0]) && is_numeric($pos[0]) && isset($pos[1]) && is_numeric($pos[1])
        && (!isset($pos[2]) || is_numeric($pos[2]));
  }
  
  /*PhpDoc: methods
  name:  getErrors
  title: "static function getErrors($pos): array - renvoie les raisons pour lesquelles $pos n'est pas une position"
  doc: retourne une liste de string
  */
  static function getErrors($pos): array {
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
  
  /*PhpDoc: methods
  name:  diff
  title: "static function diff(array $pos, array $v): array - $pos - $v en 2D, $v doit être une position"
  */
  static function diff(array $pos, array $v): array {
    if (Pos::is($v))
      return [$pos[0] - $v[0], $pos[1] - $v[1]];
    else
      throw new \Exception("Erreur dans Pos:diff(), paramètre v pas une position");
  }
  
  /*PhpDoc: methods
  name:  distance
  title: "static function distance(array $a, array $b): float - distance entre les positions $a et $b"
  */
  static function distance(array $a, array $b): float { return self::norm(self::diff($a, $b)); }
  
  /*PhpDoc: methods
  name:  vectorProduct
  title: "static function vectorProduct(array $u, array $v): float - produit vectoriel $this par $v en 2D"
  */
  static function vectorProduct(array $u, array $v): float { return $u[0] * $v[1] - $u[1] * $v[0]; }
  
  /*PhpDoc: methods
  name:  scalarProduct
  title: "static function scalarProduct(array $u, array $v): float - produit scalaire $u par $v en 2D"
  */
  static function scalarProduct(array $u, array $v): float { return $u[0] * $v[0] + $u[1] * $v[1]; }
  static function test_scalarProduct() {
    foreach ([
      [[15,20], [20,15]],
      [[1,0], [0,1]],
      [[4,0], [0,3]],
      [[1,0], [1,0]],
    ] as $lpts) {
      $v0 = $lpts[0];
      $v1 = $lpts[1];
      echo "vectorProduct(",implode(',',$v0),",",implode(',',$v1),"=",self::vectorProduct($v0, $v1),"<br>\n";
      echo "scalarProduct(",implode(',',$v0),",",implode(',',$v1),"=",self::scalarProduct($v0, $v1),"<br>\n";
    }
  }
  
  static function norm(array $u): float { return sqrt(self::scalarProduct($u, $u)); }
    
  /*PhpDoc: methods
  name:  distancePosLine
  title: "static function distancePosLine(array $pos, array $a, array $b): float - distance de $pos à la droite $a et $b"
  doc: |
    distance signée de la position $pos à la droite définie par les 2 positions $a et $b"
    La distance est positive si le point est à gauche de la droite AB et négative s'il est à droite
    # Distance signee d'un point P a une droite orientee definie par 2 points A et B
    # la distance est positive si P est a gauche de la droite AB et negative si le point est a droite
    # Les parametres sont les 3 points P, A, B
    # La fonction retourne cette distance.
    # --------------------
    sub DistancePointDroite
    # --------------------
    { my @ab = (@_[4] - @_[2], @_[5] - @_[3]); # vecteur B-A
      my @ap = (@_[0] - @_[2], @_[1] - @_[3]); # vecteur P-A
      return pvect (@ab, @ap) / Norme(@ab);
    }
  */
  static function distancePosLine(array $pos, array $a, array $b): float {
    $ab = self::diff($b, $a);
    $ap = self::diff($pos, $a);
    if (self::norm($ab) == 0)
      throw new \Exception("Erreur dans distancePosLine : Points A et B confondus et donc droite non définie");
    return self::vectorProduct($ab, $ap) / self::norm($ab);
  }
  static function test_distancePosLine() {
    foreach ([
      [[1,0], [0,0], [1,1]],
      [[1,0], [0,0], [0,2]],
    ] as $lpts) {
      echo "distancePosLine([",implode(',', $lpts[0]),"],[",implode(',',$lpts[1]),'],[',implode(',',$lpts[2]),"])->",
        self::distancePosLine($lpts[0], $lpts[1],$lpts[2]),"<br>\n";
    }
  }
  
  /*PhpDoc: methods
  name:  posInPolygon
  title: "static function posInPolygon(array $p, array $cs): bool - teste si la Pos $p est dans la LPos fermée définie par $cs"
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
  static function test_posInPolygon() {
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

{/*PhpDoc: classes
name: LPos
title: class LPos - liste de positions contenant au moins une position
methods:
doc: |
  Les méthodes is(), isValid() et getErrors() jouent le même rôle que pour la classe Pos
*/}
class LPos {
  const EXAMPLES = [
    "cas réel"=> [[ -59.572094692612, -80.040178725096 ], [ -59.865849371975, -80.549656671062 ], [ -60.15965572777, -81.000326837079 ], [ -62.255393439367, -80.863177585777 ], [ -64.48812537297, -80.921933689293 ], [ -65.74166642929, -80.588827406739 ], [ -65.74166642929, -80.549656671062 ], [ -66.290030890555, -80.255772800618 ], [ -64.037687750898, -80.294943536295 ], [ -61.883245612217, -80.392870375488 ], [ -61.138975796133, -79.981370945148 ], [ -60.610119188058, -79.628679294756 ], [ -59.572094692612, -80.040178725096 ]]
    ];

  static function is($lpos): bool { return is_array($lpos) && isset($lpos[0]) && Pos::is($lpos[0]); }

  static function isValid($lpos): bool {
    if (!is_array($lpos))
      return false;
    if (!$lpos)
      return false;
    foreach ($lpos as $pos)
      if (!Pos::isValid($pos))
        return false;
    return true;
  }

  static function getErrors($lpos): array {
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
  
  /*PhpDoc: methods
  name: filter
  title: "static function filter(array $lpos, int $precision): array - renvoie une LPos filtré supprimant les points successifs identiques"
  doc: |
    Les coordonnées sont arrondies avec $precision chiffres significatifs. Un filtre sans arrondi n'a pas de sens.
    La liste retournée 
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
  static function test_filter() {
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
  
  /*PhpDoc: methods
  name:  simplify
  title: "static function simplify(array $lpos, float $distTreshold): array - simplifie la géométrie de la ligne brisée"
  doc : |
    Algorithme de Douglas & Peucker
    Retourne un LPos simplifié ou [] si la ligne est fermée et que la distance max est inférieure au seuil
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
  static function test_simplify() {
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

{/*PhpDoc: classes
name: LLPos
title: class LLPos - liste de listes de positions
doc: |
  Les méthodes is(), isValid() et getErrors() jouent le même rôle que pour la classe Pos
*/}
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
  
  static function is($llpos): bool { return is_array($llpos) && isset($llpos[0]) && LPos::is($llpos[0]); }
  
  static function isValid($llpos): bool {
    if (!is_array($llpos))
      return false;
    foreach ($llpos as $lpos)
      if (!LPos::isValid($lpos))
        return false;
    return true;
  }

  static function getErrors($llpos): array {
    $errors = [];
    if (!is_array($llpos))
      return ["Le LLPos doit être un array"];
    foreach ($llpos as $i => $lpos)
      if ($lposErrors = LPos::getErrors($lpos))
        $errors[] = ["Erreur sur le LPos $i :", $lposErrors];
    return $errors;
  }
};

{/*PhpDoc: classes
name: LnPos
title: class LnPos - Fonctions sur les listes**n de positions
methods:
doc: |
  Une LnPos est une liste puissance n de positions avec n >= 0
  Pour n==0 c'est une position (Pos)
  Pour n==1 c'est une liste de positions (LPos)
  Pour n==2 c'est une liste de listes de positions (LLPos)
  ...
*/}
class LnPos {
  /*PhpDoc: methods
  name: toString
  title: "static function power(array $lnpos): int - renvoie la puissance de la LnPos ou -1 pour une liste vide, génère une exception si un array non liste est rencontré"
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
  static function test_power() {
    foreach(array_merge(Pos::EXAMPLES, LPos::EXAMPLES) as $lnpos)
    echo "LnPos::power(",json_encode($lnpos),")=", LnPos::power($lnpos),"<br>\n";
  }
  
  /*PhpDoc: methods
  name: toString
  title: "static function toString(array $lnpos): string - génère une chaine de caractères représentant la LnPos"
  */
  static function toString(array $lnpos): string { return json_encode($lnpos); }
  
  /*PhpDoc: methods
  name: wkt
  title: "static function wkt(array $lnpos): string - génère une chaine de caractères représentant la LnPos pour WKT"
  */
  static function wkt(array $lnpos): string {
    //echo "Appel de LnPos::wkt(",json_encode($lnpos),"<br>\n";
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return implode(' ',$lnpos);
    else
      return '('.implode(',',array_map(function(array $ln1pos): string { return self::wkt($ln1pos); }, $lnpos)).')';
  }
  static function test_wkt() {
    foreach([
      [],
      [0,1],
      [[0,1],[2,3]], // LPos
      [[[0,1],[2,3]]], // LLPos
      [[[[0,1],[2,3]]]], // LLLPos
    ] as $lnpos) {
      echo self::wkt($lnpos),"<br>\n";
    }
  }
  
  /*PhpDoc: methods
  name: aPos
  title: "static function aPos(array $lnpos): array - retourne la première position"
  */
  static function aPos(array $lnpos): array {
    if (!$lnpos) // $lnpos est la liste vide
      throw new \Exception("Erreur d'appel de LnPos::aPos() sur une liste de positions vide");
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return $lnpos;
    else
      return self::aPos($lnpos[0]);
  }
  
  /*PhpDoc: methods
  name: count
  title: "static function count(array $lnpos): int - calcul du nbre de positions"
  */
  static function count(array $lnpos): int {
    if (!$lnpos) // $lnpos est la liste vide
      return 0;
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return 1;
    else
      return array_sum(array_map(function(array $ln1pos): int { return self::count($ln1pos); }, $lnpos));
  }
  
  /*PhpDoc: methods
  name: sumncoord
  title: "static function sumncoord(array $lnpos, int $i): int - calcul de la somme de la i-ème coordonnée de chaque position"
  */
  static function sumncoord(array $lnpos, int $i): int {
    if (!$lnpos)
      return 0;
    elseif (Pos::is($lnpos)) // $lnpos est une Pos
      return $lnpos[$i];
    else
      return array_sum(array_map(function(array $ln1pos) use($i): int { return self::sumncoord($ln1pos, $i); }, $lnpos));
  }
  
  /*PhpDoc: methods
  name: center
  title: "static function center(array $lnpos, int $precision): array - calcule le centre d'une liste**n de positions, génère une erreur si la liste est vide"
  */
  static function center(array $lnpos, int $precision): array {
    if (!$lnpos)
      throw new \Exception("Erreur d'appel de LnPos::center() sur une liste de positions vide");
    $nbre = self::count($lnpos);
    return [round(self::sumncoord($lnpos, 0)/$nbre, $precision), round(self::sumncoord($lnpos, 1)/$nbre, $precision)];
  }
  static function test_center() {
    $lpos = [[1,2],[3,7,5]];
    echo "<pre>LnPos::count(",LnPos::toString($lpos),")="; print_r(LnPos::count($lpos));
    echo "<pre>LnPos::sumncoord(",LnPos::toString($lpos),", 0)="; print_r(LnPos::sumncoord($lpos, 0));
    echo "<pre>LnPos::sumncoord(",LnPos::toString($lpos),", 1)="; print_r(LnPos::sumncoord($lpos, 1));
    echo "<pre>LnPos::center(",LnPos::toString($lpos),",1)=",json_encode(LnPos::center($lpos,1)),"<br>\n";
  }
  
  /*PhpDoc: methods
  name: projLn
  title: "static function projLn(callable $projPos, int $n, array $coords): array - applique à chaque Pos de la LnPos la function $projPos et retourne la LnPos reconstruite"
  doc: |
    $projPos : function(Pos): Pos
    $coords : LnPos
  */
  static function projLn(array $lnpos, callable $projPos): array {
    if (Pos::is($lnpos)) // $lnpos est une Pos
      return $projPos($lnpos);
    else
      return array_map(function(array $ln1pos) use($projPos) { return self::projLn($ln1pos, $projPos); }, $lnpos);
  }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'LnPos'); // Test unitaire de la classe LnPos
