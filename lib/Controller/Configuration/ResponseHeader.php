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

namespace Pimcore\Controller\Configuration;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * Allows to set HTTP headers on the response via annotation. The annotation will
 * be processed by ResponseHeaderListener which will set the HTTP headers on the
 * response.
 *
 * See ResponseHeaderBag for documentation on the fields.
 *
 * @Annotation
 */
class ResponseHeader extends ConfigurationAnnotation
{
    protected string $key;

    protected string|array $values;

    protected bool $replace = false;

    public function __construct(array $data)
    {
        // value is the default key if annotation was called without assignment
        // e.g. @ResponseHeader("X-Foo") instead of @ResponseHeader(key="X-Foo")
        if (isset($data['value'])) {
            $data['key'] = $data['value'];
            unset($data['value']);
        }

        parent::__construct($data);

        if (empty($this->key)) {
            throw new \InvalidArgumentException('The @ResponseHeaderAnnotation needs at least a key to be set');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAliasName(): string
    {
        return 'response_header';
    }

    /**
     * {@inheritdoc}
     */
    public function allowArray(): bool
    {
        return true;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key)
    {
        $this->key = $key;
    }

    public function getValues(): array|string
    {
        return $this->values;
    }

    public function setValues(array|string $values)
    {
        $this->values = $values;
    }

    public function getReplace(): bool
    {
        return $this->replace;
    }

    public function setReplace(bool $replace)
    {
        $this->replace = $replace;
    }
}
