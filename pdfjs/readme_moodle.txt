Description of PDF.js import into mod_datalynx
===========================================

Library information
-------------------

Name: PDF.js
Version: 5.6.205
Upstream URL: https://github.com/mozilla/pdf.js
Download URL: https://github.com/mozilla/pdf.js/releases/download/v5.6.205/pdfjs-5.6.205-dist.zip
License: Apache License 2.0

Files kept in this folder
-------------------------

The vendored runtime kept in this folder comes from the upstream prebuilt modern
distribution:

- build/pdf.mjs
- build/pdf.mjs.map
- build/pdf.worker.mjs
- build/pdf.worker.mjs.map
- build/pdf.sandbox.mjs
- build/pdf.sandbox.mjs.map
- LICENSE

The Moodle-local maintenance file `readme_moodle.txt` is intentionally added to
this folder. No changes are made to the vendored runtime files themselves.

Upgrade steps
-------------

1. Download the latest stable PDF.js prebuilt modern distribution ZIP from the
   upstream release page.
2. Unpack the ZIP in a temporary folder outside Moodle.
3. Copy these files from the unpacked `build/` directory into this folder:
   - pdf.mjs
   - pdf.mjs.map
   - pdf.worker.mjs
   - pdf.worker.mjs.map
   - pdf.sandbox.mjs
   - pdf.sandbox.mjs.map
4. Copy the upstream `LICENSE` file into this folder.
5. Remove obsolete files from the previous bundled version.
6. Update `mod/datalynx/thirdpartylibs.xml` with the new version and any
   changed metadata.
7. Update `mod/datalynx/CHANGES` with a note about the third-party library
   update.
8. Run `npx grunt --root=mod/datalynx`.
9. Verify that PDF rendering still works in Datalynx and that the worker file
   loads correctly.

Integration note
----------------

`mod/datalynx/amd/src/pdfembed.js` is first-party Datalynx integration code.
The vendored PDF.js library itself remains in `mod/datalynx/pdfjs/` and is
loaded by URL at runtime.
