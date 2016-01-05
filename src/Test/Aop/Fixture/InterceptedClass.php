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

namespace Inneair\TransactionBundle\Test\Aop\Fixture;

use Closure;
use ReflectionClass;
use Inneair\TransactionBundle\Aop\TransactionalInterceptor;

/**
 * A class intercepted by the {@link TransactionalInterceptor}.
 */
class InterceptedClass
{
    /**
     * A method returing a value.
     *
     * @return null Null value.
     */
    public function aMethod()
    {
        return null;
    }

    /**
     * A method throwing an exception.
     *
     * @param string $exceptionClass Exception class to be thrown.
     * @throws Exception The thrown exception.
     */
    public function bMethodThrowException($exceptionClass)
    {
        $reflectedException = new ReflectionClass($exceptionClass);
        throw $reflectedException->newInstance();
    }

    /**
     * A method returning the result of another method (to check nested transactions).
     *
     * @param Closure $nestedCallback Nested [@link TransactionalInterceptor::intercept} call.
     * @param array $parameters Parameters for the nested call.
     * @return null Null value.
     */
    public function cMethod(Closure $nestedCallback, array $parameters = null)
    {
        return call_user_func_array($nestedCallback, $parameters);
    }
}
