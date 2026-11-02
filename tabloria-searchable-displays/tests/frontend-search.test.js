const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');

// Run the shipped frontend script through its DOM events, without a build step.
function mount({ hide, pagination = false, searchEnabled = true, showResultCount = true, resultLabel = 'rows' } = {}) {
	const classes = { contains: () => false, add() {}, remove() {} };
	const element = () => ({
		dataset: {}, hidden: false, value: '', innerHTML: '', textContent: '',
		listeners: {}, addEventListener(event, callback) { this.listeners[event] = callback; },
		querySelectorAll() { return []; },
	});
	const rows = ['123 Alpha', '12345 Beta', 'Other'].map((text, index) => ({
		...element(), dataset: { search: text },
		getAttribute() { return index === 1 ? 'B' : 'A'; },
	}));
	const search = searchEnabled ? element() : null;
	const filter = { ...element(), dataset: { tabloriaFilter: 'category' } };
	const pager = element();
	const reset = element();
	pager.querySelectorAll = () => [...pager.innerHTML.matchAll(/data-page="(\d+)"/g)].map(match => {
		const button = { ...element(), dataset: { page: match[1] } };
		pager.buttons.push(button);
		return button;
	});
	pager.buttons = [];
	const status = element();
	const empty = element();
	const display = {
		dataset: { hideRowsUntilSearch: hide, pagination: String(pagination), showResultCount: String(showResultCount), resultLabel, perPage: '1' },
		classList: classes, closest: () => null,
		getBoundingClientRect: () => ({ width: 800 }),
		querySelector(selector) { return ({
			'[data-tabloria-search]': search,
			'.tabloria-display__pagination': pager,
			'.tabloria-display__status': status,
			'.tabloria-display__empty': empty,
			'[data-tabloria-reset-filters]': reset,
		})[selector]; },
		querySelectorAll(selector) {
			if (selector === '[data-tabloria-item]') return rows;
			if (selector === '[data-tabloria-filter]') return [filter];
			return [];
		},
	};
	const document = {
		addEventListener(event, callback) { callback(); },
		querySelectorAll: () => [display], querySelector: () => null,
		documentElement: { classList: classes },
	};
	vm.runInNewContext(readFileSync(require.resolve('../assets/frontend.js'), 'utf8'), {
		document, window: { addEventListener() {} },
	});
	return {
		visible: () => rows.filter(row => !row.hidden).map(row => row.dataset.search),
		query(value) { search.value = value; search.listeners.input(); },
		filter(value) { filter.value = value; filter.listeners.change(); },
		page(value) { pager.buttons.find(button => button.dataset.page === String(value)).listeners.click(); },
		reset() { reset.listeners.click(); },
		pager, status, empty, resetButton: reset,
	};
}

for (const hide of [undefined, 'false']) {
	test(`default/disabled mode (${hide}) retains search and clear behavior`, () => {
		const table = mount({ hide });
		assert.equal(table.visible().length, 3);
		table.query('123');
		assert.deepEqual(table.visible(), ['123 Alpha', '12345 Beta']);
		table.query('');
		assert.equal(table.visible().length, 3);
	});
}

test('enabled mode hides initially, searches, changes query, and hides on clear', () => {
	const table = mount({ hide: 'true' });
	assert.deepEqual(table.visible(), []);
	assert.equal(table.status.textContent, 'Showing 0 rows');
	assert.equal(table.pager.innerHTML, '');
	table.query('123');
	assert.deepEqual(table.visible(), ['123 Alpha', '12345 Beta']);
	table.query('12345');
	assert.deepEqual(table.visible(), ['12345 Beta']);
	table.query('ALPHA');
	assert.deepEqual(table.visible(), ['123 Alpha']);
	table.query('absent');
	assert.deepEqual(table.visible(), []);
	table.query('');
	assert.deepEqual(table.visible(), []);
	table.query('   ');
	assert.deepEqual(table.visible(), []);
	table.filter('A');
	assert.deepEqual(table.visible(), []);
});

test('active search preserves row order, pagination and visitor filters', () => {
	const table = mount({ hide: 'true', pagination: true });
	table.query('123');
	assert.deepEqual(table.visible(), ['123 Alpha']);
	table.page(2);
	assert.deepEqual(table.visible(), ['12345 Beta']);
	table.query('12345');
	assert.deepEqual(table.visible(), ['12345 Beta']);
	assert.equal(table.status.textContent, 'Showing 1 to 1 of 1 rows');
	table.query('123');
	table.filter('B');
	assert.deepEqual(table.visible(), ['12345 Beta']);
	table.query('');
	assert.deepEqual(table.visible(), []);
	assert.equal(table.pager.innerHTML, '');
	table.query('123');
	assert.deepEqual(table.visible(), ['12345 Beta']);
});

test('disabled search cannot leave rows inaccessible', () => {
	assert.equal(mount({ hide: 'true', searchEnabled: false }).visible().length, 3);
});


test('result count can be hidden without changing filtering or pagination', () => {
	const table = mount({ pagination: true, showResultCount: false });
	assert.equal(table.status.hidden, true);
	assert.equal(table.status.textContent, '');
	table.query('123');
	assert.deepEqual(table.visible(), ['123 Alpha']);
	assert.equal(table.status.hidden, true);
	assert.equal(table.status.textContent, '');
});

test('Reset all clears active filters and the search query', () => {
	const table = mount({ resultLabel: 'offers' });
	table.query('123');
	table.filter('B');
	assert.deepEqual(table.visible(), ['12345 Beta']);
	assert.equal(table.resetButton.hidden, false);
	table.reset();
	assert.deepEqual(table.visible(), ['123 Alpha', '12345 Beta', 'Other']);
	assert.equal(table.resetButton.hidden, true);
	assert.match(table.status.textContent, /offers$/);
});

test('coupon reveal opens normally and copy writes the coupon code', async () => {
	const listeners = {};
	const reveal = { hidden: true, querySelector: selector => selector === '[data-tabloria-copy-status]' ? status : null };
	const status = { textContent: '' };
	const card = { querySelector: selector => selector === '[data-tabloria-coupon-reveal]' ? reveal : null };
	const show = {
		listeners: {}, attributes: {},
		addEventListener(event, callback) { this.listeners[event] = callback; },
		closest(selector) { return selector === '.tabloria-coupon' ? card : null; },
		setAttribute(name, value) { this.attributes[name] = value; },
	};
	const copy = {
		dataset: { couponCode: 'SAVE20', copiedLabel: 'Copied now' }, listeners: {},
		addEventListener(event, callback) { this.listeners[event] = callback; },
		closest(selector) { return selector === '[data-tabloria-coupon-reveal]' ? reveal : null; },
	};
	const classes = { contains: () => false, add() {}, remove() {} };
	const empty = { hidden: true };
	const display = {
		dataset: { pagination: 'false', perPage: '12' }, classList: classes, closest: () => null,
		getBoundingClientRect: () => ({ width: 800 }),
		querySelector(selector) {
			return ({ '.tabloria-display__empty': empty })[selector] || null;
		},
		querySelectorAll(selector) {
			if (selector === '[data-tabloria-show-coupon]') return [show];
			if (selector === '[data-tabloria-copy-coupon]') return [copy];
			return [];
		},
	};
	let copied = '';
	const document = {
		addEventListener(event, callback) { listeners[event] = callback; callback(); },
		querySelectorAll: () => [display], querySelector: () => null,
		documentElement: { classList: classes }, body: { classList: classes },
	};
	vm.runInNewContext(readFileSync(require.resolve('../assets/frontend.js'), 'utf8'), {
		document,
		navigator: { clipboard: { writeText(value) { copied = value; return Promise.resolve(); } } },
		window: { addEventListener() {} }, Promise, Error,
	});

	show.listeners.click();
	assert.equal(reveal.hidden, false);
	assert.equal(show.attributes['aria-expanded'], 'true');
	copy.listeners.click();
	await Promise.resolve();
	await Promise.resolve();
	assert.equal(copied, 'SAVE20');
	assert.equal(status.textContent, 'Copied now');
});
