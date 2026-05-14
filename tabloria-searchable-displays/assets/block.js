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
	var CheckboxControl = components.CheckboxControl;

	if (apiFetch.createNonceMiddleware && window.tabloriaBlock && window.tabloriaBlock.nonce) {
		apiFetch.use(apiFetch.createNonceMiddleware(window.tabloriaBlock.nonce));
	}

	function api(path) {
		return apiFetch({ url: String(window.tabloriaBlock.restUrl).replace(/\/$/, '') + path });
	}

	function Edit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps({ className: 'tabloria-block-editor' });
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
					setError(err.message || __('Could not load Tabloria Displays.', 'tabloria-searchable-displays'));
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
					setError(err.message || __('Could not load this Display preview.', 'tabloria-searchable-displays'));
				});
		}, [displayId]);

		var options = [{ label: __('Choose a Published Display', 'tabloria-searchable-displays'), value: 0 }].concat(displays.map(function (display) {
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
					{ title: __('Tabloria Display', 'tabloria-searchable-displays'), initialOpen: true },
					el(SelectControl, {
						label: __('Select Display', 'tabloria-searchable-displays'),
						value: displayId,
						options: options,
						onChange: function (value) {
							setAttributes({ displayId: parseInt(value, 10) || 0 });
						}
					}),
					el(ToggleControl, {
						label: __('Show title', 'tabloria-searchable-displays'),
						checked: showTitle,
						onChange: function (value) {
							setAttributes({ showTitle: value });
						}
					}),
					el(TextControl, {
						label: __('Override title', 'tabloria-searchable-displays'),
						value: overrideTitle,
						onChange: function (value) {
							setAttributes({ overrideTitle: value });
						}
					})
				)
			),
			loading ? el(Placeholder, { label: __('Tabloria Display', 'tabloria-searchable-displays') }, el(Spinner)) : null,
			!loading && error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
			!loading && !displays.length ? el(
				Placeholder,
				{
					icon: 'screenoptions',
					label: __('Tabloria Display', 'tabloria-searchable-displays'),
					instructions: __('Create a Published Display in Tabloria, then insert it here.', 'tabloria-searchable-displays')
				},
				el(Button, { variant: 'primary', href: 'admin.php?page=tabloria' }, __('Create Display', 'tabloria-searchable-displays'))
			) : null,
			!loading && displays.length ? el(
				'div',
				{ className: 'tabloria-editor-preview' },
				el(
					'div',
					{ className: 'tabloria-editor-preview__toolbar' },
					el('strong', null, __('Tabloria Display', 'tabloria-searchable-displays')),
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
					}, __('Edit this Display in Tabloria', 'tabloria-searchable-displays')) : null
				),
				renderPreview(preview, showTitle, overrideTitle)
			) : null
		);
	}

	function GridEdit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps({ className: 'tabloria-block-editor tabloria-block-editor--grid' });
		var displayIds = normalizeIds(attributes.displayIds || []);
		var columns = parseInt(attributes.columns || 2, 10) || 2;
		var gap = attributes.gap || 'normal';
		var showTitles = attributes.showTitles !== false;
		var displaysState = useState([]);
		var displays = displaysState[0];
		var setDisplays = displaysState[1];
		var loadingState = useState(true);
		var loading = loadingState[0];
		var setLoading = loadingState[1];
		var errorState = useState('');
		var error = errorState[0];
		var setError = errorState[1];
		var selected = selectedDisplays(displays, displayIds);

		useEffect(function () {
			setLoading(true);
			api('/displays')
				.then(function (items) {
					setDisplays(items || []);
					setError('');
					setLoading(false);
				})
				.catch(function (err) {
					setError(err.message || __('Could not load Tabloria Displays.', 'tabloria-searchable-displays'));
					setLoading(false);
				});
		}, []);

		function toggleDisplay(displayId, checked) {
			var id = parseInt(displayId, 10) || 0;
			var next = displayIds.filter(function (item) {
				return item !== id;
			});

			if (checked && id) {
				next.push(id);
			}

			setAttributes({ displayIds: next });
		}

		return el(
			'div',
			blockProps,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __('Grid Layout', 'tabloria-searchable-displays'), initialOpen: true },
					el(SelectControl, {
						label: __('Columns', 'tabloria-searchable-displays'),
						value: columns,
						options: [
							{ label: __('1 column', 'tabloria-searchable-displays'), value: 1 },
							{ label: __('2 columns', 'tabloria-searchable-displays'), value: 2 },
							{ label: __('3 columns', 'tabloria-searchable-displays'), value: 3 },
							{ label: __('4 columns', 'tabloria-searchable-displays'), value: 4 }
						],
						onChange: function (value) {
							setAttributes({ columns: parseInt(value, 10) || 2 });
						}
					}),
					el(SelectControl, {
						label: __('Gap', 'tabloria-searchable-displays'),
						value: gap,
						options: [
							{ label: __('Compact', 'tabloria-searchable-displays'), value: 'compact' },
							{ label: __('Normal', 'tabloria-searchable-displays'), value: 'normal' },
							{ label: __('Spacious', 'tabloria-searchable-displays'), value: 'spacious' }
						],
						onChange: function (value) {
							setAttributes({ gap: value || 'normal' });
						}
					}),
					el(ToggleControl, {
						label: __('Show Display titles', 'tabloria-searchable-displays'),
						checked: showTitles,
						onChange: function (value) {
							setAttributes({ showTitles: value });
						}
					})
				),
				el(
					PanelBody,
					{ title: __('Published Displays', 'tabloria-searchable-displays'), initialOpen: true },
					loading ? el(Spinner) : null,
					!loading && error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
					!loading && !displays.length ? el('p', null, __('Create a Published Display first.', 'tabloria-searchable-displays')) : null,
					!loading && displays.length ? displays.map(function (display) {
						var id = parseInt(display.id, 10) || 0;
						return el(CheckboxControl, {
							key: id,
							label: display.name,
							checked: displayIds.indexOf(id) >= 0,
							onChange: function (checked) {
								toggleDisplay(id, checked);
							}
						});
					}) : null
				)
			),
			loading ? el(Placeholder, { label: __('Tabloria Display Grid', 'tabloria-searchable-displays') }, el(Spinner)) : null,
			!loading && error ? el(Notice, { status: 'error', isDismissible: false }, error) : null,
			!loading && !displays.length ? el(
				Placeholder,
				{
					icon: 'screenoptions',
					label: __('Tabloria Display Grid', 'tabloria-searchable-displays'),
					instructions: __('Create Published Displays in Tabloria, then arrange them here.', 'tabloria-searchable-displays')
				},
				el(Button, { variant: 'primary', href: 'admin.php?page=tabloria' }, __('Create Display', 'tabloria-searchable-displays'))
			) : null,
			!loading && displays.length ? renderGridEditorPreview(selected, columns, gap, showTitles, toggleDisplay) : null
		);
	}

	function normalizeIds(ids) {
		return (Array.isArray(ids) ? ids : []).map(function (id) {
			return parseInt(id, 10) || 0;
		}).filter(Boolean);
	}

	function selectedDisplays(displays, displayIds) {
		return displayIds.map(function (id) {
			return displays.find(function (display) {
				return parseInt(display.id, 10) === id;
			});
		}).filter(Boolean);
	}

	function renderGridEditorPreview(selected, columns, gap, showTitles, toggleDisplay) {
		if (!selected.length) {
			return el(
				Placeholder,
				{
					icon: 'screenoptions',
					label: __('Tabloria Display Grid', 'tabloria-searchable-displays'),
					instructions: __('Select two or more Published Displays from the block sidebar.', 'tabloria-searchable-displays')
				}
			);
		}

		return el(
			'div',
			{ className: 'tabloria-editor-preview tabloria-editor-grid-preview' },
			el(
				'div',
				{ className: 'tabloria-editor-preview__toolbar' },
				el('strong', null, __('Tabloria Display Grid', 'tabloria-searchable-displays')),
				el('span', null, selected.length + ' ' + __('Displays', 'tabloria-searchable-displays'))
			),
			el(
				'div',
				{
					className: 'tabloria-editor-grid-preview__items is-gap-' + gap,
					style: { '--tabloria-grid-columns': columns }
				},
				selected.map(function (display) {
					var displayType = display.display_type || (display.config && display.config.displayType) || 'searchable_listings';
					return el(
						'article',
						{ className: 'tabloria-editor-grid-preview__item', key: display.id },
						el('strong', null, showTitles ? display.name : __('Untitled Display', 'tabloria-searchable-displays')),
						el('span', null, labelForType(displayType)),
						el(Button, {
							variant: 'tertiary',
							onClick: function () {
								toggleDisplay(display.id, false);
							}
						}, __('Remove', 'tabloria-searchable-displays'))
					);
				})
			)
		);
	}

	function editDisplaysUrl() {
		return String(window.tabloriaBlock && window.tabloriaBlock.adminUrl ? window.tabloriaBlock.adminUrl : 'admin.php?page=') + 'tabloria-displays';
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

	function renderPreview(preview, showTitle, overrideTitle) {
		if (!preview || !preview.display) {
			return el('div', { className: 'tabloria-editor-preview__body tabloria-editor-preview__body--loading' }, el(Spinner));
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
			{ className: 'tabloria-editor-preview__body tabloria-editor-preview__body--' + displayType, style: { '--tabloria-accent': accent } },
			el(
				'div',
				{ className: 'tabloria-editor-preview__summary' },
				el('span', null, labelForType(displayType)),
				el('span', null, (source.row_count || rows.length || 0) + ' ' + __('rows', 'tabloria-searchable-displays')),
				source.original_filename ? el('span', null, source.original_filename) : null
			),
			showTitle ? el('h3', null, title) : null,
			displayType === 'simple_data_table' ? renderTablePreview(rows, visibleFields, preview.source) : renderCardPreview(rows, roles, visibleFields, displayType),
			el('p', { className: 'tabloria-editor-preview__note' }, __('Live editor preview. The frontend renders from the saved Published Display.', 'tabloria-searchable-displays'))
		);
	}

	function renderCardPreview(rows, roles, visibleFields, displayType) {
		return el(
			'div',
			{ className: 'tabloria-editor-preview__grid tabloria-editor-preview__grid--' + displayType },
			rows.slice(0, 6).map(function (row, index) {
				var itemTitle = row[roles.title] || bestTitle(row) || __('Display row', 'tabloria-searchable-displays');
				var subtitle = row[roles.subtitle] || bestSubtitle(row, itemTitle) || '';
				var price = row[roles.price] || '';
				var image = roles.image ? row[roles.image] : '';
				var badge = roles.badge ? row[roles.badge] : '';
				return el(
					'article',
					{ className: 'tabloria-editor-preview__card', key: index },
					renderPreviewImage(image),
					badge ? el('span', { className: 'tabloria-editor-preview__badge' }, badge) : null,
					el('strong', null, itemTitle),
					subtitle ? el('span', null, subtitle) : null,
					price ? el('em', null, price) : null,
					renderPreviewFields(row, roles, visibleFields)
				);
			})
		);
	}

	function renderPreviewImage(image) {
		var imageUrl = safeImageUrl(image);
		if (!imageUrl) {
			return el('div', { className: 'tabloria-editor-preview__image' });
		}

		return el('div', { className: 'tabloria-editor-preview__image' }, el('img', { src: imageUrl, alt: '' }));
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
			{ className: 'tabloria-editor-preview__fields' },
			fields.map(function (field) {
				return el('div', { key: field }, el('dt', null, humanize(field)), el('dd', null, row[field]));
			})
		);
	}

	function renderTablePreview(rows, visibleFields, source) {
		var columns = tableColumns(visibleFields, source, rows);
		return el(
			'div',
			{ className: 'tabloria-editor-preview__table-wrap' },
			el(
				'table',
				{ className: 'tabloria-editor-preview__table' },
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

		return columns.length ? columns : [{ key: 'row', label: __('Row', 'tabloria-searchable-displays') }];
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
			searchable_listings: __('Searchable Listings', 'tabloria-searchable-displays'),
			product_catalog: __('Product Catalog', 'tabloria-searchable-displays'),
			team_directory: __('Team Directory', 'tabloria-searchable-displays'),
			event_schedule: __('Event Schedule', 'tabloria-searchable-displays'),
			pricing_table: __('Pricing Table', 'tabloria-searchable-displays'),
			simple_data_table: __('Simple Data Table', 'tabloria-searchable-displays')
		};
		return labels[type] || __('Published Display', 'tabloria-searchable-displays');
	}

	blocks.registerBlockType('tabloria/display', {
		apiVersion: 2,
		title: __('Tabloria Display', 'tabloria-searchable-displays'),
		description: __('Insert a searchable display created from your spreadsheet.', 'tabloria-searchable-displays'),
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

	blocks.registerBlockType('tabloria/display-grid', {
		apiVersion: 2,
		title: __('Tabloria Display Grid', 'tabloria-searchable-displays'),
		description: __('Arrange multiple Published Displays in a responsive grid.', 'tabloria-searchable-displays'),
		icon: 'screenoptions',
		category: 'widgets',
		attributes: {
			displayIds: { type: 'array', default: [] },
			columns: { type: 'number', default: 2 },
			gap: { type: 'string', default: 'normal' },
			showTitles: { type: 'boolean', default: true }
		},
		edit: GridEdit,
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.apiFetch, window.wp.i18n);
