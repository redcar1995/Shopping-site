# Updating Pimcore

## Our Backward Compatibility Promise
Since we're building on top of Symfony and in an app, Pimcore and Symfony code gets mixed together, 
it just makes sense that we're adopting the same backward compatibility promise for PHP code. 

So for further information how you can ensure that your application won’t break completely 
when upgrading to a newer version of the same major release branch, please have a look at
https://symfony.com/doc/5.2/contributing/code/bc.html

The code for the admin user interface (mostly `AdminBundle` but also parts of `EcommerceFrameworkBundle`) is not covered by this promise.

## Upgrading within Version X
- Carefully read our [Upgrade Notes](../09_Upgrade_Notes/README.md) before any update. 
- Check your version constraint for `pimcore/pimcore` in your `composer.json` and adapt it if necessary to match with the desired target version.
- Run `COMPOSER_MEMORY_LIMIT=-1 composer update`
- Clear the data cache `bin/console pimcore:cache:clear`
- Run core migrations: `bin/console doctrine:migrations:migrate --prefix=Pimcore\\Bundle\\CoreBundle`
- (optional) Run [migrations of your app or bundles](../../19_Development_Tools_and_Details/37_Migrations.md)

## Upgrading from earlier Versions
- [Upgrade from Version 6 to Version X](./10_V6_to_V10.md)
- [Preparing for Version 11](./11_Preparing_for_V11.md)

