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

namespace TYPO3\CMS\Backend\Template\Components;

/**
 * Listeners can modify the buttons of the button bar in the backend module docheader
 *
 * @phpstan-import-type Buttons from ButtonBar
 */
final class ModifyButtonBarEvent
{
    /**
     * @param Buttons $buttons
     */
    public function __construct(private array $buttons, private readonly ButtonBar $buttonBar) {}

    /**
     * @return Buttons
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    /**
     * @param Buttons $buttons
     */
    public function setButtons(array $buttons): void
    {
        $this->buttons = $buttons;
    }

    public function getButtonBar(): ButtonBar
    {
        return $this->buttonBar;
    }
}
