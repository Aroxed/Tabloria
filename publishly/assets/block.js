(function (blocks, element, components, blockEditor, apiFetch, i18n) {
	'use strict';

	var el = element.createElement;
	var useEffect = element.useEffect;
	var useState = element.useState;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;
	var Button = components.Button;
	var Spinner = components.Spinner;
	var Notice = components.Notice;

	if (apiFetch.createNonceMiddleware && window.publishlyBlock && window.publishlyBlock.nonce) {
		apiFetch.use(apiFetch.createNonceMiddleware(window.publishlyBlock.nonce));
	}

	function api(path) {
		return apiFetch({ url: String(window.publishlyBlock.restUrl).replace(/\/$/, '') + path });
	}

	function Edit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps({ className: 'publishly-block-editor' });
		var displayId = attributes.displayId || 0;
		var showTitle = attributes.showTitle !== false;
		var overrideTitle = attributes.overrideTitle || '';
		var displaysState = useState([]);
		var displays = displaysState[0];
		var setDisplays = displaysState[1];
		var loadingState = useState(true);
		var loading = loadingState[0];
		var setLoading = loadingState[1];
		var previewState = useState(null);
		var preview = previewState[0];
		var setPreview = previewState[1];
		var errorState = useState('');
		var error = errorState[0];
		var setError = errorState[1];

		useEffect(function () {
			setLoading(true);
			api('/displays')
				.then(function (items) {
					setDisplays(items || []);
					setError('');
					setLoading(false);
					if (!displayId && items && items[0]) {
						setAttributes({ displayId: parseInt(items[0].id, 10) });
					}
				})
				.catch(function (err) {
					setError(err.message || __('Could not load Publishly Displays.', 'publishly'));
					setLoading(false);
				});
		}, []);

		useEffect(function () {
			if (!displayId) {
				setPreview(null);
				return;
			}
			setPreview(null);
			api('/displays/' + displayId + '/render-data')
				.then(function (data) {
					setPreview(data);
					setError('');
				})
				.catch(function (err) {
					setError(err.message || __('Could not load this Display preview.', 'publishly'));
				});
		}, [displayId]);

		var options = [{ label: __('Choose a Published Display', 'publishly'), value: 0 }].concat(displays.map(function (display) {
			return { label: display.name, value: parseInt(display.id, 10) };
		}));

		return el(
			'div',
			blockProps,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __('Publishly Display', 'publishly'), initialOpen: true },
					el(SelectControl, {
						label: __('Select Display', 'publishly'),
						value: displayId,
						options: options,
						onChange: function (value) {
							setAttributes({ displayId: parseInt(value, 10) || 0 });
						}
					}),
					el(ToggleControl, {
						label: __('Show title', 'publishly'),
						checked: showTitle,
						onChange: function (value) {
							setAttributes({ showTitle: value });
						}
					}),
					el(TextControl, {
						label: __('Override title', 'publishly'),
						value: overrideTitle,
						onChange: function (value) {
							setAttributes({ overrideTitle: value });
						}
					})
				)
			),
			loading ? el(Placeholder, { label: __('Publishly Display', 'publishly') }, el(Spinner)) : null,
			!loading && error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
			!loading && !displays.length ? el(
				Placeholder,
				{
					icon: 'screenoptions',
					label: __('Publishly Display', 'publishly'),
					instructions: __('Create a Published Display in Publishly, then insert it here.', 'publishly')
				},
				el(Button, { variant: 'primary', href: 'admin.php?page=publishly' }, __('Create Display', 'publishly'))
			) : null,
			!loading && displays.length ? el(
				'div',
				{ className: 'publishly-editor-preview' },
				el(
					'div',
					{ className: 'publishly-editor-preview__toolbar' },
					el('strong', null, __('Publishly Display', 'publishly')),
					el(SelectControl, {
						value: displayId,
						options: options,
						onChange: function (value) {
							setAttributes({ displayId: parseInt(value, 10) || 0 });
						}
					}),
					displayId ? el(Button, {
						variant: 'secondary',
						href: editDisplaysUrl()
					}, __('Edit this Display in Publishly', 'publishly')) : null
				),
				renderPreview(preview, showTitle, overrideTitle)
			) : null
		);
	}

	function editDisplaysUrl() {
		return String(window.publishlyBlock && window.publishlyBlock.adminUrl ? window.publishlyBlock.adminUrl : 'admin.php?page=') + 'publishly-displays';
	}

	function renderPreview(preview, showTitle, overrideTitle) {
		if (!preview || !preview.display) {
			return el('div', { className: 'publishly-editor-preview__body publishly-editor-preview__body--loading' }, el(Spinner));
		}

		var config = preview.display.config || {};
		var roles = config.fieldRoles || {};
		var rows = preview.rows || [];
		var title = overrideTitle || config.title || preview.display.name;
		var source = preview.source || {};
		var displayType = preview.display.display_type || config.displayType || 'searchable_listings';
		var accent = config.style && config.style.accentColor ? config.style.accentColor : '#3525cd';
		var visibleFields = config.visibleFields || [];

		return el(
			'div',
			{ className: 'publishly-editor-preview__body publishly-editor-preview__body--' + displayType, style: { '--publishly-accent': accent } },
			el(
				'div',
				{ className: 'publishly-editor-preview__summary' },
				el('span', null, labelForType(displayType)),
				el('span', null, (source.row_count || rows.length || 0) + ' ' + __('rows', 'publishly')),
				source.original_filename ? el('span', null, source.original_filename) : null
			),
			showTitle ? el('h3', null, title) : null,
			displayType === 'simple_data_table' ? renderTablePreview(rows, visibleFields, preview.source) : renderCardPreview(rows, roles, visibleFields, displayType),
			el('p', { className: 'publishly-editor-preview__note' }, __('Live editor preview. The frontend renders from the saved Published Display.', 'publishly'))
		);
	}

	function renderCardPreview(rows, roles, visibleFields, displayType) {
		return el(
			'div',
			{ className: 'publishly-editor-preview__grid publishly-editor-preview__grid--' + displayType },
			rows.slice(0, 6).map(function (row, index) {
				var itemTitle = row[roles.title] || bestTitle(row) || __('Display row', 'publishly');
				var subtitle = row[roles.subtitle] || bestSubtitle(row, itemTitle) || '';
				var price = row[roles.price] || '';
				var image = roles.image ? row[roles.image] : '';
				var badge = roles.badge ? row[roles.badge] : '';
				return el(
					'article',
					{ className: 'publishly-editor-preview__card', key: index },
					renderPreviewImage(image),
					badge ? el('span', { className: 'publishly-editor-preview__badge' }, badge) : null,
					el('strong', null, itemTitle),
					subtitle ? el('span', null, subtitle) : null,
					price ? el('em', null, price) : null,
					renderPreviewFields(row, roles, visibleFields)
				);
			})
		);
	}

	function renderPreviewImage(image) {
		if (!image) {
			return el('div', { className: 'publishly-editor-preview__image' });
		}

		return el('div', { className: 'publishly-editor-preview__image' }, el('img', { src: image, alt: '' }));
	}

	function renderPreviewFields(row, roles, visibleFields) {
		var roleFields = Object.keys(roles || {}).map(function (key) {
			return roles[key];
		});
		var fields = (visibleFields || []).filter(function (field) {
			return field && roleFields.indexOf(field) === -1 && !isTechnicalField(field) && row[field];
		}).slice(0, 3);

		if (!fields.length) {
			return null;
		}

		return el(
			'dl',
			{ className: 'publishly-editor-preview__fields' },
			fields.map(function (field) {
				return el('div', { key: field }, el('dt', null, humanize(field)), el('dd', null, row[field]));
			})
		);
	}

	function renderTablePreview(rows, visibleFields, source) {
		var columns = tableColumns(visibleFields, source, rows);
		return el(
			'div',
			{ className: 'publishly-editor-preview__table-wrap' },
			el(
				'table',
				{ className: 'publishly-editor-preview__table' },
				el('thead', null, el('tr', null, columns.map(function (column) {
					return el('th', { key: column.key }, column.label);
				}))),
				el('tbody', null, rows.slice(0, 6).map(function (row, index) {
					return el('tr', { key: index }, columns.map(function (column) {
						return el('td', { key: column.key }, row[column.key] || '');
					}));
				}))
			)
		);
	}

	function tableColumns(visibleFields, source, rows) {
		var sourceColumns = source && source.columns ? source.columns : [];
		var allowed = (visibleFields || []).length ? visibleFields : Object.keys(rows[0] || {});
		var columns = allowed.filter(function (field) {
			return !isTechnicalField(field);
		}).slice(0, 6).map(function (field) {
			var column = sourceColumns.find(function (item) {
				return item.key === field;
			});
			return { key: field, label: column ? column.label : humanize(field) };
		});

		return columns.length ? columns : [{ key: 'row', label: __('Row', 'publishly') }];
	}

	function bestTitle(row) {
		var keys = Object.keys(row || {});
		var key = keys.find(function (field) {
			return /(^|_)(name|title)($|_)/.test(field) && !/email|code|number|status|type/.test(field) && row[field];
		});
		return key ? row[key] : row[keys[0]];
	}

	function bestSubtitle(row, title) {
		var keys = Object.keys(row || {});
		var key = keys.find(function (field) {
			return /(^|_)(category|brand|role|location|building|department|course)($|_)/.test(field) && row[field] && row[field] !== title;
		});
		return key ? row[key] : '';
	}

	function isTechnicalField(field) {
		return ['entity', 'object_type', 'row_type', 'record_type'].indexOf(field) >= 0;
	}

	function humanize(field) {
		return String(field || 'Field').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/\w\S*/g, function (word) {
			return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
		});
	}

	function labelForType(type) {
		var labels = {
			searchable_listings: __('Searchable Listings', 'publishly'),
			product_catalog: __('Product Catalog', 'publishly'),
			team_directory: __('Team Directory', 'publishly'),
			event_schedule: __('Event Schedule', 'publishly'),
			pricing_table: __('Pricing Table', 'publishly'),
			simple_data_table: __('Simple Data Table', 'publishly')
		};
		return labels[type] || __('Published Display', 'publishly');
	}

	blocks.registerBlockType('publishly/display', {
		apiVersion: 2,
		title: __('Publishly Display', 'publishly'),
		description: __('Insert a searchable display created from your spreadsheet.', 'publishly'),
		icon: 'screenoptions',
		category: 'widgets',
		attributes: {
			displayId: { type: 'number', default: 0 },
			showTitle: { type: 'boolean', default: true },
			overrideTitle: { type: 'string', default: '' }
		},
		edit: Edit,
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.apiFetch, window.wp.i18n);
