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
 * @package    DataObject\Fieldcollection
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\Fieldcollection;

use Pimcore\File;
use Pimcore\Model;
use Pimcore\Model\DataObject;

/**
 * @method \Pimcore\Model\DataObject\Fieldcollection\Definition\Dao getDao()
 */
class Definition extends Model\AbstractModel
{
    use Model\DataObject\ClassDefinition\Helper\VarExport;

    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $parentClass;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $group;

    /**
     * @var array
     */
    public $layoutDefinitions;

    /**
     * @var DataObject\ClassDefinition\Data[]
     */
    protected $fieldDefinitions;

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $key
     *
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string
     */
    public function getParentClass()
    {
        return $this->parentClass;
    }

    /**
     * @param string $parentClass
     *
     * @return $this
     */
    public function setParentClass($parentClass)
    {
        $this->parentClass = $parentClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return array
     */
    public function getLayoutDefinitions()
    {
        return $this->layoutDefinitions;
    }

    /**
     * @param array $layoutDefinitions
     *
     * @return $this
     */
    public function setLayoutDefinitions($layoutDefinitions)
    {
        $this->layoutDefinitions = $layoutDefinitions;

        $this->fieldDefinitions = [];
        $this->extractDataDefinitions($this->layoutDefinitions);

        return $this;
    }

    /**
     * @param array $context additional contextual data
     *
     * @return array
     */
    public function getFieldDefinitions($context = [])
    {
        if (!\Pimcore::inAdmin() || (isset($context['suppressEnrichment']) && $context['suppressEnrichment'])) {
            return $this->fieldDefinitions;
        }

        $enrichedFieldDefinitions = [];
        if (is_array($this->fieldDefinitions)) {
            foreach ($this->fieldDefinitions as $key => $fieldDefinition) {
                $fieldDefinition = $this->doEnrichFieldDefinition($fieldDefinition, $context);
                $enrichedFieldDefinitions[$key] = $fieldDefinition;
            }
        }

        return $enrichedFieldDefinitions;
    }

    /**
     * @param array $fieldDefinitions
     *
     * @return $this
     */
    public function setFieldDefinitions($fieldDefinitions)
    {
        $this->fieldDefinitions = $fieldDefinitions;

        return $this;
    }

    /**
     * @param string $key
     * @param DataObject\ClassDefinition\Data $data
     *
     * @return $this
     */
    public function addFieldDefinition($key, $data)
    {
        $this->fieldDefinitions[$key] = $data;

        return $this;
    }

    /**
     * @param $key
     * @param array $context additional contextual data
     *
     * @return DataObject\ClassDefinition\Data|bool
     */
    public function getFieldDefinition($key, $context = [])
    {
        if (is_array($this->fieldDefinitions) && array_key_exists($key, $this->fieldDefinitions)) {
            if (!\Pimcore::inAdmin() || (isset($context['suppressEnrichment']) && $context['suppressEnrichment'])) {
                return $this->fieldDefinitions[$key];
            }

            $fieldDefinition = $this->doEnrichFieldDefinition($this->fieldDefinitions[$key], $context);

            return $fieldDefinition;
        }

        return false;
    }

    protected function doEnrichFieldDefinition($fieldDefinition, $context = [])
    {
        if (method_exists($fieldDefinition, 'enrichFieldDefinition')) {
            $context['containerType'] = 'fieldcollection';
            $context['containerKey'] = $this->getKey();
            $fieldDefinition = $fieldDefinition->enrichFieldDefinition($context);
        }

        return $fieldDefinition;
    }

    /**
     * @param array|DataObject\ClassDefinition\Layout|DataObject\ClassDefinition\Data $def
     */
    public function extractDataDefinitions($def)
    {
        if ($def instanceof DataObject\ClassDefinition\Layout) {
            if ($def->hasChildren()) {
                foreach ($def->getChildren() as $child) {
                    $this->extractDataDefinitions($child);
                }
            }
        }

        if ($def instanceof DataObject\ClassDefinition\Data) {
            $existing = $this->getFieldDefinition($def->getName());
            if ($existing && method_exists($existing, 'addReferencedField')) {
                // this is especially for localized fields which get aggregated here into one field definition
                // in the case that there are more than one localized fields in the class definition
                // see also pimcore.object.edit.addToDataFields();
                $existing->addReferencedField($def);
            } else {
                $this->addFieldDefinition($def->getName(), $def);
            }
        }
    }

    /**
     * @param $key
     *
     * @throws \Exception
     *
     * @return self|null
     */
    public static function getByKey($key)
    {
        /** @var $fc Definition */
        $fc = null;
        $cacheKey = 'fieldcollection_' . $key;

        try {
            $fc = \Pimcore\Cache\Runtime::get($cacheKey);
            if (!$fc) {
                throw new \Exception('FieldCollection in registry is not valid');
            }
        } catch (\Exception $e) {
            $fieldCollectionFolder = PIMCORE_CLASS_DIRECTORY . '/fieldcollections';
            $fieldFile = $fieldCollectionFolder . '/' . $key . '.php';

            if (is_file($fieldFile)) {
                $fc = include $fieldFile;
                \Pimcore\Cache\Runtime::set($cacheKey, $fc);
            }
        }

        if ($fc) {
            return $fc;
        }

        return null;
    }

    /**
     * @param bool $saveDefinitionFile
     *
     * @throws \Exception
     */
    public function save($saveDefinitionFile = true)
    {
        if (!$this->getKey()) {
            throw new \Exception('A field-collection needs a key to be saved!');
        }

        if (!preg_match('/[a-zA-Z]+/', $this->getKey())) {
            throw new \Exception(sprintf('Invalid key for field-collection: %s', $this->getKey()));
        }

        if ($this->getParentClass() && !preg_match('/^[a-zA-Z_\x7f-\xff\\\][a-zA-Z0-9_\x7f-\xff\\\]*$/', $this->getParentClass())) {
            throw new \Exception(sprintf('Invalid parentClass value for class definition: %s',
                $this->getParentClass()));
        }

        $infoDocBlock = $this->getInfoDocBlock();

        $definitionFile = $this->getDefinitionFile();

        if ($saveDefinitionFile) {
            $clone = clone $this;
            $clone->setDao(null);
            unset($clone->fieldDefinitions);
            DataObject\ClassDefinition::cleanupForExport($clone->layoutDefinitions);

            $exportedClass = var_export($clone, true);

            $data = '<?php ';
            $data .= "\n\n";
            $data .= $infoDocBlock;
            $data .= "\n\n";

            $data .= "\nreturn " . $exportedClass . ";\n";

            \Pimcore\File::put($definitionFile, $data);
        }

        $extendClass = 'DataObject\\Fieldcollection\\Data\\AbstractData';
        if ($this->getParentClass()) {
            $extendClass = $this->getParentClass();
            $extendClass = '\\' . ltrim($extendClass, '\\');
        }

        // create class file
        $cd = '<?php ';
        $cd .= "\n\n";
        $cd .= $infoDocBlock;
        $cd .= "\n\n";
        $cd .= 'namespace Pimcore\\Model\\DataObject\\Fieldcollection\\Data;';
        $cd .= "\n\n";
        $cd .= 'use Pimcore\\Model\\DataObject;';
        $cd .= "\n";
        $cd .= 'use Pimcore\Model\DataObject\PreGetValueHookInterface;';
        $cd .= "\n\n";

        $cd .= 'class ' . ucfirst($this->getKey()) . ' extends ' . $extendClass . ' implements \\Pimcore\\Model\\DataObject\\DirtyIndicatorInterface {';

        $cd .= "\n\n";
        $cd .= 'use \\Pimcore\\Model\\DataObject\\Traits\\DirtyIndicatorTrait;';
        $cd .= "\n\n";

        $cd .= 'protected $type = "' . $this->getKey() . "\";\n";

        if (is_array($this->getFieldDefinitions()) && count($this->getFieldDefinitions())) {
            foreach ($this->getFieldDefinitions() as $key => $def) {
                $cd .= 'protected $' . $key . ";\n";
            }
        }

        $cd .= "\n\n";

        $fdDefs = $this->getFieldDefinitions();
        if (is_array($fdDefs) && count($fdDefs)) {
            foreach ($fdDefs as $key => $def) {

                /**
                 * @var $def DataObject\ClassDefinition\Data
                 */
                $cd .= $def->getGetterCodeFieldcollection($this);

                if ($def instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                    $cd .= $def->getGetterCode($this);
                }

                $cd .= $def->getSetterCodeFieldcollection($this);

                if ($def instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                    $cd .= $def->getSetterCode($this);
                }
            }
        }

        $cd .= "}\n";
        $cd .= "\n";

        File::put($this->getPhpClassFile(), $cd);

        // update classes
        $classList = new DataObject\ClassDefinition\Listing();
        $classes = $classList->load();
        if (is_array($classes)) {
            foreach ($classes as $class) {
                foreach ($class->getFieldDefinitions() as $fieldDef) {
                    if ($fieldDef instanceof DataObject\ClassDefinition\Data\Fieldcollections) {
                        if (in_array($this->getKey(), $fieldDef->getAllowedTypes())) {
                            $this->getDao()->createUpdateTable($class);
                            break;
                        }
                    }
                }
            }
        }
    }

    public function delete()
    {
        @unlink($this->getDefinitionFile());
        @unlink($this->getPhpClassFile());

        // update classes
        $classList = new DataObject\ClassDefinition\Listing();
        $classes = $classList->load();
        if (is_array($classes)) {
            foreach ($classes as $class) {
                foreach ($class->getFieldDefinitions() as $fieldDef) {
                    if ($fieldDef instanceof DataObject\ClassDefinition\Data\Fieldcollections) {
                        if (in_array($this->getKey(), $fieldDef->getAllowedTypes())) {
                            $this->getDao()->delete($class);
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return string
     */
    protected function getDefinitionFile()
    {
        $fieldClassFolder = PIMCORE_CLASS_DIRECTORY . '/fieldcollections';
        $definitionFile = $fieldClassFolder . '/' . $this->getKey() . '.php';

        return $definitionFile;
    }

    /**
     * @return string
     */
    protected function getPhpClassFile()
    {
        $classFolder = PIMCORE_CLASS_DIRECTORY . '/DataObject/Fieldcollection/Data';
        $classFile = $classFolder . '/' . ucfirst($this->getKey()) . '.php';

        return $classFile;
    }

    /**
     * @return string
     */
    protected function getInfoDocBlock()
    {
        $cd = '';

        $cd .= '/** ';
        $cd .= "\n";
        $cd .= '* Generated at: ' . date('c') . "\n";

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $cd .= '* IP: ' . $_SERVER['REMOTE_ADDR'] . "\n";
        }

        $cd .= "\n\n";
        $cd .= "Fields Summary: \n";

        $cd = $this->getInfoDocBlockForFields($this, $cd, 1);

        $cd .= '*/ ';

        return $cd;
    }

    /**
     * @param $definition
     * @param $text
     * @param $level
     *
     * @return string
     */
    protected function getInfoDocBlockForFields($definition, $text, $level)
    {
        if (is_array($definition->getFieldDefinitions())) {
            foreach ($definition->getFieldDefinitions() as $fd) {
                $text .= str_pad('', $level, '-') . ' ' . $fd->getName() . ' [' . $fd->getFieldtype() . "]\n";
                if (method_exists($fd, 'getFieldDefinitions')) {
                    $text = $this->getInfoDocBlockForFields($fd, $text, $level + 1);
                }
            }
        }

        return $text;
    }

    /**
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param string $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }
}
