# js-beautify 2.0.3

Upstream: https://github.com/beautifier/js-beautify

Source: https://registry.npmjs.org/js-beautify/-/js-beautify-2.0.3.tgz

`beautify-css.js` is the unchanged browser bundle from `js/lib/` in the
upstream package. The MIT license is included in `LICENSE`.

Used locally by the Alternative Assets editor. The JavaScript formatter
is included only in Pro. No formatter is loaded on the public site.

Generate minified output with the project's Terser executable:

```sh
terser beautify-css.js --compress --mangle --comments '/Copyright|Permission is hereby/' --output beautify-css.min.js
```

Only pass unminified `.js` sources as input; never pass `.min.js` files.
