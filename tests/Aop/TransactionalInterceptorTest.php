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

use CG\Proxy\MethodInvocation;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Inneair\TransactionBundle\Annotation\Transactional;
use Inneair\TransactionBundle\Aop\TransactionalInterceptor;
use Inneair\TransactionBundle\DependencyInjection\Configuration;
use Inneair\TransactionBundle\Test\AbstractTest;
use Inneair\TransactionBundle\Test\Aop\Fixture\InterceptedClass;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class containing test suite for the {@link TransactionalInterceptor} class.
 */
class TransactionalInterceptorTest extends AbstractTest
{
    /**
     * Mocked service container.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $container;
    /**
     * Mocked entity manager.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $entityManager;
    /**
     * Mocked entity manager registry.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $entityManagerRegistry;
    /**
     * Mocked DB connection.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $connection;
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
     * Mocked repository.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $repository;
    /**
     * Transactional interceptor.
     * @var TransactionalInterceptor
     */
    private $transactionalInterceptor;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->container = $this->getMock(ContainerInterface::class);

        $this->connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $this->repository = $this->getMockBuilder(ObjectRepository::class)->disableOriginalConstructor()->getMock();

        $this->entityManager = $this->getMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->any())->method('getConnection')->willReturn($this->connection);
        $this->entityManager->expects($this->any())->method('getRepository')->willReturn($this->repository);

        $this->entityManagerRegistry = $this->getMock(RegistryInterface::class);
        $this->entityManagerRegistry->expects(static::any())->method('getManager')->willReturn($this->entityManager);

        $this->logger = $this->getMock(LoggerInterface::class);

        $this->reader = $this->getMock(Reader::class);

        $this->transactionalInterceptor = new TransactionalInterceptor(
            $this->container,
            $this->entityManagerRegistry,
            $this->reader,
            $this->logger
        );
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class. The method will
     * invoke a nested method (new transaction required), that should lead to opening two nested transactions.
     */
    public function testNestedNewRequiredTransactionForAnnotatedMethodInUnannotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $this->reader->expects(static::never())->method('getClassAnnotation');

        // Method 'cMethod' requires a transaction.
        $annotationRequired = new Transactional($this->buildTransactionalOptions(Transactional::REQUIRED));
        // Nested method 'aMethod' requires a new transaction whatever the transactional context.
        $annotationRequiresNew = new Transactional($this->buildTransactionalOptions(Transactional::NESTED));

        $this->reader->expects(static::exactly(2))->method('getMethodAnnotation')->willReturnOnConsecutiveCalls(
            $annotationRequired,
            $annotationRequiresNew
        );

        $this->entityManager->expects(static::exactly(2))->method('beginTransaction');
        $this->entityManager->expects(static::exactly(2))->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $nestedTransaction = function ($class, $instance)
        {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array()));
        };

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('cMethod'), $instance,
                    array($nestedTransaction, array($class, $instance)), array())
            )
        );
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class. The method will
     * invoke a nested method (new transaction required), that should lead to opening two nested transactions.
     */
    public function testNestedNewRequiredTransactionForUnannotatedMethodInAnnotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $this->reader->expects(static::exactly(2))->method('getMethodAnnotation')->willReturn(null);

        // Method 'cMethod' requires a transaction.
        $annotationRequired = new Transactional($this->buildTransactionalOptions(Transactional::REQUIRED));

        // Nested method 'aMethod' requires a new transaction whatever the transactional context.
        $annotationRequiresNew = new Transactional($this->buildTransactionalOptions(Transactional::NESTED));

        $this->reader->expects(static::exactly(2))->method('getClassAnnotation')->willReturnOnConsecutiveCalls(
            $annotationRequired,
            $annotationRequiresNew
        );

        $this->entityManager->expects(static::exactly(2))->method('beginTransaction');
        $this->entityManager->expects(static::exactly(2))->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $nestedTransaction = function ($class, $instance)
        {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array()));
        };

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('cMethod'), $instance,
                    array($nestedTransaction, array($class, $instance)), array())
            )
        );
    }

    /**
     * Invokes the interceptor for an annotated method (transaction not required), in an unannotated class.
     */
    public function testNotRequiredTransactionForAnnotatedMethodInUnannotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional($this->buildTransactionalOptions(Transactional::NOT_REQUIRED));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn($annotation);
        $this->reader->expects(static::never())->method('getClassAnnotation');
        $this->entityManager->expects(static::never())->method('beginTransaction');
        $this->entityManager->expects(static::never())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array())
            )
        );
    }

    /**
     * Invokes the interceptor for an annotated method (transaction not required), in an unannotated class.
     */
    public function testNotRequiredTransactionForUnannotatedMethodInAnnotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional($this->buildTransactionalOptions(Transactional::NOT_REQUIRED));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn($annotation);
        $this->entityManager->expects(static::never())->method('beginTransaction');
        $this->entityManager->expects(static::never())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array())
            )
        );
    }

    public function testDefaultOptions()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();
        $exceptionClassName = Exception::class;

        $annotation = new Transactional();
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn($annotation);
        $this->container->expects(static::exactly(2))->method('getParameter')->willReturnCallback(
            function ($parameter) use($exceptionClassName)
            {
                if ($parameter === (Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)) {
                    $value = Transactional::REQUIRED;
                } elseif ($parameter === (Configuration::ROOT_NODE_NAME . '.' . Configuration::NO_ROLLBACK_EXCEPTIONS)) {
                    $value = array($exceptionClassName);
                } else {
                    $value = null;
                }
                return $value;
            });
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::once())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $hasException = false;
        try {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation(
                    $class->getMethod('bMethodThrowException'),
                    $instance,
                    array($exceptionClassName),
                    array()
                )
            );
        } catch (Exception $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class.
     */
    public function testRequiredTransactionForAnnotatedMethodInUnannotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional($this->buildTransactionalOptions(Transactional::REQUIRED));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn($annotation);
        $this->reader->expects(static::never())->method('getClassAnnotation');
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::once())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array())
            )
        );
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class. The method
     * throws an exception that is ignored by the interceptor, and the transaction is committed.
     */
    public function testRequiredTransactionForAnnotatedMethodInUnannotatedClassWithIgnoredException()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional(
            $this->buildTransactionalOptions(Transactional::REQUIRED, array(Exception::class))
        );
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn($annotation);
        $this->reader->expects(static::never())->method('getClassAnnotation');
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::once())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $hasException = false;
        try {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation(
                    $class->getMethod('bMethodThrowException'),
                    $instance,
                    array(Exception::class),
                    array()
                )
            );
        } catch (Exception $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class. The method
     * throws an exception that rollbacks the transaction.
     */
    public function testRequiredTransactionForAnnotatedMethodInUnannotatedClassWithException()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional($this->buildTransactionalOptions(Transactional::REQUIRED));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn($annotation);
        $this->reader->expects(static::never())->method('getClassAnnotation');
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::never())->method('commit');
        $this->entityManager->expects(static::once())->method('rollback');

        $hasException = false;
        try {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('bMethodThrowException'), $instance, array(Exception::class),
                    array()
                )
            );
        } catch (Exception $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Invokes the interceptor for an unannotated method, in an annotated class (transaction required).
     */
    public function testRequiredTransactionForUnannotatedMethodInAnnotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional($this->buildTransactionalOptions(Transactional::REQUIRED));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn($annotation);
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::once())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array())
            )
        );
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class. The method
     * throws an exception that is ignored by the interceptor, and the transaction is committed.
     */
    public function testRequiredTransactionForUnannotatedMethodInAnnotatedClassWithIgnoredException()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional(
            $this->buildTransactionalOptions(Transactional::REQUIRED, array(Exception::class)));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn($annotation);
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::once())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $hasException = false;
        try {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('bMethodThrowException'), $instance, array(Exception::class),
                    array()
                )
            );
        } catch (Exception $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Invokes the interceptor for an annotated method (transaction required), in an unannotated class. The method
     * throws an exception that rollbacks the transaction.
     */
    public function testRequiredTransactionForUnannotatedMethodInAnnotatedClassWithException()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $annotation = new Transactional($this->buildTransactionalOptions(Transactional::REQUIRED));
        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn($annotation);
        $this->entityManager->expects(static::once())->method('beginTransaction');
        $this->entityManager->expects(static::never())->method('commit');
        $this->entityManager->expects(static::once())->method('rollback');

        $hasException = false;
        try {
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('bMethodThrowException'), $instance, array(Exception::class),
                    array()
                )
            );
        } catch (Exception $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Invokes the interceptor for a method in a class, both unannotated.
     */
    public function testUnannotatedMethodInUnannotatedClass()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn(null);
        $this->logger->expects(static::once())->method('warning');
        $this->entityManager->expects(static::never())->method('beginTransaction');
        $this->entityManager->expects(static::never())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $this->assertNull(
            $this->transactionalInterceptor->intercept(
                new MethodInvocation($class->getMethod('aMethod'), $instance, array(), array())
            )
        );
    }

    /**
     * Invokes the interceptor for a method in a class, both unannotated, which throws an exception.
     */
    public function testUnannotatedMethodInUnannotatedClassWithException()
    {
        $class = new ReflectionClass(InterceptedClass::class);
        $instance = $class->newInstance();

        $this->reader->expects(static::once())->method('getMethodAnnotation')->willReturn(null);
        $this->reader->expects(static::once())->method('getClassAnnotation')->willReturn(null);
        $this->logger->expects(static::once())->method('warning');
        $this->entityManager->expects(static::never())->method('beginTransaction');
        $this->entityManager->expects(static::never())->method('commit');
        $this->entityManager->expects(static::never())->method('rollback');

        $hasException = false;
        try {
            $this->assertNull(
                $this->transactionalInterceptor->intercept(
                    new MethodInvocation(
                        $class->getMethod('bMethodThrowException'),
                        $instance,
                        array(Exception::class),
                        array()
                    )
                )
            );
        } catch (Exception $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Builds the array of options for the {@link Transactional} annotation.
     *
     * @param integer $policy Policy.
     * @param string[] $noRollbackExceptions Array of exception class names.
     * @return array
     */
    private function buildTransactionalOptions($policy = null, array $noRollbackExceptions = null)
    {
        $options = array();
        if ($policy !== null) {
            $options['policy'] = $policy;
        }
        if ($noRollbackExceptions !== null) {
            $options['noRollbackExceptions'] = $noRollbackExceptions;
        }
        return $options;
    }
}
