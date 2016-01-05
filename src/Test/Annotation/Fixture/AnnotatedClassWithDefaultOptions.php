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

namespace Inneair\TransactionBundle\Test\Annotation\Fixture;

use Exception;
use InvalidArgumentException;
use Inneair\TransactionBundle\Annotation\Transactional;
use stdClass;

/**
 * Class which has a {@link Transactional} annotation.
 *
 * @Transactional
 */
class AnnotatedClassWithDefaultOptions
{
    public function unannotatedmethod()
    {
    }

    /**
     * @Transactional
     */
    public function annotatedMethodWithDefaultOptions()
    {
    }

    /**
     * @Transactional(policy="none")
     */
    public function annotatedMethodWithInvalidPolicyType()
    {
    }

    /**
     * @Transactional(policy=-1)
     */
    public function annotatedMethodWithInvalidPolicyValue()
    {
    }

    /**
     * @Transactional(policy=Transactional::NESTED)
     */
    public function annotatedMethodWithPolicy()
    {
    }

    /**
     * @Transactional(noRollbackExceptions={0})
     */
    public function annotatedMethodWithInvalidNoRollbackExceptionType()
    {
    }

    /**
     * @Transactional(noRollbackExceptions=stdClass::class)
     */
    public function annotatedMethodWithInvalidNoRollbackExceptionClass()
    {
    }

    /**
     * @Transactional(noRollbackExceptions="MyException")
     */
    public function annotatedMethodWithUnknownNoRollbackExceptionClass()
    {
    }

    /**
     * @Transactional(noRollbackExceptions={Exception::class, InvalidArgumentException::class})
     */
    public function annotatedMethodWithNoRollbackExceptions()
    {
    }
}
