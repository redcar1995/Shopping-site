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

namespace Pimcore\Model\Document;

use Pimcore\Model\Document;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Web2Print\Processor;

/**
 * @method \Pimcore\Model\Document\PrintAbstract\Dao getDao()
 */
abstract class PrintAbstract extends Document\PageSnippet
{
    /**
     * @internal
     *
     * @var int|null
     */
    protected ?int $lastGenerated = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $lastGenerateMessage = null;

    /**
     * @internal
     *
     * @var string|null
     */
    protected ?string $controller = 'web2print';

    public function setLastGeneratedDate(\DateTime $lastGenerated)
    {
        $this->lastGenerated = $lastGenerated->getTimestamp();
    }

    public function getLastGeneratedDate(): ?\DateTime
    {
        if ($this->lastGenerated) {
            $date = new \DateTime();
            $date->setTimestamp($this->lastGenerated);

            return $date;
        }

        return null;
    }

    public function getInProgress(): ?TmpStore
    {
        return TmpStore::get($this->getLockKey());
    }

    public function setLastGenerated(int $lastGenerated)
    {
        $this->lastGenerated = $lastGenerated;
    }

    public function getLastGenerated(): ?int
    {
        return $this->lastGenerated;
    }

    public function setLastGenerateMessage(string $lastGenerateMessage)
    {
        $this->lastGenerateMessage = $lastGenerateMessage;
    }

    public function getLastGenerateMessage(): ?string
    {
        return $this->lastGenerateMessage;
    }

    public function generatePdf(array $config): bool
    {
        return Processor::getInstance()->preparePdfGeneration($this->getId(), $config);
    }

    public function renderDocument(array $params): string
    {
        $html = Document\Service::render($this, $params, true);

        return $html;
    }

    public function getPdfFileName(): string
    {
        return PIMCORE_SYSTEM_TEMP_DIRECTORY . DIRECTORY_SEPARATOR . 'web2print-document-' . $this->getId() . '.pdf';
    }

    public function pdfIsDirty(): bool
    {
        return $this->getLastGenerated() < $this->getModificationDate();
    }

    /**
     * @internal
     *
     * @return string
     */
    public function getLockKey(): string
    {
        return 'web2print_pdf_generation_' . $this->getId();
    }
}
