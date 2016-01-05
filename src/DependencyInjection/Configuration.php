<?php

/**
 * Copyright 2016 Inneair
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache-2.0
 */

namespace Inneair\TransactionBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class validates and merges configuration from the app/config files.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Configuration key for the default policy.
     * @var string
     */
    const DEFAULT_POLICY = 'default_policy';
    /**
     * Configuration value for default not-required transaction policy.
     * @var string
     */
    const POLICY_NOT_REQUIRED = 'not-required';
    /**
     * Configuration value for default required transaction policy.
     * @var string
     */
    const POLICY_REQUIRED = 'required';
    /**
     * Configuration value for default nested transaction policy.
     * @var string
     */
    const POLICY_NESTED = 'nested';
    /**
     * Configuration key for the root node.
     * @var string
     */
    const ROOT_NODE_NAME = 'inneair_transaction';
    /**
     * Configuration key for the strict mode.
     * @var string
     */
    const STRICT_MODE = 'strict_mode';
    /**
     * Configuration key for exceptions ignored for rollback.
     * @var string
     */
    const NO_ROLLBACK_EXCEPTIONS = 'no_rollback_exceptions';

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root(static::ROOT_NODE_NAME);
        $rootNode->fixXmlConfig('no_rollback_exception')->children()
            ->booleanNode(static::STRICT_MODE)
                ->cannotBeEmpty()
                ->defaultFalse()
                ->info('Whether classes must implement the TransactionalAwareInterface interface, so as the Transactional annotation is read.')
                ->end()
            ->enumNode(static::DEFAULT_POLICY)
                ->cannotBeEmpty()
                ->values(array(static::POLICY_REQUIRED, static::POLICY_NOT_REQUIRED, static::POLICY_NESTED))
                ->defaultValue(static::POLICY_REQUIRED)
                ->info('Default transactional policy when none policy is set in the annotation')
                ->end()
            ->arrayNode(static::NO_ROLLBACK_EXCEPTIONS)
                ->prototype('scalar')
                ->cannotBeEmpty()
                ->info('Array of FQCN of exceptions for which rollback won\'t be triggered.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
