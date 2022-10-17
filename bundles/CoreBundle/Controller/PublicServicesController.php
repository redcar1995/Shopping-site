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

namespace Pimcore\Bundle\CoreBundle\Controller;

use function date;
use Pimcore\Config;
use Pimcore\Controller\Controller;
use Pimcore\File;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Site;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Tool\Storage;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use function time;

/**
 * @internal
 */
class PublicServicesController extends Controller
{
    /**
     * @param Request $request
     *
     * @return RedirectResponse|StreamedResponse
     */
    public function thumbnailAction(Request $request)
    {
        $storage = Storage::get('thumbnail');

        $assetId = (int) $request->get('assetId');
        $thumbnailName = $request->get('thumbnailName');
        $thumbnailType = $request->get('type');
        $filename = $request->get('filename');
        $requestedFileExtension = strtolower(File::getFileExtension($filename));
        $asset = Asset::getById($assetId);

        if ($asset) {
            $prefix = preg_replace('@^cache-buster\-[\d]+\/@', '', $request->get('prefix'));
            $prefix = preg_replace('@' . $asset->getId() . '/$@', '', $prefix);
            if ($asset->getPath() === ('/' . $prefix)) {
                // we need to check the path as well, this is important in the case you have restricted the public access to
                // assets via rewrite rules

                try {
                    $thumbnail = null;
                    $thumbnailStream = null;

                    // just check if the thumbnail exists -> throws exception otherwise
                    $thumbnailConfigClass = 'Pimcore\\Model\\Asset\\' . ucfirst($thumbnailType) . '\\Thumbnail\Config';
                    $thumbnailConfig = $thumbnailConfigClass::getByName($thumbnailName);

                    if (!$thumbnailConfig) {
                        // check if there's an item in the TmpStore
                        // remove an eventually existing cache-buster prefix first (eg. when using with a CDN)
                        $pathInfo = preg_replace('@^/cache-buster\-[\d]+@', '', $request->getPathInfo());
                        $deferredConfigId = 'thumb_' . $assetId . '__' . md5(urldecode($pathInfo));
                        if ($thumbnailConfigItem = TmpStore::get($deferredConfigId)) {
                            $thumbnailConfig = $thumbnailConfigItem->getData();
                            TmpStore::delete($deferredConfigId);

                            if (!$thumbnailConfig instanceof $thumbnailConfigClass) {
                                throw new \Exception('Deferred thumbnail config file doesn\'t contain a valid '.$thumbnailConfigClass.' object');
                            }
                        } elseif ($this->getParameter('pimcore.config')['assets'][$thumbnailType]['thumbnails']['status_cache']) {
                            // Delete Thumbnail Name from Cache so the next call can generate a new TmpStore entry
                            $asset->getDao()->deleteFromThumbnailCache($thumbnailName);
                        }
                    }

                    if (!$thumbnailConfig) {
                        throw $this->createNotFoundException("Thumbnail '" . $thumbnailName . "' file doesn't exist");
                    }

                    if ($thumbnailType == 'image' && strcasecmp($thumbnailConfig->getFormat(), 'SOURCE') === 0) {
                        $formatOverride = $requestedFileExtension;
                        if (in_array($requestedFileExtension, ['jpg', 'jpeg'])) {
                            $formatOverride = 'pjpeg';
                        }
                        $thumbnailConfig->setFormat($formatOverride);
                    }

                    if ($asset instanceof Asset\Video) {
                        if ($thumbnailType == 'video') {
                            $thumbnail = $asset->getThumbnail($thumbnailName, [$requestedFileExtension]);
                            $storagePath = urldecode($thumbnail['formats'][$requestedFileExtension]);

                            if ($storage->fileExists($storagePath)) {
                                $thumbnailStream = $storage->readStream($storagePath);
                            }
                        } else {
                            $time = 1;
                            if (preg_match("|~\-~time\-(\d+)\.|", $filename, $matchesThumbs)) {
                                $time = (int)$matchesThumbs[1];
                            }

                            $thumbnail = $asset->getImageThumbnail($thumbnailConfig, $time);
                            $thumbnailStream = $thumbnail->getStream();
                        }
                    } elseif ($asset instanceof Asset\Document) {
                        $page = 1;
                        if (preg_match("|~\-~page\-(\d+)\.|", $filename, $matchesThumbs)) {
                            $page = (int)$matchesThumbs[1];
                        }

                        $thumbnailConfig->setName(preg_replace("/\-[\d]+/", '', $thumbnailConfig->getName()));
                        $thumbnailConfig->setName(str_replace('document_', '', $thumbnailConfig->getName()));

                        $thumbnail = $asset->getImageThumbnail($thumbnailConfig, $page);
                        $thumbnailStream = $thumbnail->getStream();
                    } elseif ($asset instanceof Asset\Image) {
                        //check if high res image is called

                        preg_match("@([^\@]+)(\@[0-9.]+x)?\.([a-zA-Z]{2,5})@", $filename, $matches);

                        if (empty($matches) || !isset($matches[1])) {
                            throw $this->createNotFoundException('Requested asset does not exist');
                        }
                        if (array_key_exists(2, $matches) && $matches[2]) {
                            $highResFactor = (float)str_replace(['@', 'x'], '', $matches[2]);
                            $thumbnailConfig->setHighResolution($highResFactor);
                        }

                        // check if a media query thumbnail was requested
                        if (preg_match("#~\-~media\-\-(.*)\-\-query#", $matches[1], $mediaQueryResult)) {
                            $thumbnailConfig->selectMedia($mediaQueryResult[1]);
                        }

                        $thumbnail = $asset->getThumbnail($thumbnailConfig);
                        $thumbnailStream = $thumbnail->getStream();
                    }

                    if ($thumbnail && $thumbnailStream) {
                        if ($thumbnailType == 'image') {
                            $mime = $thumbnail->getMimeType();
                            $fileSize = $thumbnail->getFileSize();
                            $pathReference = $thumbnail->getPathReference();
                            $actualFileExtension = File::getFileExtension($pathReference['src']);

                            if ($actualFileExtension !== $requestedFileExtension) {
                                // create a copy/symlink to the file with the original file extension
                                // this can be e.g. the case when the thumbnail is called as foo.png but the thumbnail config
                                // is set to auto-optimized format so the resulting thumbnail can be jpeg
                                $requestedFile = preg_replace('/\.' . $actualFileExtension . '$/', '.' . $requestedFileExtension, $pathReference['src']);
                                $storage->writeStream($requestedFile, $thumbnailStream);
                            }
                        } elseif ($thumbnailType =='video' && isset($storagePath)) {
                            $mime = $storage->mimeType($storagePath);
                            $fileSize = $storage->fileSize($storagePath);
                        } else {
                            throw new \Exception('Cannot determine mime type and file size of '.$thumbnailType.' thumbnail, see logs for details.');
                        }
                        // set appropriate caching headers
                        // see also: https://github.com/pimcore/pimcore/blob/1931860f0aea27de57e79313b2eb212dcf69ef13/.htaccess#L86-L86
                        $lifetime = 86400 * 7; // 1 week lifetime, same as direct delivery in .htaccess

                        $headers = [
                            'Cache-Control' => 'public, max-age=' . $lifetime,
                            'Expires' => date('D, d M Y H:i:s T', time() + $lifetime),
                            'Content-Type' => $mime,
                            'Content-Length' => $fileSize,
                        ];

                        $headers[AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER] = true;

                        return new StreamedResponse(function () use ($thumbnailStream) {
                            fpassthru($thumbnailStream);
                        }, 200, $headers);
                    }

                    throw new \Exception('Unable to generate '.$thumbnailType.' thumbnail, see logs for details.');
                } catch (\Exception $e) {
                    Logger::error($e->getMessage());

                    return new RedirectResponse('/bundles/pimcoreadmin/img/filetype-not-supported.svg');
                }
            }
        }

        throw $this->createNotFoundException('Asset not found');
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function robotsTxtAction(Request $request)
    {
        // check for site
        $domain = \Pimcore\Tool::getHostname();
        $site = Site::getByDomain($domain);

        $config = Config::getRobotsConfig()->toArray();

        $siteId = 'default';
        if ($site instanceof Site) {
            $siteId = $site->getId();
        }

        // send correct headers
        header('Content-Type: text/plain; charset=utf8');
        while (@ob_end_flush()) ;

        // check for configured robots.txt in pimcore
        $content = '';
        if (array_key_exists($siteId, $config)) {
            $content = $config[$siteId];
        }

        if (empty($content)) {
            // default behavior, allow robots to index everything
            $content = "User-agent: *\nDisallow:";
        }

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function commonFilesAction(Request $request)
    {
        return new Response("HTTP/1.1 404 Not Found\nFiltered by common files filter", 404);
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function customAdminEntryPointAction(Request $request)
    {
        $params = $request->query->all();

        $url = match (true) {
            isset($params['token'])    => $this->generateUrl('pimcore_admin_login_check', $params),
            isset($params['deeplink']) => $this->generateUrl('pimcore_admin_login_deeplink', $params),
            default                    => $this->generateUrl('pimcore_admin_login', $params)
        };

        $redirect = new RedirectResponse($url);

        $customAdminPathIdentifier = $this->getParameter('pimcore_admin.custom_admin_path_identifier');
        if (!empty($customAdminPathIdentifier) && $request->cookies->get('pimcore_custom_admin') != $customAdminPathIdentifier) {
            $redirect->headers->setCookie(new Cookie('pimcore_custom_admin', $customAdminPathIdentifier, strtotime('+1 year')));
        }

        return $redirect;
    }
}
