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

use Pimcore\Config;
use Pimcore\FeatureToggles\Features\DebugMode;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Targeting\Document\DocumentTargetingConfigurator;

/**
 * @method \Pimcore\Model\Document\Tag\Dao getDao()
 */
class Renderlet extends Model\Document\Tag
{
    /**
     * Contains the ID of the linked object
     *
     * @var int
     */
    public $id;

    /**
     * Contains the object
     *
     * @var Document | Asset | DataObject\AbstractObject
     */
    public $o;

    /**
     * Contains the type
     *
     * @var string
     */
    public $type;

    /**
     * Contains the subtype
     *
     * @var string
     */
    public $subtype;

    /**
     * @see Document\Tag\TagInterface::getType
     *
     * @return string
     */
    public function getType()
    {
        return 'renderlet';
    }

    /**
     * @see Document\Tag\TagInterface::getData
     *
     * @return mixed
     */
    public function getData()
    {
        return [
            'id' => $this->id,
            'type' => $this->getObjectType(),
            'subtype' => $this->subtype
        ];
    }

    /**
     * Converts the data so it's suitable for the editmode
     *
     * @return mixed
     */
    public function getDataEditmode()
    {
        if ($this->o instanceof Element\ElementInterface) {
            return [
                'id' => $this->id,
                'type' => $this->getObjectType(),
                'subtype' => $this->subtype
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

        if (!$tagHandler->supports($this->view)) {
            return null;
        }

        if (!$this->options['controller'] && !$this->options['action']) {
            $this->options['controller'] = Config::getSystemConfig()->documents->default_controller;
            $this->options['action'] = Config::getSystemConfig()->documents->default_action;
        }

        if (method_exists($this->o, 'isPublished')) {
            if (!$this->o->isPublished()) {
                return '';
            }
        }

        if ($this->o instanceof Element\ElementInterface) {
            // apply best matching target group (if any)
            if ($this->o instanceof Document\Targeting\TargetingDocumentInterface) {
                $targetingConfigurator = $container->get(DocumentTargetingConfigurator::class);
                $targetingConfigurator->configureTargetGroup($this->o);
            }

            $blockparams = ['action', 'controller', 'module', 'bundle', 'template'];

            $params = [
                'template' => isset($this->options['template']) ? $this->options['template'] : null,
                'id' => $this->id,
                'type' => $this->type,
                'subtype' => $this->subtype,
                'pimcore_request_source' => 'renderlet',
            ];

            foreach ($this->options as $key => $value) {
                if (!array_key_exists($key, $params) && !in_array($key, $blockparams)) {
                    $params[$key] = $value;
                }
            }

            try {
                $moduleOrBundle = null;

                if (isset($this->options['bundle'])) {
                    $moduleOrBundle = $this->options['bundle'];
                } elseif (isset($this->options['module'])) {
                    $moduleOrBundle = $this->options['module'];
                }

                $content = $tagHandler->renderAction(
                    $this->view,
                    $this->options['controller'],
                    $this->options['action'],
                    $moduleOrBundle,
                    $params
                );

                return $content;
            } catch (\Exception $e) {
                if (\Pimcore::inDebugMode(DebugMode::RENDER_DOCUMENT_TAG_ERRORS)) {
                    return 'ERROR: ' . $e->getMessage() . ' (for details see log files in /var/logs)';
                }
                Logger::error($e);
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
        $data = \Pimcore\Tool\Serialize::unserialize($data);

        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->subtype = $data['subtype'];

        $this->setElement();

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
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->subtype = $data['subtype'];

        $this->setElement();

        return $this;
    }

    /**
     * Sets the element by the data stored for the object
     *
     * @return $this
     */
    public function setElement()
    {
        $this->o = Element\Service::getElementById($this->type, $this->id);

        return $this;
    }

    /**
     * @return array
     */
    public function resolveDependencies()
    {
        $this->load();

        $dependencies = [];

        if ($this->o instanceof Element\ElementInterface) {
            $elementType = Element\Service::getElementType($this->o);
            $key = $elementType . '_' . $this->o->getId();

            $dependencies[$key] = [
                'id' => $this->o->getId(),
                'type' => $elementType
            ];
        }

        return $dependencies;
    }

    /**
     * get correct type of object as string
     *
     * @param null $object
     *
     * @return bool|string
     *
     * @internal param mixed $data
     */
    public function getObjectType($object = null)
    {
        $this->load();

        if (!$object) {
            $object = $this->o;
        }
        if ($object instanceof Element\ElementInterface) {
            return Element\Service::getType($object);
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        $this->load();

        if ($this->o instanceof Element\ElementInterface) {
            return false;
        }

        return true;
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
            $this->type = $data->type;
            $this->subtype = $data->subtype;
            if (is_numeric($this->id)) {
                if ($idMapper) {
                    $id = $idMapper->getMappedId($this->type, $this->id);
                }

                if ($this->type == 'asset') {
                    $this->o = Asset::getById($id);
                    if (!$this->o instanceof Asset) {
                        if ($idMapper && $idMapper->ignoreMappingFailures()) {
                            $idMapper->recordMappingFailure($this->getDocumentId(), $this->type, $this->id);
                        } else {
                            throw new \Exception('cannot get values from web service import - referenced asset with id [ '.$this->id.' ] is unknown');
                        }
                    }
                } elseif ($this->type == 'document') {
                    $this->o = Document::getById($id);
                    if (!$this->o instanceof Document) {
                        if ($idMapper && $idMapper->ignoreMappingFailures()) {
                            $idMapper->recordMappingFailure($this->getDocumentId(), $this->type, $this->id);
                        } else {
                            throw new \Exception('cannot get values from web service import - referenced document with id [ '.$this->id.' ] is unknown');
                        }
                    }
                } elseif ($this->type == 'object') {
                    $this->o = DataObject::getById($id);
                    if (!$this->o instanceof DataObject\AbstractObject) {
                        if ($idMapper && $idMapper->ignoreMappingFailures()) {
                            $idMapper->recordMappingFailure($this->getDocumentId(), $this->type, $this->id);
                        } else {
                            throw new \Exception('cannot get values from web service import - referenced object with id [ '.$this->id.' ] is unknown');
                        }
                    }
                } else {
                    p_r($this);
                    throw new \Exception('cannot get values from web service import - type is not valid');
                }
            } else {
                throw new \Exception('cannot get values from web service import - id is not valid');
            }
        }
    }

    /**
     * @return bool
     */
    public function checkValidity()
    {
        $sane = true;
        if ($this->id) {
            $el = Element\Service::getElementById($this->type, $this->id);
            if (!$el instanceof Element\ElementInterface) {
                $sane = false;
                Logger::notice('Detected insane relation, removing reference to non existent '.$this->type.' with id ['.$this->id.']');
                $this->id = null;
                $this->type = null;
                $this->o = null;
                $this->subtype = null;
            }
        }

        return $sane;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $finalVars = [];
        $parentVars = parent::__sleep();
        $blockedVars = ['o'];
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
        if (!$this->o) {
            $this->setElement();
        }
    }

    /**
     * @param int $id
     *
     * @return Document\Tag\Renderlet
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return (int) $this->id;
    }

    /**
     * @param Asset|Document|Object $o
     *
     * @return Document\Tag\Renderlet
     */
    public function setO($o)
    {
        $this->o = $o;

        return $this;
    }

    /**
     * @return Asset|Document|Object
     */
    public function getO()
    {
        return $this->o;
    }

    /**
     * @param string $subtype
     *
     * @return Document\Tag\Renderlet
     */
    public function setSubtype($subtype)
    {
        $this->subtype = $subtype;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubtype()
    {
        return $this->subtype;
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
        $type = (string) $this->type;
        if ($type && array_key_exists($this->type, $idMapping) and array_key_exists($this->getId(), $idMapping[$this->type])) {
            $this->setId($idMapping[$this->type][$this->getId()]);
            $this->setO(null);
        }
    }
}
