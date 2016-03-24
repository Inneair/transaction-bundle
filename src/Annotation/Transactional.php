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

namespace Inneair\TransactionBundle\Annotation;

use Doctrine\Common\Annotations\AnnotationException;
use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * This class handles properties of the @Transactional annotation.
 * This annotation can be applied on classes or public methods. When applied on classes, all public methods inherit this
 * setting. By default, if the annotation exists with no explicit policy, the REQUIRED policy is set.
 *
 * @Annotation
 * @Target({"METHOD", "CLASS"})
 * @Attributes({
 *     @Attribute("policy", type="integer"),
 *     @Attribute("noRollbackExceptions", type="string[]")
 * })
 */
class Transactional
{
    /**
     * A transactional context is not required for this method execution.
     * No transactional context will be created, and the method will be executed in the outer transactional context, if
     * any.
     * @var integer
     */
    const NOT_REQUIRED = 1;
    /**
     * A transactional context is required for this method execution.
     * - If there is no transactional context in the call stack, this will lead to the creation of a new transaction.
     * - If there is a transactional context in the call stack, the method will be executed in this outer transactional
     * context.
     * @var integer
     */
    const REQUIRED = 2;
    /**
     * A new transactional context is required for this method execution.
     * Note that this behaviour is not strictly supported by default, and requires use of save points is enabled at
     * connection-level (see connection options).
     * - If there is no transactional context in the call stack, this will lead to the creation of a new transaction.
     * - If there is a transactional context in the call stack, this will 'only' lead to the incrementation of the
     * nesting level, and optionally to the creation of a nested transaction (with a save point), if enabled in the
     * connection configuration.
     * @var integer
     */
    const NESTED = 3;

    /**
     * Transaction policy.
     * @var integer
     */
    private $policy;
    /**
     * An array of exceptions that will not lead to a transaction rollback, if thrown during the method execution.
     * @var string[]
     */
    private $noRollbackExceptions;

    /**
     * Builds an instance of this annotation.
     *
     * @param array $options Options (defaults to an empty array).
     * @throws AnnotationException If the policy is set and has an invalid value, or if a no rollback exception does not
     * exist, or is not a valid exception.
     */
    public function __construct(array $options = array())
    {
        if (isset($options['policy'])) {
            $policy = $this->validatePolicy($options['policy']);
        } else {
            $policy = null;
        }
        $this->policy = $policy;

        if (isset($options['noRollbackExceptions'])) {
            $noRollbackExceptions = $this->validateNoRollbackExceptions(array_unique($options['noRollbackExceptions']));
        } else {
            $noRollbackExceptions = null;
        }
        $this->noRollbackExceptions = $noRollbackExceptions;
    }

    /**
     * Gets the transaction policy.
     *
     * @return integer Policy.
     */
    public function getPolicy()
    {
        return $this->policy;
    }

    /**
     * Gets the exception class names which does not trigger a rollback.
     *
     * @return string[] Exception class names. If <code>null</code>, the option was not set in the annotation, otherwise
     * the returned value is an array.
     */
    public function getNoRollbackExceptions()
    {
        return $this->noRollbackExceptions;
    }

    /**
     * Validates a policy specified in the annotation.
     *
     * @param integer $annotationPolicy Policy.
     * @return int Validated policy ID.
     * @throws AnnotationException If the policy is invalid.
     */
    private function validatePolicy($annotationPolicy)
    {
        $policies = array(static::NOT_REQUIRED, static::REQUIRED, static::NESTED);
        if (in_array($annotationPolicy, $policies)) {
            $policy = $annotationPolicy;
        } else {
            throw new AnnotationException(
                'Invalid policy: "' . $annotationPolicy . '", must be one of the constants [' .
                implode(', ', $policies) . ']'
            );
        }
        return $policy;
    }

    /**
     * Validates the no rollback exceptions in the annotation.
     *
     * @param string[] $annotationNoRollbackExceptions Exception class names.
     * @return array Rollback exceptions.
     * @throws AnnotationException If a class is not found, or is not an exception.
     */
    private function validateNoRollbackExceptions($annotationNoRollbackExceptions)
    {
        $noRollbackExceptions = array();
        foreach ($annotationNoRollbackExceptions as $exceptionClassName) {
            try {
                $exceptionClass = new ReflectionClass($exceptionClassName);
            } catch (ReflectionException $e) {
                throw new AnnotationException('Class not found: \'' . $exceptionClassName . '\'', null, $e);
            }

            if (($exceptionClassName !== Exception::class) && !$exceptionClass->isSubclassOf(Exception::class)) {
                throw new AnnotationException('Not an exception: \'' . $exceptionClassName . '\'');
            }

            $noRollbackExceptions[] = $exceptionClassName;
        }
        return $noRollbackExceptions;
    }
}
