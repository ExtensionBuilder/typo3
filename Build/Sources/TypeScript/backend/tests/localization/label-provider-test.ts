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

import { LabelProvider } from '@typo3/backend/localization/label-provider.js';
import { expect } from '@open-wc/testing';
import type { } from 'mocha';

const labels = {
  interpolation: 'Hi {name}',
  plural: '{n, plural, one {# book} other {# books}}',
  formatNumber: '{n, number, percent} success',
  select: '{foo, select, bar {one} baz {two} other {three}}',
  date: 'last change: {last_change, date, short}',
  time: 'time: {time, time, short}',
  sprintf: 'Hi %s (%d)',
};

describe('@typo3/backend/localization/label-provider-test:', () => {
  describe('tests for get()', () => {
    it('interpolates', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('interpolation', { name: 'Foo' })).to.equal('Hi Foo');
    });

    it('pluralizes', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('plural', { n: 0 })).to.equal('0 books');
      expect(provider.get('plural', { n: 1 })).to.equal('1 book');
      expect(provider.get('plural', { n: 3 })).to.equal('3 books');
    });

    it('formats numbers', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('formatNumber', { n: 0 })).to.equal('0% success');
      expect(provider.get('formatNumber', { n: .01 })).to.equal('1% success');
      expect(provider.get('formatNumber', { n: .03 })).to.equal('3% success');
      expect(provider.get('formatNumber', { n: 1 })).to.equal('100% success');
    });

    it('selects conditional', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('select', { foo: 'bar' })).to.equal('one');
      expect(provider.get('select', { foo: 'baz' })).to.equal('two');
      expect(provider.get('select', { foo: '' })).to.equal('three');
    });

    it('formats dates', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('date', { last_change: new Date('2026-02-21') })).to.equal('last change: 2/21/26');
    });

    it('formats times', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('time', { time: new Date('2026-07-21T05:00:00') })).to.equal('time: 5:00 AM');
    });

    it('interpolates sprintf syntax', () => {
      const provider = new LabelProvider(labels);
      expect(provider.get('sprintf', [ 'Foo', 10 ])).to.equal('Hi Foo (10)');
    });
  });
});
