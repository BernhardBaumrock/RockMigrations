<?php namespace ProcessWire;
/**
 * Base Class for RockMigration Object
 *
 * @author Bernhard Baumrock, 08.01.2019
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockMigration {

  /**
   * Get previous migration.
   *
   * @return void
   */
  public function getPrev() {
    $migrations = $this->object->getMigrations();
    $current = 0;
    foreach($migrations as $i=>$item) {
      if($item == $this->version) $current = $i;
    }
    return $current > 0 ? $this->object->getMigration($migrations[$current-1]) : null;
  }
  
  /**
   * Get next migration.
   *
   * @return void
   */
  public function getNext() {
    $migrations = $this->object->getMigrations();
    $current = 0;
    foreach($migrations as $i=>$item) {
      if($item == $this->version) $current = $i;
    }
    return $current < count($migrations)-1 ? $this->object->getMigration($migrations[$current+1]) : null;
  }
}