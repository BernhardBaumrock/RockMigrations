<?php namespace ProcessWire;
/**
 * RockMigrations module example
 *
 * Move this file to /site/modules and install it to see how it works!
 * After installation you will see new fields, templates and pages on your system:
 * https://i.imgur.com/qkF4S8Y.png
 * https://i.imgur.com/Y6r4pqu.png
 * https://i.imgur.com/2ZTvB6j.png
 *
 * Once you installed the module create a new field for the blogitem in the
 * code file, save it and hit modules refresh and see the magic :)
 *
 * @author Bernhard Baumrock, 30.12.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class MigrationsExample extends WireData implements Module {

  // A note on class constants:
  // I'm using this technique for quite some time now and I think it is great!
  // Class constants make it impossible to introduce typos. Your IDE helps you
  // with code completion and you are forced to think WHERE a field or a template
  // lives and that makes your code better. You can easily prefix all your field
  // names by using eg $field1 = self::prefix."field1", $field2 = self::prefix."field2"
  // which would give you, for example when used for a slider component:
  // slider_field1, slider_field2

  // prefix to use for all fields and templates
  const prefix = "rmex_";
  const tags = "RockMigrationsExample";

  // fields
  const field_text = self::prefix."text";

  // templates
  const tpl_blog = self::prefix."blog";
  const tpl_blogitem = self::prefix."blogitem";

  public static function getModuleInfo() {
    return [
      'title' => 'RockMigrations Example Blog',
      'version' => '0.0.2',
      'summary' => 'This will install a demo Blog on your site as example how to use RockMigrations for professional site development. Look into the code of the module - it is well documented there!',
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
   * Migrations for this module
   * See tracy's RequestInfo Panel to find all the necessary property names
   * @return void
   */
  public function migrate() {
    $this->message("Starting migration!");
    $rm = $this->rm();

    // migrate fields and templates
    // all fields and templates get the same tag so that we can remove it
    // easily when we uninstall the module ($rm->deleteFields("tags=...")).
    // see __uninstall() method why this is important.
    $rm->migrate([
      // create the necessary fields
      // you can add any fields to this array that you want
      // in this case we make it simple and only add one field for text
      'fields' => [
        self::field_text => [
          'type' => 'textarea',
          'label' => __('Blog Item Text'),
          'tags' => self::tags,
          'inputfieldClass' => 'InputfieldCKEditor',
          'contentType' => FieldtypeTextarea::contentTypeHTML,
          'icon' => 'align-left',
        ],
      ],

      // add the templates that we need for our blog
      // we need a parent template and a template for all blog items
      'templates' => [
        self::tpl_blog => [
          'tags' => self::tags,
          'fields' => [
            'title',
          ],
        ],
        self::tpl_blogitem => [
          'tags' => self::tags,
          'fields' => [
            'title',
            self::field_text,
          ],
        ],
      ],
    ]);

    // now that the templates exist we set the parent child relationship
    // you could set that manually via template properties, but it's easier like this:
    $rm->setParentChild(self::tpl_blog, self::tpl_blogitem);

    // now we create the parent page:
    // hover over the "createPage" method and you should see docs for "createPage"
    // https://i.imgur.com/JkemCJG.png
    // First: Page title
    // Second: Page name --> null takes the title and sanitizes it
    // Third: The template to use for the page
    // Fourth: The parent of the page.
    $parent = $rm->createPage("Migrations Example Blog", "migrations-blog", self::tpl_blog, 1);

    // Now we create a sample blog page. We'd not to that on a real setup.
    // It's just to show you what is possible:
    if(!$parent->numChildren()) {
      $post = $rm->createPage("Example Blog Post 1", null, self::tpl_blogitem, $parent);
      $post->setAndSave(self::field_text, "<p>This is example blog post 1</p>");

      $post = $rm->createPage("Example Blog Post 2", null, self::tpl_blogitem, $parent);
      $post->setAndSave(self::field_text, "<p>This is example blog post 2</p>");
    }
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
    // trigger migrate when module is installed
    $this->migrate();
  }

  public function ___uninstall() {
    // we remove all fields and templates that are tagged with our tag
    $this->rm()->deleteFields("tags=".self::tags);
    $this->rm()->deleteTemplates("tags=".self::tags);
  }

}
