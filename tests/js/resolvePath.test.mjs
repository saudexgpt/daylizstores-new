// Run with: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { resolvePath } from '../../resources/js/utils/resolvePath.js';

test('matches the route-path cases the sidebar, tags and header search produce', () => {
  const expected = [
    ['/', 'dashboard', '/dashboard'],
    ['/', '/dashboard', '/dashboard'],
    ['/permission', 'index', '/permission/index'],
    ['/permission', '', '/permission'], // a parent with a single child: resolvePath(onlyOneChild.path = '')
    ['/permission/', 'role', '/permission/role'],
    ['/a/b', '../c', '/a/c'],
    ['/a/b', './c', '/a/b/c'],
    ['/a', '/x/y', '/x/y'], // an absolute child path ignores the base
    ['', 'orders', '/orders'], // no base path (the component default)
    ['/', '', '/'],
    ['/a//b', 'c/', '/a/b/c'],
  ];
  for (const [base, route, want] of expected) {
    assert.equal(resolvePath(base, route), want, `resolvePath(${JSON.stringify(base)}, ${JSON.stringify(route)})`);
  }
});

test('agrees with Node path.resolve (posix) for absolute bases', () => {
  const bases = ['/', '/a', '/a/b', '/dashboard/orders'];
  const routes = ['x', 'x/y', '', '.', '..', '../x', '/abs', 'a/../b'];
  for (const base of bases) {
    for (const route of routes) {
      assert.equal(resolvePath(base, route), path.posix.resolve(base, route), `base=${base} route=${route}`);
    }
  }
});

test('tolerates missing values instead of throwing', () => {
  assert.equal(resolvePath(undefined, 'x'), '/x');
  assert.equal(resolvePath('/a', undefined), '/a');
  assert.equal(resolvePath(null, null), '/');
});
