<?php
namespace gegeom;
{/*PhpDoc:
name:  drawing.inc.php
title: drawing.inc.php - classe abstraite de dessin des géométries de gegeom
classes:
doc: |
  Ce fichier définit la classe abstraite Drawing
  Cette classe est testée par son utilisation dans ../coordsys/draw
journal: |
  14/5/2019:
    - modif ordre des paramètres de Drawing::_construct()
  5/5/2019:
    - création
*/}
{/*PhpDoc: classes
name: Drawing
title: abstract class Drawing - classe abstraite définisant des noms de couleurs ainsi que les primtives de dessin
methods:
doc: |
  Cette classe est utilisée pour typer les méthodes de dessin ce que ne permet pas le mécanisme Php d'interface
  qui ne peut ainsi pas être utilisé à la place.
*/}
abstract class Drawing {
  /*PhpDoc: methods
  name: COLORNAMES
  title: "const COLORNAMES - quelques noms utiles de couleurs, voir https://en.wikipedia.org/wiki/Web_colors"
  */
  const COLORNAMES = [
    'DarkOrange'=> 0xFF8C00,
  ];
  
  /*PhpDoc: methods
  name: __construct
  title: "function __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) - initialisation du dessin"
  doc: |
    $width et $height indiquent la taille du dessin sur l'écran en nbre de pixels
    $world défini le système de coordonnées utilisateur
    $bgColor est la couleur de fond du dessin codé en RGB
    $bgOpacity est l'opacité du fond entre 0 (transparent) et 1 (opaque)
  */
  abstract function __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1);

  /*PhpDoc: methods
  name: polyline
  title: "function polyline(array $lpos, array $style=[]): void - dessine une ligne brisée"
  doc: |
    $lpos est une liste de positions en coordonnées utilisateur
    $style est le style de dessin
  */
  abstract function polyline(array $lpos, array $style=[]): void;
  
  /*PhpDoc: methods
  name: polygon
  title: "function polygon(array $llpos, array $style=[]): void - dessine un polygone"
  doc: |
    $llpos est une liste de listes de positions en coordonnées utilisateur
    $style est le style de dessin
  */
  abstract function polygon(array $llpos, array $style=[]): void;
     
  /*PhpDoc: methods
  name: flush
  title: "function flush(string $format='', bool $noheader=false): void - affiche l'image construite"
  doc: |
    $format est le format MIME d'affichage
    si $noheader est vrai alors le header n'est pas transmis
  */
  abstract function flush(string $format='', bool $noheader=false): void;
};

{/*PhpDoc: classes
name: DumbDrawing
title: class DumbDrawing extends Drawing - classe concrète ne produisant rien, utile pour des vérifications formelles
methods:
*/}
class DumbDrawing extends Drawing {
  function __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) {}
  function polyline(array $lpos, array $style=[]): void {}
  function polygon(array $llpos, array $style=[]): void {}
  function flush(string $format='', bool $noheader=false): void {}
};
