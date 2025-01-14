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

namespace TYPO3\CMS\Fluid\ViewHelpers\Format;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\DateFormatter;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Formats an object implementing :php:`\DateTimeInterface`.
 *
 * Possible date/time formats can be found in the PHP documentation:
 * https://www.php.net/manual/datetime.format.php
 *
 * Examples
 * ========
 *
 * Defaults
 * --------
 *
 * ::
 *
 *    <f:format.date>{dateObject}</f:format.date>
 *
 * ``1980-12-13``
 * Depending on the current date.
 *
 * Custom date format
 * ------------------
 *
 * ::
 *
 *    <f:format.date format="H:i">{dateObject}</f:format.date>
 *
 * ``01:23``
 * Depending on the current time.
 *
 * Relative date with given time
 * -----------------------------
 *
 * ::
 *
 *    <f:format.date format="Y" base="{dateObject}">-1 year</f:format.date>
 *
 * ``2016``
 * Assuming dateObject is in 2017.
 *
 * strtotime string
 * ----------------
 *
 * ::
 *
 *    <f:format.date format="d.m.Y - H:i:s">+1 week 2 days 4 hours 2 seconds</f:format.date>
 *
 * ``13.12.1980 - 21:03:42``
 * Depending on the current time, see https://www.php.net/manual/function.strtotime.php.
 *
 * Localized dates using strftime date format
 * ------------------------------------------
 *
 * ::
 *
 *    <f:format.date format="%d. %B %Y">{dateObject}</f:format.date>
 *
 * ``13. Dezember 1980``
 * Depending on the current date and defined locale. In the example you see the 1980-12-13 in a german locale.
 *
 * Localized dates using ICU-based date and time formatting
 * --------------------------------------------------------
 *
 * ::
 *
 *    <f:format.date pattern="dd. MMMM yyyy" locale="de-DE">{dateObject}</f:format.date>
 *
 * ``13. Dezember 1980``
 * Depending on the current date. In the example you see the 1980-12-13 in a german locale.
 *
 * Localized dates using default formatting patterns
 * -------------------------------------------------
 *
 * ::
 *
 *    <f:format.date pattern="FULL" locale="fr-FR">{dateObject}</f:format.date>
 *
 * ``jeudi 9 mars 2023 à 21:40:49 temps universel coordonné``
 * Depending on the current date and operating system setting. In the example you see the 2023-03-09 in a french locale.
 *
 * Inline notation
 * ---------------
 *
 * ::
 *
 *    {f:format.date(date: dateObject)}
 *
 * ``1980-12-13``
 * Depending on the value of ``{dateObject}``.
 *
 * Inline notation (2nd variant)
 * -----------------------------
 *
 * ::
 *
 *    {dateObject -> f:format.date()}
 *
 * ``1980-12-13``
 * Depending on the value of ``{dateObject}``.
 */
final class DateViewHelper extends AbstractViewHelper
{
    /**
     * Needed as child node's output can return a DateTime object which can't be escaped
     *
     * @var bool
     */
    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('date', 'mixed', 'Either an object implementing DateTimeInterface or a string that is accepted by DateTime constructor');
        $this->registerArgument('format', 'string', 'Format String which is taken to format the Date/Time', false, '');
        $this->registerArgument('pattern', 'string', 'Format date based on unicode ICO format pattern given see https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax. If both "pattern" and "format" arguments are given, pattern will be used.');
        $this->registerArgument('locale', 'string', 'A locale format such as "nl-NL" to format the date in a specific locale, if none given, uses the current locale of the current request. Only works when pattern argument is given');
        $this->registerArgument('base', 'mixed', 'A base time (an object implementing DateTimeInterface or a string) used if $date is a relative date specification. Defaults to current time.');
        $this->registerArgument('timezone', 'string', 'Timezone for the date');
    }

    /**
     * @throws Exception
     */
    public function render(): string
    {
        $format = $this->arguments['format'] ?? '';
        $pattern = $this->arguments['pattern'] ?? null;
        $base = $this->arguments['base'] ?? GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        if (is_string($base)) {
            $base = trim($base);
        }
        if ($format === '') {
            $format = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?: 'Y-m-d';
        }
        $date = $this->renderChildren();
        if ($date === null) {
            return '';
        }
        if (is_string($date)) {
            $date = trim($date);
        }
        if ($date === '') {
            $date = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp', 'now');
        }
        if (!$date instanceof \DateTimeInterface) {
            $base = $base instanceof \DateTimeInterface
                ? (int)$base->format('U')
                : (int)strtotime((MathUtility::canBeInterpretedAsInteger($base) ? '@' : '') . $base);
            $dateTimestamp = strtotime((MathUtility::canBeInterpretedAsInteger($date) ? '@' : '') . $date, $base);
            if ($dateTimestamp === false) {
                throw new Exception('"' . $date . '" could not be converted to a timestamp. Probably due to a parsing error.', 1241722579);
            }
            $date = (new \DateTime())->setTimestamp($dateTimestamp);
        }

        if (!empty($this->arguments['timezone']) && $date instanceof \DateTime) {
            $timezone = (string)$this->arguments['timezone'];
            $date->setTimezone(new \DateTimeZone($timezone));
        }

        if ($pattern !== null) {
            $locale = $this->arguments['locale'] ?? self::resolveLocale($this->renderingContext);
            return (new DateFormatter())->format($date, $pattern, $locale);
        }
        if (str_contains($format, '%')) {
            // @todo: deprecate this syntax in TYPO3 v13.
            $locale = $this->arguments['locale'] ?? self::resolveLocale($this->renderingContext);
            return (new DateFormatter())->strftime($format, $date, $locale);
        }
        return $date->format($format);
    }

    /**
     * Explicitly set argument name to be used as content.
     */
    public function getContentArgumentName(): string
    {
        return 'date';
    }

    private static function resolveLocale(RenderingContextInterface $renderingContext): Locale
    {
        $request = null;
        if ($renderingContext->hasAttribute(ServerRequestInterface::class)) {
            $request = $renderingContext->getAttribute(ServerRequestInterface::class);
        } elseif (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface) {
            // @todo: deprecate
            $request = $GLOBALS['TYPO3_REQUEST'];
        }
        if ($request && ApplicationType::fromRequest($request)->isFrontend()) {
            // Frontend application
            $siteLanguage = $request->getAttribute('language');

            // Get values from site language
            if ($siteLanguage !== null) {
                return $siteLanguage->getLocale();
            }
        } elseif (($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication
            && !empty($GLOBALS['BE_USER']->user['lang'])) {
            return new Locale($GLOBALS['BE_USER']->user['lang']);
        }
        return new Locale();
    }
}
