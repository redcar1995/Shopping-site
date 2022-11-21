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

namespace Pimcore\Model\Tool;

use Pimcore\Model;

/**
 * @method bool getById(string $id)
 * @method \Pimcore\Model\Tool\TmpStore\Dao getDao()
 */
final class TmpStore extends Model\AbstractModel
{
    /**
     * @internal
     *
     * @var string
     */
    protected string $id;

    /**
     * @internal
     *
     * @var string
     */
    protected string $tag;

    /**
     * @internal
     *
     * @var mixed
     */
    protected mixed $data = null;

    /**
     * @internal
     *
     * @var int
     */
    protected int $date;

    /**
     * @internal
     *
     * @var int
     */
    protected int $expiryDate;

    /**
     * @internal
     *
     * @var bool
     */
    protected bool $serialized = false;

    /**
     * @internal
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    private static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private static function getDefaultLifetime(): int
    {
        return 86400 * 7;
    }

    /**
     * @param string $id
     * @param mixed $data
     * @param string|null $tag
     * @param int|null $lifetime
     *
     * @return bool
     */
    public static function add(string $id, mixed $data, string $tag = null, int $lifetime = null): bool
    {
        $instance = self::getInstance();

        if (!$lifetime) {
            $lifetime = self::getDefaultLifetime();
        }

        if (self::get($id)) {
            return true;
        }

        return $instance->getDao()->add($id, $data, $tag, $lifetime);
    }

    /**
     * @param string $id
     * @param mixed $data
     * @param string|null $tag
     * @param int|null $lifetime
     *
     * @return bool
     */
    public static function set(string $id, mixed $data, string $tag = null, int $lifetime = null): bool
    {
        $instance = self::getInstance();

        if (!$lifetime) {
            $lifetime = self::getDefaultLifetime();
        }

        return $instance->getDao()->add($id, $data, $tag, $lifetime);
    }

    public static function delete(string $id): void
    {
        $instance = self::getInstance();
        $instance->getDao()->delete($id);
    }

    public static function get(string $id): ?TmpStore
    {
        $item = new self();
        if ($item->getById($id)) {
            if ($item->getExpiryDate() < time()) {
                self::delete($id);
            } else {
                return $item;
            }
        }

        return null;
    }

    public static function getIdsByTag(string $tag): array
    {
        $instance = self::getInstance();
        $items = $instance->getDao()->getIdsByTag($tag);

        return $items;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag)
    {
        $this->tag = $tag;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data)
    {
        $this->data = $data;
    }

    public function getDate(): int
    {
        return $this->date;
    }

    public function setDate(int $date)
    {
        $this->date = $date;
    }

    public function isSerialized(): bool
    {
        return $this->serialized;
    }

    public function setSerialized(bool $serialized)
    {
        $this->serialized = $serialized;
    }

    public function getExpiryDate(): int
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(int $expiryDate)
    {
        $this->expiryDate = $expiryDate;
    }

    /**
     * @param int|null $lifetime
     *
     * @return bool
     */
    public function update(int $lifetime = null): bool
    {
        if (!$lifetime) {
            $lifetime = 86400;
        }

        return $this->getDao()->add($this->getId(), $this->getData(), $this->getTag(), $lifetime);
    }
}
