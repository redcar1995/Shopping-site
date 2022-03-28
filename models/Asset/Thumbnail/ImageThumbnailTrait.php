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

namespace Pimcore\Model\Asset\Thumbnail;

use Pimcore\Helper\TemporaryFileHelperTrait;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Pimcore\Tool;
use Pimcore\Tool\Storage;
use Symfony\Component\Mime\MimeTypes;

trait ImageThumbnailTrait
{
    use TemporaryFileHelperTrait;

    /**
     * @internal
     *
     * @var Asset|null
     */
    protected $asset;

    /**
     * @internal
     *
     * @var Image\Thumbnail\Config|null
     */
    protected $config;

    /**
     * @internal
     *
     * @var array
     */
    protected array $pathReference = [];

    /**
     * @internal
     *
     * @var int|null
     */
    protected $width;

    /**
     * @internal
     *
     * @var int|null
     */
    protected $height;

    /**
     * @internal
     *
     * @var int|null
     */
    protected $realWidth;

    /**
     * @internal
     *
     * @var int|null
     */
    protected $realHeight;

    /**
     * @internal
     *
     * @var string
     */
    protected $mimetype;

    /**
     * @internal
     *
     * @var bool
     */
    protected $deferred = true;

    private static array $supportedFormats = [];

    /**
     * @return null|resource
     */
    public function getStream()
    {
        $pathReference = $this->getPathReference();

        try {
            return Storage::get($pathReference['type'])->readStream($pathReference['src']);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPathReference(bool $deferredAllowed = false): array
    {
        if (!$deferredAllowed && (($this->pathReference['type'] ?? '') === 'deferred')) {
            $this->pathReference = [];
        }

        if (empty($this->pathReference)) {
            $this->generate($deferredAllowed);
        }

        return $this->pathReference;
    }

    /**
     * @internal
     */
    public function reset()
    {
        $this->pathReference = [];
        $this->width = null;
        $this->height = null;
        $this->realHeight = null;
        $this->realWidth = null;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        if (!$this->width) {
            $this->getDimensions();
        }

        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        if (!$this->height) {
            $this->getDimensions();
        }

        return $this->height;
    }

    /**
     * @return int
     */
    public function getRealWidth()
    {
        if (!$this->realWidth) {
            $this->getDimensions();
        }

        return $this->realWidth;
    }

    /**
     * @return int
     */
    public function getRealHeight()
    {
        if (!$this->realHeight) {
            $this->getDimensions();
        }

        return $this->realHeight;
    }

    private function readDimensionsFromFile(): array
    {
        $dimensions = [];
        $pathReference = $this->getPathReference();
        if (in_array($pathReference['type'], ['thumbnail', 'asset'])) {
            try {
                $info = @getimagesize($this->getLocalFile());
                if ($info) {
                    $dimensions = [
                        'width' => $info[0],
                        'height' => $info[1],
                    ];
                }
            } catch (\Exception $e) {
                // noting to do
            }
        }

        return $dimensions;
    }

    /**
     * @return array
     */
    public function getDimensions()
    {
        if (!$this->width || !$this->height) {
            $config = $this->getConfig();
            $asset = $this->getAsset();
            $dimensions = [];

            if ($this->exists()) {
                $dimensions = $this->readDimensionsFromFile();
            }

            // try to calculate the final dimensions based on the thumbnail configuration
            if (empty($dimensions) && $config && $asset instanceof Image) {
                $dimensions = $config->getEstimatedDimensions($asset);
            }

            if (empty($dimensions)) {
                // unable to calculate dimensions -> use fallback
                // generate the thumbnail and get dimensions from the thumbnail file
                $dimensions = $this->readDimensionsFromFile();
            }

            $this->width = isset($dimensions['width']) ? $dimensions['width'] : null;
            $this->height = isset($dimensions['height']) ? $dimensions['height'] : null;

            // the following is only relevant if using high-res option (retina, ...)
            $this->realHeight = $this->height;
            $this->realWidth = $this->width;

            if ($config && $config->getHighResolution() && $config->getHighResolution() > 1) {
                $this->realWidth = (int)floor($this->width * $config->getHighResolution());
                $this->realHeight = (int)floor($this->height * $config->getHighResolution());
            }
        }

        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * @return Asset
     */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * @return Image\Thumbnail\Config|null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        if (!$this->mimetype) {
            $pathReference = $this->getPathReference(true);
            if ($pathReference['type'] === 'data-uri') {
                $this->mimetype = substr($pathReference['src'], 5, strpos($pathReference['src'], ';') - 5);
            } else {
                $fileExt = $this->getFileExtension();
                $mimeTypes = MimeTypes::getDefault()->getMimeTypes($fileExt);

                if (!empty($mimeTypes)) {
                    $this->mimetype = $mimeTypes[0];
                } else {
                    // unknown
                    $this->mimetype = 'application/octet-stream';
                }
            }
        }

        return $this->mimetype;
    }

    /**
     * @return string
     */
    public function getFileExtension()
    {
        return \Pimcore\File::getFileExtension($this->getPath(true));
    }

    /**
     * @internal
     *
     * @param array $pathReference
     *
     * @return string|null
     */
    protected function convertToWebPath(array $pathReference): ?string
    {
        $type = $pathReference['type'] ?? null;
        $path = $pathReference['src'] ?? null;

        if (Tool::isFrontend()) {
            if ($type === 'data-uri') {
                return $path;
            } elseif ($type === 'deferred') {
                $prefix = \Pimcore::getContainer()->getParameter('pimcore.config')['assets']['frontend_prefixes']['thumbnail_deferred'];
                $path = $prefix . urlencode_ignore_slash($path);
            } elseif ($type === 'thumbnail') {
                $prefix = \Pimcore::getContainer()->getParameter('pimcore.config')['assets']['frontend_prefixes']['thumbnail'];
                $path = $prefix . urlencode_ignore_slash($path);
            } else {
                $path = urlencode_ignore_slash($path);
            }
        }

        return $path;
    }

    /**
     * @internal
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getLocalFile()
    {
        return self::getLocalFileFromStream($this->getStream());
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        $pathReference = $this->getPathReference(true);
        if (
            $pathReference['type'] === 'asset' ||
            $pathReference['type'] === 'data-uri' ||
            $pathReference['type'] === 'thumbnail'
        ) {
            return true;
        } elseif ($pathReference['type'] === 'deferred') {
            return false;
        } elseif (isset($pathReference['storagePath'])) {
            // this is probably redundant, but as it doesn't hurt we can keep it
            return $this->existsOnStorage($pathReference);
        }

        return false;
    }

    /**
     * @internal
     * @param array|null $pathReference
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function existsOnStorage(?array $pathReference = []): bool
    {
        $pathReference ??= $this->getPathReference(true);
        if (isset($pathReference['storagePath'])) {
            // this is probably redundant, but as it doesn't hurt we can keep it
            return Storage::get('thumbnail')->fileExists($pathReference['storagePath']);
        }

        return false;
    }

    public static function supportsFormat(string $format): bool
    {
        if (!isset(self::$supportedFormats[$format])) {
            self::$supportedFormats[$format] = \Pimcore\Image::getInstance()->supportsFormat($format);
        }

        return self::$supportedFormats[$format];
    }
}
