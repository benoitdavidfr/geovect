# Bibliothèque Php de gestion de la géométrie

## 1. Généralités
Cette bibliothèque Php définit d'une part des fonctions géométriques et d'autre part des classes Php de gestion
des primitives géométriques [GeoJSON](https://tools.ietf.org/html/rfc7946)
et [OGC WKT](https://en.wikipedia.org/wiki/Well-known_text).  
Elle comprend tout d'abord des classes statiques dans lesquelles sont définies un certain nombre de fonctions géométriques
et, d'autre part, la classe abstraite Geometry ainsi que les 7 sous-classes suivantes correspondant
aux différentes primitives géométriques :
Point, MultiPoint, LineString, MultiLineString, Polygon, MultiPolygon et GeometryCollection

Elle comprend en outre :

  - la classe abstraite `BBox` qui définit les boites englobantes ainsi que 2 sous-classes `GBox` et `EBox` définissant
    respectivement les boites en coordonnées géographiques et euclidiennes,
  - la classe abstraite `Drawing` définissant l'interface d'un dessin et la classe `GdDrawing` implémentant cette interface
    avec GD.
  - la classe `FeatureStream` permettant de lire un flux de Feature GeoJSON pour quelques couches particulières.

La bibliothèque est compatible avec Php 8.1, documentée avec [PhpDocumentor](https://docs.phpdoc.org/3.0/)
et testée avec [PhpStan](https://phpstan.org/) au niveau 6.
Cette documentation utilise donc les types de PhpDocumentor utilisés dans PhpStan.

### 1.1. Le concept de position
La bibliothèque reprend de GeoJSON le concept de **position** constituée de 2 coordonnées éventuellement complétées
par une altitude.
Une position est définie en Php comme une liste de 2 ou 3 nombres et n'est pas un objet Php.
Une position indéfinie peut être représentée de manière standardisée par la liste vide mais n'est pas une position valide.
Dans cette bibliothèque une position peut être soit définie en coordonnées géographiques (longitude, latitude)
en degrés décimaux soit dans un système de coordonnées projeté.
Ainsi, les coordonnées des objets géométriques sont définies:

  - pour un Point, par une position, notée Pos et dont le type PhpStan est `TPos`,
  - pour une LineString ou un MultiPoint, par une liste de positions, notée LPos et dont le type PhpStan est `TLPos`,
  - pour un Polygon ou un MultiLineString, par une liste de listes de positions, notée LLPos
    et dont le type PhpStan est `TLLPos`,
  - pour un MultiPolygon, par une liste de listes de listes de positions, notée LLLPos.

L'utilisation de ce concept de position simplifie la création des objets correspondants aux primitives
puisque la structure est très proche de celle de GeoJSON.

### 1.2. Les changements de systèmes de coordonnées
La bibliothèque est indépendante des systèmes de coordonnées.
Les coordonnées géographiques sont cependant distinguées des coordonnées euclidiennes lorsque les fonctionnalités
sont différentes.
Les changements de systèmes de coordonnées sont effectués en passant en paramètre aux méthodes proj() ou geo()
une fonction anonyme qui fait correspondre une position en coord. projetées à une position en coord. géo.
ou vice-versa.  
Cette bibliothèque a été conçue pour permettre d'utiliser facilement les fonctions définies
dans la [bibliothèque CoordSys](https://github.com/benoitdavidfr/geovect/tree/master/coordsys).

### 1.3. Dessin des primitives géométriques
Les primitives géométriques peuvent se dessiner dans un objet dessin.
Afin de rendre la bibliothèque indépendante des différentes techniques de dessin (GD, SVG, ...), elle utilise des primitives
génériques de dessin définies par la classe abstraite `Drawing`.
Elles utilisent un style de dessin défini
par la [spec simplestyle](https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0).  
La classe `GdDrawing` implémente ces primitives au dessus de la [bibliothèque GD](https://www.php.net/manual/fr/ref.image.php). 

### 1.4. Pourquoi une nouvelle bibliothèque ?
Cette bibliothèque redéfinit des fonctionnalités proches de [geometry](https://github.com/benoitdavidfr/geometry)
et de [geom2d](https://github.com/benoitdavidfr/geom2d).
Elle apporte principalement 4 spécificités :

  - structuration des coordonnées des primitives comme listes de positions, très proche de l'approche GeoJSON,
    et simplifiant plusieurs algorithmes,
  - définition d'une classe abstraite BBox et de 2 classes concrètes GBox et EBox respt. pour les coord. géographiques
    et euclidiennes ; permettant ainsi dans le code de mieux distinguer le type de BBox utilisé,
  - indépendance par rapport aux changements de systèmes de coordonnées en utilisant en paramètre une fonction de changement
    de coordonnées,
  - indépendance par rapport aux techniques de dessin en définissant des primitives de dessin et une classe abstraite de dessin.

Les fonctionnalités de [geometry](https://github.com/benoitdavidfr/geometry) ont été largement reprises dans gegeom.  
Celles de [geom2d](https://github.com/benoitdavidfr/geom2d) ne l'ont été que partiellement,
notamment celles de tuilage.

## 2. Les fonction géométriques
### 2.1 Définition de classes statiques
Les fonctions géométriques sont définies dans les 4 classes statiques suivantes:

  - **Pos** pour les fonctions dont le premier paramètre est une position
  - **LPos** pour les fonctions dont le premier paramètre est une liste de positions
  - **LLPos** pour les fonctions dont le premier paramètre est une liste de listes de positions
  - **LnPos** pour les fonctions dont le premier paramètre est une liste**n de positions

### 2.2 Fonctions statiques définies dans la classe Pos
Dans certains cas, une position peut être interprétée comme un vecteur.

- `is(mixed $pos): bool` - teste si $pos est une position, permet notament de distinguer Pos, LPos, LLPos et LLLPos
  mais ne vérifie pas la validité de $pos
- `getErrors(mixed $pos): array` - renvoie les raisons pour lesquelles $pos n'est pas une position
- `fromGeoDMd() TPos`- décode une position en coords géographiques en degré minutes décimales conforme
  au motif suivant `!^(\d+)°((\d\d)(,(\d+))?\')?(N|S) - (\d+)°((\d\d)(,(\d+))?\')?(E|W)$!  
  exemple: `45°23,45'N - 1°12'W`
- `formatInGeoDMd(TPos $pos, float $resolution): string` - Formate une position (lon,lat) en lat,lon degrés,
  minutes décimales, $resolution est la résolution de la position en degrés à conserver
- `diff(TPos $pos, TPos $v): TPos` - $pos - $v en 2D où $pos et $v sont 2 positions
- `vectorProduct(TPos $u, TPos $v): float` - produit vectoriel $u par $v en 2D
- `scalarProduct(TPos $u, TPos $v): float` - produit scalaire $u par $v en 2D
- `norm(TPos $u): float` - norme de $u en 2D
- `distance(TPos $a, TPos $b): float` - distance euclidienne entre les positions $a et $b
- `distancePosLine(TPos $pos, TPos $a, TPos $b): float` - distance signée de la position $pos à la droite définie
  par les 2 positions $a et $b ;
  la distance est positive si le point est à gauche de la droite AB et négative s'il est à droite

  
    class Pos {  
  
      /**
       * distancePosLine() - 
       *
       * 
       *
       * @param  $pos
       * @param TPos $a
       * @param TPos $b
       * @return float
       */
      static function  {
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

### 3.3 La classe LPos
### 3.4 La classe LLPos
### 3.5 La classe LnPos


## 2. Les boites englobantes
### 2.1. La classe abstraite BBox
La classe BBox définit une boite englobante définie par 2 positions min et max.
Ces 2 positions peuvent ne pas être définies et dans ce cas la boite est dite **vide**.
Si une boite n'est pas vide alors min et max contiennent chacun une position définie.

#### Méthodes
Elle comporte les méthodes suivantes:

  - `__construct(...$params)` - initialise une boite en fonction du paramètre.  
    - Sans paramètre la boite est initialisée indéterminée.  
    - Si c'est un array de 2 ou 3 nombres, ou une chaine correspondant à 2 ou 3 nombres, interprétés comme une position, alors la boite est définie par cette position,  
    - Si c'est un array de 4 ou 6 nombres, ou une chaine correspondant à 4 ou 6 nombres, interprétés
      comme 2 positions, alors la boite est définie par ces 2 positions,  
    - Si c'est une liste de positions, ou une liste de listes de positions, alors la boite est la boite minimum contenant
      toutes ces positions,
  - `__toString(): string` -  chaine représentant la boite avec des coord. arrondies
  - `empty(): bool` - indique si la boite est vide ou non
  - `posInBBox(array $pos): bool` - teste si une position est dans la bbox considérée comme fermée à gauche et ouverte à droite
  - `bound(array $pos): BBox` - ajoute une position à la boite et renvoie la boite modifiée
  - `asArray(): array` - renvoie [xmin, ymin, xmax, ymax] ou []
  - `center(): array` - position du centre de la boite ou [] si elle est indéterminée
  - `polygon(): array` - liste de listes de positions avec les 5 pos. du polygone de la boite ou [] si elle est indéterminée
  - `union(BBox $b2): BBox` - agrandit la boite courante pour contenir la boite en paramètre et renvoie la boite courante
  - `intersects(BBox $b2): ?BBox` - retourne l'intersection des 2 boites si elles s'intersectent, sinon null
  - `size(): float` - longueur du plus grand côté
  - `dist(BBox $b2): float` - distance la plus courte entre les positions des 2 BBox, génère une erreur si une des 2 est
    indéterminée
  - `distance(BBox $b2): float` - distance entre 2 boites, nulle ssi les 2 boites sont identiques
  - //`isIncludedIn(BBox $bbox1):bool` - teste si this est inclus dans bbox1 (NON IMPLEMENTEE)
  
### 2.2. La classe concrète GBox
La classe GBox définit des boites englobantes en coordonnées géographiques.

#### Méthodes
Outre les méthodes génériques de BBox, la méthode suivante est définie :

  - `proj(callable $projPos): EBox` - projection d'un GBox selon la fonction anonyme de projection en paramètre
  
### 2.3. La classe concrète EBox
La classe EBox définit des boites englobantes en coordonnées euclidiennes.

#### Méthodes
Outre les méthodes génériques de BBox, les méthodes suivantes sont définies :

  - `area(): float` - surface de la boite
  - `covers(EBox $b2): float` - taux de couverture de $b2 par $this
  - `geo(callable $projPos): GBox` - calcule les coord. géo. en utilisant la fonction anonyme en paramètre

## 3. Les 7 primitives géométriques et leur sur-classe abstraite
### 3.1. La classe abstraite Geometry
La classe abstraite Geometry permet de gérer a minima une géométrie sans avoir à connaître son type
et porte aussi 2 méthodes statiques de construction d'objet à partir respectivement d'un GeoJSON ou d'un WKT. 

#### Méthodes
Elle définit les méthodes suivantes:
  
  - `static fromGeoJSON(array $geom, string $prefix=''): Geometry` - crée une géométrie
    à partir d'une géométrie GeoJSON fournie comme array Php créé par json_decode(),
  - `static fromWkt(string $wkt, string $prefix=''): Geometry` - crée une géométrie à partir d'un WKT
  - `__construct(array $coords, array $style=[])` - initialise une géométrie à partir des coordonnées, comme position,
    liste de positions, ..., et d'un éventuel style,
  - `coords(): array` - retourne les coordonnées comme position ou liste de positions ou liste de listes ...,
    retourne [] pour une GeometryCollection,
  - `asArray(): array` - retourne la représentation GeoJSON comme array Php
  - `__toString(): string` - retourne la représentation GeoJSON en string
  - `wkt(): string` - retourne la représentation WKT
  - `isValid(): bool` - retourne vrai ssi l'objet est valide
  - `getErrors(): array` - retourne l'arbre des erreurs si l'objet est invalide, sinon renvoie []
  - `proj2D(): Geometry` - projection 2D, supprime l'éventuelle 3ème coordonnée, renvoie un nouveau Geometry
  - `center(): array` - retourne le centre de l'objet comme position
  - `aPos(): array` - retourne une position de l'objet
  - `gbox(): GBox` - retourne le BBox de l'objet considéré en coordonnées géographiques
  - `ebox(): EBox` - retourne le BBox de l'objet considéré en coordonnées euclidiennes
  - `proj(callable $projPos): Geometry` - retourne un nouvel objet de la même classe en changeant de système de coordonnées en appliquant la fonction anonyme passée en paramètre à chaque position,
    retourne un nouvel objet de la même classe que l'objet d'origine,
  - `nbPoints(): int` - retourne le nombre de points de l'objet (pas de positions)
  - `length(): float` - retourne la longueur de l'objet dans le système de coordonnées (ne pas utiliser avec des coord. géo.)
  - `area(array $options=[]): float` - retourne la surface de l'objet, par défaut cette surface est positive ssi la géométrie
    tourne dans le sens des aiguilles d'une montre,
    si $options['noDirection'] est défini et vrai alors le calcul ne tient pas compte du sens  
    (ne pas utiliser avec des coord. géo.)
  - `filter(int $precision=9999): Geometry` - filtre la géométrie en supprimant les points intermédiaires successifs identiques,
    le paramètre `$precision` donne le nombre de chiffres signficatifs à conserver, une valeur par défaut est définie,
    renvoie un nouvel objet de la même classe que l'objet d'origine,
  - `simplify(float $distTreshold): Geometry` - simplifie la géométrie en utilisant la méthode de Douglas & Peucker,
    le paramètre `$distTreshold` est la distance minimum d'un point au segment père,
    renvoie un nouvel objet de la même classe que l'objet d'origine,
  - `draw(Drawing $drawing, array $style)` - dessine l'objet dans le dessin $drawing avec le style $style inspiré
    de [la spec simplestyle](https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0).
  

### 3.2. La classe Point
La classe Point implémente la primitive Point en 2D ou 3D défini par une position ;
pour certaines méthodes l'objet est considéré comme un vecteur.  
Elle hérite de la classe Geometry.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `distance(array $pos): float` - distance euclidienne entre le point $this et la position $pos
  - `add($v): Point` - somme vectorielle 2D $this + $v, où $v est un Point ou une position
  - `diff($v): Point` - différence vectorielle 2D $this - $v, où $v est un Point ou une position
  - `norm(): float` - norme du vecteur, cad la distance euclidienne entre 2 points
  - `vectorProduct(Point $v): float` - produit vectoriel $this * $v en 2D
  - `scalarProduct(Point $v): float` - produit scalaire $this * $v en 2D
  - `scalMult(float $scal): Point` - multiplication de $this considéré comme un vecteur par un scalaire
  - `distancePointLine(array $a, array $b): float` - distance signée du point courant à la droite définie
    par les 2 positions `$a` et `$b`  
    La distance est positive si le point est à gauche de la droite AB et négative s'il est à droite
  - `projPointOnLine(array $a, array $b): float` - projection du point sur la droite (A,B),  
    renvoie u / P' = A + u * (B-A). u == 0 <=> P'== A, u == 1 <=> P' == B
    Le point projeté est sur le segment ssi u est dans [0 .. 1].
  - // `round(int $nbdigits): Point` - arrondit un point avec le nb de chiffres indiqués (NON IMPLEMENTEE)
  
### 3.3. La classe MultiPoint
La classe MultiPoint implémente la primitive MultiPoint correspondant à une liste de positions.  
Elle hérite de la classe Geometry et ne définit aucune méthode spécifique.

### 3.4. La classe LineString
La classe LineString implémente la primtive LineString en 2D ou 3D correspondant à une ligne brisée.
Elle hérite de la classe `Geometry`.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `isClosed(): bool` - teste la fermeture de la liste brisée (cad premier et dernier points identiques)
  - // `distancePointPointList(Point $pt): array` - distance minimum d'une liste de points au point pt (NON IMPLEMENTE)  
    retourne la distance et le no du point qui correspond à la distance minimum sous la forme ['dist'=>$dist, 'n'=>$n]
  - // `distancePointLineString(Point $pt): array` - distance minimum de la ligne brisée au point pt (NON IMPLEMENTE)  
    retourne la distance et le point qui correspond à la distance minimum sous la forme ['dmin'=>$dmin, 'pt'=>$pt]

### 3.5. La classe MultiLineString
La classe MultiLineString implémente la primitive MultiLineString correspondant à une liste de lignes brisées.  
Elle hérite de la classe Geometry et ne possède aucune méthode spécifique.

### 3.6. La classe Polygon
La classe Polygon implémente la primitive Polygon correspondant à un extérieur défini par une ligne brisée fermée
et d'éventuels trous chacun défini comme une ligne brisée fermée.
Elle hérite de la classe Geometry.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `posInPolygon(array $pos): bool` - teste si une position est dans le polygone
  - `inters(Geometry $geom): bool` - teste l'intersection entre un polygone et soit un polygone soit multi-polygone
  - // `addHole(LineString $hole): void` - ajoute un trou au polygone (NON IMPLEMENTE) 

### 3.7. La classe MultiPolygon
La classe MultiPolygon implémente la primitive MultiPolygon correspondant à une liste de polygones.  
Elle hérite de la classe Geometry.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `inters(Geometry $geom): bool` - teste l'intersection entre un multi-polygone et soit un polygone soit un multi-polygone
  - //`pointInPolygon(array $pos): bool` - teste si une position est dans le polygone (NON IMPLEMENTE)

### 3.8. La classe GeometryCollection
La classe GeometryCollection implémente la primitive GeometryCollection correspondant à une liste
d'objets élémentaires.  
Elle hérite de la classe Geometry et ne possède aucune méthode spécifique.

## 4. Le dessin des primitives géométriques
### 4.1. La classe abstraite Drawing
La classe abstraite `Drawing` définit l'interface à respecter par une classe de dessin.  
Le paramètre `$style` respecte les principes définis
par la [spec simplestyle](https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0), notamment:

  - stroke: couleur RVB de dessin d'une ligne brisée ou d'un contour de polygone
  - stroke-opacity : opacité entre 0 (transparent) et 1 (opaque)
  - fill: couleur RVB de remplissage d'un polygone
  - fill-opacity : opacité entre 0 (transparent) et 1 (opaque)

#### Méthodes
Elle définit les méthodes abstraites suivantes:

  - `__construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1)` - initialisation du dessin  
    - `$width` et $height indiquent la taille du dessin sur l'écran en nbre de pixels  
    - `$world` défini le système de coordonnées utilisateur, par défaut [-180, -90, 180, 90]  
    - `$bgColor` est la couleur de fond du dessin codé en RGB
    - `$bgOpacity` est l'opacité du fond du dessin entre 0 (transparent) et 1 (opaque)
  - `polyline(array $lpos, array $style): void` - dessine une ligne brisée
    - `$lpos` est une liste de positions en coordonnées utilisateur
    - `$style` est le style de dessin
  - `polygon(array $llpos, array $style): void` - dessine une ligne brisée
    - `$llpos` est une liste de listes de positions en coordonnées utilisateur
    - `$style` est le style de dessin
  - `flush(string $format='', bool $noheader=false): void` - affiche l'image construite
    - `$format` est le format MIME d'affichage
    - si `$noheader` est vrai alors le header n'est pas transmis

### 4.2. La classe concrète GdDrawing
La classe GdDrawing implémente les primtives de dessin avec la [bibliothèque GD](https://www.php.net/manual/fr/ref.image.php).

## 5. Les flux de Feature
### 5.1. La classe FeatureStream
La classe `FeatureStream` facilite l'accès à quelques flux de Feature.  
Cette classe propose, d'une part, une constante LAYERS qui retourne les couches disponibles
et d'autre part implémente un constructeur prenant en paramètres l'URI de la couche
et permettant d'itérer sur les objets de la couche ainsi définie.
L'itérateur renvoie un array respectant le format des Feature GeoJSON.


