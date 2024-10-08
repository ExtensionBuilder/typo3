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

namespace TYPO3\CMS\Core\TypoScript\IncludeTree\IncludeNode;

/**
 * A node created for "<INCLUDE_TYPOSCRIPT: ..." includes.
 *
 * @internal: Internal tree structure.
 * @deprecated: Remove together with related code in v14, search for keyword INCLUDE_TYPOSCRIPT
 */
final class IncludeTyposcriptInclude extends AbstractInclude {}
