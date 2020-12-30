# RockMigrations Module

Support Board Link: https://processwire.com/talk/topic/21212-rockmigrations-easy-migrations-from-devstaging-to-live-server/

## Why Migrations?

Benjamin Milde wrote a great blog post about that: https://processwire.com/blog/posts/introduction-migrations-module/

## Why another migrations module?

I just didn't like the way the other module works. You need to create a file for every migration that you want to apply (like creating a new field, template, etc). With RockMigrations the goal is to make most of the necessary changes a 1-liner that you can add to any file you want (meaning that you can use RockMigrations in any of your modules).

## Example

Please see the file `MigrationsExample.module.php` for a sample setup.

Some examples of outdated techniques I'm not using any more are in the old readme file: https://github.com/BernhardBaumrock/RockMigrations/blob/fdd763485ca572d45143067d4966d9f49c572a95/README.md


## WARNING

**All api functions are destructive and can completely ruin your pw installation! This is intended behaviour and therefore you have to be careful and know what you are doing!**

For example deleting a template will also delete all pages having this template. Usually, when using the regular PW API, you'd need to firt check if there are any pages using this template, then delete those pages and finally also delete the corresponding fieldgroup. If the template has the system flag set, you'd also need to remove that flag before deleting the template. That's a lot of things to think of, if all you want to do is to delete a template (and of course all pages having this template). Using RockMigrations it's only one line of code: `$rm->deleteTemplate('yourtemplatename');`

If you are using RockMigrations I'm happy to hear about that: https://processwire.com/talk/topic/21212-rockmigrations-easy-migrations-from-devstaging-to-live-server/
