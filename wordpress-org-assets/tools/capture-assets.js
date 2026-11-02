const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');

const projectRoot = path.resolve(__dirname, '..', '..');
const sourceDir = path.join(projectRoot, 'wordpress-org-assets', 'source');
const outputDir = path.join(projectRoot, 'wordpress-org-assets', 'assets');
const previewDir = path.join(projectRoot, 'wordpress-org-assets', 'previews');
const baseUrl = process.env.TABLORIA_CAPTURE_URL || 'http://127.0.0.1:8081';
const username = process.env.TABLORIA_CAPTURE_USER;
const password = process.env.TABLORIA_CAPTURE_PASSWORD;

if (!username || !password) {
	throw new Error('Set TABLORIA_CAPTURE_USER and TABLORIA_CAPTURE_PASSWORD.');
}

fs.mkdirSync(outputDir, { recursive: true });
fs.mkdirSync(previewDir, { recursive: true });

async function login(page) {
	await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	await page.locator('#user_login').fill(username);
	await page.locator('#user_pass').fill(password);
	await Promise.all([
		page.waitForURL(/wp-admin/),
		page.locator('#wp-submit').click(),
	]);
}

async function api(page, route, options = {}) {
	return page.evaluate(async ({ route, options }) => {
		const response = await fetch(route, {
			...options,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': window.tabloriaAdmin.nonce,
				...(options.headers || {}),
			},
		});
		const body = await response.json();
		if (!response.ok) {
			throw new Error(body.message || `Request failed: ${response.status}`);
		}
		return body;
	}, { route, options });
}

async function ensureDemo(page) {
	await page.goto(`${baseUrl}/wp-admin/admin.php?page=tabloria-displays`, { waitUntil: 'networkidle' });
	await page.waitForFunction(() => window.tabloriaAdmin && window.tabloriaAdmin.nonce);

	let displays = await api(page, `${baseUrl}/index.php?rest_route=/tabloria/v1/displays`);
	let display = displays.find((item) => item.name === 'Studio Essentials');

	if (!display) {
		const csv = fs.readFileSync(path.join(sourceDir, 'catalog.csv'), 'utf8');
		const upload = await page.evaluate(async ({ csv }) => {
			const form = new FormData();
			form.append('file', new File([csv], 'studio-essentials.csv', { type: 'text/csv' }));
			const response = await fetch(`${window.tabloriaAdmin.restUrl}/upload`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': window.tabloriaAdmin.nonce },
				body: form,
			});
			const body = await response.json();
			if (!response.ok) {
				throw new Error(body.message || `Upload failed: ${response.status}`);
			}
			return body;
		}, { csv });

		const config = {
			displayType: 'product_catalog',
			title: 'Studio Essentials',
			sourceId: upload.source.id,
			fieldRoles: {
				title: 'product_name',
				subtitle: 'category',
				image: 'image',
				price: 'price',
				badge: 'status',
			},
			visibleFields: ['description', 'brand', 'product_url'],
			defaultFilters: [],
			visitorFilters: ['category', 'status'],
			sort: { field: 'product_name', direction: 'asc' },
			search: { enabled: true, hideRowsUntilSearch: false, placeholder: 'Search products…' },
			filters: { enabled: true, showColumnNames: false, hoverColor: '#3525cd' },
			pagination: { enabled: true, perPage: 12, showResultCount: true, resultLabel: 'products' },
			style: { accentColor: '#3525cd', density: 'comfortable', imageShape: 'rounded', displayWidth: 'normal', cardMaxWidth: 320 },
			layoutOptions: { cards: { urlDisplay: 'button', urlLabel: 'View product', buttonStyle: 'primary' } },
		};

		display = await api(page, `${baseUrl}/index.php?rest_route=/tabloria/v1/displays`, {
			method: 'POST',
			body: JSON.stringify({
				sourceId: upload.source.id,
				name: 'Studio Essentials',
				displayType: 'product_catalog',
				config,
				status: 'published',
			}),
		});
	}

	const pages = await api(page, `${baseUrl}/index.php?rest_route=/wp/v2/pages&slug=tabloria-studio-essentials&context=edit`);
	const pagePayload = {
		title: 'Studio Essentials',
		slug: 'tabloria-studio-essentials',
		status: 'publish',
		content: `<!-- wp:tabloria/display {"displayId":${display.id}} /-->`,
	};
	let demoPage;
	if (pages.length) {
		demoPage = await api(page, `${baseUrl}/index.php?rest_route=/wp/v2/pages/${pages[0].id}`, {
			method: 'POST',
			body: JSON.stringify(pagePayload),
		});
	} else {
		demoPage = await api(page, `${baseUrl}/index.php?rest_route=/wp/v2/pages`, {
			method: 'POST',
			body: JSON.stringify(pagePayload),
		});
	}

	return { display, demoPage };
}

async function captureBanner(browser, width, height, filename) {
	const page = await browser.newPage({ viewport: { width, height }, deviceScaleFactor: 1 });
	await page.goto(`file://${path.join(sourceDir, 'banner.html')}`, { waitUntil: 'networkidle' });
	await page.screenshot({ path: path.join(outputDir, filename) });
	await page.close();
}

async function prepareFrontend(page) {
	await page.addStyleTag({ content: `
		body { background: #f6f5fb !important; }
		.wp-site-blocks > header,
		.wp-site-blocks > footer,
		.wp-block-template-part { display: none !important; }
		.wp-site-blocks { padding: 0 !important; }
		main, .wp-block-post-content { margin: 0 !important; padding: 0 !important; }
		.tabloria-display {
			max-width: 1460px !important;
			margin: 36px auto !important;
			padding: 36px 44px 30px !important;
			background: white;
			border: 1px solid #e8e5f4;
			border-radius: 24px;
			box-shadow: 0 22px 65px rgba(36, 31, 82, .11);
		}
		.tabloria-display__header h2 { font-size: 38px !important; letter-spacing: -.035em; }
		.tabloria-card { box-shadow: 0 12px 30px rgba(39, 34, 78, .10) !important; }
	` });
	await page.locator('.tabloria-card__image img').first().waitFor({ state: 'visible' });
	await page.waitForFunction(() => Array.from(document.images).every((image) => image.complete));
}

async function captureScreenshots(browser, adminPage, demo) {
	const publicContext = await browser.newContext({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 });
	const frontend = await publicContext.newPage();
	await frontend.goto(`${baseUrl}/?page_id=${demo.demoPage.id}`, { waitUntil: 'networkidle' });
	await prepareFrontend(frontend);
	await frontend.screenshot({ path: path.join(outputDir, 'screenshot-1.png') });

	await frontend.locator('[data-tabloria-search]').fill('lamp');
	await frontend.waitForTimeout(250);
	await frontend.addStyleTag({ content: `
		.tabloria-display__grid { grid-template-columns: minmax(0, 320px) !important; justify-content: center !important; }
	` });
	await frontend.evaluate(() => window.scrollTo(0, 0));
	await frontend.screenshot({ path: path.join(outputDir, 'screenshot-2.png') });
	await publicContext.close();

	await adminPage.setViewportSize({ width: 1600, height: 1000 });
	await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=tabloria-displays`, { waitUntil: 'networkidle' });
	const card = adminPage.locator('.tabloria-display-card', { hasText: 'Studio Essentials' });
	await card.waitFor({ state: 'visible' });
	await card.click();
	await adminPage.locator('[data-editor-tab="preview"]').waitFor({ state: 'visible' });
	const previewImages = adminPage.locator('.tabloria-mini-card__image img');
	await previewImages.first().waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});
	console.log(`Admin preview images: ${await previewImages.count()}`);
	await adminPage.screenshot({ path: path.join(outputDir, 'screenshot-3.png') });

	await adminPage.goto(`${baseUrl}/wp-admin/post.php?post=${demo.demoPage.id}&action=edit`, { waitUntil: 'domcontentloaded', timeout: 30000 });
	await adminPage.locator('.wp-block-tabloria-display, [data-type="tabloria/display"]').first().waitFor({ state: 'visible', timeout: 20000 });
	await adminPage.keyboard.press('Escape');
	await adminPage.waitForTimeout(300);
	await adminPage.screenshot({ path: path.join(outputDir, 'screenshot-4.png') });

	await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=tabloria-displays`, { waitUntil: 'networkidle' });
	await adminPage.locator('.tabloria-display-card', { hasText: 'Studio Essentials' }).waitFor({ state: 'visible' });
	await adminPage.locator('.tabloria-display-card').evaluateAll((cards) => {
		cards.forEach((item) => {
			if (!item.textContent.includes('Studio Essentials')) {
				item.style.display = 'none';
			}
		});
	});
	await adminPage.screenshot({ path: path.join(outputDir, 'screenshot-5.png') });
}

async function main() {
	const browser = await chromium.launch({
		headless: true,
		executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
		args: ['--no-sandbox', '--disable-dev-shm-usage'],
	});

	try {
		await captureBanner(browser, 1544, 500, 'banner-1544x500.png');
		await captureBanner(browser, 772, 250, 'banner-772x250.png');

		const context = await browser.newContext({ viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1 });
		const page = await context.newPage();
		await login(page);
		const demo = await ensureDemo(page);
		await captureScreenshots(browser, page, demo);
		await context.close();
		console.log(`Captured WordPress.org assets in ${outputDir}`);
	} finally {
		await browser.close();
	}
}

main().catch((error) => {
	console.error(error);
	process.exitCode = 1;
});
