# RockMigrations Module

## Why another migrations module?

Compare to Lostkobrakai's module

## Roadmap

* Button to create new modules based on the BaseModule
* Error handling if upgrades fail

## Usage

Create a folder `RockMigrations` in the root directory of your module. You can then create one php file for each migration that has the name of the version number that this migration belongs to.

For example you could create a migration for your module that fires for version `1.2.3` by creating the file `1.2.3.php`. Migrations are sorted by version number internally:

Code completion:
![code completion](https://i.imgur.com/7eWpE4V.png)