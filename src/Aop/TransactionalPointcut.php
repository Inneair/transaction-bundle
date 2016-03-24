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

namespace Inneair\TransactionBundle\Aop;

use Doctrine\Common\Annotations\Reader;
use JMS\AopBundle\Aop\PointcutInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use Inneair\TransactionBundle\Annotation\Transactional;
use Inneair\TransactionBundle\Annotation\TransactionalAwareInterface;
use Inneair\TransactionBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This class defines a pointcut specification for transaction management.
 */
class TransactionalPointcut implements PointcutInterface
{
    /**
     * Annotation reader in PHP class.
     * @var Reader
     */
    private $reader;
    /**
     * Logger.
     * @var LoggerInterface
     */
    private $logger;
    /**
     * Whether target classes must also implement the {@link TransactionalAwareInterface} interface.
     * @var boolean
     */
    private $strictModeEnabled;

    /**
     * Creates a transactional pointcut.
     *
     * @param ContainerInterface $container
     * @param Reader $reader An annotations reader.
     * @param LoggerInterface $logger Logger.
     * @see TransactionalAwareInterface
     */
    public function __construct(ContainerInterface $container, Reader $reader, LoggerInterface $logger)
    {
        $this->reader = $reader;
        $this->logger = $logger;
        $this->strictModeEnabled = $container->getParameter(
            Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE
        );
    }

    /**
     * The interceptor is activated for any classes (if strict mode is disabled), or classes implementing the
     * {@link TransactionalAwareInterface} interface.
     *
     * {@inheritDoc}
     */
    public function matchesClass(ReflectionClass $class)
    {
        return (!$this->strictModeEnabled || $class->implementsInterface(TransactionalAwareInterface::class));
    }

    /**
     * The interceptor is activated for public methods in Transactional annotated components.
     *
     * {@inheritDoc}
     */
    public function matchesMethod(ReflectionMethod $method)
    {
        $transactionalEnabled = false;
        if ($method->isPublic()) {
            // Gets method-level annotation.
            /** @var Transactional $annotation */
            $annotation = $this->reader->getMethodAnnotation($method, Transactional::class);
            $transactionalEnabled = ($annotation !== null);
            if (!$transactionalEnabled) {
                // If there is no method-level annotation,
                // gets class-level annotation.
                $annotation = $this->reader->getClassAnnotation($method->getDeclaringClass(), Transactional::class);
                $transactionalEnabled = ($annotation !== null);
            }

            if ($transactionalEnabled) {
                switch ($annotation->getPolicy()) {
                case Transactional::NOT_REQUIRED:
                    $policyName = 'not required';
                    break;
                case Transactional::REQUIRED:
                    REQUIRED:
                    $policyName = 'required';
                    break;
                case Transactional::NESTED:
                    NESTED:
                    $policyName = 'nested';
                    break;
                default:
                    $policyName = 'default';
                    break;
                }
                $this->logger->debug(
                    'TX policy for \'' . $method->getDeclaringClass()->getName() . '::' . $method->getName() . '\': '
                    . $policyName
                );
                $noRollbackExceptionsStr = implode(
                    ', ',
                    ($annotation->getNoRollbackExceptions() === null)
                        ? array('default')
                        : $annotation->getNoRollbackExceptions()
                );
                $this->logger->debug(
                    'TX no-rollback exceptions for \'' . $method->getDeclaringClass()->getName() . '::'
                    . $method->getName() . '\': ' . $noRollbackExceptionsStr
                );
            }
        }

        return $transactionalEnabled;
    }
}
