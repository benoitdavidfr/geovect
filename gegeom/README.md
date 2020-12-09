# Bibliothèque Php de gestion de la géométrie

## 1. Généralités
Cette bibliothèque Php définit des classes Php de gestion des primitives géométriques
[GeoJSON](https://tools.ietf.org/html/rfc7946) et [OGC WKT](https://en.wikipedia.org/wiki/Well-known_text).  
Elle comprend, tout d'abord, la classe abstraite Geometry ainsi que les 7 sous-classes suivantes correspondant
aux différentes primitives géométriques :
Point, MultiPoint, LineString, MultiLineString, Polygon, MultiPolygon et GeometryCollection

Elle comprend en outre :

  - la classe abstraite `BBox` qui définit les boites englobantes ainsi que 2 sous-classes `GBox` et `EBox` définissant
    respectivement les boites en coordonnées géographiques et euclidiennes,
  - la classe abstraite `Drawing` définissant l'interface d'un dessin et la classe `GdDrawing` implémentant cette interface
    avec GD.
  - la classe `FeatureStream` permettant de lire un flux de Feature GeoJSON pour quelques couches particulières.

La bibliothèque a été mise en conformité avec Php 8.

### 1.1. Le concept de position
La bibliothèque reprend de GeoJSON le concept de **position** constituée de 2 coordonnées éventuellement complétées par une altitude.
Une position est définie en Php comme une liste de 2 ou 3 nombres et n'est pas un objet Php.
Une position indéfinie peut être représentée de manière standardisée par la liste vide mais n'est pas une position valide.
Dans cette bibliothèque une position peut être soit définie en coordonnées géographiques (longitude, latitude) en degrés décimaux
soit dans un système de coordonnées projeté.
Ainsi, les coordonnées des objets géométriques sont définies:

  - pour un Point, par une position, notée Pos,
  - pour une LineString ou un MultiPoint, par une liste de positions, notée LPos,
  - pour un Polygon ou un MultiLineString, par une liste de listes de positions, notée LLPos,
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

### 1.4. Porquoi une nouvelle bibliothèque ?
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

## 2. Les boites englobantes
### 2.1. La classe abstraite BBox
La classe BBox définit une boite englobante définie par 2 positions min et max.
Ces 2 positions peuvent ne pas être définies et dans ce cas la boite est dite **indéterminée**.
Si une boite n'est pas indéterminée alors min et max contiennent chacun une position définie.

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
  - `defined(): bool` - indique si la boite est déterminée ou non
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
Elle permet d'associer à cette géométrie un style inspiré
de [la spec simplestyle](https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0).

#### Méthodes
Elle définit les méthodes suivantes:
  
  - `static fromGeoJSON(array $geom, string $prefix=''): Geometry` - crée une géométrie
    à partir d'une géométrie GeoJSON fournie comme array Php créé par json_decode(),
  - `static fromWkt(string $wkt, string $prefix=''): Geometry` - crée une géométrie à partir d'un WKT
  - `__construct(array $coords, array $style=[])` - initialise une géométrie à partir des coordonnées, comme position,
    liste de positions, ..., et d'un éventuel style,
  - `coords(): array` - retourne les coordonnées comme position ou liste de positions ou liste de listes ...,
    retourne [] pour une GeometryCollection,
  - `geoms(): array` - pour une GeometryCollection retourne la liste des objets contenus dans l'objet,
    pour les autres types retourne le singleton composé de l'objet,
  - `asArray(): array` - retourne la représentation GeoJSON comme array Php
  - `__toString(): string` - retourne une représentation en string
  - `geojson(): string` - retourne la représentation GeoJSON en string
  - `wkt(): string` - retourne la représentation WKT
  - `isValid(): bool` - retourne vrai ssi l'objet est valide
  - `getErrors(): array` - retourne l'arbre des erreurs si l'objet est invalide, sinon renvoie []
  - `proj2D(): Geometry` - projection 2D, supprime l'éventuelle 3ème coordonnée, renvoie un nouveau Geometry
  - `center(): array` - retourne le centre de l'objet comme position
  - `aPos(): array` - retourne une position de l'objet
  - `bbox(): GBox` - retourne le BBox de l'objet considéré en coordonnées géographiques
  - `ebox(): EBox` - retourne le BBox de l'objet considéré en coordonnées euclidiennes
  - `proj(callable $projPos): Geometry` - change de système de coordonnées en appliquant la fonction anonyme passée en paramètre,
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
  - `draw(Drawing $drawing, array $style)` - dessine l'objet dans le dessin $drawing avec le style $style

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
La classe LineString implémente la primtive LineString en 2D ou 3D correspondant à une liste brisée.
Elle hérite de la classe `Geometry`.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `isClosed(): bool` - teste la fermeture de la liste brisée (premier de dernier points identiques)
  - // `distancePointPointList(Point $pt): array` - distance minimum d'une liste de points au point pt (NON IMPLEMENTE)  
    retourne la distance et le no du point qui correspond à la distance minimum sous la forme ['dist'=>$dist, 'n'=>$n]
  - // `distancePointLineString(Point $pt): array` - distance minimum de la ligne brisée au point pt (NON IMPLEMENTE)  
    retourne la distance et le point qui correspond à la distance minimum sous la forme ['dmin'=>$dmin, 'pt'=>$pt]

### 3.5. La classe MultiLineString
La classe MultiLineString implémente la primitive MultiLineString correspondant à une liste de lignes brisées.  
Elle hérite de la classe Geometry et ne possède aucune méthode spécifique.

### 3.6. La classe Polygon
La classe Polygon implémente la primtive Polygon correspondant à un extérieur défini par une ligne brisée fermée
et d'éventuels trous chacun défini comme une ligne brisée fermée.
Elle hérite de la classe Geometry.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `posInPolygon(array $pos): bool` - teste si une position est dans le polygone
  - `inters(Geometry $geom): bool` - teste l'intersection entre avec un polygone ou multi-polygone
  - // `addHole(LineString $hole): void` - ajoute un trou au polygone (NON IMPLEMENTE) 

### 3.7. La classe MultiPolygon
La classe MultiPolygon implémente la primitive MultiPolygon correspondant à une liste de polygones.  
Elle hérite de la classe Geometry.

#### Méthodes
Outre les méthodes génériques de Geometry, les méthodes suivantes sont définies :

  - `inters(Geometry $geom): bool` - teste l'intersection entre les 2 polygones ou multi-polygones
  - //`pointInPolygon(array $pos): bool` - teste si une position est dans le polygone (NON IMPLEMENTE)

### 3.8. La classe GeometryCollection
La classe GeometryCollection implémente la primitive GeometryCollection correspondant à une liste
d'objets élémentaires.  
Elle hérite de la classe Geometry et ne possède aucune méthode spécifique.

## 4. Le dessin des primtives géométriques
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


