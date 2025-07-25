<?php

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

namespace TYPO3\CMS\Core\Configuration\Loader;

use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Configuration\Loader\Exception\YamlFileLoadingException;
use TYPO3\CMS\Core\Configuration\Loader\Exception\YamlParseException;
use TYPO3\CMS\Core\Configuration\Processor\PlaceholderProcessorList;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * A YAML file loader that allows to load YAML files, based on the Symfony/Yaml component
 *
 * In addition to just load a YAML file, it adds some special functionality.
 *
 * - A special "imports" key in the YAML file allows to include other YAML files recursively.
 *   The actual YAML file gets loaded after the import statements, which are interpreted first,
 *   at the very beginning. Imports can be referenced with a relative path.
 *
 * - Merging configuration options of import files when having simple "lists" will add items to the list instead
 *   of overwriting them.
 *
 * - Special placeholder values set via %optionA.suboptionB% replace the value with the named path of the configuration
 *   The placeholders will act as a full replacement of this value.
 *
 * - Environment placeholder values set via %env(option)% will be replaced by env variables of the same name
 */
readonly class YamlFileLoader
{
    public const PATTERN_PARTS = '%[^(%]+?\([\'"]?([^(]*?)[\'"]?\)%|%([^%()]*?)%';
    public const PROCESS_PLACEHOLDERS = 0x01;
    public const PROCESS_IMPORTS = 0x02;
    public const ALLOW_EMPTY_FILE = 0x04;

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * Loads and parses a YAML file, and returns an array with the found data
     *
     * @param string $fileName either relative to TYPO3's base project folder or prefixed with EXT:...
     * @param int $flags Flags to configure behaviour of the loader: see public PROCESS_ constants above
     * @return array the configuration as array
     */
    public function load(string $fileName, int $flags = self::PROCESS_PLACEHOLDERS | self::PROCESS_IMPORTS): array
    {
        return $this->loadAndParse($fileName, $flags, null);
    }

    /**
     * Internal method which does all the logic. Built so it can be re-used recursively.
     *
     * @param string $fileName either relative to TYPO3's base project folder or prefixed with EXT:...
     * @param string|null $currentFileName when called recursively
     * @return array the configuration as array
     */
    protected function loadAndParse(string $fileName, int $flags, ?string $currentFileName): array
    {
        $sanitizedFileName = $this->getStreamlinedFileName($fileName, $currentFileName);
        $content = $this->getFileContents($sanitizedFileName);
        try {
            $content = Yaml::parse($content);
        } catch (ParseException $e) {
            throw new YamlParseException(
                'YAML file "' . $fileName . '" has syntax errors: ' . $e->getMessage(),
                1740817000,
                $e
            );
        }

        if ($content === null && $this->hasFlag($flags, self::ALLOW_EMPTY_FILE)) {
            $content = [];
        }

        if (!is_array($content)) {
            throw new YamlParseException(
                'YAML file "' . $fileName . '" does not contain data.',
                1497332874
            );
        }

        if ($this->hasFlag($flags, self::PROCESS_IMPORTS)) {
            $content = $this->processImports($content, $flags, $sanitizedFileName);
        }
        if ($this->hasFlag($flags, self::PROCESS_PLACEHOLDERS)) {
            // Check for "%" placeholders
            $content = $this->processPlaceholders($content, $content);
        }
        return $content;
    }

    /**
     * Put into a separate method to ease the pains with unit tests
     *
     * @return string the contents or empty string if file_get_contents fails
     */
    protected function getFileContents(string $fileName): string
    {
        return is_readable($fileName) ? (string)file_get_contents($fileName) : '';
    }

    /**
     * Fetches the absolute file name, but if a different file name is given, it is built relative to that.
     *
     * @param string $fileName either relative to TYPO3's base project folder or prefixed with EXT:...
     * @param string|null $currentFileName when called recursively this contains the absolute file name of the file that included this file
     * @return string the contents of the file
     * @throws YamlFileLoadingException when the file was not accessible
     */
    protected function getStreamlinedFileName(string $fileName, ?string $currentFileName): string
    {
        if (!empty($currentFileName)) {
            if (PathUtility::isExtensionPath($fileName) || PathUtility::isAbsolutePath($fileName)) {
                $streamlinedFileName = GeneralUtility::getFileAbsFileName($fileName);
            } else {
                // Now this path is considered to be relative the current file name
                $streamlinedFileName = PathUtility::getAbsolutePathOfRelativeReferencedFileOrPath(
                    $currentFileName,
                    $fileName
                );
                if (!GeneralUtility::isAllowedAbsPath($streamlinedFileName)) {
                    throw new YamlFileLoadingException(
                        'Referencing a file which is outside of TYPO3s main folder',
                        1560319866
                    );
                }
            }
        } else {
            $streamlinedFileName = GeneralUtility::getFileAbsFileName($fileName);
        }
        if (!$streamlinedFileName) {
            throw new YamlFileLoadingException('YAML File "' . $fileName . '" could not be loaded', 1485784246);
        }
        return $streamlinedFileName;
    }

    /**
     * Checks for the special "imports" key on the main level of a file,
     * which calls "load" recursively.
     */
    protected function processImports(array $content, int $flags, ?string $fileName): array
    {
        if (isset($content['imports']) && is_array($content['imports'])) {
            // Reverse the order of imports to follow the order of the declarations, see #92100
            $content['imports'] = array_reverse($content['imports']);
            foreach ($content['imports'] as $import) {
                try {
                    $import = $this->processPlaceholders($import, $content);
                    $resource = $import['resource'];
                    if ($import['glob'] ?? false) {
                        $resource = $this->getStreamlinedFileName($resource, $fileName);
                        foreach (array_reverse(glob($resource)) as $file) {
                            $content = ArrayUtility::replaceAndAppendScalarValuesRecursive($this->loadAndParse($file, $flags, $fileName), $content);
                        }
                    } else {
                        $importedContent = $this->loadAndParse($resource, $flags, $fileName);
                        // override the imported content with the one from the current file
                        $content = ArrayUtility::replaceAndAppendScalarValuesRecursive($importedContent, $content);
                    }
                } catch (ParseException|YamlParseException|YamlFileLoadingException $exception) {
                    $this->logger->error($exception->getMessage(), ['exception' => $exception]);
                }
            }
            unset($content['imports']);
        }
        return $content;
    }

    /**
     * Main function that gets called recursively to check for %...% placeholders
     * inside the array
     *
     * @param array $content the current sub-level content array
     * @param array $referenceArray the global configuration array
     * @return array the modified sub-level content array
     */
    protected function processPlaceholders(array $content, array $referenceArray): array
    {
        foreach ($content as $k => $v) {
            if ($this->containsPlaceholder($k)) {
                $resolvedKey = $this->processPlaceholderLine($k, $referenceArray);
                if (isset($content[$resolvedKey])) {
                    if ($k === $resolvedKey) {
                        throw new \UnexpectedValueException(
                            'Unresolvable placeholder key "' . $k . '" could not be substituted.',
                            1719672440
                        );
                    }
                    throw new \UnexpectedValueException(
                        'Placeholder key "' . $k . '" can not be substituted with "' . $resolvedKey . '" because key already exists',
                        1719316250
                    );
                }
                unset($content[$k]);
                $k = $resolvedKey;
                $content[$k] = $v;
            }
            if (is_array($v)) {
                $content[$k] = $this->processPlaceholders($v, $referenceArray);
            } elseif ($this->containsPlaceholder($v)) {
                $content[$k] = $this->processPlaceholderLine($v, $referenceArray);
            }
        }
        return $content;
    }

    protected function processPlaceholderLine(string $line, array $referenceArray): mixed
    {
        $parts = $this->getParts($line);
        foreach ($parts as $partKey => $part) {
            $result = $this->processSinglePlaceholder($partKey, $part, $referenceArray);
            // Replace whole content if placeholder is the only thing in this line
            if ($line === $partKey) {
                $line = $result;
            } elseif (is_string($result) || is_numeric($result)) {
                $line = str_replace($partKey, $result, $line);
            } else {
                throw new \UnexpectedValueException(
                    'Placeholder can not be substituted if result is not string or numeric',
                    1581502783
                );
            }
            if ($result !== $partKey && $this->containsPlaceholder($line)) {
                $line = $this->processPlaceholderLine($line, $referenceArray);
            }
        }
        return $line;
    }

    protected function processSinglePlaceholder(string $placeholder, string $value, array $referenceArray): mixed
    {
        $processorList = GeneralUtility::makeInstance(
            PlaceholderProcessorList::class,
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['yamlLoader']['placeholderProcessors']
        );
        foreach ($processorList->compile() as $processor) {
            if ($processor->canProcess($placeholder, $referenceArray)) {
                try {
                    $result = $processor->process($value, $referenceArray);
                } catch (\UnexpectedValueException) {
                    $result = $placeholder;
                }
                if (is_array($result)) {
                    $result = $this->processPlaceholders($result, $referenceArray);
                }
                break;
            }
        }
        return $result ?? $placeholder;
    }

    protected function getParts(string $placeholders): array
    {
        // find occurrences of placeholders like %some()% and %array.access%.
        // Only find the innermost ones, so we can nest them.
        preg_match_all(
            '/' . self::PATTERN_PARTS . '/',
            $placeholders,
            $parts,
            PREG_UNMATCHED_AS_NULL
        );
        $matches = array_filter(
            array_merge($parts[1], $parts[2])
        );
        return array_combine($parts[0], $matches);
    }

    /**
     * Finds possible placeholders.
     * May find false positives for complexer structures, but they will be sorted later on.
     */
    protected function containsPlaceholder(mixed $value): bool
    {
        return is_string($value) && substr_count($value, '%') >= 2;
    }

    protected function hasFlag(int $flags, int $flag): bool
    {
        return ($flags & $flag) === $flag;
    }
}
