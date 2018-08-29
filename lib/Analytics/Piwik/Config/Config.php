<?php

declare(strict_types=1);

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

namespace Pimcore\Analytics\Piwik\Config;

use Pimcore\Config\Config as ConfigObject;

class Config
{
    /**
     * @var ConfigObject
     */
    private $config;

    public function __construct(ConfigObject $config)
    {
        $this->config = $config;
    }

    public static function fromReportConfig(ConfigObject $reportConfig): self
    {
        $config = null;
        if ($reportConfig->piwik) {
            $config = $reportConfig->piwik;
        } else {
            $config = new ConfigObject([]);
        }

        return new self($config);
    }

    public function isConfigured(): bool
    {
        if (null === $this->getPiwikUrl()) {
            return false;
        }

        return true;
    }

    public function getConfig(): ConfigObject
    {
        return $this->config;
    }

    public function isSiteConfigured(string $configKey): bool
    {
        $config = $this->getConfigForSite($configKey);

        if (null === $config) {
            return false;
        }

        $piwikSiteId = $this->normalizePiwikSiteId($config);
        if (null === $piwikSiteId) {
            return false;
        }

        return true;
    }

    /**
     * @param string $configKey
     *
     * @return null|ConfigObject
     */
    public function getConfigForSite(string $configKey)
    {
        if (!$this->config->sites || !$this->config->sites->$configKey) {
            return null;
        }

        return $this->config->sites->$configKey;
    }

    public function getConfiguredSites(): array
    {
        $sites = $this->config->get('sites');
        if ($sites && $sites instanceof ConfigObject) {
            return array_keys($sites->toArray());
        }

        return [];
    }

    /**
     * @return string|null
     */
    public function getPiwikUrl()
    {
        $url = $this->normalizeStringValue($this->config->piwik_url);

        if (null !== $url && 0 !== strpos($url, 'http')) {
            $url = null;

            @trigger_error(
                'Configured Piwik URL does not include a protocol (https:// or http://). Please update your settings to re-enable Piwik.',
                E_USER_DEPRECATED
            );
        }

        return $url;
    }

    /**
     * @return string|null
     */
    public function getApiToken()
    {
        return $this->normalizeStringValue($this->config->api_token);
    }

    /**
     * @return string|null
     */
    public function getReportToken()
    {
        return $this->normalizeStringValue($this->config->report_token);
    }

    public function getApiClientOptions(): array
    {
        $value = $this->normalizeStringValue($this->config->api_client_options);
        if (empty($value)) {
            return [];
        }

        $options = @json_decode($value, true);
        if (is_array($options)) {
            return $options;
        }

        return [];
    }

    /**
     * @param string $configKey
     *
     * @return int|null
     */
    public function getPiwikSiteId(string $configKey)
    {
        $config = $this->getConfigForSite($configKey);
        if (null !== $config) {
            return $this->normalizePiwikSiteId($config);
        }
    }

    public function isIframeIntegrationConfigured(): bool
    {
        return !empty($this->getIframeUsername()) && !empty($this->getIframePassword());
    }

    /**
     * @return null|string
     */
    public function getIframeUsername()
    {
        return $this->normalizeStringValue($this->config->iframe_username);
    }

    /**
     * @return null|string
     */
    public function getIframePassword()
    {
        return $this->normalizeStringValue($this->config->iframe_password);
    }

    public function generateIframeUrl(array $parameters = []): string
    {
        if (!$this->isIframeIntegrationConfigured()) {
            throw new \RuntimeException('Iframe integration is not configured');
        }

        $parameters = array_merge([
            'module' => 'Login',
            'action' => 'logme',
            'login' => $this->getIframeUsername(),
            'password' => $this->getIframePassword(),
        ], $parameters);

        return sprintf(
            '%s/index.php?%s',
            rtrim($this->getPiwikUrl(), '/'),
            http_build_query($parameters)
        );
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    private function normalizeStringValue($value)
    {
        if (null === $value) {
            return $value;
        }

        $value = trim((string)$value);
        if (empty($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param ConfigObject $config
     *
     * @return int|null
     */
    private function normalizePiwikSiteId(ConfigObject $config)
    {
        if (!$config->site_id) {
            return null;
        }

        $piwikSiteId = (int)$config->site_id;
        if ($piwikSiteId > 0) {
            return $piwikSiteId;
        }
    }
}
