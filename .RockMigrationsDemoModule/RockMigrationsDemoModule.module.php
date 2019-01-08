<?php
/**
 * RockMigrationsDemoModule
 *
 * @author Bernhard Baumrock, 08.01.2019
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockMigrationsDemoModule extends WireData implements Module {

  /**
   * Initialize the module (optional)
   */
  public function init() {
  }

  /**
   * Execute the upgrade from one version to another.
   * Does also execute on downgrades.
   * Must be hookable to work!
   *
   * @param string $from
   * @param string $to
   * @return void
   */
  public function ___upgrade($from, $to) {
    $rm = $this->modules->get('RockMigrations');
    $rm->setModule($this);
    $rm->executeUpgrade($from, $to);
  }
}
