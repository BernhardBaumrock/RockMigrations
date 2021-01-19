<?php namespace ProcessWire;
/**
 * RockMigrations module example
 *
 * Move this file to /site/modules and install it to see how it works!
 *
 * @author Bernhard Baumrock, 30.12.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class MigrationsExample extends WireData implements Module, ConfigurableModule {

  // prefix to use for all fields and templates
  const prefix = "rmex_";
  const tags = "RockMigrationsExample";

  // fields
  const field_foo = self::prefix."foo";
  const field_bar = self::prefix."bar";

  // templates
  const tpl_foos = self::prefix."foos";
  const tpl_foo = self::prefix."foo";
  const tpl_bars = self::prefix."bars";
  const tpl_bar = self::prefix."bar";

  public static function getModuleInfo() {
    return [
      'title' => 'MigrationsExample',
      'version' => '0.0.1',
      'summary' => 'Module that shows how I use RockMigrations for module development',
      'autoload' => true,
      'singular' => true,
      'icon' => 'smile-o',
      'requires' => ['RockMigrations'],
      'installs' => [],
    ];
  }

  public function init() {
    // trigger migration of every modules refresh of superusers
    $this->rm()->fireOnRefresh($this, "migrate");
  }

  /**
   * Remove unused fields or templates
   * @return void
   */
  public function cleanup() {
    $this->rm()->deleteField("field_not_used_any_more");
    $this->rm()->deleteTemplate("tpl_not_used_any_more");
  }

  /**
   * Migrations for this module
   * See tracy's RequestInfo Panel to find all the necessary property names
   * @return void
   */
  public function migrate() {
    $this->message("Starting migrations!");
    $this->cleanup();
    $this->migrateFoos();
    $this->migrateBars();
  }

  /**
   * Migrate bars/bar
   * @return void
   */
  public function migrateBars() {
    $this->rm()->migrate([
      'fields' => [
        self::field_bar => [
          'type' => 'text',
          'label' => 'My bar text',
          'tags' => self::tags,
        ],
      ],
      'templates' => [
        self::tpl_bars => [
          'noParents' => -1, // only one
          'fields' => ['title'],
          'tags' => self::tags,
        ],
        self::tpl_bar => [
          'tags' => self::tags,
          'fields' => [
            'title',
            self::field_bar,
          ],
        ],
      ],
    ]);
    $this->rm()->setParentChild(self::tpl_bars, self::tpl_bar);
    $this->rm()->createPage("My Bars", "bars", self::tpl_bars, 1);
  }

  /**
   * Migrate foos/foo
   * @return void
   */
  public function migrateFoos() {
    $this->rm()->migrate([
      'fields' => [
        self::field_foo => [
          'type' => 'text',
          'label' => 'My foo text',
          'tags' => self::tags,
        ],
      ],
      'templates' => [
        self::tpl_foos => [
          'noParents' => -1, // only one
          'fields' => ['title'],
          'tags' => self::tags,
        ],
        self::tpl_foo => [
          'tags' => self::tags,
          'fields' => [
            'title',
            self::field_foo,
          ],
          // you could set a custom page class easily:
          // 'pageClass' => "FooPage",
        ],
      ],
    ]);
    $this->rm()->setParentChild(self::tpl_foos, self::tpl_foo);
    $this->rm()->createPage("My Foos", "foos", self::tpl_foos, 1);
  }

  /**
   * Get RockMigrations instance
   * @return RockMigrations
   */
  public function rm() {
    return $this->wire->modules->get('RockMigrations');
  }

  /** Module methods */

  public function ___install() {
    $this->migrate();
  }

  public function ___uninstall() {
    // remove foos
    $this->rm()->deleteField(self::field_foo);
    $this->rm()->deleteTemplate(self::tpl_foos);
    $this->rm()->deleteTemplate(self::tpl_foo);
    // remove bars
    $this->rm()->deleteField(self::field_bar);
    $this->rm()->deleteTemplate(self::tpl_bars);
    $this->rm()->deleteTemplate(self::tpl_bar);
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Help',
      'icon' => 'life-ring',
      'value' => "
        <p>You will see several things when the module is installed:</p>
        <ul>
          <li>There will be pages /foos and /bars in the page</li>
          <li>There will be several fields and templates with prefix >>".self::prefix." <<</li>
          <li>Create a new page under /foos and you will automatically create a foo page (correct family settings)</li>
          <li>Create a new page under /bars and you will automatically create a bar page (correct family settings)</li>
          <li>All fields, templates and pages will be removed on uninstall of the module - see MigrationsExample.module.php::uninstall() method how easy that is accomplished using RockMigrations</li>
        </ul>
      ",
    ]);
    return $inputfields;
  }
}
