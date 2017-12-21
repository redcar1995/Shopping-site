# Upgrade Notes for Upgrades within Pimcore 5

## Pimcore 5.1

**Symfony 3.4**: Pimcore 5.1 uses Symfony 3.4 as dependency. Please have a look at [Symfony's release notes](https://symfony.com/blog/symfony-3-4-0-released)
for 3.4 and fix any potential Symfony related deprecations in your code before upgrading. After upgrading please make sure
to fix any new deprecations marked by Symfony 3.4 to be ready for future Symfony versions. 

**Admin Controllers**: As preparation for Symfony 4 we had to refactor some of our implementations to make sure they will
be compatible with Symfony 4. Unfortunately this also concerns 3 methods which the `AdminController` overwrites from the
standard symfony controller: `json()`, `getUser()` on the `AdminController` and `createNotFoundException` on the
`AbstractRestController`.

If you implement any controller which inherits from Pimcore's `AdminController` please make sure to update the following
method calls to ensure the same functionality:

Controllers inheriting from `Pimcore\Bundle\AdminBundle\Controller\AdminController`:

| Old call           | New call                | Note                                                                                                                                                                                                                   |
|--------------------|-------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `$this->json()`    | `$this->adminJson()`    | You can still use `json()` as it is a standard Symfony controller method, but please be aware that it uses the Symfony Serializer instead of Pimcore's admin serializer and the results may differ from `adminJson()`. |
| `$this->getUser()` | `$this->getAdminUser()` | You can still use `getUser()`, but please be aware that the returned user is a `Pimcore\Bundle\AdminBundle\Security\User\User` and not the `Pimcore\Model\User` which is returned in `getAdminUser()`.                 |

Controllers inheriting from `Pimcore\Bundle\AdminBundle\Controller\Rest\AbstractRestController`:

| Old call                           | New call                                    | Note |
|------------------------------------|---------------------------------------------|------|
| `$this->createNotFoundException()` | `$this-> createNotFoundResponseException()` |      |

**Extensions:** The default priority of bundles enabled via extension manager was changed from `0` to `10` to make sure
those bundles are loaded before the `AppBundle` is loaded. Please make sure this works for your application
and set a manual priority otherwise. See https://github.com/pimcore/pimcore/pull/2328 for details.

**E-Commerce:** Due to performance reasons, we needed to change the way how index service attributes are handled. They are
now built at runtime instead of handling each attribute as service. To achieve this, config service definition now relies
on the method `setAttributeFactory` being called creating a service instance. If your config definition uses `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\AbstractConfig`
as parent definition you should be set, otherwise you'll need to make sure your service definition includes the method
call:

```yaml
services:
    AppBundle\IndexService\Config\CustomConfig:
        # [...]
        calls:
            - [setAttributeFactory, ['@Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\AttributeFactory']]
```

**Targeting/Personalization**: The targeting/personalization engine was completely revamped and now uses server side targeting
instead of the frontend targeting which was used in earlier versions. The new targeting integration is **incompatible**
with the previous one and will break existing targeting setups. If you are currently using targeting please do **NOT**
update to Pimcore 5.1 until there is a migration guide for targeting setups. **NOTE**: the targeting feature is **experimental**
and may be subject to change in later versions.
  

## Build 156 (2017-12-13)

The experimental `GridColumnConfig` feature was revamped to register and build its operators via DI instead of predefined
namespaces (see [PR#2333](https://github.com/pimcore/pimcore/pull/2333)). If you already implemented custom operators
please make sure you update them to the new structure.

## Build 149 (2017-11-14)

The Piwik integration which was recently added was refactored to always use a full URI including the protocol for the Piwik
URL configuration setting. Please update your settings to include the protocol as otherwise the Piwik tracking will be 
disabled.

Before:

* `piwik.example.com`
* `analytics.example.com/piwik`

Now:

* `https://piwik.example.com`
* `https://analytics.example.com/piwik`

## Build 148 (2017-11-13)

The ecommerce order manager now has a dependency on the `Pimcore\Model\Factory`. To make the class backwards compatible,
the dependency is injected via a dedicated setter which is marked as `@required` to support autowiring. If you don't extend
the default order manager you don't need to do anything as the core order manager is already properly configured. If you
have custom order manager service definitions, please make sure the definition is autowired or has an explicit setter
call (see examples below).

```yaml
services:
    # enable autowiring either on a service level or as _defaults
    # for the whole file
    AppBundle\Ecommerce\Order\OrderManager:
        autowire: true
        arguments:
            - '@pimcore_ecommerce.environment'
            - '@?'
            - '@pimcore_ecommerce.voucher_service'
            - []


    # or add a dedicated setter call
    AppBundle\Ecommerce\Order\OrderManager:
        arguments:
            - '@pimcore_ecommerce.environment'
            - '@?'
            - '@pimcore_ecommerce.voucher_service'
            - []
        calls:
            - [setModelFactory, ['@Pimcore\Model\Factory']]
```

## Build 134 (2017-10-03)

This build changes the default setting for the legacy name mapping in the ecommerce framework (see [LegacyClassMappingTool](https://github.com/pimcore/pimcore/blob/master/pimcore/lib/Pimcore/Bundle/EcommerceFrameworkBundle/Legacy/LegacyClassMappingTool.php))
to false, disabling legacy class mapping for new projects. When you're updating from a previous version and the ecommerce 
framework is enabled, the updater will automatically enable the class mapping for you by creating a config file in `app/config/local`.
If you're starting fresh, you'll need to enable the mapping manually if needed by setting the following config value:

```yaml
pimcore_ecommerce_framework:
    use_legacy_class_mapping: true
```

## Build 100 (2017-08-30)

### Objects were renamed to Data Objects
The introduction of object type hints in PHP 7.2 forced us to rename several namespaces to be compliant with the
[PHP 7 reserved words](http://php.net/manual/de/reserved.other-reserved-words.php).  
- Namespace `Pimcore\Model\Object` was renamed to `Pimcore\Model\DataObject`
    - PHP classes of Data Objects are now also in the format eg. `Pimcore\Model\DataObject\News`
- Several other internal classes were renamed or moved as well
    - `Pimcore\Event\Object*` to `Pimcore\Event\DataObject*`
    - `Pimcore\Model\User\Workspace\Object` to `Pimcore\Model\User\Workspace\DataObject`
- [Object Placeholders](../../19_Development_Tools_and_Details/23_Placeholders/01_Object_Placeholder.md) syntax changed to `%DataObject()`
- There's a compatibility autoloader which enables you to still use the former namespace (< PHP 7.2), but you should migrate asap. to the new namespaces.
- After the update please consider the following: 
    - If you're using custom [class overrides](../../20_Extending_Pimcore/03_Overriding_Models.md) in your `app/config/config.yml`, please adapt them using the new namespace.
    - If you're using event listeners on object events, please rename them as well to `pimcore.dataobject.*`
    - Your code should continue to work as before, due to the compatibility autoloader, which is creating class aliases on the fly
    - Update your `.gitignore` to exclude `/var/classes/DataObject` instead of `/var/classes/Object`
- If the update fails, please try the following: 
    - fix the above configuration changes, mainly the class overrides and potentially other relevant configurations
    - `composer dump-autoload`
    - `./bin/console cache:clear --no-warmup`
    - run the [migration script](https://gist.github.com/brusch/03521a225cffee4baa8f3565342252d4) manually on cli
  

## Build 97 (2017-08-24)

This build re-adds support to access website config settings from controllers and views, but in a slightly different way
than in Pimcore 4. See [Website Settings](../../18_Tools_and_Features/27_Website_Settings.md) for details.

## Build 96 (2017-08-22)

This build adds support for migrations in bundle installers (see [Installers](../../20_Extending_Pimcore/13_Bundle_Developers_Guide/05_Pimcore_Bundles/01_Installers.md)).
With this change, extension manager commands can now also be executed as CLI commands and installers use an `OutputWriter`
object to return information to the extension manager or to CLI scripts. As this `OutputWriter` is initialized in `AbstractInstaller`s 
constructor, please update your custom installers to call the parent constructor. 

### Upgrade errors

If you get an error like the following while upgrading to build 96, please run `composer update` manually on the command
line and continue to upgrade:

```
Class Doctrine\Bundle\MigrationsBundle\Command\MigrationsExecuteDoctrineCommand not found in ExecuteCommand.php (line 8)
```

You can avoid this problem by installing the `doctrine/doctrine-migrations-bundle` package *BEFORE* running the upgrade:

```bash
$ composer require doctrine/doctrine-migrations-bundle "^1.2"
```

## Build 86 (2017-08-02)

E-Commerce Framework configuration was moved to a Symfony Config. For details see 
[Config Signature changes](./03_Ecommerce_Framework/02_Ecommerce_Framework_Config_Signature_Changes.md)


## Build 85 (2017-08-01)

This build changed how the admin session is handled and introduced a regression breaking the maintenance page checking. The
result of this regression is that subsequent updates can't be installed as the updater activates the maintenance mode and
the update can't be processed as the maintenance check does not recognize the admin session as being excluded from the maintenance.
You won't be able to interact with the admin as the system will present the maintenance page for every request.

If you updated up to build 85 and experience this issue, you can solve it with the following steps:

* remove `var/config/maintenance.php` - this allows you to open the admin interface again as it disables maintenance mode
* if you want to use the web updater please apply [these changes](https://github.com/pimcore/pimcore/commit/e4b2d2952d5e16cbea2d59b78629ab5d733d779b)
  manually before continuing the update
* alternatively you can use the CLI updater to circumvent the maintenance page. the following command would update to build 86:

    PIMCORE_ENVIRONMENT=dev bin/console pimcore:update --ignore-maintenance-mode -u 86
    
### Session related BC breaks

The admin session ID can't be injected via GET parameter anymore. This was possbile in previous Pimcore versions to support
Flash based file uploaders but was obsolete.


## Build 60 (2017-05-31)

The navigation view helper signature has changed and now uses a different syntax to render navigations. In short,
building the navigation container and rendering the navigation is now split up into 2 distinct calls and needs to be adapted
in templates. This applies to all navigation types (menu, breadcrumbs, ...).

```php
<?php
// previously
echo $this->navigation($this->document, $navStartNode)->menu()->renderMenu(null, ['maxDepth' => 1]);

// now
$nav = $this->navigation()->buildNavigation($this->document, $navStartNode);
echo $this->navigation()->menu()->renderMenu($nav, ['maxDepth' => 1]);
```

See the [navigation documentation](./../../03_Documents/03_Navigation.md) for details.

## Build 54 (2017-05-16)

Added new `nested` naming scheme for document editables, which allows reliable copy/paste in nested block elements. Pimcore
defaults to the new naming scheme for fresh installations, but configures updated installations to use the `legacy` scheme.

To configure Pimcore to use the `legacy` naming scheme strategy, set the following config:

```yaml
pimcore:
    documents:
        editables:
            naming_strategy: legacy
```

See [Editable Naming Strategies](../../03_Documents/13_Editable_Naming_Strategies.md) for information how to migrate to
the `nested` naming strategy.
