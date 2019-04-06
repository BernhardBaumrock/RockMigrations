# RockMigrations Module

## Why Migrations?

Benjamin Milde wrote a great blog post about that: https://processwire.com/blog/posts/introduction-migrations-module/

## Why another migrations module?

Benjamin's Migrations Module is great (https://modules.processwire.com/modules/migrations/), but there where two things that I didn't like:

* For me, it didn't feel easy to use
* You have to define Migrations in a central place and I wanted to be able to use Migrations in my Modules

The second point does bring downsides with it, so this might have been intended by him. Anyhow - I like when things work "my way" :)

## Roadmap/Notes

* Error handling if upgrades fail?
* Handle order of Migrations from different Modules
* GUI for executing Migrations
* Implement a "dry run/test run" feature (eg to check for field name collisions)

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

## Usage

Create a new module. You can use [RockModuleCreator](https://github.com/BernhardBaumrock/RockModuleCreator) to make your life easier.

Create a folder `RockMigrations` in the root directory of your module. You can then create one php file for each migration that has the name of the version number that this migration belongs to. For example you could create a migration for your module that fires for version `1.2.3` by creating the file `1.2.3.php`. Migrations are sorted by version number internally.

![screenshot](https://i.imgur.com/ErjEicZ.png)

If you are using code completion in your IDE you'll get nice and helpful suggestions of what you can do:

![code completion](https://i.imgur.com/rwr6SBJ.png)

## Demo Code for Tracy Console:

```php
$demo = $modules->get('RockMigrationsDemoModule');
$rm = $modules->get('RockMigrations');
$rm->setModule($demo);
d($rm->executeUpgrade(null, '0.0.10'));
d($rm->executeUpgrade('0.0.10', null));
```

Result:

![result](https://i.imgur.com/iFvHwyO.png)
