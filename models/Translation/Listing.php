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

namespace Pimcore\Model\Translation;

use Pimcore\Model;
use Pimcore\Model\Exception\NotFoundException;

/**
 * @method \Pimcore\Model\Translation\Listing\Dao getDao()
 * @method Model\Translation[] load()
 * @method array loadRaw()
 * @method Model\Translation|false current()
 * @method int getTotalCount()
 * @method void onCreateQueryBuilder(?callable $callback)
 * @method void cleanup()
 *
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * @internal
     *
     * @var int maximum number of cacheable items
     */
    protected static $cacheLimit = 5000;

    /**
     * @internal
     *
     * @var string
     */
    protected $domain = Model\Translation::DOMAIN_DEFAULT;

    /**
     * @internal
     *
     * @var string[]|null
     */
    protected ?array $languages = null;

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     */
    public function setDomain(string $domain): void
    {
        if (!Model\Translation::isAValidDomain($domain)) {
            throw new NotFoundException(sprintf('Translation domain table "translations_%s" does not exist', $domain));
        }

        $this->domain = $domain;
    }

    /**
     * @return string[]|null
     */
    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    /**
     * @param string[]|null $languages
     */
    public function setLanguages(?array $languages): void
    {
        $this->languages = $languages;
    }

    /**
     * @return \Pimcore\Model\Translation[]
     */
    public function getTranslations()
    {
        return $this->getData();
    }

    /**
     * @param array $translations
     *
     * @return \Pimcore\Model\Translation\Listing
     */
    public function setTranslations($translations)
    {
        return $this->setData($translations);
    }

    /**
     * @return int
     */
    public static function getCacheLimit()
    {
        return self::$cacheLimit;
    }

    /**
     * @param int $cacheLimit
     */
    public static function setCacheLimit($cacheLimit)
    {
        self::$cacheLimit = $cacheLimit;
    }
}
