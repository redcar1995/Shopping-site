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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\WebsiteSetting;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\WebsiteSetting\Listing\Dao getDao()
 */
class Listing extends Model\Listing\JsonListing
{
    /**
     * Contains the results of the list. They are all an instance of WebsiteSetting
     *
     * @var array
     */
    public $settings = [];

    /**
     * @param array $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }
}
