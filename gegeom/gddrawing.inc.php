<?php
namespace gegeom;
/*PhpDoc:
name:  gddrawing.inc.php
title: gddrawing.inc.php - dessin des géométries de gegeom utilisant GD + copie & rééchantillonnage d'image
classes:
doc: |
  Ce fichier définit la classe GdDrawing implémentant:
    - la classe abstraite Drawing en utilisant GD
    - les méthodes imagecopy() et resample() qui permettent d'utiliser un fond raster
  Classe utilisée et testée par:
    - /coordsys/draw.php (dessiner un planisphère) - pour les méthodes de dessin vecteur
    - /geoapi/ignpx/wmst.php (proxy WMS du serveur WMTS IGN) - pour imagecopy() et resample()
journal: |
  5/8/2022:
   - corrections suite à analyse PhpStan level 6
   - structuration de la doc conformément à phpDocumentor
  22/5/2019:
    - modif de GdDrawing::proj() pour dessiner correctement l'Antarctique en WM
  19/5/2019:
    - gestion des trous dans polygon(), pas satisfaisant
  14/5/2019:
    - modif ordre des paramètres de Drawing::__construct()
  4/5/2019:
    - changement de logique
      - les primitives de dessin sont intégrées dans les primitives géométriques
      - la généricité est mise en oeuvre par la classe Drawing dont hérite GdDrawing
      - renommage de la classe en GdDrawing
  27/4/2019:
    - création
includes: [drawing.inc.php]
*/
require_once __DIR__.'/drawing.inc.php';

/**
 * class GdDrawing extends Drawing - classe implémentant un dessin utilisant les primitives GD + copie & rééchantillonnage d'image
 *
 * Un dessin définit un système de coord. utilisateurs, une taille d'image d'affichage et une couleur de fond.
 * Il définit des méthodes de dessin d'une ligne brisée et d'un polygone.
 * Il permet aussi de rééchantilloner l'image dessinée, pour par ex. modifier son échelle.
 */
class GdDrawing extends Drawing {
  const ErrorCreate = 'GdDrawing::ErrorCreate';
  const ErrorCreateFromPng = 'GdDrawing::ErrorCreateFromPng';
  const ErrorCopy = 'GdDrawing::ErrorCopy';
  const ErrorColorAllocate = 'GdDrawing::ErrorColorAllocate';
  const ErrorFilledRectangle = 'GdDrawing::ErrorFilledRectangle';
  const ErrorRectangle = 'GdDrawing::ErrorRectangle';
  const ErrorPolyline = 'GdDrawing::ErrorPolyline';
  const ErrorPolygon = 'GdDrawing::ErrorPolygon';
  const ErrorFilledPolygon = 'GdDrawing::ErrorFilledPolygon';
  const ErrorDrawString = 'GdDrawing::ErrorDrawString';
  const ErrorSaveAlpha = 'GdDrawing::ErrorSaveAlpha';
  
  protected EBox $world; // rectangle englobant définissant le système de coordonnées utilisateur
  protected int $width; // largeur de l'écran
  protected int $height; // hauteur de l'écran
  /** @var array<int, int> $colors */
  protected array $colors=[]; // table des couleurs [RGBA => int]
  protected mixed $im; // l'image comme resource
  
  /**
   * __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) - initialise
   *
   * @param int $width largeur du dessin sur l'écran en nbre de pixels
   * @param int $height hauteur du dessin sur l'écran en nbre de pixels
   * @param EBox $world système de coordonnées utilisateur
   * @param int $bgColor couleur de fond du dessin codé en RGB, ex. 0xFFFFFF
   * @param float $bgOpacity opacité du fond entre 0 (transparent) et 1 (opaque)
   */
  function __construct(int $width, int $height, ?BBox $world=null, int $bgColor=0xFFFFFF, float $bgOpacity=1) {
    //printf("Drawing::__construct(%d, %d, $world, %x, %f)<br>\n", $width, $height, $bgColor, $bgOpacity);
    if (($width <= 0) || ($width > 100000))
      throw new \SExcept("width=$width dans GdDrawing::__construct() incorrect", self::ErrorCreate);
    if (($height <= 0) || ($height > 100000))
      throw new \SExcept("height=$height dans GdDrawing::__construct() incorrect");
    $this->world = $world ?? new GBox([-180, -90, 180, 90]);
    if (($this->world->north() - $this->world->south())==0)
      throw new \SExcept("Erreur north - south == 0 dans GdDrawing::__construct()", self::ErrorCreate);
    $ratio = ($this->world->east() - $this->world->west()) / ($this->world->north() - $this->world->south());
    if ($width / $height > $ratio) {
      $this->height = $height;
      $this->width = intval(round($height * $ratio));
    }
    else {
      $this->width = $width;
      $this->height = intval(round($width / $ratio));
      //echo "height = round($width / $ratio) = $this->height<br>\n";
    }
    //print_r($this);
    if (!($this->im = imagecreatetruecolor($this->width, $this->height)))
      throw new \SExcept("erreur de imagecreatetruecolor($this->width, $this->height)", self::ErrorCreate);
    // remplissage dans la couleur de fond
    if (!imagealphablending($this->im, false))
      throw new \SExcept("erreur de imagealphablending()", self::ErrorCreate);
    $bgcolor = $this->colorallocatealpha($bgColor, $bgOpacity);
    if (!imagefilledrectangle($this->im, 0, 0, $this->width - 1, $this->height - 1, $bgcolor))
      throw new \SExcept("erreur de imagefilledrectangle()", self::ErrorCreate);
    if (!imagealphablending($this->im, true))
      throw new \SExcept("erreur de imagealphablending()", self::ErrorCreate);
  }
  
  // prend une couleur définie en rgb et un alpha et renvoie une ressource évitant ainsi les duplications
  private function colorallocatealpha(int $rgb, float $opacity): int {
    if ($opacity < 0) $opacity = 0;
    if ($opacity > 1) $opacity = 1;
    $alpha = intval((1 - $opacity) * 128);
    $rgba = ($rgb << 8) | ($alpha & 0xFF);
    if (isset($this->colors[$rgba]))
      return $this->colors[$rgba];
    $color = imagecolorallocatealpha(
      $this->im, ($rgba >> 24) & 0xFF, ($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0x7F);
    if ($color === FALSE)
      throw new \Exception("Erreur imagecolorallocatealpha() ligne ".__LINE__);
    $this->colors[$rgba] = $color;
    return $color;
  }
  
  /**
   * proj(array $pos): array - transforme une position en coord. World en une position en coordonnées écran"
   *
   * Le retour est un array de 2 entiers.
   * Le dessin de l'Antarctique en WM génère par défaut des erreurs car la proj en WM fournit un y = -INF qui reste flottant
   * après un round() puis génère une erreur de type dans imageline()/imagefilledpolygon()
   * La solution consiste à remplacer les valeurs très grandes ou très petites par un entier très grand/petit.
   * Cette solution ne fonctionne pas bien avec PHP_INT_MAX/PHP_INT_MIN car le remplissage du polygon remplit l'extérieur.
   * Après tests, l'utilisation des valeurs 1000000 et -1000000 donne de bons résultats.
   *
   * @param TPos $pos
   * @return array<int, int>
  */
  function proj(array $pos): array {
    $x = round(($pos[0] - $this->world->west()) / ($this->world->east() - $this->world->west()) * $this->width);
    $y = round(($this->world->north() - $pos[1]) / ($this->world->north() - $this->world->south()) * $this->height);
    // il s'assurer que $x et $y soient entiers et pas INF
    if ($x < -1000000)
      $x = -1000000;
    if ($x > 1000000)
      $x = 1000000;
    if ($y < -1000000)
      $y = -1000000;
    if ($y > 1000000)
      $y = 1000000;
    return [intval($x), intval($y)];
  }
  
  /**
   * userCoord(array $pos): TPos - passe de coord écran en coord. utilisateurs
   *
   * @param array<int, int> $pos coord. écran
   * @return TPos coord. utilisateur
   */
  function userCoord(array $pos): array {
    return [
      $this->world->west() + $pos[0] / $this->width * ($this->world->east() - $this->world->west()),
      $this->world->north() - $pos[1] / $this->height * ($this->world->north() - $this->world->south()),
    ];
  }
  
  /**
   * polyline(array $lpos, array $style=[]): void - dessine une ligne brisée
   *
   * @param TLPos $lpos liste de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polyline(array $lpos, array $style=[]): void {
    $color = $this->colorallocatealpha(
      isset($style['stroke']) ? $style['stroke'] : 0x000000,
      isset($style['stroke-opacity']) ? $style['stroke-opacity'] : 1);
    $pPim = null; // previous pim
    foreach ($lpos as $pos) {
      $pim = $this->proj($pos);
      if (!$pPim)
        $pPim = $pim;
      elseif (($pim[0]<>$pPim[0]) || ($pim[1]<>$pPim[1])) {
        if (!imageline($this->im, $pPim[0], $pPim[1], $pim[0], $pim[1], $color))
          throw new \Exception("Erreur imageline(im, $pPim[0], $pPim[1], $pim[0], $pim[1], $color) ligne ".__LINE__);
      }
      $pPim = $pim;
    }
  }
  
  /**
   * polygon(array $llpos, array $style=[]): void - dessine un polygone
   *
   * @param TLLPos $llpos liste de listes de positions en coordonnées utilisateur
   * @param array<string, string> $style style de dessin
  */
  function polygon(array $llpos, array $style=[]): void {
    $color = $this->colorallocatealpha(
      isset($style['fill']) ? $style['fill'] : 0x808080,
      isset($style['fill-opacity']) ? $style['fill-opacity'] : 1);
    $pts = []; // le tableau des coords écran des points
    $pt = []; // coords écran courant
    $pt0 = []; // première coords écran
    $ptn = []; // le dernier point de l'extérieur
    foreach ($llpos as $lpos) {
      foreach ($lpos as $i => $pos) {
        $pt = $this->proj($pos);
        if ($i == 0)
          $pt0 = $pt;
        $pts[] = $pt[0];
        $pts[] = $pt[1];
      }
      // si le dernier point du ring est différent du premier alors ajout du premier point pour fermer le ring
      if ($pt <> $pt0) {
        $pts[] = $pt0[0];
        $pts[] = $pt0[1];
      }
      // je mémorise le dernier point de l'extérieur pour y revenir après chaque trou
      if (!$ptn)
        $ptn = $pt;
      else {
        $pts[] = $ptn[0];
        $pts[] = $ptn[1];
      }
    }
    //echo "<pre>imagefilledpolygon(pts="; print_r($pts); die(")");
    if (!imagefilledpolygon($this->im, $pts, $color))
      throw new \SExcept("Erreur imagefilledpolygon(im, pts, $color)", self::ErrorFilledPolygon);
    if (isset($style['stroke'])) {
      foreach ($llpos as $lpos)
        $this->polyline($lpos, $style);
    }
  }
  
  /*PhpDoc: methods
  name: imagecopy
  title: "function imagecopy($src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h): void - effectue une copie d'une image source dans l'image du dessin"
  doc: |
    Les paramètres sont ceux définis par GD:
      imagecopy(resource $dst_im, resource $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h): bool
        - dst_im - Lien vers la ressource cible de l'image.
        - src_im - Lien vers la ressource source de l'image.
        - dst_x - X : coordonnées du point de destination.
        - dst_y - Y : coordonnées du point de destination.
        - src_x - X : coordonnées du point source.
        - src_y - Y : coordonnées du point source.
        - src_w - Largeur de la source.
        - src_h - Hauteur de la source.
    Cette méthode n'utilise pas les coordonnées utilisateurs du dessin
  *
  function imagecopy($src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h): void {
    if (!imagecopy($this->im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h))
      throw new \Exception("Erreur imagecopy() ligne ".__LINE__);
  }
  */
  /*PhpDoc: methods
  name: imagecopy
  title: "function resample(BBox $newWorld, int $width, int $height): Drawing - rééchantillonnage de l'image"
  doc: |
    Effectue un rééchantillonnage de l'image paramétré en coordonnées utilisateur.
    En pratique génère un nouveau dessin dans le nouveau syst. de coord. utilisteur fourni.
  *
  function resample(BBox $newWorld, int $width, int $height): Drawing {
    $newDrawing = new GdDrawing($newWorld, $width, $height);
    $nw = $this->proj($newWorld->northWest()); // les coords du rect de destination en coord. image d'origine
    $se = $this->proj($newWorld->southEast());
    //imagecopyresampled ( resource $dst_image , resource $src_image , int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h ) : bool;
    if (!imagecopyresampled($newDrawing->im, $this->im, 0, 0, $nw[0], $nw[1], $width, $height, $se[0]-$nw[0], $se[1]-$nw[1]))
      throw new \Exception("Erreur imagecopyresampled() ligne ".__LINE__);
    return $newDrawing;
  }
  */
  
  /**
   * flush(string $format='', bool $noheader=false): void - affiche l'image construite
   *
   * @param string $format format MIME d'affichage
   * @param bool $noheader si vrai alors le header n'est pas transmis
  */
  function flush(string $format='', bool $noheader=false): void {
    if ($format == 'image/jpeg') {
      if (!$noheader)
        header('Content-type: image/jpeg');
      imagejpeg($this->im);
    }
    else {
      if (!imagesavealpha ($this->im, true))
        throw new \SExcept("Erreur imagesavealpha()", self::ErrorSaveAlpha);
      if (!$noheader)
        header('Content-type: image/png');
      imagepng($this->im);
    }
    imagedestroy($this->im);
    die();
  }
};


if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // test élémentaire de GdDrawing
  require_once __DIR__.'/gegeom.inc.php';

  $drawing = new GdDrawing(1000, 800, new EBox([0,0,1000,800]), 0xFFFFFF);

  //(new Polygon([[[0,0],[500,0],[500,500],[0,0]]]))->draw($drawing, ['fill'=> Drawing::COLORNAMES['DarkOrange']]);
  //(new Polygon([[[1000,800],[500,500],[1000,500],[1000,800]]]))->draw($drawing);
  //(new LineString([[50,500],[1000,800]]))->draw($drawing, ['stroke'=> 0x0000FF]);

  (new Polygon([
    [[100,100],[100,700],[900,700],[900,100],[100,100]],
    //[[150,200],[300,200],[300,300],[150,200]],
    [[110,110],[890,110],[890,300],[110,300],[110,110]],
    //[[200,400],[500,400],[500,500],[200,400]],
    [[200,400],[500,400],[500,500]],
    [[890,690],[890,680],[880,680],[880,690],[890,690]]
  ]))->draw($drawing, ['fill'=> 0xaaff, 'stroke'=> 0]);

  //(new Polygon([[[100,100],[100,200],[200,200],[300,300],[100,100]]]))->draw($drawing, ['fill'=> 0xaaff]);

  $drawing->flush();
  //$drawing->flush('', true);  // Utiliser cette ligne pour ne pas transmettre le header en cas de bug
}
