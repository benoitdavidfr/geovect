<?php
/*PhpDoc:
name:  sexcept.inc.php
title: sexcept.inc.php - Exception avec code string
classes:
doc: |
*/
/*PhpDoc: classes
name: SExcept
title: class SExcept extends Exception - Exception avec code string
doc: |
  J'étend la classe des Exception avec un code de type chaine de caractères qui identifie l'erreur.
  Je garde le code numérique en plus comme sous-code, notamment pour enregistrer évent. le code d'erreur Http
*/
class SExcept extends Exception {
  private $scode; // code sous forme d'une chaine de caractères
  
  public function __construct(string $message, string $scode='', int $code=0, Throwable $previous = null) {
    $this->scode = $scode;
    parent::__construct($message, $code, $previous);
  }
  
  public function getSCode() { return $this->scode; }
};
