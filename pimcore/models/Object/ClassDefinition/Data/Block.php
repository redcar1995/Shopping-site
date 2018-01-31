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
 * @package    Object|Class
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Object\ClassDefinition\Data;

use Pimcore\Db;
use Pimcore\Model;
use Pimcore\Model\Element;
use Pimcore\Model\Object;
use Pimcore\Tool\Serialize;
use Pimcore\Logger;

class Block extends Model\Object\ClassDefinition\Data
{
    use Element\ChildsCompatibilityTrait;

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = "block";

    /**
     * @var bool
     */
    public $lazyLoading;

    /**
     * @var boolean
     */
    public $disallowAddRemove;

    /**
     * @var boolean
     */
    public $disallowReorder;

    /**
     * @var boolean
     */
    public $collapsible;

    /**
     * @var boolean
     */
    public $collapsed;

    /**
     * Type for the column to query
     *
     * @var string
     */
    public $queryColumnType = "longtext";

    /**
     * Type for the column
     *
     * @var string
     */
    public $columnType = "longtext";

    /**
     * @var string
     */
    public $styleElement = "";

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = "\\Pimcore\\Model\\Object\\Data\\Block";

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
     * @var array
     */
    protected $referencedFields = [];

    /**
     * @var array
     */
    public $fieldDefinitionsCache;


    /**
     * @see Object\ClassDefinition\Data::getDataForResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataForResource($data, $object = null, $params = [])
    {
        $result = [];

        if (is_array($data)) {
            foreach ($data as $blockElements) {
                $resultElement = [];

                /**
                 * @var  $blockElement Object\Data\BlockElement
                 */
                foreach ($blockElements as $elementName => $blockElement) {
                    /** @var  $fd Object\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn("class definition seems to have changed, element name: " . $elementName);
                        continue;
                    }
                    $elementData = $blockElement->getData();
                    $dataForResource = $fd->marshal($elementData, $object, ["raw" => true, "blockmode" => true]);
//                    $blockElement->setData($fd->unmarshal($dataForResource, $object, ["raw" => true]));

                    // do not serialize the block element itself
                    $resultElement[$elementName] = [
                        "name" => $blockElement->getName(),
                        "type" => $blockElement->getType(),
                        "data" => $dataForResource
                    ];
                }
                $result[] = $resultElement;
            }
        }
        $result = Serialize::serialize($result);

        return $result;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataFromResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataFromResource($data, $object = null, $params = [])
    {
        if ($data) {
            $count = 0;

            $unserializedData = unserialize($data);
            $result = [];

            foreach ($unserializedData as $blockElements) {
                $items = [];
                /** @var  $blockElement Object\Data\BlockElement */
                foreach ($blockElements as $elementName => $blockElementRaw) {

                    /** @var  $fd Object\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn("class definition seems to have changed, element name: " . $elementName);
                        continue;
                    }

                    // do not serialize the block element itself
//                    $elementData = $blockElement->getData();
                    $elementData = $blockElementRaw["data"];

                    $dataFromResource = $fd->unmarshal($elementData, $object, ["raw" => true, "blockmode" => true]);
                    $blockElementRaw["data"] = $dataFromResource;

                    if ($blockElementRaw["type"] == "localizedfields") {
                        /** @var  $data Object\Localizedfield */
                        $data = $blockElementRaw["data"];
                        if ($data) {
                            $data->setObject($object);
                            $data->setContext(['containerType' => 'block',
                                               'fieldname' => $this->getName(),
                                               'index' => $count,
                                               'containerKey' => $this->getName(),
                                               'classId' => $object ? $object->getClassId() : null]);
                            $blockElementRaw["data"] = $data;
                        }
                    }
                    $blockElement = new Object\Data\BlockElement($blockElementRaw["name"], $blockElementRaw["type"], $blockElementRaw["data"]);
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
     * @see Object\ClassDefinition\Data::getDataForQueryResource
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataForQueryResource($data, $object = null, $params = [])
    {
        return null;
    }

    /**
     * @see Object\ClassDefinition\Data::getDataForEditmode
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
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
                 * @var  $blockElement Object\Data\BlockElement
                 */
                foreach ($blockElements as $elementName => $blockElement) {
                    /** @var  $fd Object\ClassDefinition\Data */
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn("class definition seems to have changed, element name: " . $elementName);
                        continue;
                    }
                    $elementData = $blockElement->getData();
                    $params['context']['containerType'] = 'block';
                    $dataForEditMode = $fd->getDataForEditmode($elementData, $object, $params);
                    $resultElement[$elementName] = $dataForEditMode;
                }
                $result[] = [
                    "oIndex" => $idx,
                    "data" => $resultElement
                ];
            }
        }

        return $result;
    }

    /**
     * @see Model\Object\ClassDefinition\Data::getDataFromEditmode
     * @param string $data
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        $result = [];
        $count = 0;

        foreach ($data as $rawBlockElement) {
            $resultElement = [];

            $oIndex = $rawBlockElement["oIndex"];
            $blockElement = $rawBlockElement["data"];

            foreach ($blockElement as $elementName => $elementData) {

                /** @var  $fd Object\ClassDefinition\Data */
                $fd = $this->getFielddefinition($elementName);
                $dataFromEditMode = $fd->getDataFromEditmode($elementData, $object,
                    [
                        "context" => [
                            "containerType" => "block",
                            "fieldname" => $this->getName(),
                            "index" => $count,
                            "oIndex" => $oIndex,
                            "classId" => $object->getClassId()
                        ]
                    ]
                );

                $elementType = $fd->getFieldtype();

                $resultElement[$elementName] = new Object\Data\BlockElement($elementName, $elementType, $dataFromEditMode);
            }

            $result[] = $resultElement;
            $count++;
        }

        return $result;
    }

    /**
     * @see Object\ClassDefinition\Data::getVersionPreview
     * @param string $data
     * @param null|Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getVersionPreview($data, $object = null, $params = [])
    {
        return "not supported";
    }


    /**
     * converts object data to a simple string value or CSV Export
     * @abstract
     * @param Object\AbstractObject $object
     * @param array $params
     * @return string
     */
    public function getForCsvExport($object, $params = [])
    {
        return null;
    }

    /**
     * @param $importValue
     * @param null|Model\Object\AbstractObject $object
     * @param mixed $params
     * @return string
     */
    public function getFromCsvImport($importValue, $object = null, $params = [])
    {
        return null;
    }


    /**
     * converts data to be exposed via webservices
     * @param string $object
     * @param mixed $params
     * @return mixed
     */
    public function getForWebserviceExport($object, $params = [])
    {
        return "not supported yet";
    }


    /**
     * @param mixed $value
     * @param null $relatedObject
     * @param mixed $params
     * @param null $idMapper
     * @return mixed|void
     * @throws \Exception
     */
    public function getFromWebserviceImport($value, $relatedObject = null, $params = [], $idMapper = null)
    {
        // do nothing
    }


    /** True if change is allowed in edit mode.
     * @param string $object
     * @param mixed $params
     * @return bool
     */
    public function isDiffChangeAllowed($object, $params = [])
    {
        return true;
    }

    /** Generates a pretty version preview (similar to getVersionPreview) can be either html or
     * a image URL. See the ObjectMerger plugin documentation for details
     * @param $data
     * @param null $object
     * @param mixed $params
     * @return array|string
     */
    public function getDiffVersionPreview($data, $object = null, $params = [])
    {
        if ($data) {
            return "not supported";
        }
    }


    /**
     * @param Model\Object\ClassDefinition\Data $masterDefinition
     */
    public function synchronizeWithMasterDefinition(Model\Object\ClassDefinition\Data $masterDefinition)
    {
        $this->disallowAddRemove = $masterDefinition->disallowAddRemove;
        $this->disallowReorder = $masterDefinition->disallowReorder;
        $this->collapsible = $masterDefinition->collapsible;
        $this->collapsed = $masterDefinition->collapsed;
    }

    /**
     * @param Object\Data\ExternalImage $data
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
     * @return $this
     */
    public function setChildren($children)
    {
        $this->childs = $children;
        $this->fieldDefinitionsCache = null;

        return $this;
    }

    /**
     * @return boolean
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
     * @return $this
     */
    public function setValues($data = [], $blockedKeys = [])
    {
        foreach ($data as $key => $value) {
            if (!in_array($key, $blockedKeys)) {
                $method = "set" . $key;
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
     * @return array
     */
    public function doGetFieldDefinitions($def = null, $fields = [])
    {
        if ($def === null) {
            $def = $this->getChilds();
        }

        if (is_array($def)) {
            foreach ($def as $child) {
                $fields = array_merge($fields, $this->doGetFieldDefinitions($child, $fields));
            }
        }

        if ($def instanceof Object\ClassDefinition\Layout) {
            if ($def->hasChilds()) {
                foreach ($def->getChilds() as $child) {
                    $fields = array_merge($fields, $this->doGetFieldDefinitions($child, $fields));
                }
            }
        }

        if ($def instanceof Object\ClassDefinition\Data) {
            $fields[$def->getName()] = $def;
        }

        return $fields;
    }

    /**
     * @return array
     */
    public function getFieldDefinitions()
    {
        if (empty($this->fieldDefinitionsCache)) {
            $definitions = $this->doGetFieldDefinitions();
            foreach ($this->getReferencedFields() as $rf) {
                if ($rf instanceof Object\ClassDefinition\Data\Localizedfields) {
                    $definitions = array_merge($definitions, $this->doGetFieldDefinitions($rf->getChilds()));
                }
            }

            $this->fieldDefinitionsCache = $definitions;
        }

        return $this->fieldDefinitionsCache;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getFielddefinition($name)
    {
        $fds = $this->getFieldDefinitions();
        if (isset($fds[$name])) {
            return $fds[$name];
        }

        return;
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
                    Logger::warn("class definition seems to have changed, element name: " . $elementName);
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
                    Logger::warn("class definition seems to have changed, element name: " . $elementName);
                    continue;
                }
                $data = $blockElement->getData();

                $tags = $fd->getCacheTags($data, $tags);
            }
        }

        return $tags;
    }

    /**
     * @return boolean
     */
    public function isCollapsed()
    {
        return $this->collapsed;
    }

    /**
     * @param boolean $collapsed
     */
    public function setCollapsed($collapsed)
    {
        $this->collapsed = $collapsed;
    }

    /**
     * @return boolean
     */
    public function isCollapsible()
    {
        return $this->collapsible;
    }

    /**
     * @param boolean $collapsible
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
        if ($object instanceof Object\Concrete) {
            if ($this->getLazyLoading() and !in_array($this->getName(), $object->getO__loadedLazyFields())) {
                $object->addO__loadedLazyField($this->getName());
            }
        }

        return $data;
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

        if ($container instanceof Object\Concrete) {
            if (!method_exists($this, 'getLazyLoading') or !$this->getLazyLoading() or (array_key_exists('force', $params) && $params['force'])) {
                $data = null;

                $query = 'select ' . $field . ' from object_store_' . $container->getClassId() . ' where oo_id  = ' . $container->getId();
                $data = $db->fetchOne($query);
                $data = $this->getDataFromResource($data, $container, $params);
            } else {
                return null;
            }
        } elseif ($container instanceof Object\Localizedfield) {
            $context = $params['context'];
            $object = $context['object'];

            if ($context && $context['containerType'] == 'fieldcollection') {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_collection_' . $context['containerKey'] . '_localized_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId()  . ' and fieldname = ' . $db->quote($context['fieldname']) . ' and `index` =  ' . $context['index'];
            } else {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_localized_data_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId();
            }
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $container, $params);
        } elseif ($container instanceof Object\Objectbrick\Data\AbstractData) {
            $context = $params['context'];

            $object = $context['object'];
            $brickType = $context['containerKey'];
            $brickField = $context['brickField'];
            $fieldname = $context['fieldname'];
            $query = 'select ' . $db->quoteIdentifier($brickField) . ' from object_brick_store_' . $brickType . '_' . $object->getClassId()
                . ' where  o_id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($fieldname);
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $container, $params);
        } elseif ($container instanceof Object\Fieldcollection\Data\AbstractData) {
            $context = $params['context'];
            $collectionType = $context['containerKey'];
            $object = $context['object'];
            $fcField = $context['fieldname'];

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
     *
     * @return array|mixed|null
     */
    public function preGetData($object, $params = [])
    {
        $data = null;
        if ($object instanceof Object\Concrete) {
            $data = $object->{$this->getName()};
            if ($this->getLazyLoading() and !in_array($this->getName(), $object->getO__loadedLazyFields())) {
                $data = $this->load($object, ['force' => true]);

                $setter = 'set' . ucfirst($this->getName());
                if (method_exists($object, $setter)) {
                    $object->$setter($data);
                }
            }
        } elseif ($object instanceof Object\Localizedfield) {
            $data = $params['data'];
        } elseif ($object instanceof Object\Fieldcollection\Data\AbstractData) {
            $data = $object->{$this->getName()};
        } elseif ($object instanceof Object\Objectbrick\Data\AbstractData) {
            $data = $object->{$this->getName()};
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
}
