(function () {
	'use strict';

	var root = document.getElementById('publishly-admin-root');
	if (!root) {
		return;
	}

	var env = window.publishlyAdmin || {};
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
		notice: ''
	};

	function initialScreen() {
		if (env.currentPage === 'publishly-displays') {
			return 'displays';
		}
		if (env.currentPage === 'publishly-settings') {
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
				var data = text ? JSON.parse(text) : {};
				if (!response.ok) {
					throw new Error(data.message || response.statusText || 'Publishly request failed.');
				}
				return data;
			});
		});
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
			'<div class="publishly-app">',
			renderTopbar(),
			'<main class="publishly-shell">',
			state.error ? '<div class="publishly-error">' + escapeHtml(state.error) + '</div>' : '',
			state.notice ? '<div class="publishly-notice-banner">' + escapeHtml(state.notice) + '</div>' : '',
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
			'<header class="publishly-topbar">',
			'<div class="publishly-brand"><span class="publishly-logo">P</span><span>Publishly</span></div>',
			'<nav class="publishly-nav" aria-label="Publishly navigation">',
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
			return ['welcome', 'upload', 'detection', 'outcome', 'setup', 'customize', 'preview', 'success'].indexOf(state.screen) >= 0;
		}
		if (screen === 'displays') {
			return ['displays', 'editor'].indexOf(state.screen) >= 0;
		}
		return state.screen === screen;
	}

	function bindTopbar() {
		root.querySelectorAll('.publishly-nav [data-screen]').forEach(function (button) {
			button.addEventListener('click', function () {
				setScreen(button.dataset.screen);
			});
		});
	}

	function renderCurrentScreen() {
		if (state.loading) {
			return '<div class="publishly-loading">Loading Publishly...</div>';
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
			case 'customize':
				return renderCustomize();
			case 'preview':
				return renderPreview();
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
		} else if (state.screen === 'customize') {
			bindCustomize();
		} else if (state.screen === 'preview') {
			bindPreview();
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
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Spreadsheet publishing assistant</p>',
			'<h1>Turn spreadsheets into searchable WordPress pages.</h1>',
			'<p class="publishly-subtitle">Upload CSV files and create reusable displays for listings, catalogs, directories, schedules, and more.</p>',
			'<div class="publishly-actions">',
			'<button class="publishly-button is-primary" data-action="start">Create Display</button>',
			'<button class="publishly-button" data-action="upload">Upload Spreadsheet</button>',
			'<button class="publishly-button is-ghost" disabled>Connect Google Sheets <span class="publishly-badge">Coming soon</span></button>',
			'</div>',
			'</section>',
			'<section>',
			'<h2>Popular Starting Points</h2>',
			'<div class="publishly-grid">',
			displayTypes.map(function (type, index) {
				return renderStartingPoint(type, index);
			}).join(''),
			'</div>',
			'</section>'
		].join('');
	}

	function renderStartingPoint(type, index) {
		var preview = [
			'<div class="publishly-card__preview">',
			'<div style="display:grid;grid-template-columns:52px 1fr;gap:12px;align-items:center">',
			'<span class="publishly-thumb" style="height:48px"></span>',
			'<span class="publishly-preview-lines"><span class="publishly-line" style="height:10px;width:78%"></span><span class="publishly-line" style="height:10px;width:54%"></span></span>',
			'</div>',
			'<span class="publishly-line" style="height:10px;width:' + (70 - index * 4) + '%"></span>',
			'</div>'
		].join('');

		return [
			'<article class="publishly-card" data-type="' + escapeHtml(type.key) + '">',
			preview,
			'<h3>' + escapeHtml(type.label.replace('Searchable ', '')) + '</h3>',
			'<p>' + escapeHtml(type.copy) + '</p>',
			'</article>'
		].join('');
	}

	function bindWelcome() {
		root.querySelectorAll('[data-action="start"], [data-action="upload"], .publishly-card[data-type]').forEach(function (el) {
			el.addEventListener('click', function () {
				if (el.dataset.type) {
					state.displayType = el.dataset.type;
				}
				state.display = null;
				state.source = null;
				state.sample = [];
				state.config = null;
				setScreen('upload');
			});
		});
	}

	function renderUpload() {
		return [
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Create Display</p>',
			'<h1>Upload your spreadsheet.</h1>',
			'<p class="publishly-subtitle">Start with a CSV file. Publishly will detect columns, field types, and a few useful publishing defaults.</p>',
			'</section>',
			'<form class="publishly-upload" id="publishly-upload-form">',
			'<div>',
			'<h2>Choose a CSV file</h2>',
			'<p class="publishly-muted">Excel support is prepared in the architecture, but this MVP imports CSV first.</p>',
			'<input class="publishly-input" type="file" name="file" accept=".csv,text/csv" required />',
			'<div class="publishly-actions" style="justify-content:center">',
			'<button class="publishly-button" type="button" data-back>Back</button>',
			'<button class="publishly-button is-primary" type="submit">Upload Spreadsheet</button>',
			'</div>',
			'</div>',
			'</form>'
		].join('');
	}

	function bindUpload() {
		var form = root.querySelector('#publishly-upload-form');
		var back = root.querySelector('[data-back]');

		if (!form || !back) {
			return;
		}

		back.addEventListener('click', function () {
			setScreen('welcome');
		});
		form.addEventListener('submit', function (event) {
			event.preventDefault();
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
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Smart Detection</p>',
			'<h1>Publishly understood your spreadsheet automatically.</h1>',
			'<p class="publishly-subtitle">' + escapeHtml(state.source.original_filename) + ' - ' + escapeHtml(state.source.row_count) + ' rows detected.</p>',
			'</section>',
			'<section class="publishly-panel">',
			'<h2>Detected Columns</h2>',
			'<div class="publishly-columns">',
			(state.source.columns || []).map(function (column) {
				return '<div class="publishly-column"><div><strong>' + escapeHtml(column.label) + '</strong><span class="publishly-muted">' + escapeHtml(column.key) + '</span></div><span class="publishly-type">' + escapeHtml(column.type) + '</span></div>';
			}).join(''),
			'</div>',
			'</section>',
			'<section class="publishly-panel">',
			'<h2>First Rows Preview</h2>',
			renderTable(state.sample, state.source.columns),
			'</section>',
			'<div class="publishly-actions">',
			'<button class="publishly-button" data-back>Back</button>',
			'<button class="publishly-button is-primary" data-continue>Looks good - continue</button>',
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
			setScreen('outcome');
		});
	}

	function renderOutcome() {
		return [
			'<section class="publishly-hero" style="text-align:center;margin-left:auto;margin-right:auto">',
			'<p class="publishly-kicker">Published Display</p>',
			'<h1>What would you like to display?</h1>',
			'<p class="publishly-subtitle">Choose the kind of page you want to create from this spreadsheet. Publishly will choose helpful defaults for fields, filters, sorting, and pagination.</p>',
			'</section>',
			'<section class="publishly-grid">',
			displayTypes.map(function (type) {
				var selected = state.displayType === type.key ? ' is-selected' : '';
				return '<article class="publishly-card' + selected + '" data-type="' + escapeHtml(type.key) + '">' + renderOutcomePreview(type.key) + '<h3>' + escapeHtml(type.label) + '</h3><p>' + escapeHtml(type.copy) + '</p></article>';
			}).join(''),
			'</section>',
			'<div class="publishly-actions" style="justify-content:flex-end">',
			'<button class="publishly-button" data-back>Back</button>',
			'<button class="publishly-button is-primary" data-continue>Continue</button>',
			'</div>'
		].join('');
	}

	function renderOutcomePreview(type) {
		var content = '<span class="publishly-line" style="height:12px;width:92%"></span><span class="publishly-line" style="height:36px;width:100%;border-radius:8px"></span><span class="publishly-line" style="height:10px;width:70%"></span>';
		if (type === 'product_catalog' || type === 'pricing_table') {
			content = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;height:100%"><span class="publishly-thumb"></span><span class="publishly-thumb"></span><span class="publishly-thumb"></span></div>';
		}
		if (type === 'team_directory') {
			content = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><span class="publishly-dot"></span><span class="publishly-dot"></span><span class="publishly-line" style="height:10px;width:80%"></span><span class="publishly-line" style="height:10px;width:60%"></span></div>';
		}
		return '<div class="publishly-card__preview">' + content + '</div>';
	}

	function bindOutcome() {
		root.querySelectorAll('.publishly-card[data-type]').forEach(function (card) {
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
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Display Setup</p>',
			'<h1>Set up your Display</h1>',
			'<p class="publishly-subtitle">Choose how spreadsheet fields should appear on the page.</p>',
			'</section>',
			'<div class="publishly-grid is-two">',
			'<div>',
			'<section class="publishly-panel">',
			'<div class="publishly-field"><label for="display-name">Display name</label><input id="display-name" class="publishly-input" value="' + escapeHtml(state.config.title || '') + '" /></div>',
			'</section>',
			'<section class="publishly-panel">',
			'<h2>Field Roles</h2>',
			'<p class="publishly-muted">Map your spreadsheet columns to visual roles in the Display.</p>',
			renderRoleRows(roles, state.config, 'role-'),
			'</section>',
			'<section class="publishly-panel">',
			'<h2>Filtering & Sorting</h2>',
			'<p class="publishly-muted">Keep this simple: one default filter, visitor filters, and one default sort.</p>',
			'<h3>Default Filter</h3>',
			renderDefaultFilter(),
			'<h3 style="margin-top:24px">Visitor Filters</h3>',
			columns.map(function (column) {
				var checked = (state.config.visitorFilters || []).indexOf(column.key) >= 0 ? ' checked' : '';
				return '<label class="publishly-check-row"><input type="checkbox" name="visitor-filter" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
			}).join(''),
			'<h3 style="margin-top:24px">Default Sort</h3>',
			'<div class="publishly-filter-builder">' + renderColumnSelect('sort-field', state.config.sort.field || '', true) + '<select class="publishly-select" id="sort-direction"><option value="asc"' + selected(state.config.sort.direction, 'asc') + '>Low to high</option><option value="desc"' + selected(state.config.sort.direction, 'desc') + '>High to low</option></select><span></span></div>',
			'</section>',
			'</div>',
			'<aside class="publishly-panel">',
			'<h2>Visible Fields</h2>',
			'<p class="publishly-muted">Select fields visitors should see on the page.</p>',
			columns.map(function (column) {
				var checked = (state.config.visibleFields || []).indexOf(column.key) >= 0 ? ' checked' : '';
				return '<label class="publishly-check-row"><input type="checkbox" name="visible-field" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
			}).join(''),
			'</aside>',
			'</div>',
			'<div class="publishly-actions" style="justify-content:flex-end">',
			'<button class="publishly-button" data-back>Back</button>',
			'<button class="publishly-button is-primary" data-continue>Preview Display</button>',
			'</div>'
		].join('');
	}

	function renderDefaultFilter() {
		var filter = (state.config.defaultFilters || [])[0] || { field: '', operator: 'equals', value: '' };
		return [
			'<div class="publishly-filter-builder">',
			renderColumnSelect('default-filter-field', filter.field || '', true),
			'<select id="default-filter-operator" class="publishly-select">',
			'<option value="equals"' + selected(filter.operator, 'equals') + '>is</option>',
			'<option value="not_equals"' + selected(filter.operator, 'not_equals') + '>is not</option>',
			'<option value="contains"' + selected(filter.operator, 'contains') + '>contains</option>',
			'</select>',
			'<input id="default-filter-value" class="publishly-input" placeholder="Value, for example Available" value="' + escapeHtml(filter.value || '') + '" />',
			'</div>'
		].join('');
	}

	function renderColumnSelect(id, current, includeNone) {
		var columns = state.source ? state.source.columns || [] : [];
		return [
			'<select class="publishly-select" id="' + escapeHtml(id) + '">',
			includeNone ? '<option value="">None</option>' : '',
			columns.map(function (column) {
				return '<option value="' + escapeHtml(column.key) + '"' + selected(current, column.key) + '>' + escapeHtml(column.label) + '</option>';
			}).join(''),
			'</select>'
		].join('');
	}

	function roleDefinitions() {
		return [
			{ key: 'title', label: 'Title field', description: 'Primary heading' },
			{ key: 'image', label: 'Image field', description: 'Visual media', optional: true, empty: 'No image-like column detected.' },
			{ key: 'price', label: commerceDisplayType() ? 'Price field' : 'Highlighted value', description: 'Money, rate, fee, or other featured numeric value', optional: true, commerceOnlyWhenEmpty: true, empty: 'No price-like column detected.' },
			{ key: 'badge', label: 'Badge field', description: 'Status, category, role, or tag', optional: true, empty: 'No badge-like column detected.' },
			{ key: 'subtitle', label: 'Subtitle field', description: 'Secondary context' }
		];
	}

	function renderRoleRows(roles, config, idPrefix) {
		var rows = roles.map(function (role) {
			return renderRoleRow(role, config, idPrefix);
		}).filter(Boolean);
		return rows.length ? rows.join('') : '<p class="publishly-muted">No role fields are needed for this source.</p>';
	}

	function renderRoleRow(role, config, idPrefix) {
		var current = config.fieldRoles && config.fieldRoles[role.key] ? config.fieldRoles[role.key] : '';
		var options = roleOptions(role.key, current);
		if (!options.length && role.optional && !(role.commerceOnlyWhenEmpty && commerceDisplayType())) {
			return '';
		}

		var description = options.length ? role.description : role.empty;
		return '<div class="publishly-role-row"><div><strong>' + escapeHtml(role.label) + '</strong><br><span class="publishly-muted">' + escapeHtml(description || role.description) + '</span></div>' + renderRoleSelect(idPrefix + role.key, role, current, options) + '</div>';
	}

	function renderRoleSelect(id, role, current, options) {
		if (!options.length) {
			return '<select class="publishly-select" id="' + escapeHtml(id) + '" disabled><option value="">' + escapeHtml(role.empty || 'No matching column') + '</option></select>';
		}

		return [
			'<select class="publishly-select" id="' + escapeHtml(id) + '">',
			'<option value="">None</option>',
			options.map(function (column) {
				return '<option value="' + escapeHtml(column.key) + '"' + selected(current, column.key) + '>' + escapeHtml(column.label) + '</option>';
			}).join(''),
			'</select>'
		].join('');
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
		return ['product_catalog', 'pricing_table'].indexOf(state.displayType) >= 0;
	}

	function selected(current, value) {
		return String(current || '') === String(value || '') ? ' selected' : '';
	}

	function bindSetup() {
		if (!root.querySelector('[data-back]') || !root.querySelector('[data-continue]')) {
			return;
		}
		root.querySelector('[data-back]').addEventListener('click', function () {
			setScreen('outcome');
		});
		root.querySelector('[data-continue]').addEventListener('click', function () {
			collectSetup();
			setScreen('customize');
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
		config.defaultFilters = filterField && filterValue ? [{
			field: filterField,
			operator: valueOf('#default-filter-operator') || 'equals',
			value: filterValue
		}] : [];
		config.sort = {
			field: valueOf('#sort-field'),
			direction: valueOf('#sort-direction') || 'asc'
		};
		state.config = config;
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

	function renderCustomize() {
		state.config = state.config || generateConfig(state.source, state.displayType);
		var style = state.config.style || {};
		var swatches = ['#3525cd', '#00687a', '#a44100', '#1b1b24'];

		return [
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Appearance</p>',
			'<h1>Customize appearance</h1>',
			'<p class="publishly-subtitle">Light visual controls only. The Display stays reusable and Gutenberg-ready.</p>',
			'</section>',
			'<div class="publishly-grid is-two">',
			'<div>',
			'<section class="publishly-panel">',
			'<h2>Visual Style</h2>',
			'<div class="publishly-field"><span class="publishly-label">Accent color</span><div class="publishly-swatch-row">',
			swatches.map(function (color) {
				var active = (style.accentColor || '#3525cd') === color ? ' is-selected' : '';
				return '<button type="button" class="publishly-swatch' + active + '" style="--swatch:' + color + '" data-color="' + color + '" aria-label="Use color ' + color + '"></button>';
			}).join(''),
			'</div></div>',
			'<div class="publishly-field" style="margin-top:22px"><span class="publishly-label">Card density</span><div class="publishly-segmented" data-segment="density"><button type="button" data-value="comfortable" class="' + selectedClass(style.density, 'comfortable') + '">Comfortable</button><button type="button" data-value="compact" class="' + selectedClass(style.density, 'compact') + '">Compact</button></div></div>',
			'<div class="publishly-field" style="margin-top:22px"><span class="publishly-label">Image shape</span><div class="publishly-segmented" data-segment="imageShape"><button type="button" data-value="rounded" class="' + selectedClass(style.imageShape, 'rounded') + '">Rounded</button><button type="button" data-value="square" class="' + selectedClass(style.imageShape, 'square') + '">Square</button></div></div>',
			'</section>',
			'<section class="publishly-panel">',
			'<h2>Components</h2>',
			'<p class="publishly-muted">These controls update the preview immediately. The saved Display will use all spreadsheet rows.</p>',
			renderToggle('show-search', 'Show search', state.config.search.enabled),
			renderToggle('show-filters', 'Show filters', state.config.filters.enabled),
			renderToggle('show-pagination', 'Show pagination', state.config.pagination.enabled),
			'<div class="publishly-field" style="margin-top:14px"><label for="per-page">Items per page</label><select id="per-page" class="publishly-select"' + (!state.config.pagination.enabled ? ' disabled' : '') + '><option value="6"' + selected(state.config.pagination.perPage, 6) + '>6 items</option><option value="9"' + selected(state.config.pagination.perPage, 9) + '>9 items</option><option value="12"' + selected(state.config.pagination.perPage, 12) + '>12 items</option><option value="24"' + selected(state.config.pagination.perPage, 24) + '>24 items</option></select></div>',
			'</section>',
			'</div>',
			'<aside class="publishly-preview">',
			'<div class="publishly-preview__bar">Live preview</div>',
			'<div class="publishly-preview__body">' + renderMiniPreview() + '</div>',
			'</aside>',
			'</div>',
			'<div class="publishly-actions" style="justify-content:flex-end">',
			'<button class="publishly-button" data-back>Back</button>',
			'<button class="publishly-button is-primary" data-continue>Preview</button>',
			'</div>'
		].join('');
	}

	function selectedClass(current, value) {
		current = current || (value === 'comfortable' || value === 'rounded' ? value : '');
		return String(current) === String(value) ? 'is-selected' : '';
	}

	function renderToggle(id, label, checked) {
		return '<label class="publishly-toggle-row"><span><strong>' + escapeHtml(label) + '</strong></span><input id="' + escapeHtml(id) + '" type="checkbox"' + (checked ? ' checked' : '') + '></label>';
	}

	function bindCustomize() {
		if (!root.querySelector('[data-back]') || !root.querySelector('[data-continue]')) {
			return;
		}
		root.querySelectorAll('.publishly-swatch').forEach(function (button) {
			button.addEventListener('click', function () {
				state.config.style.accentColor = button.dataset.color;
				render();
			});
		});
			root.querySelectorAll('[data-segment] button').forEach(function (button) {
				button.addEventListener('click', function () {
					state.config.style[button.parentNode.dataset.segment] = button.dataset.value;
					render();
				});
			});
			['#show-search', '#show-filters', '#show-pagination', '#per-page'].forEach(function (selector) {
				var control = root.querySelector(selector);
				if (!control) {
					return;
				}
				control.addEventListener('change', function () {
					collectCustomize();
					render();
				});
			});
			root.querySelector('[data-back]').addEventListener('click', function () {
				setScreen('setup');
			});
		root.querySelector('[data-continue]').addEventListener('click', function () {
			collectCustomize();
			setScreen('preview');
		});
	}

	function collectCustomize() {
		var search = root.querySelector('#show-search');
		var filters = root.querySelector('#show-filters');
		var pagination = root.querySelector('#show-pagination');
		var perPage = root.querySelector('#per-page');

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
	}

	function renderPreview() {
		return [
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Website Preview</p>',
			'<h1>Your spreadsheet is becoming a real page.</h1>',
			'<p class="publishly-subtitle">Review the Display, then save it so Gutenberg can insert it by ID.</p>',
			'</section>',
			'<section class="publishly-preview">',
			'<div class="publishly-preview__bar">yourwebsite.com/' + escapeHtml(slugify(state.config.title || 'publishly-display')) + '</div>',
			'<div class="publishly-preview__body">' + renderMiniPreview(true) + '</div>',
			'</section>',
			'<div class="publishly-actions" style="justify-content:flex-end">',
			'<button class="publishly-button" data-back>Back to edit</button>',
			'<button class="publishly-button is-primary" data-save>Save Display</button>',
			'</div>'
		].join('');
	}

	function bindPreview() {
		root.querySelector('[data-back]').addEventListener('click', function () {
			setScreen('customize');
		});
		root.querySelector('[data-save]').addEventListener('click', saveDisplay);
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
			'<section class="publishly-success">',
			'<div class="publishly-success__icon">OK</div>',
			'<div>',
			'<h2>Your Display is ready.</h2>',
			'<p class="publishly-muted">"' + escapeHtml(state.config.title) + '" has been saved as a reusable Published Display. Insert it in Gutenberg or use the shortcode fallback.</p>',
			'<p><code>[publishly id="' + escapeHtml(id) + '"]</code></p>',
			'</div>',
			'<div class="publishly-actions" style="margin-top:0;justify-content:flex-end">',
			'<button class="publishly-button is-primary" data-copy-shortcode>Copy shortcode</button>',
			'<button class="publishly-button" data-edit>Edit Display</button>',
			'<button class="publishly-button" data-list>Published Displays</button>',
			'</div>',
			'</section>',
			'<section class="publishly-preview" style="margin-top:28px">',
			'<div class="publishly-preview__bar">Saved Display preview</div>',
			'<div class="publishly-preview__body">' + renderMiniPreview(true) + '</div>',
			'</section>'
		].join('');
	}

	function bindSuccess() {
		root.querySelector('[data-copy-shortcode]').addEventListener('click', function (event) {
			copyText('[publishly id="' + state.display.id + '"]', event.currentTarget);
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
			return '<div class="publishly-loading">Loading Published Displays...</div>';
		}

		return [
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Reusable Displays</p>',
			'<h1>Published Displays</h1>',
			'<p class="publishly-subtitle">Manage the spreadsheet displays you can insert into WordPress pages.</p>',
			'<div class="publishly-actions"><button class="publishly-button is-primary" data-create>Create Display</button></div>',
			'</section>',
			state.displays.length ? '<section class="publishly-display-cards">' + state.displays.map(renderDisplayCard).join('') + '</section>' : renderEmptyDisplays()
		].join('');
	}

	function renderDisplayCard(display) {
		var source = display.source || {};
		return [
			'<article class="publishly-display-card" data-display-id="' + escapeHtml(display.id) + '">',
			'<span class="publishly-type">' + escapeHtml(display.status || 'published') + '</span>',
			'<h3 style="margin-top:18px">' + escapeHtml(display.name) + '</h3>',
			'<div class="publishly-card__preview"><span class="publishly-line" style="height:14px;width:72%"></span><span class="publishly-line" style="height:54px;width:100%;border-radius:8px"></span><span class="publishly-line" style="height:10px;width:56%"></span></div>',
			'<div class="publishly-display-card__meta">',
			'<span>Type: ' + escapeHtml(labelForType(display.display_type)) + '</span>',
			'<span>Rows: ' + escapeHtml(source.row_count || 0) + '</span>',
			'<span>Source: ' + escapeHtml(source.original_filename || 'Spreadsheet') + '</span>',
			'<span>Updated: ' + escapeHtml(display.updated_at || '') + '</span>',
			'</div>',
			'<div class="publishly-display-card__actions">',
			'<button class="publishly-button" data-edit="' + escapeHtml(display.id) + '">Edit</button>',
			'<button class="publishly-button" data-copy="' + escapeHtml(display.id) + '">Insert</button>',
			'<label class="publishly-button">Update Data<input type="file" accept=".csv,text/csv" data-update="' + escapeHtml(display.id) + '" hidden></label>',
			'<button class="publishly-button is-danger" data-delete="' + escapeHtml(display.id) + '">Delete</button>',
			'</div>',
			'</article>'
		].join('');
	}

	function renderEmptyDisplays() {
		return '<section class="publishly-empty"><h2>No displays yet.</h2><p class="publishly-muted">Upload a spreadsheet to create your first searchable page.</p><div class="publishly-actions" style="justify-content:center"><button class="publishly-button is-primary" data-create>Create Display</button></div></section>';
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
		root.querySelectorAll('[data-copy]').forEach(function (button) {
			button.addEventListener('click', function (event) {
				copyText('[publishly id="' + button.dataset.copy + '"]', event.currentTarget);
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
				if (!window.confirm('Delete this Published Display? The source spreadsheet data will stay in Publishly.')) {
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
		root.querySelectorAll('[data-update]').forEach(function (input) {
			input.addEventListener('change', function () {
				if (!input.files || !input.files[0]) {
					return;
				}
				var data = new FormData();
				data.append('file', input.files[0]);
				state.loading = true;
				render();
				request('/displays/' + input.dataset.update + '/update-data', { method: 'POST', body: data })
					.then(function (response) {
						state.loading = false;
						state.loadedDisplays = false;
						window.alert('Update complete. Matched: ' + response.summary.matchedCount + ', new: ' + response.summary.newCount + ', missing: ' + response.summary.missingCount + '.');
						loadDisplays();
					})
					.catch(function (error) {
						state.loading = false;
						setError(error);
					});
			});
		});
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

		state.error = '';
		state.notice = '';
		state.loading = true;
		state.screen = 'editor';
		state.editorTab = 'preview';
		state.updateSummary = null;
		state.display = display;
		state.displayType = display.display_type || state.displayType;
		state.config = cloneConfig(display.config || state.config || {});
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
			['style', 'Style'],
			['source', 'Data Source'],
			['publish', 'Publish']
		];

		return [
			'<section class="publishly-editor-header">',
			'<div>',
			'<p class="publishly-kicker">Display Editor</p>',
			'<h1>Edit Display</h1>',
			'<p class="publishly-subtitle">' + escapeHtml(config.title || state.display.name || 'Published Display') + ' from ' + escapeHtml(source.original_filename || 'Spreadsheet') + '</p>',
			'</div>',
			'<div class="publishly-editor-actions">',
			'<button class="publishly-button" data-editor-list>Published Displays</button>',
			'<button class="publishly-button is-primary" data-editor-save>Save changes</button>',
			'</div>',
			'</section>',
			'<div class="publishly-editor-layout' + (!showSidePreview ? ' is-preview-tab' : '') + '">',
			'<aside class="publishly-editor-tabs" aria-label="Display editor sections">',
			tabs.map(function (tab) {
				var active = activeTab === tab[0] ? ' is-active' : '';
				return '<button type="button" class="' + active + '" data-editor-tab="' + escapeHtml(tab[0]) + '">' + escapeHtml(tab[1]) + '</button>';
			}).join(''),
			'</aside>',
			'<section class="publishly-editor-main">',
			renderEditorTab(activeTab),
			'</section>',
			showSidePreview ? [
			'<aside class="publishly-editor-side">',
			'<div class="publishly-preview">',
			'<div class="publishly-preview__bar">Live preview</div>',
			'<div class="publishly-preview__body">' + renderMiniPreview(true) + '</div>',
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
		if (tab === 'style') {
			return renderEditorStyle();
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
			'<div class="publishly-editor-section">',
			'<h2>Preview</h2>',
			'<p class="publishly-muted">This preview uses the first rows from the current source. The saved Display renders the full dataset on the front end.</p>',
			'<div class="publishly-preview" style="margin-top:18px">',
			'<div class="publishly-preview__bar">yourwebsite.com/' + escapeHtml(slugify(state.config.title || 'publishly-display')) + '</div>',
			'<div class="publishly-preview__body">' + renderMiniPreview(true) + '</div>',
			'</div>',
			'</div>'
		].join('');
	}

	function renderEditorFields() {
		var config = ensureConfig();
		var columns = state.source.columns || [];
		var roles = roleDefinitions();

		return [
			'<div class="publishly-editor-section">',
			'<h2>Fields</h2>',
			'<p class="publishly-muted">Choose what each row should look like and which columns visitors should see.</p>',
			'<div class="publishly-field" style="margin-top:18px"><label for="editor-display-name">Display name</label><input id="editor-display-name" class="publishly-input" value="' + escapeHtml(config.title || '') + '"></div>',
			'<div class="publishly-editor-stack">',
			'<section>',
			'<h3>Field Roles</h3>',
			renderRoleRows(roles, config, 'editor-role-'),
			'</section>',
			'<section>',
			'<h3>Visible Fields</h3>',
			'<p class="publishly-muted">These fields appear in expanded card details and table-like display variants.</p>',
			columns.map(function (column) {
				var checked = (config.visibleFields || []).indexOf(column.key) >= 0 ? ' checked' : '';
				return '<label class="publishly-check-row"><input type="checkbox" name="editor-visible-field" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
			}).join('') || '<p class="publishly-muted">No columns found in this source.</p>',
			'</section>',
			'</div>',
			'</div>'
		].join('');
	}

	function renderEditorFilters() {
		var config = ensureConfig();
		var columns = state.source.columns || [];
		var filter = (config.defaultFilters || [])[0] || { field: '', operator: 'equals', value: '' };

		return [
			'<div class="publishly-editor-section">',
			'<h2>Search & Filters</h2>',
			'<p class="publishly-muted">Control what visitors can search, filter, sort, and page through.</p>',
			'<div class="publishly-editor-stack">',
			'<section class="publishly-editor-subsection">',
			'<h3>Components</h3>',
			renderToggle('editor-show-search', 'Show search', config.search.enabled),
			renderToggle('editor-show-filters', 'Show filters', config.filters.enabled),
			renderToggle('editor-show-pagination', 'Show pagination', config.pagination.enabled),
			'<div class="publishly-field" style="margin-top:14px"><label for="editor-per-page">Items per page</label><select id="editor-per-page" class="publishly-select"' + (!config.pagination.enabled ? ' disabled' : '') + '><option value="6"' + selected(config.pagination.perPage, 6) + '>6 items</option><option value="9"' + selected(config.pagination.perPage, 9) + '>9 items</option><option value="12"' + selected(config.pagination.perPage, 12) + '>12 items</option><option value="24"' + selected(config.pagination.perPage, 24) + '>24 items</option></select></div>',
			'</section>',
			'<section class="publishly-editor-subsection">',
			'<h3>Default Filter</h3>',
			'<div class="publishly-filter-builder">',
			renderColumnSelect('editor-default-filter-field', filter.field || '', true),
			'<select id="editor-default-filter-operator" class="publishly-select"><option value="equals"' + selected(filter.operator, 'equals') + '>is</option><option value="not_equals"' + selected(filter.operator, 'not_equals') + '>is not</option><option value="contains"' + selected(filter.operator, 'contains') + '>contains</option></select>',
			'<input id="editor-default-filter-value" class="publishly-input" placeholder="Value, for example Available" value="' + escapeHtml(filter.value || '') + '">',
			'</div>',
			'</section>',
			'<section class="publishly-editor-subsection">',
			'<h3>Visitor Filters</h3>',
			columns.map(function (column) {
				var checked = (config.visitorFilters || []).indexOf(column.key) >= 0 ? ' checked' : '';
				return '<label class="publishly-check-row"><input type="checkbox" name="editor-visitor-filter" value="' + escapeHtml(column.key) + '"' + checked + '><span>' + escapeHtml(column.label) + '</span></label>';
			}).join('') || '<p class="publishly-muted">No columns found in this source.</p>',
			'</section>',
			'<section class="publishly-editor-subsection">',
			'<h3>Default Sort</h3>',
			'<div class="publishly-filter-builder">' + renderColumnSelect('editor-sort-field', config.sort.field || '', true) + '<select class="publishly-select" id="editor-sort-direction"><option value="asc"' + selected(config.sort.direction, 'asc') + '>Low to high</option><option value="desc"' + selected(config.sort.direction, 'desc') + '>High to low</option></select><span></span></div>',
			'</section>',
			'</div>',
			'</div>'
		].join('');
	}

	function renderEditorStyle() {
		var config = ensureConfig();
		var style = config.style || {};
		var swatches = ['#3525cd', '#00687a', '#a44100', '#1b1b24'];

		return [
			'<div class="publishly-editor-section">',
			'<h2>Style</h2>',
			'<p class="publishly-muted">Adjust the Display without changing the spreadsheet data.</p>',
			'<div class="publishly-field" style="margin-top:18px"><span class="publishly-label">Accent color</span><div class="publishly-swatch-row">',
			swatches.map(function (color) {
				var active = (style.accentColor || '#3525cd') === color ? ' is-selected' : '';
				return '<button type="button" class="publishly-swatch' + active + '" style="--swatch:' + color + '" data-editor-color="' + color + '" aria-label="Use color ' + color + '"></button>';
			}).join(''),
			'</div></div>',
			'<div class="publishly-field" style="margin-top:22px"><span class="publishly-label">Card density</span><div class="publishly-segmented" data-editor-segment="density"><button type="button" data-value="comfortable" class="' + selectedClass(style.density, 'comfortable') + '">Comfortable</button><button type="button" data-value="compact" class="' + selectedClass(style.density, 'compact') + '">Compact</button></div></div>',
			'<div class="publishly-field" style="margin-top:22px"><span class="publishly-label">Image shape</span><div class="publishly-segmented" data-editor-segment="imageShape"><button type="button" data-value="rounded" class="' + selectedClass(style.imageShape, 'rounded') + '">Rounded</button><button type="button" data-value="square" class="' + selectedClass(style.imageShape, 'square') + '">Square</button></div></div>',
			'</div>'
		].join('');
	}

	function renderEditorDataSource() {
		var source = state.source || {};
		var columns = source.columns || [];
		var summary = state.updateSummary;

		return [
			'<div class="publishly-editor-section">',
			'<h2>Data Source</h2>',
			'<p class="publishly-muted">Replace the CSV when the spreadsheet changes. Publishly keeps the same Display ID and shows how columns matched.</p>',
			'<div class="publishly-source-grid">',
			renderSourceStat('File', source.original_filename || 'Spreadsheet'),
			renderSourceStat('Rows', source.row_count || 0),
			renderSourceStat('Columns', columns.length),
			renderSourceStat('Updated', source.updated_at || source.created_at || ''),
			'</div>',
			'<div class="publishly-actions">',
			'<label class="publishly-button is-primary">Replace CSV<input type="file" accept=".csv,text/csv" data-editor-update hidden></label>',
			'</div>',
			summary ? renderUpdateSummary(summary) : '',
			'<section class="publishly-editor-subsection" style="margin-top:24px">',
			'<h3>Current Sample</h3>',
			renderTable((state.sample || []).slice(0, 5), columns),
			'</section>',
			'</div>'
		].join('');
	}

	function renderSourceStat(label, value) {
		return '<div class="publishly-source-stat"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>';
	}

	function renderUpdateSummary(summary) {
		return [
			'<section class="publishly-update-summary">',
			'<h3>Update Summary</h3>',
			'<div class="publishly-match-grid">',
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
			'<article class="publishly-match-card">',
			'<span>' + escapeHtml(label) + '</span>',
			'<strong>' + escapeHtml(count) + '</strong>',
			items.length ? '<ul>' + items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('') + '</ul>' : '<p class="publishly-muted">No column changes.</p>',
			'</article>'
		].join('');
	}

	function renderEditorPublish() {
		var id = state.display ? state.display.id : 0;
		var shortcode = '[publishly id="' + id + '"]';

		return [
			'<div class="publishly-editor-section">',
			'<h2>Publish</h2>',
			'<p class="publishly-muted">Use the Publishly Display block in Gutenberg, or paste the shortcode anywhere shortcodes are supported.</p>',
			'<div class="publishly-copy-box">',
			'<code>' + escapeHtml(shortcode) + '</code>',
			'<button type="button" class="publishly-button is-primary" data-editor-copy>Copy shortcode</button>',
			'</div>',
			'<div class="publishly-source-grid" style="margin-top:20px">',
			renderSourceStat('Display ID', id),
			renderSourceStat('Status', state.display.status || 'published'),
			renderSourceStat('Type', labelForType(state.displayType)),
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
				copyText('[publishly id="' + state.display.id + '"]', event.currentTarget);
			});
		}

		root.querySelectorAll('[data-editor-color]').forEach(function (button) {
			button.addEventListener('click', function () {
				ensureConfig().style.accentColor = button.dataset.editorColor;
				state.notice = '';
				render();
			});
		});

		root.querySelectorAll('[data-editor-segment] button').forEach(function (button) {
			button.addEventListener('click', function () {
				ensureConfig().style[button.parentNode.dataset.editorSegment] = button.dataset.value;
				state.notice = '';
				render();
			});
		});

		root.querySelectorAll('.publishly-editor-main input, .publishly-editor-main select').forEach(function (control) {
			if (control.matches('[data-editor-update], [data-editor-color]')) {
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

		if (root.querySelector('#editor-show-search')) {
			config.search.enabled = root.querySelector('#editor-show-search').checked;
			config.filters.enabled = root.querySelector('#editor-show-filters').checked;
			config.pagination.enabled = root.querySelector('#editor-show-pagination').checked;
			config.pagination.perPage = parseInt(valueOf('#editor-per-page'), 10) || 12;
		}

		if (root.querySelector('#editor-default-filter-field')) {
			var filterField = valueOf('#editor-default-filter-field');
			var filterValue = valueOf('#editor-default-filter-value');
			config.defaultFilters = filterField && filterValue ? [{
				field: filterField,
				operator: valueOf('#editor-default-filter-operator') || 'equals',
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
				config: state.config,
				status: state.display.status || 'published'
			}
		}).then(function (display) {
			state.display = display;
			state.displayType = display.display_type || state.displayType;
			state.config = cloneConfig(display.config || state.config);
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
			'<section class="publishly-hero">',
			'<p class="publishly-kicker">Defaults</p>',
			'<h1>Settings</h1>',
			'<p class="publishly-subtitle">Keep MVP settings small so Publishly stays focused on publishing Displays.</p>',
			'</section>',
			'<section class="publishly-panel">',
			'<div class="publishly-form-grid">',
			'<div class="publishly-field"><label for="setting-accent">Default accent color</label><input id="setting-accent" class="publishly-input" type="color" value="' + escapeHtml(settings.defaultAccentColor || '#3525cd') + '"></div>',
			'<div class="publishly-field"><label for="setting-rows">Default rows per page</label><select id="setting-rows" class="publishly-select"><option value="12"' + selected(settings.defaultRowsPerPage, 12) + '>12</option><option value="24"' + selected(settings.defaultRowsPerPage, 24) + '>24</option><option value="48"' + selected(settings.defaultRowsPerPage, 48) + '>48</option></select></div>',
			'</div>',
			'<label class="publishly-check-row" style="margin-top:18px"><input id="setting-delete" type="checkbox"' + (settings.deleteDataOnUninstall ? ' checked' : '') + '><span>Delete plugin data on uninstall</span></label>',
			'<div class="publishly-actions"><button class="publishly-button is-primary" data-settings-save>Save Settings</button></div>',
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
			return '<p class="publishly-muted">No rows found in this spreadsheet.</p>';
		}

		return [
			'<div class="publishly-table-wrap"><table class="publishly-table"><thead><tr>',
			columns.map(function (column) {
				return '<th>' + escapeHtml(column.label) + '</th>';
			}).join(''),
			'</tr></thead><tbody>',
			rows.map(function (row) {
				return '<tr>' + columns.map(function (column) {
					return '<td>' + escapeHtml(row[column.key] || '') + '</td>';
				}).join('') + '</tr>';
			}).join(''),
			'</tbody></table></div>'
		].join('');
	}

	function renderMiniPreview(full) {
		var rows = state.sample && state.sample.length ? state.sample : sampleRowsFromConfig();
		var roles = state.config.fieldRoles || {};
		var title = state.config.title || 'Available Apartments';
		var accent = state.config.style && state.config.style.accentColor ? state.config.style.accentColor : '#3525cd';
		var density = state.config.style && state.config.style.density === 'compact' ? 'is-compact' : 'is-comfortable';
		var imageShape = state.config.style && state.config.style.imageShape === 'square' ? 'is-square' : 'is-rounded';
		var totalRows = state.source && state.source.row_count ? parseInt(state.source.row_count, 10) : rows.length;
		var perPage = state.config.pagination && state.config.pagination.perPage ? parseInt(state.config.pagination.perPage, 10) : 12;
		var previewCount = Math.min(rows.length, full ? perPage : 3);
		return [
			'<div class="publishly-mini-preview ' + density + ' ' + imageShape + '" style="--accent:' + escapeHtml(accent) + '">',
			'<p class="publishly-preview-note">Preview sample. The saved Display will render all ' + escapeHtml(totalRows) + ' rows.</p>',
			'<h2>' + escapeHtml(title) + '</h2>',
			renderMiniControls(),
			'<div class="publishly-mini-grid">',
			rows.slice(0, previewCount).map(function (row) {
				var itemTitle = row[roles.title] || bestRowTitle(row) || 'Display item';
				var subtitle = row[roles.subtitle] || bestRowSubtitle(row, itemTitle) || '';
				var price = row[roles.price] || '';
				var image = roles.image ? row[roles.image] : '';
				return '<article class="publishly-mini-card">' + renderMiniImage(image) + '<div class="publishly-mini-card__body"><h4>' + escapeHtml(itemTitle) + '</h4><p>' + escapeHtml(subtitle) + '</p>' + (price ? '<strong style="color:' + escapeHtml(accent) + '">' + escapeHtml(price) + '</strong>' : '') + renderMiniFields(row, roles) + '</div></article>';
			}).join(''),
			'</div>',
			renderMiniPagination(totalRows, perPage, previewCount),
			'</div>'
		].join('');
	}

	function renderMiniControls() {
		var parts = [];
		if (state.config.search && state.config.search.enabled) {
			parts.push('<input class="publishly-input" placeholder="Search this Display..." />');
		}
		if (state.config.filters && state.config.filters.enabled && state.config.visitorFilters && state.config.visitorFilters.length) {
			parts.push('<div class="publishly-mini-filters">' + state.config.visitorFilters.slice(0, 3).map(function (field) {
				return '<select class="publishly-select"><option>' + escapeHtml(labelForField(field)) + '</option></select>';
			}).join('') + '</div>');
		}

		return parts.length ? '<div class="publishly-mini-controls">' + parts.join('') + '</div>' : '';
	}

	function renderMiniImage(image) {
		if (!image) {
			return '<div class="publishly-mini-card__image"></div>';
		}

		return '<div class="publishly-mini-card__image"><img src="' + escapeHtml(image) + '" alt="" loading="lazy"></div>';
	}

	function renderMiniFields(row, roles) {
		var visibleFields = state.config.visibleFields || [];
		var roleFields = Object.keys(roles || {}).map(function (key) {
			return roles[key];
		});
		var fields = visibleFields.filter(function (field) {
			return field && roleFields.indexOf(field) === -1 && !isTechnicalField(field) && row[field];
		}).slice(0, 4);

		if (!fields.length) {
			return '';
		}

		return '<dl class="publishly-mini-card__fields">' + fields.map(function (field) {
			return '<div><dt>' + escapeHtml(labelForField(field)) + '</dt><dd>' + escapeHtml(row[field]) + '</dd></div>';
		}).join('') + '</dl>';
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
			'<div class="publishly-mini-pagination">',
			'<p>Previewing ' + (totalRows ? 1 : 0) + ' to ' + visibleEnd + ' of ' + totalRows + ' rows' + (visibleEnd < finalEnd ? '<br><span>Final Display shows ' + finalEnd + ' per page.</span>' : '') + '</p>',
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
			style: { accentColor: '#3525cd', density: 'comfortable', imageShape: 'rounded' }
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
		return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'publishly-display';
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
		return '<section class="publishly-empty"><h2>No spreadsheet selected.</h2><p class="publishly-muted">Upload a spreadsheet to continue creating a Display.</p><div class="publishly-actions" style="justify-content:center"><button class="publishly-button is-primary" data-action="upload">Upload Spreadsheet</button></div></section>';
	}

	render();
	if (state.screen === 'displays') {
		loadDisplays();
	} else if (state.screen === 'settings') {
		loadSettings();
	}
})();
