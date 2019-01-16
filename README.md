# RockMigrations Module

## Why another migrations module?

I want it to be
* easy to use
* usable inside of modules

## Roadmap

* Error handling if upgrades fail?

## Usage

Create a new module. You can use [RockModuleCreator](https://github.com/BernhardBaumrock/RockModuleCreator) to make your life easier.

Create a folder `RockMigrations` in the root directory of your module. You can then create one php file for each migration that has the name of the version number that this migration belongs to. For example you could create a migration for your module that fires for version `1.2.3` by creating the file `1.2.3.php`. Migrations are sorted by version number internally.

![screenshot](https://i.imgur.com/ErjEicZ.png)

Code completion:
![code completion](https://i.imgur.com/7eWpE4V.png)

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
