# Events and Event Listeners

## General

Pimcore provides an extensive number of events that are fired during execution of Pimcore functions. These events can be 
used to hook into many Pimcore functions such as saving an object, asset or document and can be used to change or extend 
the default behavior of Pimcore.

The most common use-case for events is using them in a [bundle/extension](13_Bundle_Developers_Guide/06_Plugin_Backend_UI.md), but 
of course you can use them also anywhere in your code or in your dependency injection configuration (`app/config/services.yml`). 

Pimcore implements the standard Symfony framework event dispatcher and just adds some pimcore specific events, 
so you can also subscribe to all Symfony core eventsand events triggered by arbitrary Symfony bundles. 

For that reason it's recommended to have a look into the Symfony [Events and Event Listeners documentation](http://symfony.com/doc/3.4/event_dispatcher.html)
first, which covers all basics in that matter. 

## Available Events

All Pimcore events are defined and documented as a constant on component specific classes: 
- [Assets](https://github.com/pimcore/pimcore/blob/master/lib/Event/AssetEvents.php)
- [Documents](https://github.com/pimcore/pimcore/blob/master/lib/Event/DocumentEvents.php)
- [Data Objects](https://github.com/pimcore/pimcore/blob/master/lib/Event/DataObjectEvents.php)
- [Versions](https://github.com/pimcore/pimcore/blob/master/lib/Event/VersionEvents.php)
- [Data Object Class Definition](https://github.com/pimcore/pimcore/blob/master/lib/Event/DataObjectClassDefinitionEvents.php)
- [Data Object Classification Store](https://github.com/pimcore/pimcore/blob/master/lib/Event/DataObjectClassificationStoreEvents.php)
- [Data Object Custom Layouts](https://github.com/pimcore/pimcore/blob/master/lib/Event/DataObjectCustomLayoutEvents.php)
- [Data Object Import](https://github.com/pimcore/pimcore/blob/master/lib/Event/DataObjectImportEvents.php)
- [Users / Roles](https://github.com/pimcore/pimcore/blob/master/lib/Event/UserRoleEvents.php)
- [Workflows](https://github.com/pimcore/pimcore/blob/master/lib/Event/WorkflowEvents.php)
- [Elements](https://github.com/pimcore/pimcore/blob/master/lib/Event/ElementEvents.php)
- [Mail](https://github.com/pimcore/pimcore/blob/master/lib/Event/MailEvents.php)
- [Admin](https://github.com/pimcore/pimcore/blob/master/lib/Event/AdminEvents.php)
- [Frontend](https://github.com/pimcore/pimcore/blob/master/lib/Event/FrontendEvents.php)
- [Cache](https://github.com/pimcore/pimcore/blob/master/lib/Event/CoreCacheEvents.php)
- [Search](https://github.com/pimcore/pimcore/blob/master/lib/Event/SearchBackendEvents.php)
- [System](https://github.com/pimcore/pimcore/blob/master/lib/Event/SystemEvents.php)
- [Translation](https://github.com/pimcore/pimcore/blob/master/lib/Event/TranslationEvents.php)
- [Bundle Manager for injecting js/css files to Pimcore backend or editmode](https://github.com/pimcore/pimcore/blob/master/lib/Event/BundleManagerEvents.php)
- [Web Services](https://github.com/pimcore/pimcore/blob/master/lib/Event/WebserviceEvents.php)

## Examples

### Hook into the pre-update event of assets, documents and objects
The following example shows how to register events for assets, documents and objects 

in your `app/config/services.yml`: 
```yaml
services:
    AppBundle\EventListener\TestListener:
        tags:
            - { name: kernel.event_listener, event: pimcore.asset.preUpdate, method: onPreUpdate }
            - { name: kernel.event_listener, event: pimcore.document.preUpdate, method: onPreUpdate }
            - { name: kernel.event_listener, event: pimcore.dataobject.preUpdate, method: onPreUpdate }
```

in your listener class `src/AppBundle/EventListener/TestListener`
```php
<?php
namespace AppBundle\EventListener;
  
use Pimcore\Event\Model\ElementEventInterface;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Event\Model\DocumentEvent;

class TestListener {
     
    public function onPreUpdate (ElementEventInterface $e) {
       
        if($e instanceof AssetEvent) {
            // do something with the asset
            $foo = $e->getAsset(); 
        } else if ($e instanceof DocumentEvent) {
            // do something with the document
            $foo = $e->getDocument(); 
        } else if ($e instanceof DataObjectEvent) {
            // do something with the object
            $foo = $e->getObject(); 
            $foo->setMyValue(microtime(true));
            // we don't have to call save here as we are in the pre-update event anyway ;-) 
        }
    }
}
```

### Hook into the list of objects in the tree, the grid list and the search panel

There are some global events having effect on several places. One of those is `pimcore.admin.object.list.beforeListLoad`.
The object list can be modified (changing condition for instance) before being loaded. This global event will apply to the tree, the grid list, the search panel and everywhere objects are listed in the Pimcore GUI.
This way, it is possible to create custom permission rules to decide if the object should or not be listed (for instance, the user must be the owner of the object, ...).

It extends the possibility further than just limiting list permissions folder by folder depending the user/role object workspace.
To ensure maximum security, it is advisable to combine this with an object DI to overload isAllowed method with the same custom permission rules. This way proper permission is ensuring all the way (rest services, ...).
