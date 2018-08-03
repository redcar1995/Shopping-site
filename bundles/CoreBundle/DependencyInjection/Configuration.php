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

namespace Pimcore\Bundle\CoreBundle\DependencyInjection;

use Pimcore\Cache\Pool\Redis;
use Pimcore\Storage\Redis\ConnectionFactory;
use Pimcore\Targeting\Storage\CookieStorage;
use Pimcore\Targeting\Storage\TargetingStorageInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('pimcore');
        $rootNode->addDefaultsIfNotSet();

        $rootNode
            ->children()
                ->arrayNode('error_handling')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('render_error_document')
                            ->info('Render error document in case of an error instead of showing Symfony\'s error page')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('bundles')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('search_paths')
                            ->prototype('scalar')->end()
                        ->end()
                        ->booleanNode('handle_composer')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('flags')
                    ->info('Generic map for feature flags, such as `zend_date`')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('translations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('case_insensitive')
                            ->info('Force pimcore translations to NOT be case sensitive. This only applies to translations set via Pimcore\'s translator (e.g. website translations)')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('debugging')
                            ->info('If debugging is enabled, the translator will return the plain translation key instead of the translated message.')
                            ->addDefaultsIfNotSet()
                            ->canBeDisabled()
                            ->children()
                                ->scalarNode('parameter')
                                    ->defaultValue('pimcore_debug_translations')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('maps')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('tile_layer_url_template')
                            ->defaultValue('https://a.tile.openstreetmap.org/{z}/{x}/{y}.png')
                        ->end()
                        ->scalarNode('geocoding_url_template')
                            ->defaultValue('https://nominatim.openstreetmap.org/search?q={q}&addressdetails=1&format=json&limit=1')
                        ->end()
                        ->scalarNode('reverse_geocoding_url_template')
                            ->defaultValue('https://nominatim.openstreetmap.org/reverse?format=json&lat={lat}&lon={lon}&addressdetails=1')
                        ->end()
                    ->end()
                ->end()
            ->end();

        $this->addObjectsNode($rootNode);
        $this->addAssetNode($rootNode);
        $this->addDocumentsNode($rootNode);
        $this->addEncryptionNode($rootNode);
        $this->addModelsNode($rootNode);
        $this->addRoutingNode($rootNode);
        $this->addCacheNode($rootNode);
        $this->addContextNode($rootNode);
        $this->addAdminNode($rootNode);
        $this->addWebProfilerNode($rootNode);
        $this->addSecurityNode($rootNode);
        $this->addNewsletterNode($rootNode);
        $this->addCustomReportsNode($rootNode);
        $this->addMigrationsNode($rootNode);
        $this->addTargetingNode($rootNode);
        $this->addSitemapsNode($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    private function addModelsNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('models')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('class_overrides')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar');
    }

    /**
     * Add asset specific extension config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addAssetNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('assets')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('image')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('low_quality_image_preview')
                                ->addDefaultsIfNotSet()
                                ->canBeDisabled()
                                ->children()
                                    ->scalarNode('generator')
                                    ->defaultNull()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('focal_point_detection')
                                ->addDefaultsIfNotSet()
                                ->canBeDisabled()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('versions')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('use_hardlinks')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Add object specific extension config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addObjectsNode(ArrayNodeDefinition $rootNode)
    {
        $objectsNode = $rootNode
            ->children()
                ->arrayNode('objects')
                    ->addDefaultsIfNotSet();

        $classDefinitionsNode = $objectsNode
            ->children()
                ->arrayNode('class_definitions')
                    ->addDefaultsIfNotSet();

        $this->addImplementationLoaderNode($classDefinitionsNode, 'data');
        $this->addImplementationLoaderNode($classDefinitionsNode, 'layout');
    }

    /**
     * Add encryption specific extension config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addEncryptionNode(ArrayNodeDefinition $rootNode)
    {
        $encryptionNode = $rootNode
            ->children()
            ->arrayNode('encryption')->addDefaultsIfNotSet();

        $encryptionNode
            ->children()
            ->scalarNode('secret')->defaultNull();
    }

    /**
     * Add document specific extension config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addDocumentsNode(ArrayNodeDefinition $rootNode)
    {
        $documentsNode = $rootNode
            ->children()
                ->arrayNode('documents')
                    ->addDefaultsIfNotSet();

        $this->addImplementationLoaderNode($documentsNode, 'tags');

        $documentsNode
            ->children()
                ->arrayNode('editables')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('naming_strategy')
                            ->info('Sets naming strategy used to build editable names')
                            ->values(['legacy', 'nested'])
                            ->defaultValue('nested')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('areas')
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->booleanNode('autoload')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Add implementation node config (map, prefixes)
     *
     * @param ArrayNodeDefinition $node
     * @param string $name
     */
    private function addImplementationLoaderNode(ArrayNodeDefinition $node, $name)
    {
        $node
            ->children()
                ->arrayNode($name)
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('map')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('prefixes')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addRoutingNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('routing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('static')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('locale_params')
                                    ->info('Route params from this list will be mapped to _locale if _locale is not set explicitely')
                                    ->prototype('scalar')
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
    }

    /**
     * Add context config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addContextNode(ArrayNodeDefinition $rootNode)
    {
        $contextNode = $rootNode->children()
            ->arrayNode('context');

        /** @var ArrayNodeDefinition|NodeDefinition $prototype */
        $prototype = $contextNode->prototype('array');

        // define routes child on each context entry
        $this->addRoutesChild($prototype, 'routes');
    }

    /**
     * Add admin config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addAdminNode(ArrayNodeDefinition $rootNode)
    {
        $adminNode = $rootNode->children()
            ->arrayNode('admin')
            ->addDefaultsIfNotSet();

        // add session attribute bag config
        $this->addAdminSessionAttributeBags($adminNode);

        // unauthenticated routes won't be double checked for authentication in AdminControllerListener
        $this->addRoutesChild($adminNode, 'unauthenticated_routes');

        $adminNode
            ->children()
                ->arrayNode('translations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('path')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param ArrayNodeDefinition $adminNode
     */
    private function addAdminSessionAttributeBags(ArrayNodeDefinition $adminNode)
    {
        // Normalizes session bag config. Allows the following formats (all formats will be
        // normalized to the third format.
        //
        // attribute_bags:
        //      - foo
        //      - bar
        //
        // attribute_bags:
        //      foo: _foo
        //      bar: _bar
        //
        // attribute_bags:
        //      foo:
        //          storage_key: _foo
        //      bar:
        //          storage_key: _bar
        $normalizers = [
            'assoc' => function (array $array) {
                $result = [];
                foreach ($array as $name => $value) {
                    if (null === $value) {
                        $value = [
                            'storage_key' => '_' . $name
                        ];
                    }

                    if (is_string($value)) {
                        $value = [
                            'storage_key' => $value
                        ];
                    }

                    $result[$name] = $value;
                }

                return $result;
            },

            'sequential' => function (array $array) {
                $result = [];
                foreach ($array as $name) {
                    $result[$name] = [
                        'storage_key' => '_' . $name
                    ];
                }

                return $result;
            }
        ];

        $adminNode
            ->children()
                ->arrayNode('session')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('attribute_bags')
                            ->useAttributeAsKey('name')
                            ->beforeNormalization()
                                ->ifArray()->then(function ($v) use ($normalizers) {
                                    if (isAssocArray($v)) {
                                        return $normalizers['assoc']($v);
                                    } else {
                                        return $normalizers['sequential']($v);
                                    }
                                })
                            ->end()
                            ->example([
                                ['foo', 'bar'],
                                [
                                    'foo' => '_foo',
                                    'bar' => '_bar',
                                ],
                                [
                                    'foo' => [
                                        'storage_key' => '_foo'
                                    ],
                                    'bar' => [
                                        'storage_key' => '_bar'
                                    ]
                                ]
                            ])
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('storage_key')
                                        ->defaultNull()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addSecurityNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('encoder_factories')
                            ->info('Encoder factories to use as className => factory service ID mapping')
                            ->example([
                                'AppBundle\Model\DataObject\User1' => [
                                    'id' => 'website_demo.security.encoder_factory2'
                                ],
                                'AppBundle\Model\DataObject\User2' => 'website_demo.security.encoder_factory2'
                            ])
                            ->useAttributeAsKey('class')
                            ->prototype('array')
                            ->beforeNormalization()->ifString()->then(function ($v) {
                                return ['id' => $v];
                            })->end()
                            ->children()
                                ->scalarNode('id')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Configure exclude paths for web profiler toolbar
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addWebProfilerNode(ArrayNodeDefinition $rootNode)
    {
        $webProfilerNode = $rootNode->children()
            ->arrayNode('web_profiler')
                ->example([
                    'toolbar' => [
                        'excluded_routes' => [
                            ['path' => '^/test/path']
                        ]
                    ]
                ])
                ->addDefaultsIfNotSet();

        $toolbarNode = $webProfilerNode->children()
            ->arrayNode('toolbar')
                ->addDefaultsIfNotSet();

        $this->addRoutesChild($toolbarNode, 'excluded_routes');
    }

    /**
     * Add a route prototype child
     *
     * @param ArrayNodeDefinition $parent
     * @param $name
     */
    private function addRoutesChild(ArrayNodeDefinition $parent, $name)
    {
        $node = $parent->children()->arrayNode($name);

        /** @var ArrayNodeDefinition|NodeDefinition $prototype */
        $prototype = $node->prototype('array');
        $prototype
            ->beforeNormalization()
                ->ifNull()->then(function () {
                    return [];
                })
            ->end()
            ->children()
                ->scalarNode('path')->defaultFalse()->end()
                ->scalarNode('route')->defaultFalse()->end()
                ->scalarNode('host')->defaultFalse()->end()
                ->arrayNode('methods')
                    ->prototype('scalar')->end()
                ->end()
            ->end();
    }

    /**
     * Add cache config
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addCacheNode(ArrayNodeDefinition $rootNode)
    {
        $defaultOptions = ConnectionFactory::getDefaultOptions();

        $rootNode->children()
            ->arrayNode('cache')
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('pool_service_id')
                        ->defaultValue(null)
                    ->end()
                    ->integerNode('default_lifetime')
                        ->defaultValue(2419200) // 28 days
                    ->end()
                    ->arrayNode('pools')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('doctrine')
                                ->canBeDisabled()
                                ->children()
                                    ->scalarNode('connection')
                                        ->defaultValue('default')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('redis')
                                ->canBeEnabled()
                                ->children()
                                    ->arrayNode('connection')
                                        ->info('Redis connection options. See ' . ConnectionFactory::class)
                                        ->children()
                                            ->scalarNode('server')->end()
                                            ->integerNode('port')
                                                ->defaultValue($defaultOptions['port'])
                                            ->end()
                                            ->integerNode('database')
                                                ->defaultValue($defaultOptions['database'])
                                            ->end()
                                            ->scalarNode('password')
                                                ->defaultValue($defaultOptions['password'])
                                            ->end()
                                            ->scalarNode('persistent')
                                                ->defaultValue($defaultOptions['persistent'])
                                            ->end()
                                            ->booleanNode('force_standalone')
                                                ->defaultValue($defaultOptions['force_standalone'])
                                            ->end()
                                            ->integerNode('connect_retries')
                                                ->defaultValue($defaultOptions['connect_retries'])
                                            ->end()
                                            ->floatNode('timeout')
                                                ->defaultValue($defaultOptions['timeout'])
                                            ->end()
                                            ->floatNode('read_timeout')
                                                ->defaultValue($defaultOptions['read_timeout'])
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('options')
                                        ->info('Redis cache pool options. See ' . Redis::class)
                                        ->children()
                                            ->booleanNode('notMatchingTags')->end()
                                            ->integerNode('compress_tags')->end()
                                            ->integerNode('compress_data')->end()
                                            ->integerNode('compress_threshold')->end()
                                            ->scalarNode('compression_lib')->end()
                                            ->booleanNode('use_lua')->end()
                                            ->integerNode('lua_max_c_stack')->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();
    }

    /**
     * Adds configuration tree for newsletter source adapters
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addNewsletterNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('newsletter')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('source_adapters')
                            ->useAttributeAsKey('name')
                                ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds configuration tree for custom report adapters
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addCustomReportsNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('custom_report')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('adapters')
                            ->useAttributeAsKey('name')
                                ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds configuration tree node for migrations
     *
     * @param ArrayNodeDefinition $rootNode
     */
    private function addMigrationsNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('migrations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('sets')
                            ->useAttributeAsKey('identifier')
                            ->defaultValue([])
                            ->info('Migration sets which can be used apart from bundle migrations. Use the -s option in migration commands to select a specific set.')
                            ->example([
                                [
                                    'custom_set' => [
                                        'name'       => 'Custom Migrations',
                                        'namespace'  => 'App\\Migrations\\Custom',
                                        'directory'  => 'src/App/Migrations/Custom'
                                    ],
                                    'custom_set_2' => [
                                        'name'       => 'Custom Migrations 2',
                                        'namespace'  => 'App\\Migrations\\Custom2',
                                        'directory'  => 'src/App/Migrations/Custom2',
                                        'connection' => 'custom_connection'
                                    ],
                                ]
                            ])
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('identifier')->end()
                                    ->scalarNode('name')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('namespace')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('directory')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('connection')
                                        ->info('If defined, the DBAL connection defined here will be used')
                                        ->defaultNull()
                                        ->beforeNormalization()
                                            ->ifTrue(function ($v) {
                                                return empty(trim($v));
                                            })
                                            ->then(function () {
                                                return null;
                                            })
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addTargetingNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('targeting')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage_id')
                            ->info('Service ID of the targeting storage which should be used. This ID will be aliased to ' . TargetingStorageInterface::class)
                            ->defaultValue(CookieStorage::class)
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('session')
                            ->info('Enables HTTP session support by configuring session bags and the full page cache')
                            ->canBeEnabled()
                        ->end()
                        ->arrayNode('data_providers')
                            ->useAttributeAsKey('key')
                                ->prototype('scalar')
                            ->end()
                        ->end()
                        ->arrayNode('conditions')
                            ->useAttributeAsKey('key')
                                ->prototype('scalar')
                            ->end()
                        ->end()
                        ->arrayNode('action_handlers')
                            ->useAttributeAsKey('name')
                                ->prototype('scalar')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addSitemapsNode(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('sitemaps')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('generators')
                            ->useAttributeAsKey('name')
                            ->prototype('array')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) {
                                        return [
                                            'enabled'      => true,
                                            'generator_id' => $v,
                                            'priority'     => 0
                                        ];
                                    })
                                ->end()
                                ->addDefaultsIfNotSet()
                                ->canBeDisabled()
                                ->children()
                                    ->scalarNode('generator_id')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->integerNode('priority')
                                        ->defaultValue(0)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
    }
}
