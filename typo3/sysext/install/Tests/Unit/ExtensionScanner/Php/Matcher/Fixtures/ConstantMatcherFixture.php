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

namespace TYPO3\CMS\Install\Tests\Unit\ExtensionScanner\Php\Matcher\Fixtures;

/**
 * Fixture file
 */
class ConstantMatcherFixture
{
    public function aMethod(): void
    {
        // Matches
        $foo = TYPO3_DLOG;

        // No match
        $foo = \My\Project\AClass::TYPO3_DLOG;
        // @extensionScannerIgnoreLine
        $foo = TYPO3_DLOG;

        // Match (again). No longer ignored.
        $foo = TYPO3_DLOG;
    }
}
