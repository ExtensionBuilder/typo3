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

namespace TYPO3\CMS\Form\ViewHelpers\Be;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumn;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Backend\View\Drawing\DrawingConfiguration;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Backend\View\PageViewMode;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Used by the form editor.
 * Render a content element preview like the page module
 *
 * Scope: backend
 * @internal
 */
final class RenderContentElementPreviewViewHelper extends AbstractViewHelper
{
    /**
     * As this ViewHelper renders HTML, the output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('contentElementUid', 'int', 'The uid of a content element');
    }

    public function render(): string
    {
        $content = '';
        $contentElementUid = $this->arguments['contentElementUid'];
        $contentRecord = BackendUtility::getRecord('tt_content', $contentElementUid);
        $request = null;
        if ($this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        }
        if (!empty($contentRecord) && $request !== null) {
            $backendLayout = GeneralUtility::makeInstance(BackendLayout::class, 'dummy', 'dummy', []);
            $pageRow = BackendUtility::getRecord('pages', $contentRecord['pid']);
            $pageLayoutContext = GeneralUtility::makeInstance(
                PageLayoutContext::class,
                $pageRow,
                $backendLayout,
                $request->getAttribute('site') ?? new NullSite(),
                DrawingConfiguration::create($backendLayout, BackendUtility::getPagesTSconfig($contentRecord['pid']), PageViewMode::LayoutView),
                $request
            );
            $gridColumn = GeneralUtility::makeInstance(GridColumn::class, $pageLayoutContext, []);
            $columnItem = GeneralUtility::makeInstance(GridColumnItem::class, $pageLayoutContext, $gridColumn, $contentRecord);
            return $columnItem->getPreview();
        }
        return $content;
    }
}
