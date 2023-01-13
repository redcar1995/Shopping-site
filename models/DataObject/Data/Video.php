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

namespace Pimcore\Model\DataObject\Data;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\OwnerAwareFieldInterface;
use Pimcore\Model\DataObject\Traits\ObjectVarTrait;
use Pimcore\Model\DataObject\Traits\OwnerAwareFieldTrait;
use Pimcore\Model\Element\ElementInterface;

class Video implements OwnerAwareFieldInterface
{
    use OwnerAwareFieldTrait;
    use ObjectVarTrait;

    protected string $type;

    protected string|int|ElementInterface|Asset|\Pimcore\Model\Element\ElementDescriptor $data;

    protected string|int|Asset|\Pimcore\Model\Element\ElementDescriptor|null $poster = null;

    protected string $title;

    protected string $description;

    public function setData(Asset|int|string $data): void
    {
        $this->data = $data;
        $this->markMeDirty();
    }

    public function getData(): Asset|int|string
    {
        return $this->data;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->markMeDirty();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->markMeDirty();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setPoster(Asset|int|string|null $poster): void
    {
        $this->poster = $poster;
        $this->markMeDirty();
    }

    public function getPoster(): Asset|int|string|null
    {
        return $this->poster;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->markMeDirty();
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
