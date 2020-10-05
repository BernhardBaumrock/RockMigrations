# RockMigrations Module

Support Board Link: https://processwire.com/talk/topic/21212-rockmigrations-easy-migrations-from-devstaging-to-live-server/

## Why Migrations?

Benjamin Milde wrote a great blog post about that: https://processwire.com/blog/posts/introduction-migrations-module/

## Why another migrations module?

I just didn't like the way the other module works. You need to create a file for every migration that you want to apply (like creating a new field, template, etc). With RockMigrations the goal is to make most of the necessary changes a 1-liner that you can add to any file you want (meaning that you can use RockMigrations in any of your modules).

## Example

See this example of how it works and how easy it is to use. Let's start with a very simple Migration that only creates (on upgrade) or deletes (on downgrade) one field:

```php
// See section DETAILS below
$upgrade = function(RockMigrations $rm) {
  $rm->createField('yournewfield', 'text');
};

$downgrade = function(RockMigrations $rm) {
  $rm->deleteField('yournewfield');
};
```

## Migration config files

Here's an example of a simple Migration using array syntax:

```php
$modules->get('RockMigrations')->migrate([
  'fields' => [
    'cke' => [
      'type' => 'textarea',
      'inputfieldClass' => 'InputfieldCKEditor',
      'textformatters' => ['TextformatterEntities'],
      'contentType' => 1, // html
    ],
  ],
  'templates' => [
    'ckeditor-example-template' => [
      'icon' => 'align-left',
      'noChildren' => 1, // no children allowed
      'parentTemplates' => ['home'],
      'fields' => ['cke'],
    ],
  ],
]);
```

You can also define a php file that returns an array:

```php
$rm = $modules->get('RockMigrations');
$rm->migrate($config->paths($rm)."examples/FooConfig.php");
```

See the shipped FooConfig.php file for what is possible.

Using the latter option (`migrate()`) instead of a single file migration that defines an `upgrade` and `downgrade` function is easier as long as you do not need to **remove** data from your system. Take this example:

```php
$rm->migrate([
  'fields' => [
    'foo' => ['type'=>'text'],
    'bar' => ['type'=>'text'],
  ],
]);
```

What if you wanted to remove the `foo` field and rename your `bar` field to `foobar` instead and convert it to a textarea? You'd do this:

```php
/** @var RockMigrations $rm */
$rm->deleteField('foo');
$rm->renameField('bar', 'foobar');
$rm->migrate([
  'fields' => [
    'foobar' => ['type'=>'textarea'],
  ],
]);
```

## WARNING

**All api functions are destructive and can completely ruin your pw installation! This is intended behaviour and therefore you have to be careful and know what you are doing!**

For example deleting a template will also delete all pages having this template. Usually, when using the regular PW API, you'd need to firt check if there are any pages using this template, then delete those pages and finally also delete the corresponding fieldgroup. If the template has the system flag set, you'd also need to remove that flag before deleting the template. That's a lot of things to think of, if all you want to do is to delete a template (and of course all pages having this template). Using RockMigrations it's only one line of code: `$rm->deleteTemplate('yourtemplatename');`

## Details

You can use the Migrations Demo Module from this source as Module Skeleton URL: https://github.com/BernhardBaumrock/RockMigrationsDemo/archive/master.zip

All migrations are placed inside a `RockMigrations` folder in the root directory of your module. You can then create one php file for each migration that has the name of the version number that this migration belongs to. For example you could create a migration for your module that fires for version `1.2.3` by creating the file `1.2.3.php`. Migrations are sorted by version number internally.

![screenshot](https://i.imgur.com/Hw94jLq.png)

If you are using a code intellisense plugin in your IDE you'll get nice and helpful suggestions of what you can do (I'm using Intelephense in VSCode):

![code completion](https://i.imgur.com/rwr6SBJ.png)

This makes creating migrations really easy.

## Examples

### Options fields

```php
$rm->migrate([
  'fields' => [
    'yourfield' => [
      'type' => 'options',
      "inputfieldClass" => "InputfieldCheckboxes",
      'icon' => 'bolt',
    ],
  ],
]);
    
$rm->setFieldOptionsString("yourfield", "
  1=one|Eins
  2=two|Zwei
  3=three|Drei
 ", true);
 ```

Please also see the readme of the RockMigrationsDemo Repo for some examples of what you can do and how: https://github.com/BernhardBaumrock/RockMigrationsDemo

## Run Migrations

You can run your migrations either manually or automatically when a module version change is detected.

### Manually (using Tracy)

```php
// get the migrations module
$rm = $modules->get('RockMigrations');
// set the module to execute
$rm->setModule($modules->get('RockMigrationsDemo'));

// execute upgrade 0.0.1
$rm->execute(null, '0.0.1');

// execute downgrade 0.0.1
$rm->execute('0.0.1', null);

// or to test migrations while developing
$rm->test('0.0.1');
```

Using the `$rm->test($version)` method, the module will execute the DOWNgrade first and then execute the corresponding UPGRADE. For example `$rm->test('0.0.5')` would first execute the downgrade from 0.0.5 to 0.0.4 and then the upgrade from 0.0.4 to 0.0.5; It is actually a shortcut for `$rm->down('0.0.5'); $rm->up('0.0.5');` This can save you lots of time while developing the 0.0.5 migration where you might have to go back and forth from version 0.0.5 to 0.0.4 and vice versa to check if both the upgrade and downgrade are working.

If there is no migration file for one version of the module, RockMigrations will not to anything. Some examples:

```php
// will execute all upgrades from version 0.0.3 (!) to 0.0.5
$rm->execute('0.0.2', '0.0.5');
// execute upgrade 0.0.3
// execute upgrade 0.0.4
// execute upgrade 0.0.5

// let's say we have files 0.0.3, 0.0.5 and 0.0.7 available as migrations
$rm->execute(null, '0.0.10');
// execute upgrade 0.0.3
// execute upgrade 0.0.5
// execute upgrade 0.0.7

// same setup, other command
$rm->execute(null, '0.0.3');
// execute upgrade 0.0.3

// execute only the upgrade of version 0.0.7
$rm->up('0.0.7');

// execute only the downgrade of version 0.0.7
$rm->down('0.0.7');
```

### Automatically (by version number changes)

By placing this code in your module you can tell RockMigrations to handle upgrades (or downgrades) of your module automatically:

```php
public function ___upgrade($from, $to) {
  $this->modules->get('RockMigrations')->setModule($this)->executeUpgrade($from, $to);
}
public function ___install() {
  $this->modules->get('RockMigrations')->setModule($this)->executeInstall();
}
public function ___uninstall() {
  $this->modules->get('RockMigrations')->setModule($this)->executeUninstall();
}
```

This means that whenever you change your version number of your module (eg from 0.0.2 to 0.0.3) and do a modules refresh in the backend, RockMigrations will kick in and execute all available migrations for you. This can be a great setup combined with GIT. Just push your changes, do a modules refresh and you are all done. You could even automate this process via Webhooks.

## Shared data across up and downgrades

You can use the `$rm->data` property (WireData) to share data across your functions:

```php
$this->data->tpl = "demoTemplate003";
$upgrade = function(RockMigrations $rm) {
  $rm->createTemplate($rm->data->tpl);
};
$downgrade = function(RockMigrations $rm) {
  $rm->removeTemplate($rm->data->tpl);
};
```

If you are using RockMigrations I'm happy to hear about that: https://processwire.com/talk/topic/21212-rockmigrations-easy-migrations-from-devstaging-to-live-server/
