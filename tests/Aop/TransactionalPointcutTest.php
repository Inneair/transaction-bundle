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

namespace Inneair\TransactionBundle\Test\Aop;

use Doctrine\Common\Annotations\Reader;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Inneair\TransactionBundle\Annotation\Transactional;
use Inneair\TransactionBundle\Aop\TransactionalPointcut;
use Inneair\TransactionBundle\DependencyInjection\Configuration;
use Inneair\TransactionBundle\Test\AbstractTest;
use Inneair\TransactionBundle\Test\Aop\Fixture\NonTransactionalAwareClass;
use Inneair\TransactionBundle\Test\Aop\Fixture\TransactionalAwareClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class containing test suite for the {@link TransactionalPointcut} class.
 */
class TransactionalPointcutTest extends AbstractTest
{
    /**
     * Mocked service container.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $container;
    /**
     * Mocked logger.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;
    /**
     * Mocked annotation reader.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $reader;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->container = $this->getMock(ContainerInterface::class);
        $this->logger = $this->getMock(LoggerInterface::class);
        $this->reader = $this->getMock(Reader::class);
    }

    public function testMatchesNonTransactionalAwareClassWithoutStrictMode()
    {
        $this->assertTrue(
            $this->buildTransactionPointcut(false)->matchesClass(
                new ReflectionClass(NonTransactionalAwareClass::class)
            )
        );
    }

    public function testMatchesNonTransactionalAwareClassWithStrictMode()
    {
        $this->assertFalse(
            $this->buildTransactionPointcut(true)->matchesClass(
                new ReflectionClass(NonTransactionalAwareClass::class)
            )
        );
    }

    public function testMatchesTransactionalAwareClassWithStrictMode()
    {
        $this->assertTrue(
            $this->buildTransactionPointcut(true)->matchesClass(
                new ReflectionClass(TransactionalAwareClass::class)
            )
        );
    }

    public function testMatchesNonPublicMethod()
    {
        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertFalse($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('nonPublicMethod')));
        $this->assertFalse($this->buildTransactionPointcut(true)->matchesMethod($class->getMethod('nonPublicMethod')));
    }

    public function testMatchesAnnotatedPublicMethodWithDefaultPolicy()
    {
        $this->reader->expects(static::exactly(2))->method('getMethodAnnotation')->willReturn(new Transactional());
        $this->reader->expects(static::never())->method('getClassAnnotation');

        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertTrue($this->buildTransactionPointcut(true)->matchesMethod($class->getMethod('publicMethod')));
        $this->assertTrue($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('publicMethod')));
    }

    public function testMatchesAnnotatedPublicMethodWithNotRequiredPolicy()
    {
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(
            new Transactional(array('policy' => Transactional::NOT_REQUIRED))
        );
        $this->reader->expects(static::never())->method('getClassAnnotation');

        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertTrue($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('publicMethod')));
    }

    public function testMatchesAnnotatedPublicMethodWithRequiredPolicy()
    {
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(
            new Transactional(array('policy' => Transactional::REQUIRED))
        );
        $this->reader->expects(static::never())->method('getClassAnnotation');

        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertTrue($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('publicMethod')));
    }

    public function testMatchesAnnotatedPublicMethodWithNestedPolicy()
    {
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(
            new Transactional(array('policy' => Transactional::NESTED))
        );
        $this->reader->expects(static::never())->method('getClassAnnotation');

        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertTrue($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('publicMethod')));
    }

    public function testMatchesUnannotatedPublicMethodInAnnotatedClass()
    {
        $this->reader->expects(static::exactly(2))->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::exactly(2))->method('getClassAnnotation')->willReturn(new Transactional());

        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertTrue($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('publicMethod')));
        $this->assertTrue($this->buildTransactionPointcut(true)->matchesMethod($class->getMethod('publicMethod')));
    }

    public function testMatchesUnannotatedPublicMethodInUnannotatedClass()
    {
        $this->reader->expects(static::exactly(2))->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::exactly(2))->method('getClassAnnotation')->willReturn(null);

        $class = new ReflectionClass(TransactionalAwareClass::class);
        $this->assertFalse($this->buildTransactionPointcut(false)->matchesMethod($class->getMethod('publicMethod')));
        $this->assertFalse($this->buildTransactionPointcut(true)->matchesMethod($class->getMethod('publicMethod')));
    }

    /**
     * Builds a transactional pointcut with/without strict mode enabled.
     *
     * @param boolean $strictModeEnabled Whether strict mode is enabled.
     * @return TransactionalPointcut Pointcut.
     */
    private function buildTransactionPointcut($strictModeEnabled)
    {
        $this->container->expects(static::any())->method('getParameter')->with(
            Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE
        )->willReturn($strictModeEnabled);

        return new TransactionalPointcut($this->container, $this->reader, $this->logger);
    }
}
