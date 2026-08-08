Description of Leaflet import into mod_datalynx
==============================================

Library information
-------------------

Name: Leaflet
Version: 1.9.4
Upstream URL: https://leafletjs.com/
Download URL: https://registry.npmjs.org/leaflet/-/leaflet-1.9.4.tgz
License: BSD 2-Clause "Simplified" License

Files kept in this folder
-------------------------

Taken unmodified from the `dist/` directory of the upstream npm package:

- leaflet.js
- leaflet.js.map
- leaflet.css
- images/layers.png
- images/layers-2x.png
- images/marker-icon.png
- images/marker-icon-2x.png
- images/marker-shadow.png

Plus the upstream `LICENSE`. The Moodle-local maintenance file `readme_moodle.txt`
is intentionally added to this folder. No changes are made to the vendored
library files themselves.

Why the library is bundled rather than loaded from a CDN
--------------------------------------------------------

Loading Leaflet from a public CDN transfers every user's IP address to a
third-party host outside the EU, which is not acceptable for the installations
this plugin targets. Bundling also means maps keep working on installations
without outbound internet access to that CDN.

Why version 1.9.x and not 2.x
-----------------------------

Leaflet 2.0 is still an alpha release and drops the UMD build in favour of
ESM-only distribution. When 2.x becomes stable the integration should be
revisited: an ESM build can be imported directly by `amd/src/location.js` and
bundled by Rollup, which would remove the RequireJS `paths` indirection
described below.

Upgrade steps
-------------

1. Download the npm tarball for the new version from
   https://registry.npmjs.org/leaflet/-/leaflet-<version>.tgz
2. Unpack it in a temporary folder outside Moodle.
3. Copy the files listed above from `package/dist/` into this folder, and
   `package/LICENSE` into this folder.
4. Remove obsolete files from the previous bundled version.
5. Update the Leaflet `<library>` entry in `mod/datalynx/thirdpartylibs.xml`
   with the new version and any changed metadata.
6. Update `mod/datalynx/CHANGES` with a note about the third-party library
   update.
7. Run `npx grunt ignorefiles` from the Moodle root so the generated
   `.eslintignore`, `.stylelintignore` and `phpcs.xml` stay in step.
8. Verify that maps still render in a location field in display mode, edit mode
   and in the filter form.

Integration note
----------------

`mod/datalynx/amd/src/location.js` is first-party Datalynx integration code. It
loads the vendored library through RequireJS:

    requirejs.config({paths: {mod_datalynx_leaflet: '<wwwroot>/mod/datalynx/leaflet/leaflet'}});
    requirejs(['mod_datalynx_leaflet'], ...);

Loading it with a plain `<script>` tag does not work. Leaflet 1.x is a UMD
bundle, and because RequireJS is always present on a Moodle page, Leaflet's
wrapper takes the AMD branch and calls `define()` anonymously. RequireJS then
rejects that orphaned `define()` with "Mismatched anonymous define() module" and
`window.L` is never assigned. Letting RequireJS fetch the file is what pairs the
anonymous `define()` with a module id — the same approach Moodle core uses for
jQuery and jQuery UI.

The marker icon URLs are also set explicitly in `location.js`, because Leaflet
derives them from its own `<script>` tag, which does not exist on this path.
