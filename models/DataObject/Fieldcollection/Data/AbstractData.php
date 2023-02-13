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

namespace Pimcore\Model\DataObject\Fieldcollection\Data;

use Pimcore\Model;
use Pimcore\Model\DataObject\ClassDefinition\Data\LazyLoadingSupportInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\DataObject\ObjectAwareFieldInterface;

/**
 * @method Dao getDao()
 */
abstract class AbstractData extends Model\AbstractModel implements Model\DataObject\LazyLoadedFieldsInterface, Model\Element\ElementDumpStateInterface, Model\Element\DirtyIndicatorInterface, ObjectAwareFieldInterface
{
    use Model\Element\ElementDumpStateTrait;
    use Model\DataObject\Traits\LazyLoadedRelationTrait;
    use Model\Element\Traits\DirtyIndicatorTrait;

    protected int $index;

    protected ?string $fieldname = null;

    protected Concrete|Model\Element\ElementDescriptor|null $object = null;

    protected ?int $objectId = null;

    protected string $type = '';

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): static
    {
        $this->index = (int) $index;

        return $this;
    }

    public function getFieldname(): ?string
    {
        return $this->fieldname;
    }

    public function setFieldname(?string $fieldname): static
    {
        $this->fieldname = $fieldname;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDefinition(): Model\DataObject\Fieldcollection\Definition
    {
        return Model\DataObject\Fieldcollection\Definition::getByKey($this->getType());
    }

    public function setObject(?Concrete $object): static
    {
        $this->objectId = $object?->getId();
        $this->object = $object;

        if (property_exists($this, 'localizedfields') && $this->localizedfields instanceof Localizedfield) {
            $dirtyLanguages = $this->localizedfields->getDirtyLanguages();
            $this->localizedfields->setObject($object);
            if(is_array($dirtyLanguages)) {
                $this->localizedfields->markLanguagesAsDirty($dirtyLanguages);
            }
            else {
                $this->localizedfields->resetLanguageDirtyMap();
            }
        }

        return $this;
    }

    public function getObject(): ?Concrete
    {
        if ($this->objectId && !$this->object) {
            $this->setObject(Concrete::getById($this->objectId));
        }

        return $this->object;
    }

    /**
     * @param string $fieldName
     * @param string|null $language
     *
     * @return mixed
     */
    public function get(string $fieldName, string $language = null): mixed
    {
        return $this->{'get'.ucfirst($fieldName)}($language);
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     * @param string|null $language
     *
     * @return mixed
     */
    public function set(string $fieldName, mixed $value, string $language = null): mixed
    {
        return $this->{'set'.ucfirst($fieldName)}($value, $language);
    }

    /**
     * @internal
     *
     * @return array
     */
    protected function getLazyLoadedFieldNames(): array
    {
        $lazyLoadedFieldNames = [];
        $fields = $this->getDefinition()->getFieldDefinitions(['suppressEnrichment' => true]);
        foreach ($fields as $field) {
            if ($field instanceof LazyLoadingSupportInterface
                && $field instanceof  Model\DataObject\ClassDefinition\Data
                && $field->getLazyLoading()) {
                $lazyLoadedFieldNames[] = $field->getName();
            }
        }

        return $lazyLoadedFieldNames;
    }

    /**
     * {@inheritdoc}
     */
    public function isAllLazyKeysMarkedAsLoaded(): bool
    {
        $object = $this->getObject();
        if ($object instanceof Concrete) {
            return $this->getObject()->isAllLazyKeysMarkedAsLoaded();
        }

        return true;
    }

    /**
     * @return array
     */
    public function __sleep(): array
    {
        $parentVars = parent::__sleep();
        $blockedVars = ['loadedLazyKeys', 'object'];
        $finalVars = [];

        if (!$this->isInDumpState()) {
            //Remove all lazy loaded fields if item gets serialized for the cache (not for versions)
            $blockedVars = array_merge($this->getLazyLoadedFieldNames(), $blockedVars);
        }

        foreach ($parentVars as $key) {
            if (!in_array($key, $blockedVars)) {
                $finalVars[] = $key;
            }
        }

        return $finalVars;
    }

    public function __wakeup(): void
    {
        if ($this->object) {
            $this->objectId = $this->object->getId();
        }
    }
}
