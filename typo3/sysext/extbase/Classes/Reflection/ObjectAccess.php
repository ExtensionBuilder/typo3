<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\Extbase\Reflection;

use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;

/**
 * Provides methods to call appropriate getter/setter on an object given the
 * property name. It does this following these rules:
 * - if the target object is an instance of ArrayAccess, it gets/sets the property
 * - if public getter/setter method exists, call it.
 * - if public property exists, return/set the value of it.
 * - else, throw exception
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class ObjectAccess
{
    private static ?PropertyAccessorInterface $propertyAccessor = null;

    /**
     * Get a property of a given object.
     * Tries to get the property the following ways:
     * - if the target is an array, and has this property, we call it.
     * - if public getter method exists, call it.
     * - if the target object is an instance of ArrayAccess, it gets the property
     * on it if it exists.
     * - if public property exists, return the value of it.
     * - else, throw exception
     *
     * @param object|array $subject Object or array to get the property from
     * @param string $propertyName name of the property to retrieve
     *
     * @throws \InvalidArgumentException in case $subject was not an object or $propertyName was not a string
     * @throws Exception\PropertyNotAccessibleException
     * @return mixed Value of the property
     */
    public static function getProperty(object|array $subject, string $propertyName): mixed
    {
        try {
            return self::getPropertyInternal($subject, $propertyName);
        } catch (NoSuchIndexException) {
            return null;
        }
    }

    /**
     * Gets a property of a given object or array.
     * This is an internal method that does only limited type checking for performance reasons.
     * If you can't make sure that $subject is either of type array or object and $propertyName of type string you should use getProperty() instead.
     *
     * @see getProperty()
     *
     * @param object|array $subject Object or array to get the property from
     * @param string $propertyName name of the property to retrieve
     *
     * @throws Exception\PropertyNotAccessibleException
     * @return mixed Value of the property
     * @internal
     */
    public static function getPropertyInternal(object|array $subject, string $propertyName): mixed
    {
        if ($subject instanceof \SplObjectStorage || $subject instanceof ObjectStorage) {
            $subject = iterator_to_array(clone $subject, false);
        }

        $propertyPath = new PropertyPath($propertyName);

        if ($subject instanceof \ArrayAccess) {
            $accessor = self::createAccessor();

            // Check if $subject is an instance of \ArrayAccess and therefore maybe has actual accessible properties.
            if ($accessor->isReadable($subject, $propertyPath)) {
                return $accessor->getValue($subject, $propertyPath);
            }

            // Use array style property path for instances of \ArrayAccess
            // https://symfony.com/doc/current/components/property_access.html#reading-from-arrays

            $propertyPath = self::convertToArrayPropertyPath($propertyPath);
        }

        if (is_object($subject)) {
            return self::getObjectPropertyValue($subject, $propertyPath);
        }

        try {
            return self::getArrayIndexValue($subject, self::convertToArrayPropertyPath($propertyPath));
        } catch (NoSuchIndexException) {
            return null;
        }
    }

    /**
     * Gets a property path from a given object or array.
     *
     * If propertyPath is "bla.blubb", then we first call getProperty($object, 'bla'),
     * and on the resulting object we call getProperty(..., 'blubb')
     *
     * For arrays the keys are checked likewise.
     *
     * @param object|array $subject Object or array to get the property path from
     * @param string $propertyPath
     *
     * @return mixed Value of the property
     */
    public static function getPropertyPath(object|array $subject, string $propertyPath): mixed
    {
        try {
            foreach (new PropertyPath($propertyPath) as $pathSegment) {
                $subject = self::getPropertyInternal($subject, $pathSegment);
            }
        } catch (\TypeError|PropertyNotAccessibleException) {
            return null;
        }
        return $subject;
    }

    /**
     * Set a property for a given object.
     * Tries to set the property the following ways:
     * - if target is an array, set value
     * - if super cow powers should be used, set value through reflection
     * - if public setter method exists, call it.
     * - if public property exists, set it directly.
     * - if the target object is an instance of ArrayAccess, it sets the property
     * on it without checking if it existed.
     * - else, return FALSE
     *
     * @param object|array $subject The target object or array
     * @param string $propertyName Name of the property to set
     * @param mixed $propertyValue Value of the property
     *
     * @throws \InvalidArgumentException in case $object was not an object or $propertyName was not a string
     * @return bool TRUE if the property could be set, FALSE otherwise
     */
    public static function setProperty(object|array &$subject, string $propertyName, mixed $propertyValue): bool
    {
        if (is_array($subject) || $subject instanceof \ArrayAccess) {
            $subject[$propertyName] = $propertyValue;
            return true;
        }

        $accessor = self::createAccessor();
        if ($accessor->isWritable($subject, $propertyName)) {
            $accessor->setValue($subject, $propertyName, $propertyValue);
            return true;
        }
        return false;
    }

    /**
     * Returns an array of properties which can be get with the getProperty()
     * method.
     * Includes the following properties:
     * - which can be get through a public getter method.
     * - public properties which can be directly get.
     *
     * @param object $object Object to receive property names for
     *
     * @return list<string> Array of all gettable property names
     * @throws Exception\UnknownClassException
     */
    public static function getGettablePropertyNames(object $object): array
    {
        if ($object instanceof \stdClass) {
            $properties = array_keys((array)$object);
            sort($properties);
            return $properties;
        }

        $classSchema = GeneralUtility::makeInstance(ReflectionService::class)
            ->getClassSchema($object);

        $accessiblePropertyNames = [];
        foreach ($classSchema->getProperties() as $propertyName => $propertyDefinition) {
            if ($propertyDefinition->isPublic()) {
                $accessiblePropertyNames[] = $propertyName;
                continue;
            }

            $accessors = [
                'get' . ucfirst($propertyName),
                'has' . ucfirst($propertyName),
                'is' . ucfirst($propertyName),
            ];

            foreach ($accessors as $accessor) {
                if (!$classSchema->hasMethod($accessor)) {
                    continue;
                }

                if (!$classSchema->getMethod($accessor)->isPublic()) {
                    continue;
                }

                foreach ($classSchema->getMethod($accessor)->getParameters() as $methodParam) {
                    if (!$methodParam->isOptional()) {
                        continue 2;
                    }
                }

                if (!is_callable([$object, $accessor])) {
                    continue;
                }

                $accessiblePropertyNames[] = $propertyName;
            }
        }

        // Fallback mechanism to not break former behaviour
        //
        // todo: Checking accessor methods of virtual(non-existing) properties should be removed (breaking) in
        //       upcoming versions. It was an unintentionally added "feature" in the past. It contradicts the method
        //       name "getGettablePropertyNames".
        foreach ($classSchema->getMethods() as $methodName => $methodDefinition) {
            $propertyName = null;
            if (str_starts_with($methodName, 'get') || str_starts_with($methodName, 'has')) {
                $propertyName = lcfirst(substr($methodName, 3));
            }

            if (str_starts_with($methodName, 'is')) {
                $propertyName = lcfirst(substr($methodName, 2));
            }

            if ($propertyName === null) {
                continue;
            }

            if (!$methodDefinition->isPublic()) {
                continue;
            }

            foreach ($methodDefinition->getParameters() as $methodParam) {
                if (!$methodParam->isOptional()) {
                    continue 2;
                }
            }

            $accessiblePropertyNames[] = $propertyName;
        }

        $accessiblePropertyNames = array_unique($accessiblePropertyNames);
        sort($accessiblePropertyNames);
        return $accessiblePropertyNames;
    }

    /**
     * Returns an array of properties which can be set with the setProperty()
     * method.
     * Includes the following properties:
     * - which can be set through a public setter method.
     * - public properties which can be directly set.
     *
     * @param object $object Object to receive property names for
     *
     * @throws \InvalidArgumentException
     * @return list<string> Array of all settable property names
     */
    public static function getSettablePropertyNames(object $object): array
    {
        $accessor = self::createAccessor();

        if ($object instanceof \stdClass || $object instanceof \ArrayAccess) {
            $propertyNames = array_keys((array)$object);
        } else {
            $classSchema = GeneralUtility::makeInstance(ReflectionService::class)->getClassSchema($object);

            $propertyNames = array_filter(
                array_keys($classSchema->getProperties()),
                static fn(string $propertyName): bool => $accessor->isWritable($object, $propertyName)
            );

            $setters = array_filter(
                array_keys($classSchema->getMethods()),
                static fn(string $methodName): bool => str_starts_with($methodName, 'set') && is_callable([$object, $methodName])
            );

            foreach ($setters as $setter) {
                $propertyNames[] = lcfirst(substr($setter, 3));
            }
        }

        $propertyNames = array_unique($propertyNames);
        sort($propertyNames);
        return $propertyNames;
    }

    /**
     * Tells if the value of the specified property can be set by this Object Accessor.
     *
     * @param object $object Object containing the property
     * @param string $propertyName Name of the property to check
     */
    public static function isPropertySettable(object $object, string $propertyName): bool
    {
        if ($object instanceof \stdClass && array_key_exists($propertyName, get_object_vars($object))) {
            return true;
        }
        if (array_key_exists($propertyName, get_class_vars(get_class($object)))) {
            return true;
        }
        return is_callable([$object, 'set' . ucfirst($propertyName)]);
    }

    /**
     * Tells if the value of the specified property can be retrieved by this Object Accessor.
     *
     * @param object|array $object Object containing the property
     * @param string $propertyName Name of the property to check
     *
     * @throws \InvalidArgumentException
     */
    public static function isPropertyGettable(object|array $object, string $propertyName): bool
    {
        if (is_array($object) || ($object instanceof \ArrayAccess && $object->offsetExists($propertyName))) {
            $propertyName = self::wrap($propertyName);
        }

        return self::createAccessor()->isReadable($object, $propertyName);
    }

    /**
     * Get all properties (names and their current values) of the current
     * $object that are accessible through this class.
     *
     * @param object $object Object to get all properties from.
     *
     * @throws \InvalidArgumentException
     * @return array<string, mixed> Associative array of all properties.
     * @todo What to do with ArrayAccess
     */
    public static function getGettableProperties(object $object): array
    {
        $properties = [];
        foreach (self::getGettablePropertyNames($object) as $propertyName) {
            $properties[$propertyName] = self::getPropertyInternal($object, $propertyName);
        }
        return $properties;
    }

    private static function createAccessor(): PropertyAccessor
    {
        if (self::$propertyAccessor === null) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
                ->enableExceptionOnInvalidIndex()
                ->getPropertyAccessor();
        }

        return self::$propertyAccessor;
    }

    /**
     * @throws Exception\PropertyNotAccessibleException
     */
    private static function getObjectPropertyValue(object $subject, PropertyPath $propertyPath): mixed
    {
        $accessor = self::createAccessor();

        if ($accessor->isReadable($subject, $propertyPath)) {
            return $accessor->getValue($subject, $propertyPath);
        }

        throw new PropertyNotAccessibleException('The property "' . (string)$propertyPath . '" on the subject does not exist.', 1476109666);
    }

    private static function getArrayIndexValue(array $subject, PropertyPath $propertyPath): mixed
    {
        return self::createAccessor()->getValue($subject, $propertyPath);
    }

    private static function convertToArrayPropertyPath(PropertyPath $propertyPath): PropertyPath
    {
        $segments = array_map(static fn(string $segment): string => static::wrap($segment), $propertyPath->getElements());

        return new PropertyPath(implode('.', $segments));
    }

    private static function wrap(string $segment): string
    {
        return '[' . $segment . ']';
    }
}
