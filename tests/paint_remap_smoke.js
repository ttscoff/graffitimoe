#!/usr/bin/env node
'use strict';

var fs = require('fs');
var path = require('path');
var vm = require('vm');

var root = path.join(__dirname, '..');
var code = fs.readFileSync(path.join(root, 'public/assets/paint-remap.js'), 'utf8');
var sandbox = { globalThis: {} };
sandbox.globalThis = sandbox;
vm.runInNewContext(code, sandbox);

var remap = sandbox.GraffitiPaintRemap.remapStyles;

function assert(cond, msg) {
  if (!cond) {
    console.error('FAIL:', msg);
    process.exit(1);
  }
}

// Unchanged text keeps styles
var r1 = remap(
  'abc',
  ['red', 'cyan', 'yellow'],
  [false, true, false],
  'abc',
  'default',
  false
);
assert(r1.colors.join(',') === 'red,cyan,yellow', 'unchanged colors');
assert(r1.bolds[1] === true, 'unchanged bold');

// Insert in middle
var r2 = remap(
  'ab',
  ['red', 'cyan'],
  [false, false],
  'aXb',
  'magenta',
  true
);
assert(r2.colors.join(',') === 'red,magenta,cyan', 'insert color');
assert(r2.bolds[0] === false && r2.bolds[1] === true && r2.bolds[2] === false, 'insert bold');

// Delete
var r3 = remap(
  'abcd',
  ['red', 'cyan', 'yellow', 'green'],
  [false, false, false, false],
  'ad',
  'default',
  false
);
assert(r3.colors.join(',') === 'red,green', 'delete middle');

// Prefix append
var r4 = remap(
  'hi',
  ['red', 'red'],
  [true, true],
  'hi!',
  'blue',
  false
);
assert(r4.colors.join(',') === 'red,red,blue', 'append');

// Dissimilar rewrite: bail out of speckled LCS, use all-insert styles
var oldDissimilar = 'hello world';
var r5 = remap(
  oldDissimilar,
  oldDissimilar.split('').map(function () {
    return 'red';
  }),
  oldDissimilar.split('').map(function () {
    return false;
  }),
  'goodbye friend',
  'magenta',
  false
);
assert(r5.colors.length === 'goodbye friend'.length, 'dissimilar length');
assert(
  r5.colors.every(function (c) {
    return c === 'magenta';
  }),
  'dissimilar all insert color'
);
assert(
  r5.colors.filter(function (c) {
    return c === 'red';
  }).length === 0,
  'dissimilar no speckle'
);

console.log('paint remap smoke test passed.');
