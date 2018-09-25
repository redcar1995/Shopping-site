<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    Redirect
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model;

use Pimcore\Event\Model\RedirectEvent;
use Pimcore\Event\RedirectEvents;
use Pimcore\Logger;

/**
 * @method \Pimcore\Model\Redirect\Dao getDao()
 */
class Redirect extends AbstractModel
{
    const TYPE_ENTIRE_URI = 'entire_uri';
    const TYPE_PATH_QUERY = 'path_query';
    const TYPE_PATH = 'path';

    const TYPES = [
        self::TYPE_ENTIRE_URI,
        self::TYPE_PATH_QUERY,
        self::TYPE_PATH
    ];

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $source;

    /**
     * @var int
     */
    public $sourceSite;

    /**
     * @var bool
     */
    public $passThroughParameters;

    /**
     * @var string
     */
    public $target;

    /**
     * @var int
     */
    public $targetSite;

    /**
     * @var string
     */
    public $statusCode = 301;

    /**
     * @var string
     */
    public $priority = 1;

    /**
     * @var bool
     */
    public $regex;

    /**
     * @var bool
     */
    public $active = true;

    /**
     * @var int
     */
    public $expiry;

    /**
     * @var int
     */
    public $creationDate;

    /**
     * @var int
     */
    public $modificationDate;

    /**
     * StatusCodes
     */
    public static $statusCodes = [
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '307' => 'Temporary Redirect'
    ];

    /**
     * @param int $id
     *
     * @return Redirect
     */
    public static function getById($id, bool $throwOnInvalid = false)
    {
        $redirect = new self();
        $redirect->setId(intval($id));
        $redirect->getDao()->getById(null, $throwOnInvalid);

        return $redirect;
    }

    /**
     * @return Redirect
     */
    public static function create()
    {
        $redirect = new self();
        $redirect->save();

        return $redirect;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = (int) $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        if (!empty($type) && !in_array($type, self::TYPES)) {
            throw new \InvalidArgumentException(sprintf('Invalid type "%s"', $type));
        }

        $this->type = $type;
    }

    /**
     * @param string $source
     *
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @param string $target
     *
     * @return $this
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * @param int $priority
     *
     * @return $this
     */
    public function setPriority($priority)
    {
        if ($priority) {
            $this->priority = $priority;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $statusCode
     *
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        if ($statusCode) {
            $this->statusCode = $statusCode;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getHttpStatus()
    {
        $statusCode = $this->getStatusCode();
        if (empty($statusCode)) {
            $statusCode = '301';
        }

        return 'HTTP/1.1 ' . $statusCode . ' ' . self::$statusCodes[$statusCode];
    }

    public function clearDependentCache()
    {

        // this is mostly called in Redirect\Dao not here
        try {
            \Pimcore\Cache::clearTag('redirect');
        } catch (\Exception $e) {
            Logger::crit($e);
        }
    }

    /**
     * @param $expiry
     *
     * @return $this
     */
    public function setExpiry($expiry)
    {
        if (is_string($expiry) && !is_numeric($expiry)) {
            $expiry = strtotime($expiry);
        }
        $this->expiry = $expiry;

        return $this;
    }

    /**
     * @return int
     */
    public function getExpiry()
    {
        return $this->expiry;
    }

    public static function maintenanceCleanUp()
    {
        $list = new Redirect\Listing();
        $list->setCondition('active = 1 AND expiry < ' . time() . " AND expiry IS NOT NULL AND expiry != ''");
        $list->load();

        foreach ($list->getRedirects() as $redirect) {
            $redirect->setActive(false);
            $redirect->save();
        }
    }

    /**
     * @return bool
     */
    public function getRegex()
    {
        return $this->regex;
    }

    public function isRegex(): bool
    {
        return (bool)$this->regex;
    }

    /**
     * @param $regex
     *
     * @return $this
     */
    public function setRegex($regex)
    {
        if ($regex) {
            $this->regex = (bool) $regex;
        } else {
            $this->regex = null;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param $active
     *
     * @return $this
     */
    public function setActive($active)
    {
        if ($active) {
            $this->active = (bool) $active;
        } else {
            $this->active = null;
        }

        return $this;
    }

    /**
     * @param $sourceSite
     *
     * @return $this
     */
    public function setSourceSite($sourceSite)
    {
        if ($sourceSite) {
            $this->sourceSite = (int) $sourceSite;
        } else {
            $this->sourceSite = null;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getSourceSite()
    {
        return $this->sourceSite;
    }

    /**
     * @param $targetSite
     *
     * @return $this
     */
    public function setTargetSite($targetSite)
    {
        if ($targetSite) {
            $this->targetSite = (int) $targetSite;
        } else {
            $this->targetSite = null;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getTargetSite()
    {
        return $this->targetSite;
    }

    /**
     * @param $passThroughParameters
     *
     * @return Redirect
     */
    public function setPassThroughParameters($passThroughParameters)
    {
        if ($passThroughParameters) {
            $this->passThroughParameters = (bool) $passThroughParameters;
        } else {
            $this->passThroughParameters = null;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getPassThroughParameters()
    {
        return $this->passThroughParameters;
    }

    /**
     * @param $modificationDate
     *
     * @return $this
     */
    public function setModificationDate($modificationDate)
    {
        $this->modificationDate = (int) $modificationDate;

        return $this;
    }

    /**
     * @return int
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @param $creationDate
     *
     * @return $this
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = (int) $creationDate;

        return $this;
    }

    /**
     * @return int
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    public function save()
    {
        \Pimcore::getEventDispatcher()->dispatch(RedirectEvents::PRE_SAVE, new RedirectEvent($this));
        $this->getDao()->save();
        \Pimcore::getEventDispatcher()->dispatch(RedirectEvents::POST_SAVE, new RedirectEvent($this));
        $this->clearDependentCache();
    }

    public function delete()
    {
        \Pimcore::getEventDispatcher()->dispatch(RedirectEvents::PRE_DELETE, new RedirectEvent($this));
        $this->getDao()->delete();
        \Pimcore::getEventDispatcher()->dispatch(RedirectEvents::POST_DELETE, new RedirectEvent($this));
        $this->clearDependentCache();
    }
}
