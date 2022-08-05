<?php
namespace gegeom;
/*PhpDoc:
name:  drawing.inc.php
title: drawing.inc.php - classe abstraite de dessin des géométries de gegeom
classes:
doc: |
  Ce fichier définit la classe abstraite Drawing
  Cette classe est testée par son utilisation dans ../coordsys/draw
journal: |
  4-5/8/2022:
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
  14/5/2019:
    - modif ordre des paramètres de Drawing::_construct()
  5/5/2019:
    - création
*/
/**
 * abstract class Drawing - classe abstraite définisant des noms de couleurs ainsi que les primtives de dessin
 *
 * Cette classe est utilisée pour typer les méthodes de dessin ce que ne permet pas le mécanisme Php d'interface
 * qui ne peut ainsi pas être utilisé à la place.
 */
abstract class Drawing {
  /** const COLORNAMES - quelques noms utiles de couleurs, voir https://en.wikipedia.org/wiki/Web_colors */
  const COLORNAMES = [
    'DarkOrange'=> 0xFF8C00,
  ];
  
  /**
   * __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) - initialisation du dessin
   *
   * @param int $width largeur du dessin sur l'écran en nbre de pixels
   * @param int $height hauteur du dessin sur l'écran en nbre de pixels
   * @param BBox $world système de coordonnées utilisateur
   * @param int $bgColor couleur de fond du dessin codé en RGB, ex. 0xFFFFFF
   * @param float $bgOpacity opacité du fond entre 0 (transparent) et 1 (opaque)
  */
  abstract function __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1);

  /**
   * polyline(array $lpos, array $style=[]): void - dessine une ligne brisée
   *
   * @param TLPos $lpos liste de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  abstract function polyline(array $lpos, array $style=[]): void;
  
  /**
   * polygon(array $llpos, array $style=[]): void - dessine un polygone
   *
   * @param TLLPos $llpos liste de listes de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  abstract function polygon(array $llpos, array $style=[]): void;
     
  /**
   * flush(string $format='', bool $noheader=false): void - affiche l'image construite
   *
   * @param string $format format MIME d'affichage
   * @param bool $noheader si vrai alors le header n'est pas transmis
  */
  abstract function flush(string $format='', bool $noheader=false): void;
};

/**
 * class DumbDrawing extends Drawing - classe concrète ne produisant rien, utile pour des vérifications formelles
 */
class DumbDrawing extends Drawing {
  function __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) {} // @phpstan-ignore-line
  /**
   * polyline(array $lpos, array $style=[]): void - dessine une ligne brisée
   *
   * @param TLPos $lpos liste de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polyline(array $lpos, array $style=[]): void {}
  /**
   * polygon(array $llpos, array $style=[]): void - dessine un polygone
   *
   * @param TLLPos $llpos liste de listes de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polygon(array $llpos, array $style=[]): void {}
  function flush(string $format='', bool $noheader=false): void {}
};
