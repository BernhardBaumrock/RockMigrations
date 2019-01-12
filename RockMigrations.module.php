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
   * @param string|Module $module
   * @return void
   */
  public function setModule($module) {
    $module = $this->modules->get((string)$module);
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

        // make sure outputformatting is off for all migrations
        $this->pages->of(false);

        // execute the migrations
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

  /* ##### fields ##### */

    /**
     * Delete the given field.
     *
     * @param string $fieldname
     * @return void
     */
    public function deleteField($fieldname) {
      $field = $this->fields->get($fieldname);
      if(!$field OR !$field->id) return;

      // make sure we can delete the field by removing all flags
      $field->flags = Field::flagSystemOverride;
      $field->flags = 0;

      // remove the field from all fieldgroups
      foreach($this->fieldgroups as $fieldgroup) {
        /** @var Fieldgroup $fieldgroup */
        $fieldgroup->remove($field);
        $fieldgroup->save();
      }

      return $this->fields->delete($field);
    }

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
     * Delete a ProcessWire Template
     *
     * @param string $name
     * @return void
     */
    public function deleteTemplate($name) {
      $template = $this->templates->get($name);
      if(!$template OR !$template->id) return;

      // remove all pages having this template
      foreach($this->pages->find("template=$template, include=all") as $p) {
        $this->deletePage($p);
      }

      // make sure we can delete the template by removing all flags
      $template->flags = Template::flagSystemOverride;
      $template->flags = 0;

      // delete the template
      $this->templates->delete($template);

      // delete the fieldgroup
      $fg = $this->fieldgroups->get($name);
      $this->fieldgroups->delete($fg);
    }
  
  /* ##### pages ##### */

    /**
     * Delete the given page including all children.
     *
     * @param Page $page
     * @return void
     */
    public function deletePage($page) {
      // make sure we got a page
      $page = $this->pages->get((string)$page);
      if(!$page->id) return;
      
      // make sure we can delete the page and delete it
      // we also need to make sure that all descendants of this page are deletable
      $all = $this->wire(new PageArray());
      $all->add($page);
      $all->add($this->pages->find("has_parent=$page"));
      foreach($all as $p) {
        $p->addStatus(Page::statusSystemOverride);
        $p->status = 1;
        $p->save();
      }
      $this->pages->delete($page, true);
    }

  /* ##### permissions ##### */

    /**
     * Add a permission to given role.
     *
     * @param string|int $role
     * @param string|int $permission
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
     * @param string|int $role
     * @param string|int $permission
     * @return void
     */
    public function removePermissionFromRole($role, $permission) {
      $role = $this->roles->get($role);
      $role->of(false);
      $role->removePermission($permission);
      return $role->save();
    }

  /* ##### users ##### */

    /**
     * Create a PW user with given password.
     * If the user already exists it will return this user.
     *
     * @param string $username
     * @param string $password
     * @return User
     */
    public function createUser($username, $password) {
      $user = $this->users->get($username);
      if($user->id) return $user;

      $user = $this->wire->users->add($username);
      $user->pass = $password;
      $user->save();
      return $user;
    }
    
    /**
     * Delete a PW user.
     *
     * @param string $username
     * @return void
     */
    public function deleteUser($username) {
      $user = $this->users->get($username);
      if(!$user->id) return;
      $u = $this->wire->users->delete($user);
    }

  /* ##### modules ##### */

    /**
     * Set module config data.
     *
     * @param string $module
     * @param array $data
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
     * @param string $module
     * @param array $data
     * @return Module
     */
    public function updateModuleConfig($module, $data) {
      $module = $this->modules->get($module);
      if(!$module) throw new WireException("Module not found!");

      $newdata = $this->getModuleConfig($module);
      foreach($data as $k=>$v) $newdata[$k] = $v;
      $this->modules->saveConfig($module, $newdata);
    }

    /**
     * Get module config data.
     *
     * @param string $module
     * @return array
     */
    public function getModuleConfig($module) {
      $module = $this->modules->get($module);
      return $this->modules->getModuleConfigData($module);
    }

    /**
     * Install module if it is not already installed.
     *
     * @param string $name
     * @return void
     */
    public function installModule($name) {
      // tbd
    }

  /* ##### languages ##### */

    /**
     * Install language support.
     * 
     * It can be helpful to completely remove language support in some situations:
     * https://processwire.com/talk/topic/7207-can%C2%B4t-install-languagesupport/
     *
     * @return void
     */
    public function installLanguageSupport() {
      $this->modules->install('LanguageSupport');
      $this->modules->install('LanguageSupportFields');
      $this->modules->install('LanguageSupportPageNames');
      $this->modules->install('LanguageTabs');
    }

    /**
     * Uninstall language support.
     *
     * @return void
     */
    public function uninstallLanguageSupport() {
      $this->modules->uninstall('LanguageTabs');
      $this->modules->uninstall('LanguageSupportPageNames');
      $this->modules->uninstall('LanguageSupportFields');
      $this->modules->uninstall('LanguageSupport');
    }

    /**
     * Reset language support.
     * This can help if you have trouble uninstalling language support manually:
     * https://processwire.com/talk/topic/7207-can%C2%B4t-install-languagesupport/
     *
     * @return void
     */
    public function resetLanguageSupport() {
      $setup = $this->pages->get('parent.id=2, name=setup');
      $this->deletePage($this->pages->get([
        'name' => 'language-translator',
        'parent' => $setup,
      ]));
      $this->deletePage($this->pages->get([
        'name' => 'languages',
        'parent' => $setup,
      ]));
      $this->deleteField('language');
      $this->deleteField('language_files');
      $this->deleteTemplate('language');
      $this->modules->uninstall('ProcessLanguageTranslator');
      $this->modules->uninstall('ProcessLanguage');
      @$this->modules->uninstall('LanguageSupport');
    }
}
