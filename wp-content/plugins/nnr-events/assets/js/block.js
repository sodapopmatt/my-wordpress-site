( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var TextControl = components.TextControl;
	var FormTokenField = components.FormTokenField;
	var ToggleControl = components.ToggleControl;

	var categories = ( ( window.NNREventsBlock && window.NNREventsBlock.categories ) || [] ).filter(
		function ( term ) {
			return term.value;
		}
	);

	var labelToSlug = {};
	var slugToLabel = {};
	categories.forEach( function ( term ) {
		labelToSlug[ term.label ] = term.value;
		slugToLabel[ term.value ] = term.label;
	} );

	function slugsToTokens( commaList ) {
		if ( ! commaList ) {
			return [];
		}
		return commaList.split( ',' ).map( function ( slug ) {
			return slugToLabel[ slug ] || slug;
		} );
	}

	function tokensToSlugs( tokens ) {
		return tokens
			.map( function ( token ) {
				return labelToSlug[ token ] || token;
			} )
			.join( ',' );
	}

	blocks.registerBlockType( 'nnr-events/event-list', {
		title: __( 'Events List', 'nnr-events' ),
		description: __( 'Displays a list of upcoming NNR events.', 'nnr-events' ),
		icon: 'calendar-alt',
		category: 'widgets',
		attributes: {
			category: { type: 'string', default: '' },
			limit: { type: 'number', default: 5 },
			columns: { type: 'number', default: 3 },
			layout: { type: 'string', default: 'grid' },
			image: { type: 'string', default: 'show' },
			color: { type: 'string', default: '' },
			filter: { type: 'string', default: 'none' },
		},
		supports: {
			html: false,
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Event List Settings', 'nnr-events' ) },
						el( FormTokenField, {
							label: __( 'Categories', 'nnr-events' ),
							value: slugsToTokens( attributes.category ),
							suggestions: categories.map( function ( term ) {
								return term.label;
							} ),
							__experimentalExpandOnFocus: true,
							onChange: function ( tokens ) {
								setAttributes( { category: tokensToSlugs( tokens ) } );
							},
							help: __(
								'Leave empty to show all categories. Add multiple to show events in any of them.',
								'nnr-events'
							),
						} ),
						el( RangeControl, {
							label: __( 'Limit', 'nnr-events' ),
							value: attributes.limit,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 20,
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'nnr-events' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Grid', 'nnr-events' ), value: 'grid' },
								{ label: __( 'List', 'nnr-events' ), value: 'list' },
							],
							onChange: function ( value ) {
								setAttributes( { layout: value } );
							},
						} ),
						'list' !== attributes.layout &&
							el( RangeControl, {
								label: __( 'Columns', 'nnr-events' ),
								value: attributes.columns,
								onChange: function ( value ) {
									setAttributes( { columns: value } );
								},
								min: 1,
								max: 4,
							} ),
						el( SelectControl, {
							label: __( 'Image', 'nnr-events' ),
							value: attributes.image,
							options: [
								{ label: __( 'Show', 'nnr-events' ), value: 'show' },
								{ label: __( 'Hide', 'nnr-events' ), value: 'hide' },
							],
							onChange: function ( value ) {
								setAttributes( { image: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Color Override', 'nnr-events' ),
							help: __(
								'Optional hex color, e.g. #2563eb. Leave blank to use the site-wide accent color.',
								'nnr-events'
							),
							value: attributes.color,
							onChange: function ( value ) {
								setAttributes( { color: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show category filter pills', 'nnr-events' ),
							help: __(
								'Lets visitors click a category to filter the list live, without a page reload.',
								'nnr-events'
							),
							checked: 'pills' === attributes.filter,
							onChange: function ( checked ) {
								setAttributes( { filter: checked ? 'pills' : 'none' } );
							},
						} )
					)
				),
				el(
					'div',
					{ className: props.className },
					el( ServerSideRender, {
						block: 'nnr-events/event-list',
						attributes: attributes,
					} )
				)
			);
		},

		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
