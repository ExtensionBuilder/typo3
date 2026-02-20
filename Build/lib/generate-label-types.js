import { basename } from 'path';
import { readFile, writeFile, access, constants } from 'fs/promises';
import { parse, TYPE } from '@formatjs/icu-messageformat-parser';
import sax from 'sax';

function parseLabelTypes(content, input) {
  const parser = sax.parser(true);
  const labels = [];
  let id = null;
  let isSource = false;
  let isDeprecated = false;
  parser.onclosetag = () => {
    isSource = false;
  };
  parser.onopentag = (node) => {
    isSource = node.name === 'source';
    // xliff 2.x
    if (node.name === 'unit') {
      id = node.attributes.id;
    }
    if (node.name === 'segment') {
      isDeprecated = (node.attributes.subState ?? '') === 'deprecated';
    }
    // xliff 1.x
    if (node.name === 'trans-unit') {
      id = node.attributes.id;
      isDeprecated = 'x-unused-since' in node.attributes;
    }
  };
  parser.oncdata = text => {
    if (isSource && !isDeprecated) {
      labels[id] = (labels[id] ?? '') + text;
    }
  };
  parser.ontext = text => {
    if (isSource && !isDeprecated) {
      labels[id] = text;
    }
  };
  parser.write(content).close();

  return Object.fromEntries(Object.entries(labels).map(([key, label]) => [key, resolveTypes(label, input)]));
}

function resolveTypes(label, input) {
  if (label.includes('%s') || label.includes('%d') || label.includes('%f')) {
    const mapping = {
      s: 'string',
      d: 'number',
      f: 'number'
    };
    return [...label.matchAll(/%([sdf])/g)].map(match => mapping[match[1]]);
  }

  try {
    return resolveMessageParameterTypesFromAst(parse(label));
  } catch (e) {
    //console.log('Failed to parse', input, e);
    return null;
  }
}

function resolveMessageParameterTypesFromAst(messageFormatElements) {
  let parameters = {};

  for (const formatElement of messageFormatElements) {
    switch (formatElement.type) {
    case TYPE.literal:
      break;
    case TYPE.argument:
      parameters[formatElement.value] = 'string';
      break;
    case TYPE.number:
      parameters[formatElement.value] = 'number';
      break;
    case TYPE.date:
    case TYPE.time:
      parameters[formatElement.value] = 'Date';
      break;

    case TYPE.plural:
    case TYPE.select:
      parameters[formatElement.value] = formatElement.type === TYPE.plural ? 'number' : 'string';
      Object.assign(
        parameters,
        ...Object.entries(formatElement.options).map(([key, elements]) => resolveMessageParameterTypesFromAst(elements.value))
      );
      break;

    case TYPE.pound:
      // This is "#" inside plural => ignore
      break;

    case TYPE.tag:
      parameters[formatElement.value] = '(chunks: unknown[]) => unknown';
      parameters = {
        ...parameters,
        ...resolveMessageParameterTypesFromAst(formatElement.children),
      };
      break;
    }
  }

  return parameters;
}

export async function generate(input) {
  const { domain, resource } = await parsePath(input);
  if (resource === null) {
    // Skip if the label file is overlayed by another
    return;
  }
  const targetFile = `types/labels/${domain}.${resource}.d.ts`;
  const content = await readFile(input, 'utf8');
  const labels = parseLabelTypes(content, input);
  if (Object.entries(labels).length === 0) {
    // Skip if the labels are empty (e.g. deprecated label file)
    return;
  }
  const declaration = createTypeScriptDeclaration(labels);
  await writeFile(targetFile, declaration, { flag: 'w' });
}

async function parsePath(path) {
  const pathParts = path.replace('../typo3/sysext/', '').split('/');
  const domain = pathParts.shift();
  const filename = basename(pathParts.pop(), '.xlf');
  const infix = pathParts.join('/');

  if (infix.startsWith('Configuration/Sets')) {
    pathParts.shift();
    pathParts.shift();
    const setName = pathParts.shift();
    return {
      domain: domain,
      resource: 'sets.' + convertCamelAndHyphensToSnake(setName),
    };
  }

  if (infix.startsWith('Resources/Private/Language')) {
    pathParts.shift();
    pathParts.shift();
    pathParts.shift();
  }
  const namespace = pathParts.map(convertCamelAndHyphensToSnake).map(path => path + '.').join('');
  const name = filename === 'locallang'
    ? 'messages'
    : (
      filename.startsWith('locallang_')
      ? filename.replace(/^locallang_/, '')
      : filename
    );

  if (filename.startsWith('locallang_')) {
    // Test if another file takes priority
    try {
      if (await access(path.replace('/locallang_', '/'), constants.R_OK)) {
        return { domain, resource: null };
      }
    } catch (e) {
      if (!('code' in e && e.code === 'ENOENT')) {
        throw e;
      }
    }
  }

  return {
    domain,
    resource: namespace + convertCamelAndHyphensToSnake(name),
  };
}

function convertCamelAndHyphensToSnake(str) {
  return str.replace(/([a-zA-Z])(?=[A-Z])/g,'$1_').replace(/-/g, '_').toLowerCase();
}

function renderParameterTypes(parameters) {
  if (Array.isArray(parameters)) {
    return '[ ' + parameters.join(', ') + ' ]';
  }
  if (parameters === null || Object.entries(parameters).length === 0) {
    return 'undefined';
  }
  return '{ ' + Object.entries(parameters).map(([key, value]) => `'${key}': ${value}`).join(', ') + ' }';
}

function createTypeScriptDeclaration(labels) {
  return `import { LabelProvider } from '@typo3/backend/localization/label-provider';
type Labels = {
${Object.entries(labels).map(([label, parameters]) => `  '${label}': ${renderParameterTypes(parameters)}`).join(",\n")}
};
declare const provider: LabelProvider<Labels>;
export default provider;
`;
}
