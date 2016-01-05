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

namespace Inneair\TransactionBundle\Test\Annotation;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use Inneair\TransactionBundle\Annotation\Transactional;
use Inneair\TransactionBundle\Test\AbstractTest;
use Inneair\TransactionBundle\Test\Annotation\Fixture\UnannotatedClass;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithDefaultOptions;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithInvalidPolicyType;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithInvalidPolicyValue;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithPolicy;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithInvalidNoRollbackExceptionType;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithInvalidNoRollbackExceptionClass;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithNoRollbackExceptions;
use Inneair\TransactionBundle\Test\Annotation\Fixture\AnnotatedClassWithUnknownNoRollbackExceptionClass;

/**
 * Class containing test suite for the {@link Transactional} class.
 */
class TransactionalTest extends AbstractTest
{
    /**
     * Annotation reader.
     * @var Reader
     */
    private $reader;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->reader = new AnnotationReader();
        AnnotationRegistry::registerLoader('class_exists');
    }

    public function testUnannotatedClass()
    {
        $this->assertNull(
            $this->reader->getClassAnnotation(new ReflectionClass(UnannotatedClass::class), Transactional::class)
        );
    }

    public function testUnannotatedMethod()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        $this->assertNull(
            $this->reader->getMethodAnnotation($class->getMethod('unannotatedmethod'), Transactional::class)
        );
    }

    public function testAnnotatedClassWithDefaultOptions()
    {
        /** @var Transactional $annotation */
        $annotation = $this->reader->getClassAnnotation(
            new ReflectionClass(AnnotatedClassWithDefaultOptions::class),
            Transactional::class
        );
        $this->assertNotNull($annotation);
        $this->assertInstanceOf(Transactional::class, $annotation);
        $this->assertNull($annotation->getPolicy());
        $this->assertNull($annotation->getNoRollbackExceptions());
    }

    public function testAnnotatedClassWithInvalidPolicyType()
    {
        $hasException = false;
        try {
            $this->reader->getClassAnnotation(
                new ReflectionClass(AnnotatedClassWithInvalidPolicyType::class),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedClassWithInvalidPolicyValue()
    {
        $hasException = false;
        try {
            $this->reader->getClassAnnotation(
                new ReflectionClass(AnnotatedClassWithInvalidPolicyValue::class),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedClassWithPolicy()
    {
        /** @var Transactional $annotation */
        $annotation = $this->reader->getClassAnnotation(
            new ReflectionClass(AnnotatedClassWithPolicy::class),
            Transactional::class
        );
        $this->assertNotNull($annotation);
        $this->assertInstanceOf(Transactional::class, $annotation);
        $this->assertSame(Transactional::NESTED, $annotation->getPolicy());
    }

    public function testAnnotatedClassWithInvalidNoRollbackExceptionType()
    {
        $hasException = false;
        try {
            $this->reader->getClassAnnotation(
                new ReflectionClass(AnnotatedClassWithInvalidNoRollbackExceptionType::class),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedClassWithInvalidNoRollbackExceptionClass()
    {
        $hasException = false;
        try {
            $this->reader->getClassAnnotation(
                new ReflectionClass(AnnotatedClassWithInvalidNoRollbackExceptionClass::class),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedClassWithUnknownNoRollbackExceptionClass()
    {
        $hasException = false;
        try {
            $this->reader->getClassAnnotation(
                new ReflectionClass(AnnotatedClassWithUnknownNoRollbackExceptionClass::class),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedClassWithNoRollbackExceptions()
    {
        /** @var Transactional $annotation */
        $annotation = $this->reader->getClassAnnotation(
            new ReflectionClass(AnnotatedClassWithNoRollbackExceptions::class),
            Transactional::class
        );
        $this->assertNotNull($annotation);
        $this->assertInstanceOf(Transactional::class, $annotation);
        $noRollbackExceptions = $annotation->getNoRollbackExceptions();
        $this->assertTrue(is_array($noRollbackExceptions));
        $this->assertCount(2, $noRollbackExceptions);
        $this->assertTrue(in_array(Exception::class, $noRollbackExceptions));
        $this->assertTrue(in_array(InvalidArgumentException::class, $noRollbackExceptions));
    }

    public function testAnnotatedMethodWithDefaultOptions()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        /** @var Transactional $annotation */
        $annotation = $this->reader->getMethodAnnotation(
            $class->getMethod('annotatedMethodWithDefaultOptions'),
            Transactional::class
        );
        $this->assertNotNull($annotation);
        $this->assertInstanceOf(Transactional::class, $annotation);
        $this->assertNull($annotation->getPolicy());
        $this->assertNull($annotation->getNoRollbackExceptions());
    }

    public function testAnnotatedMethodWithInvalidPolicyType()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        $hasException = false;
        try {
            $this->reader->getMethodAnnotation(
                $class->getMethod('annotatedMethodWithInvalidPolicyType'),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedMethodWithInvalidPolicyValue()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        $hasException = false;
        try {
            $this->reader->getMethodAnnotation(
                $class->getMethod('annotatedMethodWithInvalidPolicyValue'),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedMethodWithPolicy()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        /** @var Transactional $annotation */
        $annotation = $this->reader->getMethodAnnotation(
            $class->getMethod('annotatedMethodWithPolicy'),
            Transactional::class
        );
        $this->assertNotNull($annotation);
        $this->assertInstanceOf(Transactional::class, $annotation);
        $this->assertSame(Transactional::NESTED, $annotation->getPolicy());
    }

    public function testAnnotatedMethodWithInvalidNoRollbackExceptionType()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        $hasException = false;
        try {
            $this->reader->getMethodAnnotation(
                $class->getMethod('annotatedMethodWithInvalidNoRollbackExceptionType'),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedMethodWithInvalidNoRollbackExceptionClass()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        $hasException = false;
        try {
            $this->reader->getMethodAnnotation(
                $class->getMethod('annotatedMethodWithInvalidNoRollbackExceptionClass'),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedMethodWithUnknownNoRollbackExceptionClass()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        $hasException = false;
        try {
            $this->reader->getMethodAnnotation(
                $class->getMethod('annotatedMethodWithUnknownNoRollbackExceptionClass'),
                Transactional::class
            );
        } catch (AnnotationException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testAnnotatedMethodWithNoRollbackExceptions()
    {
        $class = new ReflectionClass(AnnotatedClassWithDefaultOptions::class);
        /** @var Transactional $annotation */
        $annotation = $this->reader->getMethodAnnotation(
            $class->getMethod('annotatedMethodWithNoRollbackExceptions'),
            Transactional::class
        );
        $this->assertNotNull($annotation);
        $this->assertInstanceOf(Transactional::class, $annotation);
        $noRollbackExceptions = $annotation->getNoRollbackExceptions();
        $this->assertTrue(is_array($noRollbackExceptions));
        $this->assertCount(2, $noRollbackExceptions);
        $this->assertTrue(in_array(Exception::class, $noRollbackExceptions));
        $this->assertTrue(in_array(InvalidArgumentException::class, $noRollbackExceptions));
    }
}
