# GitHub Copilot Instructions — Moodle 4.5 (mod_datalynx / MOODLE_405_DEVDAVID)

## Project snapshot
- **Moodle 4.5**, branch `MOODLE_405_DEVDAVID`, PHP 8.1–8.3
- Primary plugin under active development: `mod/datalynx` (frankenstyle `mod_datalynx`)
- Third-party & Wunderbyte plugins live as **git submodules** (e.g. `mod/booking`, `local/shopping_cart`)
- Node.js `>=22.11.0 <23`; JavaScript compiled via Grunt (Rollup + Babel)

## PHP coding standards (enforced by PHPCS `--standard=Moodle`)

Every PHP file **must** open with:
```php
<?php
// This file is part of mod_datalynx for Moodle - http://moodle.org/
// … GPL boilerplate …

/**
 * Short one-line description.
 *
 * @package   mod_datalynx
 * @copyright 2024 Your Name <you@example.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```
- License block (`// This file is part of…`) **must** appear immediately after `<?php`, before the docblock.
- Every file-level docblock requires `@package`, `@copyright`, and `@license`.
- Every function/method requires a full docblock with `@param` (type + name + description) and `@return`.
- 4-space indent, no tabs, max line length 132 chars.
- Use `[]` array syntax, not `array()`.
- Single-quoted strings preferred unless interpolation needed.
- `require_once` not `include_once` for Moodle core includes.

## Namespacing (PSR-4)
- Namespace root for `mod/datalynx`: `mod_datalynx`
- Classes in `mod/datalynx/classes/local/foo.php` → namespace `mod_datalynx` (the `local/` directory is **not** part of the namespace).
- Classes in `mod/datalynx/classes/external/` → namespace `mod_datalynx\external`

## Database — never use raw SQL
```php
// Good
$records = $DB->get_records('datalynx_contents', ['fieldid' => $fieldid]);
$DB->insert_record('datalynx_entries', $data);

// Bad — never do this
$DB->execute("SELECT * FROM {datalynx_contents} WHERE fieldid = $fieldid");
```
Schema lives in `db/install.xml` (use the XMLDB editor — never edit XML by hand).  
Upgrade steps go in `db/upgrade.php` using `xmldb_*_upgrade()` with `local_savepoint()`.

## JavaScript (AMD / ES Modules)
- Source: `amd/src/*.js` → compiled to `amd/build/*.min.js` by `npx grunt`
- Prefer ES Module `export`/`import` syntax; avoid legacy `define()` wrappers
- Never edit `amd/build/` files directly
- After JS changes: `npx grunt` (or `npx grunt --root=mod/datalynx`)

## Templates
- Mustache only, in `templates/` within each component
- PHP: `$OUTPUT->render_from_template('mod_datalynx/template', $data)`
- JS: `import Templates from 'core/templates'; Templates.render('mod_datalynx/template', data)`
- Bootstrap 4 in use; write `data-bs-*` attributes alongside `data-*` for forward compatibility

## Events & Hooks
- Events: subclass `\core\event\base`, register observers in `db/events.php`
- Hooks (Moodle 4.5+): declare in `db/hooks.php` — **prefer hooks over legacy callbacks** in `lib.php`

## External API (web services)
- Implement in `classes/external/` extending `\core_external\external_api`
- Declare in `db/services.php`
- Define `execute_parameters()`, `execute()`, `execute_returns()`

## Testing
- **PHPUnit**: extend `advanced_testcase`; use `$this->getDataGenerator()->create_module('datalynx')`
- Test files: `tests/*_test.php`; suite declared in `phpunit.xml.dist`
- Init once: `php admin/tool/phpunit/cli/init.php`
- Run plugin suite: `vendor/bin/phpunit --testsuite mod_datalynx_testsuite`
- **Behat**: features in `tests/behat/*.feature`; use standard Moodle selectors
- Init: `php admin/tool/behat/cli/init.php`
- Run: `vendor/bin/behat --config /var/moodledata-b/behatrun/behat/behat.yml`

## Language strings
- Always provide **both** `lang/en/mod_datalynx.php` and `lang/de/mod_datalynx.php`
- Retrieve: `get_string('key', 'mod_datalynx')`

## version.php minimum
```php
$plugin->component = 'mod_datalynx';
$plugin->version   = 2024110100;
$plugin->requires  = 2024100400; // Moodle 4.5
$plugin->maturity  = MATURITY_STABLE;
```

## Key linting commands
```bash
# Check a single file
~/.config/composer/vendor/bin/phpcs --standard=Moodle mod/datalynx/some_file.php

# Auto-fix
~/.config/composer/vendor/bin/phpcbf --standard=Moodle mod/datalynx/some_file.php

# JS lint
npx grunt eslint

# Build JS/CSS
npx grunt            # all
npx grunt --root=mod/datalynx  # plugin only
```

