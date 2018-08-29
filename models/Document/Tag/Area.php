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

use Pimcore\Document\Tag\Block\BlockName;
use Pimcore\ExtensionManager;
use Pimcore\Model;
use Pimcore\Tool\HtmlUtils;

/**
 * @method \Pimcore\Model\Document\Tag\Dao getDao()
 */
class Area extends Model\Document\Tag
{
    /**
     * @see Model\Document\Tag\TagInterface::getType
     *
     * @return string
     */
    public function getType()
    {
        return 'area';
    }

    /**
     * @see Model\Document\Tag\TagInterface::getData
     *
     * @return mixed
     */
    public function getData()
    {
        return null;
    }

    /**
     * @see Model\Document\Tag\TagInterface::admin
     */
    public function admin()
    {
        $options = $this->getEditmodeOptions();
        $this->outputEditmodeOptions($options);

        $attributes = $this->getEditmodeElementAttributes($options);
        $attributeString = HtmlUtils::assembleAttributeString($attributes);

        $this->outputEditmode('<div ' . $attributeString . '>');

        $this->frontend();

        $this->outputEditmode('</div>');
    }

    /**
     * @see Model\Document\Tag\TagInterface::frontend
     */
    public function frontend()
    {
        $count = 0;
        $options = $this->getOptions();

        // TODO inject area handler via DI when tags are built through container
        $tagHandler = \Pimcore::getContainer()->get('pimcore.document.tag.handler');

        // don't show disabled bricks
        if (!$tagHandler->isBrickEnabled($this, $options['type'] && $options['dontCheckEnabled'] != true)) {
            return;
        }

        // push current block name
        $blockState = $this->getBlockState();
        $blockState->pushBlock(BlockName::createFromTag($this));

        $this->current = $count;

        // create info object and assign it to the view
        $info = null;
        try {
            $info = new Area\Info();
            $info->setId($options['type']);
            $info->setTag($this);
            $info->setIndex($count);
        } catch (\Exception $e) {
            $info = null;
        }

        // start at first index
        $blockState->pushIndex(1);

        $params = [];
        if (is_array($options['params']) && array_key_exists($options['type'], $options['params'])) {
            if (is_array($options['params'][$options['type']])) {
                $params = $options['params'][$options['type']];
            }
        }

        $info->setParams($params);

        $tagHandler->renderAreaFrontend($info);

        // remove current block and index from stack
        $blockState->popIndex();
        $blockState->popBlock();
    }

    /**
     * @see Model\Document\Tag\TagInterface::setDataFromResource
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setDataFromResource($data)
    {
        return $this;
    }

    /**
     * @see Model\Document\Tag\TagInterface::setDataFromEditmode
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setDataFromEditmode($data)
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return false;
    }

    /**
     * @deprecated Only used in legacy mode
     *
     * @return array
     */
    public function getAreaDirs()
    {
        return ExtensionManager::getBrickDirectories();
    }

    /**
     * @deprecated Only used in legacy mode
     *
     * @return array|mixed
     */
    public function getBrickConfigs()
    {
        return ExtensionManager::getBrickConfigs();
    }

    /**
     * Gets an element from the referenced brick. E.g. if you have an area "myArea" which defines "gallery-single-images"
     * as used areabrick and this areabrick defines a block "gallery", you can use $area->getElement('gallery') to get
     * an instance of the block element.
     *
     * @param string $name
     *
     * @return Model\Document\Tag
     */
    public function getElement(string $name)
    {
        $document = Model\Document\Page::getById($this->getDocumentId());
        $namingStrategy = \Pimcore::getContainer()->get('pimcore.document.tag.naming.strategy');

        $parentBlockNames = $this->getParentBlockNames();
        $parentBlockNames[] = $this->getName();

        $id = $namingStrategy->buildChildElementTagName($name, 'area', $parentBlockNames, 1);
        $element = $document->getElement($id);

        if ($element) {
            $element->setParentBlockNames($parentBlockNames);
        }

        return $element;
    }
}
