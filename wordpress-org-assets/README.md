# Tabloria WordPress.org assets

This directory contains the source material and generated image assets for the Tabloria WordPress.org listing.

- `assets/` contains the files to upload to the WordPress.org SVN `assets` directory.
- `source/` contains the deterministic banner composition, demo CSV, and original product imagery.
- `tools/capture-assets.js` creates the demo content in the local WordPress testbed and captures the banner and screenshots.

The capture script expects the local Docker WordPress site on `http://127.0.0.1:8081`, the temporary image server on port `8090`, Google Chrome, and `playwright-core` installed under `/tmp/tabloria-shot`.

```bash
npm install --prefix /tmp/tabloria-shot playwright-core
python3 -m http.server 8090 --directory wordpress-org-assets/source
TABLORIA_CAPTURE_USER=your-local-admin \
TABLORIA_CAPTURE_PASSWORD=your-local-password \
NODE_PATH=/tmp/tabloria-shot/node_modules \
node wordpress-org-assets/tools/capture-assets.js
```

The generated product images are original AI-generated demo assets. They contain no logos, text, people, or third-party product designs.
