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

namespace Inneair\TransactionBundle\Test\DependencyInjection;

use Doctrine\Common\Annotations\Reader;
use Exception;
use InvalidArgumentException;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use Inneair\TransactionBundle\Annotation\Transactional;
use Inneair\TransactionBundle\DependencyInjection\Configuration;
use Inneair\TransactionBundle\DependencyInjection\InneairTransactionExtension;
use Inneair\TransactionBundle\Test\AbstractTest;
use Inneair\TransactionBundle\Test\DependencyInjection\Fixture\InvalidConfiguration;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class InneairTransactionExtensionTest extends AbstractTest
{
    /**
     * The extension.
     * @var InneairTransactionExtension
     */
    private $extension;
    /**
     * Container builder.
     * @var ContainerBuilder
     */
    private $container;
    /**
     * Mocked annotation reader.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $annotationReader;
    /**
     * Mocked logger.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;
    /**
     * Mocked entity manager registry.
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    private $entityManagerRegistry;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->annotationReader = $this->getMock(Reader::class);
        $this->logger = $this->getMock(LoggerInterface::class);
        $this->entityManagerRegistry = $this->getMock(RegistryInterface::class);
        $this->extension = new InneairTransactionExtension();

        $this->container = new ContainerBuilder();
        $this->container->set('annotation_reader', $this->annotationReader);
        $this->container->set('logger', $this->logger);
        $this->container->set('doctrine', $this->entityManagerRegistry);
        $this->container->registerExtension($this->extension);
    }

    public function testDefaultConfiguration()
    {
        $this->extension->load(array(), $this->container);

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertFalse(
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertSame(
            Transactional::REQUIRED,
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
    }

    public function testConfigurationWithStrictMode()
    {
        $this->extension->load(array(array(Configuration::STRICT_MODE => true)), $this->container);

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertTrue(
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
    }

    public function testConfigurationWithoutStrictMode()
    {
        $this->extension->load(array(array(Configuration::STRICT_MODE => false)), $this->container);

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertFalse(
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
    }

    public function testConfigurationWithRequiredPolicy()
    {
        $this->extension->load(
            array(array(Configuration::DEFAULT_POLICY => Configuration::POLICY_REQUIRED)),
            $this->container
        );

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertSame(
            Transactional::REQUIRED,
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
    }

    public function testConfigurationWithNotRequiredPolicy()
    {
        $this->extension->load(
            array(array(Configuration::DEFAULT_POLICY => Configuration::POLICY_NOT_REQUIRED)),
            $this->container
        );

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertSame(
            Transactional::NOT_REQUIRED,
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
    }

    public function testConfigurationWithNestedPolicy()
    {
        $this->extension->load(
            array(array(Configuration::DEFAULT_POLICY => Configuration::POLICY_NESTED)),
            $this->container);

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertSame(
            Transactional::NESTED,
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
    }

    public function testConfigurationWithUnsupportedPolicy()
    {
        $extension = $this->getMock(InneairTransactionExtension::class, array('getConfiguration'));
        $extension->expects(static::once())->method('getConfiguration')->willReturn(new InvalidConfiguration());
        $hasException = false;
        try {
            $extension->load(array(array(Configuration::DEFAULT_POLICY => null)), $this->container);
        } catch (InvalidArgumentException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testYamlFullConfiguration()
    {
        $this->loadConfigurationFile('config-full.yml');

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertTrue(
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertSame(
            Transactional::NESTED,
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::NO_ROLLBACK_EXCEPTIONS)
        );
        $noRollbackExceptions = $this->container->getParameter(
            Configuration::ROOT_NODE_NAME . '.' . Configuration::NO_ROLLBACK_EXCEPTIONS
        );
        $this->assertCount(1, $noRollbackExceptions);
        $this->assertSame('Exception', current($noRollbackExceptions));
    }

    public function testYamlConfigurationWithUnknownExceptionClass()
    {
        $hasException = false;
        try {
            $this->loadConfigurationFile('config-with-unknown-exception-class.yml');
        } catch (InvalidArgumentException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testYamlConfigurationWithInvalidExceptionClass()
    {
        $hasException = false;
        try {
            $this->loadConfigurationFile('config-with-invalid-exception-class.yml');
        } catch (InvalidArgumentException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testXmlFullConfiguration()
    {
        $this->loadConfigurationFile('config-full.xml');

        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertTrue(
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::STRICT_MODE)
        );
        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertSame(
            Transactional::NESTED,
            $this->container->getParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::DEFAULT_POLICY)
        );
        $this->assertTrue(
            $this->container->hasParameter(Configuration::ROOT_NODE_NAME . '.' . Configuration::NO_ROLLBACK_EXCEPTIONS)
        );
        $noRollbackExceptions = $this->container->getParameter(
            Configuration::ROOT_NODE_NAME . '.' . Configuration::NO_ROLLBACK_EXCEPTIONS
        );
        $this->assertCount(1, $noRollbackExceptions);
        $this->assertSame('Exception', current($noRollbackExceptions));
    }

    public function testXmlConfigurationWithUnknownExceptionClass()
    {
        $hasException = false;
        try {
            $this->loadConfigurationFile('config-with-unknown-exception-class.xml');
        } catch (InvalidArgumentException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    public function testXmlConfigurationWithInvalidExceptionClass()
    {
        $hasException = false;
        try {
            $this->loadConfigurationFile('config-with-invalid-exception-class.xml');
        } catch (InvalidArgumentException $e) {
            $hasException = true;
        }
        $this->assertTrue($hasException);
    }

    /**
     * Loads a configuration file.
     *
     * @param string $configFileName Name of a configuration file.
     * @throws InvalidArgumentException If the configuration contains a class name that does not exist, or is not an
     * exception.
     * @throws Exception If the configuration file is not supported.
     */
    private function loadConfigurationFile($configFileName)
    {
        $xmlExtension = '.xml';
        $ymlExtension = '.yml';
        $fileLocator = new FileLocator(__DIR__ . '/Fixture');
        if (mb_strrpos($configFileName, $ymlExtension) === (mb_strlen($configFileName) - mb_strlen($ymlExtension))) {
            $loader = new YamlFileLoader($this->container, $fileLocator);
        } elseif (
            mb_strrpos($configFileName, $xmlExtension) === (mb_strlen($configFileName) - mb_strlen($xmlExtension))
        ) {
            $loader = new XmlFileLoader($this->container, $fileLocator);
        } else {
            throw Exception('Configuration file not supported: ' . $configFileName);
        }
        $loader->load($configFileName);

        $this->container->compile();
    }
}
