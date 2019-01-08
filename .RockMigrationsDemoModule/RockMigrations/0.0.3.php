<?php namespace ProcessWire;
$this->data->tpl = "demoTemplate003";

$upgrade = function(RockMigrations $rm) {
  d("--- execute upgrade 003 ---");
  $rm->createTemplate($rm->data->tpl);
}; // dont forget the semicolon!

$downgrade = function(RockMigrations $rm) {
  d("--- execute downgrade 003 ---");
  $rm->removeTemplate($rm->data->tpl);
}; // dont forget the semicolon!
