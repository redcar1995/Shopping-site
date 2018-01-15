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
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\AdminBundle\Controller\Admin;

use Pimcore\Analytics\Google\Config\SiteConfigProvider;
use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Config;
use Pimcore\Controller\Configuration\TemplatePhp;
use Pimcore\Event\Admin\IndexSettingsEvent;
use Pimcore\Event\AdminEvents;
use Pimcore\Google;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Schedule\Manager\Procedural;
use Pimcore\Model\User;
use Pimcore\Templating\Model\ViewModel;
use Pimcore\Tool;
use Pimcore\Tool\Admin;
use Pimcore\Tool\Session;
use Pimcore\Version;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

class IndexController extends AdminController
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route("/", name="pimcore_admin_index")
     * @TemplatePhp()
     *
     * @param Request $request
     * @param SiteConfigProvider $siteConfigProvider
     *
     * @return ViewModel
     *
     * @throws \Exception
     */
    public function indexAction(
        Request $request,
        SiteConfigProvider $siteConfigProvider
    ) {
        $user = $this->getAdminUser();
        $view = new ViewModel([
            'config' => Config::getSystemConfig()
        ]);

        $this
            ->addRuntimePerspective($view, $user)
            ->addReportConfig($view)
            ->addPluginAssets($view);

        $settings = $this->buildPimcoreSettings($request, $view, $user);
        $this->buildGoogleAnalyticsSettings($view, $settings, $siteConfigProvider);

        // allow to alter settings via an event
        $this->eventDispatcher->dispatch(AdminEvents::INDEX_SETTINGS, new IndexSettingsEvent($settings));

        $view->settings = $settings;

        return $view;
    }

    /**
     * @param ViewModel $view
     * @param User $user
     *
     * @return $this
     */
    protected function addRuntimePerspective(ViewModel $view, User $user)
    {
        $runtimePerspective = Config::getRuntimePerspective($user);

        $view->runtimePerspective = $runtimePerspective;
        $view->extjsDev           = isset($runtimePerspective['extjsDev']) ? $runtimePerspective['extjsDev'] : false;

        return $this;
    }

    /**
     * @param ViewModel $view
     *
     * @return $this
     */
    protected function addReportConfig(ViewModel $view)
    {
        // TODO where is this used?
        $view->report_config = Config::getReportConfig();

        return $this;
    }

    /**
     * @param ViewModel $view
     *
     * @return $this
     */
    protected function addPluginAssets(ViewModel $view)
    {
        $bundleManager = $this->get('pimcore.extension.bundle_manager');

        $view->pluginJsPaths  = $bundleManager->getJsPaths();
        $view->pluginCssPaths = $bundleManager->getCssPaths();

        return $this;
    }

    /**
     * Build pimcore.settings data
     *
     * @param Request $request
     * @param ViewModel $view
     * @param User $user
     *
     * @return ViewModel
     */
    protected function buildPimcoreSettings(Request $request, ViewModel $view, User $user)
    {
        $config = $view->config;

        $settings = new ViewModel([
            'version'   => Version::getVersion(),
            'build'     => Version::getRevision(),
            'buildDate'     => Version::getBuildDate(),
            'debug'     => \Pimcore::inDebugMode(),
            'devmode'   => PIMCORE_DEVMODE || $view->extjsDev,
            'sessionId' => htmlentities(Session::getSessionId(), ENT_QUOTES, 'UTF-8'),
            'isLegacyModeAvailable' => \Pimcore::isLegacyModeAvailable()
        ]);

        // languages
        $settings->getParameters()->add([
            'language'         => $request->getLocale(),
            'websiteLanguages' => Admin::reorderWebsiteLanguages(
                $this->getAdminUser(),
                $config->general->validLanguages,
                true
            )
        ]);

        // flags
        $namingStrategy = $this->get('pimcore.document.tag.naming.strategy');

        $settings->getParameters()->add([
            'showCloseConfirmation' => true,
            'debug_admin_translations' => (bool)$config->general->debug_admin_translations,
            'document_generatepreviews' => (bool)$config->documents->generatepreview,
            'document_naming_strategy' => $namingStrategy->getName(),
            'asset_disable_tree_preview' => (bool)$config->assets->disable_tree_preview,
            'htmltoimage' => \Pimcore\Image\HtmlToImage::isSupported(),
            'videoconverter' => \Pimcore\Video::isAvailable(),
            'asset_hide_edit' => (bool)$config->assets->hide_edit_image,
        ]);

        $dashboardHelper = new \Pimcore\Helper\Dashboard($user);

        // perspective and portlets
        $settings->getParameters()->add([
            'perspective'           => $view->runtimePerspective,
            'availablePerspectives' => Config::getAvailablePerspectives($user),
            'disabledPortlets'      => $dashboardHelper->getDisabledPortlets(),
        ]);

        $this
            ->addSystemVarSettings($settings)
            ->addCsrfToken($settings, $user)
            ->addMaintenanceSettings($settings)
            ->addMailSettings($settings, $config)
            ->addCustomViewSettings($settings);

        return $settings;
    }

    private function buildGoogleAnalyticsSettings(
        ViewModel $view,
        ViewModel $settings,
        SiteConfigProvider $siteConfigProvider
    ) {
        $config = $view->config;

        $settings->getParameters()->add([
            'google_analytics_enabled'      => (bool)$siteConfigProvider->isSiteReportingConfigured(),
            'google_webmastertools_enabled' => (bool)Google\Webmastertools::isConfigured(),
            'google_maps_api_key'           => $config->services->google->browserapikey ?: ''
        ]);
    }

    /**
     * @param ViewModel $settings
     *
     * @return $this
     */
    protected function addSystemVarSettings(ViewModel $settings)
    {
        // upload limit
        $max_upload = filesize2bytes(ini_get('upload_max_filesize') . 'B');
        $max_post   = filesize2bytes(ini_get('post_max_size') . 'B');
        $upload_mb  = min($max_upload, $max_post);

        $settings->upload_max_filesize = (int)$upload_mb;

        // session lifetime (gc)
        $session_gc_maxlifetime = ini_get('session.gc_maxlifetime');
        if (empty($session_gc_maxlifetime)) {
            $session_gc_maxlifetime = 120;
        }

        $settings->session_gc_maxlifetime = (int)$session_gc_maxlifetime;

        return $this;
    }

    /**
     * @param ViewModel $settings
     * @param User $user
     *
     * @return $this
     */
    protected function addCsrfToken(ViewModel $settings, User $user)
    {
        $csrfToken = Session::useSession(function (AttributeBagInterface $adminSession) use ($user) {
            if (!$adminSession->has('csrfToken') && !$adminSession->get('csrfToken')) {
                $adminSession->set('csrfToken', sha1(microtime() . $user->getName() . uniqid()));
            }

            return $adminSession->get('csrfToken');
        });

        $settings->csrfToken = $csrfToken;

        return $this;
    }

    /**
     * @param ViewModel $settings
     *
     * @return $this
     */
    protected function addMaintenanceSettings(ViewModel $settings)
    {
        // check maintenance
        $maintenance_active = false;

        $manager = $this->get(Procedural::class);

        $lastExecution = $manager->getLastExecution();
        if ($lastExecution) {
            if ((time() - $lastExecution) < 3660) { // maintenance script should run at least every hour + a little tolerance
                $maintenance_active = true;
            }
        }

        $settings->maintenance_active = $maintenance_active;
        $settings->maintenance_mode    = Admin::isInMaintenanceMode();

        return $this;
    }

    /**
     * @param ViewModel $settings
     * @param \stdClass $config
     *
     * @return $this
     */
    protected function addMailSettings(ViewModel $settings, $config)
    {
        //mail settings
        $mailIncomplete = false;
        if ($config->email) {
            if (!$config->email->debug->emailaddresses) {
                $mailIncomplete = true;
            }
            if (!$config->email->sender->email) {
                $mailIncomplete = true;
            }
            if ($config->email->method == 'smtp' && !$config->email->smtp->host) {
                $mailIncomplete = true;
            }
        }

        $settings->mail = !$mailIncomplete;

        return $this;
    }

    /**
     * @param ViewModel $settings
     *
     * @return $this
     */
    protected function addCustomViewSettings(ViewModel $settings)
    {
        $cvData = [];

        // still needed when publishing objects
        $cvConfig = Tool::getCustomViewConfig();

        if ($cvConfig) {
            foreach ($cvConfig as $node) {
                $tmpData = $node;
                // backwards compatibility
                $treeType = $tmpData['treetype'] ? $tmpData['treetype'] : 'object';
                $rootNode = Service::getElementByPath($treeType, $tmpData['rootfolder']);

                if ($rootNode) {
                    $tmpData['rootId']         = $rootNode->getId();
                    $tmpData['allowedClasses'] = $tmpData['classes'] ? explode(',', $tmpData['classes']) : null;
                    $tmpData['showroot']       = (bool)$tmpData['showroot'];

                    // Check if a user has privileges to that node
                    if ($rootNode->isAllowed('list')) {
                        $cvData[] = $tmpData;
                    }
                }
            }
        }

        $settings->customviews = $cvData;

        return $this;
    }
}
