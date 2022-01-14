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

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations',
      'version' => '0.0.86',
      'summary' => 'Module to handle Migrations inside your Modules easily.',
      'autoload' => true,
      'singular' => true,
      'icon' => 'bolt',
    ];
  }

  public function init() {
    // load the RockMigration Object Class
    require_once('RockMigration.class.php');

    // set API variable
    $this->wire('rockmigrations', $this);

    // attach hooks
    $this->loadFilesOnDemand();
  }

  /**
   * Add script to pw $config
   * Usage:
   * $rm->addScript(__DIR__."/Foo.js");
   */
  public function addScript($path, $timestamp = true) {
    if(!is_file($path)) return;
    $path = Paths::normalizeSeparators($path);
    $config = $this->wire->config;
    $url = str_replace($config->paths->root, $config->urls->root, $path);
    $m = $timestamp ? "?m=".filemtime($path) : '';
    $this->wire->config->scripts->add($url.$m);
  }

  /**
   * Add style to pw $config
   * Usage:
   * $rm->addStyle(__DIR__."/Foo.js");
   */
  public function addStyle($path, $timestamp = true) {
    if(!is_file($path)) return;
    $path = Paths::normalizeSeparators($path);
    $config = $this->wire->config;
    $url = str_replace($config->paths->root, $config->urls->root, $path);
    $m = $timestamp ? "?m=".filemtime($path) : '';
    $this->wire->config->styles->add($url.$m);
  }

  /**
   * Register autoloader for all classes in given folder
   * This will NOT trigger init() or ready()
   * You can also use $rm->initClasses() with setting autoload=true
   */
  public function autoload($path, $namespace) {
    $path = Paths::normalizeSeparators($path);
    spl_autoload_register(function($class) use($path, $namespace) {
      if(strpos($class, "$namespace\\") !== 0) return;
      $name = substr($class, strlen($namespace)+1);
      $file = "$path/$name.php";
      if(is_file($file)) require_once($file);
    });
  }

  /**
   * Execute downgrade of given version
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
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
   * Execute the upgrade from one version to another
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
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
   * Execute all Upgrade Scripts on Installation
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
   *
   * @return void
   */
  public function executeInstall() {
    // check if module is set
    if(!$this->module) throw new WireException("Please set the module first: setModule(\$yourmodule)");

    $version = $this->modules->getModuleInfo($this->module)['version'];
    $versionStr = $this->modules->formatVersion($version);
    return $this->executeUpgrade(null, $versionStr);
  }

  /**
   * Execute all Downgrade Scripts on Uninstallation
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
   *
   * @return void
   */
  public function executeUninstall() {
    // check if module is set
    if(!$this->module) throw new WireException("Please set the module first: setModule(\$yourmodule)");

    $version = $this->modules->getModuleInfo($this->module)['version'];
    $versionStr = $this->modules->formatVersion($version);
    return $this->executeUpgrade($versionStr, null);
  }

  /**
   * for backwards compatibility
   */
  public function executeUpgrade($from, $to, $module = null) {
    return $this->execute($from, $to, $module);
  }

  /**
   * This will add a hook after Modules::refresh
   * It will be executed only for superusers!
   *
   * Usage:
   * In your module's init() use
   * $rm->fireOnRefresh($this, "migrate");
   *
   * In ready.php you can use it with a callback function:
   * $rm->fireOnRefresh(function($event) use($rm) {
   *   $rm->deleteField(...);
   * });
   *
   * @param Module $module module or callback
   * @param string $method the method name to invoke
   * @param int|array $priority options array for the hook; if you provide
   * an integer value it will be casted to the hook priority ['priority'=>xxx]
   *
   * @return void
   */
  public function fireOnRefresh($module, $method = null, $priority = []) {
    // If flags are present dont attach hooks to Modules::refresh
    // See the readme for more information!
    if(defined("DontFireOnRefresh")) return;
    if($this->wire->config->DontFireOnRefresh) return;

    // attach the hook
    if(is_int($priority)) $priority = ['priority'=>$priority];
    if($module instanceof Module) {
      $this->wire->addHookAfter("Modules::refresh", $module, $method, $priority);
    }
    elseif(is_callable($module)) {
      $callback = $module;
      $this->wire->addHookAfter("Modules::refresh", $callback, null, $priority);
    }
  }

  /**
   * Helper that returns a new Inputfield object from array syntax
   *
   * Usage:
   * $f = $rm->getInputfield(['type'=>'markup', 'value'=>'foo']);
   * $form->insertAfter($f, $form->get('title'));
   *
   * @return Inputfield
   */
  public function getInputfield($array) {
    $form = new InputfieldForm();
    $form->add($array);
    return $form->children()->first();
  }

  /**
   * Get Migration Object from Version Number
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
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
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
   *
   * @param Module $module
   * @return array
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
  public function ___getMigrationsPath() {
    return $this->config->paths($this->module) . $this->className() . "/";
  }

  /**
   * Get page from data
   * @return Page
   */
  public function getPage($data) {
    return $this->wire->pages->get((string)$data);
  }

  /**
   * Get textformatter from name
   * @return Textformatter
   */
  public function getTextformatter($name) {
    $formatter = $this->wire->modules->get("Textformatter".$name);
    if(!$formatter) $formatter = $this->wire->modules->get($name);
    return $formatter;
  }

  /**
   * Create view files with include statement in templates folder
   *
   * Usage:
   * Let's say we have a module "Foo"; Inside the module's path we have
   * a "views" folder. In the module's fireOnRefresh() migration we simply add:
   *
   * public function migrate() {
   *   $rm = ...
   *   $rm->migrate(...); // migrate fields and templates
   *   $rm->includeViews($this);
   * }
   *
   * This will look for php files in folder /path/to/your/module/views and create
   * a php file in /site/templates that include()'s the related PHP file from
   * within the modules folder. This means that you can manage the code of the
   * template file from within your module without ever copying any files manually
   * to the templates folder :)
   *
   * @return void
   */
  public function includeViews($path) {
    $config = $this->wire->config;
    $files = $this->wire->files;
    if($path instanceof Module) $path = $config->paths($path)."views";
    foreach($files->find($path, ['extensions' => ['php']]) as $file) {
      $name = pathinfo($file, PATHINFO_FILENAME);
      $url = str_replace($config->paths->root, '', $file);
      $content = "<?php namespace ProcessWire;\n"
        ."// DONT CHANGE THIS FILE\n"
        ."// it is created automatically via RockMigrations\n"
        ."include(\$config->paths->root.'$url');\n";
      file_put_contents($config->paths->templates.$name.".php", $content);
    }
  }

  /**
   * Trigger init() method of classes in this folder
   *
   * If autoload is set to TRUE it will attach a class autoloader before
   * triggering the init() method. The autoloader is important so that we do
   * not get any conflicts on the loading order of the classes. This could
   * happen if we just used require() in here because then the loadind order
   * would depend on the file names of loaded classes. This would cause problems
   * if for example class BAR was dependent on class FOO which would not exist
   * on load of BAR.
   *
   * @return void
   */
  public function initClasses($path, $namespace = "ProcessWire", $autoload = true) {
    if($autoload) $this->autoload($path, $namespace);
    foreach($this->files->find($path, ['extensions' => ['php']]) as $file) {
      $info = $this->info($file);
      $class = $info->filename;
      if($namespace) $class = "\\$namespace\\$class";
      $tmp = new $class();
      if(method_exists($tmp, "init")) $tmp->init();

      // attach hooks for some magic methods
      if(method_exists($tmp, "buildForm")) {
        $this->wire->addHookAfter("ProcessPageEdit::buildForm", $tmp, "buildForm");
      }
      if(method_exists($tmp, "buildFormContent")) {
        $this->wire->addHookAfter("ProcessPageEdit::buildFormContent", $tmp, "buildFormContent");
      }
    }
  }

  /**
   * DEPRECATED - use initClasses() instead
   */
  public function initPageClass($data, $options = []) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'method' => 'init',
      'namespace' => 'ProcessWire',
    ]);
    $opt->setArray($options);

    // load existing page or setup a new one
    try {
      $page = $this->wire->pages->get((string)$data);
    } catch (\Throwable $th) {
      $this->wire->classLoader->loadClass($data);
      $class = "\\{$opt->namespace}\\$data";
      $page = $this->wire(new $class());
    }

    // trigger $page->init() or $page->ready()
    if(!method_exists($page, $opt->method)) return;
    $page->{$opt->method}();
  }

  /**
   * Load files on demand on local installation
   *
   * Usage: set $config->filesOnDemand = 'your.hostname.com' in your config file
   *
   * Make sure that this setting is only used on your local test config and not
   * on a live system!
   *
   * @return void
   */
  public function loadFilesOnDemand() {
    if(!$host = $this->wire->config->filesOnDemand) return;
    $hook = function(HookEvent $event) use($host) {
      $config = $this->wire->config;
      $file = $event->return;

      // this makes it possible to prevent downloading at runtime
      if(!$this->wire->config->filesOnDemand) return;

      // convert url to disk path
      if($event->method == 'url') {
        $file = $config->paths->root.substr($file, strlen($config->urls->root));
      }

      // load file from remote if it does not exist
      if(!file_exists($file)) {
        $host = rtrim($host, "/");
        $src = "$host/site/assets/files/";
        $url = str_replace($config->paths->files, $src, $file);
        $http = $this->wire(new WireHttp()); /** @var WireHttp $http */
        try {
          $http->download($url, $file);
        } catch (\Throwable $th) {
          // do not throw exception, show error message instead
          $this->error($th->getMessage());
        }
      }
    };
    $this->addHookAfter("Pagefile::url", $hook);
    $this->addHookAfter("Pagefile::filename", $hook);
  }

  /**
   * Trigger ready() method of classes in this folder
   * This will NOT load the classes - use autoload() or initClasses() before
   * @return void
   */
  public function readyClasses($path, $namespace = "ProcessWire") {
    foreach($this->files->find($path, ['extensions' => ['php']]) as $file) {
      $info = $this->info($file);
      $class = $info->filename;
      if($namespace) $class = "\\$namespace\\$class";
      $tmp = new $class();
      if(method_exists($tmp, "ready")) $tmp->ready();
    }
  }

  /**
   * DEPRECATED - use initClasses() instead
   */
  public function readyPageClass($data) {
    $this->initPageClass($data, 'ready');
  }

  /**
   * Set the logo url of the backend logo (AdminThemeUikit)
   * @return void
   */
  public function setAdminLogoUrl($url) {
    $this->setModuleConfig("AdminThemeUikit", ['logoURL' => $url]);
  }

  /**
   * Set default options for several things in PW
   */
  public function setDefaults($options = []) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray([
      'pagenameReplacements' => 'de',
      'toggleBehavior' => 1,
    ]);
    $opt->setArray($options);

    // set german pagename replacements
    $this->setPagenameReplacements($opt->pagenameReplacements);

    // AdminThemeUikit settings
    $this->setModuleConfig("AdminThemeUikit", [
      // use consistent inputfield clicks
      // see https://github.com/processwire/processwire/pull/169
      'toggleBehavior' => $opt->toggleBehavior,
    ]);

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
   * Set page name from page title
   *
   * Usage:
   * $rm->setPageNameFromTitle("basic-page");
   *
   * Make sure to install Page Path History module!
   *
   * @param mixed $object
   */
  public function setPageNameFromTitle($template) {
    $template = $this->getTemplate($template);
    $tpl = "template=$template";
    $this->addHookAfter("Pages::saveReady($tpl,id>0)", function(HookEvent $event) {
      /** @var Page $page */
      $page = $event->arguments(0);
      $langs = $this->wire->languages;
      if($langs) {
        foreach($langs as $lang) {
          $prop = $lang->isDefault() ? "name" : "name$lang";
          $old = $page->get($prop);
          $new = $page->getLanguageValue($lang, "title");
          $new = $event->sanitizer->pageNameTranslate($new);
          if($new AND $old!=$new) {
            $page->set($prop, $new);
            $this->message($this->_("Page name updated to $new ($lang->name)"));
          }
        }
      }
      else {
        $old = $page->name;
        $new = $event->sanitizer->pageNameTranslate($page->title);
        if($new AND $old!=$new) {
          $page->name = $new;
          $this->message($this->_("Page name updated to $new"));
        }
      }
    });
    $this->addHookAfter("ProcessPageEdit::buildForm", function(HookEvent $event) use($template) {
      $page = $event->object->getPage();
      if($page->template != $template) return;
      $form = $event->return;
      if($f = $form->get('_pw_page_name')) {
        $f->notes = $this->_('Page name will be set automatically from page title on save.');
      }
    });
  }

  /**
   * Change current user to superuser
   * When bootstrapped sometimes we get permission conflicts
   * See https://processwire.com/talk/topic/458-superuser-when-bootstrapping/
   * @return void
   */
  public function sudo() {
    $id = $this->wire->config->superUserPageID;
    $this->wire->users->setCurrentUser($this->wire->users->get($id));
  }

  /**
   * Test upgrade for given version
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
   *
   * This will execute the downgrade and then the upgrade of only this version.
   *
   * @param string $version
   * @return void
   */
  public function test($version) {
    $this->down($version);
    $this->modules->refresh();
    $this->up($version);
  }

  /**
   * For backwards compatibility
   */
  public function testUpgrade($version) {
    $this->test($version);
  }

  /**
   * Trigger method of class if it exists
   *
   * Usage:
   *
   * In your module's init()
   * $rm->trigger("\Foo\Bar", "init");
   *
   * In your module's ready()
   * $rm->trigger("\Foo\Bar", "ready");
   *
   * @return void
   */
  public function trigger($class, $method) {
    $obj = new $class();
    if(method_exists($obj, $method)) $obj->$method();
  }

  /**
   * Execute upgrade of given version
   * DEPRECATED SEE https://bit.ly/3lPWg3Q
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
   * Wrap fields of a form into a fieldset
   *
   * Usage:
   * $rm->wrapFields($form, ['foo', 'bar'], [
   *   'label' => 'your fieldset label',
   *   'icon' => 'bolt',
   * ]);
   *
   * @return InputfieldFieldset
   */
  public function wrapFields(InputfieldWrapper $form, array $fields, array $fieldset) {
    $_fields = [];
    $last = false;
    foreach($fields as $field) {
      $f = $form->get((string)$field);
      if($f instanceof Inputfield) {
        $_fields[] = $f;
        $last = $f;
      }
    }
    if(!$last) return;

    /** @var InputfieldFieldset $f */
    $fs = $this->wire('modules')->get('InputfieldFieldset');
    foreach($fieldset as $k=>$v) $fs->$k = $v;
    $form->insertAfter($fs, $last);

    // now remove fields from the form and add them to the fieldset
    foreach($_fields as $f) {
      $form->remove($f);
      $fs->add($f);
    }

    return $fs;
  }

  /* ##################### RockMigrations API Methods ##################### */

  /* ##### fields ##### */

    /**
     * Add field to template
     *
     * @param Field|string $field
     * @param Template|string $template
     * @return void
     */
    public function addFieldToTemplate($_field, $_template, $afterfield = null, $beforefield = null) {
      $field = $this->getField($_field, false);
      if(!$field) return $this->log("Field $_field not found");
      $template = $this->getTemplate($_template, false);
      if(!$template) return $this->log("Template $_template not found");

      $afterfield = $this->getField($afterfield, false);
      $beforefield = $this->getField($beforefield, false);
      $fg = $template->fieldgroup; /** @var Fieldgroup $fg */

      if($afterfield) $fg->insertAfter($field, $afterfield);
      elseif($beforefield) $fg->insertBefore($field, $beforefield);
      else $fg->add($field);

      // add end field for fieldsets
      if($field->type instanceof FieldtypeFieldsetOpen
        AND !$field->type instanceof FieldtypeFieldsetClose) {
        $closer = $field->type->getFieldsetCloseField($field, false);
        $this->addFieldToTemplate($closer, $template, $field);
      }

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
     * @param bool $sortFields
     * @return void
     */
    public function addFieldsToTemplate($fields, $template, $sortFields = false) {
      foreach($fields as $k=>$v) {
        // if the key is an integer, it's a simple field
        if(is_int($k)) $this->addFieldToTemplate((string)$v, $template);
        else $this->addFieldToTemplate((string)$k, $template, $v);
      }
      if($sortFields) $this->setFieldOrder($fields, $template);
    }

    /**
     * Add matrix item to given field
     * @param Field|string $field
     * @param string $name
     * @param array $data
     * @return Field|null
     */
    public function addMatrixItem($field, $name, $data) {
      if(!$field = $this->getField($field, false)) return;

      // get number
      $n = 1;
      while(array_key_exists("matrix{$n}_name", $field->getArray())) $n++;
      $prefix = "matrix{$n}_";

      $field->set($prefix."name", $name);
      $field->set($prefix."sort", $n);
      foreach($this->getMatrixDataArray($data) as $key => $val) {
        // eg set matrix1_label = ...
        $field->set($prefix.$key, $val);
        if($key === "fields") {
          $tpl = $this->getRepeaterTemplate($field);
          $this->addFieldsToTemplate($val, $tpl);
        }
      }

      $field = $this->resetMatrixRepeaterFields($field);
      $field->save();
      return $field;
    }

    /**
     * Add textformatter to given field
     * You can set a template context as third parameter
     * @param mixed $formatter formatter to add
     * @param mixed $field to add the formatter to
     * @return void
     */
    public function addTextformatterToField($formatter, $field) {
      $name = (string)$formatter;
      $formatter = $this->getTextformatter($name);
      if(!$formatter) return $this->log("Formatter $name not found");
      $formatters = $this->getFieldData($field, "textformatters") ?: [];
      $formatters = array_merge($formatters, [(string)$formatter]);
      $this->setFieldData($field, [
        'textformatters' => $formatters,
      ]);
    }

    /**
     * Change type of field
     * @param Field|string $field
     * @param string $type
     * @param bool $keepSettings
     * @return Field
     */
    public function changeFieldtype($field, $type, $keepSettings = true) {
      $field = $this->getField($field);

      // if type is already set, return early
      if($field->type == $type) return $field;

      // change type and save field
      $field->type = $type;
      $this->fields->changeFieldtype($field, $keepSettings);
      $field->save();
      return $field;
    }

    /**
     * Create a field of the given type
     *
     * @param string $name
     * @param string $type
     * @param array $options
     * @return Field
     */
    public function createField($name, $typename, $options = null) {
      $field = $this->getField($name, false);
      if(!$field) {
        // handle special cases
        if(is_string($typename)) {
          // type 'page' does not work because it tries to get the page module
          if(strtolower($typename) === 'page') $typename = "FieldtypePage";
        }
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
        $field->label = $name; // set label (mandatory since ~3.0.172)
        $field->save();

        // create end field for fieldsets
        if($field->type instanceof FieldtypeFieldsetOpen) {
          $field->type->getFieldsetCloseField($field, true);
        }

        // this will auto-generate the repeater template
        if($field->type instanceof FieldtypeRepeater) {
          $field->type->getRepeaterTemplate($field);
        }
      }

      // set options
      if($options) $field = $this->setFieldData($field, $options);

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

      // delete _END field for fieldsets first
      if($field->type instanceof FieldtypeFieldsetOpen) {
        $closer = $field->type->getFieldsetCloseField($field, false);
        $this->deleteField($closer);
      }

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
     * If parameter is a string we use it as selector for $fields->find()
     *
     * Usage:
     * $rm->deleteFields("tags=MyModule");
     *
     * @param array|string $fields
     * @return void
     */
    public function deleteFields($fields) {
      if(is_string($fields)) $fields = $this->wire->fields->find($fields);
      foreach($fields as $field) $this->deleteField($field);
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
     * Get field by name
     *
     * @param Field|string $name
     * @return mixed
     */
    public function getField($name, $exception = null) {
      if(!$name) return false;
      if($name AND !is_string($name) AND !$name instanceof Field) {
        $func = @debug_backtrace()[1]['function'];
        throw new WireException("Invalid type set for field in $func");
      }
      $field = $this->fields->get((string)$name);

      // return field when found or no exception
      if($field) return $field;
      if($exception === false) return false;

      // field was not found, throw exception
      if(!$exception) $exception = "Field $name not found";
      throw new WireException($exception);
    }

    /**
     * Get field data of field
     * @return array
     */
    public function getFieldData($field, $property = null) {
      $field = $this->getField($field);
      $arr = $field->getArray();
      if(!$property) return $arr;
      if(!array_key_exists($property, $arr)) return false;
      return $arr[$property];
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
     * Remove Field from Template
     *
     * @param Field|string $field
     * @param Template|string $template
     * @param bool $force
     * @return void
     */
    public function removeFieldFromTemplate($field, $template, $force = false) {
      $field = $this->getField($field, false);
      if(!$field) return;

      $template = $this->templates->get((string)$template);
      if(!$template) return;
      $fg = $template->fieldgroup; /** @var Fieldgroup $fg */

      // remove global flag to force deletion
      if($force) $field->flags = 0;

      $fg->remove($field);
      $fg->save();
    }

    /**
     * See method above
     */
    public function removeFieldsFromTemplate($fields, $template, $force = false) {
      foreach($fields as $field) $this->removeFieldFromTemplate($field, $template, $force);
    }

    /**
     * Remove matrix item from field
     * @param Field|string $field
     * @param string $name
     * @return Field|null
     */
    public function removeMatrixItem($field, $name) {
      if(!$field = $this->getField($field, false)) return;
      $info = $field->type->getMatrixTypesInfo($field, ['type'=>$name]);
      if(!$info) return;

      // reset all properties of that field
      foreach($field->getArray() as $prop=>$val) {
        if(strpos($prop, $info['prefix']) !== 0) continue;
        $field->set($prop, null);
      }

      $field = $this->resetMatrixRepeaterFields($field);
      $field->save();
      return $field;
    }

    /**
     * Rename this field
     * @return Field|false
     */
    public function renameField($oldname, $newname) {
      $field = $this->getField($oldname, false);
      if(!$field) return false;

      // the new field must not exist
      $newfield = $this->getField($newname, false);
      if($newfield) throw new WireException("Field $newname already exists");

      // change the old field
      $field->name = $newname;
      $field->save();
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
    public function setFieldData($_field, $data, $template = null) {
      $field = $this->getField($_field, false);
      if(!$field) return $this->log("Field $_field not found");

      // prepare data array
      foreach($data as $key=>$val) {

        // this makes it possible to set the template via name
        if($key === "template_id") {
          $data[$key] = $this->templates->get($val)->id;
        }

        // support repeater field array
        $contexts = [];
        if($key === "repeaterFields") {
          $fields = $data[$key];
          $addFields = [];
          $index = 0;
          foreach($fields as $i=>$_field) {
            if(is_string($i)) {
              // we've got a field with field context info here
              $fieldname = $i;
              $fielddata = $_field;
              $contexts[] = [
                $fieldname,
                $fielddata,
                $this->getRepeaterTemplate($field),
              ];
            }
            else {
              // field without field context info
              $fieldname = $_field;
            }
            $addFields[$index] = $this->fields->get((string)$fieldname)->id;
            $index++;
          }
          $data[$key] = $addFields;

          // add fields to repeater template
          if($tpl = $this->getRepeaterTemplate($field)) {
            $this->addFieldsToTemplate($addFields, $tpl);
          }

          // set field contexts now that the fields are present
          foreach($contexts as $c) {
            $this->setFieldData($c[0], $c[1], $c[2]);
          }

        }

        // add support for setting options of a select field
        // this will remove non-existing options from the field!
        if($key === "options") {
          $options = $data[$key];
          $this->setOptions($field, $options, true);

          // this prevents setting the "options" property directly to the field
          // if not done, the field shows raw option values when rendered
          unset($data['options']);
        }

      }

      // set data
      if(!$template) {
        // set field data directly
        foreach($data as $k=>$v) $field->set($k, $v);
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

      // Make sure Table field actually updates database schema
      if ($field->type == "FieldtypeTable") {
        $fieldtypeTable = $field->getFieldtype();
        $fieldtypeTable->_checkSchema($field, true); // Commit changes
      }

      $field->save();
      return $field;
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
      $page->of(false);
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
     * Set options of an options field via string
     *
     * Update: Better use $rm->setOptions($field, $options);
     *
     * $rm->setFieldOptionsString("yourfield", "
     *   1=foo|My Foo Option
     *   2=bar|My Bar Option
     * ");
     *
     * @param Field|string $name
     * @param string $options
     * @return void
     */
    public function setFieldOptionsString($name, $options, $removeOthers = false) {
      $field = $this->getField($name);

      $manager = $this->wire(new SelectableOptionManager());

      // now set the options
      $manager->setOptionsString($field, $options, $removeOthers);
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

      // make sure that all fields exist
      foreach($fields as $i=>$field) {
        if(!$this->fields->get($field)) unset($fields[$i]);
      }
      $fields = array_values($fields); // reset indices

      foreach($fields as $i => $field) {
        if(!$i) continue;
        $this->addFieldToTemplate($field, $template, $fields[$i-1]);
      }
    }

    /**
     * Set matrix item data
     * @param Field|string $field
     * @param string $name
     * @param array $data
     * @return Field|null
     */
    public function setMatrixItemData($field, $name, $data) {
      if(!$field = $this->getField($field, false)) return;
      $info = $field->type->getMatrixTypesInfo($field, ['type'=>$name]);
      if(!$info) return;
      foreach($this->getMatrixDataArray($data) as $key => $val) {
        // eg set matrix1_label = ...
        $field->set($info['prefix'].$key, $val);
        if($key === "fields") {
          $tpl = $this->getRepeaterTemplate($field);
          $this->addFieldsToTemplate($val, $tpl);
        }
      }

      $field = $this->resetMatrixRepeaterFields($field);
      $field->save();
      return $field;
    }

    /**
     * Set options of an options field as array
     *
     * Usage:
     * $rm->setOptions($field, [
     *   1 => 'foo|My foo option',
     *   2 => 'bar|My bar option',
     * ]);
     *
     * CAUTION: Make sure that you do not set 0 as key of any option!
     * See https://github.com/BernhardBaumrock/RockMigrations/issues/16
     *
     * @param Field|string $field
     * @param array $options
     * @param bool $allowDelete
     * @return Field|null
     */
    public function setOptions($field, $options, $allowDelete = false) {
      $string = "";
      foreach($options as $k=>$v) $string.="\n$k=$v";
      return $this->setFieldOptionsString($field, $string, $allowDelete);
    }

    /**
     * Set items of a RepeaterMatrix field
     *
     * If wipe is set to TRUE it will wipe all existing matrix types before
     * setting the new ones. Otherwise it will override settings of old types
     * and add the type to the end of the matrix if it does not exist yet.
     *
     * CAUTION: wipe = true will also delete all field data stored in the
     * repeater matrix fields!!
     *
     * Usage:
     *  $rm->setMatrixItems('your_matrix_field', [
     *    'foo' => [
     *      'label' => 'foo label',
     *      'fields' => ['field1', 'field2'],
     *    ],
     *    'bar' => [
     *      'label' => 'bar label',
     *      'fields' => ['field1', 'field3'],
     *    ],
     *  ], true);
     *
     * @param Field|string $field
     * @param array $items
     * @param bool $wipe
     * @return Field|null
     */
    public function setMatrixItems($field, $items, $wipe = false) {
      if(!$this->modules->isInstalled('FieldtypeRepeaterMatrix')) return;
      if(!$field = $this->getField($field, false)) return;

      // get all matrix types of that field
      $types = $field->type->getMatrixTypes();

      // if wipe is turned on we remove all existing items
      // this is great when you want to control the matrix solely by migrations
      if($wipe) {
        foreach($types as $type => $v) $this->removeMatrixItem($field, $type);
      }

      // loop all provided items
      foreach($items as $name => $data) {
        $type = $field->type->getMatrixTypeByName($name);
        if(!$type) $field = $this->addMatrixItem($field, $name, $data);
        else $this->setMatrixItemData($field, $name, $data);
      }

      return $field;
    }

  /* ##### templates ##### */

    /**
     * Allow given child for given parent
     */
    public function addAllowedChild($child, $parent) {
      $child = $this->getTemplate($child);
      $parent = $this->getTemplate($parent);
      $childs = $parent->childTemplates;
      $childs[] = $child;
      $this->setTemplateData($parent, ['childTemplates' => $childs]);
    }

    /**
     * Add role to template
     *
     * Usage:
     * $rm->addRoleToTemplate(["view", "edit"], "myrole", "mytemplate");
     *
     * @param mixed $permission
     * @param mixed $role
     * @param mixed $tpl
     * @return void
     */
    public function addRoleToTemplate($permission, $role, $tpl) {
      if(is_array($permission)) {
        foreach($permission as $p) $this->addRoleToTemplate($p, $role, $tpl);
        return;
      }

      $tpl = $this->wire->templates->get((string)$tpl);
      $role = $this->wire->roles->get((string)$role);

      if(!$tpl) return;
      if(!$role) return;

      $tpl->useRoles = 1;

      $prop = "roles";
      if($permission == "edit") $prop = "editRoles";
      if($permission == "add") $prop = "addRoles";
      if($permission == "create") $prop = "createRoles";

      $arr = $this->getRoleArray($tpl->$prop);
      $newArray = array_merge($arr, [(int)(string)$role]);
      $tpl->$prop = $newArray;
      $tpl->save();
    }

    /**
     * Add given access to given template for given role
     *
     * Example for single template:
     * $rm->addTemplateAccess("my-template", "my-role", "edit");
     *
     * Example for multiple templates:
     * $rm->addTemplateAccess(['home', 'basic-page'], "guest", "view");
     *
     * @return void
     */
    public function addTemplateAccess($templates, $role, $acc) {
      $role = $this->getRole($role);
      if(!$role->id) return;
      if(!is_array($templates)) $templates = [$templates];
      foreach($templates as $tpl) {
        $tpl = $this->getTemplate($tpl);
        $tpl->addRole($role, $acc);
        $tpl->save();
      }
    }

    /**
     * Create a new ProcessWire Template
     *
     * @param string $name
     * @param bool $addTitlefield
     * @return void
     */
    public function createTemplate($name, $addTitlefield = true) {
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

      // add title field to this template
      if($addTitlefield) $this->addFieldToTemplate('title', $t);

      return $t;
    }

    /**
     * Delete a ProcessWire Template
     *
     * @param mixed $tpl
     * @return void
     */
    public function deleteTemplate($tpl) {
      $template = $this->getTemplate($tpl, false);
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
      $fg = $this->fieldgroups->get((string)$tpl);
      if($fg) $this->fieldgroups->delete($fg);
    }

    /**
     * Delete templates
     *
     * Usage
     * $rm->deleteTemplates("tags=YourModule");
     *
     * @param string $selector
     */
    public function deleteTemplates($selector) {
      $templates = $this->wire->templates->find($selector);
      foreach($templates as $tpl) $this->deleteTemplate($tpl);
    }

    /**
     * Get template by name
     *
     * @param Template|string $name
     * @return Template|null
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
     * Get template of given repeater field
     * @param Field|string $field
     * @return Template
     */
    public function getRepeaterTemplate($field) {
      $field = $this->getField($field);
      return $this->templates->get($field->template_id);
    }

    /**
     * Remove all template context field settings
     * @return void
     */
    public function removeContext($tpl, $field) {
      $tpl = $this->getTemplate($tpl);
      $field = $this->getField($field);
      $tpl->fieldgroup->setFieldContextArray($field->id, []);
    }

    /**
     * Remove access to template for given role
     * @return void
     */
    public function removeTemplateAccess($tpl, $role) {
      $role = $this->getRole($role);
      if(!$role->id) return;
      $tpl = $this->getTemplate($tpl);
      $tpl->removeRole($role, "all");
      $tpl->save();
    }

    /**
     * This renames a template and corresponding fieldgroup
     * @return Template
     */
    public function renameTemplate($oldname, $newname) {
      $t = $this->templates->get((string)$oldname);

      // if the new template already exists we return it
      // this is important if you run one migration multiple times
      // $bar = $rm->renameTemplate('foo', 'bar');
      // $rm->setTemplateData($bar, [...]);
      $newTemplate = $this->templates->get((string)$newname);
      if($newTemplate) return $newTemplate;

      $t->name = $newname;
      $t->save();

      $fg = $t->fieldgroup;
      $fg->name = $newname;
      $fg->save();

      return $t;
    }

    /**
     * Set template icon
     */
    public function setIcon($template, $icon) {
      $template = $this->templates->get((string)$template);
      $template->setIcon($icon);
      $template->save();
      return $template;
    }

    /**
     * Set parent child family settings for two templates
     */
    public function setParentChild($parent, $child, $onlyOneParent = true) {
      $noParents = 0; // many parents are allowed
      if($onlyOneParent) $noParents = -1;
      $this->setTemplateData($child, [
        'noChildren' => 1, // may not have children
        'noParents' => '', // can be used for new pages
        'parentTemplates' => [(string)$parent],
      ]);
      $this->setTemplateData($parent, [
        'noChildren' => 0, // may have children
        'noParents' => $noParents, // only one page
        'childTemplates' => [(string)$child],
        'childNameFormat' => 'title',
      ]);
    }

    /**
     * Set settings of a template's access tab
     * Thx @apeisa https://bit.ly/2QU1b8e
     * Usage:
     * $rm->setTemplateAccess("my-tpl", "my-role", ["view", "edit"]);
     * @return void
     */
    public function setTemplateAccess($tpl, $role, $access) {
      $tpl = $this->getTemplate($tpl);
      $role = $this->getRole($role);
      $this->removeTemplateAccess($tpl, $role);
      $this->setTemplateData($tpl, ['useRoles'=>1]);
      foreach($access as $acc) $this->addTemplateAccess($tpl, $role, $acc);
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
     * You can also provide a page:
     * $rm->setTemplateData($myPage, ...);
     *
     * @param Template|Page|string $template
     * @param array $data
     * @return Template
     */
    public function setTemplateData($template, $data) {
      if($template instanceof Page) $template = $template->template;
      $template = $this->templates->get((string)$template);
      if(!$template) throw new WireException("template not found!");
      foreach($data as $k=>$v) {
        if(($k === 'fields' || $k === 'fields-') AND is_array($v)) {
          // if the key is 'fields' we set the fields
          // if the key is 'fields-' we set fields and remove others
          $removeOthers = $k==='fields-';
          $this->setTemplateFields($template, $v, $removeOthers);
          continue;
        }
        $template->{$k} = $v;
      }
      $template->save();
      return $template;
    }

    /**
     * Set fields of template via array
     * @return void
     */
    public function setTemplateFields($template, $fields, $removeOthers = false) {
      $template = $this->templates->get((string)$template);
      $last = null;
      $names = [];
      foreach($fields as $name=>$data) {
        if(is_int($name) AND is_int($data)) {
          $name = $this->getField((string)$data)->name;
          $data = [];
        }
        if(is_int($name)) {
          $name = $data;
          $data = [];
        }
        $names[] = $name;
        $this->addFieldToTemplate($name, $template, $last);
        $this->setFieldData($name, $data, $template);
        $last = $name;
      }

      if(!$removeOthers) return;
      foreach($template->fields as $field) {
        $name = (string)$field;
        if(!in_array($name, $names)) {
          // remove this field from the template
          // global fields like the title field are also removed
          $this->removeFieldFromTemplate($name, $template, true);
        }
      }
    }

    /**
     * Set data for multiple templates
     * @return void
     */
    public function setTemplatesData($templates, $data) {
      foreach($templates as $t) $this->setTemplateData($t, $data);
    }

  /* ##### pages ##### */

    /**
     * Create a new Page
     *
     * If the page exists it will return the existing page.
     * All available languages will be set active by default for this page.
     *
     * If you need to set a multilang title use
     * $rm->setFieldLanguageValue($page, "title", ['default'=>'foo', 'german'=>'bar']);
     *
     * @param array|string $title
     * @param string $name
     * @param Template|string $template
     * @param Page|string $parent
     * @param array $status
     * @param array $data
     * @return Page
     */
    public function createPage($title, $name = null, $template = '', $parent = '', $status = [], $data = []) {
      if(is_array($title)) return $this->createPageByArray($title);

      // create pagename from page title if it is not set
      if(!$name) $name = $this->sanitizer->pageName($title);

      // make sure parent is a page and not a selector
      if(!$parent) throw new WireException("Parent must be set! If you want to migrate the root page use ->setPageData() method.");
      $parent = $this->pages->get((string)$parent);

      // get page if it exists
      $selector = [
        'name' => $name,
        'template' => $template,
        'parent' => $parent,
      ];
      $page = $this->pages->get($selector);

      if($page->id) {
        // set status
        $page->status($status);
        $page->save();

        // set page data
        $this->setPageData($page, $data);

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

      // set page data
      $this->setPageData($p, $data);

      // enable all languages for this page
      $this->enableAllLanguagesForPage($p);

      return $p;
    }

    /**
     * Create page by array
     *
     * This is more future proof and has more options than the old version,
     * eg you can provide a callback:
     * $rm->createPage([
     *   'title' => 'foo',
     *   'onCreate' => function($page) { ... },
     * ]);
     *
     * @return Page
     */
    public function createPageByArray($array) {
      $data = $this->wire(new WireData()); /** @var WireData $data */
      $data->setArray($array);

      // check for necessary properties
      $parent = $this->pages->get((string)$data->parent);
      if(!$parent->id) throw new WireException("Invalid parent");
      $template = $this->templates->get((string)$data->template);
      if(!$template instanceof Template OR !$template->id) throw new WireException("Invalid template");

      // check name
      $name = $data->name;
      if(!$name) {
        if(!$data->title) throw new WireException("If no name is set you need to set a title!");
        $name = $this->sanitizer->pageName($data->title);
      }

      // set flag if page was created or not
      $created = !$this->pages->get("parent=$parent,name=$name")->id;

      // create page
      $page = $this->createPage(
        $data->title,
        $name,
        $template,
        $parent,
        $data->status,
        $data->pageData
      );

      // if page was created we fire the onCreate callback
      if($created AND is_callable($data->onCreate)) $data->onCreate->__invoke($page);

      return $page;
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

      // temporarily disable filesOnDemand feature
      // this prevents PW from downloading files that are deleted from a local dev
      // system but only exist on the live system
      $ondemand = $this->wire->config->filesOnDemand;
      $this->wire->config->filesOnDemand = false;

      // make sure we can delete the page and delete it
      // we also need to make sure that all descendants of this page are deletable
      // todo: make this recursive?
      $all = $this->wire(new PageArray());
      $all->add($page);
      $all->add($this->pages->find("has_parent=$page, include=all"));
      foreach($all as $p) {
        $p->addStatus(Page::statusSystemOverride);
        $p->status = 1;
        $p->save();
      }
      $this->pages->delete($page, true);

      $this->wire->config->filesOnDemand = $ondemand;
    }

    /**
     * Delete pages matching the given selector
     * @param mixed $selector
     * @return void
     */
    public function deletePages($selector) {
      $pages = $this->pages->find($selector);
      foreach($pages as $page) $this->deletePage($page);
    }

    /**
     * Enable all languages for given page
     *
     * @param Page|string $page
     * @return void
     */
    public function enableAllLanguagesForPage($page) {
      $page = $this->pages->get((string)$page);
      if($this->languages) {
        foreach($this->languages as $lang) $page->set("status$lang", 1);
      }
      $page->save();
    }

    /**
     * Move one page on top of another one
     * @return void
     */
    public function movePageAfter($page, $reference) {
      $page = $this->getPage($page);
      $ref = $this->getPage($reference);
      if(!$page->id) throw new WireException("Page does not exist");
      if(!$ref->id) throw new WireException("Reference does not exist");
      if($page->parent !== $ref->parent) throw new WireException("Both pages must have the same parent");
      $this->wire->pages->sort($page, $ref->sort+1);
    }

    /**
     * Move one page on top of another one
     * @return void
     */
    public function movePageBefore($page, $reference) {
      $page = $this->getPage($page);
      $ref = $this->getPage($reference);
      if(!$page->id) throw new WireException("Page does not exist");
      if(!$ref->id) throw new WireException("Reference does not exist");
      if($page->parent !== $ref->parent) throw new WireException("Both pages must have the same parent");
      $this->wire->pages->sort($page, $ref->sort);
    }

    /**
     * Set page data via array
     *
     * Usage (set title of root page):
     * $rm->setPageData("/", ['title' => 'foo']);
     *
     * @param Page $page
     * @param array $data
     * @return void
     */
    public function setPageData($page, $data) {
      if(!$data) return;
      $page = $this->wire->pages->get((string)$page);
      if(!$page->id) return;
      foreach($data as $k=>$v) $page->setAndSave($k, $v);
    }

  /* ##### roles & permissions ##### */

    /**
     * Add a permission to given role
     *
     * @param string|int $permission
     * @param string|int $role
     * @return boolean
     */
    public function addPermissionToRole($permission, $role) {
      $role = $this->getRole($role);
      if(!$role->id) return;
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
     * Create view file for template (if it does not exist already)
     * @return void
     */
    public function createViewFile($template, $content = "\n") {
      $template = $this->getTemplate($template);
      $file = $this->wire->config->paths->templates.$template->name.".php";
      if(is_file($content)) $content = file_get_contents($content);
      if(!is_file($file)) $this->wire->files->filePutContents($file, $content);
    }

    /**
     * Create webmaster user + role
     *
     * Usage:
     * $rm->createWebmaster("johndoe", [
     *   // null = random password that will be shown in notice
     *   'password' => 'foobar',
     *
     *   // template permissions
     *   'templates' => [
     *     'home' => ["view", "edit", "add"],
     *     'basic-page' => ["view", "edit", "create", "add"],
     *   ],
     * ]);
     */
    public function createWebmaster($name, $options = []) {
      $opt = $this->wire(new WireData()); /** @var WireData $opt */
      $opt->setArray([
        'password' => null,
        'templates' => [],
        'theme' => 'AdminThemeUikit',
      ]);
      $opt->setArray($options);

      // create role
      $role = $this->createRole("webmaster", [
        'page-edit',
        'page-delete',
        'page-move',
        'page-sort',
      ]);

      // create user
      $user = $this->wire->users->get("name=$name");
      if(!$user->id) {
        $rand = new WireRandom();
        $pass = $rand->alphanumeric(0, ['minLength'=>10, 'maxLength'=>15]);
        $user = $this->createUser($name, $pass, $opt->theme);
        $this->message("created user $name and set password $pass");
      }
      $this->addRoleToUser($role, $user);

      // set template access
      foreach($opt->templates as $tpl=>$permissions) {
        $this->setTemplateAccess($tpl, $role, $permissions);
      }
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
     * Delete the given role
     *
     * @param Role|string $role
     * @return void
     */
    public function deleteRole($role) {
      $role = $this->getRole($role);
      if(!$role->id) return;
      $this->roles->delete($role);
    }

    /**
     * Get role object
     * @return Role|null
     */
    public function getRole($role, $exception = false) {
      $_role = (string)$role;
      $role = $this->roles->get($_role);

      // return role when found or no exception
      if($role) return $role;
      if($exception === false) return;

      // role was not found, throw exception
      if(!$exception) $exception = "Role $_role not found";
      throw new WireException($exception);
    }

    /**
     * Remove a permission from given role
     *
     * @param string|int $permission
     * @param string|int $role
     * @return void
     */
    public function removePermissionFromRole($permission, $role) {
      $role = $this->getRole($role);
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
     * Set permissions for given role
     * This will remove all permissions that are not listed in the array!
     * If you just want to add permissions use addPermissionsToRole()
     * @return void
     */
    public function setRolePermissions($role, $permissions) {
      $role = $this->getRole($role);
      foreach($role->permissions as $p) $this->removePermissionFromRole($p, $role);
      foreach($permissions as $perm) $this->addPermissionToRole($perm, $role);
    }

  /* ##### users ##### */

    /**
     * Create a PW user with given password
     *
     * If the user already exists it will return this user.
     *
     * @param string $username
     * @param string $password
     * @param string $adminTheme
     * @return User
     */
    public function createUser($username, $password, $adminTheme = null) {
      $user = $this->users->get($username);
      if($user->id) return $user;

      $user = $this->wire->users->add($username);
      $user->pass = $password;
      if($adminTheme) $user->admin_theme = 'AdminThemeUikit';
      $user->save();
      if($adminTheme) $user->setAndSave('admin_theme', $adminTheme);
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
      $user->of(false);
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
     * By default this will remember old settings and only set the ones that are
     * specified as $data parameter. If you want to reset old parameters
     * set the $reset param to true.
     *
     * @param string|Module $module
     * @param array $data
     * @param bool $merge
     * @return Module|false
     */
    public function setModuleConfig($module, $data, $reset = false) {
      /** @var Module $module */
      $module = $this->modules->get((string)$module);
      if(!$module) {
        if($this->config->debug) throw new WireException("Module not found!");
        else return false;
      }

      // now we merge the new config data over the old config
      // if reset is TRUE we skip this step which means we may lose old config!
      if(!$reset) {
        $old = $this->wire->modules->getConfig($module);
        $data = array_merge($old, $data);
      }

      $this->modules->saveConfig($module, $data);
      return $module;
    }

    /**
     * Update module config data
     *
     * @param string|Module $module
     * @param array $data
     * @return Module
     */
    public function updateModuleConfig($module, $data) {
      $module = $this->modules->get((string)$module);
      if(!$module) throw new WireException("Module not found!");

      $newdata = $this->getModuleConfig($module);
      foreach($data as $k=>$v) $newdata[$k] = $v;
      $this->modules->saveConfig((string)$module, $newdata);
    }

    /**
     * Get module config data
     *
     * @param string $module
     * @return array
     */
    public function getModuleConfig($module, $property = null) {
      $module = $this->modules->get($module);
      $data = $this->modules->getModuleConfigData($module);
      if($property) {
        if(array_key_exists($property, $data)) return $data[$property];
        return false;
      }
      return $data;
    }

    /**
     * Install module (even if dependencies are not met!)
     *
     * If an URL is provided the module will be downloaded before installation.
     *
     * You can provide module settings as 3rd parameter. If no url is provided
     * you can submit config data as 2nd parameter (shorter syntax).
     *
     * @param string $name
     * @param string|array $url
     * @param array $config
     * @return Module
     */
    public function installModule($name, $url = null, $config = []) {
      if(is_array($url)) {
        $config = $url;
        $url = null;
      }

      // if the module is already installed we return it
      $module = $this->modules->get((string)$name);
      if(!$module) {
        // if an url was provided, download the module
        if($url) $this->downloadModule($url);

        // install the module
        // force option installs module even if dependencies are not met
        $module = $this->modules->install($name, ['force' => true]);
      }
      if(count($config)) $this->setModuleConfig($module, $config);
      return $module;
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
     * This deletes the module files and then removes the entry in the modules
     * table. Removing the module via uninstall() did cause an endless loop.
     * @param string $name
     * @return void
     */
    public function deleteModule($name, $path = null) {
      if($this->wire->modules->isInstalled($name)) {
        $module = $this->wire->modules->get($name);
        $this->uninstallModule($name);
        if(!$path) $path = $this->wire->config->paths($module);
      }
      else {
        if(!$path) $path = $this->wire->config->paths->siteModules.$name;
      }
      $this->wire->database->exec("DELETE FROM modules WHERE class = '$name'");
      if(is_dir($path)) $this->files->rmdir($path, true);
    }

    /* ##### languages ##### */

      /**
       * Add language and install company modules (fields/pagenames/tabs)
       * This will also set the url prefix for the installed language:
       * example.com/en/your-page-title
       */
      public function addLanguage($name, $title, $options = []) {
        $opt = $this->wire(new WireData()); /** @var WireData $opt */
        $opt->setArray([
          'fields' => true,
          'pageNames' => true,
          'tabs' => true,

          // url-prefix part for this language
          // (set on root pages' settings tab)
          // use true to set pageName from language name
          'pageName' => true,

          // convert title field to language field?
          'titleField' => true,
        ]);

        $lang = $this->addNewLanguage($name, $title);
        if($opt->fields) $this->installModule("LanguageSupportFields");
        if($opt->tabs) $this->installModule("LanguageTabs");
        if($opt->titleField) $this->setFieldData('title', ['type' => 'TextLanguage']);
        if($opt->pageNames) $this->installModule("LanguageSupportPageNames");

        // set the url part of the new language on root page
        // i've tried several options of setting the page name via api but had
        // no luck! the only way to make that work was using custom sql
        if($opt->pageName !== false) {
          if($opt->pageName === true) $opt->pageName = $lang->name;
          $this->database->query("UPDATE `pages` SET `name$lang` = '{$opt->pageName}' WHERE `id` = '1'");
        }
      }

      /**
       * Install the languagesupport module
       * @return void
       */
      public function addLanguageSupport() {
        if($this->modules->isInstalled("LanguageSupport")) return;
        $this->installModule("LanguageSupport");
      }

      /**
       * Adds new language if it doesn't exists yet. Also installs language support if missing
       *
       * @param string $languageName of new language
       * @param string $languageTitle (optional)
       *
       * @return Language $newLang that was created
       */
      public function addNewLanguage(string $languageName, string $languageTitle = null) {
        // Make sure Language Support is installed
        $this->addLanguageSupport();
        $newLang = $this->languages->get($languageName);
        if(!$newLang->id) {
          $newLang = $this->languages->add($languageName);
          $this->languages->reloadLanguages();
        }
        if($languageTitle) {
          $newLang->title = $languageTitle;
          $newLang->save('title');
        }
        return $newLang;
      }

      /**
       * Set core translations from zip file to a language. Removes old translations if any.
       * Installs LanguageSupport if it is not installed.
       *
       * <code>
       * $rm->setTranslationsToLanguage("https://github.com/jmartsch/pw-lang-de/archive/refs/heads/main.zip", "german");
       * </code>
       *
       * @param string $urlToTranslationsZip public url to language zip file OR key like DE or FI
       * @param string $languageName that is updated, null for default language
       *
       * @return Language $language
       */
      public function setTranslationsToLanguage(string $urlToTranslationsZip, string $languageName = null) {
        $zip = $this->getLanguageZipUrl($urlToTranslationsZip);

        // Make sure Language Support is installed
        $this->addLanguageSupport();
        if($languageName) $language = $this->languages->get($languageName);
        else $language = $this->languages->getDefault();
        if (!$language->id) throw new WireException("Language $languageName does not exists.");
        $http = new WireHttp();

        // Download zip to cache folder for unzipping
        $zipTemp = $this->config->paths->cache . $languageName . "_temp.zip";
        $http->download($zip, $zipTemp);

        // Unzip files and add .json files to language
        $items = $this->files->unzip($zipTemp, $this->config->paths->cache);
        if(count($items)) {
          // Delete all old files first
          $language->language_files->deleteAll();
          $language->save();
          foreach($items as $item) {
            if(strpos($item, ".json") === false) continue;
            $language->language_files->add($this->config->paths->cache . $item);
          }
        }
        $language->save();

        return $language;
      }

      /**
       * Deletes a language
       * @param string $languageName
       * @return void
       */
      public function deleteLanguage(string $languageName) {
        if($languageName == "default") {
          // Not sure if this should be allowed?
          throw new WireException("You cannot delete default language.");
        }
        $language = $this->languages->get($languageName);
        if (!$language->name) return;
        $this->languages->delete($language);
      }

      /**
       * Get language zip url
       * @return string
       */
      public function getLanguageZipUrl($url) {
        if(strtoupper($url) == 'DE') return "https://github.com/jmartsch/pw-lang-de/archive/refs/heads/main.zip";
        if(strtoupper($url) == 'FI') return "https://github.com/apeisa/Finnish-ProcessWire/archive/refs/heads/master.zip";
        return $url;
      }

    /* ##### helpers ##### */

    /**
     * Fire the callback if the version upgrading to ($to) is higher or equal
     * to provided version ($version)
     *
     * 0.0.1 --> 0.0.2, VERSION = 0.0.2 --> fires
     * 0.0.1 --> 0.0.5, VERSION = 0.0.2 --> fires
     * 0.0.4 --> 0.0.5, VERSION = 0.0.2 --> fires
     * 0.0.4 --> 0.0.5, VERSION = 1.0.2 --> does not fire
     *
     * @return void
     */
    public function fireSince($version, $to, $func) {
      if($this->isLower($to, $version)) return;
      $func->__invoke($this);
    }

    /**
     * Sanitize repeater matrix array
     * @param array $data
     * @return array
     */
    private function getMatrixDataArray($data) {
      $newdata = [];
      foreach($data as $key=>$val) {
        // make sure fields is an array of ids
        if($key === 'fields') {
          $ids = [];
          foreach($val as $_field) {
            $ids[] = $this->fields->get((string)$_field)->id;
          }
          $val = $ids;
        }
        $newdata[$key] = $val;
      }
      return $newdata;
    }

    /**
     * Get role array containing only role ids
     * @param mixed $data
     * @return array
     */
    private function getRoleArray($data) {
      $arr = [];
      foreach($data as $item) {
        if(is_int($item)) $arr[] = $item;
        if(is_object($item)) $item = (string)$item;
        if(is_string($item)) $arr[] = (int)$item;
      }
      return $arr;
    }

    /**
     * Is v1 lower than v2?
     * @return bool
     */
    public function isLower($v1, $v2) {
      return version_compare($v1, $v2) < 0;
    }

    /**
     * Is v1 higher than v2?
     * @return bool
     */
    public function isHigher($v1, $v2) {
      return version_compare($v1, $v2) > 0;
    }

    /**
     * Is v1 the same as v2?
     * @return bool
     */
    public function isSame($v1, $v2) {
      return version_compare($v1, $v2) === 0;
    }

    /**
     * Reset repeaterFields property of matrix field
     * @param Field $field
     * @return Field
     */
    private function resetMatrixRepeaterFields(Field $field) {
      $ids = [$this->fields->get('repeater_matrix_type')->id];
      //enumerate only existing fields
      $keys = array_keys($field->getArray());
      $items = preg_grep("/matrix(\d+)_fields/", $keys);
      foreach($items as $item) {
        $ids = array_merge($ids, $field->get($item) ?: []);
      }
      $field->set('repeaterFields', $ids);

      // remove unneeded fields
      $tpl = $this->getRepeaterTemplate($field);
      foreach($tpl->fields as $f) {
        if($f->name === 'repeater_matrix_type') continue;
        if(in_array($f->id, $ids)) continue;
        $this->removeFieldFromTemplate($f, $tpl);
      }

      return $field;
    }

    /**
     * Set page name replacements as array or by filename
     *
     * This will update the 'replacements' setting of InputfieldPageName module
     *
     * Usage: $rm->setPagenameReplacements("de");
     * Usage: $rm->setPagenameReplacements(['ä'=>'ae']);
     *
     * @param mixed $data
     * @return void
     */
    public function setPagenameReplacements($data) {
      if(is_string($data)) {
        $file = __DIR__."/replacements/$data.txt";
        if(!is_file($file)) {
          return $this->log("File $file not found");
        }
        $replacements = explode("\n", $this->wire->files->render($file));
        $arr = [];
        foreach($replacements as $row) {
          $items = explode("=", $row);
          $arr[$items[0]] = $items[1];
        }
      }
      elseif(is_array($data)) $arr = $data;
      if(!is_array($arr)) return;
      $this->setModuleConfig("InputfieldPageName", ['replacements' => $arr]);
    }

  /* ##### config file support ##### */

  /**
   * Get config data object
   * @return WireData
   */
  public function getConfig($config, $vars = []) {
    $config = $this->getConfigArray($config, $vars);
    $data = $this->wire(new WireData()); /** @var WireData $data */
    $config = $data->setArray($config);
    return $config;
  }

  /**
   * Get config array
   * @return array
   */
  public function getConfigArray($config, $vars = []) {
    if(is_string($config)) {
      if(is_file($config)) {
        $config = $this->files->render($config, $vars);
      }
    }
    if(!is_array($config)) throw new WireException("Invalid config data");

    // this ensures that $config->fields is an empty array rather than
    // a processwire fields object (proxied from the wire object)
    if(!array_key_exists("fields", $config)) $config['fields'] = [];
    if(!array_key_exists("templates", $config)) $config['templates'] = [];
    if(!array_key_exists("pages", $config)) $config['pages'] = [];
    if(!array_key_exists("roles", $config)) $config['roles'] = [];

    return $config;
  }

  /**
   * Get pathinfo of file/directory as WireData
   * @return WireData
   */
  public function info($str) {
    if($str instanceof Pagefile) $str = $str->filename;
    $config = $this->wire('config');
    $info = $this->wire(new WireData()); /** @var WireData $info */
    $info->setArray(pathinfo($str));
    $info->dirname = Paths::normalizeSeparators($info->dirname)."/";
    $info->path = "{$info->dirname}{$info->basename}";
    $info->url = str_replace($config->paths->root, $config->urls->root, $info->path);
    $info->is_dir = is_dir($info->path);
    $info->is_file = is_file($info->path);
    $info->isDir = !$info->extension;
    $info->isFile = !!$info->extension;
    $info->exists = ($info->is_dir || $info->is_file);
    if($info->is_file) $info->m = "?m=".filemtime($info->path);
    return $info;
  }

  /**
   * DEPRECATED - use initClasses() instead
   */
  public function loadClasses($dir, $namespace = null) {
    foreach($this->files->find($dir, ['extensions' => ['php']]) as $file) {
      $info = $this->info($file);
      require_once($info->path);
      $class = $info->filename;
      if($namespace) $class = "\\$namespace\\$class";
      $tmp = new $class();
      if(method_exists($tmp, "init")) $tmp->init();
    }
  }

  /**
   * Migrate PW setup based on config array
   *
   * The method returns the used config so that you can do actions after migration
   * eg adding custom tags to all fields or templates that where migrated
   *
   * @return WireData
   */
  public function migrate($config, $vars = []) {
    $config = $this->getConfig($config, $vars);

    // trigger before callback
    if(is_callable($config->before)) {
      $config->before->__invoke($this);
    }

    // create fields+templates
    foreach($config->fields as $name=>$data) {
      // if no type is set this means that only field data was set
      // for example to update only label or icon of an existing field
      if(array_key_exists('type', $data)) $this->createField($name, $data['type']);
    }
    foreach($config->templates as $name=>$data) $this->createTemplate($name, false);
    foreach($config->roles as $name=>$data) $this->createRole($name);

    // set field+template data after they have been created
    foreach($config->fields as $name=>$data) $this->setFieldData($name, $data);
    foreach($config->templates as $name=>$data) $this->setTemplateData($name, $data);
    foreach($config->roles as $role=>$data) {
      // set permissions for this role
      if(array_key_exists("permissions", $data)) $this->setRolePermissions($role, $data['permissions']);
      if(array_key_exists("access", $data)) {
        foreach($data['access'] as $tpl=>$access) $this->setTemplateAccess($tpl, $role, $access);
      }
    }

    // setup pages
    foreach($config->pages as $name=>$data) {
      if(isset($data['name'])) {
        $name = $data['name'];
      } elseif(is_int($name)) {
        // no name provided
        $name = uniqid();
      }

      $d = $this->wire(new WireData()); /** @var WireData $d */
      $d->setArray($data);
      $this->createPage(
        $d->title ?: $name,
        $name,
        $d->template,
        $d->parent,
        $d->status,
        $d->data);
    }

    // trigger after callback
    if(is_callable($config->after)) {
      $config->after->__invoke($this);
    }

    return $config;
  }

  public function __debugInfo() {
    return [];
  }
}
