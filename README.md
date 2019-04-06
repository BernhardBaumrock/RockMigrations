# RockMigrations Module

## Why another migrations module?

I want it to be
* easy to use
* usable inside of modules

## Roadmap

* Error handling if upgrades fail?
* Handle order of Migrations from different Modules
* GUI for executing Migrations
* Implement a "dry run/test run" feature (eg to check for field name collisions)

## Example

See this example of how it works and how easy it is to use. Let's start with a very simple Migration that only creates or deletes one field:

```php
$upgrade = function(RockMigrations $rm) {
  $rm->createField('yournewfield', 'text');
};

$downgrade = function(RockMigrations $rm) {
  $rm->deleteField('yournewfield');
};
```

You only need to create one file defining two methods (`$upgrade` and `$downgrade`). And this one is a real-world Migration that is a little longer but also very easy to understand:

```php
<?php namespace ProcessWire;
/**
 * This is an example Migration for the RockMigrations module.
 * It adjusts the fields of the training template.
 *
 * Filename: /site/modules/YourModule/RockMigrations/0.0.8.php
 */
$upgrade = function(RockMigrations $rm) {
  // this upgrade function is called when "YourModule" gets upgraded from
  // a version prior to 0.0.8 to a version higher or equal to 0.0.8

  // create a new checkbox field and set some properties
  $rm->createField('paid', 'checkbox');
  $rm->setFieldData('paid', [
    "label" => "Training wurde vom Konto abgebucht",
    "collapsed" => Inputfield::collapsedNo,
  ]);

  // now add this field to the "training" template
  $rm->addFieldToTemplate('paid', 'training');

  // move client field on top of "first"-checkbox
  $rm->addFieldToTemplate('client', 'training', 'to');

  // add checkbox "markaspaid" to the "training" template
  $rm->createField('markaspaid', 'checkbox');
  $rm->addFieldToTemplate('markaspaid', 'training');
  $rm->setFieldData('markaspaid', [
    "label" => "Als bezahlt markieren und vom Guthaben abbuchen",
  ]);
  
  // set checkbox widths from 3 columns to 4 columns
  $rm->setFieldData('first', ['columnWidth' => 25]);
  $rm->setFieldData('done', ['columnWidth' => 25]);
  $rm->setFieldData('paid', ['columnWidth' => 25]);
  $rm->setFieldData('markaspaid', ['columnWidth' => 25]);
};

$downgrade = function(RockMigrations $rm) {
  // this function is called on downgrade of the module
  // this might not be necessary but it is good while developing
  // because you can always revert back to the initial state and
  // check if your upgrade function works as expected
  $rm->deleteField('paid');
  $rm->deleteField('markaspaid');
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
