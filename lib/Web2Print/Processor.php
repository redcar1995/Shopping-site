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

namespace Pimcore\Web2Print;

use Pimcore\Config;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Helper\Mail;
use Pimcore\Logger;
use Pimcore\Messenger\GenerateWeb2PrintPdfMessage;
use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Web2Print\Exception\CancelException;
use Pimcore\Web2Print\Exception\NotPreparedException;
use Pimcore\Web2Print\Processor\HeadlessChrome;
use Pimcore\Web2Print\Processor\PdfReactor;
use Pimcore\Web2Print\Processor\WkHtmlToPdf;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Twig\Sandbox\SecurityError;

abstract class Processor
{
    /**
     * @var LockInterface|null
     */
    private static $lock = null;

    /**
     * @return Processor
     *
     * @throws \Exception
     */
    public static function getInstance()
    {
        $config = Config::getWeb2PrintConfig();

        if ($config->get('generalTool') === 'pdfreactor') {
            return new PdfReactor();
        } elseif ($config->get('generalTool') === 'wkhtmltopdf') {
            return new WkHtmlToPdf();
        } elseif ($config->get('generalTool') === 'headlesschrome') {
            return new HeadlessChrome();
        } else {
            throw new \Exception('Invalid Configuration - ' . $config->get('generalTool'));
        }
    }

    /**
     * @param int $documentId
     * @param array $config
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function preparePdfGeneration($documentId, $config)
    {
        $document = $this->getPrintDocument($documentId);
        if (Model\Tool\TmpStore::get($document->getLockKey())) {
            throw new \Exception('Process with given document already running.');
        }
        Model\Tool\TmpStore::add($document->getLockKey(), true);

        $jobConfig = new \stdClass();
        $jobConfig->documentId = $documentId;
        $jobConfig->config = $config;

        $this->saveJobConfigObjectFile($jobConfig);
        $this->updateStatus($documentId, 0, 'prepare_pdf_generation');

        $disableBackgroundExecution = $config['disableBackgroundExecution'] ?? false;

        if (!$disableBackgroundExecution) {
            \Pimcore::getContainer()->get('messenger.bus.pimcore-core')->dispatch(
                new GenerateWeb2PrintPdfMessage($jobConfig->documentId)
            );

            return true;
        }

        return (bool)self::getInstance()->startPdfGeneration($jobConfig->documentId);
    }

    /**
     * @param int $documentId
     *
     * @return string|null
     *
     * @throws Model\Element\ValidationException
     * @throws NotPreparedException
     */
    public function startPdfGeneration($documentId)
    {
        $jobConfigFile = $this->loadJobConfigObject($documentId);
        if (!$jobConfigFile) {
            throw new NotPreparedException('PDF Generation for document ' . $documentId . ' is not prepared.');
        }

        $document = $this->getPrintDocument($documentId);

        $lock = $this->getLock($document);
        // check if there is already a generating process running, wait if so ...
        $lock->acquire(true);

        $pdf = null;

        try {
            $preEvent = new DocumentEvent($document, [
                'processor' => $this,
                'jobConfig' => $jobConfigFile->config,
            ]);
            \Pimcore::getEventDispatcher()->dispatch($preEvent, DocumentEvents::PRINT_PRE_PDF_GENERATION);

            $pdf = $this->buildPdf($document, $jobConfigFile->config);
            file_put_contents($document->getPdfFileName(), $pdf);

            $postEvent = new DocumentEvent($document, [
                'filename' => $document->getPdfFileName(),
                'pdf' => $pdf,
            ]);
            \Pimcore::getEventDispatcher()->dispatch($postEvent, DocumentEvents::PRINT_POST_PDF_GENERATION);

            $document->setLastGenerated((time() + 1));
            $document->setLastGenerateMessage('');
            $document->save();
        } catch (CancelException $e) {
            Logger::debug($e->getMessage());
        } catch (\Exception $e) {
            Logger::err((string) $e);
            $document->setLastGenerateMessage($e->getMessage());
            $document->save();
        }

        $lock->release();
        Model\Tool\TmpStore::delete($document->getLockKey());

        @unlink(static::getJobConfigFile($documentId));

        return $pdf;
    }

    /**
     * @param Document\PrintAbstract $document
     * @param object $config
     *
     * @return string
     *
     * @throws \Exception
     */
    abstract protected function buildPdf(Document\PrintAbstract $document, $config);

    /**
     * @param \stdClass $jobConfig
     *
     * @return bool
     */
    protected function saveJobConfigObjectFile($jobConfig)
    {
        file_put_contents(static::getJobConfigFile($jobConfig->documentId), json_encode($jobConfig));

        return true;
    }

    /**
     * @param int $documentId
     *
     * @return \stdClass|null
     */
    protected function loadJobConfigObject($documentId)
    {
        $file = static::getJobConfigFile($documentId);
        if (file_exists($file)) {
            return json_decode(file_get_contents($file));
        }

        return null;
    }

    /**
     * @param int $documentId
     *
     * @return Document\PrintAbstract
     *
     * @throws \Exception
     */
    protected function getPrintDocument($documentId)
    {
        $document = Document\PrintAbstract::getById($documentId);
        if (empty($document)) {
            throw new \Exception('PrintDocument with ' . $documentId . ' not found.');
        }

        return $document;
    }

    /**
     * @param int $processId
     *
     * @return string
     */
    public static function getJobConfigFile($processId)
    {
        return PIMCORE_SYSTEM_TEMP_DIRECTORY . DIRECTORY_SEPARATOR . 'pdf-creation-job-' . $processId . '.json';
    }

    /**
     * @return array
     */
    abstract public function getProcessingOptions();

    /**
     * @param int $documentId
     * @param int $status
     * @param string $statusUpdate
     *
     * @throws CancelException
     */
    protected function updateStatus($documentId, $status, $statusUpdate)
    {
        $jobConfig = $this->loadJobConfigObject($documentId);
        if (!$jobConfig) {
            throw new CancelException('PDF Generation for document ' . $documentId . ' is canceled.');
        }
        $jobConfig->status = $status;
        $jobConfig->statusUpdate = $statusUpdate;
        $this->saveJobConfigObjectFile($jobConfig);
    }

    /**
     * @param int $documentId
     *
     * @return array|null
     */
    public function getStatusUpdate($documentId)
    {
        $jobConfig = $this->loadJobConfigObject($documentId);
        if ($jobConfig) {
            return [
                'status' => $jobConfig->status,
                'statusUpdate' => $jobConfig->statusUpdate,
            ];
        }

        return null;
    }

    /**
     * @param int $documentId
     *
     * @throws \Exception
     */
    public function cancelGeneration($documentId)
    {
        $document = Document\PrintAbstract::getById($documentId);
        if (empty($document)) {
            throw new \Exception('Document with id ' . $documentId . ' not found.');
        }

        $this->getLock($document)->release();
        Model\Tool\TmpStore::delete($document->getLockKey());
        @unlink(static::getJobConfigFile($documentId));
    }

    /**
     * @param string $html
     * @param array $params
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function processHtml($html, $params)
    {
        $document = $params['document'] ?? null;
        $hostUrl = $params['hostUrl'] ?? null;
        $templatingEngine = \Pimcore::getContainer()->get('pimcore.templating.engine.delegating');

        try {
            $twig = $templatingEngine->getTwigEnvironment(true);
            $template = $twig->createTemplate((string) $html);

            $html = $twig->render($template, $params);
        } catch (SecurityError $e) {
            Logger::err((string) $e);

            throw new \Exception(sprintf('Failed rendering the print template: %s. Please check your twig sandbox security policy or contact the administrator.', $e->getMessage()));
        } finally {
            $templatingEngine->disableSandboxExtensionFromTwigEnvironment();
        }

        return Mail::setAbsolutePaths($html, $document, $hostUrl);
    }

    /**
     * @param Document\PrintAbstract $document
     *
     * @return LockInterface
     */
    protected function getLock(Document\PrintAbstract $document): LockInterface
    {
        if (!self::$lock) {
            self::$lock = \Pimcore::getContainer()->get(LockFactory::class)->createLock($document->getLockKey());
        }

        return self::$lock;
    }

    /**
     * Returns the generated pdf file. Its path or data depending supplied parameter
     *
     * @param string $html
     * @param array $params
     * @param bool $returnFilePath return the path to the pdf file or the content
     *
     * @return string
     */
    abstract public function getPdfFromString($html, $params = [], $returnFilePath = false);
}
