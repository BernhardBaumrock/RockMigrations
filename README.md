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

## Usage

![img](https://i.imgur.com/okYjsWz.gif)

**Please copy the file `MigrationsExample.module.php` to `site/modules` and install it to see an example of how I'm mostly using RockMigrations at the moment: https://github.com/BernhardBaumrock/RockMigrations/blob/master/examples/MigrationsExample.module.php**

Some examples of outdated techniques I'm not using any more are in the old readme file: https://github.com/BernhardBaumrock/RockMigrations/blob/fdd763485ca572d45143067d4966d9f49c572a95/README.md

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
        'events' => ['view', 'edit', 'add'],
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

## Common ProcessWire Helpers

### Load files on demand from remote to local development

This idea comes from a blog-post of Ryan when he introduced the multiple hooks feature in 3.0.137: https://processwire.com/blog/posts/pw-3.0.137/#on-demand-mirroring-of-remote-web-server-files-to-your-dev-environment

This feature is now part of RockMigrations and setup is as easy as setting a config variable:

```php
$config->filesOnDemand = "https://www.example.com";
```
