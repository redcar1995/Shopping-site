# Upgrade Notes

## 10.0.0
- `\Pimcore\Helper\RobotsTxt` has been removed
- `\Pimcore\Cache\Runtime::get('pimcore_editmode')` isn't supported anymore, use `EditmodeResolver` service instead. 
- [Documents] `Editable::factory()` was removed, use `EditableLoader` service instead.
- [Data Objects] Removed CSV import feature. Use https://github.com/pimcore/data-importer or https://github.com/w-vision/DataDefinitions instead.
- [DataObjects] marked `Pimcore\DataObject\GridColumnConfig\Operator` operator classes as final and internal
- [DataObjects] PHP Class `Pimcore\Model\DataObject\Data\Geopoint` has been replaced with `GeoCoordinates`. Changed the signature of `__construct`.
- Added `Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\AbstractFilterType::getFilterValues()` with the same signature as `getFilterFrontend()`. To upgrade, rename `getFilterFrontend()` to `getFilterValues()` and remove the rendering stuff to just return the data array.

    Before:
    ```php
    public function getFilterFrontend(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList, $currentFilter) 
    {
        // ...
        return $this->render($this->getTemplate($filterDefinition), [
            //...
        ]);
    }
    ```
    After:
    ```php
    public function getFilterValues(AbstractFilterDefinitionType $filterDefinition, ProductListInterface $productList, array $currentFilter): array 
    {
        // ...
        return [
            //...
        ];
    }
    ```
- Added Validation for Geo datatypes
    - for Geopolyline and Geopolygon invalid data doesn't get serialized 1:1 anymore
    - for Geobounds and Geopoint invalid data doesn't get dropped silently anymore
- Calling `$imageAsset->getThumbnail('non-existing-thumbnail-definition)` with a non-existing thumbnail definition will now throw an exception. Same goes for video assets and video image thumbnails.
- Removed grid column operator `ObjectBrickGetter` since it is obsolete
- Grid operator `AnyGetter` available only for admin users from now on
- [Ecommerce] Added `getAttributeConfig` method to `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ConfigInterface` interface
- [Ecommerce] Added `getClientConfig` method to `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ElasticSearchConfigInterface`   
- [Ecommerce] Added abstract method `setSuccessorOrder` to `Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder`
- [Ecommerce] Indexing doesn't catch any exceptions that occur during preprocessing of attributes in BatchProcessing workers (e.g. elasticsearch). 
  You can change that behavior with event listeners.
- [Ecommerce] Added abstract method `setCartHash` to `Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder`  
- [Ecommerce] Added `getFieldNameMapped` to ` Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ElasticSearchConfigInterface`
- [Ecommerce] Added `getReverseMappedFieldName` to ` Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ElasticSearchConfigInterface`
- [Ecommerce] Changed tenant config type hint to `FindologicConfigInterface` in `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\DefaultFindologic::__construct`
- [Ecommerce] Changed price fields `totalNetPrice` and `totalPrice` of `OnlineShopOrderItem` to decimal.
- [Ecommerce] Removed deprecated configuration options `enabled`, `pricing_manager_id` and `pricing_manager_options` for pricing_manager. 
  Use tenant specific options.  
- [Ecommerce] Removed deprecated functions `get/setCurrentTenant` and `get/setCurrentSubTenant` 
  of `EnvironmentInterface`
- [Ecommerce] Removed deprecated service alias for `Pimcore\Bundle\EcommerceFrameworkBundle\IEnvironment`
- [Ecommerce] Removed deprecated functions `getGeneralSearchColumns`, `createOrUpdateTable`, `getIndexColumns` and `getIndexColumnsByFilterGroup` 
  of `IndexService`  
- [Ecommerce] Removed deprecated function `getPaginatorAdapter` from 
  `ProductList\MySql`, `ProductList\DefaultFindologic`, `ProductList\ElasticSearch\AbstractElasticSearch`, `Token\Listing` and `AbstractOrderList`
- [Ecommerce] Removed deprecated functions `getCalculatedPrice` and `getCalculatedPriceInfo` from `AbstractSetProduct`
- [Ecommerce] Removed deprecated protected function `getAvailableFilterValues` from `Order\Listing`  
- [Ecommerce] Activated `generateTypeDeclarations` for all generated data object classes and field collections. For migration 
  activate `generateTypeDeclarations` to all Ecommerce Framework data object classes and update your source code accordingly.
- [Ecommerce] Made methods abstract instead of throwing `UnsupportedException` where easily possible for model classes (`AbstractProduct`, `AbstractSetProduct`, `AbstractOfferToolProduct`, `AbstractOfferItem`, `AbstractOffer`). 
- [Ecommerce] Added type declarations to Ecommerce Framework product interfaces (`ProductInterface`, `IndexableInterface`, `CheckoutableInterface`).
- [Ecommerce] Removed Elasticsearch 5 and 6 support 
- [Ecommerce] `getItemAmount` and `getItemCount` of `Carts` now require string parameter (instead of boolean). Use one of 
`CartInterface::COUNT_MAIN_ITEMS_ONLY`, `CartInterface::COUNT_MAIN_AND_SUB_ITEMS`, `CartInterface::COUNT_MAIN_OR_SUB_ITEMS`. 
- [Ecommerce] Removed legacy CheckoutManager architecture, migrate your project to V7 if not already
  - `CancelPaymentOrRecreateOrderStrategy` is now default strategy for handling active payments 
  - Removed method `isCartReadOnly` from cart and `cart_readonly_mode` configuration option as readonly mode 
    does not exist anymore.
  - Removed deprecated method `initPayment` from `PaymentInterface`
- [Ecommerce] Removed deprecated `ecommerce:indexservice:process-queue` command, 
  use `ecommerce:indexservice:process-preparation-queue` or `ecommerce:indexservice:process-update-queue` instead
- [Ecommerce] Removed deprecated `mapping` option in index attributes configuration (never worked properly anyway) 
- [Ecommerce] Removed deprecated `IndexUpdater` tool
- [Ecommerce] Removed legacy BatchProcessing worker mode, product centric batch processing is now standard
  - Removed abstract class `AbstractBatchProcessingWorker`, use `ProductCentricBatchProcessing` instead
  - Removed methods from interface `BatchProcessingWorkerInterface` and its implementations:
     - `BatchProcessingWorkerInterface::processPreparationQueue`
     - `BatchProcessingWorkerInterface::processUpdateIndexQueue`
  - Added methods to interface `BatchProcessingWorkerInterface`
    - `BatchProcessingWorkerInterface::prepareDataForIndex`
    - `BatchProcessingWorkerInterface::resetPreparationQueue`
    - `BatchProcessingWorkerInterface::resetIndexingQueue`
  - Removed constants 
     - `ProductCentricBatchProcessingWorker::WORKER_MODE_LEGACY` 
     - `ProductCentricBatchProcessingWorker::WORKER_MODE_PRODUCT_CENTRIC`
  - Removed configuration node `worker_mode` in `index_service` configuration  
- [Ecommerce] Moved method `getIdColumnType` from `MysqlConfigInterface` to `ConfigInterface`. Since it was and still is 
  implemented in `AbstractConfig` this should not have any consequences.
- [Web2Print] 
   - Removed `PdfReactor8`, use `PdfReactor` instead.
   - Removed PDFreactor version selection in web2print settings, since most current PDFreactor client lib
     should be backwards compatible to older versions.       
- [Email & Newsletter] Swiftmailer has been replaced with Symfony Mailer. `\Pimcore\Mail` class now extends from `Symfony\Component\Mime\Email` and new mailer service `Pimcore\Mail\Mailer` has been introduced, which decorates `Symfony\Component\Mailer\Mailer`, for sending mails.

    Email method and transport setting has been removed from System settings. Cleanup Swiftmailer config and setup mailer transports "main" & "newsletter" in config.yaml:
    ```yaml
    framework:
        mailer:
            transports:
                main: smtp://user:pass@smtp.example.com:port
                pimcore_newsletter: smtp://user:pass@smtp.example.com:port
    ```
    please see [Symfony Transport Setup](https://symfony.com/doc/current/mailer.html#transport-setup) for more information.
    
    API changes:
    
    Before:
    ```php
        $mail = new \Pimcore\Mail($subject = null, $body = null, $contentType = null, $charset = null);
        $mail->setBodyText("This is just plain text");
        $mail->setBodyHtml("<b>some</b> rich text: {{ myParam }}");
        ...
    ```
    After:
    ```php
        $mail= new \Pimcore\Mail($headers = null, $body = null, $contentType = null);
        $mail->setTextBody("This is just plain text");
        $mail->setHtmlBody("<b>some</b> rich text: {{ myParam }}");
        ...
    ```

    Before:
    ```php
      $mail->setFrom($emailAddress, $name);
      $mail->setTo($emailAddress, $name);
      ...
    ```
      
    After:
    ```php
      $mail->from(new \Symfony\Component\Mime\Address($emailAddress, $name));
      $mail->to(new \Symfony\Component\Mime\Address($emailAddress, $name));
      ...
    ```
- [Security] BruteforceProtectionHandler & BruteforceProtectionListener has been made final and marked as internal.
- [JWTCookieSaveHandler] `Pimcore\Targeting\Storage\Cookie\JWT\Decoder` has been removed in favor of `Lcobucci\JWT\Encoding\JoseDecoder`.
- `simple_html_dom` library has been removed. Use `Symfony\Component\DomCrawler\Crawler` instead.
- Removed deprecated Twig extension `pimcore_action()`.
- Removed method `getFlag()` from `Pimcore\Config`.
- Removed `Pimcore\Report` class.

## 6.9.0
- `\Pimcore\Helper\RobotsTxt` has been deprecated and will be removed in Pimcore 10
- [Documents] `Editable::factory()` is deprecated and will be removed in Pimcore 10, use `EditableLoader` service instead. 
- [Data Objects] CSV import feature will be removed in Pimcore 10. Use https://github.com/pimcore/data-hub or https://github.com/w-vision/DataDefinitions instead
- [DataObjects] PHP Class `Pimcore\Model\DataObject\Data\Geopoint` will go away in Pimcore 10. Use `GeoCoordinates` instead which changes the signature of `__construct` parameters
- [Ecommerce] `getClientConfig` method will be added to `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ElasticSearchConfigInterface` in Pimcore 10
- [ECommerce] `setSuccessorOrder` will be added to `Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder` in Pimcore 10
- [Ecommerce] `getFieldNameMapped` will be added to ` Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ElasticSearchConfigInterface` in Pimcore 10
- [Ecommerce] `getReverseMappedFieldName` will be added to ` Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\ElasticSearchConfigInterface` in Pimcore 10
- [Ecommerce] Tenant config type hint will be changed to `FindologicConfigInterface` in `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\DefaultFindologic::__construct` in Pimcore 10
- Calling static methods on `Pimcore\Model\DataObject\AbstractObject` is deprecated, use `Pimcore\Model\DataObject` instead.
- [Ecommerce] Abstract method `setCartHash` will be added to `Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder` in Pimcore 10
- Abstract method `load` will be added to `Pimcore\Model\Listing\Dao\AbstractDao` in Pimcore 10
- [Elastic Search] `getClientConfig` will be added to the `Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config` interface in Pimcore 10
- `PageSnippet::$elements` property visibility changed from `protected` to `private` 
- `PageSnippet::$inheritedElements` property visibility changed from `protected` to `private` 
- [Ecommerce] Ecommerce tracking `*.js.php` templates are deprecated and will not supported on Pimcore 10. Please use Twig `*.js.twig` templates. Also `Tracker::templateExtension` property is deprecated and will be removed in Pimcore 10. 
- Config option and container parameter `pimcore.routing.defaults` is deprecated, use `pimcore.documents.default_controller` instead. 
- Method `\Pimcore\Tool::getRoutingDefaults()` is deprecated and will be removed in Pimcore 10. 
- `PageSnippet::$module|$action|get/setAction()|get/setModule()` are deprecated and will be removed in Pimcore 10 - see below for migration.
- `DocType::$module|$action|get/setAction()|get/setModule()` are deprecated and will be removed in Pimcore 10 - see below for migration.
- `Staticroute::$module|$action|get/setAction()|get/setModule()` are deprecated and will be removed in Pimcore 10 - see below for migration.
- Using dynamic modules, controllers and actions in static routes (e.g. `%controller`) is deprecated and will not continue to work in Pimcore 10.
- `\Pimcore\Controller\Config\ConfigNormalizer` has been deprecated and will be removed in Pimcore 10. 
- Templating helper `$this->action()` as well as the Twig extension `pimcore_action()` are deprecated and will be removed in Pimcore 10. Use Twig `render()` instead.
- `\Pimcore\Model\Element\Reference\Placeholder` has been deprecated and will be removed in Pimcore 10. Use `\Pimcore\Model\Element\ElementDescriptor` instead.
- `WebDebugToolbarListenerPass` has been deprecated and will be removed in Pimcore 10. 
- `MigrationInstaller` for bundles has been deprecated and will be removed in Pimcore 10, please use `AbstractInstaller` instead. 
- The entire `Pimcore\Migrations` namespace has been deprecated and will be removed in Pimcore 10. Please use Doctrine Migrations directly instead for your project and bundles.
- `ClassMapLoader` now takes priority over `PrefixLoader` e.g. config `pimcore.document.editables.map` has higher priority than `pimcore.document.editables.prefixes`.
- The config option `asset.image.thumbnails.webp_auto_support` has been deprecated and will be removed in Pimcore 10. Use the `<picture>` tag instead (you can force that in your thumbnail config).
- `\Pimcore\Model\Asset\Image\Thumbnail\Processor::setHasWebpSupport()` and `hasWebpSupport()` has been deprecated and will be removed in Pimcore 10.
- `\Pimcore\Tool\Frontend::hasWebpSupport()` and all related `webp` methods have been deprecated and will be removed in Pimcore 10. 
- Config option `pimcore.translations.case_insensitive` has been deprecated and will be removed in Pimcore 10. 
- `PageSnippet::$elements` property visibility changed from `protected` to `private`
- `PageSnippet::$inheritedElements` property visibility changed from `protected` to `private`
- `Pimcore\Model\Translation\AbstractTranslation`, `Pimcore\Model\Translation\Admin` and `Pimcore\Model\Translation\Website` with corresponding listing classes have been deprecated and will be removed in Pimcore 10. Use new class `Pimcore\Model\Translation` with domain support (`Translation::DOMAIN_DEFAULT` or `Translation::DOMAIN_ADMIN`).
- Calling `getQuery()` on listing classes (to fetch Zend Compatibility Query Builder) has been deprecated and will be removed in Pimcore 10. Use `getQueryBuilder()` which returns Doctrine Query builder instead.
- Using onCreateQuery callback to modify listing queries has been deprecated. Use onCreateQueryBuilder() instead. e.g.
```php
    /** @var \Pimcore\Model\DataObject\News\Listing $list */
    $list = new Pimcore\Model\DataObject\News\Listing();

    $list->onCreateQueryBuilder(
        function (\Doctrine\DBAL\Query\QueryBuilder $select) use ($list) {
            // modify listing $select->join(....);
        }
    );
```
- Using Zend\Paginator for listing classes has been deprecated and will be removed in Pimcore 10. Use Knp\Component\Pager\Paginator instead.
- [Ecommerce] Elasticsearch 5 and 6 support is deprecated, use newer versions of elasticsearch.
- [Ecommerce] Calling `getItemAmount` and `getItemCount` of `Carts` with boolean parameter is deprecated. Use one of 
  `CartInterface::COUNT_MAIN_ITEMS_ONLY`, `CartInterface::COUNT_MAIN_AND_SUB_ITEMS`, `CartInterface::COUNT_MAIN_OR_SUB_ITEMS` 
  instead. 
- `Pimcore\Targeting\Storage\Cookie\JWT\Decoder` class has been deprecated and will be removed in Pimcore 10.
- `Pimcore\Tool\Console`: Methods `getSystemEnvironment()`, `exec()`, `execInBackground()` and `runPhpScriptInBackground()` have been deprecated, use `Symfony\Component\Process\Process` instead where possible. For long running background tasks (which should run even when parent process has exited), switch to a queue implementation.
- Image thumbnails: Using getHtml() on Image Thumbnails will return `<picture>` tag instead of `<img>` tag in Pimcore 10. Please use `getImageTag()` method instead. Also, passing `$removeAttribute` param to `getHtml()` has been deprecated and will throw Exception in Pimcore 10.
- Deprecated `Pimcore\Tool\Mime`, use `Symfony\Component\Mime\MimeTypes` instead.  
- `Pimcore\Config::getFlag()` method has been deprecated and will be removed in Pimcore 10.

- [Analytics] Matomo(Piwik) integration has been deprecated in Core and Ecommerce bundle, and will be removed in Pimcore 10.
- [Targeting and Personalization] VisitedPageBefore condition has been deprecated, as it is based on deprecated Piwik integration and will be removed in Pimcore 10.
- [AdminBundle] Marked classes @internal and controllers declared as final - please see all changes here: https://github.com/pimcore/pimcore/pull/8453/files

#### Migrating legacy module/controller/action configurations to new controller references
You can use `./bin/console migration:controller-reference` to migrate your existing Documents, 
Staticroutes and Document Types to the new controller references in the format: `AppBundle\Controller\FooController::barAction`.
The migration has to be done **before** upgrading to the next major version! We recomment to perform the migration 
right after the upgrade to version 6.9.0. 
If there are some errors during the execution of the command, don't panic. 
You can run the command as often as you want, since it doesn't touch already migrated entities. 

## 6.8.0
- HybridAuth integration is deprecated and will be removed in Pimcore 10.
- `Pimcore\Browser` is deprecated and will be replaced by `\Browser` in Pimcore 10. [#7084](https://github.com/pimcore/pimcore/pull/7084)
- Javascript - All classes under namespaces `pimcore.document.tags` are deprecated and will be removed in Pimcore 10. These are moved to new namespace `pimcore.document.editables`. 
If you have custom editables or wysiwyg global config then please change namespace from `pimcore.document.tags.*` to `pimcore.document.editables.*`
- Javascript - Class `pimcore.document.tag` is deprecated as well and will be removed in Pimcore 10. Use new class `pimcore.document.editable` instead.
- All classes in namespace `Pimcore\Document\Tag` moved to new namespace `Pimcore\Document\Editable` (including their services) for better readability and marked as deprecated. Please update custom document editable classes and mappings. [#6921](https://github.com/pimcore/pimcore/pull/6921)
- All `document_tag_` css classes are deprecated and will be removed in Pimcore 10. Use new classes `document_editable_` for custom styling of document editables.
- Method signature `AbstractObject::setChildren($children)` has been updated to `AbstractObject::setChildren($children, array $objectTypes = [self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER], $includingUnpublished = false)`.
- Edit Template for Area Bricks `edit.html.(php|twig)` has been deprecated and will be removed in Pimcore 10. Use new feature [editable dialog box](https://github.com/pimcore/pimcore/pull/6923#issuecomment-671257112) instead.
- `EventedControllerInterface` is marked as deprecated and will be removed in Pimcore 10. Please use new interfaces for kernel events `KernelControllerEventInterface::onKernelControllerEvent()` or `KernelResponseEventInterface::onKernelResponseEvent()` instead.
- PHP templating engine (including templating helpers & vars) has been deprecated and will be removed in Pimcore 10. Use Twig Instead.
- The Tag Manager has been deprecated and will be removed in Pimcore 10. 
- Class `\Pimcore\Model\Tool\Tracking\Event` has been deprecated and will be removed in Pimcore 10. 
- Auto view rendering has been deprecated and will be removed in Pimcore 10, which means views will not be tied to action implicitly using the filename and `$this->view` (`ViewModel`) in actions stops working. Use Symfony way of [Rendering Templates](https://symfony.com/doc/current/templates.html#rendering-templates) instead.
- Event `\Pimcore\Event\AdminEvents::INDEX_SETTINGS` has been deprecated and will be removed in Pimcore 10, use `\Pimcore\Event\AdminEvents::INDEX_ACTION_SETTINGS` instead.
- Class `\Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\Helper` has been deprecated and will be removed in Pimcore 10. Use `Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\ListHelper` service instead.
- Class `\Pimcore\Model\Tool\Lock` has been deprecated and will be removed in Pimcore 10 use the lock factory service `Symfony\Component\Lock\LockFactory` instead 
- Payment providers `Datatrans`, `Heidelpay`, `Hobex`, `Klarna`, `Mpay24Seamless`, `OGone`, `PayPalSmartPaymentButton`, `PayU` are deprecated and will be moved to a separate bundle in Pimcore 10.
- Payment providers `WirecardSeamless`, `Qpay`, `Paypal` are deprecated and will be removed in Pimcore 10.
- QRCodes (`\Pimcore\Model\Tool\Qrcode\Config`) have been deprecated and will be removed in Pimcore 10. 

## 6.7.0
- [Ecommerce][IndexService] Elastic Search worker does not use mockup cache anymore. Now mockup objects are build directly based on information in response of ES response (_source flag). Therefore `AbstractElasticSearch` Worker does not extend `AbstractMockupCacheWorker` anymore. 
- Rules regarding default values in combination with inheritance enabled have been clarified. Read [this](../../05_Objects/01_Object_Classes/01_Data_Types/README.md#page_Default-values) for details.
- [Ecommerce] Deprecated FactFinder integration and will be removed in Pimcore 10.
- Saving unpublished data objects via API will not throw Validation exceptions anymore (just like Admin UI). Please set `omitMandatoryCheck` explicitly to `false` to force mandatory checks.
- `\Pimcore\DataObject\GridColumnConfig\Operator\ObjectBrickGetter` operator is deprecated and will be removed in Pimcore 10
- Calling `Pimcore\Model\DataObject\ClassDefinition\Data::isEqual()` is deprecated since version 6.7.0 and will be removed in version 7 . Implement `\Pimcore\Model\DataObject\ClassDefinition\Data\EqualComparisonInterface` instead.
- Following properties and methods are deprecated to unify document editables and will be removed in Pimcore 10. [#6900](https://github.com/pimcore/pimcore/pull/6900):
    - `PageSnippet::$elements`. Use `PageSnippet::$editables` instead.
    - `PageSnippet::$inheritedElements`. Use `PageSnippet::$inheritedEditables` instead.
    - `PageSnippet::getElements`. Use `PageSnippet::getEditables` instead.
    - `PageSnippet::setElements`. Use `PageSnippet::setEditables` instead.
    - `PageSnippet::setRawElement`. Use `PageSnippet::setRawEditable` instead.
    - `PageSnippet::removeElement`. Use `PageSnippet::removeEditable` instead.
    - `TargetingDocumentInterface::TARGET_GROUP_ELEMENT_PREFIX`. Use `TargetingDocumentInterface::TARGET_GROUP_EDITABLE_PREFIX` instead.
    - `TargetingDocumentInterface::TARGET_GROUP_ELEMENT_SUFFIX`. Use `TargetingDocumentInterface::TARGET_GROUP_EDITABLE_SUFFIX` instead.
    - `TargetingDocumentInterface::getTargetGroupElementPrefix`. Use `TargetingDocumentInterface::getTargetGroupEditablePrefix` instead.
    - `TargetingDocumentInterface::getTargetGroupElementName`. Use `TargetingDocumentInterface::getTargetGroupEditableName` instead.
    - `TargetingDocumentInterface::hasTargetGroupSpecificElements`. Use `TargetingDocumentInterface::hasTargetGroupSpecificEditables` instead.
    - `TargetingDocumentInterface::getTargetGroupSpecificElementNames`. Use `TargetingDocumentInterface::getTargetGroupSpecificEditableNames` instead.
    - `TargetingDocumentInterface::getTargetGroupSpecificElementNames`. Use `TargetingDocumentInterface::getTargetGroupSpecificEditableNames` instead.
    
- The legacy editable naming scheme has been deprecated and will be removed in Pimcore 10. Please migrate to the new naming scheme. 
- All classes in namespace `Pimcore\Document\Tag\NamingStrategy` are marked as deprecated and will be removed in Pimcore 10. 
- `TagHandlerInterface` and `DelegatingTagHandler` are marked as deprecated and will be removed in Pimcore 10.
## 6.6.4
- If you are using the specific settings 'max. items' option for ObjectBricks & Fieldcollections on your class definition, then API will validate the max limit on save() calls from now on.

## 6.6.2
- class `ElementDescriptor` has been moved from 'Pimcore\Model\Version' to 'Pimcore\Model\Element'.
The BC layer will be removed in Pimcore 10. Use the following [migration scripts](https://gist.github.com/weisswurstkanone/a63f733fe58930778f41c695f862724a) to migrate your version and recyclebin files
if necessary.   

## 6.6.0
- Default config for monolog handler `main` in prod environment is now `stream` instead of `fingers_crossed`. If you still want to use `fingers_crossed` please update your project config accordingly. 
- `app` migration set is now located in `app/Migrations` instead of `app/Resources/migrations` - Pimcore will automatically move existing migration scripts for you (update your VCS!)
- Replaced `html2text` from [Martin Bayer] with `Html2Text\Html2Text` library. `Pimcore\Mail::determineHtml2TextIsInstalled`, `Pimcore\Mail::getHtml2TextBinaryEnabled`, `Pimcore\Mail::enableHtml2textBinary`, are deprecated in favour of new library and will be removed in Pimcore 10. Also, `Pimcore\Mail::setHtml2TextOptions` now accepts array options instead of string.
- Ecommerce: interpreter getters in the application which do not return the correct type: a string or integer field may receive "false" - if false was returned which should actually be null, see [#5876](https://github.com/pimcore/pimcore/pull/5876)
- Dirty detection `\Pimcore\Model\DataObject\DirtyIndicatorInterface` & `\Pimcore\Model\DataObject\Traits\DirtyIndicatorTrait` is deprecated and will be removed in Pimcore 10. Please use new interface `\Pimcore\Model\Element\DirtyIndicatorInterface` and trait `\Pimcore\Model\Element\Traits\DirtyIndicatorTrait` instead.
- Image thumbnails using any (P)JPEG/AUTO format will now all use `.jpg` as file extension (used to be `.jpeg` or `.pjpeg`). 
You can delete all existing `.pjpeg` and `.jpeg` thumbnails as they are not getting used anymore (`.jpg` files will be newly generated). 
You can use the following command to delete the obsolete files: `find web/var/tmp/image-thumbnails/ -type f \( -iname \*.jpeg -o -iname \*.pjpeg \) -delete`   
If you're using pre-generation for your thumbnails, don't forget to run the command (e.g. `./bin/console pimcore:thumbnails:image ...`). 

- [Workflows] Added new option `save_version` to changePublishedState under transitions configuration for documents and objects to save only version while transition from places. e.g.
    ```yml
    transitions:
        start_work:
            from: 'todo'
            to: ['edit_text', 'edit_images']
            options:
                label: 'Start Work'
                changePublishedState: save_version
    ```
## 6.5.2
- Passing multiple relations (w/o multiple assignments check) in data objects is deprecated and will throw exception in Pimcore 10.

## 6.5.0

> **IMPORTANT!**  
> If you are using the config option 'Cache Raw Relation Data' on your class definition, please run the following script
> prior to the update, or disable the option manually in your class definitions

```php
use Pimcore\Model\DataObject\ClassDefinition;

$list = new ClassDefinition\Listing();
$list = $list->load();

foreach ($list as $class) {
    if (method_exists($class, 'getCacheRawRelationData') && $class->getCacheRawRelationData()) {
        $class->setCacheRawRelationData(false);
        // get rid of the CacheRawRelationDataInterface & CacheRawRelationDataTrait
        $class->save();
    }
}
```

- [Data Objects] Relations are always lazy-loaded from now on
  see [5772](https://github.com/pimcore/pimcore/issues/5772)
- [Data Objects] Relation Types DB Caching Layer is always turned on now. Removed support for non-cached alternative. 
  All rows of the affected `object_relation_` table will be fetched in one go and cached together with the object. 
  see [5427](https://github.com/pimcore/pimcore/issues/5427)
- [Data Objects] If you have custom lazy-loaded datatypes **not** extending `Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations`,
  implement the `Pimcore\Model\DataObject\ClassDefinition\Data\LazyLoadingSupportInterface`
  The `method_exists('getLazyLoading')` calls will be removed in Pimcore 10.
- It is now possible to configure `php:memory_limit` for `web2print:pdf-creation` command with following configuration:
```yaml
pimcore:
  documents: 
    web_to_print: 
      pdf_creation_php_memory_limit: '2048M'
```

- Using static methods for [dynamic text labels](../../05_Objects/01_Object_Classes/03_Layout_Elements/01_Dynamic_Text_Labels.md) is now deprecated, use services instead.
- Removed method `\Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations::isRemoteOwner()`, as this method was only used for `ReverseManyToManyObjectRelation` internal check are now made using `instanceof` 
- [Data Objects] inheritance skips now objects of other classes (so far only folders) so with an object path like `A (class Product) > B (other class) > C (class Product)` object C can inherit data from A.
- The built in cookie info bar (in system settings) is now marked as deprecated and will be removed in Pimcore 10. 
- `\Pimcore\Config::getSystemConfig()` is now marked as deprecated and will be removed in Pimcore 10. Use `Pimcore\Config` service or `\Pimcore\Config::getSystemConfiguration()` method instead.
- Javascript function `ts(key)` (alias of `t(key)`) is marked as deprecated and will be removed in Pimcore 10. Please use `t(key)` instead. 

## 6.4.0
- Deprecated the REST Webservice API. The API will be removed in Pimcore 10, use the [Pimcore Datahub](https://github.com/pimcore/data-hub) instead.
- Removed `Pimcore\Bundle\EcommerceFrameworkBundle\PricingManagerPricingManagerInterface::getRule()` and `Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager::getRule()`
- The `DocumentRenderer::setEventDispatcher()` method has been removed. Pass event dispatcher to the constructor instead.
- `RedirectHandler::setRequestHelper()` and `RedirectHandler::setSiteResolver()` methods have been removed. Pass instance of `Pimcore\Http\RequestHelper` & `Pimcore\Http\Request\Resolver\SiteResolver` to the constructor instead.
- The `ContainerService::setEventDispatcher()` method has been removed and DocumentRenderer event listeners moved to`Pimcore\Bundle\CoreBundle\EventListener\FrontendDocumentRendererListener`
- Ecommerce: max length of `cartId` is now `190` characters instead of `255`
- MaxMind GeoIP database is **not** updated automatically anymore, please read the [instructions](../../18_Tools_and_Features/37_Targeting_and_Personalization/README.md) for setting up geo support for targeting.

- System Settings - Full Page Cache configuration changed from
    ```yaml
      pimcore:
        cache:
          ...
    ```
    to 
    ```yaml
    pimcore:
      full_page_cache:
          ...
    ```
    in system.yml to avoid conflicts between output and data cache [#5369](https://github.com/pimcore/pimcore/issues/5369). If you are using custom config files then you have to migrate them manually. Also new config `pimcore:full_page_cache` is disabled by default, so you have to enable fullpage cache again in system settings.
- Properties `$children`, `$hasChildren`, `$siblings`, `$hasSiblings` in `Pimcore\Model\Document` & `$o_children`, `$o_hasChildren`, `$o_siblings`, `$o_hasSiblings` in `Pimcore\Model\AbstractObject` uses array to cache result.

## 6.3.0
- Asset Metadata: character `~` is not allowed anymore for (predefined/custom) metadata naming. All existing and new metadata name with '~' converts to '---'. This change is introduced to support Localized columns in asset grid [#5093](https://github.com/pimcore/pimcore/pull/5093)
- Custom document editables now have to implement the method `isEmpty()` which is defined on `\Pimcore\Model\Document\Tag\TagInterface`
- Grid helper functions are moved from `bundles/AdminBundle/Resources/public/js/pimcore/object/helpers/gridcolumnconfig.js(removed)` to `bundles/AdminBundle/Resources/public/js/pimcore/element/helpers/gridColumnConfig.js`

#### Removed jQuery from Admin UI & E-Commerce Back Office
[BC Break] Replaced jQuery functions & libraries with vanilla JS or ExtJS equivalents. [Read more](https://github.com/pimcore/pimcore/pull/5222#issuecomment-552452543)  
To get jQuery back in the admin UI, please use the [this code snippet](https://gist.github.com/brusch/73b3afda260550718298630579dc2d06).

The following files have been removed:
  ```
  bundles/AdminBundle/Resources/public/js/lib/jquery-3.4.1.min.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/jquery-1.11.1.min.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/jquery-2.1.3.min.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/jquery-3.4.1.min.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/bootstrap4/js/bootstrap.bundle.min.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/bootstrap4/js/bootstrap.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/bootstrap4/js/bootstrap.min.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/pickadate.classic.css
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/pickadate.classic.date.css
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/pickadate.classic.time.css
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/picker.date.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/picker.date.v3.5.3.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/picker.js
  bundles/EcommerceFrameworkBundle/Resources/public/vendor/picker.v3.5.3.js
  ```

## 6.2.2
- Object Keys: characters `>` and `<` not allowed anymore.

## 6.2.0 
- Support for links and folders as a fallback document, details see [#4860](https://github.com/pimcore/pimcore/pull/4860)
- Documents & DataObjects: save button (visible when user has no publish permission) does not unpublish element anymore (if user has unpublish permission). 
  Details see [#4905](https://github.com/pimcore/pimcore/issues/4905)


### Workflow Refactorings
- Notifications for workflows now support Pimcore notifications. Due to that, some namespaces
  were renamed. If you don't have overwritten any of the internal classes, no action is needed. 
   - `Pimcore\Workflow\EventSubscriber\NotificationEmailSubscriber` became `Pimcore\Workflow\EventSubscriber\NotificationSubscriber`  
   - `Pimcore\Workflow\NotificationEmail\NotificationEmailInterface` became `Pimcore\Workflow\NotificationEmail\NotificationInterface` 


## 6.1.2
- Sessions: the native PHP session mechanism is now the default (instead of `session.handler.native_file`). 
To use the former handler use the following config: 
```yaml
framework:
    session:
        handler_id:  session.handler.native_file
        save_path:   "%kernel.project_dir%/../var/sessions"
```
If you have configured your own session handler nothing will change. 

- Bugfix for 6.1.0 - only relevant, when you directly implement interfaces. If you just extend existing E-Commerce Framework
   implementations, default implementations for the new methods are provided.
   - New method in `CartFactoryInterface`: `public function getCartReadOnlyMode(): string;` - default implementation in `CartFactory` available.
   
## 6.1.0 

### E-Commerce Framework Refactorings
- New methods in interfaces - only relevant, when you directly implement interfaces. If you just extend existing E-Commerce Framework
  implementations, default implementations for the new methods are provided.
  - New method in `EcommerceFrameworkBundle\EnvironmentInterface`: `public function setDefaultCurrency(Currency $currency);` - default implementation in `Environment` available.
  - New method in `CartInterface`: `public function getPricingManagerTokenInformationDetails(): array` - default implementation in `AbstractCart` available.
  - New method in `CartInterface`: `public function isCalculated(): bool` - default implementation in `AbstractCart` available.
  - New method in `CartPriceCalculatorInterface`: `public function getAppliedPricingRules(): array;` - default implementation in `CartPriceCalculator` available.
  - New method in `BracketInterface`: `public function getConditionsByType(string $typeClass): array;` - default implementation in `Bracket` available.
  - New method in `RuleInterface`: `public function getConditionsByType(string $typeClass): array` - default implementation in `Rule` available.
  - New method in `VoucherServiceInterface`: `public function getPricingManagerTokenInformationDetails(CartInterface $cart, string $locale = null): array;` - default implementation in `DefaultService` available.
  - New method in `CartFactoryInterface`: `public function getCartReadOnlyMode(): string;` - default implementation in `CartFactory` available.
  
- Changed return type of `applyCartRules(CartInterface $cart)` in `PricingManagerInterface` - from `PricingManagerInterface` to `array`  
- Introduction of new Checkout Manager architecture. It is parallel to the old architecture, which is deprecated now and will be removed in 
  Pimcore 10. For details see [Checkout Manager Details](../../10_E-Commerce_Framework/13_Checkout_Manager/08_Checkout_Manager_Details.md).

### E-Commerce Back Office
- Following views are migrated from .php to .twig (with snake_case naming)
 ```
 bundles/EcommerceFrameworkBundle/Resources/views/AdminOrder/detail.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/AdminOrder/itemCancel.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/AdminOrder/itemComplaint.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/AdminOrder/itemEdit.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/AdminOrder/list.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Includes/paging.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/back-office.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/voucherCodeTabError.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/voucherCodeTabPattern.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/voucherCodeTabSingle.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/paginator.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/statistics.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/usageStatisticScript.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/modals/cleanupReservationsModal.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/modals/pattern/cleanupModal.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/modals/pattern/generateModal.html.php
 bundles/EcommerceFrameworkBundle/Resources/views/Voucher/parts/modals/single/assignSettingsModal.html.php
 
 ```
 
 ### Link Editable
 - Link Editables no longer apply configured classes to the editable container. If you have custom css relying on this classes you have to adopt it. see [#4740](https://github.com/pimcore/pimcore/issues/4740)
