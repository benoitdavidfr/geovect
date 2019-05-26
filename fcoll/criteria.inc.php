<?php
namespace fcoll;
{/*PhpDoc:
name: criteria.inc.php
title: criteria.inc.php - définit la classe statique Criteria
classes:
doc: |
*/}
use gegeom\GBox;
use \gegeom\Geometry;

{/*PhpDoc: classes
name: Criteria
title: class Criteria - classe statique regroupant des traitements sur un ensemble de critères élémentaires sur un Feature
doc : |
  Cette classe statique définit des traitements sur un ensemble de critères élémentaires s'appliquant à un Feature GeoJSON.
  Cet ensemble est structuré comme un array contenant:
    - soit la clé bbox et une valeur définissant un GBox, la conformité === l'intersection d'un Feature avec ce GBox
    - soit comme clé un nom de propriété et comme valeur
      - soit une valeur atomique, la conformité === la propriété de l'objet = cette valeur
      - soit une liste de valeurs, la conformité === la propriété est une des valeurs
  L'ensemble des critères est satisfait ssi chacun des critères l'est.
methods:
*/}
class Criteria {
  /*PhDoc: methods
  name: conjunction
  title: "static function conjunction(array $criteria1, array $criteria2): ?array - conjonction de 2 ens. de critères"
  doc: |
    Permet de calculer la conjunction des critères définis par une vue et ceux définis dans features()
    Retourne null en cas d'incompatibilité, cad que la conjonction correspond à une expression toujours fausse
  */
  static function conjunction(array $criteria1, array $criteria2): ?array {
    $result = $criteria2;
    foreach ($criteria1 as $name => $value) {
      if (!isset($result[$name]))
        $result[$name] = $value;
      else { // $name défini dans les 2 ensembles de critères
        if ($name == 'bbox') {
          $bbox1 = new GBox($result['bbox']);
          $bbox2 = new GBox($value);
          if ($bbox = $bbox1->intersects($bbox2))
            $result['bbox'] = $bbox->asArray();
          else
            return null;
        }
        elseif (is_array($value) && is_array($result[$name])) {
          if ($int = array_intersect($value, $result[$name]))
            $result[$name] = $int;
          else
            return null;
        }
        elseif (is_array($value)) {
          if (!in_array($result[$name], $value))
            return null;
        }
        elseif (is_array($result[$name])) {
          if (in_array($value, $result[$name]))
            $result[$name] = $value;
          else
            return null;
        }
        elseif ($result[$name] <> $value)
          return null;
      }
    }
    return $result;
  }
  static function test_conjunction() {
    echo "<pre>";
    foreach([
      /*[['a'=>5],['b'=>6]],
      [['a'=>5],['a'=>6]],
      [['a'=>[5,6]],['a'=>6]],
      [['a'=>5],['a'=>[5,6]]],
      [['a'=>[5,6,7,8]],['a'=>[3,4,5,6]]],
      [['a'=>[6,7,8]],['a'=>[3,4,5]]],*/
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,180,90]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,0,0]]],
      [['bbox'=>[0,0,180,90]], ['bbox'=>[-180,-90,-1,-1]]],
    ] as $pair) {
      print_r([
        ['parameters'=> $pair],
        Table::conjunction($pair[0], $pair[1]) ?? 'empty',
      ]);
    }
  }
  
  /*PhDoc: methods
  name: meetCriteria
  title: "static function meetCriteria(array $criteria, array $feature): bool - teste si l'objet satisfait chacun des critères
  */
  static function meetCriteria(array $criteria, array $feature): bool {
    foreach ($criteria as $name => $value) {
      if ($name == 'bbox') {
        $bbox = new GBox($value);
        $geom = Geometry::fromGeoJSON($feature['geometry']);
        if (!$bbox->inters($geom->bbox()))
          return false;
      }
      elseif (isset($feature['properties'][$name])) {
        if (is_array($value)) {
          if (!in_array($feature['properties'][$name], $value))
            return false;
        }
        elseif ($feature['properties'][$name] <> $value)
          return false;
      }
    }
    return true;
  }
  
};