<?php

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

namespace Pimcore\Security\Hasher;

use Pimcore\Model\DataObject\ClassDefinition\Data\Password;
use Pimcore\Model\DataObject\Concrete;
use Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\RuntimeException;

/**
 * @internal
 *
 * @method Concrete getUser()
 */
class PasswordFieldHasher extends AbstractUserAwarePasswordHasher
{
    use CheckPasswordLengthTrait;

    /**
     * @var string
     */
    protected $fieldName = 'password';

    /**
     * If true, the user password hash will be updated if necessary.
     *
     * @var bool
     */
    protected $updateHash = true;

    /**
     * @param string $fieldName
     */
    public function __construct($fieldName)
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return bool
     */
    public function getUpdateHash()
    {
        return $this->updateHash;
    }

    /**
     * @param bool $updateHash
     */
    public function setUpdateHash($updateHash)
    {
        $this->updateHash = (bool)$updateHash;
    }

    /**
     * {@inheritdoc}
     */
    public function hashPassword($raw, $salt): string
    {
        if ($this->isPasswordTooLong($raw)) {
            throw new BadCredentialsException(sprintf('Password exceeds a maximum of %d characters', static::MAX_PASSWORD_LENGTH));
        }

        return $this->getFieldDefinition()->calculateHash($raw);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw): bool
    {
        if ($this->isPasswordTooLong($raw)) {
            return false;
        }

        return $this->getFieldDefinition()->verifyPassword($raw, $this->getUser(), $this->updateHash);
    }

    /**
     * @return Password
     *
     * @throws RuntimeException
     */
    protected function getFieldDefinition()
    {
        $field = $this->getUser()->getClass()->getFieldDefinition($this->fieldName);

        if (!$field instanceof Password) {
            throw new RuntimeException(sprintf(
                'Field %s for user type %s is expected to be of type %s, %s given',
                $this->fieldName,
                get_class($this->user),
                Password::class,
                get_debug_type($field)
            ));
        }

        return $field;
    }

    public function verify(string $hashedPassword, string $plainPassword, ?string $salt = null): bool
    {
        return $this->getFieldDefinition()->verifyPassword($plainPassword, $this->getUser(), $this->updateHash);
    }
}
