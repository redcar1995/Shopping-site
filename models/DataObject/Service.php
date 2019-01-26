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
 * @package    Object
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject;

use Pimcore\Cache\Runtime;
use Pimcore\DataObject\GridColumnConfig\ConfigElementInterface;
use Pimcore\DataObject\GridColumnConfig\Service as GridColumnConfigService;
use Pimcore\Db\ZendCompatibility\QueryBuilder;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Element;
use Pimcore\Tool\Admin as AdminTool;
use Pimcore\Tool\Session;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

/**
 * @method \Pimcore\Model\Element\Dao getDao()
 */
class Service extends Model\Element\Service
{
    /**
     * @var array
     */
    protected $_copyRecursiveIds;

    /**
     * @var Model\User
     */
    protected $_user;

    /**
     * System fields used by filter conditions
     *
     * @var array
     */
    protected static $systemFields = ['o_path', 'o_key', 'o_id', 'o_published', 'o_creationDate', 'o_modificationDate', 'o_fullpath'];

    /**
     * @param  Model\User $user
     */
    public function __construct($user = null)
    {
        $this->_user = $user;
    }

    /**
     * finds all objects which hold a reference to a specific user
     *
     * @static
     *
     * @param  int $userId
     *
     * @return Concrete[]
     */
    public static function getObjectsReferencingUser($userId)
    {
        $userObjects = [];
        $classesList = new ClassDefinition\Listing();
        $classesList->setOrderKey('name');
        $classesList->setOrder('asc');
        $classes = $classesList->load();

        $classesToCheck = [];
        if (is_array($classes)) {
            foreach ($classes as $class) {
                $fieldDefinitions = $class->getFieldDefinitions();
                $dataKeys = [];
                if (is_array($fieldDefinitions)) {
                    foreach ($fieldDefinitions as $tag) {
                        if ($tag instanceof ClassDefinition\Data\User) {
                            $dataKeys[] = $tag->getName();
                        }
                    }
                }
                if (is_array($dataKeys) and count($dataKeys) > 0) {
                    $classesToCheck[$class->getName()] = $dataKeys;
                }
            }
        }

        foreach ($classesToCheck as $classname => $fields) {
            $listName = '\\Pimcore\\Model\\DataObject\\' . ucfirst($classname) . '\\Listing';
            $list = new $listName();
            $conditionParts = [];
            foreach ($fields as $field) {
                $conditionParts[] = $field . ' = ?';
            }
            $list->setCondition(implode(' AND ', $conditionParts), array_fill(0, count($conditionParts), $userId));
            $objects = $list->load();
            $userObjects = array_merge($userObjects, $objects);
        }

        return $userObjects;
    }

    /**
     * @param $target
     * @param $source
     *
     * @return mixed
     */
    public function copyRecursive($target, $source)
    {

        // avoid recursion
        if (!$this->_copyRecursiveIds) {
            $this->_copyRecursiveIds = [];
        }
        if (in_array($source->getId(), $this->_copyRecursiveIds)) {
            return;
        }

        $source->getProperties();
        //load all in case of lazy loading fields
        self::loadAllObjectFields($source);

        $new = Element\Service::cloneMe($source);
        $new->setId(null);
        $new->setChilds(null);
        $new->setKey(Element\Service::getSaveCopyName('object', $new->getKey(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user->getId());
        $new->setUserModification($this->_user->getId());
        $new->setDao(null);
        $new->setLocked(false);
        $new->setCreationDate(time());
        $new->save();

        // add to store
        $this->_copyRecursiveIds[] = $new->getId();

        foreach ($source->getChilds() as $child) {
            $this->copyRecursive($new, $child);
        }

        $this->updateChilds($target, $new);

        // triggers actions after the complete document cloning
        \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_COPY, new DataObjectEvent($new, [
            'base_element' => $source // the element used to make a copy
        ]));

        return $new;
    }

    /**
     * @param  AbstractObject $target
     * @param  AbstractObject $source
     *
     * @return AbstractObject copied object
     */
    public function copyAsChild($target, $source)
    {
        $isDirtyDetectionDisabled = Model\DataObject\AbstractObject::isDirtyDetectionDisabled();
        Model\DataObject\AbstractObject::setDisableDirtyDetection(true);

        //load properties
        $source->getProperties();

        //load all in case of lazy loading fields
        self::loadAllObjectFields($source);

        $new = Element\Service::cloneMe($source);
        $new->setId(null);

        $new->setChildren(null);
        $new->setKey(Element\Service::getSaveCopyName('object', $new->getKey(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user->getId());
        $new->setUserModification($this->_user->getId());
        $new->setDao(null);
        $new->setLocked(false);
        $new->setCreationDate(time());
        $new->save();

        Model\DataObject\AbstractObject::setDisableDirtyDetection($isDirtyDetectionDisabled);

        $this->updateChilds($target, $new);

        // triggers actions after the complete object cloning
        \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_COPY, new DataObjectEvent($new, [
            'base_element' => $source // the element used to make a copy
        ]));

        return $new;
    }

    /**
     * @param $target
     * @param $source
     *
     * @return AbstractObject
     *
     * @throws \Exception
     */
    public function copyContents($target, $source)
    {

        // check if the type is the same
        if (get_class($source) != get_class($target)) {
            throw new \Exception('Source and target have to be the same type');
        }

        //load all in case of lazy loading fields
        self::loadAllObjectFields($source);

        $new = Element\Service::cloneMe($source);
        $new->setChilds($target->getChilds());
        $new->setId($target->getId());
        $new->setPath($target->getRealPath());
        $new->setKey($target->getKey());
        $new->setParentId($target->getParentId());
        $new->setScheduledTasks($source->getScheduledTasks());
        $new->setProperties($source->getProperties());
        $new->setUserModification($this->_user->getId());

        $new->save();

        $target = AbstractObject::getById($new->getId());

        return $target;
    }

    /**
     * @param $field
     *
     * @return bool
     */
    public static function isHelperGridColumnConfig($field)
    {
        return strpos($field, '#') === 0;
    }

    /**
     * Language only user for classification store !!!
     *
     * @param  AbstractObject $object
     * @param null $fields
     * @param null $requestedLanguage
     *
     * @return array
     */
    public static function gridObjectData($object, $fields = null, $requestedLanguage = null, $params = [])
    {
        $data = Element\Service::gridElementData($object);

        if ($object instanceof Concrete) {
            $context = ['object' => $object,
                'purpose' => 'gridview'];
            $data['classname'] = $object->getClassName();
            $data['idPath'] = Element\Service::getIdPath($object);
            $data['inheritedFields'] = [];
            $data['permissions'] = $object->getUserPermissions();
            $data['locked'] = $object->isLocked();

            $user = AdminTool::getCurrentUser();

            if (empty($fields)) {
                $fields = array_keys($object->getclass()->getFieldDefinitions());
            }

            $haveHelperDefinition = false;

            foreach ($fields as $key) {
                $brickType = null;
                $brickGetter = null;
                $dataKey = $key;
                $keyParts = explode('~', $key);

                $def = $object->getClass()->getFieldDefinition($key, $context);

                if (strpos($key, '#') === 0) {
                    if (!$haveHelperDefinition) {
                        $helperDefinitions = self::getHelperDefinitions();
                        $haveHelperDefinition = true;
                    }
                    if ($helperDefinitions[$key]) {
                        $context['fieldname'] = $key;
                        $data[$key] = self::calculateCellValue($object, $helperDefinitions, $key, $context);
                    }
                } elseif (substr($key, 0, 1) == '~') {
                    $type = $keyParts[1];
                    if ($type == 'classificationstore') {
                        $field = $keyParts[2];
                        $groupKeyId = explode('-', $keyParts[3]);

                        $groupId = $groupKeyId[0];
                        $keyid = $groupKeyId[1];
                        $getter = 'get' . ucfirst($field);
                        if (method_exists($object, $getter)) {
                            /** @var $classificationStoreData Classificationstore */
                            $classificationStoreData = $object->$getter();

                            /** @var $csFieldDefinition Model\DataObject\ClassDefinition\Data\Classificationstore */
                            $csFieldDefinition = $object->getClass()->getFieldDefinition($field);
                            $csLanguage = $requestedLanguage;
                            if (!$csFieldDefinition->isLocalized()) {
                                $csLanguage = 'default';
                            }

                            $fielddata = $classificationStoreData->getLocalizedKeyValue($groupId, $keyid, $csLanguage, true, true);

                            $keyConfig = Model\DataObject\Classificationstore\KeyConfig::getById($keyid);
                            $type = $keyConfig->getType();
                            $definition = json_decode($keyConfig->getDefinition());
                            $definition = \Pimcore\Model\DataObject\Classificationstore\Service::getFieldDefinitionFromJson($definition, $type);

                            if (method_exists($definition, 'getDataForGrid')) {
                                $fielddata = $definition->getDataForGrid($fielddata, $object);
                            }
                            $data[$key] = $fielddata;
                        }
                    }
                } elseif (count($keyParts) > 1) {
                    // brick
                    $brickType = $keyParts[0];
                    if (strpos($brickType, '?') !== false) {
                        $brickDescriptor = substr($brickType, 1);
                        $brickDescriptor = json_decode($brickDescriptor, true);
                        $brickType = $brickDescriptor['containerKey'];
                    }

                    $brickKey = $keyParts[1];

                    $key = self::getFieldForBrickType($object->getclass(), $brickType);

                    $brickClass = Objectbrick\Definition::getByKey($brickType);
                    $context['outerFieldname'] = $key;

                    if ($brickDescriptor) {
                        $innerContainer = $brickDescriptor['innerContainer'] ? $brickDescriptor['innerContainer'] : 'localizedfields';
                        $localizedFields = $brickClass->getFieldDefinition($innerContainer);
                        $def = $localizedFields->getFieldDefinition($brickDescriptor['brickfield']);
                    } else {
                        $def = $brickClass->getFieldDefinition($brickKey, $context);
                    }
                }

                if (!empty($key)) {
                    // some of the not editable field require a special response
                    $getter = 'get' . ucfirst($key);
                    $needLocalizedPermissions = false;

                    // if the definition is not set try to get the definition from localized fields
                    if (!$def) {
                        if ($locFields = $object->getClass()->getFieldDefinition('localizedfields')) {
                            $def = $locFields->getFieldDefinition($key, $context);
                            if ($def) {
                                $needLocalizedPermissions = true;
                            }
                        }
                    }

                    //relation type fields with remote owner do not have a getter
                    if (method_exists($object, $getter)) {

                        //system columns must not be inherited
                        if (in_array($key, Concrete::$systemColumnNames)) {
                            $data[$dataKey] = $object->$getter();
                        } else {
                            $valueObject = self::getValueForObject($object, $key, $brickType, $brickKey, $def, $context, $brickDescriptor);
                            $data['inheritedFields'][$dataKey] = ['inherited' => $valueObject->objectid != $object->getId(), 'objectid' => $valueObject->objectid];

                            if (method_exists($def, 'getDataForGrid')) {
                                if ($brickKey) {
                                    $context['containerType'] = 'objectbrick';
                                    $context['containerKey'] = $brickType;
                                    $context['outerFieldname'] = $key;
                                }

                                $params = array_merge($params, ['context' => $context]);
                                if (!isset($params['purpose'])) {
                                    $params['purpose'] = 'gridview';
                                }

                                $tempData = $def->getDataForGrid($valueObject->value, $object, $params);

                                if ($def instanceof ClassDefinition\Data\Localizedfields) {
                                    $needLocalizedPermissions = true;
                                    foreach ($tempData as $tempKey => $tempValue) {
                                        $data[$tempKey] = $tempValue;
                                    }
                                } else {
                                    $data[$dataKey] = $tempData;
                                    if ($def instanceof Model\DataObject\ClassDefinition\Data\Select && $def->getOptionsProviderClass()) {
                                        $data[$dataKey . '%options'] = $def->getOptions();
                                    }
                                }
                            } else {
                                $data[$dataKey] = $valueObject->value;
                            }
                        }
                    }

                    if ($needLocalizedPermissions) {
                        if (!$user->isAdmin()) {
                            $locale = \Pimcore::getContainer()->get('pimcore.locale')->findLocale();

                            $permissionTypes = ['View', 'Edit'];
                            foreach ($permissionTypes as $permissionType) {
                                //TODO, this needs refactoring! Ideally, call it only once!
                                $languagesAllowed = self::getLanguagePermissions($object, $user, 'l' . $permissionType);

                                if ($languagesAllowed) {
                                    $languagesAllowed = array_keys($languagesAllowed);

                                    if (!in_array($locale, $languagesAllowed)) {
                                        $data['metadata']['permission'][$key]['no' . $permissionType] = 1;
                                        if ($permissionType == 'View') {
                                            $data[$key] = null;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param $helperDefinitions
     * @param $key
     *
     * @return bool
     */
    public static function expandGridColumnForExport($helperDefinitions, $key)
    {
        $config = self::getConfigForHelperDefinition($helperDefinitions, $key);
        if ($config instanceof \Pimcore\DataObject\GridColumnConfig\Operator\AbstractOperator && $config->expandLocales()) {
            return $config->getValidLanguages();
        }

        return false;
    }

    /**
     * @param $helperDefinitions
     * @param $key
     * @param $context array
     *
     * @return mixed|null|ConfigElementInterface|ConfigElementInterface[]
     */
    public static function getConfigForHelperDefinition($helperDefinitions, $key, $context = [])
    {
        $cacheKey = 'gridcolumn_config_' . $key;
        if (Runtime::isRegistered($cacheKey)) {
            $config = Runtime::get($cacheKey);
        } else {
            $definition = $helperDefinitions[$key];
            $attributes = json_decode(json_encode($definition->attributes));

            // TODO refactor how the service is accessed into something non-static and inject the service there
            $service = \Pimcore::getContainer()->get(GridColumnConfigService::class);
            $config = $service->buildOutputDataConfig([$attributes], $context);

            if (!$config) {
                return null;
            }
            $config = $config[0];
            Runtime::save($config, $cacheKey);
        }

        return $config;
    }

    /**
     * @param $object
     * @param $definition
     *
     * @return null
     */
    public static function calculateCellValue($object, $helperDefinitions, $key, $context = [])
    {
        $config = static::getConfigForHelperDefinition($helperDefinitions, $key, $context);
        if (!$config) {
            return null;
        }

        $result = $config->getLabeledValue($object);
        if (isset($result->value)) {
            $result = $result->value;

            if ($config->renderer) {
                $classname = 'Pimcore\\Model\\DataObject\\ClassDefinition\\Data\\' . ucfirst($config->renderer);
                /** @var $rendererImpl Model\DataObject\ClassDefinition\Data */
                $rendererImpl = new $classname();
                if (method_exists($rendererImpl, 'getDataForGrid')) {
                    $result = $rendererImpl->getDataForGrid($result, $object, []);
                }
            }

            return $result;
        }

        return null;
    }

    /**
     * @return mixed
     */
    public static function getHelperDefinitions()
    {
        return Session::useSession(function (AttributeBagInterface $session) {
            $existingColumns = $session->get('helpercolumns', []);

            return $existingColumns;
        }, 'pimcore_gridconfig');
    }

    /**
     * @param $object
     * @param $user
     * @param $type
     *
     * @return array|null
     */
    public static function getLanguagePermissions($object, $user, $type)
    {
        $languageAllowed = null;

        $object = $object instanceof Model\DataObject\Fieldcollection\Data\AbstractData ||
        $object instanceof  Model\DataObject\Objectbrick\Data\AbstractData ?
            $object->getObject() : $object;

        $permission = $object->getPermissions($type, $user);

        if (!is_null($permission)) {
            // backwards compatibility. If all entries are null, then the workspace rule was set up with
            // an older pimcore

            $permission = $permission[$type];
            if ($permission) {
                $permission = explode(',', $permission);
                if (is_null($languageAllowed)) {
                    $languageAllowed = [];
                }

                foreach ($permission as $language) {
                    $languageAllowed[$language] = 1;
                }
            }
        }

        return $languageAllowed;
    }

    /**
     * @param $classId
     * @param $permissionSet
     *
     * @return array|null
     */
    public static function getLayoutPermissions($classId, $permissionSet)
    {
        $layoutPermissions = null;

        if (!is_null($permissionSet)) {
            // backwards compatibility. If all entries are null, then the workspace rule was set up with
            // an older pimcore

            $permission = $permissionSet['layouts'];
            if ($permission) {
                $permission = explode(',', $permission);
                if (is_null($layoutPermissions)) {
                    $layoutPermissions = [];
                }

                foreach ($permission as $p) {
                    $setting = explode('_', $p);
                    $c = $setting[0];

                    if ($c == $classId) {
                        $l = $setting[1];

                        if (is_null($layoutPermissions)) {
                            $layoutPermissions = [];
                        }
                        $layoutPermissions[$l] = $l;
                    }
                }
            }
        }

        return $layoutPermissions;
    }

    /**
     * @param ClassDefinition $class
     * @param $bricktype
     *
     * @return int|null|string
     */
    public static function getFieldForBrickType(ClassDefinition $class, $bricktype)
    {
        $fieldDefinitions = $class->getFieldDefinitions();
        foreach ($fieldDefinitions as $key => $fd) {
            if ($fd instanceof ClassDefinition\Data\Objectbricks) {
                if (in_array($bricktype, $fd->getAllowedTypes())) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * gets value for given object and getter, including inherited values
     *
     * @static
     *
     * @param $object
     * @param $key
     * @param null $brickType
     * @param null $brickKey
     * @param null $fieldDefinition
     *
     * @return \stdclass, value and objectid where the value comes from
     */
    private static function getValueForObject($object, $key, $brickType = null, $brickKey = null, $fieldDefinition = null, $context = [], $brickDescriptor = null)
    {
        $getter = 'get' . ucfirst($key);
        $value = $object->$getter();
        if (!empty($value) && !empty($brickType)) {
            $getBrickType = 'get' . ucfirst($brickType);
            $value = $value->$getBrickType();
            if (!empty($value) && !empty($brickKey)) {
                if ($brickDescriptor) {
                    $innerContainer = $brickDescriptor['innerContainer'] ? $brickDescriptor['innerContainer'] : 'localizedfields';
                    $localizedFields = $value->{'get' . ucfirst($innerContainer)}();
                    $brickDefinition = Model\DataObject\Objectbrick\Definition::getByKey($brickType);
                    $fieldDefinitionLocalizedFields = $brickDefinition->getFieldDefinition('localizedfields');
                    $fieldDefinition = $fieldDefinitionLocalizedFields->getFieldDefinition($brickKey);
                    $value = $localizedFields->getLocalizedValue($brickDescriptor['brickfield']);
                } else {
                    $brickFieldGetter = 'get' . ucfirst($brickKey);
                    $value = $value->$brickFieldGetter();
                }
            }
        }

        if (!$fieldDefinition) {
            $fieldDefinition = $object->getClass()->getFieldDefinition($key, $context);
        }

        if (!empty($brickType) && !empty($brickKey) && !$brickDescriptor) {
            $brickClass = Objectbrick\Definition::getByKey($brickType);
            $context = ['object' => $object, 'outerFieldname' => $key];
            $fieldDefinition = $brickClass->getFieldDefinition($brickKey, $context);
        }

        if ($fieldDefinition->isEmpty($value)) {
            $parent = self::hasInheritableParentObject($object);
            if (!empty($parent)) {
                return self::getValueForObject($parent, $key, $brickType, $brickKey, $fieldDefinition, $context, $brickDescriptor);
            }
        }

        $result = new \stdClass();
        $result->value = $value;
        $result->objectid = $object->getId();

        return $result;
    }

    /**
     * @param Concrete $object
     *
     * @return AbstractObject|null
     */
    public static function hasInheritableParentObject(Concrete $object)
    {
        if ($object->getClass()->getAllowInherit()) {
            return $object->getNextParentForInheritance();
        }

        return null;
    }

    /**
     * call the getters of each object field, in case some of the are lazy loading and we need the data to be loaded
     *
     * @static
     *
     * @param  Concrete $object
     */
    public static function loadAllObjectFields($object)
    {
        $object->getProperties();

        if ($object instanceof Concrete) {
            //load all in case of lazy loading fields
            $fd = $object->getClass()->getFieldDefinitions();
            foreach ($fd as $def) {
                $getter = 'get' . ucfirst($def->getName());
                if (method_exists($object, $getter)) {
                    $object->$getter();
                }
            }
        }
    }

    /**
     *
     * @param string $filterJson
     * @param ClassDefinition $class
     * @param $requestedLanguage
     *
     * @return string
     */
    public static function getFeatureFilters($filterJson, $class, $requestedLanguage)
    {
        $joins = [];
        $conditions = [];

        if ($filterJson) {
            $filters = json_decode($filterJson, true);
            foreach ($filters as $filter) {
                $operator = '=';

                $filterField = $filter['property'];
                $filterOperator = $filter['operator'];

                if ($filter['type'] == 'string') {
                    $operator = 'LIKE';
                } elseif ($filter['type'] == 'numeric') {
                    if ($filterOperator == 'lt') {
                        $operator = '<';
                    } elseif ($filterOperator == 'gt') {
                        $operator = '>';
                    } elseif ($filterOperator == 'eq') {
                        $operator = '=';
                    }
                } elseif ($filter['type'] == 'date') {
                    if ($filterOperator == 'lt') {
                        $operator = '<';
                    } elseif ($filterOperator == 'gt') {
                        $operator = '>';
                    } elseif ($filterOperator == 'eq') {
                        $operator = '=';
                    }
                    $filter['value'] = strtotime($filter['value']);
                } elseif ($filter['type'] == 'list') {
                    $operator = '=';
                } elseif ($filter['type'] == 'boolean') {
                    $operator = '=';
                    $filter['value'] = (int)$filter['value'];
                }

                $keyParts = explode('~', $filterField);

                if (substr($filterField, 0, 1) != '~') {
                    continue;
                }

                $type = $keyParts[1];
                if ($type != 'classificationstore') {
                    continue;
                }

                $fieldName = $keyParts[2];
                $groupKeyId = explode('-', $keyParts[3]);

                /** @var $csFieldDefinition Model\DataObject\ClassDefinition\Data\Classificationstore */
                $csFieldDefinition = $class->getFieldDefinition($fieldName);

                $language = $requestedLanguage;
                if (!$csFieldDefinition->isLocalized()) {
                    $language = 'default';
                }

                $groupId = $groupKeyId[0];
                $keyid = $groupKeyId[1];

                $keyConfig = Model\DataObject\Classificationstore\KeyConfig::getById($keyid);
                $type = $keyConfig->getType();
                $definition = json_decode($keyConfig->getDefinition());
                $field = \Pimcore\Model\DataObject\Classificationstore\Service::getFieldDefinitionFromJson($definition, $type);

                if ($field instanceof ClassDefinition\Data) {
                    $mappedKey = 'cskey_' . $fieldName . '_' . $groupId . '_' . $keyid;
                    $joins[] = ['fieldname' => $fieldName, 'groupId' => $groupId, 'keyId' => $keyid, 'language' => $language];
                    $condition = $field->getFilterConditionExt(
                        $filter['value'],
                        $operator,
                        [
                            'name' => $mappedKey]
                    );

                    $conditions[$mappedKey] = $condition;
                }
            }
        }

        $result = [
            'joins' => $joins,
            'conditions' => $conditions
        ];

        return $result;
    }

    /**
     *
     * @param string $filterJson
     * @param ClassDefinition $class
     *
     * @return string
     */
    public static function getFilterCondition($filterJson, $class)
    {
        $systemFields = self::getSystemFields();

        // create filter condition
        $conditionPartsFilters = [];

        if ($filterJson) {
            $db = \Pimcore\Db::get();
            $filters = json_decode($filterJson, true);
            foreach ($filters as $filter) {
                $operator = '=';

                $filterField = $filter['property'];
                $filterOperator = $filter['operator'];

                if ($filter['type'] == 'string') {
                    $operator = 'LIKE';
                } elseif ($filter['type'] == 'numeric') {
                    if ($filterOperator == 'lt') {
                        $operator = '<';
                    } elseif ($filterOperator == 'gt') {
                        $operator = '>';
                    } elseif ($filterOperator == 'eq') {
                        $operator = '=';
                    }
                } elseif ($filter['type'] == 'date') {
                    if ($filterOperator == 'lt') {
                        $operator = '<';
                    } elseif ($filterOperator == 'gt') {
                        $operator = '>';
                    } elseif ($filterOperator == 'eq') {
                        $operator = '=';
                    }
                    $filter['value'] = strtotime($filter['value']);
                } elseif ($filter['type'] == 'list') {
                    $operator = '=';
                } elseif ($filter['type'] == 'boolean') {
                    $operator = '=';
                    $filter['value'] = (int)$filter['value'];
                }

                $field = $class->getFieldDefinition($filterField);
                $brickField = null;
                $brickType = null;
                if (!$field) {

                    // if the definition doesn't exist check for a localized field
                    $localized = $class->getFieldDefinition('localizedfields');
                    if ($localized instanceof ClassDefinition\Data\Localizedfields) {
                        $field = $localized->getFieldDefinition($filterField);
                    }

                    //if the definition doesn't exist check for object brick
                    $keyParts = explode('~', $filterField);

                    if (substr($filterField, 0, 1) == '~') {
                        // not needed for now
//                            $type = $keyParts[1];
//                            $field = $keyParts[2];
//                            $keyid = $keyParts[3];
                    } elseif (count($keyParts) > 1) {
                        $brickType = $keyParts[0];
                        $brickKey = $keyParts[1];

                        $key = self::getFieldForBrickType($class, $brickType);
                        $field = $class->getFieldDefinition($key);

                        if (strpos($brickType, '?') !== false) {
                            $brickDescriptor = substr($brickType, 1);
                            $brickDescriptor = json_decode($brickDescriptor, true);
                            $brickType = $brickDescriptor['containerKey'];
                        }

                        $brickClass = Objectbrick\Definition::getByKey($brickType);

                        if ($brickDescriptor) {
                            $brickField = $brickClass->getFieldDefinition('localizedfields')->getFieldDefinition($brickDescriptor['brickfield']);
                        } else {
                            $brickField = $brickClass->getFieldDefinition($brickKey);
                        }
                    }
                }
                if ($field instanceof ClassDefinition\Data\Objectbricks || $brickDescriptor) {
                    // custom field
                    $db = \Pimcore\Db::get();
                    $brickPrefix = '';

                    $ommitPrefix = false;

                    if ($brickField instanceof Model\DataObject\ClassDefinition\Data\Checkbox
                        || (($brickField instanceof Model\DataObject\ClassDefinition\Data\Date || $brickField instanceof Model\DataObject\ClassDefinition\Data\Datetime) && $brickField->getColumnType() == 'datetime')
                    ) {
                        $ommitPrefix = true;
                    }

                    if (!$ommitPrefix) {
                        if ($brickDescriptor) {
                            $brickPrefix = $db->quoteIdentifier($brickType . '_localized') . '.';
                        } else {
                            $brickPrefix = $db->quoteIdentifier($brickType) . '.';
                        }
                    }
                    if (is_array($filter['value'])) {
                        $fieldConditions = [];
                        foreach ($filter['value'] as $filterValue) {
                            $fieldConditions[] = $brickPrefix . $brickField->getFilterCondition($filterValue, $operator,
                                    ['brickType' => $brickType]
                                );
                        }
                        $conditionPartsFilters[] = '(' . implode(' OR ', $fieldConditions) . ')';
                    } else {
                        $conditionPartsFilters[] = $brickPrefix . $brickField->getFilterCondition($filter['value'], $operator,
                                ['brickType' => $brickType]);
                    }
                } elseif ($field instanceof ClassDefinition\Data) {
                    // custom field
                    if (is_array($filter['value'])) {
                        $fieldConditions = [];
                        foreach ($filter['value'] as $filterValue) {
                            $fieldConditions[] = $field->getFilterCondition($filterValue, $operator);
                        }
                        $conditionPartsFilters[] = '(' . implode(' OR ', $fieldConditions) . ')';
                    } else {
                        $conditionPartsFilters[] = $field->getFilterCondition($filter['value'], $operator);
                    }
                } elseif (in_array('o_' . $filterField, $systemFields)) {
                    // system field
                    if ($filterField == 'fullpath') {
                        $conditionPartsFilters[] = 'concat(o_path, o_key) ' . $operator . ' ' . $db->quote('%' . $filter['value'] . '%');
                    } elseif ($filterField == 'key') {
                        $conditionPartsFilters[] = 'o_key ' . $operator . ' ' . $db->quote('%' . $filter['value'] . '%');
                    } else {
                        if ($filter['type'] == 'date' && $operator == '=') {
                            //if the equal operator is chosen with the date type, condition has to be changed
                            $maxTime = $filter['value'] + (86400 - 1); //specifies the top point of the range used in the condition
                            $conditionPartsFilters[] = '`o_' . $filterField . '` BETWEEN ' . $db->quote($filter['value']) . ' AND ' . $db->quote($maxTime);
                        } else {
                            $conditionPartsFilters[] = '`o_' . $filterField . '` ' . $operator . ' ' . $db->quote($filter['value']);
                        }
                    }
                }
            }
        }

        $conditionFilters = '1 = 1';
        if (count($conditionPartsFilters) > 0) {
            $conditionFilters = '(' . implode(' AND ', $conditionPartsFilters) . ')';
        }
        Logger::log('DataObjectController filter condition:' . $conditionFilters);

        return $conditionFilters;
    }

    /**
     * @static
     *
     * @param $object
     * @param string|ClassDefinition\Data\Select|ClassDefinition\Data\Multiselect $definition
     *
     * @return array
     */
    public static function getOptionsForSelectField($object, $definition)
    {
        $class = null;
        $options = [];

        if (is_object($object) && method_exists($object, 'getClass')) {
            $class = $object->getClass();
        } elseif (is_string($object)) {
            $object = '\\' . ltrim($object, '\\');
            $object = new $object();
            $class = $object->getClass();
        }

        if ($class) {
            if (is_string($definition)) {
                /**
                 * @var ClassDefinition\Data\Select $definition
                 */
                $definition = $class->getFielddefinition($definition);
            }

            if ($definition instanceof ClassDefinition\Data\Select || $definition instanceof ClassDefinition\Data\Multiselect) {
                $_options = $definition->getOptions();

                foreach ($_options as $option) {
                    $options[$option['value']] = $option['key'];
                }
            }
        }

        return $options;
    }

    /**
     * alias of getOptionsForMultiSelectField
     *
     * @param $object
     * @param $fieldname
     *
     * @return array
     */
    public static function getOptionsForMultiSelectField($object, $fieldname)
    {
        return self::getOptionsForSelectField($object, $fieldname);
    }

    /**
     * @static
     *
     * @param $path
     * @param null $type
     *
     * @return bool
     */
    public static function pathExists($path, $type = null)
    {
        $path = Element\Service::correctPath($path);

        try {
            $object = new AbstractObject();

            $pathElements = explode('/', $path);
            $keyIdx = count($pathElements) - 1;
            $key = $pathElements[$keyIdx];
            $validKey = Element\Service::getValidKey($key, 'object');

            unset($pathElements[$keyIdx]);
            $pathOnly = implode('/', $pathElements);

            if ($validKey == $key && self::isValidPath($pathOnly, 'object')) {
                $object->getDao()->getByPath($path);

                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * Rewrites id from source to target, $rewriteConfig contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     *
     * @param $object
     * @param $rewriteConfig
     *
     * @return AbstractObject
     */
    public static function rewriteIds($object, $rewriteConfig)
    {
        // rewriting elements only for snippets and pages
        if ($object instanceof Concrete) {
            $fields = $object->getClass()->getFieldDefinitions();

            foreach ($fields as $field) {
                if (method_exists($field, 'rewriteIds')) {
                    $setter = 'set' . ucfirst($field->getName());
                    if (method_exists($object, $setter)) { // check for non-owner-objects
                        $object->$setter($field->rewriteIds($object, $rewriteConfig));
                    }
                }
            }
        }

        // rewriting properties
        $properties = $object->getProperties();
        foreach ($properties as &$property) {
            $property->rewriteIds($rewriteConfig);
        }
        $object->setProperties($properties);

        return $object;
    }

    /**
     * @param Concrete $object
     *
     * @return array
     */
    public static function getValidLayouts(Concrete $object)
    {
        $user = AdminTool::getCurrentUser();

        $resultList = [];
        $isMasterAllowed = $user->getAdmin();

        $permissionSet = $object->getPermissions('layouts', $user);
        $layoutPermissions = self::getLayoutPermissions($object->getClassId(), $permissionSet);
        if (!$layoutPermissions || isset($layoutPermissions[0])) {
            $isMasterAllowed = true;
        }

        if ($user->getAdmin()) {
            $superLayout = new ClassDefinition\CustomLayout();
            $superLayout->setId(-1);
            $superLayout->setName('Master (Admin Mode)');
            $resultList[-1] = $superLayout;
        }

        if ($isMasterAllowed) {
            $master = new ClassDefinition\CustomLayout();
            $master->setId(0);
            $master->setName('Master');
            $resultList[0] = $master;
        }

        $classId = $object->getClassId();
        $list = new ClassDefinition\CustomLayout\Listing();
        $list->setOrderKey('name');
        $condition = 'classId = ' . $list->quote($classId);
        if (is_array($layoutPermissions) && count($layoutPermissions)) {
            $layoutIds = array_values($layoutPermissions);
            $condition .= ' AND id IN (' . implode(',', $layoutIds) . ')';
        }
        $list->setCondition($condition);
        $list = $list->load();

        if ((!count($resultList) && !count($list)) || (count($resultList) == 1 && !count($list))) {
            return [];
        }

        foreach ($list as $customLayout) {
            $resultList[$customLayout->getId()] = $customLayout;
        }

        return $resultList;
    }

    /**
     * Returns the fields of a datatype container (e.g. block or localized fields)
     *
     * @param $layout
     * @param $targetClass
     * @param $targetList
     * @param $insideDataType
     *
     * @return mixed
     */
    public static function extractFieldDefinitions($layout, $targetClass, $targetList, $insideDataType)
    {
        if ($insideDataType && $layout instanceof ClassDefinition\Data and !is_a($layout, $targetClass)) {
            $targetList[$layout->getName()] = $layout;
        }

        if (method_exists($layout, 'getChildren')) {
            $children = $layout->getChildren();
            $insideDataType |= is_a($layout, $targetClass);
            if (is_array($children)) {
                foreach ($children as $child) {
                    $targetList = self::extractFieldDefinitions($child, $targetClass, $targetList, $insideDataType);
                }
            }
        }

        return $targetList;
    }

    /** Calculates the super layout definition for the given object.
     * @param Concrete $object
     *
     * @return mixed
     */
    public static function getSuperLayoutDefinition(Concrete $object)
    {
        $masterLayout = $object->getClass()->getLayoutDefinitions();
        $superLayout = unserialize(serialize($masterLayout));

        self::createSuperLayout($superLayout);

        return $superLayout;
    }

    /**
     * @param $layout
     */
    public static function createSuperLayout(&$layout)
    {
        if ($layout instanceof ClassDefinition\Data) {
            $layout->setInvisible(false);
            $layout->setNoteditable(false);
        }

        if ($layout instanceof Model\DataObject\ClassDefinition\Data\Fieldcollections) {
            unset($layout->disallowAddRemove);
            unset($layout->disallowReorder);
            $layout->layoutId = -1;
        }

        if (method_exists($layout, 'getChilds')) {
            $children = $layout->getChilds();
            if (is_array($children)) {
                foreach ($children as $child) {
                    self::createSuperLayout($child);
                }
            }
        }
    }

    /**
     * @param $masterDefinition
     * @param $layout
     *
     * @return bool
     */
    private static function synchronizeCustomLayoutFieldWithMaster($masterDefinition, &$layout)
    {
        if ($layout instanceof ClassDefinition\Data) {
            $fieldname = $layout->name;
            if (!$masterDefinition[$fieldname]) {
                return false;
            } else {
                if ($layout->getFieldtype() != $masterDefinition[$fieldname]->getFieldType()) {
                    $layout->adoptMasterDefinition($masterDefinition[$fieldname]);
                } else {
                    $layout->synchronizeWithMasterDefinition($masterDefinition[$fieldname]);
                }
            }
        }

        if (method_exists($layout, 'getChilds')) {
            $children = $layout->getChilds();
            if (is_array($children)) {
                $count = count($children);
                for ($i = $count - 1; $i >= 0; $i--) {
                    $child = $children[$i];
                    if (!self::synchronizeCustomLayoutFieldWithMaster($masterDefinition, $child)) {
                        unset($children[$i]);
                    }
                    $layout->setChilds($children);
                }
            }
        }

        return true;
    }

    /** Synchronizes a custom layout with its master layout
     * @param ClassDefinition\CustomLayout $customLayout
     */
    public static function synchronizeCustomLayout(ClassDefinition\CustomLayout $customLayout)
    {
        $classId = $customLayout->getClassId();
        $class = ClassDefinition::getById($classId);
        if ($class && ($class->getModificationDate() > $customLayout->getModificationDate())) {
            $masterDefinition = $class->getFieldDefinitions();
            $customLayoutDefinition = $customLayout->getLayoutDefinitions();

            foreach (['Localizedfields', 'Block'] as $dataType) {
                $targetList = self::extractFieldDefinitions($class->getLayoutDefinitions(), '\Pimcore\Model\DataObject\ClassDefinition\Data\\' . $dataType, [], false);
                $masterDefinition = array_merge($masterDefinition, $targetList);
            }

            self::synchronizeCustomLayoutFieldWithMaster($masterDefinition, $customLayoutDefinition);
            $customLayout->save();
        }
    }

    /**
     * @param $classId
     * @param $objectId
     *
     * @return mixed|null
     */
    public static function getCustomGridFieldDefinitions($classId, $objectId)
    {
        $object = AbstractObject::getById($objectId);

        $class = ClassDefinition::getById($classId);
        $masterFieldDefinition = $class->getFieldDefinitions();

        if (!$object) {
            return null;
        }

        $user = AdminTool::getCurrentUser();
        if ($user->isAdmin()) {
            return null;
        }

        $permissionList = [];

        $parentPermissionSet = $object->getPermissions(null, $user, true);
        if ($parentPermissionSet) {
            $permissionList[] = $parentPermissionSet;
        }

        $childPermissions = $object->getChildPermissions(null, $user);
        $permissionList = array_merge($permissionList, $childPermissions);

        $layoutDefinitions = [];

        foreach ($permissionList as $permissionSet) {
            $allowedLayoutIds = self::getLayoutPermissions($classId, $permissionSet);
            if (is_array($allowedLayoutIds)) {
                foreach ($allowedLayoutIds as $allowedLayoutId) {
                    if ($allowedLayoutId) {
                        if (!$layoutDefinitions[$allowedLayoutId]) {
                            $customLayout = ClassDefinition\CustomLayout::getById($allowedLayoutId);
                            if (!$customLayout) {
                                continue;
                            }
                            $layoutDefinitions[$allowedLayoutId] = $customLayout;
                        }
                    }
                }
            }
        }

        $mergedFieldDefinition = self::cloneDefinition($masterFieldDefinition);

        if (count($layoutDefinitions)) {
            foreach ($mergedFieldDefinition as $key => $def) {
                if ($def instanceof ClassDefinition\Data\Localizedfields) {
                    $mergedLocalizedFieldDefinitions = $mergedFieldDefinition[$key]->getFieldDefinitions();

                    foreach ($mergedLocalizedFieldDefinitions as $locKey => $locValue) {
                        $mergedLocalizedFieldDefinitions[$locKey]->setInvisible(false);
                        $mergedLocalizedFieldDefinitions[$locKey]->setNotEditable(false);
                    }
                    $mergedFieldDefinition[$key]->setChilds($mergedLocalizedFieldDefinitions);
                } else {
                    $mergedFieldDefinition[$key]->setInvisible(false);
                    $mergedFieldDefinition[$key]->setNotEditable(false);
                }
            }
        }

        foreach ($layoutDefinitions as $customLayoutDefinition) {
            $layoutDefinitions = $customLayoutDefinition->getLayoutDefinitions();
            $dummyClass = new ClassDefinition();
            $dummyClass->setLayoutDefinitions($layoutDefinitions);
            $customFieldDefinitions = $dummyClass->getFieldDefinitions();

            foreach ($mergedFieldDefinition as $key => $value) {
                if (!$customFieldDefinitions[$key]) {
                    unset($mergedFieldDefinition[$key]);
                }
            }

            foreach ($customFieldDefinitions as $key => $def) {
                if ($def instanceof ClassDefinition\Data\Localizedfields) {
                    if (!$mergedFieldDefinition[$key]) {
                        continue;
                    }
                    $customLocalizedFieldDefinitions = $def->getFieldDefinitions();
                    $mergedLocalizedFieldDefinitions = $mergedFieldDefinition[$key]->getFieldDefinitions();

                    foreach ($mergedLocalizedFieldDefinitions as $locKey => $locValue) {
                        self::mergeFieldDefinition($mergedLocalizedFieldDefinitions, $customLocalizedFieldDefinitions, $locKey);
                    }
                    $mergedFieldDefinition[$key]->setChilds($mergedLocalizedFieldDefinitions);
                } else {
                    self::mergeFieldDefinition($mergedFieldDefinition, $customFieldDefinitions, $key);
                }
            }
        }

        return $mergedFieldDefinition;
    }

    /**
     * @param $definition
     *
     * @return mixed
     */
    public static function cloneDefinition($definition)
    {
        $deepCopy = new \DeepCopy\DeepCopy();
        $theCopy = $deepCopy->copy($definition);

        return $theCopy;
    }

    /**
     * @param $mergedFieldDefinition
     * @param $customFieldDefinitions
     * @param $key
     */
    private static function mergeFieldDefinition(&$mergedFieldDefinition, &$customFieldDefinitions, $key)
    {
        if (!$customFieldDefinitions[$key]) {
            unset($mergedFieldDefinition[$key]);
        } elseif (isset($mergedFieldDefinition[$key])) {
            $def = $customFieldDefinitions[$key];
            if ($def->getNotEditable()) {
                $mergedFieldDefinition[$key]->setNotEditable(true);
            }
            if ($def->getInvisible()) {
                if ($mergedFieldDefinition[$key] instanceof ClassDefinition\Data\Objectbricks) {
                    unset($mergedFieldDefinition[$key]);

                    return;
                } else {
                    $mergedFieldDefinition[$key]->setInvisible(true);
                }
            }

            if ($def->title) {
                $mergedFieldDefinition[$key]->setTitle($def->title);
            }
        }
    }

    /**
     * @param $layout
     * @param $fieldDefinitions
     *
     * @return bool
     */
    private static function doFilterCustomGridFieldDefinitions(&$layout, $fieldDefinitions)
    {
        if ($layout instanceof ClassDefinition\Data) {
            $name = $layout->getName();
            if (!$fieldDefinitions[$name] || $fieldDefinitions[$name]->getInvisible()) {
                return false;
            } else {
                $layout->setNoteditable($layout->getNoteditable() | $fieldDefinitions[$name]->getNoteditable());
            }
        }

        if (method_exists($layout, 'getChilds')) {
            $children = $layout->getChilds();
            if (is_array($children)) {
                $count = count($children);
                for ($i = $count - 1; $i >= 0; $i--) {
                    $child = $children[$i];
                    if (!self::doFilterCustomGridFieldDefinitions($child, $fieldDefinitions)) {
                        unset($children[$i]);
                    }
                }
                $layout->setChilds(array_values($children));
            }
        }

        return true;
    }

    /**  Determines the custom layout definition (if necessary) for the given class
     * @param ClassDefinition $class
     * @param int $objectId
     *
     * @return array layout
     */
    public static function getCustomLayoutDefinitionForGridColumnConfig(ClassDefinition $class, $objectId)
    {
        $layoutDefinitions = $class->getLayoutDefinitions();

        $result = [
            'layoutDefinition' => $layoutDefinitions
        ];

        if (!$objectId) {
            return $result;
        }

        $user = AdminTool::getCurrentUser();

        if ($user->isAdmin()) {
            return $result;
        }

        $mergedFieldDefinition = self::getCustomGridFieldDefinitions($class->getId(), $objectId);
        if (is_array($mergedFieldDefinition)) {
            if ($mergedFieldDefinition['localizedfields']) {
                $childs = $mergedFieldDefinition['localizedfields']->getFieldDefinitions();
                if (is_array($childs)) {
                    foreach ($childs as $locKey => $locValue) {
                        $mergedFieldDefinition[$locKey] = $locValue;
                    }
                }
            }

            self::doFilterCustomGridFieldDefinitions($layoutDefinitions, $mergedFieldDefinition);
            $result['layoutDefinition'] = $layoutDefinitions;
            $result['fieldDefinition'] = $mergedFieldDefinition;
        }

        return $result;
    }

    /**
     * @param $item
     * @param int $nr
     *
     * @return mixed|string
     *
     * @throws \Exception
     */
    public static function getUniqueKey($item, $nr = 0)
    {
        $list = new Listing();
        $list->setUnpublished(true);
        $list->setObjectTypes([AbstractObject::OBJECT_TYPE_OBJECT, AbstractObject::OBJECT_TYPE_FOLDER, AbstractObject::OBJECT_TYPE_VARIANT]);
        $key = Element\Service::getValidKey($item->getKey(), 'object');
        if (!$key) {
            throw new \Exception('No item key set.');
        }
        if ($nr) {
            $key = $key . '_' . $nr;
        }

        $parent = $item->getParent();
        if (!$parent) {
            throw new \Exception('You have to set a parent Object to determine a unique Key');
        }

        if (!$item->getId()) {
            $list->setCondition('o_parentId = ? AND `o_key` = ? ', [$parent->getId(), $key]);
        } else {
            $list->setCondition('o_parentId = ? AND `o_key` = ? AND o_id != ? ', [$parent->getId(), $key, $item->getId()]);
        }
        $check = $list->loadIdList();
        if (!empty($check)) {
            $nr++;
            $key = self::getUniqueKey($item, $nr);
        }

        return $key;
    }

    /** Enriches the layout definition before it is returned to the admin interface.
     * @param $layout
     * @param $object Concrete
     * @param $context array
     * @param array $context additional contextual data
     */
    public static function enrichLayoutDefinition(&$layout, $object = null, $context = [])
    {
        $context['object'] = $object;

        if (method_exists($layout, 'enrichLayoutDefinition')) {
            $layout->enrichLayoutDefinition($object, $context);
        }

        if ($layout instanceof Model\DataObject\ClassDefinition\Data\Localizedfields) {
            if ($context['containerType'] == 'fieldcollection') {
                $context['subContainerType'] = 'localizedfield';
            } elseif ($context['containerType'] == 'objectbrick') {
                $context['subContainerType'] = 'localizedfield';
            } else {
                $context['ownerType'] = 'localizedfield';
            }
            $context['ownerName'] = 'localizedfields';
        }

        if (method_exists($layout, 'getChilds')) {
            $children = $layout->getChildren();
            if (is_array($children)) {
                foreach ($children as $child) {
                    self::enrichLayoutDefinition($child, $object, $context);
                }
            }
        }
    }

    /**
     * @param $object
     * @param array $params
     * @param $data Model\DataObject\Data\CalculatedValue
     *
     * @return mixed|null
     */
    public static function getCalculatedFieldValueForEditMode($object, $params = [], $data)
    {
        if (!$data) {
            return;
        }
        $fieldname = $data->getFieldname();
        $ownerType = $data->getOwnerType();
        /** @var $fd Model\DataObject\ClassDefinition\Data\CalculatedValue */
        if ($ownerType == 'object') {
            $fd = $object->getClass()->getFieldDefinition($fieldname);
        } elseif ($ownerType == 'localizedfield') {
            $fd = $object->getClass()->getFieldDefinition('localizedfields')->getFieldDefinition($fieldname);
        } elseif ($ownerType == 'classificationstore') {
            $fd = $data->getKeyDefinition();
        } elseif ($ownerType == 'fieldcollection' || $ownerType == 'objectbrick') {
            $fd = $data->getKeyDefinition();
        }

        if (!$fd) {
            return $data;
        }
        $className = $fd->getCalculatorClass();
        if (!$className || !\Pimcore\Tool::classExists($className)) {
            Logger::error('Class does not exist: ' . $className);

            return null;
        }

        $inheritanceEnabled = Model\DataObject\Concrete::getGetInheritedValues();
        Model\DataObject\Concrete::setGetInheritedValues(true);

        if (method_exists($className, 'getCalculatedValueForEditMode')) {
            $result = call_user_func($className . '::getCalculatedValueForEditMode', $object, $data);
        } else {
            $result = self::getCalculatedFieldValue($object, $data);
        }
        Model\DataObject\Concrete::setGetInheritedValues($inheritanceEnabled);

        return $result;
    }

    /**
     * @param $object
     * @param $data Model\DataObject\Data\CalculatedValue
     *
     * @return mixed|null
     */
    public static function getCalculatedFieldValue($object, $data)
    {
        if (!$data) {
            return null;
        }
        $fieldname = $data->getFieldname();
        $ownerType = $data->getOwnerType();
        /** @var $fd Model\DataObject\ClassDefinition\Data\CalculatedValue */
        if ($ownerType == 'object') {
            $fd = $object->getClass()->getFieldDefinition($fieldname);
        } elseif ($ownerType == 'localizedfield') {
            $fd = $object->getClass()->getFieldDefinition('localizedfields')->getFieldDefinition($fieldname);
        } elseif ($ownerType == 'classificationstore') {
            $fd = $data->getKeyDefinition();
        } elseif ($ownerType == 'fieldcollection' || $ownerType == 'objectbrick') {
            $fd = $data->getKeyDefinition();
        }

        if (!$fd) {
            return null;
        }
        $className = $fd->getCalculatorClass();
        if (!$className || !\Pimcore\Tool::classExists($className)) {
            Logger::error('Calculator class "' . $className.'" does not exist -> '.$fieldname.'=null');

            return null;
        }

        if (method_exists($className, 'compute')) {
            $inheritanceEnabled = Model\DataObject\Concrete::getGetInheritedValues();
            Model\DataObject\Concrete::setGetInheritedValues(true);

            $result = call_user_func($className . '::compute', $object, $data);
            Model\DataObject\Concrete::setGetInheritedValues($inheritanceEnabled);

            return $result;
        }

        return null;
    }

    /** Adds all the query stuff that is needed for displaying, filtering and exporting the feature grid data.
     * @param $list list
     * @param $featureJoins
     * @param $class
     * @param $featureFilters
     */
    public static function addGridFeatureJoins($list, $featureJoins, $class, $featureFilters)
    {
        if ($featureJoins) {
            $me = $list;
            $list->onCreateQuery(function (QueryBuilder $select) use ($list, $featureJoins, $class, $featureFilters, $me) {
                $db = \Pimcore\Db::get();

                $alreadyJoined = [];

                foreach ($featureJoins as $featureJoin) {
                    $fieldname = $featureJoin['fieldname'];
                    $mappedKey = 'cskey_' . $fieldname . '_' . $featureJoin['groupId'] . '_' . $featureJoin['keyId'];
                    if (isset($alreadyJoined[$mappedKey]) && $alreadyJoined[$mappedKey]) {
                        continue;
                    }
                    $alreadyJoined[$mappedKey] = 1;

                    $table = $me->getDao()->getTableName();
                    $select->joinLeft(
                        [$mappedKey => 'object_classificationstore_data_' . $class->getId()],
                        '('
                        . $mappedKey . '.o_id = ' . $table . '.o_id'
                        . ' and ' . $mappedKey . '.fieldname = ' . $db->quote($fieldname)
                        . ' and ' . $mappedKey . '.groupId=' . $featureJoin['groupId']
                        . ' and ' . $mappedKey . '.keyId=' . $featureJoin['keyId']
                        . ' and ' . $mappedKey . '.language = ' . $db->quote($featureJoin['language'])
                        . ')',
                        [
                            $mappedKey => 'value'
                        ]
                    );
                }

                $havings = $featureFilters['conditions'];
                if ($havings) {
                    $havings = implode(' AND ', $havings);
                    $select->having($havings);
                }
            });
        }
    }

    /**
     * @return array
     */
    public static function getSystemFields()
    {
        return self::$systemFields;
    }

    /**
     * @param $objectId
     *
     * @return mixed|AbstractObject
     */
    public static function getObjectFromSession($objectId)
    {
        $object = Session::useSession(function (AttributeBagInterface $session) use ($objectId) {
            $key = 'object_' . $objectId;
            $result = $session->get($key);

            return $result;
        }, 'pimcore_objects');

        if (!$object) {
            $object = AbstractObject::getById($objectId);
        }

        return $object;
    }

    /**
     * @param $objectId
     */
    public static function removeObjectFromSession($objectId)
    {
        Session::useSession(function (AttributeBagInterface $session) use ($objectId) {
            $key = 'object_' . $objectId;
            $session->remove($key);
        }, 'pimcore_objects');
    }

    /**
     * @param $container
     * @param $fd
     */
    public static function doResetDirtyMap($container, $fd)
    {
        $fieldDefinitions = $fd->getFieldDefinitions();

        if (is_array($fieldDefinitions)) {
            /** @var $fd Model\DataObject\ClassDefinition\Data */
            foreach ($fieldDefinitions as $fd) {
                $value = $container->getObjectVar($fd->getName());

                if ($value instanceof Localizedfield) {
                    $value->resetLanguageDirtyMap();
                }

                if ($value instanceof DirtyIndicatorInterface) {
                    $value->resetDirtyMap();

                    if (!method_exists($value, 'getFieldDefinitions')) {
                        continue;
                    }

                    self::doResetDirtyMap($value, $fieldDefinitions[$fd->getName()]);
                }
            }
        }
    }

    /**
     * @param AbstractObject $object
     */
    public static function recursiveResetDirtyMap(AbstractObject $object)
    {
        if ($object instanceof DirtyIndicatorInterface) {
            $object->resetDirtyMap();
        }

        if ($object instanceof Concrete) {
            self::doResetDirtyMap($object, $object->getClass());
        }
    }
}
