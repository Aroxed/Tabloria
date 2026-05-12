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
			api('/displays/' + displayId + '/render-data')
				.then(function (data) {
					setPreview(data);
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
						variant: 'link',
						href: 'admin.php?page=publishly-displays'
					}, __('Edit this Display in Publishly', 'publishly')) : null
				),
				renderPreview(preview, showTitle, overrideTitle)
			) : null
		);
	}

	function renderPreview(preview, showTitle, overrideTitle) {
		if (!preview || !preview.display) {
			return el('div', { className: 'publishly-editor-preview__body' }, el(Spinner));
		}

		var config = preview.display.config || {};
		var roles = config.fieldRoles || {};
		var rows = preview.rows || [];
		var title = overrideTitle || config.title || preview.display.name;

		return el(
			'div',
			{ className: 'publishly-editor-preview__body' },
			showTitle ? el('h3', null, title) : null,
			el(
				'div',
				{ className: 'publishly-editor-preview__grid' },
				rows.slice(0, 4).map(function (row, index) {
					var itemTitle = row[roles.title] || row[Object.keys(row)[0]] || __('Display row', 'publishly');
					var subtitle = row[roles.subtitle] || '';
					var price = row[roles.price] || '';
					return el(
						'article',
						{ className: 'publishly-editor-preview__card', key: index },
						el('div', { className: 'publishly-editor-preview__image' }),
						el('strong', null, itemTitle),
						subtitle ? el('span', null, subtitle) : null,
						price ? el('em', null, price) : null
					);
				})
			),
			el('p', { className: 'publishly-editor-preview__note' }, __('Live editor preview. The frontend renders from the saved Published Display.', 'publishly'))
		);
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
