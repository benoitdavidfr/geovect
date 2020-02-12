<?php
/*PhpDoc:
name: crs.inc.php
title: crs.inc.php - définit la classe abtraite CRS et la fonction degres_sexa()
classes:
functions:
doc: |
journal: |
  25/3/2019:
    - vérifiCATION que:
      - qd le code EPSG est rempli alors le CRS est dans le registre EPSG et vice-versa
      - les 2 codes sont identiques
  23-24/3/2019:
    - transfert des registres dans le fichier crsregistre.yaml défini par le schema crsregistre.schema.yaml
  22/3/2019:
    - alignement des concepts et de la codification sur sur le std OGC CRS WKT (ISO 19162:2015)
includes:
  - full.inc.php
*/

require_once __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

/*PhpDoc: classes
name: CRS
title: abstract class CRS - classe abstraite gérant les registres et les fonctions de création
doc: |
  Un CRS sera implémenté soit par un GeodeticCRS soit par un ProjectedCRS
*/
abstract class CRS {
  static private $registre=null; // copie du registre stocké dans crsregistre.yaml
  
  // retourne le schéma du registre
  static function schema() { return Yaml::parseFile(__DIR__.'/crsregistre.schema.yaml'); }
    
  // si regid est vide alors retourne le registre, sinon si id est null retourne le sous-registre regid
  // sinon retourne la valeur contenue dans le registre pour l'id si elle est définie ou null sinon
  static function registre(string $regid='', string $id='') {
    if (!self::$registre) {
      if (is_file(__DIR__.'/crsregistre.pser')
          && (filemtime(__DIR__.'/crsregistre.pser') > filemtime(__DIR__.'/crsregistre.yaml'))) {
        self::$registre = unserialize(file_get_contents(__DIR__.'/crsregistre.pser'));
      }
      else {
        self::$registre = Yaml::parseFile(__DIR__.'/crsregistre.yaml');
        file_put_contents(__DIR__.'/crsregistre.pser', serialize(self::$registre));
      }
    }
    if (!$regid)
      return self::$registre;
    if (!isset(self::$registre[$regid]))
      return null;
    if (!$id)
      return self::$registre[$regid];
    //echo "registre(regid=$regid, id=$id)\n";
    if (isset(self::$registre[$regid][$id]))
      return self::$registre[$regid][$id];
    elseif (($regid=='CRS')
        && preg_match('!^UTM(\d\d[NS])-(.*)$!', $id, $matches) && isset(self::$registre['CRS'][$matches[2]]))
      return [
        'title'=> "Système de coordonnées en projection $matches[1] dans le système de coordonnées $matches[2]",
        'BASEGEODCRS'=> $matches[2],
        'UNIT'=> 'metre',
        'PROJECTION'=> [
          'METHOD'=> 'UTM',
          'zone'=> $matches[1],
        ],
      ];
    else
      return null;
  }
  
  // crée un CRS à partir du code du registre des CRS
  static function create(string $code, string $latLon=''): GeodeticCRS {
    if (!($params = self::registre('CRS', $code)))
        throw new Exception("Code $code inconnu dans le registre des CRS");
    if (!isset($params['PROJECTION'])) {
      $geodeticCrsClass = !$latLon ? 'GeodeticCRS' : 'GeodeticCrsLatLon';
      return new $geodeticCrsClass($code, isset($params['limits']) ? $params['limits'] : []);
    }
    else
      return new $params['PROJECTION']['METHOD'](
        $params['BASEGEODCRS'],
        $params['PROJECTION'],
        isset($params['limits']) ? $params['limits'] : []);
  }
  
  // crée un CRS à partir d'un code du registre IGNF
  static function IGNF(string $code): GeodeticCRS {
    if (!($target = self::registre('IGN-F', $code)))
      throw new Exception("Code $code inconnu dans le registre IGN-F des CRS");
    else
      return self::create($target);
  }
  
  // crée un CRS à partir d'un code du registre EPSG
  static function EPSG(string $code): GeodeticCRS {
    if (!($target = self::registre('EPSG', $code)))
      throw new Exception("Code $code inconnu dans le registre EPSG des CRS");
    elseif (is_array($target))
      return self::create($target['latLon'], 'latLon');
    else
      return self::create($target);
  }
  
  // 2 cas d'utilisation:
  // 1. Sans $projName créée un CRS à partir d'un code du registre SIMPLE
  // 2. Avec ProjName créée un CRS à partir du système géodésique et de la projection évent. paramétrée
  // Dans ce second cas:
  //   $code doit être dans le registre SIMPLE et correspondre à un BASEGEODCRS
  //   $projName doit être le nom d'une classe implémentant ProjectedCRS
  //   $projParam doit correspondre aux paramètres demandés par la création de cette projection
  static function S(string $code, string $projName='', $projParams=null): GeodeticCRS {
    if (!($target = self::registre('SIMPLE', $code)))
      throw new Exception("Code $code inconnu dans le registre SIMPLE des CRS");
    elseif (!$projName)
      return self::create($target);
    elseif (isset(self::registre('CRS', $target)['PROJECTION']))
      throw new Exception("Erreur dans CRS::S(): $code doit être le code d'un GeodeticCrs");
    else
      return new $projName($target, $projParams);
  }
};


/*PhpDoc: functions
name: degres_sexa
title: function degres_sexa($r, $ptcardinal='', $dr=0)
doc: |
  Transforme une valeur en radians en une chaine en degres sexagesimaux
  si ptcardinal est fourni alors le retour respecte la notation avec point cardinal
  sinon c'est la notation signee qui est utilisee
  dr est la precision de r
*/
function degres_sexa($r, $ptcardinal='', $dr=0) {
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


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test du package


require_once __DIR__.'/full.inc.php';

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>TEST crs.inc.php</title></head><body>\n";
if (!isset($_GET['TEST'])) {
  die("<ul>
<li><a href='?TEST=crsregcheck'>Vérification du registre</a></li>
</ul>");
}

elseif ($_GET['TEST']=='crsregcheck') {
  echo "<pre>\n";
  // DATUM.ELLIPSOID -> ELLIPSOID
  foreach (CRS::registre('DATUM') as $datumId => $datum)
    if (!CRS::registre('ELLIPSOID', $datum['ELLIPSOID']))
      throw new Exception("Erreur DATUM.$datumId.ELLIPSOID = $datum[ELLIPSOID] non défini dans le registre ELLIPSOID");
  echo "DATUM.ELLIPSOID -> ELLIPSOID<br>\n";
  
  // CRS.DATUM -> DATUM | CRS.BASEGEODCRS -> CRS
  foreach (CRS::registre('CRS') as $crsId => $crs) {
    if (!isset($crs['PROJECTION'])) {
      if (!CRS::registre('DATUM', $crs['DATUM']))
        throw new Exception("Erreur CRS.$crsId.DATUM = $crs[DATUM] non défini dans le registre DATUM");
    }
    else {
      if (!CRS::registre('CRS', $crs['BASEGEODCRS']) && ($crs['BASEGEODCRS']<>'{GeodeticCRS}'))
        throw new Exception("Erreur CRS.$crsId.BASEGEODCRS = $crs[BASEGEODCRS] non défini dans le registre CRS");
      //print_r($crs);
      $projection = $crs['PROJECTION']['METHOD'];
      if (!class_exists($projection))
        throw new Exception("Erreur CRS.$crsId.PROJECTION.METHOD = $projection non défini comme projection");
      //print_r(class_implements($projection));
      if (!in_array('ProjectedCRS', class_implements($projection)))
        throw new Exception("Erreur CRS.$crsId.PROJECTION.id = $projection non défini comme projection");
    }
  }
  echo "CRS.DATUM -> DATUM + CRS.BASEGEODCRS -> CRS + CRS.PROJECTION est une classe implementant ProjectedCRS<br>\n";
  
  foreach (CRS::registre('EPSG') as $epsg => $crsId) {
    // vérif que le crsId auquel correspond le code EPSG est bien défini
    if (is_array($crsId))
      $crsId = $crsId['latLon'];
    if (!($params = CRS::registre('CRS', $crsId)))
      throw new Exception("Erreur EPSG:$epsg -> $crsId non défini dans CRS");
    //print_r($params);
    if (!preg_match('!^UTM!', $crsId)) {
      if (!isset($params['AUTHORITY']['EPSG'])) // vérif que le code csrId a un code EPSG
        echo "Code EPSG non défini pour $epsg -> $crsId\n";
      elseif ($params['AUTHORITY']['EPSG'] <> $epsg) // vérif que le code EPSG du crsId est identique à celui du reg. EPSG
        echo "Code EPSG pour $epsg -> $crsId = ",$params['AUTHORITY']['EPSG'],"\n";
    }
  }
  
  foreach (CRS::registre('CRS') as $crsId => $crs) {
    if (isset($crs['AUTHORITY']['EPSG'])) {
      $epsg = $crs['AUTHORITY']['EPSG'];
      if (!($crsId2 = CRS::registre('EPSG', $epsg)))
        echo "Le code CRS::$crsId a pour code EPSG $epsg qui n'est pas dans le registre EPSG\n";
      elseif (is_array($crsId2)) {
        if ($crsId2['latLon']<>$crsId)
          echo "Le code CRS::$crsId a pour code EPSG $epsg qui dans le registre EPSG référence le code $crsId2\n";
      }
      elseif ($crsId2 <> $crsId)
        echo "Le code CRS::$crsId a pour code EPSG $epsg qui dans le registre EPSG référence le code $crsId2\n";
    }
  }
  
  // vérification de l'intégrité du registre IGN-F
  foreach (CRS::registre('IGN-F') as $ignf => $crsId) {
    if (is_array($crsId))
      $crsId = $crsId['latLon'];
    if (!CRS::registre('CRS', $crsId))
      throw new Exception("Erreur IGN-F:$ignf -> $crsId non défini dans CRS");
  }
  
  foreach (CRS::registre('SIMPLE') as $simple => $crsId) {
    if (!CRS::registre('CRS', $crsId))
      throw new Exception("Erreur SIMPLE:$simple -> $crsId non défini dans CRS");
  }
  
  die("registre OK\n");
}

die("Aucune action ligne ".__LINE__);