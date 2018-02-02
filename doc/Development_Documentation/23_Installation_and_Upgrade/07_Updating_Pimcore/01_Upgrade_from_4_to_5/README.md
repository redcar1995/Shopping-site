# Migration Guide to Pimcore Version 5

Since Pimcore 5 is built on an entire different platform/framework (Symfony replaced ZF1), an automatic update from 
Pimcore 4 to Pimcore 5 is not possible.

This guide shows you how to migrate your Pimcore applications.

The [Pimcore CLI](https://github.com/pimcore/pimcore-cli) project provides a set of tools which ease the migration by
handling certain migration tasks automatically. Please see its the documentation for further details.

Migration of applications to Pimcore 5 can be seen as a two-step process:

1. Execute the steps described in [Basic Migration](./01_Basic_Migration.md) to migrate the filesystem layout and the pimcore
   core.
2. Migrate your application code.

## Migrate Application Code

Regarding your application code, you have 2 possibilities:

## 1) Get your application up and running with the `Compatibility Bridge` of Pimcore 5
 
Pimcore 5 ships with a `Compatibility Bridge` that should enable Pimcore 5 to run Pimcore 4 applications with some file 
moves and minor code updates.
 
In theory, you can stop your migration here and run your application with the `Compatibility Bridge`. But keep in mind that
this is not recommended as it has some major consequences like

- Performance will be significantly poorer than running Pimcore without the `Compatibility Bridge`. 
- New features of Pimcore will not be available with the `Compatibility Bridge`. 
- The `Compatibility Bridge` will be removed in future Pimcore versions.
- Etc. 

See the [migration guide](./02_Migrate_for_Compatibility_Bridge.md) for details. 


## 2) Migrate your application to Pimcore 5 Symfony stack

To take full advantage of all features of Pimcore 5 the application has to be migrated to the Symfony stack. During 
development of Pimcore 5 one major goal was to keep the migration effort as low as possible. 
The actual effort to migrate your application depends on your applications architecture and 
and how much you are using ZF1 functionality directly. 

See the [migation guide](./04_Migrate_to_Symfony_Stack.md) for a checklist for migrating 
a typical Pimcore 4 Application and the [upgrade notes](../../09_Upgrade_Notes/02_V4_to_V5.md)
for additional information for changes in Pimcore 5. 
 
