(function () {
	'use strict';

	var root = document.getElementById('tabloria-admin-root');
	if (!root) {
		return;
	}

	var env = window.tabloriaAdmin || {};
	var displayTypes = [
		{
			key: 'searchable_listings',
			label: 'Searchable Listings',
			copy: 'Create searchable cards from rows of data. Great for properties, services, resources, or locations.'
		},
		{
			key: 'product_catalog',
			label: 'Product Catalog',
			copy: 'Display products, assets, or offers with images, prices, and filters.'
		},
		{
			key: 'team_directory',
			label: 'Team Directory',
			copy: 'Create a clean people or organization directory.'
		},
		{
			key: 'event_schedule',
			label: 'Event Schedule',
			copy: 'Turn date-based rows into a readable schedule.'
		},
		{
			key: 'pricing_table',
			label: 'Pricing Table',
			copy: 'Create pricing cards from spreadsheet rows.'
		},
		{
			key: 'simple_data_table',
			label: 'Simple Data Table',
			copy: 'Publish a searchable table with sorting and pagination.'
		}
	];

	var state = {
		screen: initialScreen(),
		source: null,
		sample: [],
		displayType: 'searchable_listings',
		config: null,
		display: null,
		displays: [],
		loadedDisplays: false,
		editorTab: 'preview',
		updateSummary: null,
		settings: null,
		loading: false,
		error: '',
		notice: '',
		setupAccordions: null
	};

	function initialScreen() {
		if (env.currentPage === 'tabloria-displays') {
			return 'displays';
		}
		if (env.currentPage === 'tabloria-settings') {
			return 'settings';
		}
		return 'welcome';
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function safeImageUrl(value) {
		if (!value) {
			return '';
		}

		try {
			var url = new URL(String(value), window.location.origin);
			if (url.protocol !== 'http:' && url.protocol !== 'https:') {
				return '';
			}
			if (!/\.(jpg|jpeg|png|webp|gif)$/i.test(url.pathname)) {
				return '';
			}
			return url.href;
		} catch (error) {
			return '';
		}
	}

	function safeUrl(value) {
		if (!value) {
			return '';
		}
		if (!/^https?:\/\//i.test(String(value).trim())) {
			return '';
		}

		try {
			var url = new URL(String(value));
			if (url.protocol !== 'http:' && url.protocol !== 'https:') {
				return '';
			}
			return url.href;
		} catch (error) {
			return '';
		}
	}

	function endpoint(path) {
		return String(env.restUrl || '').replace(/\/$/, '') + path;
	}

	function request(path, options) {
		options = options || {};
		var headers = options.headers || {};
		headers['X-WP-Nonce'] = env.nonce;

		if (options.body && !(options.body instanceof FormData)) {
			headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(options.body);
		}

		options.headers = headers;

		return fetch(endpoint(path), options).then(function (response) {
			return response.text().then(function (text) {
				var data = {};
				if (text) {
					try {
						data = JSON.parse(text);
					} catch (error) {
						throw new Error(readableServerError(text));
					}
				}
				if (!response.ok) {
					throw new Error(data.message || response.statusText || 'Tabloria request failed.');
				}
				return data;
			});
		});
	}

	function readableServerError(text) {
		var message = String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
		if (!message) {
			return 'Tabloria received an invalid server response.';
		}
		return message.slice(0, 220);
	}

	function setError(error) {
		state.error = error && error.message ? error.message : String(error || '');
		render();
	}

	function setScreen(screen) {
		state.error = '';
		state.notice = '';
		state.screen = screen;
		if (screen === 'displays') {
			loadDisplays();
			return;
		}
		if (screen === 'settings' && !state.settings) {
			loadSettings();
			return;
		}
		render();
	}

	function render() {
		root.innerHTML = [
			'<div class="tabloria-app">',
			renderTopbar(),
			'<main class="tabloria-shell">',
			state.error ? '<div class="tabloria-error">' + escapeHtml(state.error) + '</div>' : '',
			state.notice ? '<div class="tabloria-notice-banner">' + escapeHtml(state.notice) + '</div>' : '',
			renderCurrentScreen(),
			'</main>',
			'</div>'
		].join('');

		bindTopbar();
		bindCurrentScreen();
	}

	function renderTopbar() {
		var items = [
			['welcome', 'Create Display'],
			['displays', 'Published Displays'],
			['settings', 'Settings']
		];

		return [
			'<header class="tabloria-topbar">',
			'<div class="tabloria-brand"><span class="tabloria-logo">T</span><span>Tabloria</span></div>',
			'<nav class="tabloria-nav" aria-label="Tabloria navigation">',
			items.map(function (item) {
				var active = navIsActive(item[0]) ? ' is-active' : '';
				return '<button type="button" class="' + active + '" data-screen="' + item[0] + '">' + escapeHtml(item[1]) + '</button>';
			}).join(''),
			'</nav>',
			'</header>'
		].join('');
	}

	function navIsActive(screen) {
		if (screen === 'welcome') {
			return ['welcome', 'upload', 'detection', 'outcome', 'setup', 'success'].indexOf(state.screen) >= 0;
		}
		if (screen === 'displays') {
			return ['displays', 'editor'].indexOf(state.screen) >= 0;
		}
		return state.screen === screen;
	}

	function bindTopbar() {
		root.querySelectorAll('.tabloria-nav [data-screen]').forEach(function (button) {
			button.addEventListener('click', function () {
				setScreen(button.dataset.screen);
			});
		});
	}

	function renderCurrentScreen() {
		if (state.loading) {
			return '<div class="tabloria-loading">Loading Tabloria...</div>';
		}

		switch (state.screen) {
			case 'upload':
				return renderUpload();
			case 'detection':
				return renderDetection();
			case 'outcome':
				return renderOutcome();
			case 'setup':
				return renderSetup();
			case 'success':
				return renderSuccess();
			case 'editor':
				return renderEditor();
			case 'displays':
				return renderDisplays();
			case 'settings':
				return renderSettings();
			case 'welcome':
			default:
				return renderWelcome();
		}
	}

	function bindCurrentScreen() {
		root.querySelectorAll('[data-action="upload"]').forEach(function (button) {
			button.addEventListener('click', function () {
				setScreen('upload');
			});
		});

		if (state.loading) {
			return;
		}

		if (state.screen === 'welcome') {
			bindWelcome();
		} else if (state.screen === 'upload') {
			bindUpload();
		} else if (state.screen === 'detection') {
			bindDetection();
		} else if (state.screen === 'outcome') {
			bindOutcome();
		} else if (state.screen === 'setup') {
			bindSetup();
		} else if (state.screen === 'success') {
			bindSuccess();
		} else if (state.screen === 'editor') {
			bindEditor();
		} else if (state.screen === 'displays') {
			bindDisplays();
		} else if (state.screen === 'settings') {
			bindSettings();
		}
	}

	function renderWelcome() {
		return [
			'<section class="tabloria-hero tabloria-choice-hero">',
			'<p class="tabloria-kicker">Published Display</p>',
			'<h1>What would you like to display?</h1>',
			'<p class="tabloria-subtitle">Choose the kind of page you want to create. Tabloria will use this to shape fields, filters, sorting, pagination, and preview defaults.</p>',
			'</section>',
			'<section class="tabloria-grid tabloria-type-grid">',
			displayTypes.map(function (type) {
				return renderDisplayTypeCard(type, state.displayType === type.key);
			}).join(''),
			'</section>',
			'<div class="tabloria-actions">',
			'<button class="tabloria-button is-primary" data-action="start">Create Display</button>',
			'<button class="tabloria-button" data-action="displays">Published Displays</button>',
			'<button class="tabloria-button is-ghost" disabled>Connect Google Sheets <span class="tabloria-badge">Coming soon</span></button>',
			'</div>'
		].join('');
	}

	function renderDisplayTypeCard(type, selected) {
		return '<article class="tabloria-card' + (selected ? ' is-selected' : '') + '" data-type="' + escapeHtml(type.key) + '">' + renderTypePreview(type.key) + '<h3>' + escapeHtml(type.label) + '</h3><p>' + escapeHtml(type.copy) + '</p></article>';
	}

	function bindWelcome() {
		root.querySelectorAll('.tabloria-card[data-type]').forEach(function (card) {
			card.addEventListener('click', function () {
				state.displayType = card.dataset.type;
				state.config = null;
				render();
			});
		});
		root.querySelector('[data-action="start"]').addEventListener('click', function () {
			state.display = null;
			state.source = null;
			state.sample = [];
			state.config = null;
			state.setupAccordions = null;
			setScreen('upload');
		});
		var displaysButton = root.querySelector('[data-action="displays"]');
		if (displaysButton) {
			displaysButton.addEventListener('click', function () {
				setScreen('displays');
			});
		}
	}

	function renderUpload() {
		return [
			'<section class="tabloria-hero">',
			'<p class="tabloria-kicker">Create Display</p>',
			'<h1>Upload your spreadsheet.</h1>',
			'<p class="tabloria-subtitle">Start with a CSV file. Tabloria will detect columns, field types, and a few useful publishing defaults.</p>',
			'</section>',
			'<form class="tabloria-upload" id="tabloria-upload-form">',
			'<div>',
			'<h2>Choose a CSV file</h2>',
			'<p class="tabloria-muted">Excel support is prepared in the architecture, but this MVP imports CSV first. Maximum file size: 5 MB.</p>',
			'<input class="tabloria-input" type="file" name="file" accept=".csv,text/csv" required />',
			'<div class="tabloria-actions" style="justify-content:center">',
			'<button class="tabloria-button" type="button" data-back>Back</button>',
			'<button class="tabloria-button is-primary" type="submit">Upload Spreadsheet</button>',
			'</div>',
			'</div>',
			'</form>'
		].join('');
	}

	function bindUpload() {
		var form = root.querySelector('#tabloria-upload-form');
		var back = root.querySelector('[data-back]');

		if (!form || !back) {
			return;
		}

		back.addEventListener('click', function () {
			setScreen('welcome');
		});
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var input = form.querySelector('input[type="file"]');
			var file = input && input.files ? input.files[0] : null;
			if (!file) {
				setError(new Error('Please choose a CSV file to upload.'));
				return;
			}
			if (file.size > 5242880) {
				setError(new Error('The CSV file is too large. Tabloria currently accepts files up to 5 MB.'));
				return;
			}
			var data = new FormData(form);
			state.loading = true;
			render();
			request('/upload', { method: 'POST', body: data })
				.then(function (response) {
					state.source = response.source;
					state.sample = response.sample || [];
					state.config = generateConfig(state.source, state.displayType);
					state.loading = false;
					setScreen('detection');
				})
				.catch(function (error) {
					state.loading = false;
					setError(error);
				});
		});
	}

	function renderDetection() {
		if (!state.source) {
			return renderMissingSource();
		}

		return [
			'<section class="tabloria-hero">',
			'<p class="tabloria-kicker">Smart Detection</p>',
			'<h1>Review detected columns.</h1>',
			'<p class="tabloria-subtitle">' + escapeHtml(state.source.original_filename) + ' - ' + escapeHtml(state.source.row_count) + ' rows detected. You can adjust field mapping on the next step.</p>',
			'</section>',
			'<section class="tabloria-panel">',
			'<h2>Detected Columns</h2>',
			'<p class="tabloria-muted">Tabloria uses these column types to suggest a starting Display. If something looks off, continue and correct the mapping before saving.</p>',
			'<div class="tabloria-columns">',
			(state.source.columns || []).map(function (column) {
				return '<div class="tabloria-column"><div><strong>' + escapeHtml(column.label) + '</strong><span class="tabloria-muted">' + escapeHtml(column.key) + '</span></div><span class="tabloria-type">' + escapeHtml(column.type) + '</span></div>';
			}).join(''),
			'</div>',
			'</section>',
			'<section class="tabloria-panel">',
			'<h2>First Rows Preview</h2>',
			renderTable(state.sample, state.source.columns),
			'</section>',
			'<div class="tabloria-actions">',
			'<button class="tabloria-button" data-back>Upload different CSV</button>',
			'<button class="tabloria-button is-primary" data-continue>Continue with detected columns</button>',
			'</div>'
		].join('');
	}

	function bindDetection() {
		if (!root.querySelector('[data-back]') || !root.querySelector('[data-continue]')) {
			return;
		}
		root.querySelector('[data-back]').addEventListener('click', function () {
			setScreen('upload');
		});
		root.querySelector('[data-continue]').addEventListener('click', function () {
			setScreen('setup');
		});
	}

	function renderOutcome() {
		return [
			'<section class="tabloria-hero" style="text-align:center;margin-left:auto;margin-right:auto">',
			'<p class="tabloria-kicker">Published Display</p>',
			'<h1>What would you like to display?</h1>',
			'<p class="tabloria-subtitle">Choose the kind of page you want to create from this spreadsheet. Tabloria will choose helpful defaults for fields, filters, sorting, and pagination.</p>',
			'</section>',
			'<section class="tabloria-grid tabloria-type-grid">',
			displayTypes.map(function (type) {
				return renderDisplayTypeCard(type, state.displayType === type.key);
			}).join(''),
			'</section>',
			'<div class="tabloria-actions" style="justify-content:flex-end">',
			'<button class="tabloria-button" data-back>Back</button>',
			'<button class="tabloria-button is-primary" data-continue>Continue</button>',
			'</div>'
		].join('');
	}

	function renderOutcomePreview(type) {
		return renderTypePreview(type);
	}

	function renderTypePreview(type) {
		var previewType = type || 'searchable_listings';
		var content = '';
		if (previewType === 'simple_data_table') {
			content = '<div class="tabloria-preview-table"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>';
		} else if (previewType === 'product_catalog') {
			content = '<div class="tabloria-preview-products"><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span></div>';
		} else if (previewType === 'team_directory') {
			content = '<div class="tabloria-preview-team"><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span></div>';
		} else if (previewType === 'event_schedule') {
			content = '<div class="tabloria-preview-schedule"><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span></div>';
		} else if (previewType === 'pricing_table') {
			content = '<div class="tabloria-preview-pricing"><span><b></b><i></i><em></em><em></em></span><span><b></b><i></i><em></em><em></em></span><span><b></b><i></i><em></em><em></em></span></div>';
		} else {
			content = '<div class="tabloria-preview-listings"><span><i></i><b></b><em></em></span><span><i></i><b></b><em></em></span></div>';
		}
		return '<div class="tabloria-card__preview tabloria-card__preview--' + escapeHtml(previewType.replace(/_/g, '-')) + '">' + content + '</div>';
	}

	function bindOutcome() {
		root.querySelectorAll('.tabloria-card[data-type]').forEach(function (card) {
			card.addEventListener('click', function () {
				state.displayType = card.dataset.type;
				state.config = generateConfig(state.source, state.displayType);
				render();
			});
		});
		root.querySelector('[data-back]').addEventListener('click', function () {
			setScreen('detection');
		});
		root.querySelector('[data-continue]').addEventListener('click', function () {
			setScreen('setup');
		});
	}

	function renderSetup() {
		if (!state.source) {
			return renderMissingSource();
		}

		state.config = state.config || generateConfig(state.source, state.displayType);
		var columns = state.source.columns || [];
		var roles = roleDefinitions();

		return [
			'<section class="tabloria-hero">',
			'<p class="tabloria-kicker">Display Setup</p>',
			'<h1>Set up and preview</h1>',
			'<p class="tabloria-subtitle">Choose the Display structure and see the page update with your spreadsheet data.</p>',
			'</section>',
			'<div class="tabloria-setup-layout">',
			'<aside class="tabloria-setup-controls">',
			'<section class="tabloria-panel tabloria-panel--compact">',
			'<div class="tabloria-form-grid tabloria-form-grid--compact">',
			'<div class="tabloria-field"><label for="display-name">Display name</label><input id="display-name" class="tabloria-input" value="' + escapeHtml(state.config.title || '') + '" /></div>',
			'<div class="tabloria-field"><label for="setup-display-type">Display type</label>' + renderDisplayTypeSelect('setup-display-type', currentDisplayType()) + '</div>',
			'</div>',
			'</section>',
			renderSetupAccordion('content', 'Display Content', 'Map columns to the visible parts of this Display.', renderContentMapper(roles, state.config, 'role-', 'visible-field', false), true),
			renderSetupAccordion('filters', 'Filtering & Sorting', 'Choose visitor filters, default filter, and sort order.', [
				'<h3>Default Filter</h3>',
				renderDefaultFilter(),
				'<h3 style="margin-top:24px">Visitor Filters</h3>',
				columns.map(function (column) {
					var checked = (state.config.visitorFilters || []).indexOf(column.key) >= 0 ? ' checked' : '';
					return '<label class="tabloria-check-row"><input type="checkbox" name="visitor-filter" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
				}).join(''),
				'<h3 style="margin-top:24px">Default Sort</h3>',
				'<div class="tabloria-filter-builder">' + renderColumnSelect('sort-field', state.config.sort.field || '', true) + '<select class="tabloria-select" id="sort-direction"><option value="asc"' + selected(state.config.sort.direction, 'asc') + '>Low to high</option><option value="desc"' + selected(state.config.sort.direction, 'desc') + '>High to low</option></select><span></span></div>'
			].join(''), false),
			renderSetupAccordion('options', 'Display options', 'Show or hide visitor controls on the published page.', [
				renderDisplayWidthOptions('display-width', state.config),
				renderCardFieldDisplayOptions('card', state.config),
				renderToggle('show-search', 'Show search', state.config.search.enabled),
				renderToggle('show-filters', 'Show filters', state.config.filters.enabled),
				renderToggle('show-pagination', 'Show pagination', state.config.pagination.enabled),
				'<div class="tabloria-field" style="margin-top:14px"><label for="per-page">Items per page</label><select id="per-page" class="tabloria-select"' + (!state.config.pagination.enabled ? ' disabled' : '') + '><option value="6"' + selected(state.config.pagination.perPage, 6) + '>6 items</option><option value="9"' + selected(state.config.pagination.perPage, 9) + '>9 items</option><option value="12"' + selected(state.config.pagination.perPage, 12) + '>12 items</option><option value="24"' + selected(state.config.pagination.perPage, 24) + '>24 items</option></select></div>'
			].join(''), false),
			'<div class="tabloria-actions tabloria-setup-actions">',
			'<button class="tabloria-button" data-back>Back</button>',
			'<button class="tabloria-button is-primary" data-continue>Save Display</button>',
			'</div>',
			'</aside>',
			'<section class="tabloria-preview tabloria-setup-preview">',
			'<div class="tabloria-preview__bar">yourwebsite.com/' + escapeHtml(slugify(state.config.title || 'tabloria-display')) + '</div>',
			'<div class="tabloria-preview__body">' + renderMiniPreview(true) + '</div>',
			'</section>',
			'</div>'
		].join('');
	}

	function renderSetupAccordion(key, title, description, body, defaultOpen) {
		return [
			'<details class="tabloria-panel tabloria-panel--compact tabloria-accordion" data-setup-accordion="' + escapeHtml(key) + '"' + (setupAccordionIsOpen(key, defaultOpen) ? ' open' : '') + '>',
			'<summary class="tabloria-accordion__summary"><span><strong>' + escapeHtml(title) + '</strong><small>' + escapeHtml(description) + '</small></span></summary>',
			'<div class="tabloria-accordion__body">',
			body,
			'</div>',
			'</details>'
		].join('');
	}

	function setupAccordionIsOpen(key, defaultOpen) {
		if (!state.setupAccordions || !Object.prototype.hasOwnProperty.call(state.setupAccordions, key)) {
			return !!defaultOpen;
		}
		return !!state.setupAccordions[key];
	}

	function renderDefaultFilter() {
		var filter = (state.config.defaultFilters || [])[0] || { field: '', operator: 'equals', value: '' };
		return [
			'<div class="tabloria-filter-builder">',
			renderColumnSelect('default-filter-field', filter.field || '', true),
			renderFilterOperatorSelect('default-filter-operator', filter.operator || 'equals'),
			'<input id="default-filter-value" class="tabloria-input" placeholder="Value, for example Available" value="' + escapeHtml(filter.value || '') + '" />',
			'</div>'
		].join('');
	}

	function renderFilterOperatorSelect(id, current) {
		return [
			'<select id="' + escapeHtml(id) + '" class="tabloria-select">',
			filterOperators().map(function (operator) {
				return '<option value="' + escapeHtml(operator.value) + '"' + selected(current, operator.value) + '>' + escapeHtml(operator.label) + '</option>';
			}).join(''),
			'</select>'
		].join('');
	}

	function filterOperators() {
		return [
			{ value: 'equals', label: '=' },
			{ value: 'not_equals', label: '!=' },
			{ value: 'contains', label: 'contains' },
			{ value: 'less_than', label: '<' },
			{ value: 'greater_than', label: '>' }
		];
	}

	function renderColumnSelect(id, current, includeNone) {
		var columns = state.source ? state.source.columns || [] : [];
		return [
			'<select class="tabloria-select" id="' + escapeHtml(id) + '">',
			includeNone ? '<option value="">None</option>' : '',
			columns.map(function (column) {
				return '<option value="' + escapeHtml(column.key) + '"' + selected(current, column.key) + '>' + escapeHtml(column.label) + '</option>';
			}).join(''),
			'</select>'
		].join('');
	}

	function roleDefinitions() {
		var definitions = {
			searchable_listings: [
				{ key: 'image', label: 'Image', description: 'Optional visual for each listing.', optional: true, empty: 'No image-like column detected.' },
				{ key: 'title', label: 'Listing title', description: 'The primary text visitors notice first.' },
				{ key: 'subtitle', label: 'Supporting line', description: 'Location, category, or short context.' },
				{ key: 'price', label: 'Highlight', description: 'A featured value such as price, rate, or amount.', optional: true, empty: 'No highlight-like column detected.' },
				{ key: 'badge', label: 'Label / badge', description: 'A short status, category, or tag.', optional: true, empty: 'No badge-like column detected.' }
			],
			product_catalog: [
				{ key: 'image', label: 'Product image', description: 'The product photo or visual.', optional: true, empty: 'No image-like column detected.' },
				{ key: 'title', label: 'Product name', description: 'The product name visitors see first.' },
				{ key: 'subtitle', label: 'Category line', description: 'Category, brand, or short product context.' },
				{ key: 'price', label: 'Price', description: 'The product price or amount.', optional: true, commerceOnlyWhenEmpty: true, empty: 'No price-like column detected.' },
				{ key: 'badge', label: 'Availability badge', description: 'Availability, category, or short label.', optional: true, empty: 'No badge-like column detected.' }
			],
			team_directory: [
				{ key: 'image', label: 'Photo', description: 'The person photo or avatar.', optional: true, empty: 'No photo-like column detected.' },
				{ key: 'title', label: 'Name', description: 'The person or organization name.' },
				{ key: 'subtitle', label: 'Position', description: 'Role, title, or short profile context.' },
				{ key: 'badge', label: 'Team / department', description: 'Team, department, group, or label.', optional: true, empty: 'No team-like column detected.' }
			],
			event_schedule: [
				{ key: 'title', label: 'Event title', description: 'The event, session, or agenda item name.' },
				{ key: 'price', label: 'Date / time', description: 'The date, time, or schedule slot.', optional: true, empty: 'No date/time-like column detected.' },
				{ key: 'subtitle', label: 'Location', description: 'Location, room, track, or context.', optional: true, empty: 'No location-like column detected.' },
				{ key: 'badge', label: 'Track / status', description: 'Track, type, status, or label.', optional: true, empty: 'No label-like column detected.' }
			],
			pricing_table: [
				{ key: 'title', label: 'Plan name', description: 'The pricing plan or offer name.' },
				{ key: 'price', label: 'Price', description: 'The main price or amount.', optional: true, commerceOnlyWhenEmpty: true, empty: 'No price-like column detected.' },
				{ key: 'subtitle', label: 'Short description', description: 'A short explanation under the plan name.', optional: true, empty: 'No description-like column detected.' },
				{ key: 'badge', label: 'Badge', description: 'Popular, recommended, tier, or short label.', optional: true, empty: 'No badge-like column detected.' }
			]
		};

		return definitions[currentDisplayType()] || definitions.searchable_listings;
	}

	function renderContentMapper(roles, config, idPrefix, visibleName, showExample) {
		var model = displayContentModel();
		showExample = showExample !== false;
		if (currentDisplayType() === 'simple_data_table') {
			return [
				'<div class="tabloria-content-mapper' + (!showExample ? ' is-controls-only' : '') + '">',
				'<div class="tabloria-content-mapper__controls">',
				'<h3>' + escapeHtml(model.structureTitle) + '</h3>',
				'<p class="tabloria-muted">' + escapeHtml(model.structureHelp) + '</p>',
				renderVisibleFieldChecks(visibleName, config),
				'</div>',
				showExample ? [
				'<aside class="tabloria-content-example">',
				'<p class="tabloria-example-label">' + escapeHtml(model.exampleLabel) + '</p>',
				renderMappingTableExample(config),
				'</aside>'
				].join('') : '',
				'</div>'
			].join('');
		}

		return [
			'<div class="tabloria-content-mapper' + (!showExample ? ' is-controls-only' : '') + '">',
			'<div class="tabloria-content-mapper__controls">',
			'<h3>' + escapeHtml(model.structureTitle) + '</h3>',
			'<p class="tabloria-muted">' + escapeHtml(model.structureHelp) + '</p>',
			renderRoleRows(roles, config, idPrefix),
			'<h3 style="margin-top:24px">' + escapeHtml(model.additionalTitle) + '</h3>',
			'<p class="tabloria-muted">' + escapeHtml(model.additionalHelp) + '</p>',
			renderVisibleFieldChecks(visibleName, config),
			'</div>',
			showExample ? [
			'<aside class="tabloria-content-example">',
			'<p class="tabloria-example-label">' + escapeHtml(model.exampleLabel) + '</p>',
			renderMappingExample(config, model),
			'</aside>'
			].join('') : '',
			'</div>'
		].join('');
	}

	function displayContentModel() {
		var models = {
			searchable_listings: {
				structureTitle: 'Listing Structure',
				structureHelp: 'Choose the columns that shape each listing.',
				additionalTitle: 'Listing Details',
				additionalHelp: 'Choose extra columns visitors can read after the main listing content.',
				exampleLabel: 'Example listing from your data',
				exampleType: 'listing'
			},
			product_catalog: {
				structureTitle: 'Product Structure',
				structureHelp: 'Choose the columns that shape each product in the catalog.',
				additionalTitle: 'Product Details',
				additionalHelp: 'Choose extra product columns such as SKU, supplier, dimensions, or description.',
				exampleLabel: 'Example product from your data',
				exampleType: 'product'
			},
			team_directory: {
				structureTitle: 'Directory Profile',
				structureHelp: 'Choose the columns that shape each profile in the directory.',
				additionalTitle: 'Contact & Profile Details',
				additionalHelp: 'Choose extra profile fields such as email, phone, website, or bio.',
				exampleLabel: 'Example profile from your data',
				exampleType: 'profile'
			},
			event_schedule: {
				structureTitle: 'Schedule Item',
				structureHelp: 'Choose the columns that shape each item in the schedule.',
				additionalTitle: 'Event Details',
				additionalHelp: 'Choose extra event fields such as speaker, description, audience, or notes.',
				exampleLabel: 'Example schedule item from your data',
				exampleType: 'schedule'
			},
			pricing_table: {
				structureTitle: 'Pricing Item',
				structureHelp: 'Choose the columns that shape each pricing plan or offer.',
				additionalTitle: 'Included Details',
				additionalHelp: 'Choose extra columns such as features, limits, term, or audience.',
				exampleLabel: 'Example pricing item from your data',
				exampleType: 'pricing'
			},
			simple_data_table: {
				structureTitle: 'Table Columns',
				structureHelp: 'Choose which spreadsheet columns appear in the published table.',
				additionalTitle: '',
				additionalHelp: '',
				exampleLabel: 'Example table from your data',
				exampleType: 'table'
			}
		};

		return models[currentDisplayType()] || models.searchable_listings;
	}

	function renderVisibleFieldChecks(name, config) {
		var columns = state.source ? state.source.columns || [] : [];
		var mappedFields = currentDisplayType() === 'simple_data_table' ? [] : Object.keys(config.fieldRoles || {}).map(function (key) {
			return config.fieldRoles[key];
		}).filter(Boolean);
		var fields = columns.filter(function (column) {
			return !isTechnicalField(column.key) && mappedFields.indexOf(column.key) === -1;
		});

		if (!fields.length) {
			return '<p class="tabloria-muted">No extra columns remain after the main structure.</p>';
		}

		return '<div class="tabloria-detail-field-list">' + fields.map(function (column) {
			var checked = (config.visibleFields || []).indexOf(column.key) >= 0 ? ' checked' : '';
			return '<label class="tabloria-check-row"><input type="checkbox" name="' + escapeHtml(name) + '" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
		}).join('') + '</div>';
	}

	function renderRoleRows(roles, config, idPrefix) {
		var rows = roles.map(function (role) {
			return renderRoleRow(role, config, idPrefix);
		}).filter(Boolean);
		return rows.length ? rows.join('') : '<p class="tabloria-muted">No role fields are needed for this source.</p>';
	}

	function renderRoleRow(role, config, idPrefix) {
		var current = config.fieldRoles && config.fieldRoles[role.key] ? config.fieldRoles[role.key] : '';
		var options = roleOptions(role.key, current);
		if (!options.length && role.optional && !(role.commerceOnlyWhenEmpty && commerceDisplayType())) {
			return '';
		}

		var description = options.length ? role.description : role.empty;
		return '<div class="tabloria-role-row"><div class="tabloria-role-row__copy"><strong>' + escapeHtml(role.label) + '</strong><span class="tabloria-muted">' + escapeHtml(description || role.description) + '</span></div>' + renderRoleSelect(idPrefix + role.key, role, current, options) + '</div>';
	}

	function renderRoleSelect(id, role, current, options) {
		if (!options.length) {
			return '<select class="tabloria-select" id="' + escapeHtml(id) + '" disabled><option value="">' + escapeHtml(role.empty || 'No matching column') + '</option></select>';
		}

		return [
			'<select class="tabloria-select" id="' + escapeHtml(id) + '">',
			'<option value="">None</option>',
			options.map(function (column) {
				return '<option value="' + escapeHtml(column.key) + '"' + selected(current, column.key) + '>' + escapeHtml(column.label) + '</option>';
			}).join(''),
			'</select>'
		].join('');
	}

	function renderMappingExample(config, model) {
		if (model.exampleType === 'schedule') {
			return renderMappingScheduleExample(config);
		}
		if (model.exampleType === 'pricing') {
			return renderMappingPricingExample(config);
		}
		if (model.exampleType === 'profile') {
			return renderMappingProfileExample(config);
		}
		return renderMappingVisualExample(config, model.exampleType);
	}

	function mappingParts(config) {
		var roles = config.fieldRoles || {};
		var row = exampleRowForConfig(config);
		var title = valueForExample(row, roles.title) || bestRowTitle(row) || 'Display item';
		var subtitle = valueForExample(row, roles.subtitle) || bestRowSubtitle(row, title) || '';
		var image = valueForExample(row, roles.image);
		var highlight = valueForExample(row, roles.price);
		var badge = valueForExample(row, roles.badge);
		var details = renderMiniFields(row, roles);

		return {
			roles: roles,
			row: row,
			title: title,
			subtitle: subtitle,
			image: image,
			highlight: highlight,
			badge: badge,
			details: details
		};
	}

	function renderMappingVisualExample(config, type) {
		var part = mappingParts(config);
		var imageLabel = type === 'product' ? 'Product image' : 'Image';
		var titleLabel = type === 'product' ? 'Product name' : 'Listing title';
		var highlightLabel = type === 'product' ? 'Price' : 'Highlight';

		return [
			'<article class="tabloria-mapping-card tabloria-mapping-' + escapeHtml(type || 'listing') + '">',
			part.roles.image || part.image ? '<div class="tabloria-slot-label">' + escapeHtml(imageLabel) + '</div>' + renderMiniImage(part.image) : '',
			'<div class="tabloria-mapping-card__body">',
			part.badge ? '<div class="tabloria-slot-label">Label / badge</div><span class="tabloria-mapping-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<div class="tabloria-slot-label">' + escapeHtml(titleLabel) + '</div>',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<div class="tabloria-slot-label">' + (type === 'product' ? 'Category line' : 'Supporting line') + '</div><p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.highlight ? '<div class="tabloria-slot-label">' + escapeHtml(highlightLabel) + '</div><strong>' + escapeHtml(part.highlight) + '</strong>' : '',
			part.details ? '<div class="tabloria-slot-label">Additional fields</div>' + part.details : '',
			'</div>',
			'</article>'
		].join('');
	}

	function renderMappingProfileExample(config) {
		var part = mappingParts(config);
		return [
			'<article class="tabloria-mapping-card tabloria-mapping-profile">',
			part.roles.image || part.image ? renderMiniImage(part.image) : '',
			'<div class="tabloria-mapping-card__body">',
			part.badge ? '<span class="tabloria-mapping-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<div class="tabloria-slot-label">Name</div>',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<div class="tabloria-slot-label">Position</div><p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.details ? '<div class="tabloria-slot-label">Contact & profile details</div>' + part.details : '',
			'</div>',
			'</article>'
		].join('');
	}

	function renderMappingScheduleExample(config) {
		var part = mappingParts(config);
		return [
			'<article class="tabloria-mapping-schedule">',
			'<div class="tabloria-mapping-schedule__time">',
			'<span>Date / time</span>',
			'<strong>' + escapeHtml(part.highlight || 'Not set') + '</strong>',
			'</div>',
			'<div class="tabloria-mapping-schedule__body">',
			part.badge ? '<span class="tabloria-mapping-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<div class="tabloria-slot-label">Event title</div>',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<div class="tabloria-slot-label">Location</div><p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.details ? '<div class="tabloria-slot-label">Event details</div>' + part.details : '',
			'</div>',
			'</article>'
		].join('');
	}

	function renderMappingPricingExample(config) {
		var part = mappingParts(config);
		return [
			'<article class="tabloria-mapping-card tabloria-mapping-pricing">',
			'<div class="tabloria-mapping-card__body">',
			part.badge ? '<span class="tabloria-mapping-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<div class="tabloria-slot-label">Plan name</div>',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.highlight ? '<div class="tabloria-slot-label">Price</div><strong>' + escapeHtml(part.highlight) + '</strong>' : '',
			part.subtitle ? '<div class="tabloria-slot-label">Short description</div><p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.details ? '<div class="tabloria-slot-label">Included details</div>' + part.details : '',
			'</div>',
			'</article>'
		].join('');
	}

	function renderMappingTableExample(config, rowsOverride, limit) {
		var sourceRows = Array.isArray(rowsOverride) ? rowsOverride : (state.sample && state.sample.length ? state.sample : sampleRowsFromConfig());
		var rows = sourceRows.slice(0, limit || 4);
		var columns = tableExampleColumns(config);
		var displayWidth = displayWidthValue(config);
		var columnWidth = displayWidth === 'full' ? 96 : (displayWidth === 'wide' ? 120 : 160);
		var tableMinWidth = Math.max(520, columns.length * columnWidth);
		if (!columns.length) {
			return '<p class="tabloria-muted">Choose columns to see a table example.</p>';
		}
		if (!rows.length) {
			return '<p class="tabloria-muted">No sample rows match the current default filter.</p>';
		}

		return [
			'<div class="tabloria-mapping-table-wrap is-width-' + escapeHtml(displayWidth) + '" style="--tabloria-table-min-width:' + tableMinWidth + 'px"><table class="tabloria-mapping-table">',
			'<thead><tr>',
			columns.map(function (column) {
				return '<th>' + escapeHtml(column.label) + '</th>';
			}).join(''),
			'</tr></thead><tbody>',
			rows.map(function (row) {
				return '<tr>' + columns.map(function (column) {
					return '<td>' + renderMiniTableCell(row, column) + '</td>';
				}).join('') + '</tr>';
			}).join(''),
			'</tbody></table></div>'
		].join('');
	}

	function tableExampleColumns(config) {
		var sourceColumns = state.source ? state.source.columns || [] : [];
		var fields = (config.visibleFields && config.visibleFields.length ? config.visibleFields : sourceColumns.map(function (column) { return column.key; })).filter(function (field) {
			return field && !isTechnicalField(field);
		});

		return fields.map(function (field) {
			var column = sourceColumns.find(function (item) {
				return item.key === field;
			});
			return { key: field, label: column ? column.label : labelForField(field), type: column ? column.type : columnTypeForField(field) };
		});
	}

	function exampleRowForConfig(config) {
		var rows = state.sample && state.sample.length ? state.sample : sampleRowsFromConfig();
		var roles = config.fieldRoles || {};
		var roleFields = Object.keys(roles).map(function (key) {
			return roles[key];
		}).filter(Boolean);
		var visibleFields = config.visibleFields || [];
		var fields = roleFields.concat(visibleFields).filter(Boolean);

		return rows.find(function (row) {
			return fields.some(function (field) {
				return row[field];
			});
		}) || rows[0] || {};
	}

	function valueForExample(row, field) {
		return field && row ? row[field] || '' : '';
	}

	function roleOptions(roleKey, current) {
		var columns = state.source ? state.source.columns || [] : [];
		var publicColumns = columns.filter(function (column) {
			return !isTechnicalField(column.key);
		});
		var options = [];

		if (roleKey === 'title') {
			options = publicColumns.filter(function (column) {
				return !matchesColumn(column, ['image'], ['photo', 'image', 'picture', 'avatar', 'logo', 'thumbnail']);
			});
		} else if (roleKey === 'image') {
			options = publicColumns.filter(function (column) {
				return matchesColumn(column, ['image'], ['photo', 'image', 'picture', 'avatar', 'logo', 'thumbnail']);
			});
		} else if (roleKey === 'price') {
			options = publicColumns.filter(function (column) {
				if (currentDisplayType() === 'event_schedule') {
					return matchesColumn(column, ['date'], ['date', 'time', 'start', 'end', 'schedule']);
				}
				return matchesColumn(column, ['price'], ['price', 'cost', 'rate', 'fee', 'amount', 'total', 'salary', 'budget', 'value']);
			});
		} else if (roleKey === 'badge') {
			options = publicColumns.filter(function (column) {
				return matchesColumn(column, ['status'], ['status', 'category', 'type', 'tag', 'label', 'role', 'department']);
			});
		} else if (roleKey === 'subtitle') {
			options = publicColumns.filter(function (column) {
				return matchesColumn(column, [], ['role', 'category', 'brand', 'building', 'location', 'department', 'course', 'company', 'city', 'region']);
			});
			if (!options.length) {
				options = publicColumns.filter(function (column) {
					return !matchesColumn(column, ['image', 'price'], ['photo', 'image', 'picture', 'avatar', 'logo', 'thumbnail', 'price', 'cost', 'rate', 'fee']);
				});
			}
		}

		if (current && !options.some(function (column) { return column.key === current; })) {
			var currentColumn = publicColumns.find(function (column) { return column.key === current; });
			if (currentColumn) {
				options.push(currentColumn);
			}
		}

		return options;
	}

	function matchesColumn(column, types, tokens) {
		var columnType = String(column.type || '').toLowerCase();
		var text = String((column.key || '') + ' ' + (column.label || '')).toLowerCase();
		if ((types || []).indexOf(columnType) >= 0) {
			return true;
		}
		return (tokens || []).some(function (token) {
			return text.indexOf(token) >= 0;
		});
	}

	function commerceDisplayType() {
		return ['product_catalog', 'pricing_table'].indexOf(currentDisplayType()) >= 0;
	}

	function currentDisplayType() {
		return state.config && state.config.displayType ? state.config.displayType : state.displayType || 'searchable_listings';
	}

	function usesCardLayout() {
		return currentDisplayType() !== 'simple_data_table';
	}

	function selected(current, value) {
		return String(current || '') === String(value || '') ? ' selected' : '';
	}

	function bindSetup() {
		if (!root.querySelector('[data-back]') || !root.querySelector('[data-continue]')) {
			return;
		}
		var typeSelect = root.querySelector('#setup-display-type');
		if (typeSelect) {
			typeSelect.addEventListener('change', function () {
				switchSetupDisplayType(typeSelect.value);
			});
		}
		root.querySelectorAll('[data-setup-accordion]').forEach(function (section) {
			section.addEventListener('toggle', function () {
				state.setupAccordions = state.setupAccordions || {};
				state.setupAccordions[section.dataset.setupAccordion] = section.open;

				if (!section.open) {
					return;
				}

				root.querySelectorAll('[data-setup-accordion]').forEach(function (otherSection) {
					if (otherSection === section || !otherSection.open) {
						return;
					}
					otherSection.open = false;
					state.setupAccordions[otherSection.dataset.setupAccordion] = false;
				});
			});
		});
		root.querySelectorAll('#display-name, .tabloria-setup-controls input, .tabloria-setup-controls select').forEach(function (control) {
			if (control.matches('#setup-display-type')) {
				return;
			}
			control.addEventListener('change', function () {
				collectSetup();
				collectCustomize();
				render();
			});
		});
		root.querySelector('[data-back]').addEventListener('click', function () {
			setScreen('detection');
		});
		root.querySelector('[data-continue]').addEventListener('click', function () {
			collectSetup();
			collectCustomize();
			saveDisplay();
		});
	}

	function collectSetup() {
		var config = state.config || generateConfig(state.source, state.displayType);
		config.title = root.querySelector('#display-name').value.trim() || 'Published Display';
		config.fieldRoles = {
			title: valueOf('#role-title'),
			image: valueOf('#role-image'),
			price: valueOf('#role-price'),
			badge: valueOf('#role-badge'),
			subtitle: valueOf('#role-subtitle')
		};
		config.visibleFields = checkedValues('visible-field');
		config.visitorFilters = checkedValues('visitor-filter');
		var filterField = valueOf('#default-filter-field');
		var filterValue = valueOf('#default-filter-value');
		var filterOperator = valueOf('#default-filter-operator') || 'equals';
		config.defaultFilters = filterField || filterValue || filterOperator !== 'equals' ? [{
			field: filterField,
			operator: filterOperator,
			value: filterValue
		}] : [];
		config.sort = {
			field: valueOf('#sort-field'),
			direction: valueOf('#sort-direction') || 'asc'
		};
		state.config = config;
	}

	function switchSetupDisplayType(displayType) {
		if (!displayTypes.some(function (type) { return type.key === displayType; }) || displayType === currentDisplayType()) {
			return;
		}

		collectSetup();
		collectCustomize();
		state.displayType = displayType;
		state.config = rebuildConfigForDisplayType(displayType, cloneConfig(state.config));
		state.notice = 'Display type changed. Review fields before saving.';
		render();
	}

	function valueOf(selector) {
		var el = root.querySelector(selector);
		return el ? el.value : '';
	}

	function checkedValues(name) {
		return Array.prototype.slice.call(root.querySelectorAll('input[name="' + name + '"]:checked')).map(function (input) {
			return input.value;
		});
	}

	function renderToggle(id, label, checked) {
		return '<label class="tabloria-toggle-row"><span><strong>' + escapeHtml(label) + '</strong></span><input id="' + escapeHtml(id) + '" type="checkbox"' + (checked ? ' checked' : '') + '></label>';
	}

	function renderDisplayWidthOptions(name, config) {
		if (currentDisplayType() !== 'simple_data_table') {
			return '';
		}

		var current = displayWidthValue(config);
		var options = [
			['normal', 'Normal'],
			['wide', 'Wide'],
			['full', 'Full Width']
		];

		return [
			'<fieldset class="tabloria-radio-group">',
			'<legend>Display Width</legend>',
			options.map(function (option) {
				return '<label class="tabloria-radio-row"><input type="radio" name="' + escapeHtml(name) + '" value="' + escapeHtml(option[0]) + '"' + (current === option[0] ? ' checked' : '') + '><span>' + escapeHtml(option[1]) + '</span></label>';
			}).join(''),
			'</fieldset>'
		].join('');
	}

	function renderCardFieldDisplayOptions(prefix, config) {
		if (!usesCardLayout()) {
			return '';
		}

		var options = cardLayoutOptions(config);
		var urlDisplayId = prefix + '-url-display';
		var urlLabelId = prefix + '-url-label';
		var buttonStyleId = prefix + '-button-style';

		return [
			'<section class="tabloria-card-field-options">',
			'<h3>Card Field Display</h3>',
			'<div class="tabloria-form-grid tabloria-form-grid--compact">',
			'<div class="tabloria-field"><label for="' + escapeHtml(urlDisplayId) + '">URL display</label><select id="' + escapeHtml(urlDisplayId) + '" class="tabloria-select"><option value="link"' + selected(options.urlDisplay, 'link') + '>Link</option><option value="button"' + selected(options.urlDisplay, 'button') + '>Button</option></select></div>',
			'<div class="tabloria-field"><label for="' + escapeHtml(urlLabelId) + '">Link/button text</label><input id="' + escapeHtml(urlLabelId) + '" class="tabloria-input" placeholder="Use column header" value="' + escapeHtml(options.urlLabel || '') + '"></div>',
			options.urlDisplay === 'button' ? '<div class="tabloria-field"><label for="' + escapeHtml(buttonStyleId) + '">Button style</label><select id="' + escapeHtml(buttonStyleId) + '" class="tabloria-select">' + cardButtonStyleOptions(options.buttonStyle).join('') + '</select></div>' : '',
			'</div>',
			'<p class="tabloria-muted">Applies to URL columns shown as extra fields in card displays.</p>',
			'</section>'
		].join('');
	}

	function cardButtonStyleOptions(current) {
		return [
			['theme', 'Theme default'],
			['primary', 'Primary'],
			['secondary', 'Secondary'],
			['outline', 'Outline']
		].map(function (option) {
			return '<option value="' + escapeHtml(option[0]) + '"' + selected(current, option[0]) + '>' + escapeHtml(option[1]) + '</option>';
		});
	}

	function displayWidthValue(config) {
		var value = config && config.style ? config.style.displayWidth : '';
		return ['wide', 'full'].indexOf(value) >= 0 ? value : 'normal';
	}

	function defaultLayoutOptions() {
		return {
			cards: {
				urlDisplay: 'link',
				urlLabel: '',
				buttonStyle: 'theme'
			}
		};
	}

	function normalizeCardLayoutOptions(options) {
		options = options || {};
		var urlDisplay = ['link', 'button'].indexOf(options.urlDisplay) >= 0 ? options.urlDisplay : 'link';
		var buttonStyle = ['theme', 'primary', 'secondary', 'outline'].indexOf(options.buttonStyle) >= 0 ? options.buttonStyle : 'theme';

		return {
			urlDisplay: urlDisplay,
			urlLabel: String(options.urlLabel || ''),
			buttonStyle: buttonStyle
		};
	}

	function ensureLayoutOptions(config) {
		config = config || {};
		config.layoutOptions = config.layoutOptions || defaultLayoutOptions();
		config.layoutOptions.cards = normalizeCardLayoutOptions(config.layoutOptions.cards);

		return config.layoutOptions;
	}

	function cardLayoutOptions(config) {
		return ensureLayoutOptions(config || state.config || {}).cards;
	}

	function collectCardFieldDisplayOptions(config, prefix) {
		var urlDisplay = root.querySelector('#' + prefix + '-url-display');
		var urlLabel = root.querySelector('#' + prefix + '-url-label');
		var buttonStyle = root.querySelector('#' + prefix + '-button-style');

		if (!urlDisplay && !urlLabel && !buttonStyle) {
			return;
		}

		var options = cardLayoutOptions(config);
		if (urlDisplay) {
			options.urlDisplay = ['link', 'button'].indexOf(urlDisplay.value) >= 0 ? urlDisplay.value : 'link';
		}
		if (urlLabel) {
			options.urlLabel = urlLabel.value.trim();
		}
		if (buttonStyle) {
			options.buttonStyle = ['theme', 'primary', 'secondary', 'outline'].indexOf(buttonStyle.value) >= 0 ? buttonStyle.value : 'theme';
		}
	}

	function collectCustomize() {
		var search = root.querySelector('#show-search');
		var filters = root.querySelector('#show-filters');
		var pagination = root.querySelector('#show-pagination');
		var perPage = root.querySelector('#per-page');
		var displayWidth = root.querySelector('input[name="display-width"]:checked');

		state.config.style = state.config.style || {};

		if (search) {
			state.config.search.enabled = search.checked;
		}
		if (filters) {
			state.config.filters.enabled = filters.checked;
		}
		if (pagination) {
			state.config.pagination.enabled = pagination.checked;
		}
		if (perPage) {
			state.config.pagination.perPage = parseInt(perPage.value, 10) || 12;
		}
		if (displayWidth) {
			state.config.style.displayWidth = displayWidth.value;
		}
		collectCardFieldDisplayOptions(state.config, 'card');
	}

	function saveDisplay() {
		collectCustomizeIfPresent();
		state.loading = true;
		render();
		var payload = {
			sourceId: state.source.id,
			name: state.config.title,
			displayType: state.displayType,
			config: state.config,
			status: 'published'
		};
		var path = state.display && state.display.id ? '/displays/' + state.display.id : '/displays';
		var method = state.display && state.display.id ? 'PUT' : 'POST';
		request(path, { method: method, body: payload })
			.then(function (display) {
				state.display = display;
				state.loading = false;
				state.loadedDisplays = false;
				setScreen('success');
			})
			.catch(function (error) {
				state.loading = false;
				setError(error);
			});
	}

	function collectCustomizeIfPresent() {
		if (root.querySelector('#show-search')) {
			collectCustomize();
		}
	}

	function renderSuccess() {
		var id = state.display ? state.display.id : 0;
		return [
			'<section class="tabloria-success">',
			'<div class="tabloria-success__icon">OK</div>',
			'<div>',
			'<h2>Your Display is ready.</h2>',
			'<p class="tabloria-muted">"' + escapeHtml(state.config.title) + '" has been saved as a reusable Published Display. Insert it in Gutenberg or use the shortcode fallback.</p>',
			'<p><code>[tabloria id="' + escapeHtml(id) + '"]</code></p>',
			'</div>',
			'<div class="tabloria-actions" style="margin-top:0;justify-content:flex-end">',
			'<button class="tabloria-button is-primary" data-copy-shortcode>Copy shortcode</button>',
			'<button class="tabloria-button" data-edit>Edit Display</button>',
			'<button class="tabloria-button" data-list>Published Displays</button>',
			'</div>',
			'</section>',
			'<section class="tabloria-preview" style="margin-top:28px">',
			'<div class="tabloria-preview__bar">Saved Display preview</div>',
			'<div class="tabloria-preview__body">' + renderMiniPreview(true) + '</div>',
			'</section>'
		].join('');
	}

	function bindSuccess() {
		root.querySelector('[data-copy-shortcode]').addEventListener('click', function (event) {
			copyText('[tabloria id="' + state.display.id + '"]', event.currentTarget);
		});
		root.querySelector('[data-edit]').addEventListener('click', function () {
			openEditor(state.display);
		});
		root.querySelector('[data-list]').addEventListener('click', function () {
			setScreen('displays');
		});
	}

	function renderDisplays() {
		if (!state.loadedDisplays) {
			return '<div class="tabloria-loading">Loading Published Displays...</div>';
		}

		return [
			'<section class="tabloria-hero">',
			'<p class="tabloria-kicker">Reusable Displays</p>',
			'<h1>Published Displays</h1>',
			'<p class="tabloria-subtitle">Manage the spreadsheet displays you can insert into WordPress pages.</p>',
			'<div class="tabloria-actions"><button class="tabloria-button is-primary" data-create>Create Display</button></div>',
			'</section>',
			state.displays.length ? '<section class="tabloria-display-cards">' + state.displays.map(renderDisplayCard).join('') + '</section>' : renderEmptyDisplays()
		].join('');
	}

	function renderDisplayCard(display) {
		var source = display.source || {};
		var displayType = display.display_type || (display.config && display.config.displayType) || 'searchable_listings';
		return [
			'<article class="tabloria-display-card" data-display-id="' + escapeHtml(display.id) + '" tabindex="0" aria-label="Edit ' + escapeHtml(display.name || 'Published Display') + '">',
			'<h3>' + escapeHtml(display.name) + '</h3>',
			renderTypePreview(displayType),
			'<div class="tabloria-display-card__meta">',
			'<span>Type: ' + escapeHtml(labelForType(displayType)) + '</span>',
			'<span>Rows: ' + escapeHtml(source.row_count || 0) + '</span>',
			'<span>Source: ' + escapeHtml(source.original_filename || 'Spreadsheet') + '</span>',
			'<span>Updated: ' + escapeHtml(display.updated_at || '') + '</span>',
			'</div>',
			'<div class="tabloria-display-card__actions">',
			'<button class="tabloria-button" data-edit="' + escapeHtml(display.id) + '">Edit</button>',
			'<button class="tabloria-button" data-copy="' + escapeHtml(display.id) + '">Copy shortcode</button>',
			'<button class="tabloria-button is-danger" data-delete="' + escapeHtml(display.id) + '">Delete</button>',
			'</div>',
			'</article>'
		].join('');
	}

	function renderEmptyDisplays() {
		return '<section class="tabloria-empty"><h2>No displays yet.</h2><p class="tabloria-muted">Upload a spreadsheet to create your first searchable page.</p><div class="tabloria-actions" style="justify-content:center"><button class="tabloria-button is-primary" data-create>Create Display</button></div></section>';
	}

	function bindDisplays() {
		root.querySelectorAll('[data-create]').forEach(function (button) {
			button.addEventListener('click', function () {
				state.display = null;
				state.source = null;
				state.sample = [];
				state.config = null;
				setScreen('welcome');
			});
		});
		root.querySelectorAll('.tabloria-display-card[data-display-id]').forEach(function (card) {
			card.addEventListener('click', function (event) {
				if (event.target.closest('.tabloria-display-card__actions')) {
					return;
				}
				openDisplayFromCard(card);
			});
			card.addEventListener('keydown', function (event) {
				if (event.target !== card || (event.key !== 'Enter' && event.key !== ' ')) {
					return;
				}
				event.preventDefault();
				openDisplayFromCard(card);
			});
		});
		root.querySelectorAll('[data-copy]').forEach(function (button) {
			button.addEventListener('click', function (event) {
				copyText('[tabloria id="' + button.dataset.copy + '"]', event.currentTarget);
			});
		});
		root.querySelectorAll('[data-edit]').forEach(function (button) {
			button.addEventListener('click', function () {
				var display = findDisplay(button.dataset.edit);
				if (!display) {
					return;
				}
				openEditor(display);
			});
		});
		root.querySelectorAll('[data-delete]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (!window.confirm('Delete this Published Display? The source spreadsheet data will stay in Tabloria.')) {
					return;
				}
				request('/displays/' + button.dataset.delete, { method: 'DELETE' })
					.then(function () {
						state.loadedDisplays = false;
						loadDisplays();
					})
				.catch(setError);
			});
		});
	}

	function openDisplayFromCard(card) {
		var display = findDisplay(card.dataset.displayId);
		if (!display) {
			return;
		}
		openEditor(display);
	}

	function loadDisplays() {
		state.loading = true;
		render();
		request('/displays')
			.then(function (displays) {
				state.displays = displays || [];
				state.loadedDisplays = true;
				state.loading = false;
				render();
			})
			.catch(function (error) {
				state.loading = false;
				setError(error);
			});
	}

	function openEditor(display) {
		if (!display) {
			return;
		}

		var displayType = display.display_type || (display.config && display.config.displayType) || state.displayType;
		state.error = '';
		state.notice = '';
		state.loading = true;
		state.screen = 'editor';
		state.editorTab = 'preview';
		state.updateSummary = null;
		state.display = display;
		state.displayType = displayType;
		state.config = cloneConfig(display.config || state.config || {});
		state.config.displayType = state.config.displayType || displayType;
		state.source = display.source || state.source;
		render();

		var sourceId = display.source_id || (display.source && display.source.id) || (state.source && state.source.id);
		if (!sourceId) {
			state.loading = false;
			render();
			return;
		}

		request('/sources/' + sourceId)
			.then(function (response) {
				state.source = response.source || state.source;
				state.sample = response.sample || [];
				state.loading = false;
				render();
			})
			.catch(function (error) {
				state.loading = false;
				setError(error);
			});
	}

	function cloneConfig(config) {
		try {
			return JSON.parse(JSON.stringify(config || {}));
		} catch (error) {
			return {};
		}
	}

	function ensureConfig() {
		var config = state.config || generateConfig(state.source, state.displayType);
		config.displayType = config.displayType || state.displayType || 'searchable_listings';
		config.title = config.title || (state.display && state.display.name) || titleFromSource(state.source);
		config.sourceId = config.sourceId || (state.source ? state.source.id : 0);
		config.fieldRoles = config.fieldRoles || {};
		config.visibleFields = Array.isArray(config.visibleFields) ? config.visibleFields : [];
		config.defaultFilters = Array.isArray(config.defaultFilters) ? config.defaultFilters : [];
		config.visitorFilters = Array.isArray(config.visitorFilters) ? config.visitorFilters : [];
		config.sort = config.sort || { field: '', direction: 'asc' };
		config.search = config.search || { enabled: true };
		config.filters = config.filters || { enabled: true };
		config.pagination = config.pagination || { enabled: true, perPage: 12 };
		config.style = config.style || { accentColor: '#3525cd', density: 'comfortable', imageShape: 'rounded' };
		ensureLayoutOptions(config);
		state.config = config;
		return config;
	}

	function renderEditor() {
		if (!state.display || !state.source) {
			return renderMissingSource();
		}

		var config = ensureConfig();
		var source = state.source || {};
		var activeTab = state.editorTab || 'preview';
		var showSidePreview = activeTab !== 'preview';
		var tabs = [
			['preview', 'Preview'],
			['fields', 'Fields'],
			['filters', 'Search & Filters'],
			['source', 'Data Source'],
			['publish', 'Publish']
		];

		return [
			'<section class="tabloria-editor-header">',
			'<div>',
			'<p class="tabloria-kicker">Display Editor</p>',
			'<h1>Edit Display</h1>',
			'<p class="tabloria-subtitle">' + escapeHtml(config.title || state.display.name || 'Published Display') + ' from ' + escapeHtml(source.original_filename || 'Spreadsheet') + '</p>',
			'</div>',
			'<div class="tabloria-editor-actions">',
			'<button class="tabloria-button" data-editor-list>Published Displays</button>',
			'<button class="tabloria-button is-primary" data-editor-save>Save changes</button>',
			'</div>',
			'</section>',
			'<div class="tabloria-editor-layout' + (!showSidePreview ? ' is-preview-tab' : '') + '">',
			'<aside class="tabloria-editor-tabs" aria-label="Display editor sections">',
			tabs.map(function (tab) {
				var active = activeTab === tab[0] ? ' is-active' : '';
				return '<button type="button" class="' + active + '" data-editor-tab="' + escapeHtml(tab[0]) + '">' + escapeHtml(tab[1]) + '</button>';
			}).join(''),
			'</aside>',
			'<section class="tabloria-editor-main">',
			renderEditorTab(activeTab),
			'</section>',
			showSidePreview ? [
			'<aside class="tabloria-editor-side">',
			'<div class="tabloria-preview">',
			'<div class="tabloria-preview__bar">Live preview</div>',
			'<div class="tabloria-preview__body">' + renderMiniPreview(true) + '</div>',
			'</div>',
			'</aside>'
			].join('') : '',
			'</div>'
		].join('');
	}

	function renderEditorTab(tab) {
		if (tab === 'fields') {
			return renderEditorFields();
		}
		if (tab === 'filters') {
			return renderEditorFilters();
		}
		if (tab === 'source') {
			return renderEditorDataSource();
		}
		if (tab === 'publish') {
			return renderEditorPublish();
		}
		return renderEditorPreview();
	}

	function renderEditorPreview() {
		return [
			'<div class="tabloria-editor-section">',
			'<h2>Preview</h2>',
			'<p class="tabloria-muted">This preview uses the first rows from the current source. The saved Display renders the full dataset on the front end.</p>',
			'<div class="tabloria-preview" style="margin-top:18px">',
			'<div class="tabloria-preview__bar">yourwebsite.com/' + escapeHtml(slugify(state.config.title || 'tabloria-display')) + '</div>',
			'<div class="tabloria-preview__body">' + renderMiniPreview(true) + '</div>',
			'</div>',
			'</div>'
		].join('');
	}

	function renderEditorFields() {
		var config = ensureConfig();
		var roles = roleDefinitions();

		return [
			'<div class="tabloria-editor-section">',
			'<h2>Display Content</h2>',
			'<p class="tabloria-muted">Pick the Display type and columns. The live preview updates from the current spreadsheet sample.</p>',
			'<div class="tabloria-form-grid tabloria-form-grid--compact" style="margin-top:18px">',
			'<div class="tabloria-field"><label for="editor-display-name">Display name</label><input id="editor-display-name" class="tabloria-input" value="' + escapeHtml(config.title || '') + '"></div>',
			'<div class="tabloria-field"><label for="editor-display-type">Display type</label>' + renderDisplayTypeSelect('editor-display-type', currentDisplayType()) + '</div>',
			'</div>',
			'<div class="tabloria-editor-stack">',
			renderContentMapper(roles, config, 'editor-role-', 'editor-visible-field', false),
			renderCardFieldDisplayOptions('editor-card', config),
			'</div>',
			'</div>'
		].join('');
	}

	function renderDisplayTypeSelect(id, current) {
		return [
			'<select id="' + escapeHtml(id) + '" class="tabloria-select">',
			displayTypes.map(function (type) {
				return '<option value="' + escapeHtml(type.key) + '"' + selected(current, type.key) + '>' + escapeHtml(type.label) + '</option>';
			}).join(''),
			'</select>'
		].join('');
	}

	function renderEditorFilters() {
		var config = ensureConfig();
		var columns = state.source.columns || [];
		var filter = (config.defaultFilters || [])[0] || { field: '', operator: 'equals', value: '' };

		return [
			'<div class="tabloria-editor-section">',
			'<h2>Search & Filters</h2>',
			'<p class="tabloria-muted">Control what visitors can search, filter, sort, and page through.</p>',
			'<div class="tabloria-editor-stack">',
			'<section class="tabloria-editor-subsection">',
			'<h3>Components</h3>',
			renderDisplayWidthOptions('editor-display-width', config),
			renderToggle('editor-show-search', 'Show search', config.search.enabled),
			renderToggle('editor-show-filters', 'Show filters', config.filters.enabled),
			renderToggle('editor-show-pagination', 'Show pagination', config.pagination.enabled),
			'<div class="tabloria-field" style="margin-top:14px"><label for="editor-per-page">Items per page</label><select id="editor-per-page" class="tabloria-select"' + (!config.pagination.enabled ? ' disabled' : '') + '><option value="6"' + selected(config.pagination.perPage, 6) + '>6 items</option><option value="9"' + selected(config.pagination.perPage, 9) + '>9 items</option><option value="12"' + selected(config.pagination.perPage, 12) + '>12 items</option><option value="24"' + selected(config.pagination.perPage, 24) + '>24 items</option></select></div>',
			'</section>',
			'<section class="tabloria-editor-subsection">',
			'<h3>Default Filter</h3>',
			'<div class="tabloria-filter-builder">',
			renderColumnSelect('editor-default-filter-field', filter.field || '', true),
			renderFilterOperatorSelect('editor-default-filter-operator', filter.operator || 'equals'),
			'<input id="editor-default-filter-value" class="tabloria-input" placeholder="Value, for example Available" value="' + escapeHtml(filter.value || '') + '">',
			'</div>',
			'</section>',
			'<section class="tabloria-editor-subsection">',
			'<h3>Visitor Filters</h3>',
			columns.map(function (column) {
				var checked = (config.visitorFilters || []).indexOf(column.key) >= 0 ? ' checked' : '';
				return '<label class="tabloria-check-row"><input type="checkbox" name="editor-visitor-filter" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
			}).join('') || '<p class="tabloria-muted">No columns found in this source.</p>',
			'</section>',
			'<section class="tabloria-editor-subsection">',
			'<h3>Default Sort</h3>',
			'<div class="tabloria-filter-builder">' + renderColumnSelect('editor-sort-field', config.sort.field || '', true) + '<select class="tabloria-select" id="editor-sort-direction"><option value="asc"' + selected(config.sort.direction, 'asc') + '>Low to high</option><option value="desc"' + selected(config.sort.direction, 'desc') + '>High to low</option></select><span></span></div>',
			'</section>',
			'</div>',
			'</div>'
		].join('');
	}

	function renderEditorDataSource() {
		var source = state.source || {};
		var columns = source.columns || [];
		var summary = state.updateSummary;

		return [
			'<div class="tabloria-editor-section">',
			'<h2>Data Source</h2>',
			'<p class="tabloria-muted">Replace the CSV when the spreadsheet changes. Tabloria keeps the same Display ID and shows how columns matched.</p>',
			'<div class="tabloria-source-grid">',
			renderSourceStat('File', source.original_filename || 'Spreadsheet'),
			renderSourceStat('Rows', source.row_count || 0),
			renderSourceStat('Columns', columns.length),
			renderSourceStat('Updated', source.updated_at || source.created_at || ''),
			'</div>',
			'<div class="tabloria-actions">',
			'<label class="tabloria-button is-primary">Replace CSV<input type="file" accept=".csv,text/csv" data-editor-update hidden></label>',
			'</div>',
			summary ? renderUpdateSummary(summary) : '',
			'<section class="tabloria-editor-subsection" style="margin-top:24px">',
			'<h3>Current Sample</h3>',
			renderTable((state.sample || []).slice(0, 5), columns),
			'</section>',
			'</div>'
		].join('');
	}

	function renderSourceStat(label, value) {
		return '<div class="tabloria-source-stat"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
	}

	function renderUpdateSummary(summary) {
		return [
			'<section class="tabloria-update-summary">',
			'<h3>Update Summary</h3>',
			'<div class="tabloria-match-grid">',
			renderMatchCard('Matched', summary.matchedCount || 0, (summary.matched || []).map(function (pair) { return pair.old && pair.new ? pair.old.label + ' -> ' + pair.new.label : ''; })),
			renderMatchCard('New', summary.newCount || 0, (summary.new || []).map(function (column) { return column.label || column.key; })),
			renderMatchCard('Missing', summary.missingCount || 0, (summary.missing || []).map(function (column) { return column.label || column.key; })),
			'</div>',
			'</section>'
		].join('');
	}

	function renderMatchCard(label, count, items) {
		items = (items || []).filter(Boolean).slice(0, 5);
		return [
			'<article class="tabloria-match-card">',
			'<span>' + escapeHtml(label) + '</span>',
			'<strong>' + escapeHtml(count) + '</strong>',
			items.length ? '<ul>' + items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>' : '<p class="tabloria-muted">No column changes.</p>',
			'</article>'
		].join('');
	}

	function renderEditorPublish() {
		var id = state.display ? state.display.id : 0;
		var shortcode = '[tabloria id="' + id + '"]';

		return [
			'<div class="tabloria-editor-section">',
			'<h2>Publish</h2>',
			'<p class="tabloria-muted">Use the Tabloria Display block in Gutenberg, or paste the shortcode anywhere shortcodes are supported.</p>',
			'<div class="tabloria-copy-box">',
			'<code>' + escapeHtml(shortcode) + '</code>',
			'<button type="button" class="tabloria-button is-primary" data-editor-copy>Copy shortcode</button>',
			'</div>',
			'<div class="tabloria-source-grid" style="margin-top:20px">',
			renderSourceStat('Display ID', id),
			renderSourceStat('Status', state.display.status || 'published'),
			renderSourceStat('Type', labelForType(currentDisplayType())),
			renderSourceStat('Last saved', state.display.updated_at || ''),
			'</div>',
			'</div>'
		].join('');
	}

	function bindEditor() {
		root.querySelectorAll('[data-editor-tab]').forEach(function (button) {
			button.addEventListener('click', function () {
				collectEditor();
				state.notice = '';
				state.editorTab = button.dataset.editorTab;
				render();
			});
		});

		var saveButton = root.querySelector('[data-editor-save]');
		if (saveButton) {
			saveButton.addEventListener('click', saveEditor);
		}

		var listButton = root.querySelector('[data-editor-list]');
		if (listButton) {
			listButton.addEventListener('click', function () {
				collectEditor();
				setScreen('displays');
			});
		}

		var copyButton = root.querySelector('[data-editor-copy]');
		if (copyButton && state.display) {
			copyButton.addEventListener('click', function (event) {
				copyText('[tabloria id="' + state.display.id + '"]', event.currentTarget);
			});
		}

		var typeSelect = root.querySelector('#editor-display-type');
		if (typeSelect) {
			typeSelect.addEventListener('change', function () {
				switchEditorDisplayType(typeSelect.value);
			});
		}

		root.querySelectorAll('.tabloria-editor-main input, .tabloria-editor-main select').forEach(function (control) {
			if (control.matches('[data-editor-update], #editor-display-type')) {
				return;
			}
			control.addEventListener('change', function () {
				collectEditor();
				state.notice = '';
				render();
			});
		});

		var updateInput = root.querySelector('[data-editor-update]');
		if (updateInput) {
			updateInput.addEventListener('change', function () {
				updateEditorData(updateInput);
			});
		}
	}

	function collectEditor() {
		var config = ensureConfig();
		var displayName = root.querySelector('#editor-display-name');
		if (displayName) {
			config.title = displayName.value.trim() || 'Published Display';
		}

		if (root.querySelector('#editor-role-title')) {
			config.fieldRoles = {
				title: valueOf('#editor-role-title'),
				image: valueOf('#editor-role-image'),
				price: valueOf('#editor-role-price'),
				badge: valueOf('#editor-role-badge'),
				subtitle: valueOf('#editor-role-subtitle')
			};
		}

		if (root.querySelector('input[name="editor-visible-field"]')) {
			config.visibleFields = checkedValues('editor-visible-field');
		}

		collectCardFieldDisplayOptions(config, 'editor-card');

		if (root.querySelector('#editor-show-search')) {
			config.style = config.style || {};
			config.search.enabled = root.querySelector('#editor-show-search').checked;
			config.filters.enabled = root.querySelector('#editor-show-filters').checked;
			config.pagination.enabled = root.querySelector('#editor-show-pagination').checked;
			config.pagination.perPage = parseInt(valueOf('#editor-per-page'), 10) || 12;
			config.style.displayWidth = checkedRadioValue('editor-display-width') || 'normal';
		}

		if (root.querySelector('#editor-default-filter-field')) {
			var filterField = valueOf('#editor-default-filter-field');
			var filterValue = valueOf('#editor-default-filter-value');
			var filterOperator = valueOf('#editor-default-filter-operator') || 'equals';
			config.defaultFilters = filterField || filterValue || filterOperator !== 'equals' ? [{
				field: filterField,
				operator: filterOperator,
				value: filterValue
			}] : [];
			config.visitorFilters = checkedValues('editor-visitor-filter');
			config.sort = {
				field: valueOf('#editor-sort-field'),
				direction: valueOf('#editor-sort-direction') || 'asc'
			};
		}

		state.config = config;
	}

	function switchEditorDisplayType(displayType) {
		if (!displayTypes.some(function (type) { return type.key === displayType; }) || displayType === currentDisplayType()) {
			return;
		}

		collectEditor();
		state.displayType = displayType;
		state.config = rebuildConfigForDisplayType(displayType, cloneConfig(state.config));
		state.notice = 'Display type changed. Review fields before saving.';
		render();
	}

	function rebuildConfigForDisplayType(displayType, previous) {
		previous = previous || {};
		var next = generateConfig(state.source, displayType);
		next.title = previous.title || next.title;
		next.sourceId = previous.sourceId || next.sourceId;
		next.visibleFields = previous.visibleFields && previous.visibleFields.length ? previous.visibleFields : next.visibleFields;
		next.defaultFilters = previous.defaultFilters || [];
		next.visitorFilters = previous.visitorFilters && previous.visitorFilters.length ? previous.visitorFilters : next.visitorFilters;
		next.sort = previous.sort || next.sort;
		next.search = previous.search || next.search;
		next.filters = previous.filters || next.filters;
		next.pagination = previous.pagination || next.pagination;
		next.style = previous.style || next.style;
		next.layoutOptions = previous.layoutOptions || next.layoutOptions;
		ensureLayoutOptions(next);
		next.displayType = displayType;
		return next;
	}

	function checkedRadioValue(name) {
		var input = root.querySelector('input[name="' + name + '"]:checked');
		return input ? input.value : '';
	}

	function saveEditor() {
		if (!state.display) {
			return;
		}

		collectEditor();
		state.loading = true;
		state.notice = '';
		render();

		request('/displays/' + state.display.id, {
			method: 'PUT',
			body: {
				name: state.config.title,
				displayType: currentDisplayType(),
				config: state.config,
				status: state.display.status || 'published'
			}
		}).then(function (display) {
			state.display = display;
			state.displayType = display.display_type || (display.config && display.config.displayType) || state.displayType;
			state.config = cloneConfig(display.config || state.config);
			state.config.displayType = state.config.displayType || state.displayType;
			state.source = display.source || state.source;
			state.loadedDisplays = false;
			state.loading = false;
			state.notice = 'Display changes saved.';
			render();
		}).catch(function (error) {
			state.loading = false;
			setError(error);
		});
	}

	function updateEditorData(input) {
		if (!state.display || !input.files || !input.files[0]) {
			return;
		}

		var data = new FormData();
		data.append('file', input.files[0]);
		state.loading = true;
		state.notice = '';
		render();

		request('/displays/' + state.display.id + '/update-data', { method: 'POST', body: data })
			.then(function (response) {
				state.source = response.source || state.source;
				state.sample = response.sample || [];
				state.updateSummary = response.summary || null;
				state.loadedDisplays = false;
				state.loading = false;
				state.editorTab = 'source';
				state.notice = 'CSV data updated. Review the column summary before saving field changes.';
				render();
			})
			.catch(function (error) {
				state.loading = false;
				setError(error);
			});
	}

	function renderSettings() {
		var settings = state.settings || {
			defaultAccentColor: '#3525cd',
			defaultRowsPerPage: 12,
			deleteDataOnUninstall: false
		};
		return [
			'<section class="tabloria-hero">',
			'<p class="tabloria-kicker">Defaults</p>',
			'<h1>Settings</h1>',
			'<p class="tabloria-subtitle">Keep MVP settings small so Tabloria stays focused on publishing Displays.</p>',
			'</section>',
			'<section class="tabloria-panel">',
			'<div class="tabloria-form-grid">',
			'<div class="tabloria-field"><label for="setting-accent">Default accent color</label><input id="setting-accent" class="tabloria-input" type="color" value="' + escapeHtml(settings.defaultAccentColor || '#3525cd') + '"></div>',
			'<div class="tabloria-field"><label for="setting-rows">Default rows per page</label><select id="setting-rows" class="tabloria-select"><option value="12"' + selected(settings.defaultRowsPerPage, 12) + '>12</option><option value="24"' + selected(settings.defaultRowsPerPage, 24) + '>24</option><option value="48"' + selected(settings.defaultRowsPerPage, 48) + '>48</option></select></div>',
			'</div>',
			'<label class="tabloria-check-row" style="margin-top:18px"><input id="setting-delete" type="checkbox"' + (settings.deleteDataOnUninstall ? ' checked' : '') + '><span>Delete plugin data on uninstall</span></label>',
			'<div class="tabloria-actions"><button class="tabloria-button is-primary" data-settings-save>Save Settings</button></div>',
			'</section>'
		].join('');
	}

	function bindSettings() {
		root.querySelector('[data-settings-save]').addEventListener('click', function (event) {
			var button = event.currentTarget;
			button.disabled = true;
			button.textContent = 'Saving...';
			request('/settings', {
				method: 'PUT',
				body: {
					defaultAccentColor: valueOf('#setting-accent'),
					defaultRowsPerPage: parseInt(valueOf('#setting-rows'), 10) || 12,
					deleteDataOnUninstall: root.querySelector('#setting-delete').checked
				}
			}).then(function (settings) {
				state.settings = settings;
				button.disabled = false;
				button.textContent = 'Saved';
				window.setTimeout(render, 900);
			}).catch(function (error) {
				button.disabled = false;
				setError(error);
			});
		});
	}

	function loadSettings() {
		state.loading = true;
		render();
		request('/settings')
			.then(function (settings) {
				state.settings = settings;
				state.loading = false;
				render();
			})
			.catch(function (error) {
				state.loading = false;
				setError(error);
			});
	}

	function renderTable(rows, columns) {
		rows = rows || [];
		columns = columns || [];
		if (!rows.length) {
			return '<p class="tabloria-muted">No rows found in this spreadsheet.</p>';
		}

		return [
			'<div class="tabloria-table-wrap"><table class="tabloria-table"><thead><tr>',
			columns.map(function (column) {
				return '<th>' + escapeHtml(column.label) + '</th>';
			}).join(''),
			'</tr></thead><tbody>',
			rows.map(function (row) {
				return '<tr>' + columns.map(function (column) {
					return '<td>' + renderMiniTableCell(row, column) + '</td>';
				}).join('') + '</tr>';
			}).join(''),
			'</tbody></table></div>'
		].join('');
	}

	function renderMiniPreview(full) {
		var rawRows = state.sample && state.sample.length ? state.sample : sampleRowsFromConfig();
		var rows = preparePreviewRows(rawRows);
		var roles = state.config.fieldRoles || {};
		var title = state.config.title || 'Available Apartments';
		var accent = state.config.style && state.config.style.accentColor ? state.config.style.accentColor : '#3525cd';
		var density = state.config.style && state.config.style.density === 'compact' ? 'is-compact' : 'is-comfortable';
		var imageShape = state.config.style && state.config.style.imageShape === 'square' ? 'is-square' : 'is-rounded';
		var displayType = currentDisplayType();
		var sourceRows = state.source && state.source.row_count ? parseInt(state.source.row_count, 10) : rawRows.length;
		var totalRows = hasActiveDefaultFilter() ? rows.length : sourceRows;
		var perPage = state.config.pagination && state.config.pagination.perPage ? parseInt(state.config.pagination.perPage, 10) : 12;
		var previewCount = Math.min(rows.length, full ? perPage : 3);
		if (displayType === 'simple_data_table') {
			return renderMiniTablePreview(rows, sourceRows, totalRows, perPage, previewCount);
		}
		return [
			'<div class="tabloria-mini-preview tabloria-mini-preview--' + escapeHtml(displayType.replace(/_/g, '-')) + ' ' + density + ' ' + imageShape + '" style="--accent:' + escapeHtml(accent) + '">',
			renderPreviewNote(sourceRows),
			'<h2>' + escapeHtml(title) + '</h2>',
			renderMiniControls(),
			renderMiniRowsByType(displayType, rows.slice(0, previewCount), roles, accent),
			renderMiniPagination(totalRows, perPage, previewCount),
			'</div>'
		].join('');
	}

	function renderMiniRowsByType(displayType, rows, roles, accent) {
		if (!rows.length) {
			return '<p class="tabloria-muted tabloria-mini-empty">No sample rows match the current default filter.</p>';
		}

		if (displayType === 'event_schedule') {
			return '<div class="tabloria-mini-schedule-list">' + rows.map(function (row) {
				return renderMiniScheduleItem(row, roles);
			}).join('') + '</div>';
		}

		if (displayType === 'pricing_table') {
			return '<div class="tabloria-mini-pricing-grid">' + rows.map(function (row) {
				return renderMiniPricingItem(row, roles, accent);
			}).join('') + '</div>';
		}

		if (displayType === 'team_directory') {
			return '<div class="tabloria-mini-profile-grid">' + rows.map(function (row) {
				return renderMiniProfileItem(row, roles);
			}).join('') + '</div>';
		}

		if (displayType === 'product_catalog') {
			return '<div class="tabloria-mini-grid tabloria-mini-grid--products">' + rows.map(function (row) {
				return renderMiniProductItem(row, roles, accent);
			}).join('') + '</div>';
		}

		return '<div class="tabloria-mini-list">' + rows.map(function (row) {
			return renderMiniListingItem(row, roles, accent);
		}).join('') + '</div>';
	}

	function miniParts(row, roles) {
		var title = valueForExample(row, roles.title) || bestRowTitle(row) || 'Display item';
		var fieldParts = miniFieldParts(row, roles);
		return {
			title: title,
			subtitle: valueForExample(row, roles.subtitle) || bestRowSubtitle(row, title) || '',
			image: valueForExample(row, roles.image),
			highlight: valueForExample(row, roles.price),
			badge: valueForExample(row, roles.badge),
			details: renderMiniFields(fieldParts.fields),
			actions: renderMiniActions(fieldParts.actions)
		};
	}

	function renderMiniProductItem(row, roles, accent) {
		var part = miniParts(row, roles);
		return [
			'<article class="tabloria-mini-card tabloria-mini-product-card">',
			renderMiniImage(part.image),
			'<div class="tabloria-mini-card__body">',
			part.badge ? '<span class="tabloria-mini-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.highlight ? '<strong style="color:' + escapeHtml(accent) + '">' + escapeHtml(part.highlight) + '</strong>' : '',
			part.details,
			part.actions,
			'</div>',
			'</article>'
		].join('');
	}

	function renderMiniListingItem(row, roles, accent) {
		var part = miniParts(row, roles);
		return [
			'<article class="tabloria-mini-listing">',
			renderMiniImage(part.image),
			'<div class="tabloria-mini-listing__body">',
			part.badge ? '<span class="tabloria-mini-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.highlight ? '<strong style="color:' + escapeHtml(accent) + '">' + escapeHtml(part.highlight) + '</strong>' : '',
			part.details,
			part.actions,
			'</div>',
			'</article>'
		].join('');
	}

	function renderMiniProfileItem(row, roles) {
		var part = miniParts(row, roles);
		return [
			'<article class="tabloria-mini-profile-card">',
			renderMiniImage(part.image),
			'<div class="tabloria-mini-card__body">',
			part.badge ? '<span class="tabloria-mini-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.details,
			part.actions,
			'</div>',
			'</article>'
		].join('');
	}

	function renderMiniScheduleItem(row, roles) {
		var part = miniParts(row, roles);
		return [
			'<article class="tabloria-mini-schedule-item">',
			'<div class="tabloria-mini-schedule-item__time"><span>Date / time</span><strong>' + escapeHtml(part.highlight || 'Not set') + '</strong></div>',
			'<div class="tabloria-mini-schedule-item__body">',
			part.badge ? '<span class="tabloria-mini-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.subtitle ? '<p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.details,
			part.actions,
			'</div>',
			'</article>'
		].join('');
	}

	function renderMiniPricingItem(row, roles, accent) {
		var part = miniParts(row, roles);
		return [
			'<article class="tabloria-mini-price-card">',
			part.badge ? '<span class="tabloria-mini-badge">' + escapeHtml(part.badge) + '</span>' : '',
			'<h4>' + escapeHtml(part.title) + '</h4>',
			part.highlight ? '<strong style="color:' + escapeHtml(accent) + '">' + escapeHtml(part.highlight) + '</strong>' : '',
			part.subtitle ? '<p>' + escapeHtml(part.subtitle) + '</p>' : '',
			part.details,
			part.actions,
			'</article>'
		].join('');
	}

	function renderMiniTablePreview(rows, sourceRows, totalRows, perPage, previewCount) {
		return [
			'<div class="tabloria-mini-preview tabloria-mini-preview--simple-data-table">',
			renderPreviewNote(sourceRows),
			'<h2>' + escapeHtml(state.config.title || 'Published Table') + '</h2>',
			renderMiniControls(),
			renderMappingTableExample(state.config, rows, previewCount),
			renderMiniPagination(totalRows, perPage, previewCount),
			'</div>'
		].join('');
	}

	function renderPreviewNote(sourceRows) {
		if (hasActiveDefaultFilter()) {
			return '<p class="tabloria-preview-note">Preview sample after the default filter. The saved Display applies this filter to all ' + escapeHtml(sourceRows) + ' source rows.</p>';
		}

		return '<p class="tabloria-preview-note">Preview sample. The saved Display will render all ' + escapeHtml(sourceRows) + ' rows.</p>';
	}

	function preparePreviewRows(rows) {
		var prepared = (rows || []).filter(function (row) {
			return (state.config.defaultFilters || []).every(function (filter) {
				return rowMatchesDefaultFilter(row, filter);
			});
		});
		var sort = state.config.sort || {};
		if (sort.field) {
			prepared = prepared.slice().sort(function (a, b) {
				return comparePreviewRows(a, b, sort.field, sort.direction);
			});
		}

		return prepared;
	}

	function rowMatchesDefaultFilter(row, filter) {
		var field = filter && filter.field ? filter.field : '';
		var value = filter && filter.value !== undefined ? String(filter.value) : '';
		if (!field || value === '') {
			return true;
		}

		var rowValue = row && row[field] !== undefined && row[field] !== null ? String(row[field]) : '';
		var rowLower = rowValue.toLowerCase();
		var valueLower = value.toLowerCase();
		var operator = filter.operator || 'equals';

		if (operator === 'contains') {
			return rowLower.indexOf(valueLower) >= 0;
		}
		if (operator === 'not_equals') {
			return rowLower !== valueLower;
		}
		if (operator === 'less_than' || operator === 'greater_than') {
			var rowNumber = numericPreviewValue(rowValue);
			var filterNumber = numericPreviewValue(value);
			if (rowNumber === null || filterNumber === null) {
				return false;
			}
			return operator === 'less_than' ? rowNumber < filterNumber : rowNumber > filterNumber;
		}

		return rowLower === valueLower;
	}

	function comparePreviewRows(a, b, field, direction) {
		var multiplier = direction === 'desc' ? -1 : 1;
		var aValue = a && a[field] !== undefined && a[field] !== null ? String(a[field]) : '';
		var bValue = b && b[field] !== undefined && b[field] !== null ? String(b[field]) : '';
		var aNumber = numericPreviewValue(aValue);
		var bNumber = numericPreviewValue(bValue);

		if (aNumber !== null && bNumber !== null) {
			return multiplier * (aNumber - bNumber);
		}

		return multiplier * aValue.localeCompare(bValue, undefined, { numeric: true, sensitivity: 'base' });
	}

	function numericPreviewValue(value) {
		var raw = String(value || '').replace(/[^0-9.-]/g, '');
		if (!raw || raw === '-' || raw === '.' || raw === '-.') {
			return null;
		}
		var number = Number(raw);
		return Number.isNaN(number) ? null : number;
	}

	function hasActiveDefaultFilter() {
		return (state.config.defaultFilters || []).some(function (filter) {
			return filter && filter.field && filter.value !== undefined && String(filter.value) !== '';
		});
	}

	function renderMiniControls() {
		var parts = [];
		if (state.config.search && state.config.search.enabled) {
			parts.push('<input class="tabloria-input" placeholder="Search all fields..." />');
		}
		if (state.config.filters && state.config.filters.enabled && state.config.visitorFilters && state.config.visitorFilters.length) {
			parts.push('<div class="tabloria-mini-filters">' + state.config.visitorFilters.slice(0, 3).map(function (field) {
				return '<select class="tabloria-select"><option>All ' + escapeHtml(labelForField(field)) + '</option></select>';
			}).join('') + '</div>');
		}

		return parts.length ? '<div class="tabloria-mini-controls">' + parts.join('') + '</div>' : '';
	}

	function renderMiniImage(image) {
		var imageUrl = safeImageUrl(image);
		if (!imageUrl) {
			return '<div class="tabloria-mini-card__image"></div>';
		}

		return '<div class="tabloria-mini-card__image"><img src="' + escapeHtml(imageUrl) + '" alt="" loading="lazy"></div>';
	}

	function miniFieldParts(row, roles) {
		var visibleFields = state.config.visibleFields || [];
		var roleFields = Object.keys(roles || {}).map(function (key) {
			return roles[key];
		});
		var fields = visibleFields.filter(function (field) {
			return field && roleFields.indexOf(field) === -1 && !isTechnicalField(field) && row[field];
		}).slice(0, 4);
		var fieldRows = [];
		var actions = [];

		fields.forEach(function (field) {
			var value = row[field];
			if (columnTypeForField(field) === 'url') {
				var url = safeUrl(value);
				if (url) {
					var options = cardLayoutOptions(state.config);
					actions.push({
						label: cardUrlLabel(field),
						url: url,
						display: options.urlDisplay,
						buttonStyle: options.buttonStyle
					});
				}
				return;
			}

			fieldRows.push({
				label: labelForField(field),
				value: value
			});
		});

		return {
			fields: fieldRows,
			actions: actions
		};
	}

	function renderMiniFields(fields) {
		fields = fields || [];

		if (!fields.length) {
			return '';
		}

		return '<dl class="tabloria-mini-card__fields">' + fields.map(function (field) {
			return '<div><dt>' + escapeHtml(field.label) + '</dt><dd>' + escapeHtml(field.value) + '</dd></div>';
		}).join('') + '</dl>';
	}

	function renderMiniActions(actions) {
		actions = actions || [];
		if (!actions.length) {
			return '';
		}

		return '<div class="tabloria-mini-card__actions">' + actions.map(function (action) {
			var buttonStyle = ['theme', 'primary', 'secondary', 'outline'].indexOf(action.buttonStyle) >= 0 ? action.buttonStyle : 'theme';
			var className = action.display === 'button' ? 'tabloria-mini-card__button tabloria-mini-card__button--' + buttonStyle : 'tabloria-mini-card__link';
			return '<a class="' + className + '" href="' + escapeHtml(action.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(action.label) + '</a>';
		}).join('') + '</div>';
	}

	function renderMiniTableCell(row, column) {
		var value = row[column.key] || '';
		if (!value) {
			return '';
		}
		if (String(column.type || '').toLowerCase() === 'url') {
			var url = safeUrl(value);
			if (url) {
				return '<a class="tabloria-mini-table-link" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(urlButtonLabel(column.key)) + '</a>';
			}
		}
		return escapeHtml(value);
	}

	function isTechnicalField(field) {
		return ['entity', 'object_type', 'row_type', 'record_type'].indexOf(field) >= 0;
	}

	function renderMiniPagination(totalRows, perPage, previewCount) {
		if (!state.config.pagination || !state.config.pagination.enabled) {
			return '';
		}

		var totalPages = Math.max(1, Math.ceil(totalRows / Math.max(1, perPage)));
		var visibleEnd = Math.min(previewCount, totalRows);
		var finalEnd = Math.min(perPage, totalRows);
		var rowLabel = hasActiveDefaultFilter() ? ' matching sample rows' : ' rows';
		var buttons = '';
		if (totalPages > 1) {
			buttons = '<button type="button" disabled>Prev</button><button type="button" class="is-active">1</button>';
			if (totalPages >= 2) {
				buttons += '<button type="button">2</button>';
			}
			if (totalPages > 3) {
				buttons += '<span>...</span>';
			}
			if (totalPages > 2) {
				buttons += '<button type="button">' + totalPages + '</button>';
			}
			buttons += '<button type="button">Next</button>';
		} else {
			buttons = '<span>Page 1 of 1</span>';
		}

		return [
			'<div class="tabloria-mini-pagination">',
			'<p>Previewing ' + (totalRows ? 1 : 0) + ' to ' + visibleEnd + ' of ' + totalRows + rowLabel + (visibleEnd < finalEnd ? '<br><span>Final Display shows ' + finalEnd + ' per page.</span>' : '') + '</p>',
			'<nav aria-label="Preview pagination">' + buttons + '</nav>',
			'</div>'
		].join('');
	}

	function bestRowTitle(row) {
		var keys = Object.keys(row);
		var key = keys.find(function (field) {
			return /(^|_)(name|title)($|_)/.test(field) && !/email|code|number|status|type/.test(field) && row[field];
		});
		return key ? row[key] : row[keys[0]];
	}

	function bestRowSubtitle(row, title) {
		var keys = Object.keys(row);
		var key = keys.find(function (field) {
			return /(^|_)(category|brand|role|location|building|department|course)($|_)/.test(field) && row[field] && row[field] !== title;
		});
		return key ? row[key] : '';
	}

	function labelForField(field) {
		if (state.source && state.source.columns) {
			var column = state.source.columns.find(function (item) {
				return item.key === field;
			});
			if (column) {
				return humanizeLabel(column.label || column.key);
			}
		}
		return humanizeLabel(field);
	}

	function columnTypeForField(field) {
		if (state.source && state.source.columns) {
			var column = state.source.columns.find(function (item) {
				return item.key === field;
			});
			if (column) {
				return String(column.type || 'text').toLowerCase();
			}
		}
		return 'text';
	}

	function urlButtonLabel(field) {
		var label = labelForField(field);
		var normalized = label.toLowerCase().trim();
		if (normalized === 'url' || normalized === 'link') {
			return 'Open link';
		}
		return label;
	}

	function cardUrlLabel(field) {
		var label = cardLayoutOptions(state.config).urlLabel;
		return label ? label : urlButtonLabel(field);
	}

	function humanizeLabel(label) {
		return String(label || 'Field').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\w\S*/g, function (word) {
			return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
		});
	}

	function sampleRowsFromConfig() {
		return [
			{ apartment_name: 'The Horizon Suite', building: 'Downtown Core', price: '$3,450/mo', status: 'Available' },
			{ apartment_name: 'Oasis Lofts #4B', building: 'Arts District', price: '$2,800/mo', status: 'Tour Pending' },
			{ apartment_name: 'Parkside Flat', building: 'West End', price: '$1,850/mo', status: 'Available' }
		];
	}

	function generateConfig(source, displayType) {
		var columns = source && source.columns ? source.columns : [];
		var publicColumns = columns.filter(function (column) {
			return ['entity', 'object_type', 'row_type', 'record_type'].indexOf(column.key) === -1;
		});
		var byType = {};
		columns.forEach(function (column) {
			if (!byType[column.type]) {
				byType[column.type] = column.key;
			}
		});
		function byLabel(tokens) {
			var found = columns.find(function (column) {
				var label = String(column.label || '').toLowerCase();
				return tokens.some(function (token) {
					return label.indexOf(token) >= 0;
				});
			});
			return found ? found.key : '';
		}
		var firstKey = columns[0] ? columns[0].key : '';
		var sortField = byType.price || byType.number || byType.date || firstKey;
		var priceField = byType.price || byLabel(['price', 'cost', 'rate', 'fee', 'amount', 'total']) || '';
		if (['team_directory', 'event_schedule', 'simple_data_table'].indexOf(displayType) >= 0 && !byType.price) {
			priceField = '';
		}
		return {
			displayType: displayType || 'searchable_listings',
			title: titleFromSource(source),
			sourceId: source ? source.id : 0,
			fieldRoles: {
				title: byLabel(['name', 'title']) || (publicColumns[0] ? publicColumns[0].key : firstKey),
				subtitle: byLabel(['building', 'location', 'role', 'category']) || '',
				image: byType.image || byLabel(['photo', 'image']) || '',
				price: priceField,
				badge: byType.status || byLabel(['status', 'type']) || ''
			},
			visibleFields: publicColumns.slice(0, 7).map(function (column) { return column.key; }),
			defaultFilters: [],
			visitorFilters: columns.filter(function (column) {
				return ['status', 'number', 'date'].indexOf(column.type) >= 0;
			}).slice(0, 4).map(function (column) { return column.key; }),
			sort: { field: sortField, direction: 'asc' },
			search: { enabled: true },
			filters: { enabled: true },
			pagination: { enabled: true, perPage: 12 },
			style: { accentColor: '#3525cd', density: 'comfortable', imageShape: 'rounded', displayWidth: 'normal' },
			layoutOptions: defaultLayoutOptions()
		};
	}

	function titleFromSource(source) {
		if (!source) {
			return 'Published Display';
		}
		return String(source.name || source.original_filename || 'Published Display').replace(/\.[a-z0-9]+$/i, '');
	}

	function labelForType(key) {
		var type = displayTypes.find(function (item) {
			return item.key === key;
		});
		return type ? type.label : 'Published Display';
	}

	function findDisplay(id) {
		return state.displays.find(function (display) {
			return String(display.id) === String(id);
		});
	}

	function slugify(value) {
		return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'tabloria-display';
	}

	function copyText(text, button) {
		var done = function () {
			var original = button.textContent;
			button.textContent = 'Copied';
			window.setTimeout(function () {
				button.textContent = original;
			}, 1600);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done);
			return;
		}

		window.prompt('Copy this shortcode:', text);
		done();
	}

	function renderMissingSource() {
		return '<section class="tabloria-empty"><h2>No spreadsheet selected.</h2><p class="tabloria-muted">Upload a spreadsheet to continue creating a Display.</p><div class="tabloria-actions" style="justify-content:center"><button class="tabloria-button is-primary" data-action="upload">Upload Spreadsheet</button></div></section>';
	}

	render();
	if (state.screen === 'displays') {
		loadDisplays();
	} else if (state.screen === 'settings') {
		loadSettings();
	}
})();
