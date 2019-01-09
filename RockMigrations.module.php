<?php namespace ProcessWire;
/**
 * RockMigrations Module
 *
 * @author Bernhard Baumrock, 08.01.2019
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class RockMigrations extends WireData implements Module {

  private $module;
  public $data;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations',
      'version' => '0.0.1',
      'summary' => 'Module to handle Migrations inside your Modules easily.',
      'autoload' => false,
      'singular' => false,
      'icon' => 'bolt',
    ];
  }

  public function init() {
    // load the RockMigration Object Class
    require_once('RockMigration.class.php');

    // new WireData object to store runtime data of migrations
    // see the demo module how to use this
    $this->data = new WireData();
  }

  /**
   * Set module that is controlled
   *
   * @param Module $module
   * @return void
   */
  public function setModule($module) {
    if(!$module instanceof Module) throw new WireException("This is not a valid Module!");
    $this->module = $module;
    return $this;
  }

  /**
   * Execute the upgrade from one version to another.
   * Does also execute on downgrades.
   *
   * @param string $from
   * @param string $to
   * @return int number of migrations that where executed
   */
  public function executeUpgrade($from, $to) {
    // check if module is set
    if(!$this->module) throw new WireException("Please set the module first: setModule(\$yourmodule)");
    
    // get migrations
    $migrations = $this->getMigrations();

    // check mode and log request
    $mode = version_compare($from, $to) > 0 ? 'downgrade' : 'upgrade';
    $this->log("Executing $mode $from to $to for module " . $this->module);
    
    // early exit if no migrations
    $count = 0;
    if(!count($migrations)) return $count;

    // flip array and numbers for downgrades
    if($mode == 'downgrade') {
      $migrations = array_reverse($migrations);
      $tmp = $from;
      $from = $to;
      $to = $tmp;
    }
    
    // now execute all available upgrades step by step
    foreach($migrations as $version) {
      // check if migration is part of the upgrade
      if(version_compare($version, $from) >= 1
        AND version_compare($version, $to) <= 0) {
        // this migration is part of the upgrade, so run it
        // this either calls upgrade() or downgrade() of the php file
        $this->log("Executing $mode $version");
        $migration = $this->getMigration($version);
        $migration->{$mode}->__invoke($this);

        // increase count
        $count++;
      }
    }

    return $count;
  }

  /**
   * Execute all Upgrade Scripts on Installation
   *
   * @return void
   */
  public function executeInstall() {
    $version = self::getModuleInfo()['version'];
    return $this->executeUpgrade(null, $version);
  }
  
  /**
   * Execute all Downgrade Scripts on Uninstallation
   *
   * @return void
   */
  public function executeUninstall() {
    $version = self::getModuleInfo()['version'];
    return $this->executeUpgrade($version, null);
  }

  /**
   * Get Migration Object from Version Number
   *
   * @param string $version
   * @return RockMigration
   */
  private function getMigration($version) {
    $migration = new RockMigration();
    $migration->version = $version;
    
    // find according php file
    $file = $this->getMigrationsPath().$version.".php";
    $upgrade = function(){};
    $downgrade = function(){};
    if(is_file($file)) include($file);
    $migration->upgrade = $upgrade;
    $migration->downgrade = $downgrade;

    return $migration;
  }

  /**
   * Get all migrations of one module
   *
   * @param Module $module
   * @return void
   */
  public function getMigrations() {
    $migrations = [];

    // find all files in the RockMigrations folder of the module
    $files = $this->files->find($this->getMigrationsPath(), [
      'extensions' => ['php']
    ]);

    // build an array of migration
    foreach($files as $file) {
      $info = pathinfo($file);
      $migrations[] = $info['filename'];
    }

    // sort array according to version numbers
    // see https://i.imgur.com/F52wGT9.png
    usort($migrations, 'version_compare');

    return $migrations;
  }

  /**
   * Get the module's migration path
   *
   * @return void
   */
  private function getMigrationsPath() {
    return $this->config->paths($this->module) . $this->className() . "/";
  }

  /* ##################### RockMigrations API Methods ##################### */

  /* ##### templates ##### */
    /**
     * Create a new ProcessWire Template
     *
     * @param string $name
     * @return void
     */
    public function createTemplate($name) {
      d("This will create the template $name. Those helper functions are not implemented yet - I'm open to suggestions!");
    }

    /**
     * Remove a ProcessWire Template
     *
     * @param string $name
     * @return void
     */
    public function removeTemplate($name) {
      d("This will remove the template $name. Here we can add tedious tasks such as cleanup of pages having this template etc...");
    }
  
  /* ##### permissions ##### */

    /**
     * Add a permission to given role.
     *
     * @param String|Integer $role
     * @param String|Integer $permission
     * @return void
     */
    public function addPermissionToRole($role, $permission) {
      $role = $this->roles->get($role);
      $role->of(false);
      $role->addPermission($permission);
      return $role->save();
    }
    /**
     * Remove a permission from given role.
     *
     * @param String|Integer $role
     * @param String|Integer $permission
     * @return void
     */
    public function removePermissionFromRole($role, $permission) {
      $role = $this->roles->get($role);
      $role->of(false);
      $role->removePermission($permission);
      return $role->save();
    }

  /* ##### modules ##### */

    /**
     * Set module config data.
     *
     * @param String $module
     * @param Array $data
     * @return Module
     */
    public function setModuleConfig($module, $data) {
      $module = $this->modules->get($module);
      if(!$module) throw new WireException("Module not found!");
      $this->modules->saveConfig($module, $data);
    }
    
    /**
     * Update module config data.
     *
     * @param String $module
     * @param String $property
     * @param Array $data
     * @return Module
     */
    public function updateModuleConfig($module, $property, $data) {
      $module = $this->modules->get($module);
      if(!$module) throw new WireException("Module not found!");

      $newdata = $this->getModuleConfig($module);
      $newdata[$property] = $data;
      $this->modules->saveConfig($module, $newdata);
    }

    /**
     * Get module config data.
     *
     * @param String $module
     * @return Array
     */
    public function getModuleConfig($module) {
      return $this->modules->getModuleConfigData($module);
    }
}
