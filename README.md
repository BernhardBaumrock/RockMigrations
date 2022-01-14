# RockMigrations Module

Support Board Link: https://processwire.com/talk/topic/21212-rockmigrations-easy-migrations-from-devstaging-to-live-server/

## Why Migrations?

Benjamin Milde wrote a great blog post about that: https://processwire.com/blog/posts/introduction-migrations-module/

## Why another migrations module?

I just didn't like the way the other module works. You need to create a file for every migration that you want to apply (like creating a new field, template, etc). With RockMigrations the goal is to make most of the necessary changes a 1-liner that you can add to any file you want (meaning that you can use RockMigrations in any of your modules).

## WARNING

**All api functions are destructive and can completely ruin your pw installation! This is intended behaviour and therefore you have to be careful and know what you are doing!**

For example deleting a template will also delete all pages having this template. Usually, when using the regular PW API, you'd need to firt check if there are any pages using this template, then delete those pages and finally also delete the corresponding fieldgroup. If the template has the system flag set, you'd also need to remove that flag before deleting the template. That's a lot of things to think of, if all you want to do is to delete a template (and of course all pages having this template). Using RockMigrations it's only one line of code: `$rm->deleteTemplate('yourtemplatename');`

If you are using RockMigrations I'm happy to hear about that: https://processwire.com/talk/topic/21212-rockmigrations-easy-migrations-from-devstaging-to-live-server/

---

## Quickstart

Just copy this to your `/site/ready.php` and see the magic of RockMigrations:

```php
/** @var RockMigrations $rm */
$rm = $this->wire('modules')->get('RockMigrations');
$rm->migrate([
  // fields to create
  'fields' => [
    'ready_text' => [
      'type' => 'textarea',
      'tags' => 'ReadyDemo',
    ],
    'context_example' => [
      'type' => 'text',
      'label' => 'Global field label',
    ],
  ],
  // templates to create
  'templates' => [
    'ready_blog' => [
      'tags' => 'ReadyDemo',
      'fields' => [
        'title',
        'context_example' => [
          'label' => 'Field label on ready_blog template',
        ],
      ],
    ],
    'ready_blogitem' => [
      'tags' => 'RaedyDemo',
      'fields' => [
        'title',
        'ready_text',
      ],
    ],
  ],
]);
$parent = $rm->createPage("Ready blog demo", "ready-blog", "ready_blog", 1);
$rm->createPage("Blog entry ".date("Y-m-d H:i:s"), null, "ready_blogitem", $parent);
```

You should get IntelliSense by your IDE to make working with RockMigrations really easy:

![img](https://i.imgur.com/KOx7sWi.png)

This will fire the migrations on every page load which is of course not the way to go, but I hope this makes it as easy as possible to get started with migrations and let's you get the idea of the power of using migrations for all kinds of site development!

![img](https://i.imgur.com/4XnJVKr.png)

Now let us remove all fields and templates that we just created. Remove the migrations that CREATE everything and replace the code by this:

```php
/** @var RockMigrations $rm */
$rm = $this->wire('modules')->get('RockMigrations');
$rm->deleteField("ready_text");
$rm->deleteTemplate("ready_blog");
$rm->deleteTemplate("ready_blogitem");
```

Congratulations!! You just finished your very first migration to create fields, templates and pages and to remove them. 🥳😎

The easiest way of preventing the migrations to run on every page load is to wrap them inside a `fireOnRefresh()` method:

```php
/** @var RockMigrations $rm */
$rm = $this->wire('modules')->get('RockMigrations');
$rm->fireOnRefresh(function() use($rm) {
  // your migrations here
  $rm->migrate(...);
  $rm->createField(...);
});
```

For more complex scenarios you can place your migrations in ProcessWire modules. See the [Blog-Example in the examples folder](https://github.com/BernhardBaumrock/RockMigrations/blob/master/examples/MigrationsExample.module.php). Just copy it to your `/site/modules` folder, install it and inspect its code and all it's comments inside the file!

PS: Did you realize the typo in the migration?? If not, that's the best proof why it makes so much sense to use class constants for all your migrations ;)

![img](https://i.imgur.com/QVFsJUI.png)

PPS: Some examples of outdated techniques I'm not using any more are in the old readme file: https://github.com/BernhardBaumrock/RockMigrations/blob/fdd763485ca572d45143067d4966d9f49c572a95/README.md

### Note about fireOnRefresh()

Prior to v0.0.82 the `fireOnRefresh` did NOT fire in bootstrapped environments. This is because ProcessWire by default runs as guest user when bootstrapped and the `fireOnRefresh` method did not attach any hooks if the user was no superuser. That was a problem for me because a `$modules->refresh()` did not trigger any migrations when invoked from the commandline. To solve that issue v0.0.82 introduces another concept:

`fireOnRefresh` fires always after Modules::refresh even for guest users. If you need to prevent RockMigrations from firing actions that where attached via `fireOnRefresh` you have two options:

* Setting a constant: `define("DontFireOnRefresh", true)`
* Setting a config property: `$config->DontFireOnRefresh = true;`

Note that the setting must be available to RockMigrations BEFORE the action gets attached via `fireOnRefresh`. This is best explained by examples:

```php
define('DontFireOnRefresh', true);
include("index.php"); // fireOnRefresh is triggered here
$modules->refresh(); // this will not trigger any migrations
```

We define the constant before ProcessWire gets loaded and therefore any module attaching migrations via `fireOnRefresh` will actually NOT attach any migrations because the flag to prevent that is present when the triggered from `init()` or `ready()`.

```php
include("index.php"); // fireOnRefresh is triggered here
$config->DontFireOnRefresh = true;
$modules->refresh(); // this WILL trigger all migrations!
```

In this example the migrations will be triggered because the migrations have been attached BEFORE the config setting was set. In other words the flag was set too late for RockMigrations to realize it.

## Conventions

Please make sure to use field and template names without dashes:

```php
// this is good
$rm->createField('my_field', ...);

// this is BAD!
$rm->createField('my-field', ...);
```

## Repeaters

```php
$rm->migrate([
  'fields' => [
    'foo_field' => [...],
    'bar_field' => [...],
    'my_repeater_field' => [
      'type' => 'repeater',
      'repeaterFields' => [
        // you can set field data in repeater context like this:
        'title' => ['required'=>0],
        'foo_field' => ['columnWidth'=>50],
        'bar_field' => ['columnWidth'=>50],
      ],
    ],
  ],
]);
```

## Language support

```php
// add english as second language
$rm->addLanguage("en", "Englisch");
// set german translations to the default language
// provide language name as second parameter
// will automatically download translations from GIT if available
$rm->setTranslationsToLanguage("de");
```

## Access Control

ProcessWire has a powerful access control system. When using RM to create new templates and pages it is quite likely that you also want to create roles and define access for those roles on the new templates. The basics of access control can easily be done via RM - for more advanced topics you might need to implement custom solutions. PRs welcome :)

Let's say we created an Events calender and we wanted to store all events (tpl `event`) under one page in the page tree. This page (tpl `events`) would only allow pages of type `event`. To manage those events we create a new role on the system called `events-manager`.

```php
$rm->migrate([
  'templates' => [
    'events' => [...],
    'event' =>  [...],
  ],
  'roles' => [
    'events-manager' => [
      'permissions' => ['page-view', 'page-edit'],
      'access' => [
        // make the parent page non-editable, but allow adding of children
        'events' => ['view', 'add'],
        // all children are editable and we allow to create new event pages
        'event' => ['view', 'edit', 'create'],
      ],
    ],
  ],
]);
```

On more complex setups you can use the API functions that are used under the hood directly. For example sometimes I'm encapsulating parts of the migrations into separate methods to split complexity and keep things that belong together together:

```php
/**
 * Migrate all data-pages
 * @return void
 */
public function migrateDatapages() {
  $rm = $this->rm();

  // migrate products (having two custom page classes)
  // these have their own migrations inside their classes' migrate() method
  // where we create fields and the template that the class uses
  $product = new Product();
  $product->migrate();
  $products = new Products();
  $products->migrate();
  $rm->setParentChild(Products::tpl, Product::tpl);
  $rm->setTemplateAccess(Products::tpl, self::role, ["view", "edit", "add"]);
  $rm->setTemplateAccess(Product::tpl, self::role, ["view", "edit", "create"]);

  // same goes for all other data pages
  // ...
}
```

## Field migrations

### Examples

CKEditor field

```php
$rm->migrate([
  'fields' => [
    'yourckefield' => [
      'type' => 'textarea',
      'label' => __('foo bar'),
      'tags' => 'YourModule',
      'inputfieldClass' => 'InputfieldCKEditor',
      'contentType' => FieldtypeTextarea::contentTypeHTML,
    ],
  ],
]);
```

Image field

```php
$rm->migrate([
  'fields' => [
    'yourimagefield' => [
      'type' => 'image',
      'tags' => 'YourTags',
      'maxFiles' => 0,
      'descriptionRows' => 1,
      'extensions' => "jpg jpeg gif png svg",
      'okExtensions' => ['svg'],
      'icon' => 'picture-o',
      'outputFormat' => FieldtypeFile::outputFormatSingle,
      'maxSize' => 3, // max 3 megapixels
    ],
  ],
]);
```

Files field

```php
$rm->migrate([
  'fields' => [
    'yourfilefield' => [
      'type' => 'file',
      'tags' => 'YourTags',
      'maxFiles' => 1,
      'descriptionRows' => 0,
      'extensions' => "pdf",
      'icon' => 'file-o',
      'outputFormat' => FieldtypeFile::outputFormatSingle,
    ],
  ],
]);
```

Options field

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'options',
      'tags' => 'YourTags',
      'label' => 'Options example',
      'options' => [
        1 => 'ONE|This is option one',
        2 => 'TWO',
        3 => 'THREE',
      ],
    ],
  ],
]);
```

Page Reference field

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'page',
      'label' => __('Select a page'),
      'tags' => 'YourModule',
      'derefAsPage' => FieldtypePage::derefAsPageArray,
      'inputfield' => 'InputfieldSelect',
      'findPagesSelector' => 'foo=bar',
      'labelFieldName' => 'title',
    ],
  ],
]);
```

Date field

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'datetime',
      'label' => __('Enter date'),
      'tags' => 'YourModule',
      'dateInputFormat' => 'j.n.y',
      'datepicker' => InputfieldDatetime::datepickerFocus,
      'defaultToday' => 1,
    ],
  ],
]);
```

## Common ProcessWire Helpers

### Load files on demand from remote to local development

This idea comes from a blog-post of Ryan when he introduced the multiple hooks feature in 3.0.137: https://processwire.com/blog/posts/pw-3.0.137/#on-demand-mirroring-of-remote-web-server-files-to-your-dev-environment

This feature is now part of RockMigrations and setup is as easy as setting a config variable:

```php
$config->filesOnDemand = "https://www.example.com";
```

### Set pagename replacements

```php
$rm->setPagenameReplacements("de");
```
