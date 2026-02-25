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
 * Dashboard widget displaying recently opened documents with breadcrumb navigation.
 */
@customElement('typo3-opendocs-recent-documents-widget')
export class RecentDocumentsWidgetElement extends LitElement {
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
    if (this.recentDocuments.length === 0) {
      return html`
        <div class="callout callout-info">
          <div class="callout-icon">
            <span class="icon-emphasized">
              <typo3-backend-icon identifier="content-widget-list" size="default"></typo3-backend-icon>
            </span>
          </div>
          <div class="callout-content">
            <div class="callout-title">
              ${labels.get('recentDocuments.emptyList')}
            </div>
          </div>
        </div>
      `;
    }

    return html`
      <div class="widget-listgroup list-group">
        ${repeat(this.recentDocuments, (openDocument) => openDocument.identifier, (openDocument) => this.renderDocumentRow(openDocument))}
      </div>
    `;
  }

  private renderDocumentRow(openDocument: OpenDocument): TemplateResult {
    /* eslint-disable @stylistic/indent */
    return html`
        <button
          type="button"
          role="menuitem"
          @click=${(e: Event) => this.handleDocumentClick(e, openDocument)}
          class="list-group-item list-group-item-action"
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
    `;
  }

  private readonly handleStoreUpdate = (): void => {
    this.syncFromStore();
  };

  private async syncFromStore(): Promise<void> {
    this.recentDocuments = await OpenDocumentStore.getRecentDocuments();
  }

  private handleDocumentClick(e: Event, openDocument: OpenDocument): void {
    e.preventDefault();
    OpenDocumentStore.navigate(openDocument);
  }
}

declare global {
  interface HTMLElementTagNameMap {
    'typo3-opendocs-recent-documents-widget': RecentDocumentsWidgetElement;
  }
}
