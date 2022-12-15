<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Tests\Model\DataObject;

use Pimcore\Model\DataObject;
use Pimcore\Model\Element\Service;
use Pimcore\Tests\Support\Test\ModelTestCase;
use Pimcore\Tests\Support\Util\TestHelper;

/**
 * Class ObjectTest
 *
 * @package Pimcore\Tests\Model\DataObject
 *
 * @group model.dataobject.object
 */
class ObjectTest extends ModelTestCase
{
    /**
     * Verifies that an object with the same parent ID cannot be created.
     */
    public function testParentIdentical()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("ParentID and ID are identical, an element can't be the parent of itself in the tree.");
        $savedObject = TestHelper::createEmptyObject();
        $this->assertTrue($savedObject->getId() > 0);

        $savedObject->setParentId($savedObject->getId());
        $savedObject->save();
    }

    /**
     * Verifies that object PHP API version note is saved
     */
    public function testSavingVersionNotes()
    {
        $versionNote = ['versionNote' => 'a new version of this object'];
        $this->testObject = TestHelper::createEmptyObject();
        $this->testObject->save($versionNote);
        $this->assertEquals($this->testObject->getLatestVersion(null, true)->getNote(), $versionNote['versionNote']);
    }

    /**
     * Parent ID of a new object cannot be 0
     */
    public function testParentIs0()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ParentID is mandatory and can´t be null. If you want to add the element as a child to the tree´s root node, consider setting ParentID to 1.');
        $savedObject = TestHelper::createEmptyObject('', false);
        $this->assertTrue($savedObject->getId() == 0);

        $savedObject->setParentId(0);
        $savedObject->save();
    }

    /**
     * Parent ID must resolve to an existing element
     *
     * @group notfound
     */
    public function testParentNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ParentID not found.');
        $savedObject = TestHelper::createEmptyObject('', false);
        $this->assertTrue($savedObject->getId() == 0);

        $savedObject->setParentId(999999);
        $savedObject->save();
    }

    /**
     * Verifies that children result should be cached based on parameters provided.
     *
     */
    public function testCacheUnpublishedChildren()
    {
        // create parent
        $parent = TestHelper::createEmptyObject();

        // create first child
        $firstChild = TestHelper::createEmptyObject('child-', false, false);
        $firstChild->setParentId($parent->getId());
        $firstChild->save();

        //without unpublished flag
        $child = $parent->getChildren();
        $this->assertEquals(0, count($child), 'Expected no child');

        $hasChild = $parent->hasChildren();
        $this->assertFalse($hasChild, 'hasChild property should be false');

        //with unpublished flag
        $child = $parent->getChildren([], true);
        $this->assertEquals(1, count($child), 'Expected 1 child');

        $hasChild = $parent->hasChildren([], true);
        $this->assertTrue($hasChild, 'hasChild property should be true');
    }

    /**
     * Verifies that siblings result should be cached based on parameters provided.
     *
     */
    public function testCacheUnpublishedSiblings()
    {
        // create parent
        $parent = TestHelper::createEmptyObject();

        // create first child
        $firstChild = TestHelper::createEmptyObject('child-', false);
        $firstChild->setParentId($parent->getId());
        $firstChild->save();

        // create first child
        $secondChild = TestHelper::createEmptyObject('child-', false, false);
        $secondChild->setParentId($parent->getId());
        $secondChild->save();

        //without unpublished flag
        $sibling = $firstChild->getSiblings();
        $this->assertEquals(0, count($sibling), 'Expected no sibling');

        $hasSibling = $firstChild->hasSiblings();
        $this->assertFalse($hasSibling, 'hasSiblings property should be false');

        //with unpublished flag
        $sibling = $firstChild->getSiblings([], true);
        $this->assertEquals(1, count($sibling), 'Expected 1 sibling');

        $hasSibling = $firstChild->hasSiblings([], true);
        $this->assertTrue($hasSibling, 'hasSiblings property should be true');
    }

    /**
     * Verifies that an object can be saved with custom user modification id.
     *
     */
    public function testCustomUserModification()
    {
        $userId = 101;
        $object = TestHelper::createEmptyObject();

        //custom user modification
        $object->setUserModification($userId);
        $object->save();
        $this->assertEquals($userId, $object->getUserModification(), 'Expected custom user modification id');

        //auto generated user modification
        $object = DataObject::getById($object->getId(), ['force' => true]);
        $object->save();
        $this->assertEquals(0, $object->getUserModification(), 'Expected auto assigned user modification id');
    }

    /**
     * Verifies that an object can be saved with custom modification date.
     *
     */
    public function testCustomModificationDate()
    {
        $customDateTime = new \Carbon\Carbon();
        $customDateTime = $customDateTime->subHour();

        $object = TestHelper::createEmptyObject();

        //custom modification date
        $object->setModificationDate($customDateTime->getTimestamp());
        $object->save();
        $this->assertEquals($customDateTime->getTimestamp(), $object->getModificationDate(), 'Expected custom modification date');

        //auto generated modification date
        $currentTime = time();
        $object = DataObject::getById($object->getId(), ['force' => true]);
        $object->save();
        $this->assertGreaterThanOrEqual($currentTime, $object->getModificationDate(), 'Expected auto assigned modification date');
    }

    /**
     * Verifies that when an object gets saved default values of fields get saved to the version
     */
    public function testDefaultValueSavedToVersion()
    {
        $object = TestHelper::createEmptyObject();
        $object->save();

        $versions = $object->getVersions();
        $latestVersion = end($versions);

        $this->assertEquals('default', $latestVersion->getData()->getInputWithDefault(), 'Expected default value saved to version');
    }

    /**
     * Verifies that when an object gets cloned, the fields get copied properly
     */
    public function testCloning()
    {
        $object = TestHelper::createEmptyObject('', false);
        $clone = Service::cloneMe($object);

        $object->setId(123);

        $this->assertEquals(null, $clone->getId(), 'Setting ID on original object should have no impact on the cloned object');

        $otherClone = clone $object;
        $this->assertEquals(123, $otherClone->getId(), 'Shallow clone should copy the fields');
    }

    /**
     * Verifies that loading only Concrete object from Concrete::getById().
     */
    public function testConcreteLoading()
    {
        $concreteObject = TestHelper::createEmptyObject();
        $loadedConcrete = DataObject\Concrete::getById($concreteObject->getId(), ['force' => true]);

        $this->assertIsObject($loadedConcrete, 'Loaded Concrete should be an object.');

        $nonConcreteObject = TestHelper::createObjectFolder();
        $loadedNonConcrete = DataObject\Concrete::getById($nonConcreteObject->getId(), ['force' => true]);

        $this->assertNull($loadedNonConcrete, 'Loaded Concrete should be null.');
    }
}
