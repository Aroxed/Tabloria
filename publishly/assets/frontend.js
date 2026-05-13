(function () {
	'use strict';

	function cssNumber(element, propertyName) {
		var value = window.getComputedStyle(element).getPropertyValue(propertyName);
		var number = parseFloat(value);
		return Number.isFinite(number) ? number : 0;
	}

	function cssLength(element, propertyName) {
		var styles = window.getComputedStyle(element);
		var value = styles.getPropertyValue(propertyName).trim();
		var number = parseFloat(value);

		if (!Number.isFinite(number)) {
			return 0;
		}

		if (value.endsWith('rem')) {
			return number * (parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16);
		}

		if (value.endsWith('em')) {
			return number * (parseFloat(styles.fontSize) || 16);
		}

		return number;
	}

	function syncBreakoutWidth(display) {
		applyContainerMode(display);
		if (display.classList.contains('is-contained')) {
			display.style.setProperty('--publishly-breakout-offset', '0px');
			return;
		}

		if (!display.classList.contains('is-width-wide') && !display.classList.contains('is-width-full')) {
			return;
		}

		var viewportWidth = document.documentElement.clientWidth || window.innerWidth || 0;
		if (!viewportWidth) {
			return;
		}

		display.style.setProperty('--publishly-viewport-width', viewportWidth + 'px');
		display.style.setProperty('--publishly-viewport-half', (viewportWidth / 2) + 'px');

		var currentOffset = cssNumber(display, '--publishly-breakout-offset');
		var rect = display.getBoundingClientRect();
		var unshiftedLeft = rect.left - currentOffset;
		var targetLeft = Math.max(0, (viewportWidth - rect.width) / 2);

		if (display.classList.contains('is-width-full')) {
			targetLeft = Math.min(cssLength(display, '--publishly-full-width-gutter'), viewportWidth / 2);
		}

		var nextOffset = targetLeft - unshiftedLeft;

		display.style.setProperty('--publishly-breakout-offset', (Math.abs(nextOffset) < 0.5 ? 0 : nextOffset).toFixed(3) + 'px');
	}

	function syncBreakoutWidths(displays) {
		displays.forEach(syncBreakoutWidth);
	}

	function applyContainerMode(display) {
		var tableContainer = display.closest('td, th, .wp-block-table');
		var semanticContainer = display.closest('.wp-block-navigation, .wp-block-list, .wp-block-quote, blockquote');
		var unsafeContainer = tableContainer || semanticContainer;
		var width = display.parentElement ? display.parentElement.getBoundingClientRect().width : display.getBoundingClientRect().width;
		var fixedContained = display.dataset.contained === 'true';

		if (unsafeContainer) {
			display.classList.add('is-contained', 'is-unsafe-container');
			display.dataset.contained = 'true';
			fixedContained = true;
			maybeWarnUnsafeContainer(display, tableContainer ? 'table' : 'semantic');
		}

		if (width > 0 && width < 520) {
			display.classList.add('is-narrow-container', 'is-auto-contained', 'is-contained');
		} else {
			display.classList.remove('is-narrow-container');
			if (!fixedContained && display.classList.contains('is-auto-contained')) {
				display.classList.remove('is-contained');
			}
			display.classList.remove('is-auto-contained');
		}
	}

	function maybeWarnUnsafeContainer(display, reason) {
		if (!document.body.classList.contains('admin-bar') || display.querySelector('.publishly-embed-warning')) {
			return;
		}

		var warning = document.createElement('div');
		warning.className = 'publishly-embed-warning';
		warning.textContent = reason === 'table'
			? 'Publishly Display is inside a table. Use Columns, Group, or Publishly Display Grid instead.'
			: 'Publishly Display is inside a semantic block. Use Group, Columns, or Publishly Display Grid instead.';
		display.insertBefore(warning, display.firstChild);
	}

	function initDisplay(display) {
		var cards = Array.prototype.slice.call(display.querySelectorAll('[data-publishly-item]'));
		var search = display.querySelector('[data-publishly-search]');
		var filters = Array.prototype.slice.call(display.querySelectorAll('[data-publishly-filter]'));
		var pagination = display.querySelector('.publishly-display__pagination');
		var status = display.querySelector('.publishly-display__status');
		var empty = display.querySelector('.publishly-display__empty');
		var perPage = parseInt(display.dataset.perPage || '12', 10);
		var usePagination = display.dataset.pagination === 'true';
		var currentPage = 1;

		function normalizeText(value) {
			return String(value || '').normalize('NFKC').toLocaleLowerCase();
		}

		function matches(card) {
			var query = search ? normalizeText(search.value.trim()) : '';
			if (query && normalizeText(card.dataset.search).indexOf(query) === -1) {
				return false;
			}

			return filters.every(function (filter) {
				var value = filter.value;
				if (!value) {
					return true;
				}
				return String(card.getAttribute('data-filter-' + filter.dataset.publishlyFilter) || '') === value;
			});
		}

		function render() {
			var matched = cards.filter(matches);
			var totalPages = usePagination ? Math.max(1, Math.ceil(matched.length / perPage)) : 1;
			currentPage = Math.min(currentPage, totalPages);
			var start = usePagination ? (currentPage - 1) * perPage : 0;
			var end = usePagination ? start + perPage : matched.length;

			cards.forEach(function (card) {
				card.hidden = true;
			});

			matched.slice(start, end).forEach(function (card) {
				card.hidden = false;
			});

			if (empty) {
				empty.hidden = matched.length > 0;
			}

			renderStatus(start, end, matched.length);
			renderPagination(totalPages, matched.length);
			syncBreakoutWidth(display);
		}

		function renderStatus(start, end, totalItems) {
			if (!status) {
				return;
			}

			if (totalItems === 0) {
				status.textContent = 'Showing 0 rows';
				return;
			}

			status.textContent = 'Showing ' + (start + 1) + ' to ' + Math.min(end, totalItems) + ' of ' + totalItems + ' rows';
		}

		function renderPagination(totalPages, totalItems) {
			if (!pagination) {
				return;
			}

			if (!usePagination || totalItems === 0) {
				pagination.innerHTML = '';
				return;
			}

			if (totalPages <= 1) {
				pagination.innerHTML = '<span class="publishly-display__page-note">Page 1 of 1</span>';
				return;
			}

			var html = '<button type="button" data-page="' + Math.max(1, currentPage - 1) + '"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
			for (var page = 1; page <= totalPages; page++) {
				if (totalPages > 7 && page > 2 && page < totalPages - 1 && Math.abs(page - currentPage) > 1) {
					if (page === 3 || page === totalPages - 2) {
						html += '<span class="publishly-display__page-note">...</span>';
					}
					continue;
				}
				html += '<button type="button" class="' + (page === currentPage ? 'is-active' : '') + '" data-page="' + page + '">' + page + '</button>';
			}
			html += '<button type="button" data-page="' + Math.min(totalPages, currentPage + 1) + '"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
			pagination.innerHTML = html;

			pagination.querySelectorAll('[data-page]').forEach(function (button) {
				button.addEventListener('click', function () {
					currentPage = parseInt(button.dataset.page, 10);
					render();
				});
			});
		}

		if (search) {
			search.addEventListener('input', function () {
				currentPage = 1;
				render();
			});
		}

		filters.forEach(function (filter) {
			filter.addEventListener('change', function () {
				currentPage = 1;
				render();
			});
		});

		render();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var displays = Array.prototype.slice.call(document.querySelectorAll('.publishly-display'));
		var scheduleBreakoutSync = function () {
			window.requestAnimationFrame(function () {
				syncBreakoutWidths(displays);
			});
		};

		displays.forEach(applyContainerMode);
		if (document.querySelector('.publishly-display.is-width-full:not(.is-contained)')) {
			document.documentElement.classList.add('publishly-has-full-width-display');
		}
		displays.forEach(initDisplay);
		syncBreakoutWidths(displays);
		window.addEventListener('load', scheduleBreakoutSync);
		window.addEventListener('resize', scheduleBreakoutSync);
	});
})();
