# RockMigrations Module

## Why Migrations?

Benjamin Milde wrote a great blog post about that: https://processwire.com/blog/posts/introduction-migrations-module/

## Why another migrations module?

Benjamin's Migrations Module is great (https://modules.processwire.com/modules/migrations/), but there were two things that I didn't like:

* For me, it didn't feel easy to use
* You have to define Migrations in a central place and I wanted to be able to use Migrations in my Modules

The second point does bring downsides with it, so this might have been intended by him. Anyhow - I like when things work "my way" :)

## Example

See this example of how it works and how easy it is to use. Let's start with a very simple Migration that only creates (on upgrade) or deletes (on downgrade) one field:

```php
$upgrade = function(RockMigrations $rm) {
  $rm->createField('yournewfield', 'text');
};

$downgrade = function(RockMigrations $rm) {
  $rm->deleteField('yournewfield');
};
```

## WARNING

**All api functions are destructive and can completely ruin your pw installation! This is intended behaviour and therefore you have to be careful and know what you are doing!**

For example deleting a template will also delete all pages having this template. Usually, when using the regular PW API, you'd need to firt check if there are any pages using this template, then delete those pages and finally also delete the corresponding fieldgroup. If the template has the system flag set, you'd also need to remove that flag before deleting the template. That's a lot of things to think of, if all you want to do is to delete a template (and of course all pages having this template). Using RockMigrations it's only one line of code: `$rm->deleteTemplate('yourtemplatename');`

The goal was: What is easy to do via the GUI should also be easy to do via the API.

## Usage

Create a new module. You can use [RockModuleCreator](https://github.com/BernhardBaumrock/RockModuleCreator) to make your life easier.

![RockModuleCreator](https://i.imgur.com/5k4NbDh.png)

You can use the Migrations Demo Module from this source as Module Skeleton URL: https://github.com/BernhardBaumrock/RockMigrationsDemo/archive/master.zip

All migrations are placed inside a `RockMigrations` folder in the root directory of your module. You can then create one php file for each migration that has the name of the version number that this migration belongs to. For example you could create a migration for your module that fires for version `1.2.3` by creating the file `1.2.3.php`. Migrations are sorted by version number internally.

![screenshot](https://i.imgur.com/Hw94jLq.png)

If you are using a code intellisense plugin in your IDE you'll get nice and helpful suggestions of what you can do (I'm using Intelephense in VSCode):

![code completion](https://i.imgur.com/rwr6SBJ.png)

This makes creating migrations really easy.

## Examples

Please see the readme of the RockMigrationsDemo Repo for detailed examples of what you can do and how: https://github.com/BernhardBaumrock/RockMigrationsDemo

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

## Roadmap/Notes/Limitations

* Error handling if upgrades fail?
* Handle order of Migrations from different Modules
* GUI for executing Migrations
* Implement a "dry run/test run" feature (eg to check for field name collisions)
* http://worldtimeapi.org/api/timezone/Etc/UTC/
