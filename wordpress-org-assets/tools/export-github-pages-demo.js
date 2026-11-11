// Export a real WordPress-rendered Tabloria Display into the GitHub Pages demo.
// Usage: node export-github-pages-demo.js wordpress-page.html site-demo.html output.html
const fs = require('node:fs');

const [wordpressPath, templatePath, outputPath] = process.argv.slice(2);
if (!wordpressPath || !templatePath || !outputPath) {
	throw new Error('Pass the WordPress page HTML, current site demo HTML, and output path.');
}

const wordpressPage = fs.readFileSync(wordpressPath, 'utf8');
let sitePage = fs.readFileSync(templatePath, 'utf8');
const marker = 'class="tabloria-display ';
const markerAt = wordpressPage.indexOf(marker);
if (markerAt < 0) {
	throw new Error('The WordPress page does not contain a Tabloria Display.');
}
const displayStart = wordpressPage.lastIndexOf('<section', markerAt);
const displayEnd = wordpressPage.indexOf('</section>', markerAt) + '</section>'.length;
if (displayStart < 0 || displayEnd < displayStart) {
	throw new Error('Could not isolate the WordPress-rendered Display.');
}

let display = wordpressPage.slice(displayStart, displayEnd);
display = display.replaceAll('http://127.0.0.1:8090/demo-products/', '/demo-products/');
display = display.replaceAll('http://localhost:8090/demo-products/', '/demo-products/');
display = display.replaceAll('/demo-products/canvas-tote.png', '/demo-products/canvas-tote.webp');
display = display.replaceAll('/demo-products/ceramic-mug.png', '/demo-products/ceramic-mug.webp');
display = display.replaceAll('/demo-products/oak-shelf.png', '/demo-products/oak-shelf.webp');
display = display.replaceAll('/demo-products/desk-lamp.png', '/demo-products/desk-lamp.webp');
display = display.replaceAll('decoding="async"', 'decoding="sync"');
display = display.replaceAll('loading="lazy"', 'loading="eager"');
if (display.includes(':8090/')) {
	throw new Error('A local demo asset URL remains in the exported Display.');
}

const shellStart = sitePage.indexOf('    <section class="catalog-shell" id="catalog">');
const shellEnd = sitePage.indexOf('\n  </main>', shellStart);
if (shellStart < 0 || shellEnd < shellStart) {
	throw new Error('Could not find the existing demo section.');
}
const shell = `    <section class="catalog-shell" id="catalog">
      <div class="container">
        <div class="catalog-panel">
          ${display}
        </div>
        <div class="csv-note">
          <div>This catalog was rendered by Tabloria in WordPress. GitHub Pages serves the exported HTML with Tabloria’s own search, filters, and styles.</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
            <a class="btn btn-secondary" href="/demo-products/catalog.csv" download>Download sample CSV</a>
            <a class="btn btn-primary" href="https://wordpress.org/plugins/tabloria-searchable-displays/" target="_blank" rel="noopener">Install Tabloria</a>
          </div>
        </div>
      </div>
    </section>`;
sitePage = sitePage.slice(0, shellStart) + shell + sitePage.slice(shellEnd);

const scriptStart = sitePage.lastIndexOf('  <script>');
const scriptEnd = sitePage.indexOf('</script>', scriptStart) + '</script>'.length;
if (scriptStart < 0 || scriptEnd < scriptStart) {
	throw new Error('Could not find the old simulated demo script.');
}
sitePage = sitePage.slice(0, scriptStart) + '  <script src="/demo-assets/frontend.js" defer></script>' + sitePage.slice(scriptEnd);
sitePage = sitePage.replace('  <style>', '  <link rel="stylesheet" href="/demo-assets/frontend.css" />\n  <style>');
sitePage = sitePage.replace('Product Catalog Demo — Tabloria CSV Display Plugin', 'CSV Product Catalog Demo — Rendered by Tabloria in WordPress');
sitePage = sitePage.replaceAll('Tabloria — CSV to WordPress Display Plugin', 'Tabloria — Searchable CSV Product Catalog for WordPress');
sitePage = sitePage.replaceAll('Turn CSV data into searchable WordPress displays, product catalogs, directories, schedules, pricing tables, and simple data tables.', 'See a product catalog rendered in WordPress from CSV, with searchable cards and category and status filters.');
sitePage = sitePage.replace('This is what a Tabloria display can look like on a WordPress page: searchable product cards generated from CSV rows.', 'Explore a searchable product catalog rendered by Tabloria from a CSV inside WordPress.');
sitePage = sitePage.replace('Built from products.csv with Tabloria', 'Rendered from catalog.csv in WordPress');
sitePage = sitePage.replace('Display type: Product Catalog · Fields: name, category, price, status, description', 'Product Catalog · Search, category and status filters · Gutenberg block');
sitePage = sitePage.replace('This demo represents a product catalog created from a simple CSV export. In the plugin, the same content would be configured through Tabloria’s CSV upload, field mapping, preview, and Gutenberg insertion flow.', 'This catalog was rendered by Tabloria in WordPress.');

if (sitePage.includes('const products = [') || sitePage.includes('id="productGrid"')) {
	throw new Error('The simulated catalog was not removed.');
}
sitePage = sitePage.replace(/[\t ]+$/gm, '');
fs.writeFileSync(outputPath, sitePage);
console.log(`Exported ${display.match(/data-tabloria-item/g)?.length || 0} WordPress-rendered cards to ${outputPath}`);
