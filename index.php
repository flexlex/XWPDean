<?php
include("dbdata.php"); //LOAD DB CONFIG
require_once("killo_wp_auth/x_wp_auth.php"); //INCLUDE LIBRARU
$xwp = new Killo_XWPAuth();
$xwp->deanToken = "K2QIWKWI400II6KS7LDC"; #Settings the Dean Token

//USAGE ESAMPLES

# $r = $xwp->login("email","pass");
# $r = $xwp->localAssign(1,1);
# $r = $xwp->createDeanStudent(["nome"=>"Name","cognome"=>"Surname","bday"=>"2000-01-01"]);
# $r = $xwp->getDeanStudent(1);
# $r = $xwp->getWPUser(1);
?>
