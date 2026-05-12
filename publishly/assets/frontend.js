(function () {
	'use strict';

	function initDisplay(display) {
		var cards = Array.prototype.slice.call(display.querySelectorAll('.publishly-card'));
		var search = display.querySelector('[data-publishly-search]');
		var filters = Array.prototype.slice.call(display.querySelectorAll('[data-publishly-filter]'));
		var pagination = display.querySelector('.publishly-display__pagination');
		var status = display.querySelector('.publishly-display__status');
		var empty = display.querySelector('.publishly-display__empty');
		var perPage = parseInt(display.dataset.perPage || '12', 10);
		var usePagination = display.dataset.pagination === 'true';
		var currentPage = 1;

		function matches(card) {
			var query = search ? search.value.trim().toLowerCase() : '';
			if (query && String(card.dataset.search || '').indexOf(query) === -1) {
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
		document.querySelectorAll('.publishly-display').forEach(initDisplay);
	});
})();
