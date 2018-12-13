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
 * @package    Document
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Tool\Email\Log;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Tool\Email\Log\Listing\Dao getDao()
 * @method Model\Tool\Email\Log[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * Contains the results of the list. They are all an instance of Document\Email
     *
     * @var array
     */
    public $emailLogs = [];

    /**
     * Returns a list of EmailLog entries
     *
     * @return array
     */
    public function getEmailLogs()
    {
        return $this->emailLogs;
    }

    /**
     * Sets EmailLog entries
     *
     * @param array $emailLogs
     *
     * @return $this
     */
    public function setEmailLogs($emailLogs)
    {
        $this->emailLogs = $emailLogs;

        return $this;
    }
}
