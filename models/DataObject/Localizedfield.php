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

use Pimcore\Model;
use Pimcore\Tool;

/**
 * @method \Pimcore\Model\DataObject\Localizedfield\Dao getDao()
 */
class Localizedfield extends Model\AbstractModel implements DirtyIndicatorInterface
{
    use Model\DataObject\Traits\DirtyIndicatorTrait;

    const STRICT_DISABLED = 0;

    const STRICT_ENABLED = 1;

    /**
     * @var bool
     */
    private static $getFallbackValues = false;

    /**
     * @var array
     */
    public $items = [];

    /**
     * @var Model\DataObject\Concrete
     */
    public $object;

    /**
     * @var Model\DataObject\ClassDefinition
     */
    public $class;

    /** @var mixed */
    public $context;

    /** @var int */
    protected $objectId;

    /**
     * @var bool
     */
    private static $strictMode;

    /** @var
     * list of dirty languages. if null then no language is dirty. if empty array then all languages are dirty
     */
    protected $o_dirtyLanguages;

    /**
     * @param bool $getFallbackValues
     */
    public static function setGetFallbackValues($getFallbackValues)
    {
        self::$getFallbackValues = $getFallbackValues;
    }

    /**
     * @return bool
     */
    public static function getGetFallbackValues()
    {
        return self::$getFallbackValues;
    }

    /**
     * @return bool
     */
    public static function isStrictMode()
    {
        return self::$strictMode;
    }

    /**
     * @param bool $strictMode
     */
    public static function setStrictMode($strictMode)
    {
        self::$strictMode = $strictMode;
    }

    /**
     * @return bool
     */
    public static function doGetFallbackValues()
    {
        return self::$getFallbackValues;
    }

    /**
     * @param array $items
     */
    public function __construct($items = null)
    {
        if ($items) {
            $this->setItems($items);
        }
        $this->markFieldDirty('_self');
        $this->markAllLanguagesAsDirty();
    }

    /**
     * @param  $item
     */
    public function addItem($item)
    {
        $this->items[] = $item;
        $this->markFieldDirty('_self');
        $this->markAllLanguagesAsDirty();
    }

    /**
     * @param  array $items
     *
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = $items;
        $this->markFieldDirty('_self');
        $this->markAllLanguagesAsDirty();

        return $this;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param Concrete $object
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setObject($object, $markAsDirty = true)
    {
        if ($object && !$object instanceof Concrete) {
            throw new \Exception('must be instance of object concrete');
        }
        if ($markAsDirty) {
            $this->markAllLanguagesAsDirty();
        }
        $this->object = $object;
        $this->objectId = $object ? $object->getId() : null;
        $this->setClass($object ? $object->getClass() : null);

        return $this;
    }

    /**
     * @return Concrete
     */
    public function getObject()
    {
        if ($this->objectId && !$this->object) {
            $this->setObject(Concrete::getById($this->objectId));
        }

        return $this->object;
    }

    /**
     * @param Model\DataObject\ClassDefinition $class
     *
     * @return $this
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return Model\DataObject\ClassDefinition
     */
    public function getClass()
    {
        if (!$this->class && $this->getObject()) {
            $this->class = $this->getObject()->getClass();
        }

        return $this->class;
    }

    /**
     * @throws \Exception
     *
     * @param null $language
     *
     * @return string
     */
    public function getLanguage($language = null)
    {
        if ($language) {
            return (string) $language;
        }

        // try to get the language from the service container
        try {
            $locale = \Pimcore::getContainer()->get('pimcore.locale')->getLocale();

            if (Tool::isValidLanguage($locale)) {
                return (string) $locale;
            }
            throw new \Exception('Not supported language');
        } catch (\Exception $e) {
            return Tool::getDefaultLanguage();
        }
    }

    /**
     * @param $language
     *
     * @return bool
     */
    public function languageExists($language)
    {
        return array_key_exists($language, $this->getItems());
    }

    /**
     * @param $name
     * @param null $language
     * @param bool $ignoreFallbackLanguage
     *
     * @return mixed
     */
    public function getLocalizedValue($name, $language = null, $ignoreFallbackLanguage = false)
    {
        $data = null;
        $language = $this->getLanguage($language);

        $context = $this->getContext();
        if ($context && $context['containerType'] == 'fieldcollection') {
            $containerKey = $context['containerKey'];
            $container = Model\DataObject\Fieldcollection\Definition::getByKey($containerKey);
        } elseif ($context && $context['containerType'] == 'objectbrick') {
            $containerKey = $context['containerKey'];
            $container = Model\DataObject\Objectbrick\Definition::getByKey($containerKey);
        } elseif ($context && $context['containerType'] == 'block') {
            $containerKey = $context['containerKey'];
            $object = $this->getObject();
            $blockDefinition = $object->getClass()->getFieldDefinition($containerKey);
            $container = $blockDefinition;
        } else {
            $object = $this->getObject();
            $container = $object->getClass();
        }
        $fieldDefinition = $container->getFieldDefinition('localizedfields')->getFieldDefinition($name);

        if ($fieldDefinition instanceof Model\DataObject\ClassDefinition\Data\CalculatedValue) {
            $valueData = new Model\DataObject\Data\CalculatedValue($fieldDefinition->getName());
            $valueData->setContextualData('localizedfield', 'localizedfields', null, $language);
            $data = Service::getCalculatedFieldValue($this->getObject(), $valueData);

            return $data;
        }

        if ($this->languageExists($language)) {
            if (array_key_exists($name, $this->items[$language])) {
                $data = $this->items[$language][$name];
            }
        }

        // check for inherited value
        $doGetInheritedValues = AbstractObject::doGetInheritedValues();

        $allowInheritance = $fieldDefinition->supportsInheritance();
        if ($context && ($context['containerType'] == 'block' || $context['containerType'] == 'fieldcollection')) {
            $allowInheritance = false;
        }

        if ($fieldDefinition->isEmpty($data) && $doGetInheritedValues && $allowInheritance && $this->getObject() instanceof Concrete) {
            $object = $this->getObject();
            $class = $object->getClass();
            $allowInherit = $class->getAllowInherit();

            if ($allowInherit) {
                if ($object->getParent() instanceof AbstractObject) {
                    $parent = $object->getParent();
                    while ($parent && $parent->getType() == 'folder') {
                        $parent = $parent->getParent();
                    }

                    if ($parent && ($parent->getType() == 'object' || $parent->getType() == 'variant')) {
                        if ($parent->getClassId() == $object->getClassId()) {
                            $method = 'getLocalizedfields';

                            $parentContainer = $parent;

                            if ($context && $context['containerType'] == 'objectbrick') {
                                $brickContainerGetter = 'get' . ucfirst($context['fieldname']);
                                $brickContainer = $parent->$brickContainerGetter();
                                $brickGetter = 'get' . $context['containerKey'];
                                $brickData = $brickContainer->$brickGetter();
                                $parentContainer = $brickData;
                            }

                            if (method_exists($parentContainer, $method)) {
                                $localizedFields = $parentContainer->getLocalizedFields();
                                if ($localizedFields instanceof Localizedfield) {
                                    if ($localizedFields->getObject()->getId() != $this->getObject()->getId()) {
                                        $data = $localizedFields->getLocalizedValue($name, $language, true);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // check for fallback value
        if ($fieldDefinition->isEmpty($data) && !$ignoreFallbackLanguage && self::doGetFallbackValues()) {
            foreach (Tool::getFallbackLanguagesFor($language) as $l) {
                if ($this->languageExists($l)) {
                    if (array_key_exists($name, $this->items[$l])) {
                        if ($data = $this->getLocalizedValue($name, $l)) {
                            break;
                        }
                    }
                }
            }
        }

        if ($fieldDefinition && method_exists($fieldDefinition, 'preGetData')) {
            $data = $fieldDefinition->preGetData(
                $this,
                [
                    'data' => $data,
                    'language' => $language,
                    'name' => $name,
                ]
            );
        }

        return $data;
    }

    /**
     * @param $name
     * @param $value
     * @param null $language
     * @param $markFieldAsDirty
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setLocalizedValue($name, $value, $language = null, $markFieldAsDirty = true)
    {
        if ($markFieldAsDirty) {
            $this->markFieldDirty('_self');
        }

        if (self::$strictMode) {
            if (!$language || !in_array($language, Tool::getValidLanguages())) {
                throw new \Exception('Language '.$language.' not accepted in strict mode');
            }
        }

        $language = $this->getLanguage($language);
        if (!$this->languageExists($language)) {
            $this->items[$language] = [];
            $this->markLanguageAsDirty($language);
        }

        $contextInfo = $this->getContext();
        if ($contextInfo && $contextInfo['containerType'] == 'block') {
            $classId = $contextInfo['classId'];
            $containerDefinition = ClassDefinition::getById($classId);
            $blockDefinition = $containerDefinition->getFieldDefinition($contextInfo['fieldname']);

            /** @var $fieldDefinition Model\DataObject\ClassDefinition\Data */
            $fieldDefinition = $blockDefinition->getFieldDefinition('localizedfields');
        } else {
            if ($contextInfo && $contextInfo['containerType'] == 'fieldcollection') {
                $containerKey = $contextInfo['containerKey'];
                $containerDefinition = Fieldcollection\Definition::getByKey($containerKey);
            } elseif ($contextInfo && $contextInfo['containerType'] == 'objectbrick') {
                $containerKey = $contextInfo['containerKey'];
                $containerDefinition = Model\DataObject\Objectbrick\Definition::getByKey($containerKey);
            } else {
                $containerDefinition = $this->getObject()->getClass();
            }

            $localizedFieldDefinition = $containerDefinition->getFieldDefinition('localizedfields');
            $fieldDefinition = $localizedFieldDefinition->getFieldDefinition($name, ['object' => $this->getObject()]);
        }

        if (method_exists($fieldDefinition, 'preSetData')) {
            $value = $fieldDefinition->preSetData(
                $this,
                $value,
                [
                    'language' => $language,
                    'name' => $name,
                ]
            );
        }

        if (!$fieldDefinition->isEqual($this->items[$language][$name], $value)) {
            $this->markLanguageAsDirty($language);
        }
        $this->items[$language][$name] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return ['items', 'context', 'objectId'];
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @return bool
     */
    public function hasDirtyLanguages()
    {
        if (AbstractObject::isDirtyDetectionDisabled()) {
            return true;
        }

        return is_array($this->o_dirtyLanguages) && count($this->o_dirtyLanguages) > 0;
    }

    /**
     * @param $language
     *
     * @return bool|mixed
     */
    public function isLanguageDirty($language)
    {
        if (AbstractObject::isDirtyDetectionDisabled()) {
            return true;
        }

        if (is_array($this->o_dirtyLanguages)) {
            if (count($this->o_dirtyLanguages) == 0) {
                return true;
            }
            if (isset($this->o_dirtyLanguages[$language])) {
                return $this->o_dirtyLanguages[$language];
            }
        }

        return false;
    }

    public function resetLanguageDirtyMap()
    {
        $this->o_dirtyLanguages = null;
    }

    public function getDirtyLanguages()
    {
        return $this->o_dirtyLanguages;
    }

    public function markAllLanguagesAsDirty()
    {
        $this->o_dirtyLanguages = [];
    }

    public function allLanguagesAreDirty()
    {
        if (AbstractObject::isDirtyDetectionDisabled()) {
            return true;
        }

        return is_array($this->o_dirtyLanguages) && count($this->o_dirtyLanguages) == 0;
    }

    /**
     * @param $language
     * @param $dirty
     */
    public function markLanguageAsDirty($language, $dirty = true)
    {
        if (!is_array($this->o_dirtyLanguages) && $dirty) {
            $this->o_dirtyLanguages = [];
        }

        if ($dirty) {
            $this->o_dirtyLanguages[$language] = true;
        }

        if (!$this->o_dirtyLanguages) {
            $this->o_dirtyLanguages = null;
        }
    }
}
