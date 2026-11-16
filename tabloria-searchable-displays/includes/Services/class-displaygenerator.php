<?php

namespace Tabloria\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DisplayGenerator {
	public const TYPES = array(
		'searchable_listings' => 'Searchable Listings',
		'product_catalog'     => 'Product Catalog',
		'team_directory'      => 'Team Directory',
		'event_schedule'      => 'Event Schedule',
		'pricing_table'       => 'Pricing Table',
		'coupon_display'      => 'Coupon Code Display',
		'simple_data_table'   => 'Simple Data Table',
	);

	public function generate( array $source, string $display_type = 'searchable_listings', array $overrides = array() ): array {
		$display_type = $this->sanitize_display_type( $display_type );
		$columns = $source['columns'] ?? array();
		$roles   = $this->guess_roles( $columns, $display_type );

		$config = array(
			'displayType'    => $display_type,
			'title'          => sanitize_text_field( $overrides['title'] ?? $this->default_title( $source, $display_type ) ),
			'sourceId'       => absint( $source['id'] ?? 0 ),
			'fieldRoles'     => $roles,
			'visibleFields'  => array_slice( $this->public_field_keys( $columns ), 0, 7 ),
			'defaultFilters' => array(),
			'visitorFilters' => $this->guess_visitor_filters( $columns ),
			'sort'           => $this->guess_sort( $columns ),
			'search'         => array(
				'enabled'             => true,
				'hideRowsUntilSearch' => false,
				'placeholder'         => 'Search all fields...',
				'emptyMessage'        => 'No rows match the current search or filters.',
			),
			'filters'        => array( 'enabled' => true, 'showColumnNames' => true, 'hoverColor' => '' ),
			'pagination'     => array(
				'enabled'         => true,
				'perPage'         => 12,
				'showResultCount' => true,
				'resultLabel'     => 'rows',
			),
			'style'          => array(
				'accentColor'  => '#3525cd',
				'density'      => 'comfortable',
				'imageShape'   => 'rounded',
				'displayWidth' => 'normal',
			),
			'layoutOptions'  => array(
				'cards' => array(
					'urlDisplay'  => 'link',
					'urlLabel'    => '',
					'buttonStyle' => 'theme',
				),
				'coupons' => array(
					'showCouponLabel' => 'Show coupon',
					'copyCodeLabel'   => 'Copy code',
					'dealLabel'       => 'View deal',
					'copiedLabel'     => 'Copied!',
				),
			),
		);

		return $this->merge_config( $config, $overrides );
	}

	public function sanitize_config( array $config, array $source ): array {
		$columns      = $source['columns'] ?? array();
		$allowed_keys = array_values( array_filter( array_map( 'sanitize_key', wp_list_pluck( $columns, 'key' ) ) ) );

		$config['displayType'] = $this->sanitize_display_type( $config['displayType'] ?? 'searchable_listings' );
		$config['title']       = sanitize_text_field( $config['title'] ?? __( 'Published Display', 'tabloria-searchable-displays' ) );
		$config['sourceId']    = absint( $source['id'] ?? ( $config['sourceId'] ?? 0 ) );

		$field_roles = array();
		$allowed_roles = array( 'title', 'subtitle', 'image', 'price', 'badge', 'coupon_code', 'affiliate_url' );
		foreach ( (array) ( $config['fieldRoles'] ?? array() ) as $role => $field ) {
			$role = sanitize_key( $role );
			if ( ! in_array( $role, $allowed_roles, true ) ) {
				continue;
			}
			$field = sanitize_key( $field );
			$field_roles[ $role ] = in_array( $field, $allowed_keys, true ) ? $field : '';
		}
		$config['fieldRoles'] = $field_roles;

		$config['visibleFields'] = array_slice(
			array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', (array) ( $config['visibleFields'] ?? array() ) ),
						static fn( string $field ): bool => in_array( $field, $allowed_keys, true )
					)
				)
			),
			0,
			40
		);

		$config['visitorFilters'] = array_slice(
			array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', (array) ( $config['visitorFilters'] ?? array() ) ),
						static fn( string $field ): bool => in_array( $field, $allowed_keys, true )
					)
				)
			),
			0,
			10
		);

		$default_filters = array();
		foreach ( array_slice( (array) ( $config['defaultFilters'] ?? array() ), 0, 10 ) as $filter ) {
			if ( ! is_array( $filter ) ) {
				continue;
			}
			$field             = sanitize_key( $filter['field'] ?? '' );
			$operator          = sanitize_key( $filter['operator'] ?? 'equals' );
			$allowed_operators = array( 'equals', 'not_equals', 'contains', 'less_than', 'greater_than' );
			$default_filters[] = array(
				'field'    => in_array( $field, $allowed_keys, true ) ? $field : '',
				'operator' => in_array( $operator, $allowed_operators, true ) ? $operator : 'equals',
				'value'    => sanitize_text_field( $filter['value'] ?? '' ),
			);
		}
		$config['defaultFilters'] = $default_filters;

		$sort = is_array( $config['sort'] ?? null ) ? $config['sort'] : array();
		$config['sort'] = array(
			'field'     => in_array( $sort['field'] ?? '', $allowed_keys, true ) ? sanitize_key( $sort['field'] ) : '',
			'direction' => 'desc' === ( $sort['direction'] ?? 'asc' ) ? 'desc' : 'asc',
		);

		$search = is_array( $config['search'] ?? null ) ? $config['search'] : array();
		$filters = is_array( $config['filters'] ?? null ) ? $config['filters'] : array();
		$pagination = is_array( $config['pagination'] ?? null ) ? $config['pagination'] : array();
		$style = is_array( $config['style'] ?? null ) ? $config['style'] : array();

		$config['search'] = array(
			'enabled'             => ! empty( $search['enabled'] ),
			'hideRowsUntilSearch' => ! empty( $search['hideRowsUntilSearch'] ),
			'placeholder'         => sanitize_text_field( $search['placeholder'] ?? 'Search all fields...' ) ?: 'Search all fields...',
			'emptyMessage'        => sanitize_text_field( $search['emptyMessage'] ?? 'No rows match the current search or filters.' ) ?: 'No rows match the current search or filters.',
		);
		$config['filters'] = array(
			'enabled'         => ! empty( $filters['enabled'] ),
			'showColumnNames' => ! array_key_exists( 'showColumnNames', $filters ) || ! empty( $filters['showColumnNames'] ),
			'hoverColor'      => sanitize_hex_color( $filters['hoverColor'] ?? '' ) ?: '',
		);
		$config['pagination'] = array(
			'enabled'         => ! empty( $pagination['enabled'] ),
			'perPage'         => max( 1, min( 100, absint( $pagination['perPage'] ?? 12 ) ) ),
			'showResultCount' => ! array_key_exists( 'showResultCount', $pagination ) || ! empty( $pagination['showResultCount'] ),
			'resultLabel'     => sanitize_text_field( $pagination['resultLabel'] ?? 'rows' ) ?: 'rows',
		);
		$display_width = sanitize_key( $style['displayWidth'] ?? 'normal' );
		$card_max_width = absint( $style['cardMaxWidth'] ?? 420 );
		$config['style'] = array(
			'accentColor'  => sanitize_hex_color( $style['accentColor'] ?? '#3525cd' ) ?: '#3525cd',
			'density'      => 'compact' === ( $style['density'] ?? 'comfortable' ) ? 'compact' : 'comfortable',
			'imageShape'   => 'square' === ( $style['imageShape'] ?? 'rounded' ) ? 'square' : 'rounded',
			'displayWidth' => in_array( $display_width, array( 'normal', 'wide', 'full' ), true ) ? $display_width : 'normal',
			'cardMaxWidth' => in_array( $card_max_width, array( 320, 420, 520, 0 ), true ) ? $card_max_width : 420,
		);

		$layout_options = is_array( $config['layoutOptions'] ?? null ) ? $config['layoutOptions'] : array();
		$card_options   = is_array( $layout_options['cards'] ?? null ) ? $layout_options['cards'] : array();
		$coupon_options = is_array( $layout_options['coupons'] ?? null ) ? $layout_options['coupons'] : array();
		$url_display    = sanitize_key( $card_options['urlDisplay'] ?? 'link' );
		$button_style   = sanitize_key( $card_options['buttonStyle'] ?? 'theme' );
		$config['layoutOptions'] = array(
			'cards' => array(
				'urlDisplay'  => in_array( $url_display, array( 'link', 'button' ), true ) ? $url_display : 'link',
				'urlLabel'    => sanitize_text_field( $card_options['urlLabel'] ?? '' ),
				'buttonStyle' => in_array( $button_style, array( 'theme', 'primary', 'secondary', 'outline' ), true ) ? $button_style : 'theme',
			),
			'coupons' => array(
				'showCouponLabel' => sanitize_text_field( $coupon_options['showCouponLabel'] ?? 'Show coupon' ) ?: 'Show coupon',
				'copyCodeLabel'   => sanitize_text_field( $coupon_options['copyCodeLabel'] ?? 'Copy code' ) ?: 'Copy code',
				'dealLabel'       => sanitize_text_field( $coupon_options['dealLabel'] ?? 'View deal' ) ?: 'View deal',
				'copiedLabel'     => sanitize_text_field( $coupon_options['copiedLabel'] ?? 'Copied!' ) ?: 'Copied!',
			),
		);

		return $config;
	}

	public function sanitize_display_type( mixed $display_type ): string {
		$display_type = sanitize_key( $display_type );
		return isset( self::TYPES[ $display_type ] ) ? $display_type : 'searchable_listings';
	}

	private function guess_roles( array $columns, string $display_type ): array {
		$by_type  = $this->columns_by_type( $columns );
		$by_label = $this->columns_by_label( $columns );
		$price    = $by_type['price'] ?? $by_label['price'] ?? $by_label['cost'] ?? $by_label['rate'] ?? $by_label['fee'] ?? '';

		if ( in_array( $display_type, array( 'team_directory', 'event_schedule', 'simple_data_table' ), true ) && empty( $by_type['price'] ) ) {
			$price = '';
		}

		$roles = array(
			'title'    => $by_label['name'] ?? $by_label['title'] ?? $by_label['student_name'] ?? $by_label['instructor_name'] ?? $this->first_public_key( $columns ),
			'subtitle' => $by_label['building'] ?? $by_label['location'] ?? $by_label['role'] ?? '',
			'image'    => $by_type['image'] ?? '',
			'price'    => $price,
			'badge'    => $by_type['status'] ?? $by_label['status'] ?? $by_label['category'] ?? $by_label['role'] ?? '',
		);

		if ( 'coupon_display' === $display_type ) {
			$roles['title'] = $by_label['offer'] ?? $by_label['deal'] ?? $by_label['name'] ?? $by_label['title'] ?? $this->first_public_key( $columns );
			$roles['subtitle'] = $by_label['store'] ?? $by_label['merchant'] ?? $by_label['brand'] ?? $by_label['category'] ?? '';
			$roles['badge'] = $by_label['discount'] ?? $by_label['saving'] ?? $by_label['status'] ?? '';
			$roles['coupon_code'] = $by_label['coupon_code'] ?? $by_label['promo_code'] ?? $by_label['coupon'] ?? $by_label['code'] ?? '';
			$roles['affiliate_url'] = $by_label['affiliate_url'] ?? $by_label['deal_url'] ?? $by_label['website'] ?? $by_label['url'] ?? $by_label['link'] ?? ( $by_type['url'] ?? '' );
		}

		return $roles;
	}

	private function guess_visitor_filters( array $columns ): array {
		$filters = array();
		foreach ( $columns as $column ) {
			if ( in_array( $column['type'] ?? 'text', array( 'status', 'number', 'date' ), true ) ) {
				$filters[] = $column['key'];
			}
		}

		return array_slice( $filters, 0, 4 );
	}

	private function guess_sort( array $columns ): array {
		foreach ( $columns as $column ) {
			if ( in_array( $column['type'] ?? '', array( 'price', 'number', 'date' ), true ) ) {
				return array(
					'field'     => $column['key'],
					'direction' => 'asc',
				);
			}
		}

		return array(
			'field'     => $this->first_key( $columns ),
			'direction' => 'asc',
		);
	}

	private function default_title( array $source, string $display_type ): string {
		$name = $source['name'] ?? __( 'Published Display', 'tabloria-searchable-displays' );
		$clean_name = preg_replace( '/\.[a-z0-9]+$/i', '', $name );
		$fallback   = self::TYPES[ $display_type ] ?? 'Published Display';

		return sanitize_text_field( $clean_name ?: $fallback );
	}

	private function columns_by_type( array $columns ): array {
		$map = array();
		foreach ( $columns as $column ) {
			$type = $column['type'] ?? '';
			if ( $type && empty( $map[ $type ] ) ) {
				$map[ $type ] = $column['key'];
			}
		}
		return $map;
	}

	private function columns_by_label( array $columns ): array {
		$map = array();
		foreach ( $columns as $column ) {
			$column_key = sanitize_key( $column['key'] ?? '' );
			if ( $column_key && empty( $map[ $column_key ] ) ) {
				$map[ $column_key ] = $column['key'];
			}

			$key = strtolower( (string) ( $column['label'] ?? $column['key'] ?? '' ) );
			foreach ( array( 'name', 'title', 'building', 'location', 'role', 'price', 'cost', 'rate', 'fee', 'status', 'category', 'offer', 'deal', 'store', 'merchant', 'brand', 'discount', 'saving', 'coupon', 'code', 'website', 'url', 'link' ) as $token ) {
				if ( str_contains( $key, $token ) && empty( $map[ $token ] ) ) {
					$map[ $token ] = $column['key'];
				}
			}
		}
		return $map;
	}

	private function first_key( array $columns ): string {
		return sanitize_key( $columns[0]['key'] ?? '' );
	}

	private function first_public_key( array $columns ): string {
		$keys = $this->public_field_keys( $columns );
		return sanitize_key( $keys[0] ?? $this->first_key( $columns ) );
	}

	private function public_field_keys( array $columns ): array {
		$keys = array();
		foreach ( $columns as $column ) {
			$key = sanitize_key( $column['key'] ?? '' );
			if ( '' === $key || in_array( $key, array( 'entity', 'object_type', 'row_type', 'record_type' ), true ) ) {
				continue;
			}
			$keys[] = $key;
		}

		return $keys;
	}

	private function merge_config( array $config, array $overrides ): array {
		foreach ( $overrides as $key => $value ) {
			if ( is_array( $value ) && isset( $config[ $key ] ) && is_array( $config[ $key ] ) ) {
				$config[ $key ] = array_replace_recursive( $config[ $key ], $value );
			} else {
				$config[ $key ] = $value;
			}
		}

		return $config;
	}
}
