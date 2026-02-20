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

type SprintfParameters = Array<string|number>;

export class LabelProvider<LabelParameterMap extends Record<string, SprintfParameters|undefined>> {
  constructor(
    private readonly labels: Record<keyof LabelParameterMap, string>
  ) {}

  public get<K extends keyof LabelParameterMap>(
    key: K,
    // Workaround to ensure that TypeScript enforces the exact number of parameters
    // Note: `args?` allows to omit parameters, when they are actually required.
    ...args: (LabelParameterMap[K] extends undefined ? [] : [Readonly<LabelParameterMap[K]>])
  ): string;

  public get<K extends keyof LabelParameterMap>(
    key: K,
    args?: Readonly<LabelParameterMap[K]>,
  ): string {
    if (!(key in this.labels)) {
      throw new Error('Label is not defined: ' + String(key));
    }

    const label = this.labels[key];

    if (args === undefined) {
      return label;
    }

    return this.sprintf(label, args);
  }

  private sprintf(
    label: string,
    args: Readonly<SprintfParameters>
  ): string {
    // code taken from lit-helper
    let index = 0;
    return label.replace(/%[sdf]/g, (match) => {
      const arg = args[index++];
      switch (match) {
        case '%s':
          return String(arg);
        case '%d':
          return String(typeof arg === 'number' ? arg : parseInt(String(arg), 10));
        case '%f':
          return String(typeof arg === 'number' ? arg : parseFloat(arg).toFixed(2));
        default:
          return match;
      }
    });
  }
}
