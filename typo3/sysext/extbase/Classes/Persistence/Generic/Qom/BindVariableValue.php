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

namespace TYPO3\CMS\Extbase\Persistence\Generic\Qom;

/**
 * Evaluates to the value of a bind variable.
 *
 * @internal only to be used within Extbase, not part of TYPO3 Core API.
 */
final readonly class BindVariableValue implements BindVariableValueInterface
{
    public function __construct(protected string $variableName) {}

    public function collectBoundVariableNames(array &$boundVariables): void
    {
        $boundVariables[$this->variableName] = null;
    }

    public function getBindVariableName(): string
    {
        return $this->variableName;
    }
}
