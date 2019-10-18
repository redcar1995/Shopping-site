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

use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\Element;
use Pimcore\Tool;

class Localizedfields extends Data implements CustomResourcePersistingInterface
{
    use Element\ChildsCompatibilityTrait;

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = 'localizedfields';

    /**
     * Type for the generated phpdoc
     *
     * @var string
     */
    public $phpdocType = '\\Pimcore\\Model\\DataObject\\Localizedfield';

    /**
     * @var array
     */
    public $childs = [];

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $region;

    /**
     * @var string
     */
    public $layout;

    /**
     * @var string
     */
    public $title;

    /**
     * @var int
     */
    public $width;

    /**
     * @var int
     */
    public $height;

    /**
     * @var int
     */
    public $maxTabs;

    /**
     * @var int
     */
    public $labelWidth;

    /**
     * @var bool
     */
    public $border = false;

    /**
     * @var bool
     */
    public $provideSplitView;

    /**
     * @var string
     */
    public $tabPosition = 'top';

    /**
     * @var
     */
    public $hideLabelsWhenTabsReached;

    /**
     * contains further localized field definitions if there are more than one localized fields in on class
     *
     * @var array
     */
    protected $referencedFields = [];

    /**
     * @var array
     */
    public $fieldDefinitionsCache;

    /**
     * @see Data::getDataForEditmode
     *
     * @param string $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return array
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        $fieldData = [];
        $metaData = [];

        if (!$data instanceof DataObject\Localizedfield) {
            return [];
        }

        $result = $this->doGetDataForEditMode($data, $object, $fieldData, $metaData, 1, $params);

        // replace the real data with the data for the editmode
        foreach ($result['data'] as $language => &$data) {
            foreach ($data as $key => &$value) {
                $fieldDefinition = $this->getFielddefinition($key);
                if ($fieldDefinition instanceof CalculatedValue) {
                    $childData = new DataObject\Data\CalculatedValue($fieldDefinition->getName());
                    $childData->setContextualData('localizedfield', $this->getName(), null, $language);
                    $value = $fieldDefinition->getDataForEditmode($childData, $object, $params);
                } else {
                    $value = $fieldDefinition->getDataForEditmode($value, $object, $params);
                }
            }
        }

        return $result;
    }

    /**
     * @param $data DataObject\Localizedfield
     * @param $object
     * @param $fieldData
     * @param $metaData
     * @param int $level
     *
     * @return array
     */
    private function doGetDataForEditMode($data, $object, &$fieldData, &$metaData, $level = 1, $params = [])
    {
        $class = $object->getClass();
        $inheritanceAllowed = $class->getAllowInherit();
        $inherited = false;

        $dataItems = $data->getInternalData(true);
        foreach ($dataItems as $language => $values) {
            foreach ($this->getFieldDefinitions() as $fd) {
                if ($fd instanceof Data\Relations\AbstractRelations && !DataObject\Concrete::isLazyLoadingDisabled() && $fd->getLazyLoading()) {
                    $lazyKey = $fd->getName() . DataObject\LazyLoadedFieldsInterface::LAZY_KEY_SEPARATOR . $language;
                    if (!$data->isLazyKeyLoaded($lazyKey)) {
                        $params['language'] = $language;
                        $params['object'] = $object;
                        if (!isset($params['context'])) {
                            $params['context'] = [];
                        }
                        $params['context']['object'] = $object;

                        $value = $fd->load($data, $params);
                        if ($value === 0 || !empty($value)) {
                            $data->setLocalizedValue($fd->getName(), $value, $language, false);
                            $values[$fd->getName()] = $value;
                        }

                        $data->markLazyKeyAsLoaded($lazyKey);
                    }
                }

                $key = $fd->getName();
                $fdata = isset($values[$fd->getName()]) ? $values[$fd->getName()] : null;

                if (!isset($fieldData[$language][$key]) || $fd->isEmpty($fieldData[$language][$key])) {
                    // never override existing data
                    $fieldData[$language][$key] = $fdata;
                    if (!$fd->isEmpty($fdata)) {
                        $inherited = $level > 1;
                        if ($params['context'] && $params['context']['containerType'] == 'block') {
                            $inherited = false;
                        }

                        $metaData[$language][$key] = ['inherited' => $inherited, 'objectid' => $object->getId()];
                    }
                }
            }
        }

        if ($params['context'] && $params['context']['containerType'] == 'block') {
            $inheritanceAllowed = false;
        }

        if ($inheritanceAllowed) {
            // check if there is a parent with the same type
            $parent = DataObject\Service::hasInheritableParentObject($object);
            if ($parent) {
                // same type, iterate over all language and all fields and check if there is something missing
                $validLanguages = Tool::getValidLanguages();
                $foundEmptyValue = false;

                foreach ($validLanguages as $language) {
                    $fieldDefinitions = $this->getFieldDefinitions();
                    foreach ($fieldDefinitions as $fd) {
                        $key = $fd->getName();
                        if ($fd->isEmpty($fieldData[$language][$key])) {
                            $foundEmptyValue = true;
                            $inherited = true;
                            $metaData[$language][$key] = ['inherited' => true, 'objectid' => $parent->getId()];
                        }
                    }
                }

                if ($foundEmptyValue) {
                    $parentData = null;
                    // still some values are passing, ask the parent
                    if ($params['context'] && $params['context']['containerType'] == 'objectbrick') {
                        $brickContainerGetter = 'get' . ucfirst($params['fieldname']);
                        $brickContainer = $parent->$brickContainerGetter();
                        $brickGetter = 'get' . ucfirst($params['context']['containerKey']);
                        $brickData = $brickContainer->$brickGetter();
                        if ($brickData) {
                            $parentData = $brickData->getLocalizedFields();
                        }
                    } else {
                        if (method_exists($parent, 'getLocalizedFields')) {
                            $parentData = $parent->getLocalizedFields();
                        }
                    }
                    if ($parentData) {
                        $parentResult = $this->doGetDataForEditMode(
                            $parentData,
                            $parent,
                            $fieldData,
                            $metaData,
                            $level + 1,
                            $params
                        );
                    }
                }
            }
        }

        $result = [
            'data' => $fieldData,
            'metaData' => $metaData,
            'inherited' => $inherited,
        ];

        return $result;
    }

    /**
     * @see Data::getDataFromEditmode
     *
     * @param array $data
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return DataObject\Localizedfield
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        $localizedFields = $this->getDataFromObjectParam($object, $params);

        if (!$localizedFields instanceof DataObject\Localizedfield) {
            $localizedFields = new DataObject\Localizedfield();
            $context = isset($params['context']) ? $params['context'] : null;
            $localizedFields->setContext($context);
        }

        if ($object) {
            $localizedFields->setObject($object);
        }

        if (is_array($data)) {
            foreach ($data as $language => $fields) {
                foreach ($fields as $name => $fdata) {
                    $fd = $this->getFielddefinition($name);
                    $params['language'] = $language;
                    $localizedFields->setLocalizedValue(
                        $name,
                        $fd->getDataFromEditmode($fdata, $object, $params),
                        $language
                    );
                }
            }
        }

        return $localizedFields;
    }

    /**
     * @param $data
     * @param null $object
     * @param mixed $params
     *
     * @return \stdClass
     */
    public function getDataForGrid($data, $object = null, $params = [])
    {
        $result = new \stdClass();
        foreach ($this->getFieldDefinitions() as $fd) {
            $key = $fd->getName();
            $context = $params['context'];
            if ($context && $context['containerType'] == 'objectbrick') {
                $result->$key = 'NOT SUPPORTED';
            } else {
                $result->$key = $object->{'get' . ucfirst($fd->getName())}();
            }
            if (method_exists($fd, 'getDataForGrid')) {
                $result->$key = $fd->getDataForGrid($result->$key, $object, $params);
            }
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
        // this is handled directly in the template
        // /pimcore/modules/admin/views/scripts/object/preview-version.php
        return 'LOCALIZED FIELDS';
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
        return 'NOT SUPPORTED';
    }

    /**
     * @param string $importValue
     * @param null|Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return null
     */
    public function getFromCsvImport($importValue, $object = null, $params = [])
    {
        return;
    }

    /**
     * @param $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataForSearchIndex($object, $params = [])
    {
        $dataString = '';
        $lfData = $this->getDataFromObjectParam($object);

        if ($lfData instanceof DataObject\Localizedfield) {
            foreach ($lfData->getInternalData(true) as $language => $values) {
                foreach ($values as $lData) {
                    if (is_string($lData)) {
                        $dataString .= $lData.' ';
                    }
                }
            }
        }

        return $dataString;
    }

    /**
     * @param Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return mixed
     */
    public function getForWebserviceExport($object, $params = [])
    {
        $data = $object->getObjectVar($this->getName());
        $wsData = [];

        $items = null;

        if (!$data instanceof DataObject\Localizedfield) {
            $items = [];
        } else {
            $items = $data->getInternalData(true);
        }

        $user = Tool\Admin::getCurrentUser();

        $languagesAllowed = null;
        if ($user && !$user->isAdmin()) {
            $languagesAllowed = DataObject\Service::getLanguagePermissions($object, $user, 'lView');

            if ($languagesAllowed) {
                $languagesAllowed = array_keys($languagesAllowed);
            }
        }

        $validLanguages = Tool::getValidLanguages();
        $localeService = \Pimcore::getContainer()->get('pimcore.locale');
        $localeBackup = $localeService->getLocale();

        if ($validLanguages) {
            foreach ($validLanguages as $language) {
                foreach ($this->getFieldDefinitions() as $fd) {
                    if ($languagesAllowed && !in_array($language, $languagesAllowed)) {
                        continue;
                    }

                    $localeService->setLocale($language);

                    $params['locale'] = $language;

                    $el = new Model\Webservice\Data\DataObject\Element();
                    $el->name = $fd->getName();
                    $el->type = $fd->getFieldType();
                    $el->value = $fd->getForWebserviceExport($object, $params);
                    if ($el->value == null && self::$dropNullValues) {
                        continue;
                    }
                    $el->language = $language;
                    $wsData[] = $el;
                }
            }

            $localeService->setLocale($localeBackup);
        }

        return $wsData;
    }

    /**
     * @param mixed $value
     * @param null $object
     * @param mixed $params
     * @param null $idMapper
     *
     * @return mixed|null|DataObject\Localizedfield
     *
     * @throws \Exception
     */
    public function getFromWebserviceImport($value, $object = null, $params = [], $idMapper = null)
    {
        if (is_array($value)) {
            $validLanguages = Tool::getValidLanguages();

            if (!$idMapper || !$idMapper->ignoreMappingFailures()) {
                foreach ($value as $v) {
                    if (!in_array($v->language, $validLanguages)) {
                        throw new \Exception('Invalid language in localized fields');
                    }
                }
            }

            $localizedFields = $object->getLocalizedFields();
            if (!$localizedFields) {
                $localizedFields = new DataObject\Localizedfield();
            }

            if ($object instanceof DataObject\Concrete) {
                $localizedFields->setObject($object, false);
            }

            $user = Tool\Admin::getCurrentUser();

            $languagesAllowed = null;
            if ($user && !$user->isAdmin()) {
                $languagesAllowed = DataObject\Service::getLanguagePermissions($object, $user, 'lEdit');

                if ($languagesAllowed) {
                    $languagesAllowed = array_keys($languagesAllowed);
                }
            }

            foreach ($value as $field) {
                if ($field instanceof \stdClass) {
                    $field = Tool\Cast::castToClass('\\Pimcore\\Model\\Webservice\\Data\\DataObject\\Element', $field);
                }

                if ($idMapper && $idMapper->ignoreMappingFailures()) {
                    if (!in_array($field->language, $validLanguages)) {
                        continue;
                    }
                }

                if ($languagesAllowed && !in_array($field->language, $languagesAllowed)) {
                    //TODO needs to be discussed. Maybe it is better to throw an exception instead of ignoring
                    //the language
                    continue;
                }

                if (!$field instanceof Model\Webservice\Data\DataObject\Element) {
                    throw new \Exception(
                        "Invalid import data in field [ $field->name ] for language [ $field->language ] in localized fields [ ".$this->getName(
                        ).' ]'
                    );
                }
                $fd = $this->getFielddefinition($field->name);
                if (!$fd instanceof DataObject\ClassDefinition\Data) {
                    if ($idMapper && $idMapper->ignoreMappingFailures()) {
                        continue;
                    }
                    throw new \Exception(
                        "Unknown field [ $field->name ] for language [ $field->language ] in localized fields [ ".$this->getName(
                        ).' ] '
                    );
                } elseif ($fd->getFieldtype() != $field->type) {
                    throw new \Exception(
                        "Type mismatch for field [ $field->name ] for language [ $field->language ] in localized fields [ ".$this->getName(
                        ).' ]. Should be [ '.$fd->getFieldtype().' ], but is [ '.$field->type.' ] '
                    );
                }

                $localizedFields->setLocalizedValue(
                    $field->name,
                    $this->getFielddefinition($field->name)->getFromWebserviceImport(
                        $field->value,
                        $object,
                        $params,
                        $idMapper
                    ),
                    $field->language
                );
            }

            return $localizedFields;
        } elseif (!empty($value)) {
            throw new \Exception('Invalid data in localized fields');
        } else {
            return null;
        }
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
        return is_array($this->childs) && count($this->childs) > 0;
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
     * @param mixed $data
     * @param array $blockedKeys
     *
     * @return $this
     */
    public function setValues($data = [], $blockedKeys = [])
    {
        foreach ($data as $key => $value) {
            if (!in_array($key, $blockedKeys)) {
                $method = 'set'.$key;
                if (method_exists($this, $method)) {
                    $this->$method($value);
                }
            }
        }

        return $this;
    }

    /**
     * @param $object
     * @param array $params
     */
    public function save($object, $params = [])
    {
        $localizedFields = $this->getDataFromObjectParam($object, $params);
        if ($localizedFields instanceof DataObject\Localizedfield) {
            if ((!isset($params['newParent']) || !$params['newParent']) && isset($params['isUpdate']) && $params['isUpdate'] && !$localizedFields->hasDirtyLanguages()) {
                return;
            }

            if ($object instanceof DataObject\Fieldcollection\Data\AbstractData || $object instanceof DataObject\Objectbrick\Data\AbstractData) {
                $object = $object->getObject();
            }

            $localizedFields->setObject($object, false);
            $context = isset($params['context']) ? $params['context'] : null;
            $localizedFields->setContext($context);
            $localizedFields->loadLazyData();
            $localizedFields->save($params);
        }
    }

    /**
     * @param $object
     * @param array $params
     *
     * @return DataObject\Localizedfield
     */
    public function load($object, $params = [])
    {
        if ($object instanceof DataObject\Fieldcollection\Data\AbstractData || $object instanceof DataObject\Objectbrick\Data\AbstractData) {
            $object = $object->getObject();
        }

        $localizedFields = new DataObject\Localizedfield();
        $localizedFields->setObject($object);
        $context = isset($params['context']) ? $params['context'] : null;
        $localizedFields->setContext($context);
        $localizedFields->load($object, $params);

        $localizedFields->resetDirtyMap();
        $localizedFields->resetLanguageDirtyMap();

        return $localizedFields;
    }

    /**
     * @param $object
     * @param array $params
     */
    public function delete($object, $params = [])
    {
        $localizedFields = $this->getDataFromObjectParam($object, $params);

        if ($localizedFields instanceof DataObject\Localizedfield) {
            $localizedFields->setObject($object);
            $context = isset($params['context']) ? $params['context'] : null;
            $localizedFields->setContext($context);
            $localizedFields->delete(true, false);
        }
    }

    /**
     * This method is called in DataObject\ClassDefinition::save() and is used to create the database table for the localized data
     *
     * @param $class
     * @param array $params
     */
    public function classSaved($class, $params = [])
    {
        $localizedFields = new DataObject\Localizedfield();
        $localizedFields->setClass($class);
        $context = isset($params['context']) ? $params['context'] : null;
        $localizedFields->setContext($context);
        $localizedFields->createUpdateTable($params);

        foreach ($this->getFieldDefinitions() as $fd) {
            if (method_exists($fd, 'classSaved')) {
                $fd->classSaved($class, $params);
            }
        }
    }

    /**
     * @param $container
     * @param array $params
     *
     * @return DataObject\Localizedfield
     *
     * @throws \Exception
     */
    public function preGetData($container, $params = [])
    {
        if (!$container instanceof DataObject\Concrete && !$container instanceof DataObject\Fieldcollection\Data\AbstractData
                    && !$container instanceof DataObject\Objectbrick\Data\AbstractData) {
            throw new \Exception('Localized Fields are only valid in Objects, Fieldcollections and Objectbricks');
        }

        $lf = $container->getObjectVar('localizedfields');
        if (!$lf instanceof DataObject\Localizedfield) {
            $lf = new DataObject\Localizedfield();

            $object = $container;
            if ($container instanceof DataObject\Objectbrick\Data\AbstractData) {
                $object = $container->getObject();

                $context = [
                    'containerType' => 'objectbrick',
                    'containerKey' => $container->getType(),
                ];
                $lf->setContext($context);
            } elseif ($container instanceof DataObject\Fieldcollection\Data\AbstractData) {
                $object = $container->getObject();

                $context = [
                    'containerType' => 'fieldcollection',
                    'containerKey' => $container->getType(),
                ];
                $lf->setContext($context);
            } elseif ($container instanceof DataObject\Concrete) {
                $context = ['object' => $container];
                $lf->setContext($context);
            }
            $lf->setObject($object);

            $container->setObjectVar('localizedfields', $lf);
        }

        return $container->getObjectVar('localizedfields');
    }

    /**
     * @param $class
     *
     * @return string
     */
    public function getGetterCode($class)
    {
        $code = '';
        if (!$class instanceof DataObject\Fieldcollection\Definition) {
            $code .= parent::getGetterCode($class);
        }

        $fieldDefinitions = $this->getFieldDefinitions();
        foreach ($fieldDefinitions as $fd) {

            /**
             * @var $fd DataObject\ClassDefinition\Data
             */
            $code .= $fd->getGetterCodeLocalizedfields($class);
        }

        return $code;
    }

    /**
     * @param $class
     *
     * @return string
     */
    public function getSetterCode($class)
    {
        $code = '';
        if (!$class instanceof DataObject\Fieldcollection\Definition) {
            $code .= parent::getSetterCode($class);
        }

        foreach ($this->getFieldDefinitions() as $fd) {

            /**
             * @var $fd DataObject\ClassDefinition\Data
             */
            $code .= $fd->getSetterCodeLocalizedfields($class);
        }

        return $code;
    }

    /**
     * @param $name
     * @param array $context additional contextual data
     *
     * @return mixed
     */
    public function getFielddefinition($name, $context = [])
    {
        $fds = $this->getFieldDefinitions($context);
        if (isset($fds[$name])) {
            $fieldDefinition = $fds[$name];
            if (!\Pimcore::inAdmin() || (isset($context['suppressEnrichment']) && $context['suppressEnrichment'])) {
                return $fieldDefinition;
            }

            $fieldDefinition = $this->doEnrichFieldDefinition($fieldDefinition, $context);

            return $fieldDefinition;
        }

        return;
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

        if (!\Pimcore::inAdmin() || (isset($context['suppressEnrichment']) && $context['suppressEnrichment'])) {
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

    protected function doEnrichFieldDefinition($fieldDefinition, $context = [])
    {
        if (method_exists($fieldDefinition, 'enrichFieldDefinition')) {
            $context['class'] = $this;
            $fieldDefinition = $fieldDefinition->enrichFieldDefinition($context);
        }

        return $fieldDefinition;
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

        if (!$data instanceof DataObject\Localizedfield) {
            return $tags;
        }

        foreach ($data->getInternalData(true) as $language => $values) {
            foreach ($this->getFieldDefinitions() as $fd) {
                $tags = $fd->getCacheTags($values[$fd->getName()], $tags);
            }
        }

        return $tags;
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function resolveDependencies($data)
    {
        $dependencies = [];

        if (!$data instanceof DataObject\Localizedfield) {
            return [];
        }

        foreach ($data->getInternalData(true) as $language => $values) {
            foreach ($this->getFieldDefinitions() as $fd) {
                $dependencies = array_merge($dependencies, $fd->resolveDependencies($values[$fd->getName()]));
            }
        }

        return $dependencies;
    }

    /**
     * @param $height
     *
     * @return $this
     */
    public function setHeight($height)
    {
        $this->height = $this->getAsIntegerCast($height);

        return $this;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
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
     * @return bool
     */
    public function getBorder(): bool
    {
        return $this->border;
    }

    /**
     * @param bool $border
     */
    public function setBorder(bool $border): void
    {
        $this->border = $border;
    }

    /**
     * @param string $name
     *
     * @return $this|Data
     *
     * @throws \Exception
     */
    public function setName($name)
    {
        if ($name !== 'localizedfields') {
            throw new \Exception('Localizedfields can only be named `localizedfields`, no other names are allowed');
        }

        $this->name = $name;

        return $this;
    }

    /**
     * @param $region
     *
     * @return $this
     */
    public function setRegion($region)
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * @param $width
     *
     * @return $this
     */
    public function setWidth($width)
    {
        $this->width = $this->getAsIntegerCast($width);

        return $this;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
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
        $conf = \Pimcore\Config::getSystemConfig();
        if ($conf->general->validLanguages) {
            $languages = explode(',', $conf->general->validLanguages);
        }

        $data = $this->getDataForValidity($data, $languages);
        $validationExceptions = [];
        if (!$omitMandatoryCheck) {
            foreach ($languages as $language) {
                foreach ($this->getFieldDefinitions() as $fd) {
                    try {
                        if (isset($data[$language]) && isset($data[$language][$fd->getName()])) {
                            $fd->checkValidity($data[$language][$fd->getName()]);
                        } else {
                            $fd->checkValidity(null);
                        }
                    } catch (Model\Element\ValidationException $ve) {
                        $ve->addContext($this->getName() . '-' . $language);
                        $validationExceptions[] = $ve;
                    }
                }
            }
        }

        if (count($validationExceptions) > 0) {
            $aggregatedExceptions = new Model\Element\ValidationException();
            $aggregatedExceptions->setSubItems($validationExceptions);
            throw $aggregatedExceptions;
        }
    }

    /**
     * @param DataObject\Localizedfield|mixed $localizedObject
     * @param array $languages
     *
     * @return array
     */
    protected function getDataForValidity($localizedObject, array $languages)
    {
        if (!$localizedObject->getObject()
            || $localizedObject->getObject()->getType() != 'variant'
            || !$localizedObject instanceof DataObject\Localizedfield) {
            return $localizedObject->getInternalData(true);
        }

        //prepare data for variants
        $data = [];
        foreach ($languages as $language) {
            $data[$language] = [];
            foreach ($this->getFieldDefinitions() as $fd) {
                $data[$language][$fd->getName()] = $localizedObject->getLocalizedValue($fd->getName(), $language);
            }
        }

        return $data;
    }

    /** See parent class.
     * @param mixed $data
     * @param null $object
     * @param mixed $params
     *
     * @return array|null
     */
    public function getDiffDataForEditmode($data, $object = null, $params = [])
    {
        $return = [];

        $myname = $this->getName();

        if (!$data instanceof DataObject\Localizedfield) {
            return [];
        }

        foreach ($data->getInternalData(true) as $language => $values) {
            foreach ($this->getFieldDefinitions() as $fd) {
                $fieldname = $fd->getName();

                $subdata = $fd->getDiffDataForEditmode($values[$fieldname], $object, $params);

                foreach ($subdata as $item) {
                    $diffdata['field'] = $this->getName();
                    $diffdata['key'] = $this->getName().'~'.$fieldname.'~'.$item['key'].'~'.$language;

                    $diffdata['type'] = $item['type'];
                    $diffdata['value'] = $item['value'];

                    // this is not needed anymoe
                    unset($item['type']);
                    unset($item['value']);

                    $diffdata['title'] = $this->getName().' / '.$item['title'];
                    $diffdata['lang'] = $language;
                    $diffdata['data'] = $item;
                    $diffdata['extData'] = [
                        'fieldname' => $fieldname,
                    ];

                    $diffdata['disabled'] = $item['disabled'];
                    $return[] = $diffdata;
                }
            }
        }

        return $return;
    }

    /** See parent class.
     * @param $data
     * @param null $object
     * @param mixed $params
     *
     * @return DataObject\Localizedfield
     */
    public function getDiffDataFromEditmode($data, $object = null, $params = [])
    {
        $localFields = $this->getDataFromObjectParam($object, $params);
        $localData = [];

        // get existing data
        if ($localFields instanceof DataObject\Localizedfield) {
            $localData = $localFields->getInternalData(true);
        }

        $mapping = [];
        foreach ($data as $item) {
            $extData = $item['extData'];
            $fieldname = $extData['fieldname'];
            $language = $item['lang'];
            $values = $mapping[$fieldname];

            $itemdata = $item['data'];

            if ($itemdata) {
                if (!$values) {
                    $values = [];
                }

                $values[] = $itemdata;
            }

            $mapping[$language][$fieldname] = $values;
        }

        foreach ($mapping as $language => $fields) {
            foreach ($fields as $key => $value) {
                $fd = $this->getFielddefinition($key);
                if ($fd & $fd->isDiffChangeAllowed($object)) {
                    if ($value == null) {
                        unset($localData[$language][$key]);
                    } else {
                        $localData[$language][$key] = $fd->getDiffDataFromEditmode($value);
                    }
                }
            }
        }

        $localizedFields = new DataObject\Localizedfield($localData);
        $localizedFields->setObject($object);

        return $localizedFields;
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
     * Rewrites id from source to target, $idMapping contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     *
     * @param mixed $object
     * @param array $idMapping
     * @param array $params
     *
     * @return Model\Element\ElementInterface
     */
    public function rewriteIds($object, $idMapping, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);

        $validLanguages = Tool::getValidLanguages();

        foreach ($validLanguages as $language) {
            foreach ($this->getFieldDefinitions() as $fd) {
                if (method_exists($fd, 'rewriteIds')) {
                    $d = $fd->rewriteIds($data, $idMapping, ['language' => $language]);
                    $data->setLocalizedValue($fd->getName(), $d, $language);
                }
            }
        }

        return $data;
    }

    /**
     * @return mixed
     */
    public function getHideLabelsWhenTabsReached()
    {
        return $this->hideLabelsWhenTabsReached;
    }

    /**
     * @param mixed $hideLabelsWhenTabsReached
     *
     * @return $this
     */
    public function setHideLabelsWhenTabsReached($hideLabelsWhenTabsReached)
    {
        $this->hideLabelsWhenTabsReached = $hideLabelsWhenTabsReached;

        return $this;
    }

    /**
     * @param int $maxTabs
     */
    public function setMaxTabs($maxTabs)
    {
        $this->maxTabs = $maxTabs;
    }

    /**
     * @return int
     */
    public function getMaxTabs()
    {
        return $this->maxTabs;
    }

    /**
     * @param int $labelWidth
     */
    public function setLabelWidth($labelWidth)
    {
        $this->labelWidth = $labelWidth;
    }

    /**
     * @return int
     */
    public function getLabelWidth()
    {
        return $this->labelWidth;
    }

    /**
     * @return bool
     */
    public function getProvideSplitView()
    {
        return $this->provideSplitView;
    }

    /**
     * @param bool $provideSplitView
     */
    public function setProvideSplitView($provideSplitView): void
    {
        $this->provideSplitView = $provideSplitView;
    }

    /** Encode value for packing it into a single column.
     * @param mixed $value
     * @param Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return mixed
     */
    public function marshal($value, $object = null, $params = [])
    {
        if ($value instanceof DataObject\Localizedfield) {
            $items = $value->getInternalData();
            if (is_array($items)) {
                $result = [];
                foreach ($items as $language => $languageData) {
                    $languageResult = [];
                    foreach ($languageData as $elementName => $elementData) {
                        $fd = $this->getFielddefinition($elementName);
                        if (!$fd) {
                            // class definition seems to have changed
                            Logger::warn('class definition seems to have changed, element name: '.$elementName);
                            continue;
                        }

                        $dataForResource = $fd->marshal($elementData, $object, ['raw' => true]);

                        $languageResult[$elementName] = $dataForResource;
                    }

                    $result[$language] = $languageResult;
                }

                return $result;
            }
        }

        return null;
    }

    /** See marshal
     * @param mixed $value
     * @param Model\DataObject\AbstractObject $object
     * @param mixed $params
     *
     * @return mixed
     */
    public function unmarshal($value, $object = null, $params = [])
    {
        $lf = new DataObject\Localizedfield();
        $lf->setObject($object);
        if (is_array($value)) {
            $items = [];
            foreach ($value as $language => $languageData) {
                $languageResult = [];
                foreach ($languageData as $elementName => $elementData) {
                    $fd = $this->getFielddefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: '.$elementName);
                        continue;
                    }

                    $dataFromResource = $fd->unmarshal($elementData, $object, ['raw' => true]);

                    $languageResult[$elementName] = $dataFromResource;
                }

                $items[$language] = $languageResult;
            }

            $lf->setItems($items);
        }

        return $lf;
    }

    /**
     * @return bool
     */
    public function supportsDirtyDetection()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getTabPosition(): string
    {
        return $this->tabPosition;
    }

    /**
     * @param string $tabPosition
     */
    public function setTabPosition($tabPosition): void
    {
        $this->tabPosition = $tabPosition;
    }
}
