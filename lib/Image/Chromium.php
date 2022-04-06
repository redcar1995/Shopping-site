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

namespace Pimcore\Image;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use Pimcore\Tool\Session;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

/**
 * @internal
 *
 */
class Chromium
{
    /**
     * @return bool
     */
    public static function isSupported()
    {
        return (bool)self::getChromiumBinary() && class_exists(BrowserFactory::class);
    }

    /**
     * @return bool
     */
    public static function getChromiumBinary()
    {
        foreach (['chromium', 'chrome'] as $app) {
            $chromium = \Pimcore\Tool\Console::getExecutable($app);
            if ($chromium) {
                return $chromium;
            }
        }

        return false;
    }

    /**
     * @param string $url
     * @param string $outputFile
     * @param string $windowSize
     *
     * @return bool
     *
     * @throws \Exception
     */
    public static function convert(string $url, string $outputFile, string $windowSize = '1280,1024'): bool
    {
        $browserFactory = new BrowserFactory(self::getChromiumBinary());

        // starts headless chrome
        $browser = $browserFactory->createBrowser([
                'noSandbox' => file_exists('/.dockerenv'),
                'startupTimeout' => 120,
                'windowSize' => explode(',', $windowSize),
        ]);

        try {
            $headers = [];
            if (php_sapi_name() !== 'cli') {
                $headers['Cookie'] = Session::useSession(function (AttributeBagInterface $session) {
                    return Session::getSessionName() . '=' . Session::getSessionId();
                });
            }

            $page = $browser->createPage();

            if (!empty($headers)) {
                $page->getSession()->sendMessageSync(new Message(
                    'Network.setExtraHTTPHeaders',
                    ['headers' => $headers]
                ));
            }

            $page->navigate($url)->waitForNavigation();

            $page->screenshot([
                'captureBeyondViewport' => true,
                'clip' => $page->getFullPageClip(),
            ])->saveToFile($outputFile);
        } finally {
            $browser->close();

            return true;
        }
    }
}
