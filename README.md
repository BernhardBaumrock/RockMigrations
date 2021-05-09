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
  'fields' => [
    'ready_text' => [
      'type' => 'textarea',
      'tags' => 'ReadyDemo',
    ],
  ],
  'templates' => [
    'ready_blog' => [
      'tags' => 'ReadyDemo',
      'fields' => ['title'],
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

**Now that you took your first steps with RockMigrations you are ready to try the Blog-Example in the examples folder. Just copy it to your `/site/modules` folder, install it and inspect its code and all it's comments inside the file!**

PS: Did you realize the typo in the migration?? If not, that's the best proof why it makes so much sense to use class constants for all your migrations ;)

![img](https://i.imgur.com/QVFsJUI.png)

PPS: Some examples of outdated techniques I'm not using any more are in the old readme file: https://github.com/BernhardBaumrock/RockMigrations/blob/fdd763485ca572d45143067d4966d9f49c572a95/README.md

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
      'extensions' => "pdf jpg png zip docx svg",
      'okExtensions' => ['svg'],
      'icon' => 'picture-o',
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
        1 => 'ONE',
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

## Common ProcessWire Helpers

### Load files on demand from remote to local development

This idea comes from a blog-post of Ryan when he introduced the multiple hooks feature in 3.0.137: https://processwire.com/blog/posts/pw-3.0.137/#on-demand-mirroring-of-remote-web-server-files-to-your-dev-environment

This feature is now part of RockMigrations and setup is as easy as setting a config variable:

```php
$config->filesOnDemand = "https://www.example.com";
```

### Set german pagename replacements

```php
$rm->setModuleConfig("InputfieldPageName", [
  'replacements' => [
    "æ"=>"ae",
    "å"=>"a",
    "ä"=>"ae",
    "ã"=>"a",
    "ß"=>"ss",
    "ö"=>"oe",
    "ü"=>"ue",
    "đ"=>"dj",
    "ж"=>"zh",
    "х"=>"kh",
    "ц"=>"tc",
    "ч"=>"ch",
    "ш"=>"sh",
    "щ"=>"shch",
    "ю"=>"iu",
    "я"=>"ia",
    ":"=>"-",
    ","=>"-",
    "à"=>"a",
    "á"=>"a",
    "â"=>"a",
    "è"=>"e",
    "é"=>"e",
    "ë"=>"e",
    "ê"=>"e",
    "ě"=>"e",
    "ì"=>"i",
    "í"=>"i",
    "ï"=>"i",
    "î"=>"i",
    "ı"=>"i",
    "İ"=>"i",
    "ğ"=>"g",
    "õ"=>"o",
    "ò"=>"o",
    "ó"=>"o",
    "ô"=>"o",
    "ø"=>"o",
    "ù"=>"u",
    "ú"=>"u",
    "û"=>"u",
    "ů"=>"u",
    "ñ"=>"n",
    "ç"=>"c",
    "č"=>"c",
    "ć"=>"c",
    "Ç"=>"c",
    "ď"=>"d",
    "ĺ"=>"l",
    "ľ"=>"l",
    "ń"=>"n",
    "ň"=>"n",
    "ŕ"=>"r",
    "ř"=>"r",
    "š"=>"s",
    "ş"=>"s",
    "Ş"=>"s",
    "ť"=>"t",
    "ý"=>"y",
    "ž"=>"z",
    "а"=>"a",
    "б"=>"b",
    "в"=>"v",
    "г"=>"g",
    "д"=>"d",
    "е"=>"e",
    "ё"=>"e",
    "з"=>"z",
    "и"=>"i",
    "й"=>"i",
    "к"=>"k",
    "л"=>"l",
    "м"=>"m",
    "н"=>"n",
    "о"=>"o",
    "п"=>"p",
    "р"=>"r",
    "с"=>"s",
    "т"=>"t",
    "у"=>"u",
    "ф"=>"f",
    "ы"=>"y",
    "э"=>"e",
    "ę"=>"e",
    "ą"=>"a",
    "ś"=>"s",
    "ł"=>"l",
    "ż"=>"z",
    "ź"=>"z",
  ],
]);
```
