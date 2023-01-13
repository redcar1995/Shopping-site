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

namespace Pimcore\Session\Attribute;

use Pimcore\Session\Attribute\Exception\AttributeBagLockedException;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

class LockableAttributeBag extends AttributeBag implements LockableAttributeBagInterface
{
    protected bool $locked = false;

    /**
     * {@inheritdoc}
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(): void
    {
        $this->locked = false;
    }

    /**
     * {@inheritdoc}
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, mixed $value): void
    {
        $this->checkLock();

        parent::set($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function replace(array $attributes): void
    {
        $this->checkLock();

        parent::replace($attributes);
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function remove(string $name): mixed
    {
        $this->checkLock();

        return parent::remove($name);
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    public function clear(): mixed
    {
        $this->checkLock();

        return parent::clear();
    }

    /**
     * @throws AttributeBagLockedException
     *      if lock is set
     */
    protected function checkLock(): void
    {
        if ($this->locked) {
            throw new AttributeBagLockedException('Attribute bag is locked');
        }
    }
}
