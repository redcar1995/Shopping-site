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

namespace Pimcore\Model\Document\Tag\Area;

use Pimcore\Model\Document;
use Pimcore\Model\Document\Tag;
use Pimcore\Templating\Model\ViewModelInterface;
use Symfony\Component\HttpFoundation\Request;

class Info
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var Tag|Tag\Area|Tag\Areablock
     */
    public $tag;

    /**
     * @var array
     */
    public $params;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var ViewModelInterface
     */
    public $view;

    /**
     * @var string
     */
    public $type;

    /**
     * @var int
     */
    public $index;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Tag|Tag\Area|Tag\Areablock
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @param Tag $tag
     */
    public function setTag(Tag $tag)
    {
        $this->tag = $tag;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function getParam(string $name)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        return null;
    }

    /**
     * @param array $params
     *
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return ViewModelInterface
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param ViewModelInterface $view
     *
     * @return $this
     */
    public function setView(ViewModelInterface $view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * @param int $index
     *
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @return Document|Document\PageSnippet
     */
    public function getDocument()
    {
        $document = null;

        if ($this->view && isset($this->view->document)) {
            $document = $this->view->document;
        } else {
            $document = $this->tag->getDocument();
        }

        return $document;
    }
}
