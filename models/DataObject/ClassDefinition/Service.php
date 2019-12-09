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

namespace Pimcore\Model\DataObject\ClassDefinition;

use Pimcore\Loader\ImplementationLoader\LoaderInterface;
use Pimcore\Logger;
use Pimcore\Model\DataObject;
use Pimcore\Model\Webservice;

/**
 * Class Service
 *
 * @package Pimcore\Model\DataObject\ClassDefinition
 */
class Service
{
    /**
     * @static
     *
     * @param  DataObject\ClassDefinition $class
     *
     * @return string
     */
    public static function generateClassDefinitionJson($class)
    {
        $data = Webservice\Data\Mapper::map($class, '\\Pimcore\\Model\\Webservice\\Data\\ClassDefinition\\Out', 'out');
        unset($data->name);
        unset($data->creationDate);
        unset($data->modificationDate);
        unset($data->userOwner);
        unset($data->userModification);
        unset($data->fieldDefinitions);

        self::removeDynamicOptionsFromLayoutDefinition($data->layoutDefinitions);

        //add propertyVisibility to export data
        $data->propertyVisibility = $class->propertyVisibility;

        $json = json_encode($data, JSON_PRETTY_PRINT);

        return $json;
    }

    public static function removeDynamicOptionsFromLayoutDefinition(&$layout)
    {
        if (method_exists($layout, 'getChilds')) {
            $children = $layout->getChildren();
            if (is_array($children)) {
                foreach ($children as $child) {
                    if ($child instanceof DataObject\ClassDefinition\Data\Select) {
                        if ($child->getOptionsProviderClass()) {
                            $child->options = null;
                        }
                    }
                    self::removeDynamicOptionsFromLayoutDefinition($child);
                }
            }
        }
    }

    /**
     * @param DataObject\ClassDefinition $class
     * @param string $json
     * @param bool $throwException
     *
     * @return bool
     */
    public static function importClassDefinitionFromJson($class, $json, $throwException = false, $ignoreId = false)
    {
        $userId = 0;
        $user = \Pimcore\Tool\Admin::getCurrentUser();
        if ($user) {
            $userId = $user->getId();
        }

        $importData = json_decode($json, true);

        if ($importData['layoutDefinitions'] !== null) {
            // set layout-definition
            $layout = self::generateLayoutTreeFromArray($importData['layoutDefinitions'], $throwException);
            if ($layout === false) {
                return false;
            }
            $class->setLayoutDefinitions($layout);
        }

        // set properties of class
        if (isset($importData['id']) && $importData['id'] && !$ignoreId) {
            $class->setId($importData['id']);
        }
        $class->setModificationDate(time());
        $class->setUserModification($userId);

        foreach (['description', 'icon', 'group', 'allowInherit', 'allowVariants', 'showVariants', 'parentClass',
                    'listingParentClass', 'useTraits', 'listingUseTraits', 'previewUrl', 'propertyVisibility',
                    'linkGeneratorReference'] as $importPropertyName) {
            if (isset($importData[$importPropertyName])) {
                $class->{'set' . ucfirst($importPropertyName)}($importData[$importPropertyName]);
            }
        }

        $class->save();

        return true;
    }

    /**
     * @param DataObject\Fieldcollection\Definition $fieldCollection
     *
     * @return string
     */
    public static function generateFieldCollectionJson($fieldCollection)
    {
        $fieldCollection->setKey(null);
        $fieldCollection->setFieldDefinitions(null);

        $json = json_encode($fieldCollection, JSON_PRETTY_PRINT);

        return $json;
    }

    /**
     * @param DataObject\Fieldcollection\Definition $fieldCollection
     * @param string $json
     * @param bool $throwException
     *
     * @return bool
     */
    public static function importFieldCollectionFromJson($fieldCollection, $json, $throwException = false)
    {
        $importData = json_decode($json, true);

        if (!is_null($importData['layoutDefinitions'])) {
            $layout = self::generateLayoutTreeFromArray($importData['layoutDefinitions'], $throwException);
            $fieldCollection->setLayoutDefinitions($layout);
        }

        foreach (['parentClass', 'title', 'group'] as $importPropertyName) {
            if (isset($importData[$importPropertyName])) {
                $fieldCollection->{'set' . ucfirst($importPropertyName)}($importData[$importPropertyName]);
            }
        }

        $fieldCollection->save();

        return true;
    }

    /**
     * @param DataObject\Objectbrick\Definition $objectBrick
     *
     * @return string
     */
    public static function generateObjectBrickJson($objectBrick)
    {
        $objectBrick->setKey(null);
        $objectBrick->setFieldDefinitions(null);

        // set classname attribute to the real class name not to the class ID
        // this will allow to import the brick on a different instance with identical class names but different class IDs
        if (is_array($objectBrick->classDefinitions)) {
            foreach ($objectBrick->classDefinitions as &$cd) {
                // for compatibility (upgraded pimcore4s that may deliver class ids in $cd['classname'] we need to
                // get the class by id in order to be able to correctly set the classname for the generated json
                if (!$class = DataObject\ClassDefinition::getByName($cd['classname'])) {
                    $class = DataObject\ClassDefinition::getById($cd['classname']);
                }

                if ($class) {
                    $cd['classname'] = $class->getName();
                }
            }
        }

        $json = json_encode($objectBrick, JSON_PRETTY_PRINT);

        return $json;
    }

    /**
     * @param DataObject\Objectbrick\Definition $objectBrick
     * @param string $json
     * @param bool $throwException
     *
     * @return bool
     */
    public static function importObjectBrickFromJson($objectBrick, $json, $throwException = false)
    {
        $importData = json_decode($json, true);

        // reverse map the class name to the class ID, see: self::generateObjectBrickJson()
        $toAssignClassDefinitions = [];
        if (is_array($importData['classDefinitions'])) {
            foreach ($importData['classDefinitions'] as &$cd) {
                if (is_numeric($cd['classname'])) {
                    $class = DataObject\ClassDefinition::getById($cd['classname']);
                    if ($class) {
                        $cd['classname'] = $class->getName();
                        $toAssignClassDefinitions[] = $cd;
                    }
                } else {
                    $class = DataObject\ClassDefinition::getByName($cd['classname']);
                    if ($class) {
                        $toAssignClassDefinitions[] = $cd;
                    }
                }
            }
        }

        if ($importData['layoutDefinitions'] !== null) {
            $layout = self::generateLayoutTreeFromArray($importData['layoutDefinitions'], $throwException);
            $objectBrick->setLayoutDefinitions($layout);
        }

        $objectBrick->setClassDefinitions($toAssignClassDefinitions);
        $objectBrick->setParentClass($importData['parentClass']);
        if (isset($importData['title'])) {
            $objectBrick->setTitle($importData['title']);
        }
        if (isset($importData['group'])) {
            $objectBrick->setGroup($importData['group']);
        }
        $objectBrick->save();

        return true;
    }

    /**
     * @param array $array
     * @param bool $throwException
     * @param bool $insideLocalizedField
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function generateLayoutTreeFromArray($array, $throwException = false, $insideLocalizedField = false)
    {
        if (is_array($array) && count($array) > 0) {
            /** @var LoaderInterface $loader */
            $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.object.' . $array['datatype']);

            if ($loader->supports($array['fieldtype'])) {
                $item = $loader->build($array['fieldtype']);

                $insideLocalizedField = $insideLocalizedField || $item instanceof DataObject\ClassDefinition\Data\Localizedfields;

                if (method_exists($item, 'addChild')) { // allows childs
                    $item->setValues($array, ['childs']);
                    $childs = $array['childs'] ?? [];

                    if (!empty($childs['datatype'])) {
                        $childO = self::generateLayoutTreeFromArray($childs, $throwException, $insideLocalizedField);
                        $item->addChild($childO);
                    } elseif (is_array($childs) && count($childs) > 0) {
                        foreach ($childs as $child) {
                            $childO = self::generateLayoutTreeFromArray($child, $throwException, $insideLocalizedField);
                            if ($childO !== false) {
                                $item->addChild($childO);
                            } else {
                                if ($throwException) {
                                    throw new \Exception('Could not add child ' . var_export($child, true));
                                }

                                Logger::err('Could not add child ' . var_export($child, true));

                                return false;
                            }
                        }
                    }
                } else {
                    $item->setValues($array);

                    if ($item instanceof DataObject\ClassDefinition\Data\EncryptedField) {
                        $item->setupDelegate($array);
                    }
                }

                return $item;
            }
        }
        if ($throwException) {
            throw new \Exception('Could not add child ' . var_export($array, true));
        }

        return false;
    }

    /**
     * @param array $tableDefinitions
     * @param array $tableNames
     */
    public static function updateTableDefinitions(&$tableDefinitions, $tableNames)
    {
        if (!is_array($tableDefinitions)) {
            $tableDefinitions = [];
        }

        $db = \Pimcore\Db::get();
        $tmp = [];
        foreach ($tableNames as $tableName) {
            $tmp[$tableName] = $db->fetchAll('show columns from ' . $tableName);
        }

        foreach ($tmp as $tableName => $columns) {
            foreach ($columns as $column) {
                $column['Type'] = strtolower($column['Type']);
                if (strtolower($column['Null']) === 'yes') {
                    $column['Null'] = 'null';
                }
                //                $fieldName = strtolower($column["Field"]);
                $fieldName = $column['Field'];
                $tableDefinitions[$tableName][$fieldName] = $column;
            }
        }
    }

    /**
     * @param $tableDefinitions
     * @param $table
     * @param $colName
     * @param $type
     * @param $default
     * @param $null
     *
     * @return bool
     */
    public static function skipColumn($tableDefinitions, $table, $colName, $type, $default, $null)
    {
        $tableDefinition = $tableDefinitions[$table] ?? false;
        if ($tableDefinition) {
            $colDefinition = $tableDefinition[$colName];
            if ($colDefinition) {
                if (!strlen($default) && strtolower($null) === 'null') {
                    $default = null;
                }

                if ($colDefinition['Type'] == $type && strtolower($colDefinition['Null']) == strtolower($null)
                    && $colDefinition['Default'] == $default) {
                    return true;
                }
            }
        }

        return false;
    }
}
