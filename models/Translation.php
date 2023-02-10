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

namespace Pimcore\Model;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Pimcore\Cache;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Event\Model\TranslationEvent;
use Pimcore\Event\Traits\RecursionBlockingEventDispatchHelperTrait;
use Pimcore\Event\TranslationEvents;
use Pimcore\File;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Tool;
use Pimcore\Translation\TranslationEntriesDumper;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

/**
 * @method \Pimcore\Model\Translation\Dao getDao()
 */
final class Translation extends AbstractModel
{
    use RecursionBlockingEventDispatchHelperTrait;

    const DOMAIN_DEFAULT = 'messages';

    const DOMAIN_ADMIN = 'admin';

    protected ?string $key = null;

    /**
     * @var string[]
     */
    protected array $translations = [];

    protected ?int $creationDate = null;

    protected ?int $modificationDate = null;

    protected string $domain = self::DOMAIN_DEFAULT;

    protected ?string $type = 'simple';

    /**
     * ID of the owner user
     */
    protected ?int $userOwner = null;

    /**
     * ID of the user who make the latest changes
     */
    protected ?int $userModification = null;

    public function getType(): string
    {
        return $this->type ?: 'simple';
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public static function IsAValidLanguage(string $domain, string $locale): bool
    {
        return in_array($locale, (array)static::getValidLanguages($domain));
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @param string[] $translations
     *
     * @return $this
     */
    public function setTranslations(array $translations): static
    {
        $this->translations = $translations;

        return $this;
    }

    public function setDate(int $date): static
    {
        $this->setModificationDate($date);

        return $this;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function setCreationDate(int $date): static
    {
        $this->creationDate = (int) $date;

        return $this;
    }

    public function getModificationDate(): ?int
    {
        return $this->modificationDate;
    }

    public function setModificationDate(int $date): static
    {
        $this->modificationDate = (int) $date;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = !empty($domain) ? $domain : self::DOMAIN_DEFAULT;
    }

    public function getUserOwner(): ?int
    {
        return $this->userOwner;
    }

    public function setUserOwner(?int $userOwner): void
    {
        $this->userOwner = $userOwner;
    }

    public function getUserModification(): ?int
    {
        return $this->userModification;
    }

    public function setUserModification(?int $userModification): void
    {
        $this->userModification = $userModification;
    }

    /**
     * @internal
     *
     * @param string $domain
     *
     * @return array
     */
    public static function getValidLanguages(string $domain = self::DOMAIN_DEFAULT): array
    {
        if ($domain == self::DOMAIN_ADMIN) {
            return \Pimcore\Tool\Admin::getLanguages();
        }

        return Tool::getValidLanguages();
    }

    public function addTranslation(string $language, string $text): void
    {
        $this->translations[$language] = $text;
    }

    public function getTranslation(string $language): string
    {
        return $this->translations[$language];
    }

    public function hasTranslation(string $language): bool
    {
        return isset($this->translations[$language]);
    }

    /**
     * @internal
     */
    public static function clearDependentCache(): void
    {
        Cache::clearTags(['translator', 'translate']);
    }

    /**
     * @param string $id
     * @param string $domain
     * @param bool $create
     * @param bool $returnIdIfEmpty
     * @param array|null $languages
     *
     * @return static|null
     *
     * @throws \Exception
     */
    public static function getByKey(string $id, string $domain = self::DOMAIN_DEFAULT, bool $create = false, bool $returnIdIfEmpty = false, array $languages = null): ?static
    {
        $cacheKey = 'translation_' . $id . '_' . $domain;
        if (is_array($languages)) {
            $cacheKey .= '_' . implode('-', $languages);
        }

        if (RuntimeCache::isRegistered($cacheKey)) {
            return RuntimeCache::get($cacheKey);
        }

        $translation = new static();
        $translation->setDomain($domain);
        $idOriginal = $id;
        $languages = $languages ? array_intersect(static::getValidLanguages($domain), $languages) : static::getValidLanguages($domain);

        try {
            $translation->getDao()->getByKey($id, $languages);
        } catch (\Exception $e) {
            if (!$create && !$returnIdIfEmpty) {
                return null;
            }

            $translation->setKey($id);
            $translation->setCreationDate(time());
            $translation->setModificationDate(time());

            if ($create && ($e instanceof NotFoundResourceException || $e instanceof TableNotFoundException)) {
                $translations = [];
                foreach ($languages as $lang) {
                    $translations[$lang] = '';
                }
                $translation->setTranslations($translations);
                TranslationEntriesDumper::addToSaveQueue($translation);
            }
        }

        if ($returnIdIfEmpty) {
            $translations = $translation->getTranslations();
            foreach ($languages as $language) {
                if (!array_key_exists($language, $translations) || empty($translations[$language])) {
                    $translations[$language] = $idOriginal;
                }
            }
            $translation->setTranslations($translations);
        }

        // add to key cache
        RuntimeCache::set($cacheKey, $translation);

        return $translation;
    }

    /**
     * @return string[]
     */
    public static function getRegisteredDomains(): array
    {
        $translationsConfig = \Pimcore\Config::getSystemConfiguration('translations');

        return $translationsConfig['domains'];
    }

    /**
     * @param string $id
     * @param string $domain
     * @param bool $create - creates an empty translation entry if the key doesn't exists
     * @param bool $returnIdIfEmpty - returns $id if no translation is available
     * @param string|null $language
     *
     * @return string|null
     *
     * @throws \Exception
     */
    public static function getByKeyLocalized(string $id, string $domain = self::DOMAIN_DEFAULT, bool $create = false, bool $returnIdIfEmpty = false, string $language = null): ?string
    {
        if ($domain == self::DOMAIN_ADMIN) {
            if ($user = Tool\Admin::getCurrentUser()) {
                $language = $user->getLanguage();
            } elseif ($user = Tool\Authentication::authenticateSession()) {
                $language = $user->getLanguage();
            }

            if (!$language) {
                $language = \Pimcore::getContainer()->get('pimcore.locale')->findLocale();
            }

            if (!in_array($language, Tool\Admin::getLanguages())) {
                $config = \Pimcore\Config::getSystemConfiguration('general');
                $language = $config['language'] ?? null;
            }
        }

        if (!$language) {
            $language = \Pimcore::getContainer()->get(LocaleServiceInterface::class)->findLocale();
            if (!$language) {
                return null;
            }
        }

        $translationItem = self::getByKey($id, $domain, $create, $returnIdIfEmpty);
        if ($translationItem instanceof self) {
            return $translationItem->getTranslation($language);
        }

        return null;
    }

    public static function isAValidDomain(string $domain): bool
    {
        $translation = new static();

        return $translation->getDao()->isAValidDomain($domain);
    }

    public function save(): void
    {
        $this->dispatchEvent(new TranslationEvent($this), TranslationEvents::PRE_SAVE);

        $this->getDao()->save();

        $this->dispatchEvent(new TranslationEvent($this), TranslationEvents::POST_SAVE);

        self::clearDependentCache();
    }

    public function delete(): void
    {
        $this->dispatchEvent(new TranslationEvent($this), TranslationEvents::PRE_DELETE);

        $this->getDao()->delete();
        self::clearDependentCache();

        $this->dispatchEvent(new TranslationEvent($this), TranslationEvents::POST_DELETE);
    }

    /**
     * Imports translations from a csv file
     * The CSV file has to have the same format as an Pimcore translation-export-file
     *
     * @param string $file - path to the csv file
     * @param string $domain
     * @param bool $replaceExistingTranslations
     * @param array|null $languages
     * @param array|null $dialect
     *
     * @return array
     *
     * @throws \Exception
     *
     * @internal
     */
    public static function importTranslationsFromFile(string $file, string $domain = self::DOMAIN_DEFAULT, bool $replaceExistingTranslations = true, array $languages = null, array $dialect = null): array
    {
        $delta = [];

        if (is_readable($file)) {
            if (!$languages || !is_array($languages)) {
                $languages = static::getValidLanguages($domain);
            }

            //read import data
            $tmpData = file_get_contents($file);

            //replace magic excel bytes
            $tmpData = str_replace("\xEF\xBB\xBF", '', $tmpData);

            //convert to utf-8 if needed
            $tmpData = Tool\Text::convertToUTF8($tmpData);

            //store data for further usage
            $importFile = PIMCORE_SYSTEM_TEMP_DIRECTORY . '/import_translations';
            File::put($importFile, $tmpData);

            $importFileOriginal = PIMCORE_SYSTEM_TEMP_DIRECTORY . '/import_translations_original';
            File::put($importFileOriginal, $tmpData);

            // determine csv type if not set
            if (empty($dialect)) {
                $dialect = Tool\Admin::determineCsvDialect(PIMCORE_SYSTEM_TEMP_DIRECTORY . '/import_translations_original');
            }

            //read data
            $data = [];
            if (($handle = fopen(PIMCORE_SYSTEM_TEMP_DIRECTORY . '/import_translations', 'r')) !== false) {
                while (($rowData = fgetcsv($handle, 0, $dialect->delimiter, $dialect->quotechar, $dialect->escapechar)) !== false) {
                    $data[] = $rowData;
                }
                fclose($handle);
            }

            //process translations
            if (is_array($data) && count($data) > 1) {
                $keys = $data[0];
                // remove wrong quotes in some export/import constellations
                $keys = array_map(function ($value) {
                    return trim($value, '﻿""');
                }, $keys);
                $data = array_slice($data, 1);
                foreach ($data as $row) {
                    $keyValueArray = [];
                    for ($counter = 0; $counter < count($row); $counter++) {
                        $rd = str_replace('&quot;', '"', $row[$counter]);
                        $keyValueArray[$keys[$counter]] = $rd;
                    }

                    $textKey = $keyValueArray['key'] ?? null;
                    if ($textKey) {
                        $t = static::getByKey($textKey, $domain, true);
                        $dirty = false;
                        foreach ($keyValueArray as $key => $value) {
                            if (in_array($key, $languages)) {
                                $currentTranslation = $t->hasTranslation($key) ? $t->getTranslation($key) : null;
                                if ($replaceExistingTranslations) {
                                    $t->addTranslation($key, $value);
                                    if ($currentTranslation != $value) {
                                        $dirty = true;
                                    }
                                } else {
                                    if (!$currentTranslation) {
                                        $t->addTranslation($key, $value);
                                        if ($currentTranslation != $value) {
                                            $dirty = true;
                                        }
                                    } elseif ($currentTranslation != $value && $value) {
                                        $delta[] =
                                            [
                                                'lg' => $key,
                                                'key' => $textKey,
                                                'text' => $t->getTranslation($key),
                                                'csv' => $value,
                                            ];
                                    }
                                }
                            }
                        }

                        if ($dirty) {
                            if (array_key_exists('creationDate', $keyValueArray) && $keyValueArray['creationDate']) {
                                $t->setCreationDate((int) $keyValueArray['creationDate']);
                            }
                            $t->setModificationDate(time()); //ignore modificationDate from file
                            $t->save();
                        }
                    }

                    // call the garbage collector if memory consumption is > 100MB
                    if (memory_get_usage() > 100_000_000) {
                        \Pimcore::collectGarbage();
                    }
                }
                static::clearDependentCache();
            } else {
                throw new \Exception('less than 2 rows of data - nothing to import');
            }
        } else {
            throw new \Exception("$file is not readable");
        }

        return $delta;
    }
}
