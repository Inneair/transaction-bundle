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

use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Inneair\TransactionBundle\Annotation\Transactional;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This class loads and manages the bundle configuration.
 */
class InneairTransactionExtension extends Extension
{
    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException If the class name of a no rollback exception cannot be found, or it is not a
     * valid exception class, or if the configuration options contain an unsupported default policy.
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter(
            Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE,
            $config[Configuration::STRICT_MODE]);

        switch ($config[Configuration::DEFAULT_POLICY]) {
        case Configuration::POLICY_NOT_REQUIRED:
            $policy = Transactional::NOT_REQUIRED;
            break;
        case Configuration::POLICY_REQUIRED:
            $policy = Transactional::REQUIRED;
            break;
        case Configuration::POLICY_NESTED:
            $policy = Transactional::NESTED;
            break;
        default:
            throw new InvalidArgumentException(
                'Unsupported default policy "' . $config[Configuration::DEFAULT_POLICY] . '"'
            );
        }
        $container->setParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY, $policy);

        $noRollbackExceptions = array_unique($config[Configuration::NO_ROLLBACK_EXCEPTIONS]);
        foreach ($noRollbackExceptions as $exceptionClassName) {
            try {
                $exceptionClass = new ReflectionClass($exceptionClassName);
            } catch (ReflectionException $e) {
                throw new InvalidArgumentException('Class not found: \'' . $exceptionClassName . '\'', null, $e);
            }

            if (($exceptionClassName !== Exception::class) && !$exceptionClass->isSubclassOf(Exception::class)) {
                throw new InvalidArgumentException('Not an exception: \'' . $exceptionClassName . '\'');
            }
        }
        $container->setParameter(
            Configuration::ROOT_NODE_NAME . '.' . Configuration::NO_ROLLBACK_EXCEPTIONS,
            $noRollbackExceptions
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__ . '/../Resources/config/schema';
    }
}
