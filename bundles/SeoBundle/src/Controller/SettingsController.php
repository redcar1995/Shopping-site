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

namespace Pimcore\Bundle\SeoBundle\Controller;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Config;
use Pimcore\Model\Tool\SettingsStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends AdminController
{
    /**
     * @Route("/robots-txt", name="pimcore_bundle_seo_settings_robotstxtget", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function robotsTxtGetAction(): JsonResponse
    {
        $this->checkPermission('robots.txt');

        $config = Config::getRobotsConfig();

        return $this->adminJson([
            'success' => true,
            'data' => $config,
            'onFileSystem' => file_exists(PIMCORE_WEB_ROOT . '/robots.txt'),
        ]);
    }

    /**
     * @Route("/robots-txt", name="pimcore_bundle_seo_settings_robotstxtput", methods={"PUT"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function robotsTxtPutAction(Request $request): JsonResponse
    {
        $this->checkPermission('robots.txt');

        $values = $request->get('data');
        if (!is_array($values)) {
            $values = [];
        }

        foreach ($values as $siteId => $robotsContent) {
            SettingsStore::set('robots.txt-' . $siteId, $robotsContent, 'string', 'robots.txt');
        }

        return $this->adminJson([
            'success' => true,
        ]);
    }
}
