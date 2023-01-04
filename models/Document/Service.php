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

use Pimcore\Config;
use Pimcore\Document\Renderer\DocumentRenderer;
use Pimcore\Document\Renderer\DocumentRendererInterface;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\File;
use Pimcore\Image\Chromium;
use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Editable\IdRewriterInterface;
use Pimcore\Model\Document\Editable\LazyLoadingInterface;
use Pimcore\Model\Element;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Tool;
use Pimcore\Tool\Serialize;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Pimcore\Model\Document\Service\Dao getDao()
 * @method int[] getTranslations(Document $document, string $task = 'open')
 * @method addTranslation(Document $document, Document $translation, $language = null)
 * @method removeTranslation(Document $document)
 * @method int getTranslationSourceId(Document $document)
 * @method removeTranslationLink(Document $document, Document $targetDocument)
 */
class Service extends Model\Element\Service
{
    protected ?Model\User $_user;

    protected array $_copyRecursiveIds;

    /**
     * @var Document[]
     */
    protected array $nearestPathCache;

    /**
     * @param Model\User|null $user
     */
    public function __construct(Model\User $user = null)
    {
        $this->_user = $user;
    }

    /**
     * Renders a document outside of a view
     *
     * Parameter order was kept for BC (useLayout before query and options).
     *
     * @static
     *
     * @param Document\PageSnippet $document
     * @param array $attributes
     * @param bool $useLayout
     * @param array $query
     * @param array $options
     *
     * @return string
     */
    public static function render(Document\PageSnippet $document, array $attributes = [], bool $useLayout = false, array $query = [], array $options = []): string
    {
        $container = \Pimcore::getContainer();

        /** @var DocumentRendererInterface $renderer */
        $renderer = $container->get(DocumentRenderer::class);

        // keep useLayout compatibility
        $attributes['_useLayout'] = $useLayout;
        $content = $renderer->render($document, $attributes, $query, $options);

        return $content;
    }

    /**
     * @param Document $target
     * @param Document $source
     *
     * @return Page|Document|null copied document
     *
     * @throws \Exception
     */
    public function copyRecursive(Document $target, Document $source): Page|Document|null
    {
        // avoid recursion
        if (!$this->_copyRecursiveIds) {
            $this->_copyRecursiveIds = [];
        }
        if (in_array($source->getId(), $this->_copyRecursiveIds)) {
            return null;
        }

        if ($source instanceof Document\PageSnippet) {
            $source->getEditables();
        }

        $source->getProperties();

        // triggers actions before document cloning
        $event = new DocumentEvent($source, [
            'target_element' => $target,
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, DocumentEvents::PRE_COPY);
        $target = $event->getArgument('target_element');

        /** @var Document $new */
        $new = Element\Service::cloneMe($source);
        $new->setId(null);
        $new->setChildren(null);
        $new->setKey(Element\Service::getSafeCopyName($new->getKey(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user ? $this->_user->getId() : 0);
        $new->setUserModification($this->_user ? $this->_user->getId() : 0);
        $new->setDao(null);
        $new->setLocked(null);
        $new->setCreationDate(time());
        if ($new instanceof Page) {
            $new->setPrettyUrl(null);
        }

        $new->save();

        // add to store
        $this->_copyRecursiveIds[] = $new->getId();

        foreach ($source->getChildren(true) as $child) {
            $this->copyRecursive($new, $child);
        }

        $this->updateChildren($target, $new);

        // triggers actions after the complete document cloning
        $event = new DocumentEvent($new, [
            'base_element' => $source, // the element used to make a copy
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, DocumentEvents::POST_COPY);

        return $new;
    }

    /**
     *
     * @param Document $target
     * @param Document $source
     * @param bool $enableInheritance
     * @param bool $resetIndex
     * @param bool $language
     *
     * @return Page|Document|PageSnippet
     *
     * @throws ValidationException
     */
    public function copyAsChild(Document $target, Document $source, bool $enableInheritance = false, bool $resetIndex = false, bool $language = false): Page|Document|PageSnippet
    {
        if ($source instanceof Document\PageSnippet) {
            $source->getEditables();
        }

        $source->getProperties();

        // triggers actions before document cloning
        $event = new DocumentEvent($source, [
            'target_element' => $target,
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, DocumentEvents::PRE_COPY);
        $target = $event->getArgument('target_element');

        /**
         * @var Document $new
         */
        $new = Element\Service::cloneMe($source);
        $new->setId(null);
        $new->setChildren(null);
        $new->setKey(Element\Service::getSafeCopyName($new->getKey(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user ? $this->_user->getId() : 0);
        $new->setUserModification($this->_user ? $this->_user->getId() : 0);
        $new->setDao(null);
        $new->setLocked(null);
        $new->setCreationDate(time());

        if ($resetIndex) {
            // this needs to be after $new->setParentId($target->getId()); -> dependency!
            $new->setIndex($new->getDao()->getNextIndex());
        }

        if ($new instanceof Page) {
            $new->setPrettyUrl(null);
        }

        if ($enableInheritance && ($new instanceof Document\PageSnippet) && $new->supportsContentMaster()) {
            $new->setEditables([]);
            $new->setContentMasterDocumentId($source->getId(), true);
        }

        if ($language) {
            $new->setProperty('language', 'text', $language, false, true);
        }

        $new->save();

        $this->updateChildren($target, $new);

        //link translated document
        if ($language) {
            $this->addTranslation($source, $new, $language);
        }

        // triggers actions after the complete document cloning
        $event = new DocumentEvent($new, [
            'base_element' => $source, // the element used to make a copy
        ]);
        \Pimcore::getEventDispatcher()->dispatch($event, DocumentEvents::POST_COPY);

        return $new;
    }

    /**
     * @param Document $target
     * @param Document $source
     *
     * @return Link|Page|Document|PageSnippet
     *
     * @throws ValidationException
     */
    public function copyContents(Document $target, Document $source): Link|Page|Document|PageSnippet
    {
        // check if the type is the same
        if (get_class($source) != get_class($target)) {
            throw new \Exception('Source and target have to be the same type');
        }

        if ($source instanceof Document\PageSnippet) {
            /** @var PageSnippet $target */
            $target->setEditables($source->getEditables());

            $target->setTemplate($source->getTemplate());
            $target->setController($source->getController());

            if ($source instanceof Document\Page) {
                /** @var Page $target */
                $target->setTitle($source->getTitle());
                $target->setDescription($source->getDescription());
            }
        } elseif ($source instanceof Document\Link) {
            /** @var Link $target */
            $target->setInternalType($source->getInternalType());
            $target->setInternal($source->getInternal());
            $target->setDirect($source->getDirect());
            $target->setLinktype($source->getLinktype());
        }

        $target->setUserModification($this->_user ? $this->_user->getId() : 0);
        $target->setProperties(self::cloneProperties($source->getProperties()));
        $target->save();

        return $target;
    }

    /**
     * @param Document $document
     *
     * @return array
     *
     * @internal
     */
    public static function gridDocumentData(Document $document): array
    {
        $data = Element\Service::gridElementData($document);

        if ($document instanceof Document\Page) {
            $data['title'] = $document->getTitle();
            $data['description'] = $document->getDescription();
        } else {
            $data['title'] = '';
            $data['description'] = '';
            $data['name'] = '';
        }

        return $data;
    }

    /**
     * @param Document $doc
     *
     * @return Document
     *
     * @internal
     */
    public static function loadAllDocumentFields(Document $doc): Document
    {
        $doc->getProperties();

        if ($doc instanceof Document\PageSnippet) {
            foreach ($doc->getEditables() as $name => $data) {
                if ($data instanceof LazyLoadingInterface) {
                    $data->load();
                }
            }
        }

        return $doc;
    }

    /**
     * @static
     *
     * @param string $path
     * @param string|null $type
     *
     * @return bool
     */
    public static function pathExists(string $path, string $type = null): bool
    {
        if (!$path) {
            return false;
        }

        $path = Element\Service::correctPath($path);

        try {
            $document = new Document();
            // validate path
            if (self::isValidPath($path, 'document')) {
                $document->getDao()->getByPath($path);

                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, Document::getTypes());
    }

    /**
     * Rewrites id from source to target, $rewriteConfig contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     *
     * @param Document $document
     * @param array $rewriteConfig
     * @param array $params
     *
     * @return Document|PageSnippet
     *
     * @internal
     */
    public static function rewriteIds(Document $document, array $rewriteConfig, array $params = []): Document|PageSnippet
    {
        // rewriting elements only for snippets and pages
        if ($document instanceof Document\PageSnippet) {
            if (array_key_exists('enableInheritance', $params) && $params['enableInheritance']) {
                $editables = $document->getEditables();
                $changedEditables = [];
                $contentMaster = $document->getContentMasterDocument();
                if ($contentMaster instanceof Document\PageSnippet) {
                    $contentMasterEditables = $contentMaster->getEditables();
                    foreach ($contentMasterEditables as $contentMasterEditable) {
                        if ($contentMasterEditable instanceof IdRewriterInterface) {
                            $editable = clone $contentMasterEditable;
                            $editable->rewriteIds($rewriteConfig);

                            if (Serialize::serialize($editable) != Serialize::serialize($contentMasterEditable)) {
                                $changedEditables[] = $editable;
                            }
                        }
                    }
                }

                if (count($changedEditables) > 0) {
                    $editables = $changedEditables;
                }
            } else {
                $editables = $document->getEditables();
                foreach ($editables as &$editable) {
                    if ($editable instanceof IdRewriterInterface) {
                        $editable->rewriteIds($rewriteConfig);
                    }
                }
            }

            $document->setEditables($editables);
        } elseif ($document instanceof Document\Hardlink) {
            if (array_key_exists('document', $rewriteConfig) && $document->getSourceId() && array_key_exists((int) $document->getSourceId(), $rewriteConfig['document'])) {
                $document->setSourceId($rewriteConfig['document'][(int) $document->getSourceId()]);
            }
        } elseif ($document instanceof Document\Link) {
            if (array_key_exists('document', $rewriteConfig) && $document->getLinktype() == 'internal' && $document->getInternalType() == 'document' && array_key_exists((int) $document->getInternal(), $rewriteConfig['document'])) {
                $document->setInternal($rewriteConfig['document'][(int) $document->getInternal()]);
            }
        }

        // rewriting properties
        $properties = $document->getProperties();
        foreach ($properties as &$property) {
            $property->rewriteIds($rewriteConfig);
        }
        $document->setProperties($properties);

        return $document;
    }

    /**
     * @param string $url
     *
     * @return Document|null
     *
     * @internal
     */
    public static function getByUrl(string $url): ?Document
    {
        $urlParts = parse_url($url);
        $document = null;

        if ($urlParts['path']) {
            $document = Document::getByPath($urlParts['path']);

            // search for a page in a site
            if (!$document) {
                $sitesList = new Model\Site\Listing();
                $sitesObjects = $sitesList->load();

                foreach ($sitesObjects as $site) {
                    if ($site->getRootDocument() && (in_array($urlParts['host'], $site->getDomains()) || $site->getMainDomain() == $urlParts['host'])) {
                        if ($document = Document::getByPath($site->getRootDocument() . $urlParts['path'])) {
                            break;
                        }
                    }
                }
            }
        }

        return $document;
    }

    public static function getUniqueKey(ElementInterface $element, int $nr = 0): string
    {
        $list = new Listing();
        $list->setUnpublished(true);
        $key = Element\Service::getValidKey($element->getKey(), 'document');
        if (!$key) {
            throw new \Exception('No item key set.');
        }
        if ($nr) {
            $key = $key . '_' . $nr;
        }

        $parent = $element->getParent();
        if (!$parent) {
            throw new \Exception('You have to set a parent document to determine a unique Key');
        }

        if (!$element->getId()) {
            $list->setCondition('parentId = ? AND `key` = ? ', [$parent->getId(), $key]);
        } else {
            $list->setCondition('parentId = ? AND `key` = ? AND id != ? ', [$parent->getId(), $key, $element->getId()]);
        }
        $check = $list->loadIdList();
        if (!empty($check)) {
            $nr++;
            $key = self::getUniqueKey($element, $nr);
        }

        return $key;
    }

    /**
     * Get the nearest document by path. Used to match nearest document for a static route.
     *
     * @param string|Request $path
     * @param bool $ignoreHardlinks
     * @param array $types
     *
     * @return Document|null
     *
     * @internal
     */
    public function getNearestDocumentByPath(string|Request $path, bool $ignoreHardlinks = false, array $types = []): ?Document
    {
        if ($path instanceof Request) {
            $path = urldecode($path->getPathInfo());
        }

        $cacheKey = $ignoreHardlinks . implode('-', $types);
        $document = null;

        if (isset($this->nearestPathCache[$cacheKey])) {
            $document = $this->nearestPathCache[$cacheKey];
        } else {
            $paths = ['/'];
            $tmpPaths = [];

            $pathParts = explode('/', $path);
            foreach ($pathParts as $pathPart) {
                $tmpPaths[] = $pathPart;

                $t = implode('/', $tmpPaths);
                $paths[] = $t;
            }

            $paths = array_reverse($paths);
            foreach ($paths as $p) {
                if ($document = Document::getByPath($p)) {
                    if (empty($types) || in_array($document->getType(), $types)) {
                        $document = $this->nearestPathCache[$cacheKey] = $document;

                        break;
                    }
                } elseif (Model\Site::isSiteRequest()) {
                    // also check for a pretty url in a site
                    $site = Model\Site::getCurrentSite();

                    // undo the changed made by the site detection in self::match()
                    $originalPath = preg_replace('@^' . $site->getRootPath() . '@', '', $p);

                    $sitePrettyDocId = $this->getDao()->getDocumentIdByPrettyUrlInSite($site, $originalPath);
                    if ($sitePrettyDocId) {
                        if ($sitePrettyDoc = Document::getById($sitePrettyDocId)) {
                            $document = $this->nearestPathCache[$cacheKey] = $sitePrettyDoc;

                            break;
                        }
                    }
                }
            }
        }

        if ($document) {
            if (!$ignoreHardlinks) {
                if ($document instanceof Document\Hardlink) {
                    if ($hardLinkedDocument = Document\Hardlink\Service::getNearestChildByPath($document, $path)) {
                        $document = $hardLinkedDocument;
                    } else {
                        $document = Document\Hardlink\Service::wrap($document);
                    }
                }
            }

            return $document;
        }

        return null;
    }

    /**
     * @param int $id
     * @param Request|null $request
     * @param string|null $hostUrl
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @internal
     */
    public static function generatePagePreview(int $id, Request $request = null, string $hostUrl = null): bool
    {
        $success = false;

        /** @var Page $doc */
        $doc = Document::getById($id);
        if (!$hostUrl) {
            $hostUrl = Config::getSystemConfiguration('documents')['preview_url_prefix'];
            if (empty($hostUrl)) {
                $hostUrl = Tool::getHostUrl(null, $request);
            }
        }

        $url = $hostUrl . $doc->getRealFullPath();
        $tmpFile = PIMCORE_SYSTEM_TEMP_DIRECTORY . '/screenshot_tmp_' . $doc->getId() . '.png';
        $file = $doc->getPreviewImageFilesystemPath();

        File::mkdir(dirname($file));

        if (Chromium::convert($url, $tmpFile)) {
            $im = \Pimcore\Image::getInstance();
            $im->load($tmpFile);
            $im->scaleByWidth(800);
            $im->save($file, 'jpeg', 85);

            unlink($tmpFile);

            $success = true;
        }

        return $success;
    }
}
