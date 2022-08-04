<?php
namespace unittest;
/*PhpDoc:
name:  unittest.inc.php
title: unittest.inc.php - définition de la classe UnitTest de test unitaire de méthodes ou de fonctions
doc: |
  Ce fichier définit la classe UnitTest
  Pour effectuer des tests unitaires dans un fichier xxx.inc.php:
    - insérer dans le code:
      - après la définition d'une classe {Class} "UnitTest::class(__NAMESPACE__, __FILE__, '{Class}');"
      - dans la classe des méthodes static test_xxxx
      - après la définition d'une fonction:
        "UnitTest::function(__FILE__, '{nom de la fonction}', {code de test comme fonction anonyme});"
    - l'appel du fichier en mode web permettra d'appeler chaque méthode de test et chaque fonction de test
journal: |
  4/8/2022:
    - corrections suite à analyse PhpStan level 6
    - structuration de la doc conformément à phpDocumentor
  8/5/2019:
    - création par scission de position.inc.php
*/

/**
 * class UnitTest - classe regroupant les 2 mécanismes de test
 *
 * Pour effectuer des tests unitaires de fonction ou de méthode, le principe est d'appeler en mode web le fichier inc.php  
 * Si le basename() du nom passé en paramètre est différent de celui du nom du fichier appelé alors la fonction ne fait rien ;
 * cela permet de ne rien afficher pour les fichiers inclus.
*/
class UnitTest {
  static bool $first = true;

  static function print_header(string $file): void {
    if (self::$first) {
      echo "<!DOCTYPE HTML><html><head><title>$file</title><meta charset='UTF-8'></head><body>\n",
           "<h2>Test de $file</h2>\n";
      self::$first = false;
    }
  }
  
  /**
   * class(string $nameSpace, string $file, string $class): void - effectue les tests unitaires de la classe fournie
   *
   * $file doit être __FILE__ au moment de l'appel afin de correspondre au chemin du fichier du source 
   * Si le paramètre class n'est pas défini alors l'exécution du fichier affiche la liste des classes à tester permettant
   * de rappeler le même fichier en définissant ce paramètre.
   * Si le paramètre class est défini mais que method ne l'est pas alors la fonction détermine la liste des méthodes
   * à tester qui sont celles pour lesquelles une méthodes de test a été définie avec un nom commencant par test_
   * puis le nom de la méthode ;
   * la fonction affiche alors la liste des méthodes à tester permettant de rappeller le même fichier avec ce paramètre.
   *
   * Enfin, si les paramètres class et method sont définis alors la méthode de test correspondante est appelée.
   * Pour effectuer ces tests cette méthode doit être appelée après la définition de la classe avec en paramètres
   * d'une part le paramètre formel __FILE__ et d'autre part le nom de la classe des méthodes à tester.
   * Par défaut les méthodes héritées ne sont pas proposées pour le test.
   * Pour tester une méthode redéfinie dans une classe fille,
   * utiliser comme nom de la méthode de test : 'test_'.{subClassName}.'_'.{methodName}
   */
  static function class(string $nameSpace, string $file, string $class): void {
    //echo "nameSpace='$nameSpace', file='$file', class='$class'<br>\n";
    $file = basename($file);
    if ($file <> basename($_SERVER['PHP_SELF'])) return;
    self::print_header($file);
    
    if (!isset($_GET['class']) && !isset($_GET['function'])) {
      if (!class_exists($nameSpace.'\\'.$class))
        echo "<b>Attention : la classe $nameSpace\\$class n'est pas définie</b><br>\n";
      else
        echo "<a href='?class=$class'>Test unitaire de la classe $class</a><br>\n";
    }
    elseif (isset($_GET['class']) && ($_GET['class'] == $class)) {
      if (!isset($_GET['method'])) {
        //echo "<pre>get_class_methods($nameSpace\\$class)=";
        //print_r(get_class_methods($nameSpace.'\\'.$class)); echo "</pre>\n";        
        $parentClass = get_parent_class($nameSpace.'\\'.$class);
        //echo "<pre>get_class_methods($parentClass)="; print_r(get_class_methods($parentClass)); echo "</pre>\n";        
        //echo "parentClass=$parentClass<br>\n";
        
        foreach(get_class_methods($nameSpace.'\\'.$class) as $method) {
          //echo "method=$method<br>\n";
          if (strncmp($method, 'test_', 5) <> 0)
            continue; //"La méthode $method n'est pas une méthode de test<br>\n";
          elseif ($parentClass && in_array($method, get_class_methods($parentClass)))
            continue; // "La méthode $method existe dans la classe mère<br>\n";
          else {
            //echo "La méthode $method est une méthode de test<br>\n";
            $method = substr($method, 5); // suppression de l'en-tete 'test_'
            if (strncmp($method, $class.'_', strlen($class)+1)==0)
              $method = substr($method, strlen($class)+1);
            echo "<a href='?class=$class&method=$method'>Test unitaire de $class::$method()</a><br>\n";
          }
        }
      }
      else {
        echo "<h3>Test de $class::$_GET[method]()</h3>\n";
        $class = $nameSpace.'\\'.$class;
        $parentClass = get_parent_class($class);
        $testmethod = "test_$_GET[method]";
        if ($parentClass && in_array($testmethod, get_class_methods($parentClass)))
          $testmethod = "test_$_GET[class]_$_GET[method]";
        $class::$testmethod();
      }
    }
  }
  
  /**
   * function(string $currentfile, string $functionName, callable $test_function): void - effectue le test unitaire de la fonction fournie
   *
   * $currentfile doit être __FILE__ au moment de l'appel afin de correspondre au chemin du fichier du source 
   * Si le paramètre function n'est pas défini alors l'exécution du fichier affiche la liste des fonctions à tester permettant
   * de rappeler le même fichier en définissant ce paramètre.
   * Si le paramètre function est défini alors la fonction de test correspondante est appelée.
   * Pour effectuer ces tests cette méthode doit être appelée après la définition de la fonction avec en paramètres
   * d'une part le paramètre formel __FILE__ et d'autre part le nom de la fonction à tester.
  */
  static function function(string $file, string $functionName, callable $test_function): void {
    $file = basename($file);
    if ($file <> basename($_SERVER['PHP_SELF'])) return;
    self::print_header($file);

    if (!isset($_GET['class']) && !isset($_GET['function'])) {
      echo "<a href='?function=$functionName'>Test unitaire de la fonction $functionName()</a><br>\n";
    }
    elseif (isset($_GET['function']) && ($_GET['function']==$functionName)) {
      $test_function();
    }
  }
};


if (basename(__FILE__) <> basename($_SERVER['PHP_SELF'])) return; // Test unitaire de la classe UnitTest

UnitTest::class(__NAMESPACE__, __FILE__, 'ClasseInexistante');

class ClasseATester {
  static function methodeStatiqueATester(): void { echo "méthode statique à tester<br>\n"; }
  static function test_methodeStatiqueATester(): void { self::methodeStatiqueATester(); }
  
  function methodeNonStatiqueATester(): void { echo "méthode non statique à tester<br>\n"; }
  static function test_methodeNonStatiqueATester(): void { (new CATester)->methodeNonStatiqueATester(); }
  
  function methodA(): void { echo "CATester::methodeA<br>\n"; }
  static function test_methodA(): void { (new self)->methodA(); }
};

UnitTest::class(__NAMESPACE__, __FILE__, 'ClasseATester');

class ClasseFille extends ClasseATester {
  function methodA(): void { echo "CFille::methodeA<br>\n"; }
  static function test_CFille_methodA(): void { (new self)->methodA(); }
  
  static function test_methodFille(): void {}
};

UnitTest::class(__NAMESPACE__, __FILE__, 'ClasseFille');

function fonction_a_tester(): void { echo "fonction à tester<br>\n"; }

UnitTest::function(__FILE__, 'fonction_a_tester', function() { fonction_a_tester(); });