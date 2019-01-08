<?php namespace ProcessWire;
$upgrade = function() {
  d("--- execute upgrade 001 ---");
  d("create field001");
  d("create template001");
}; // dont forget the semicolon!

$downgrade = function() {
  d("--- execute downgrade 001 ---");
  d("remove field001");
  d("remove template001");
}; // dont forget the semicolon!