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

namespace Pimcore\Model\Document\Editable;

use Carbon\Carbon;
use Pimcore\Model;

/**
 * @method \Pimcore\Model\Document\Editable\Dao getDao()
 */
class Date extends Model\Document\Editable implements EditmodeDataInterface
{
    /**
     * Contains the date
     *
     * @internal
     *
     * @var Carbon|null
     */
    protected ?\Carbon\Carbon $date = null;

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'date';
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): mixed
    {
        return $this->date;
    }

    public function getDate(): ?\Carbon\Carbon
    {
        return $this->getData();
    }

    /**
     * {@inheritdoc}
     */
    public function getDataEditmode(): ?int
    {
        if ($this->date) {
            return $this->date->getTimestamp();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function frontend()
    {
        if ($this->date instanceof Carbon) {
            if (isset($this->config['outputFormat']) && $this->config['outputFormat']) {
                return $this->date->formatLocalized($this->config['outputFormat']);
            } else {
                if (isset($this->config['format']) && $this->config['format']) {
                    $format = $this->config['format'];
                } else {
                    $format = \DateTimeInterface::ATOM;
                }

                return $this->date->format($format);
            }
        }
    }

    public function getDataForResource(): mixed
    {
        if ($this->date) {
            return $this->date->getTimestamp();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataFromResource(mixed $data): static
    {
        if ($data) {
            $this->setDateFromTimestamp((int)$data);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDataFromEditmode(mixed $data): static
    {
        if (strlen((string) $data) > 5) {
            $timestamp = strtotime($data);
            $this->setDateFromTimestamp($timestamp);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        if ($this->date) {
            return false;
        }

        return true;
    }

    private function setDateFromTimestamp(int $timestamp): void
    {
        $this->date = new Carbon();
        $this->date->setTimestamp($timestamp);
    }
}
