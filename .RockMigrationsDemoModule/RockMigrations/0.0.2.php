<?php namespace ProcessWire;
$upgrade = function() {
  d("--- execute upgrade 002 ---");
  d("create page foo");
  d("create page bar");
}; // dont forget the semicolon!

$downgrade = function() {
  d("--- execute downgrade 002 ---");
  d("remove page foo");
  d("remove page bar");
}; // dont forget the semicolon!
