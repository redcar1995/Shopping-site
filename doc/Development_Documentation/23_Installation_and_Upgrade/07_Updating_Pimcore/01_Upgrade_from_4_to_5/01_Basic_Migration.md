# Basic Migration

The following steps are needed for every migration to Pimcore 5 and should be done before continuing migration either
to the Pimcore 4 compatibility bridge or the Symfony Stack.

- **Backup your system!**

- Replace your `composer.json` with [this one](https://github.com/pimcore/skeleton/blob/master/composer.json) and re-add your custom dependencies. 

- The [Pimcore CLI](https://github.com/pimcore/pimcore-cli) provides a set of commands to ease the migration. It is able
  to do the following:

  - extract Pimcore 5 build
  - create several necessary directories
  - move config files to new locations
  - move class files to new location
  - move versions to new location
  - move logs to new location
  - move email logs to new location
  - move assets to new location
  - move website folder to /legacy/website
  - move plugins folder to /legacy/plugins
  - update `system.php` to be ready for Pimcore 5
  
- A simpler [migration.sh](./migration.sh) script handles basic file moving and can be adapted to your needs
- Refactor `constants.php` and move it to `app/constants.php`
- Refactor `startup.php` and move content either to `AppKernel::boot()` or `AppBundle::boot()`

- Update system configs in `/var/config/system.php` (this will be done automatically by Pimcore CLI)
    - `email` > `method` => if `''`, change to `null`
    - `email` > `smtp` > `ssl` => if `''` change to `null`
    - `email` > `smtp` > `auth` > `method` => if `''` change to `null`
    - `email` > `smtp` > `auth` > `password` => add if not there with value `''`
    - `newsletter` > `method` => if `''` change to `null`
    - `newsletter` > `smtp` > `ssl` => if `''` change to `null`
    - `newsletter` > `smtp` > `auth` > `method` => if `''` change to `null`
    - `newsletter` > `smtp` > `auth` > `password` => add if not there with value `''`

- Change document root of your webserver to `/web` directory - document root must not be project root anymore

- Update your `composer.json` to include all dependencies and settings from Pimcore's `composer.json`. The Pimcore CLI will
  use Pimcore's `composer.json` and back up your existing one. If you have any custom dependencies or settings please make
  sure to re-add them to the `composer.json`.
  Alternatively, you can add a file named `composer.local.json` only including your custom dependencies. It will be merged
  with `composer.json` dynamically during composer operations thanks to the `wikimedia/composer-merge-plugin` composer plugin.
  If you do so, please make sure you remove `composer.local.json` from the `.gitignore` file.

- Run `composer update` to install new dependencies. If you encounter errors, please fix them until the command works properly.
  You can use `--no-scripts` to install dependencies and then iterate through errors in subsequent calls to save some time.

- At this point, the basic application should work again. Please try to run `bin/console` to see if the console works

- The [pimcore-migrations-40-to-54.php](https://gist.github.com/brusch/c3e572947a7a7e8523e18e9787cf88c3) script contains
  all migrations which were introduced from Pimcore 5.0 to 5.4 and are needed when migrating from Pimcore 4. 
  To execute the script, use the following command (making a backup at this stage is strongly recommended):
  
  ```bash
  $ php pimcore-migrations-40-to-54.php
  ```
  
- Execute all core migrations from 5.4.x to the latest version, by running the following command: 

  ```bash
  $ ./bin/console pimcore:migrations:migrate -s pimcore_core 
  ```
  
- Run `composer update` once again to update the autoloader and class maps
- The admin interface of your system should now work again and you can proceed to [migrate your application code](./README.md). 
- Update your [.gitignore](https://github.com/pimcore/pimcore/blob/master/.gitignore)
