<?php

namespace Pimcore\Tests\Model\LazyLoading;

use Pimcore\Cache;
use Pimcore\Model\DataObject\Data\BlockElement;
use Pimcore\Model\DataObject\Data\ElementMetadata;
use Pimcore\Model\DataObject\Fieldcollection;
use Pimcore\Model\DataObject\LazyLoading;
use Pimcore\Model\DataObject\Objectbrick\Data\LazyLoadingLocalizedTest;
use Pimcore\Model\DataObject\Objectbrick\Data\LazyLoadingTest;

class AdvancedManyToManyRelationTest extends AbstractLazyLoadingTest
{
    protected function loadMetadataRelations($fieldname)
    {
        $relations = $this->loadRelations();

        $metaDataList = [];
        foreach ($relations as $relation) {
            $objectMetadata = new ElementMetadata($fieldname, ['metadata'], $relation);
            $objectMetadata->setMetaData('some-metadata');
            $metaDataList[] = $objectMetadata;
        }

        return $metaDataList;
    }

    protected function checkSerialization(LazyLoading $object, string $messagePrefix, bool $contentShouldBeIncluded = false)
    {
        parent::checkSerialization($object, $messagePrefix, false);
        $serializedString = serialize($object);
        $this->checkSerializedStringForNeedle($serializedString, 'some-metadata', $contentShouldBeIncluded, $messagePrefix);
    }

    public function testClassAttributes()
    {
        //prepare data object

        $object = $this->createDataObject();
        $object->setAdvancedRelations($this->loadMetadataRelations('advancedRelations'));
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix);

            //load relation and check if relation loads correctly
            $relationObjects = $object->getAdvancedRelations();
            $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

            //check if relation meta data is there
            $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix);
        }
    }

    public function testLocalizedClassAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();
        $object->setLadvancedRelations($this->loadMetadataRelations('ladvancedRelations'));
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix);

            //load relation and check if relation loads correctly
            $relationObjects = $object->getLadvancedRelations();
            $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

            //check if relation meta data is there
            $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix);
        }
    }

    public function testBlockClassAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();
        $data = [
            'blockadvancedRelations' => new BlockElement('blockadvancedRelations', 'advancedManyToManyRelation', $this->loadMetadataRelations('blockadvancedRelations')),
        ];
        $object->setTestBlock([$data]);
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            // inherited data isn't assigned to a property, it's only returned by the getter and therefore doesn't get serialized
            $contentShouldBeIncluded = ($objectType === 'inherited') ? false : true;

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, $contentShouldBeIncluded);

            //load relation and check if relation loads correctly
            $blockItems = $object->getTestBlock();
            $relationObjects = $blockItems[0]['blockadvancedRelations']->getData();
            $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

            //check if relation meta data is there
            $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, $contentShouldBeIncluded);
        }
    }

    public function testLazyBlockClassAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();
        $data = [
            'blockadvancedRelationsLazyLoaded' => new BlockElement('blockadvancedRelationsLazyLoaded', 'advancedManyToManyRelation', $this->loadMetadataRelations('blockadvancedRelationsLazyLoaded')),
        ];
        $object->setTestBlockLazyloaded([$data]);
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix);

            //load relation and check if relation loads correctly
            $blockItems = $object->getTestBlockLazyloaded();
            $relationObjects = $blockItems[0]['blockadvancedRelationsLazyLoaded']->getData();
            $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

            //check if relation meta data is there
            $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix);
        }
    }

    public function testFieldCollectionAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();

        $items = new Fieldcollection();
        $item = new Fieldcollection\Data\LazyLoadingTest();
        $item->setAdvancedRelations($this->loadMetadataRelations('advancedRelations'));
        $items->add($item);
        $object->setFieldcollection($items);
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);

            //load relation and check if relation loads correctly
            $collection = $object->getFieldcollection();
            if ($objectType == 'parent') {
                $item = $collection->get(0);
                $relationObjects = $item->getAdvancedRelations();
                $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

                //check if relation meta data is there
                $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');
            }

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);
        }
    }

    public function testFieldCollectionLocalizedAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();

        $items = new Fieldcollection();
        $item = new Fieldcollection\Data\LazyLoadingLocalizedTest();
        $item->setLadvancedRelations($this->loadMetadataRelations('ladvancedRelations'));
        $items->add($item);
        $object->setFieldcollection($items);
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);

            //load relation and check if relation loads correctly
            $collection = $object->getFieldcollection();
            if ($objectType == 'parent') {
                $item = $collection->get(0);
                $relationObjects = $item->getLadvancedRelations();
                $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

                //check if relation meta data is there
                $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');
            }

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);
        }
    }

    public function testBrickAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();
        $brick = new LazyLoadingTest($object);
        $brick->setAdvancedRelations($this->loadMetadataRelations('advancedRelations'));
        $object->getBricks()->setLazyLoadingTest($brick);
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);

            //load relation and check if relation loads correctly
            $brick = $object->getBricks()->getLazyLoadingTest();
            $relationObjects = $brick->getAdvancedRelations();
            $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

            //check if relation meta data is there
            $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);
        }
    }

    public function testLocalizedBrickAttributes()
    {
        //prepare data object
        $object = $this->createDataObject();
        $brick = new LazyLoadingLocalizedTest($object);
        $brick->getLocalizedfields()->setLocalizedValue('ladvancedRelations', $this->loadMetadataRelations('ladvancedRelations'));
        $object->getBricks()->setLazyLoadingLocalizedTest($brick);
        $object->save();
        $parentId = $object->getId();
        $childId = $this->createChildDataObject($object)->getId();

        foreach (['parent' => $parentId, 'inherited' => $childId] as $objectType => $id) {
            $messagePrefix = "Testing object-type $objectType: ";

            //clear cache and collect garbage
            Cache::clearAll();
            \Pimcore::collectGarbage();

            //reload data object from database
            $object = LazyLoading::getById($id, true);

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);

            //load relation and check if relation loads correctly
            $brick = $object->getBricks()->getLazyLoadingLocalizedTest();
            $relationObjects = $brick->getLocalizedFields()->getLocalizedValue('ladvancedRelations');
            $this->assertEquals(self::RELATION_COUNT, count($relationObjects), $messagePrefix . 'relations not loaded properly');

            //check if relation meta data is there
            $this->assertEquals($relationObjects[2]->getMetaData(), 'some-metadata', $messagePrefix . 'relations metadata not loaded properly');

            //serialize data object and check for (not) wanted content in serialized string
            $this->checkSerialization($object, $messagePrefix, false);
        }
    }
}
