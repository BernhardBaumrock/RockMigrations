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
   * Execute the upgrade from one version to another
   * 
   * Does also execute on downgrades.
   * If a module is set, we execute this upgrade on that module and not on the current.
   *
   * @param string $from
   * @param string $to
   * @param Module|string $module
   * 
   * @return int number of migrations that where executed
   */
  public function execute($from, $to, $module = null) {
    $currentModule = $this->module;
    if($module) {
      $module = $this->modules->get((string)$module);
      if(!$module) throw new WireException("Module not found!");
      $this->module = $module;
    }

    // check if module is set
    if(!$this->module) throw new WireException("Module invalid or not set!");
    
    // get migrations
    $migrations = $this->getMigrations();

    // check mode and log request
    $mode = version_compare($from, $to) > 0 ? 'downgrade' : 'upgrade';
    $this->log("Executing $mode $from   -->   $to for module " . $this->module);
    
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

    // make sure we execute the migrations on the default language.
    // this is necessary that field values are set in the default language,
    // eg. when creating a new page and setting the title of a multi-lang page.
    $lang = $this->user->language;
    if($this->languages) $this->user->language = $this->languages->getDefault();
    
    // now execute all available upgrades step by step
    foreach($migrations as $version) {
      // check if migration is part of the upgrade
      if(version_compare($version, $from) >= 1
        AND version_compare($version, $to) <= 0) {
        // this migration is part of the upgrade, so run it
        // this either calls upgrade() or downgrade() of the php file

        // make sure outputformatting is off for all migrations
        $this->pages->of(false);

        // execute the migrations
        $migration = $this->getMigration($version);
        $this->log("Executing $mode {$migration->file}");
        $migration->{$mode}->__invoke($this);

        // increase count
        $count++;
      }
    }

    // change language back to original
    $this->user->setAndSave('language', $lang);

    // reset the module to it's initial state
    if($module) $this->module = $currentModule;

    return $count;
  }

  /**
   * for backwards compatibility
   */
  public function executeUpgrade($from, $to, $module = null) {
    return $this->execute($from, $to, $module);
  }

  /**
   * Test upgrade for given version
   * 
   * This will execute the downgrade and then the upgrade of only this version.
   *
   * @param string $version
   * @return void
   */
  public function test($version) {
    $this->down($version);
    $this->up($version);
  }

  /**
   * For backwards compatibility
   */
  public function testUpgrade($version) {
    $this->test($version);
  }

  /**
   * Execute upgrade of given version
   *
   * @param string $version
   * @return void
   */
  public function up($version) {
    // check if module is set
    if(!$this->module) throw new WireException("Please set the module first: setModule(\$yourmodule)");

    // get migration
    $migration = $this->getMigration($version);
    if(!$migration) throw new WireException("Migration $version not found");
    
    // now we execute the upgrade
    $prev = @$migration->getPrev()->version;
    $this->executeUpgrade($prev, $version);
  }
  
  /**
   * Execute downgrade of given version
   *
   * @param string $version
   * @return void
   */
  public function down($version) {
    // check if module is set
    if(!$this->module) throw new WireException("Please set the module first: setModule(\$yourmodule)");

    // get migration
    $migration = $this->getMigration($version);
    if(!$migration) throw new WireException("Migration $version not found");
    
    // now we execute the upgrade
    $prev = @$migration->getPrev()->version;
    $this->executeUpgrade($version, $prev);
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
  public function getMigration($version) {
    $migration = new RockMigration();
    $migration->version = $version;
    $migration->object = $this;
    
    // find according php file
    $file = $this->getMigrationsPath().$version.".php";
    $upgrade = function(){};
    $downgrade = function(){};
    $migration->file = null;
    if(is_file($file)) {
      include($file);
      $migration->file = $file;
    }
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
     * Get field by name
     *
     * @param Field|string $name
     * @return mixed
     */
    public function getField($name, $exception = null) {
      $field = $this->fields->get((string)$name);

      // return field when found or no exception
      if($field) return $field;
      if($exception === false) return;
      
      // field was not found, throw exception
      if(!$exception) $exception = "Field $name not found";
      throw new WireException($exception);
    }

    /**
     * Create a field of the given type
     *
     * @param string $name
     * @param string $type
     * @param array $options
     * @return void
     */
    public function createField($name, $typename, $options = null) {
      $field = $this->getField($name, false);
      if(!$field) {
        // setup fieldtype
        $type = $this->modules->get($typename);
        if(!$type) {
          // shortcut types are possible, eg "text" for "FieldtypeText"
          $type = "Fieldtype".ucfirst($typename);
          $type = $this->modules->get($type);
          if(!$type) throw new WireException("Invalid Fieldtype");
        }
        
        // create the new field
        if(strtolower($name) !== $name) throw new WireException("Fieldname must be lowercase!");
        $name = strtolower($name);
        $field = $this->wire(new Field());
        $field->type = $type;
        $field->name = $name;
        $field->save();
      }

      // set options
      if($options) $field = $this->setFieldData($field, $options);

      return $field;
    }

    /**
     * Set options of an options field via string
     *
     * @param Field|string $name
     * @param string $options
     * @return void
     */
    public function setFieldOptionsString($name, $options) {
      $field = $this->getField($name);
      
      $manager = $this->wire(new SelectableOptionManager());
      $manager->setOptionsString($field, $options, false);
      $field->save();

      return $field;
    }

    /**
     * Delete the given field
     *
     * @param string $name
     * @return void
     */
    public function deleteField($name) {
      $field = $this->getField($name, false);
      if(!$field) return;

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

    /**
     * Delete given fields
     *
     * @param array $fields
     * @return void
     */
    public function deleteFields($fields) {
      foreach($fields as $field) $this->deleteField($field);
    }

    /**
     * Set the language value of the given field
     * 
     * $rm->setFieldLanguageValue("/admin/therapy", 'title', [
     *   'default' => 'Therapie',
     *   'english' => 'Therapy',
     * ]);
     *
     * @param Page|string $page
     * @param Field|string $field
     * @param array $data
     * @return void
     */
    public function setFieldLanguageValue($page, $field, $data) {
      $page = $this->pages->get((string)$page);
      if(!$page->id) throw new WireException("Page not found!");
      $field = $this->getField($field);
      
      // set field value for all provided languages
      foreach($data as $lang=>$val) {
        $lang = $this->languages->get($lang);
        if(!$lang->id) continue;
        $page->{$field}->setLanguageValue($lang, $val);
      }
      $page->save();
    }

    /**
     * Set data of a field
     * 
     * If a template is provided the data is set in template context only.
     * You can also provide an array of templates.
     * 
     * Multilang is also possible:
     * $rm->setFieldData('yourfield', [
     *   'label' => 'foo', // default language
     *   'label1021' => 'bar', // other language
     * ]);
     *
     * @param Field|string $field
     * @param array $data
     * @param Template|array|string $template
     * @return void
     */
    public function setFieldData($field, $data, $template = null) {
      $field = $this->getField($field);

      // set data
      if(!$template) {
        // set field data directly
        foreach($data as $k=>$v) $field->{$k} = $v;
      }
      else {
        // make sure the template is set as array of strings
        if(!is_array($template)) $template = [(string)$template];

        foreach($template as $t) {
          $tpl = $this->templates->get((string)$t);
          if(!$tpl) throw new WireException("Template $t not found");

          // set field data in template context
          $fg = $tpl->fieldgroup;
          $current = $fg->getFieldContextArray($field->id);
          $fg->setFieldContextArray($field->id, array_merge($current, $data));
          $fg->saveContext();
        }
      }

      $field->save();
      return $field;
    }

    /**
     * Set field order at given template
     * 
     * The first field is always the reference for all other fields.
     *
     * @param array $fields
     * @param Template|string $name
     * @return void
     */
    public function setFieldOrder($fields, $name) {
      $template = $this->templates->get((string)$name);
      if(!$template) throw new WireException("Template $name not found");

      foreach($fields as $i => $field) {
        if(!$i) continue;
        $this->addFieldToTemplate($field, $template, $fields[$i-1]);
      }
    }

    /**
     * Move one field after another
     *
     * @param Field|string $field
     * @param Field|string $after
     * @param Template|string $template
     * @return void
     */
    public function moveFieldAfter($field, $after, $template) {
      $this->addFieldToTemplate($field, $template, $after);
    }
    
    /**
     * Move one field before another
     *
     * @param Field|string $field
     * @param Field|string $before
     * @param Template|string $template
     * @return void
     */
    public function moveFieldBefore($field, $before, $template) {
      $this->addFieldToTemplate($field, $template, null, $before);
    }

    /**
     * Delete template overrides for the given field
     * 
     * Example usage:
     * Delete custom field width for 'myfield' and 'mytemplate':
     * $rm->deleteFieldTemplateOverrides('myfield', [
     *   'mytemplate' => ['columnWidth'],
     * ]);
     *
     * @param Field|string $field
     * @param array $templatesettings
     * @return void
     */
    public function deleteFieldTemplateOverrides($field, $templatesettings) {
      $field = $this->getField($field);

      // loop data
      foreach($templatesettings as $tpl=>$val) {
        // get template
        $template = $this->templates->get((string)$tpl);
        if(!$template) throw new WireException("Template $tpl not found");
        
        // set field data in template context
        $fg = $template->fieldgroup;
        $data = $fg->getFieldContextArray($field->id);
        foreach($val as $setting) unset($data[$setting]);
        $fg->setFieldContextArray($field->id, $data);
        $fg->saveContext();
      }
      
    }

    /**
     * Add field to template
     *
     * @param Field|string $field
     * @param Template|string $template
     * @return void
     */
    public function addFieldToTemplate($field, $template, $afterfield = null, $beforefield = null) {
      $field = $this->getField($field);
      $template = $this->getTemplate($template);
      
      $afterfield = $this->fields->get((string)$afterfield);
      $beforefield = $this->fields->get((string)$beforefield);
      $fg = $template->fieldgroup; /** @var Fieldgroup $fg */

      if($afterfield) $fg->insertAfter($field, $afterfield);
      elseif($beforefield) $fg->insertBefore($field, $beforefield);
      else $fg->add($field);
      $fg->save();
    }

    /**
     * Add fields to template.
     * 
     * Simple:
     * $rm->addFieldsToTemplate(['field1', 'field2'], 'yourtemplate');
     * 
     * Add fields at special positions:
     * $rm->addFieldsToTemplate([
     *   'field1',
     *   'field4' => 'field3', // this will add field4 after field3
     * ], 'yourtemplate');
     *
     * @param array $fields
     * @param string $template
     * @return void
     */
    public function addFieldsToTemplate($fields, $template) {
      foreach($fields as $k=>$v) {
        // if the key is an integer, it's a simple field
        if(is_int($k)) $this->addFieldToTemplate($v, $template);
        else $this->addFieldToTemplate($k, $template, $v);
      }
    }

    /**
     * Remove Field from Template
     *
     * @param Field|string $field
     * @param Template|string $template
     * @return void
     */
    public function removeFieldFromTemplate($field, $template) {
      $field = $this->getField($field, false);
      if(!$field) return;
      
      $template = $this->templates->get((string)$template);
      if(!$template) return;
      $fg = $template->fieldgroup; /** @var Fieldgroup $fg */

      $fg->remove($field);
      $fg->save();
    }

  /* ##### templates ##### */

    /**
     * Get template by name
     *
     * @param Template|string $name
     * @return mixed
     */
    public function getTemplate($name, $exception = null) {
      $template = $this->templates->get((string)$name);

      // return template when found or no exception
      if($template) return $template;
      if($exception === false) return;
      
      // template was not found, throw exception
      if(!$exception) $exception = "Template not found";
      throw new WireException($exception);
    }

    /**
     * Create a new ProcessWire Template
     *
     * @param string $name
     * @return void
     */
    public function createTemplate($name) {
      $t = $this->templates->get((string)$name);
      if($t) return $t;

      // create new fieldgroup
      $fg = $this->wire(new Fieldgroup());
      $fg->name = $name;
      $fg->save();

      // create new template
      $t = $this->wire(new Template());
      $t->name = $name;
      $t->fieldgroup = $fg;
      $t->save();

      return $t;
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
    
    /**
     * Set data of a template
     * 
     * TODO: Set data in template context.
     * TODO: Wording is inconsistant! Set = Update, because it only sets
     * provided key value pairs and not the whole array
     * 
     * Multilang is also possible:
     * $rm->setTemplateData('yourtemplate', [
     *   'label' => 'foo', // default language
     *   'label1021' => 'bar', // other language
     * ]);
     *
     * @param Template|string $template
     * @param array $data
     * @return void
     */
    public function setTemplateData($template, $data) {
      $template = $this->templates->get((string)$template);
      if(!$template) throw new WireException("template not found!");
      foreach($data as $k=>$v) $template->{$k} = $v;
      $template->save();
      return $template;
    }
  
  /* ##### pages ##### */

    /**
     * Create a new Page
     * 
     * If the page exists it will return the existing page.
     * All available languages will be set active by default for this page.
     *
     * @param string $title
     * @param string $name
     * @param Template|string $template
     * @param Page|string $parent
     * @param array $status
     * @return void
     */
    public function createPage($title, $name, $template, $parent, $status = []) {
      $page = $this->pages->get([
        'name' => $name,
        'template' => $template,
        'parent' => $parent,
      ]);
      if($page->id) {
        $page->status($status);
        $page->save();
        return $page;
      }

      // create a new page
      $p = $this->wire(new Page());
      $p->template = $template;
      $p->title = $title;
      $p->name = $name;
      $p->parent = $parent;
      $p->status($status);
      $p->save();

      // enable all languages for this page
      $this->enableAllLanguagesForPage($p);

      return $p;
    }

    /**
     * Enable all languages for given page
     *
     * @param Page|string $page
     * @return void
     */
    public function enableAllLanguagesForPage($page) {
      $page = $this->pages->get((string)$page);
      foreach ($this->languages as $lang) $page->set("status$lang", 1);
      $page->save();
    }

    /**
     * Delete the given page including all children.
     *
     * @param Page|string $page
     * @return void
     */
    public function deletePage($page) {
      // make sure we got a page
      $page = $this->pages->get((string)$page);
      if(!$page->id) return;
      
      // make sure we can delete the page and delete it
      // we also need to make sure that all descendants of this page are deletable
      // todo: make this recursive?
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
     * Add a permission to given role
     *
     * @param string|int $permission
     * @param string|int $role
     * @return boolean
     */
    public function addPermissionToRole($permission, $role) {
      $role = $this->roles->get((string)$role);
      $role->of(false);
      $role->addPermission($permission);
      return $role->save();
    }

    /**
     * Add an array of permissions to an array of roles
     *
     * @param array|string $permissions
     * @param array|string $roles
     * @return void
     */
    public function addPermissionsToRoles($permissions, $roles) {
      if(!is_array($permissions)) $permissions = [(string)$permissions];
      if(!is_array($roles)) $roles = [(string)$roles];
      foreach($permissions as $permission) {
        foreach ($roles as $role) {
          $this->addPermissionToRole($permission, $role);
        }
      }
    }

    /**
     * Remove a permission from given role
     *
     * @param string|int $permission
     * @param string|int $role
     * @return void
     */
    public function removePermissionFromRole($permission, $role) {
      $role = $this->roles->get((string)$role);
      $role->of(false);
      $role->removePermission($permission);
      return $role->save();
    }

    /**
     * Remove an array of permissions to an array of roles
     *
     * @param array|string $permissions
     * @param array|string $roles
     * @return void
     */
    public function removePermissionsFromRoles($permissions, $roles) {
      if(!is_array($permissions)) $permissions = [(string)$permissions];
      if(!is_array($roles)) $roles = [(string)$roles];
      foreach($permissions as $permission) {
        foreach ($roles as $role) {
          $this->removePermissionFromRole($permission, $role);
        }
      }
    }

    /**
     * Create permission with given name
     *
     * @param string $name
     * @param string $description
     * @return Permission
     */
    public function createPermission($name, $description = null) {
      // if the permission exists return it
      $permission = $this->permissions->get((string)$name);
      if(!$permission->id) $permission = $this->permissions->add($name);
      $permission->setAndSave('title', $description);
      return $permission;
    }

    /**
     * Delete the given permission
     *
     * @param Permission|string $permission
     * @return void
     */
    public function deletePermission($permission) {
      $permission = $this->permissions->get((string)$permission);
      if(!$permission->id) return;
      $this->permissions->delete($permission);
    }

    /**
     * Create role with given name
     *
     * @param string $name
     * @param array $permissions
     * @return void
     */
    public function createRole($name, $permissions = []) {
      // if the role exists return it
      $role = $this->roles->get((string)$name);
      if(!$role->id) $role = $this->roles->add($name);

      // add permissions
      foreach($permissions as $permission) $this->addPermissionToRole($permission, $role);

      return $role;
    }

    /**
     * Delete the given role
     *
     * @param Role|string $role
     * @return void
     */
    public function deleteRole($role) {
      $role = $this->roles->get((string)$role);
      if(!$role->id) return;
      $this->roles->delete($role);
    }

  /* ##### users ##### */

    /**
     * Create a PW user with given password
     * 
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
     * Delete a PW user
     *
     * @param string $username
     * @return void
     */
    public function deleteUser($username) {
      $user = $this->users->get($username);
      if(!$user->id) return;
      $u = $this->wire->users->delete($user);
    }

    /**
     * Add role to user
     *
     * @param string $role
     * @param User|string $user
     * @return void
     */
    public function addRoleToUser($role, $user) {
      /** @var User $user */
      $user = $this->users->get((string)$user);
      if(!$user->id) throw new WireException("User not found");
      $user->addRole($role);
      $user->save();
    }

    /**
     * Add roles to user
     *
     * @param array $roles
     * @param User|string $user
     * @return void
     */
    public function addRolesToUser($roles, $user) {
      foreach($roles as $role) $this->addRoleToUser($role, $user);
    }

  /* ##### modules ##### */

    /**
     * Set module config data
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
     * Update module config data
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
     * Get module config data
     *
     * @param string $module
     * @return array
     */
    public function getModuleConfig($module) {
      $module = $this->modules->get($module);
      return $this->modules->getModuleConfigData($module);
    }

    /**
     * Install module
     * 
     * If an URL is provided the module will be downloaded before installation.
     *
     * @param string $name
     * @param string $url
     * @return void
     */
    public function installModule($name, $url = null) {
      // if the module is already installed we return it
      $module = $this->modules->get((string)$name);
      if($module) return $module;

      // if an url was provided, download the module
      if($url) $this->downloadModule($url);

      // install and return the module
      return $this->modules->install($name);
    }

    /**
     * Download module from url
     *
     * @param string $url
     * @return void
     */
    public function downloadModule($url) {
      require_once($this->config->paths->modules . "Process/ProcessModule/ProcessModuleInstall.php");
      $install = $this->wire(new ProcessModuleInstall());
      $install->downloadModule($url);
    }
    
    /**
     * Uninstall module
     *
     * @param string|Module $name
     * @return void
     */
    public function uninstallModule($name) {
      $this->modules->uninstall((string)$name);
    }

    /**
     * Delete module
     *
     * @param string $name
     * @return void
     */
    public function deleteModule($name) {
      $module = $this->modules->get((string)$name);
      $this->uninstallModule($name);
      $this->files->rmdir($this->config->paths($module), true);
    }

  /* ##### languages ##### */

    /**
     * Language support via API is tricky! For the time it is recommended to
     * enable language support manually and then do all further changes via API.
     */

    // /**
    //  * Install language support.
    //  * 
    //  * It can be helpful to completely remove language support in some situations:
    //  * https://processwire.com/talk/topic/7207-can%C2%B4t-install-languagesupport/
    //  *
    //  * @return void
    //  */
    // public function installLanguageSupport() {
    //   $this->modules->install('LanguageSupport');
    //   $this->modules->install('LanguageSupportFields');
    //   $this->modules->install('LanguageSupportPageNames');
    //   $this->modules->install('LanguageTabs');
    // }

    // /**
    //  * Uninstall language support.
    //  *
    //  * @return void
    //  */
    // public function uninstallLanguageSupport() {
    //   $this->modules->uninstall('LanguageTabs');
    //   $this->modules->uninstall('LanguageSupportPageNames');
    //   $this->modules->uninstall('LanguageSupportFields');
    //   $this->modules->uninstall('LanguageSupport');
    // }

    // /**
    //  * Reset language support.
    //  * This can help if you have trouble uninstalling language support manually:
    //  * https://processwire.com/talk/topic/7207-can%C2%B4t-install-languagesupport/
    //  *
    //  * @return void
    //  */
    // public function resetLanguageSupport() {
    //   $setup = $this->pages->get('parent.id=2, name=setup');
    //   $this->deletePage($this->pages->get([
    //     'name' => 'language-translator',
    //     'parent' => $setup,
    //   ]));
    //   $this->deletePage($this->pages->get([
    //     'name' => 'languages',
    //     'parent' => $setup,
    //   ]));
    //   $this->deleteField('language');
    //   $this->deleteField('language_files');
    //   $this->deleteTemplate('language');
    //   $this->modules->uninstall('ProcessLanguageTranslator');
    //   $this->modules->uninstall('ProcessLanguage');
    //   @$this->modules->uninstall('LanguageSupport');
    // }
}
