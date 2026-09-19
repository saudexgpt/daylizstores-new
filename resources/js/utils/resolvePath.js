/**
 * Browser-safe stand-in for Node's `path.resolve(basePath, routePath)`, used to
 * build absolute route paths (sidebar links, tags, header search).
 *
 * Node's `path` module does not exist in the browser — Vite stubs it, so
 * `import path from 'path'` builds fine and then throws the first time
 * `path.resolve` is called. There is no filesystem here, so "resolve" is just
 * joining onto the base, collapsing `.`/`..`/empty segments and always
 * returning an absolute path with no trailing slash.
 *
 * @param {string} basePath
 * @param {string} routePath
 * @returns {string}
 */
export function resolvePath(basePath, routePath) {
  const target = String(routePath || '');
  const joined = target.startsWith('/') ? target : `${basePath || ''}/${target}`;
  const segments = [];
  for (const segment of joined.split('/')) {
    if (segment === '' || segment === '.') {
      continue;
    }
    if (segment === '..') {
      segments.pop();
    } else {
      segments.push(segment);
    }
  }
  return '/' + segments.join('/');
}

export default resolvePath;
