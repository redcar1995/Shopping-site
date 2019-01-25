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

namespace Pimcore\Model\Document\Tag;

use Pimcore\Cache;
use Pimcore\FeatureToggles\Features\DebugMode;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Targeting\Document\DocumentTargetingConfigurator;
use Pimcore\Tool\DeviceDetector;
use Pimcore\Tool\Frontend;

/**
 * @method \Pimcore\Model\Document\Tag\Dao getDao()
 */
class Snippet extends Model\Document\Tag
{
    /**
     * Contains the ID of the linked snippet
     *
     * @var int
     */
    public $id;

    /**
     * Contains the object for the snippet
     *
     * @var Document\Snippet
     */
    public $snippet;

    /**
     * @see Document\Tag\TagInterface::getType
     *
     * @return string
     */
    public function getType()
    {
        return 'snippet';
    }

    /**
     * @see Document\Tag\TagInterface::getData
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return (int) $this->id;
    }

    /**
     * Converts the data so it's suitable for the editmode
     *
     * @return mixed
     */
    public function getDataEditmode()
    {
        if ($this->snippet instanceof Document\Snippet) {
            return [
                'id' => $this->id,
                'path' => $this->snippet->getFullPath()
            ];
        }

        return null;
    }

    /**
     * @see Document\Tag\TagInterface::frontend
     *
     * @return string
     */
    public function frontend()
    {
        // TODO inject services via DI when tags are built through container
        $container = \Pimcore::getContainer();

        $tagHandler = $container->get('pimcore.document.tag.handler');
        $targetingConfigurator = $container->get(DocumentTargetingConfigurator::class);

        if (!$tagHandler->supports($this->view)) {
            return null;
        }

        try {
            if (!$this->snippet instanceof Document\Snippet) {
                return null;
            }

            if (!$this->snippet->isPublished()) {
                return '';
            }

            // apply best matching target group (if any)
            $targetingConfigurator->configureTargetGroup($this->snippet);

            $params = $this->options;
            $params['document'] = $this->snippet;

            // check if output-cache is enabled, if so, we're also using the cache here
            $cacheKey = null;
            if ($cacheConfig = \Pimcore\Tool\Frontend::isOutputCacheEnabled()) {

                // cleanup params to avoid serializing Element\ElementInterface objects
                $cacheParams = $params;
                array_walk($cacheParams, function (&$value, $key) {
                    if ($value instanceof Model\Element\ElementInterface) {
                        $value = $value->getId();
                    }
                });

                // TODO is this enough for cache or should we disable caching completely?
                if ($this->snippet->getUseTargetGroup()) {
                    $cacheParams['target_group'] = $this->snippet->getUseTargetGroup();
                }

                if (Frontend::hasWebpSupport()) {
                    $cacheParams['webp'] = true;
                }

                $cacheKey = 'tag_snippet__' . md5(serialize($cacheParams));
                if ($content = Cache::load($cacheKey)) {
                    return $content;
                }
            }

            $content = $tagHandler->renderAction(
                $this->view,
                $this->snippet->getController(),
                $this->snippet->getAction(),
                $this->snippet->getModule(),
                $params
            );

            // write contents to the cache, if output-cache is enabled
            if ($cacheConfig && !DeviceDetector::getInstance()->wasUsed()) {
                Cache::save($content, $cacheKey, ['output', 'output_inline'], $cacheConfig['lifetime']);
            }

            return $content;
        } catch (\Exception $e) {
            Logger::error($e);

            if (\Pimcore::inDebugMode(DebugMode::RENDER_DOCUMENT_TAG_ERRORS)) {
                return 'ERROR: ' . $e->getMessage() . ' (for details see log files in /var/logs)';
            }
        }
    }

    /**
     * @see Document\Tag\TagInterface::setDataFromResource
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setDataFromResource($data)
    {
        if (intval($data) > 0) {
            $this->id = $data;
            $this->snippet = Document\Snippet::getById($this->id);
        }

        return $this;
    }

    /**
     * @see Document\Tag\TagInterface::setDataFromEditmode
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setDataFromEditmode($data)
    {
        if (intval($data) > 0) {
            $this->id = $data;
            $this->snippet = Document\Snippet::getById($this->id);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        if ($this->snippet instanceof Document\Snippet) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function resolveDependencies()
    {
        $dependencies = [];

        if ($this->snippet instanceof Document\Snippet) {
            $key = 'document_' . $this->snippet->getId();

            $dependencies[$key] = [
                'id' => $this->snippet->getId(),
                'type' => 'document'
            ];
        }

        return $dependencies;
    }

    /**
     * @param Model\Webservice\Data\Document\Element $wsElement
     * @param $document
     * @param mixed $params
     * @param null $idMapper
     *
     * @throws \Exception
     */
    public function getFromWebserviceImport($wsElement, $document = null, $params = [], $idMapper = null)
    {
        $data = $wsElement->value;
        if ($data->id !== null) {
            $this->id = $data->id;
            if (is_numeric($this->id)) {
                $this->snippet = Document\Snippet::getById($this->id);
                if (!$this->snippet instanceof Document\Snippet) {
                    throw new \Exception('cannot get values from web service import - referenced snippet with id [ ' . $this->id . ' ] is unknown');
                }
            } else {
                throw new \Exception('cannot get values from web service import - id is not valid');
            }
        }
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $finalVars = [];
        $parentVars = parent::__sleep();
        $blockedVars = ['snippet'];
        foreach ($parentVars as $key) {
            if (!in_array($key, $blockedVars)) {
                $finalVars[] = $key;
            }
        }

        return $finalVars;
    }

    /**
     * this method is called by Document\Service::loadAllDocumentFields() to load all lazy loading fields
     */
    public function load()
    {
        $this->snippet = Document::getById($this->id);
    }

    /**
     * Rewrites id from source to target, $idMapping contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     *
     * @param array $idMapping
     */
    public function rewriteIds($idMapping)
    {
        $id = $this->getId();
        if (array_key_exists('document', $idMapping) && array_key_exists($id, $idMapping['document'])) {
            $this->id = $idMapping['document'][$id];
        }
    }

    /**
     * @param Document\Snippet $snippet
     */
    public function setSnippet($snippet)
    {
        $this->snippet = $snippet;
    }

    /**
     * @return Document\Snippet
     */
    public function getSnippet()
    {
        return $this->snippet;
    }
}
