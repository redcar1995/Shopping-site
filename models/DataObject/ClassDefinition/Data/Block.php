<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\ClassDefinition\Data;

use Pimcore\Db;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\Element;
use Pimcore\Tool\Serialize;

class Block extends Data implements CustomResourcePersistingInterface, ResourcePersistenceAwareInterface
{
    use Element\ChildsCompatibilityTrait;
    use Extension\ColumnType;

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = 'block';

    /**
     * @var bool
     */
    public $lazyLoading;

    /**
     * @var bool
     */
    public $disallowAddRemove;

    /**
     * @var bool
     */
    public $disallowReorder;

    /**
     * @var bool
     */
    public $collapsible;

    /**
     * @var bool
     */
    public $collapsed;

    /**
     * @var int
     */
    public $maxItems;

    /**
     * Type for the column
     *
     * @var string
     */
    public $columnType = 'longtext';

    /**
     * @var string
     */
    public $styleElement = '';

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = '\\Pimcore\\Model\\DataObject\\Data\\BlockElement[][]';

    /**
     * @var array
     */
    public $childs = [];

    /**
     * @var string
     */
    public $layout;

    /**
     * contains further child field definitions if there are more than one localized fields in on class
     *
     * @var array
     */
    protected $referencedFields = [];

    /**
     * @var array
     */
    public $fieldDefinitionsCache;

    /**
     * @see ResourcePersistenceAwareInterface::getDataForResource
     *
     * @param array $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataForResource($data, $object = null, $params = [])
    {
        $result = [];

        if (is_array($data)) {
            foreach ($data as $blockElements) {
                $resultElement = [];

                /**
                 * @var  $blockElement DataObject\Data\BlockElement
                 */
                foreach ($blockElements as $elementName => $blockElement) {
                    /** @var $fd DataObject\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                        continue;
                    }
                    $elementData = $blockElement->getData();
                    $dataForResource = $fd->marshal($elementData, $object, ['raw' => true, 'blockmode' => true]);
                    //                    $blockElement->setData($fd->unmarshal($dataForResource, $object, ["raw" => true]));

                    // do not serialize the block element itself
                    $resultElement[$elementName] = [
                        'name' => $blockElement->getName(),
                        'type' => $blockElement->getType(),
                        'data' => $dataForResource
                    ];
                }
                $result[] = $resultElement;
            }
        }
        $result = Serialize::serialize($result);

        return $result;
    }

    /**
     * @see ResourcePersistenceAwareInterface::getDataFromResource
     *
     * @param string $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return array|null
     */
    public function getDataFromResource($data, $object = null, $params = [])
    {
        if ($data) {
            $count = 0;

            $unserializedData = Serialize::unserialize($data);
            $result = [];

            foreach ($unserializedData as $blockElements) {
                $items = [];
                /** @var $blockElement DataObject\Data\BlockElement */
                foreach ($blockElements as $elementName => $blockElementRaw) {

                    /** @var $fd DataObject\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                        continue;
                    }

                    // do not serialize the block element itself
                    //                    $elementData = $blockElement->getData();
                    $elementData = $blockElementRaw['data'];

                    $dataFromResource = $fd->unmarshal($elementData, $object, ['raw' => true, 'blockmode' => true]);
                    $blockElementRaw['data'] = $dataFromResource;

                    if ($blockElementRaw['type'] == 'localizedfields') {
                        /** @var $data DataObject\Localizedfield */
                        $data = $blockElementRaw['data'];
                        if ($data) {
                            $data->setObject($object);
                            $data->setContext(['containerType' => 'block',
                                'fieldname' => $this->getName(),
                                'index' => $count,
                                'containerKey' => $this->getName(),
                                'classId' => $object ? $object->getClassId() : null]);
                            $blockElementRaw['data'] = $data;
                        }
                    }
                    $blockElement = new DataObject\Data\BlockElement($blockElementRaw['name'], $blockElementRaw['type'], $blockElementRaw['data']);

                    if (isset($params['owner'])) {
                        $blockElement->setOwner($params['owner'], $params['fieldname'], $params['language']);
                    }

                    $items[$elementName] = $blockElement;
                }
                $result[] = $items;
                $count++;
            }

            return $result;
        }

        return null;
    }

    /**
     * @see Data::getDataForEditmode
     *
     * @param string $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        $params = (array)$params;
        $result = [];
        $idx = -1;

        if (is_array($data)) {
            foreach ($data as $blockElements) {
                $resultElement = [];
                $idx++;

                /**
                 * @var  $blockElement DataObject\Data\BlockElement
                 */
                foreach ($blockElements as $elementName => $blockElement) {
                    /** @var $fd DataObject\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                        continue;
                    }
                    $elementData = $blockElement->getData();
                    $params['context']['containerType'] = 'block';
                    $dataForEditMode = $fd->getDataForEditmode($elementData, $object, $params);
                    $resultElement[$elementName] = $dataForEditMode;
                }
                $result[] = [
                    'oIndex' => $idx,
                    'data' => $resultElement
                ];
            }
        }

        return $result;
    }

    /**
     * @see Data::getDataFromEditmode
     *
     * @param array $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        $result = [];
        $count = 0;

        foreach ($data as $rawBlockElement) {
            $resultElement = [];

            $oIndex = $rawBlockElement['oIndex'];
            $blockElement = $rawBlockElement['data'];

            foreach ($blockElement as $elementName => $elementData) {

                /** @var $fd DataObject\ClassDefinition\Data */
                $fd = $this->getFielddefinition($elementName);
                $dataFromEditMode = $fd->getDataFromEditmode(
                    $elementData,
                    $object,
                    [
                        'context' => [
                            'containerType' => 'block',
                            'fieldname' => $this->getName(),
                            'index' => $count,
                            'oIndex' => $oIndex,
                            'classId' => $object->getClassId()
                        ]
                    ]
                );

                $elementType = $fd->getFieldtype();

                $resultElement[$elementName] = new DataObject\Data\BlockElement($elementName, $elementType, $dataFromEditMode);
            }

            $result[] = $resultElement;
            $count++;
        }

        return $result;
    }

    /**
     * @see Data::getVersionPreview
     *
     * @param string $data
     * @param null|DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return string
     */
    public function getVersionPreview($data, $object = null, $params = [])
    {
        return 'not supported';
    }

    /**
     * converts object data to a simple string value or CSV Export
     *
     * @abstract
     *
     * @param DataObject\AbstractObject $object
     * @param array $params
     *
     * @return string
     */
    public function getForCsvExport($object, $params = [])
    {
        return null;
    }

    /**
     * @param $importValue
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return string
     */
    public function getFromCsvImport($importValue, $object = null, $params = [])
    {
        return null;
    }

    /**
     * converts data to be exposed via webservices
     *
     * @param string $object
     * @param mixed $params
     *
     * @return mixed
     */
    public function getForWebserviceExport($object, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);
        $result = [];
        $idx = -1;

        if (is_array($data)) {
            foreach ($data as $blockElements) {
                $resultElement = [];
                $idx++;

                /**
                 * @var  $blockElement DataObject\Data\BlockElement
                 */
                foreach ($blockElements as $elementName => $blockElement) {
                    /** @var $fd DataObject\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                        continue;
                    }

                    $params['context']['containerType'] = 'block';
                    $params['injectedData'] = $blockElement->getData();
                    $dataForEditMode = $fd->getForWebserviceExport($object, $params);
                    $resultElement[$elementName] = $dataForEditMode;
                }
                $result[] = $resultElement;
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @param null $relatedObject
     * @param mixed $params
     * @param null $idMapper
     *
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function getFromWebserviceImport($value, $relatedObject = null, $params = [], $idMapper = null)
    {
        $result = [];

        if (is_array($value)) {
            foreach ($value as $blockElementsData) {
                $resultElement = [];

                /**
                 * @var  $blockElement DataObject\Data\BlockElement
                 */
                foreach ($blockElementsData as $elementName => $blockElementDataRaw) {

                    /** @var $fd DataObject\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                        continue;
                    }

                    $data = $fd->getFromWebserviceImport($blockElementDataRaw, $relatedObject, $params, $idMapper);
                    $blockElement = new DataObject\Data\BlockElement($elementName, $fd->getFieldtype(), $data);

                    $resultElement[$elementName] = $blockElement;
                }
                $result[] = $resultElement;
            }
        }

        return $result;
    }

    /** True if change is allowed in edit mode.
     * @param string $object
     * @param mixed $params
     *
     * @return bool
     */
    public function isDiffChangeAllowed($object, $params = [])
    {
        return true;
    }

    /** Generates a pretty version preview (similar to getVersionPreview) can be either html or
     * a image URL. See the ObjectMerger plugin documentation for details
     *
     * @param $data
     * @param null $object
     * @param mixed $params
     *
     * @return array|string
     */
    public function getDiffVersionPreview($data, $object = null, $params = [])
    {
        if ($data) {
            return 'not supported';
        }
    }

    /**
     * @param Model\DataObject\ClassDefinition\Data $masterDefinition
     */
    public function synchronizeWithMasterDefinition(Model\DataObject\ClassDefinition\Data $masterDefinition)
    {
        $this->disallowAddRemove = $masterDefinition->disallowAddRemove;
        $this->disallowReorder = $masterDefinition->disallowReorder;
        $this->collapsible = $masterDefinition->collapsible;
        $this->collapsed = $masterDefinition->collapsed;
    }

    /**
     * @param DataObject\Data\ExternalImage $data
     *
     * @return bool
     */
    public function isEmpty($data)
    {
        if (is_null($data) || count($data) == 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        return $this->childs;
    }

    /**
     * @param array $children
     *
     * @return $this
     */
    public function setChildren($children)
    {
        $this->childs = $children;
        $this->fieldDefinitionsCache = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        if (is_array($this->childs) && count($this->childs) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $child
     */
    public function addChild($child)
    {
        $this->childs[] = $child;
        $this->fieldDefinitionsCache = null;
    }

    /**
     * @param $layout
     *
     * @return $this
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;

        return $this;
    }

    /**
     * @return string
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * @param mixed $data
     * @param array $blockedKeys
     *
     * @return $this
     */
    public function setValues($data = [], $blockedKeys = [])
    {
        foreach ($data as $key => $value) {
            if (!in_array($key, $blockedKeys)) {
                $method = 'set' . $key;
                if (method_exists($this, $method)) {
                    $this->$method($value);
                }
            }
        }

        return $this;
    }

    /**
     * @param null $def
     * @param array $fields
     *
     * @return array
     */
    public function doGetFieldDefinitions($def = null, $fields = [])
    {
        if ($def === null) {
            $def = $this->getChildren();
        }

        if (is_array($def)) {
            foreach ($def as $child) {
                $fields = array_merge($fields, $this->doGetFieldDefinitions($child, $fields));
            }
        }

        if ($def instanceof DataObject\ClassDefinition\Layout) {
            if ($def->hasChildren()) {
                foreach ($def->getChildren() as $child) {
                    $fields = array_merge($fields, $this->doGetFieldDefinitions($child, $fields));
                }
            }
        }

        if ($def instanceof DataObject\ClassDefinition\Data) {
            $fields[$def->getName()] = $def;
        }

        return $fields;
    }

    /**
     * @param array $context additional contextual data
     *
     * @return array
     */
    public function getFieldDefinitions($context = [])
    {
        if (empty($this->fieldDefinitionsCache)) {
            $definitions = $this->doGetFieldDefinitions();
            foreach ($this->getReferencedFields() as $rf) {
                if ($rf instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                    $definitions = array_merge($definitions, $this->doGetFieldDefinitions($rf->getChildren()));
                }
            }

            $this->fieldDefinitionsCache = $definitions;
        }

        if (isset($context['suppressEnrichment']) && $context['suppressEnrichment']) {
            return $this->fieldDefinitionsCache;
        }

        $enrichedFieldDefinitions = [];
        if (is_array($this->fieldDefinitionsCache)) {
            foreach ($this->fieldDefinitionsCache as $key => $fieldDefinition) {
                $fieldDefinition = $this->doEnrichFieldDefinition($fieldDefinition, $context);
                $enrichedFieldDefinitions[$key] = $fieldDefinition;
            }
        }

        return $enrichedFieldDefinitions;
    }

    /**
     * @param $name
     * @param array $context additional contextual data
     *
     * @return mixed
     */
    public function getFielddefinition($name, $context = [])
    {
        $fds = $this->getFieldDefinitions();
        if (isset($fds[$name])) {
            if (isset($context['suppressEnrichment']) && $context['suppressEnrichment']) {
                return $fds[$name];
            }
            $fieldDefinition = $this->doEnrichFieldDefinition($fds[$name], $context);

            return $fieldDefinition;
        }

        return;
    }

    public function doEnrichFieldDefinition($fieldDefinition, $context = [])
    {
        if (method_exists($fieldDefinition, 'enrichFieldDefinition')) {
            $context['containerType'] = 'block';
            $context['containerKey'] = $this->getName();
            $fieldDefinition = $fieldDefinition->enrichFieldDefinition($context);
        }

        return $fieldDefinition;
    }

    /**
     * @param array $referencedFields
     */
    public function setReferencedFields($referencedFields)
    {
        $this->referencedFields = $referencedFields;
    }

    /**
     * @return array
     */
    public function getReferencedFields()
    {
        return $this->referencedFields;
    }

    /**
     * @param $field
     */
    public function addReferencedField($field)
    {
        $this->referencedFields[] = $field;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $vars = get_object_vars($this);
        unset($vars['fieldDefinitionsCache']);
        unset($vars['referencedFields']);

        return array_keys($vars);
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function resolveDependencies($data)
    {
        $dependencies = [];

        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $blockElements) {
            foreach ($blockElements as $elementName => $blockElement) {
                $fd = $this->getFielddefinition($elementName);
                if (!$fd) {
                    // class definition seems to have changed
                    Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                    continue;
                }
                $elementData = $blockElement->getData();

                $dependencies = array_merge($dependencies, $fd->resolveDependencies($elementData));
            }
        }

        return $dependencies;
    }

    /**
     * This is a dummy and is mostly implemented by relation types
     *
     * @param mixed $data
     * @param array $tags
     *
     * @return array
     */
    public function getCacheTags($data, $tags = [])
    {
        $tags = is_array($tags) ? $tags : [];

        if ($this->getLazyLoading()) {
            return $tags;
        }

        if (!is_array($data)) {
            return $tags;
        }

        foreach ($data as $blockElements) {
            foreach ($blockElements as $elementName => $blockElement) {
                $fd = $this->getFielddefinition($elementName);
                if (!$fd) {
                    // class definition seems to have changed
                    Logger::warn('class definition seems to have changed, element name: ' . $elementName);
                    continue;
                }
                $data = $blockElement->getData();

                $tags = $fd->getCacheTags($data, $tags);
            }
        }

        return $tags;
    }

    /**
     * @return bool
     */
    public function isCollapsed()
    {
        return $this->collapsed;
    }

    /**
     * @param bool $collapsed
     */
    public function setCollapsed($collapsed)
    {
        $this->collapsed = $collapsed;
    }

    /**
     * @return bool
     */
    public function isCollapsible()
    {
        return $this->collapsible;
    }

    /**
     * @param bool $collapsible
     */
    public function setCollapsible($collapsible)
    {
        $this->collapsible = $collapsible;
    }

    /**
     * @return string
     */
    public function getStyleElement()
    {
        return $this->styleElement;
    }

    /**
     * @param string $styleElement
     *
     * @return $this
     */
    public function setStyleElement($styleElement)
    {
        $this->styleElement = $styleElement;

        return $this;
    }

    /**
     * @return bool
     */
    public function getLazyLoading()
    {
        return $this->lazyLoading;
    }

    /**
     * @param  $lazyLoading
     *
     * @return $this
     */
    public function setLazyLoading($lazyLoading)
    {
        $this->lazyLoading = $lazyLoading;

        return $this;
    }

    /**
     * @param $object
     * @param $data
     * @param array $params
     *
     * @return mixed
     */
    public function preSetData($object, $data, $params = [])
    {
        $this->markLazyloadedFieldAsLoaded($object);

        return $data;
    }

//
//    public function getTableName($container, $params = []) {
//        $db = Db::get();
//        $data = null;
//
//        if ($container instanceof DataObject\Concrete) {
//            return "object_store_" . $container->getClassId();
//        } elseif ($container instanceof DataObject\Fieldcollection\Data\AbstractData) {
//
//            //TODO
//        } elseif ($container instanceof DataObject\Localizedfield) {
//            //TODO
//        } elseif ($container instanceof DataObject\Objectbrick\Data\AbstractData) {
//            //TODO
//        }
//
//
//    }

    /**
     * @param $object
     * @param array $params
     */
    public function save($object, $params = [])
    {
    }

    /**
     * @param $object
     * @param array $params
     *
     * @return null
     */
    public function load($container, $params = [])
    {
        $field = $this->getName();
        $db = Db::get();

        if ($container instanceof DataObject\Concrete) {
            if (!method_exists($this, 'getLazyLoading') or !$this->getLazyLoading() or (array_key_exists('force', $params) && $params['force'])) {
                $data = null;

                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_store_' . $container->getClassId() . ' where oo_id  = ' . $container->getId();
                $data = $db->fetchOne($query);
                $data = $this->getDataFromResource($data, $container, $params);
            } else {
                return null;
            }
        } elseif ($container instanceof DataObject\Localizedfield) {
            $context = $params['context'];
            $object = $context['object'];

            if ($context && $context['containerType'] == 'fieldcollection') {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_collection_' . $context['containerKey'] . '_localized_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($context['fieldname']) . ' and `index` =  ' . $context['index'];
            } else {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_localized_data_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId();
            }
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $container, $params);
        } elseif ($container instanceof DataObject\Objectbrick\Data\AbstractData) {
            $context = $params['context'];

            $object = $context['object'];
            $brickType = $context['containerKey'];
            $brickField = $context['brickField'];
            $fieldname = $context['fieldname'];
            $query = 'select ' . $db->quoteIdentifier($brickField) . ' from object_brick_store_' . $brickType . '_' . $object->getClassId()
                . ' where  o_id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($fieldname);
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $container, $params);
        } elseif ($container instanceof DataObject\Fieldcollection\Data\AbstractData) {
            $context = $params['context'];
            $collectionType = $context['containerKey'];
            $object = $context['object'];
            $fcField = $context['fieldname'];

            //TODO index!!!!!!!!!!!!!!

            $query = 'select ' . $db->quoteIdentifier($field) . ' from object_collection_' . $collectionType . '_' . $object->getClassId()
                . ' where  o_id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($fcField) . ' and `index` = '. $context['index'];
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $container, $params);
        }

        return $data;
    }

    /**
     * @param $object
     * @param array $params
     */
    public function delete($object, $params = [])
    {
    }

    /**
     * @param $object
     * @param array $params
     *
     * @return array|mixed|null
     */
    public function preGetData($object, $params = [])
    {
        $data = null;
        if ($object instanceof DataObject\Concrete) {
            $data = $object->getObjectVar($this->getName());
            if ($this->getLazyLoading() and !in_array($this->getName(), $object->getO__loadedLazyFields())) {
                $data = $this->load($object, ['force' => true]);

                $setter = 'set' . ucfirst($this->getName());
                if (method_exists($object, $setter)) {
                    $object->$setter($data);
                    $this->markLazyloadedFieldAsLoaded($object);
                }
            }
        } elseif ($object instanceof DataObject\Localizedfield) {
            $data = $params['data'];
        } elseif ($object instanceof DataObject\Fieldcollection\Data\AbstractData) {
            $data = $object->getObjectVar($this->getName());
        } elseif ($object instanceof DataObject\Objectbrick\Data\AbstractData) {
            $data = $object->getObjectVar($this->getName());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @return bool
     */
    public function isRemoteOwner()
    {
        return false;
    }

    /**
     * @return int
     */
    public function getMaxItems()
    {
        return $this->maxItems;
    }

    /**
     * @param int $maxItems
     */
    public function setMaxItems($maxItems)
    {
        $this->maxItems = $maxItems;
    }

    /**
     * @return bool
     */
    public function isDisallowAddRemove()
    {
        return $this->disallowAddRemove;
    }

    /**
     * @param bool $disallowAddRemove
     */
    public function setDisallowAddRemove($disallowAddRemove)
    {
        $this->disallowAddRemove = $disallowAddRemove;
    }

    /**
     * @return bool
     */
    public function isDisallowReorder()
    {
        return $this->disallowReorder;
    }

    /**
     * @param bool $disallowReorder
     */
    public function setDisallowReorder($disallowReorder)
    {
        $this->disallowReorder = $disallowReorder;
    }

    /**
     * Checks if data is valid for current data field
     *
     * @param mixed $data
     * @param bool $omitMandatoryCheck
     *
     * @throws \Exception
     */
    public function checkValidity($data, $omitMandatoryCheck = false)
    {
        if (!$omitMandatoryCheck) {
            if (is_array($data)) {
                $blockDefinitions = $this->getFieldDefinitions();

                $validationExceptions = [];

                $idx = -1;
                foreach ($data as $item) {
                    $idx++;
                    if (!is_array($item)) {
                        continue;
                    }

                    foreach ($blockDefinitions as $fd) {
                        try {
                            $blockElement = $item[$fd->getName()];
                            if (!$blockElement) {
                                if ($fd->getMandatory()) {
                                    throw new Element\ValidationException('Block element empty [ '.$fd->getName().' ]');
                                } else {
                                    continue;
                                }
                            }

                            $data = $blockElement->getData();
                            $fd->checkValidity($data);
                        } catch (Model\Element\ValidationException $ve) {
                            $ve->addContext($this->getName() . '-' . $idx);
                            $validationExceptions[] = $ve;
                        }
                    }
                }

                if ($validationExceptions) {
                    $aggregatedExceptions = new Model\Element\ValidationException();
                    $aggregatedExceptions->setSubItems($validationExceptions);
                    throw $aggregatedExceptions;
                }
            }
        }
    }
}
