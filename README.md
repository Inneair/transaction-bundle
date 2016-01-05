# Inneair Transaction bundle for Symfony

[![Build status](https://secure.travis-ci.org/Inneair/TransactionBundle.png)][travis-bundle]
[![Coverage status](https://coveralls.io/repos/Inneair/TransactionBundle/badge.svg?branch=master&service=github)][coveralls-bundle]

[![Latest stable version](https://poser.pugx.org/Inneair/TransactionBundle/v/stable.png)][packagist-bundle]
[![License](https://poser.pugx.org/Inneair/TransactionBundle/license)][packagist-bundle]

This bundle provides an easy way to manage transactions with annotations. It is highly inspired from the one provided as
example by the [JMSAopBundle][jmsaop-bundle], but provides :
- a configurable policy, allowing a class/method to choose whereas a new transaction must/shall be opened, or must not
exist at all.
- an inheritance of the transactional context, from an annotated class/method to an other.
- a configurable list of exceptions that shall not trigger a rollback when a method call completes with an exception.

**Why use this bundle instead of the standard behaviour of the Doctrine-ORM's entity manager?**

If you dig into the code of the Doctrine-ORM's entity manager, we can see the entity manager already supports
recursive calls of the method `begin_transaction`, and starts really a transaction for the top call. However, it means
this behaviour - consisting of determining if a transaction shall be opened - is coded in the persistence layer.
Transactions shall be managed by business components, who are able to state what's the expected behaviour. The ORM
is part of the persistence layer, and shall not be aware of this.

# Summary
- [Installation][installation]
- [Configuration][configuration]
- [Usage][usage]
- [Example][example]

# <a name="installation"></a>Installation

## 1. Download

The bundle can be installed in your Symfony project with [Composer][composer]. Open a command console, enter your
project directory and execute the following command to download the latest stable version of this bundle:
```bash
composer require Inneair/TransactionBundle
```

## 2. Activation

Activate the bundle by modifying the `app/AppKernel.php` file:
```php
<?php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Inneair\TransactionBundle\InneairTransactionBundle(),
        );

        // ...
    }

    // ...
}
```

# <a name="configuration"></a>Configuration

## 1. YAML configuration

```yaml
inneair_transaction:
    strict_mode: false
    default_policy: required
    no_rollback_exceptions:
        - 'Company\\Bundle\\MyException1'
        - 'Company\\Bundle\\MyException2'
```

## 2. XML configuration

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:Inneair-transaction="http://example.org/schema/dic/inneair_transaction"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd
        inneair_transaction http://example.org/dic/schema/inneair_transaction/transaction-1.0.xsd">

    <Inneair-transaction:config strict-mode="false" default-policy="required">
        <Inneair-transaction:no-rollback-exception>Company\Bundle\MyException1</Inneair-transaction:no-rollback-exception>
        <Inneair-transaction:no-rollback-exception>Company\Bundle\MyException2</Inneair-transaction:no-rollback-exception>
    </Inneair-transaction:config>
</container>
```

## 3. Reference

`strict_mode`: whether classes must implement the `TransactionalAwareInterface` interface, so as the annotation
`@Transactional` is read. Default is `false`.

`default_policy`: default transactional policy when none policy is set in the annotation, which must be one of these
values:
- `required`: a transaction must be started if no transactional context already exist.
- `not-required`: a transaction must not be started, whatever there is an existing transaction context.
- `nested`: a new transaction (use of save points) must be started, even if there is already a transactional context.
Save points must be enabled in the connection parameters (see the
[DoctrineBundle configuration reference][doctrinebundle-config]). **However, we strongly recommend to avoid the use of
nested transactions. Components shall be designed to use the `required` or `not-required` strategies.**

Default is `required`.

`no_rollback_exceptions`: array of full qualified class names of exceptions, for which rollback won't be triggered if
a transactional context is alive. Use of this parameter shall be exceptional. Default is the empty array.

# <a name="usage"></a>Usage

Inside any container-managed component, insert the annotation `@Transaction` to enable automatic transaction
management. When a parameter is defined in the annotation, it overwrites the default value in the bundle configuration.

**WARNING: keep separated concerns, and decoupled components. Transaction management in controllers, though frequently
shown in examples, is not a good design orientation. Controllers in web applications are HTTP interfaces, and their role
is basically to receive requests, call business services, return responses: by definition, they belong to the
presentation layer. Transactions shall be managed by business components only.**

## 1. Whole service

The annotation can be inserted in the PHP doc block of the class itself, allowing transaction management for all public
methods. All annotation parameters are optional. If none is specified, the default configuration of the bundle applies
(see above).

```php
use Exception;
use MyCompany\MyBundle\MyException;
use Inneair\TransactionBundle\Annotation\Transactional;

/**
 * @Transactional(policy=Transactional::REQUIRED, noRollbackExceptions={Exception::class, MyException::class})
 */
class AccountManager
{
    // ...
}
```

## 2. Single method

The annotation can be inserted in the PHP doc block of a public method itself, to allow transaction management. The
annotation parameters are optional. If none is specified, the default configuration of the bundle applies (see above).

```php
use Exception;
use MyCompany\MyBundle\MyException;
use Inneair\TransactionBundle\Annotation\Transactional;

class AccountManager
{
    /**
     * @Transactional(policy=Transactional::REQUIRED, noRollbackExceptions={Exception::class, MyException::class})
     */
    public function createAccount(Account $account)
    {
        // ...
    }
}
```

# <a name="example"></a>A concrete example

Let's imagine an application dealing with human resources, and organization of people among many companies. A good
strategy (among other ones) would be to implement:
- a business service for the management of companies `CompanyManager`.
- a business service for the management of people in these companies `PersonManager`.

Let's focus on the user story: **"I want to register a new person in a new company (in one step)"**.

## 1. The `Company` model

The company is at the top of our business model, it is standalone (no dependencies).
```php
<?php

namespace Inneair\Demo\Model;

class Company
{
    /**
     * ID of the company.
     * @var integer
     */
    private $id;
    /**
     * Name of the company.
     * @var string
     */
    private $name;
    
    // Let's imagine there are a getter/setter for all properties.
    // ...
}
```

## 2. The `Person` model

The person is also a very simple concept, but in our application, a person always belongs to a company. Therefore, the
model shall contain a dependency.
```php
<?php

namespace Inneair\Demo\Model;

class Person
{
    /**
     * ID of the person.
     * @var integer
     */
    private $id;
    /**
     * First name of the person.
     * @var string
     */
    private $firstName;
    /**
     * The company the person belongs to.
     * @var Company
     */
    private $company;

    // Let's imagine there are a getter/setter for all properties.
    // ...
}
```

## 3. The `CompanyManager` service

When another component (let's say an HTTP controller) needs to add a new company, this must be done in an atomic,
consistent, isolated, and durable way (ACID principles...). A transaction is an appropriate solution! Using the
`@Transactional` annotation, each time a component calls `addCompany` method, a new transaction context will be
opened. If something goes bad in this method (an exception for instance), every operation already done in the
persistence layer is automatically rolled back, and the system remains in a consistent state. Otherwise, the transaction
is committed.
```php
<?php

namespace Inneair\Demo\Service;

use Inneair\TransactionBundle\Annotation\Transactional;

class CompanyManager implements CompanyManagerInterface
{
    /**
     * Adds a company.
     *
     * @param Company $company The company.
     * @return Company The new company.
     * @throws BusinessException If an existing company has already the same name.
     * @Transactional
     */
    public function addCompany(Company $company)
    {
        // Check there is not another company with the same name in the repository, or throw a business exception!
        // -> probably a request to the persistence layer...

        // Insert the company in a repository.
        // -> probably a request to the persistence layer...

        return $company;
    }
}
```

## 4. The `PersonManager` service

When another component (let's say an HTTP controller) needs to add a new person in a new company, this must also be done
in an atomic, consistent, isolated, and durable way (ACID principles...). If the company is created, and the person can
not be created, we want all operations to be rolled back. A transaction is also needed, so let's add the
`@Transactional` annotation for the `addPerson` method.
```php
<?php

namespace Inneair\Demo\Service;

use Inneair\TransactionBundle\Annotation\Transactional;

class PersonManager implements PersonManagerInterface
{
    /**
     * Company manager.
     * @var CompanyManagerInterface
     */
    private $companyManager;

    /**
     * Adds a person.
     *
     * @param Person $person The person.
     * @return Person The new person.
     * @Transactional
     */
    public function addPerson(Person $person)
    {
        // Add the company.
        $person->setCompany($this->companyManager->addCompany($person->getCompany()));

        // Insert the person in a repository
        // -> probably a request to the persistence layer...
        
        return $person;
    }
}
```

At this point, you may remark two transactions are opened:
- a first one when the `addPerson` is called,
- a second one when the `addCompany` is called inside the `addPerson` method.

In fact, absolutely not, and this is why this bundle really helps. With the default `required` policy, we actually
specify we want a transaction to be opened **only** if there is not an active transactional context. Then, a new
transaction is not opened when the `addCompany` is called within the transactional context of the `addPerson` method.
The transactional context is automatically inherited. On the other hand, if the `addCompany` is called outside a
transactional context, a new transaction is opened.

**Using the `@Transactional` annotation is a key factor to design reliable business services, with a low coding
effort.**

[composer]: <https://getcomposer.org/> (Get Composer)
[configuration]: <#configuration>
[coveralls-bundle]: <https://coveralls.io/github/Inneair/TransactionBundle?branch=master> (Test coverage on Coveralls)
[doctrinebundle-config]: <http://symfony.com/doc/current/bundles/DoctrineBundle/configuration.html> (DoctrineBundle configuration reference)
[example]: <#example>
[installation]: <#installation>
[jmsaop-bundle]: <https://github.com/schmittjoh/JMSAopBundle>
[packagist-bundle]: <https://packagist.org/packages/Inneair/TransactionBundle> (Bundle packages on Packagist)
[travis-bundle]: <http://travis-ci.org/Inneair/TransactionBundle> (Build status on Travis CI)
[usage]: <#usage>
