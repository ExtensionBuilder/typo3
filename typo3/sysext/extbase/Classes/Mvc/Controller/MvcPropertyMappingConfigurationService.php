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

namespace TYPO3\CMS\Extbase\Mvc\Controller;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Error\Http\BadRequestException;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Security\Exception\InvalidArgumentForHashGenerationException;
use TYPO3\CMS\Extbase\Security\HashScope;

/**
 * This is a service which can generate a request hash and check whether the currently given arguments
 * fit to the request hash.
 *
 * It is used when forms are generated and submitted:
 * After a form has been generated, the method "generateTrustedPropertiesToken" is called with the names of all form fields.
 * It cleans up the array of form fields and creates another representation of it, which is then json encoded and a hmac
 * is appended. This is called the request hash.
 *
 * The json encoded form field list and the appended hmac will be submitted with the form (as attribute __trustedProperties).
 *
 * On the validation side, the validation happens in two steps:
 * 1) Check if the request hash is consistent (the hmac value fits to the json encoded field list string)
 * 2) Check that _all_ GET/POST parameters submitted occur inside the form field list of the request hash.
 *
 * Note: It is crucially important that a private key is computed into the hash value! This is done inside the HashService.
 *
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
class MvcPropertyMappingConfigurationService implements SingletonInterface
{
    protected HashService $hashService;

    public function injectHashService(HashService $hashService): void
    {
        $this->hashService = $hashService;
    }

    /**
     * Generate a request hash for a list of form fields
     *
     * @throws InvalidArgumentForHashGenerationException
     */
    public function generateTrustedPropertiesToken(array $formFieldNames, string $fieldNamePrefix = ''): string
    {
        $formFieldArray = [];
        foreach ($formFieldNames as $formField) {
            $formFieldParts = explode('[', $formField);
            $currentPosition = &$formFieldArray;
            $formFieldPartsCount = count($formFieldParts);
            for ($i = 0; $i < $formFieldPartsCount; $i++) {
                $formFieldPart = $formFieldParts[$i];
                $formFieldPart = rtrim($formFieldPart, ']');
                if (!is_array($currentPosition)) {
                    throw new InvalidArgumentForHashGenerationException('The form field "' . $formField . '" is declared as array, but it collides with a previous form field of the same name which declared the field as string. This is an inconsistency you need to fix inside your Fluid form. (String overridden by Array)', 1255072196);
                }
                if ($i === $formFieldPartsCount - 1) {
                    if (isset($currentPosition[$formFieldPart]) && is_array($currentPosition[$formFieldPart])) {
                        throw new InvalidArgumentForHashGenerationException('The form field "' . $formField . '" is declared as string, but it collides with a previous form field of the same name which declared the field as array. This is an inconsistency you need to fix inside your Fluid form. (Array overridden by String)', 1255072587);
                    }
                    // Last iteration - add a string
                    if ($formFieldPart === '') {
                        $currentPosition[] = 1;
                    } else {
                        $currentPosition[$formFieldPart] = 1;
                    }
                } else {
                    if ($formFieldPart === '') {
                        throw new InvalidArgumentForHashGenerationException('The form field "' . $formField . '" is invalid. Reason: "[]" used not as last argument, but somewhere in the middle (like foo[][bar]).', 1255072832);
                    }
                    if (!isset($currentPosition[$formFieldPart])) {
                        $currentPosition[$formFieldPart] = [];
                    }
                    $currentPosition = &$currentPosition[$formFieldPart];
                }
            }
        }
        if ($fieldNamePrefix !== '') {
            $formFieldArray = ($formFieldArray[$fieldNamePrefix] ?? []);
        }
        return $this->encodeAndHashFormFieldArray($formFieldArray);
    }

    /**
     * Encode and hash the form field array
     */
    protected function encodeAndHashFormFieldArray(array $formFieldArray): string
    {
        $encodedFormFieldArray = json_encode($formFieldArray);
        return $this->hashService->appendHmac($encodedFormFieldArray, HashScope::TrustedProperties->prefix());
    }

    /**
     * Initialize the property mapping configuration in $controllerArguments if
     * the trusted properties are set inside the request.
     *
     * @throws BadRequestException
     */
    public function initializePropertyMappingConfigurationFromRequest(Request $request, Arguments $controllerArguments): void
    {
        /** @var ExtbaseRequestParameters $extbaseRequestParameters */
        $extbaseRequestParameters = $request->getAttribute('extbase');
        $trustedPropertiesToken = $extbaseRequestParameters->getInternalArgument('__trustedProperties');
        if (!is_string($trustedPropertiesToken)) {
            return;
        }

        try {
            $encodedTrustedProperties = $this->hashService->validateAndStripHmac($trustedPropertiesToken, HashScope::TrustedProperties->prefix());
        } catch (InvalidHashStringException $e) {
            throw new BadRequestException('The HMAC of the form could not be validated.', 1581862822);
        }
        $trustedProperties = json_decode($encodedTrustedProperties, true);
        if (!is_array($trustedProperties)) {
            if (str_starts_with($encodedTrustedProperties, 'a:')) {
                throw new BadRequestException('Trusted properties used outdated serialization format instead json.', 1699604555);
            }
            throw new BadRequestException('The HMAC of the form could not be utilized.', 1691267306);
        }

        foreach ($trustedProperties as $propertyName => $propertyConfiguration) {
            if (!$controllerArguments->hasArgument($propertyName) || !is_array($propertyConfiguration)) {
                continue;
            }
            $propertyMappingConfiguration = $controllerArguments->getArgument($propertyName)->getPropertyMappingConfiguration();
            $this->modifyPropertyMappingConfiguration($propertyConfiguration, $propertyMappingConfiguration);
        }
    }

    /**
     * Modify the passed $propertyMappingConfiguration according to the $propertyConfiguration which
     * has been generated by Fluid. In detail, if the $propertyConfiguration contains
     * an __identity field, we allow modification of objects; else we allow creation.
     *
     * All other properties are specified as allowed properties.
     */
    protected function modifyPropertyMappingConfiguration(
        array $propertyConfiguration,
        PropertyMappingConfigurationInterface $propertyMappingConfiguration
    ): void {
        if (isset($propertyConfiguration['__identity'])) {
            $propertyMappingConfiguration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED, true);
            unset($propertyConfiguration['__identity']);
        } else {
            $propertyMappingConfiguration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
        }

        foreach ($propertyConfiguration as $innerKey => $innerValue) {
            if (is_array($innerValue)) {
                $this->modifyPropertyMappingConfiguration($innerValue, $propertyMappingConfiguration->forProperty($innerKey));
            }
            $propertyMappingConfiguration->allowProperties($innerKey);
        }
    }
}
