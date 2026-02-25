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

import { html, LitElement, type TemplateResult } from 'lit';
import { customElement, state } from 'lit/decorators.js';
import { repeat } from 'lit/directives/repeat.js';
import OpenDocumentStore, { OpenDocumentStoreChangedEvent } from '../open-document-store';
import type { OpenDocument } from '../open-document-store';
import '@typo3/backend/element/icon-element';
import '@typo3/backend/element/breadcrumb';
import '@typo3/backend/element/datetime-element';
import labels from '~labels/opendocs.messages';

/**
 * Toolbar dropdown menu listing recent documents.
 */
@customElement('typo3-open-document-menu')
export class OpenDocumentMenuElement extends LitElement {
  @state() private recentDocuments: OpenDocument[] = [];

  public override connectedCallback(): void {
    super.connectedCallback();
    document.addEventListener(OpenDocumentStoreChangedEvent, this.handleStoreUpdate);
    this.syncFromStore();
  }

  public override disconnectedCallback(): void {
    super.disconnectedCallback();
    document.removeEventListener(OpenDocumentStoreChangedEvent, this.handleStoreUpdate);
  }

  protected override createRenderRoot(): HTMLElement | ShadowRoot {
    return this;
  }

  protected override render(): TemplateResult {
    /* eslint-disable @stylistic/indent */
    return html`
      <p class="dropdown-headline">${labels.get('toolbarItems.headline')}</p>
      ${this.recentDocuments.length > 0
        ? html`
            <ul class="dropdown-list" role="menu" aria-label="${labels.get('toolbarItems.headline')}">
              ${repeat(
                this.recentDocuments,
                (openDocument) => openDocument.identifier,
                (openDocument) => this.renderRecentDocumentItem(openDocument)
              )}
            </ul>
          `
        : html`<p class="dropdown-item-text">${labels.get('recentDocuments.emptyList')}</p>`
      }
    `;
    /* eslint-enable @stylistic/indent */
  }

  private readonly handleStoreUpdate = (): void => {
    this.syncFromStore();
  };

  private async syncFromStore(): Promise<void> {
    this.recentDocuments = await OpenDocumentStore.getRecentDocuments();
  }

  private renderRecentDocumentItem(openDocument: OpenDocument): TemplateResult {
    /* eslint-disable @stylistic/indent */
    return html`
      <li>
        <button
          type="button"
          role="menuitem"
          class="dropdown-item"
          @click=${() => this.handleNavigate(openDocument)}
        >
          <div class="activity">
            <div class="activity-icon">
              <typo3-backend-icon
                identifier="${openDocument.iconIdentifier}"
                overlay="${openDocument.iconOverlayIdentifier || ''}"
                size="small"
              ></typo3-backend-icon>
            </div>
            <div class="activity-title">
              ${openDocument.title}
            </div>
            <div class="activity-time">
              <typo3-backend-datetime datetime="${openDocument.updatedAt}" mode="relative"></typo3-backend-datetime>
            </div>
            <div class="activity-source">
              ${openDocument.breadcrumb.length > 0
                ? html`
                  <typo3-breadcrumb
                    .nodes="${openDocument.breadcrumb.slice(0, -1)}"
                    mode="path"
                    label="${labels.get('recentDocuments.path')}"
                    condensed
                  >
                  </typo3-breadcrumb>`
                : ''
              }
            </div>
          </div>
        </button>
      </li>
    `;
    /* eslint-enable @stylistic/indent */
  }

  private handleNavigate(openDocument: OpenDocument): void {
    OpenDocumentStore.navigate(openDocument);
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'typo3-open-document-menu': OpenDocumentMenuElement;
  }
}
