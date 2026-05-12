<?php

namespace Publishly\Services;

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
		'simple_data_table'   => 'Simple Data Table',
	);

	public function generate( array $source, string $display_type = 'searchable_listings', array $overrides = array() ): array {
		$columns = $source['columns'] ?? array();
		$roles   = $this->guess_roles( $columns, $display_type );

		$config = array(
			'displayType'    => sanitize_key( $display_type ),
			'title'          => sanitize_text_field( $overrides['title'] ?? $this->default_title( $source, $display_type ) ),
			'sourceId'       => absint( $source['id'] ?? 0 ),
			'fieldRoles'     => $roles,
			'visibleFields'  => array_slice( $this->public_field_keys( $columns ), 0, 7 ),
			'defaultFilters' => array(),
			'visitorFilters' => $this->guess_visitor_filters( $columns ),
			'sort'           => $this->guess_sort( $columns ),
			'search'         => array( 'enabled' => true ),
			'filters'        => array( 'enabled' => true ),
			'pagination'     => array(
				'enabled' => true,
				'perPage' => 12,
			),
			'style'          => array(
				'accentColor' => '#3525cd',
				'density'     => 'comfortable',
				'imageShape'  => 'rounded',
			),
		);

		return $this->merge_config( $config, $overrides );
	}

	public function sanitize_config( array $config, array $source ): array {
		$columns      = $source['columns'] ?? array();
		$allowed_keys = wp_list_pluck( $columns, 'key' );

		$config['displayType'] = sanitize_key( $config['displayType'] ?? 'searchable_listings' );
		$config['title']       = sanitize_text_field( $config['title'] ?? __( 'Published Display', 'publishly' ) );
		$config['sourceId']    = absint( $source['id'] ?? ( $config['sourceId'] ?? 0 ) );

		$field_roles = array();
		foreach ( (array) ( $config['fieldRoles'] ?? array() ) as $role => $field ) {
			$field_roles[ sanitize_key( $role ) ] = in_array( $field, $allowed_keys, true ) ? sanitize_key( $field ) : '';
		}
		$config['fieldRoles'] = $field_roles;

		$config['visibleFields'] = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) ( $config['visibleFields'] ?? array() ) ),
				static fn( string $field ): bool => in_array( $field, $allowed_keys, true )
			)
		);

		$config['visitorFilters'] = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) ( $config['visitorFilters'] ?? array() ) ),
				static fn( string $field ): bool => in_array( $field, $allowed_keys, true )
			)
		);

		$config['defaultFilters'] = array_values(
			array_map(
				static function ( array $filter ) use ( $allowed_keys ): array {
					$field = sanitize_key( $filter['field'] ?? '' );
					return array(
						'field'    => in_array( $field, $allowed_keys, true ) ? $field : '',
						'operator' => sanitize_key( $filter['operator'] ?? 'equals' ),
						'value'    => sanitize_text_field( $filter['value'] ?? '' ),
					);
				},
				(array) ( $config['defaultFilters'] ?? array() )
			)
		);

		$config['sort'] = array(
			'field'     => in_array( $config['sort']['field'] ?? '', $allowed_keys, true ) ? sanitize_key( $config['sort']['field'] ) : '',
			'direction' => 'desc' === ( $config['sort']['direction'] ?? 'asc' ) ? 'desc' : 'asc',
		);

		$config['search'] = array( 'enabled' => ! empty( $config['search']['enabled'] ) );
		$config['filters'] = array( 'enabled' => ! empty( $config['filters']['enabled'] ) );
		$config['pagination'] = array(
			'enabled' => ! empty( $config['pagination']['enabled'] ),
			'perPage' => max( 1, min( 100, absint( $config['pagination']['perPage'] ?? 12 ) ) ),
		);
		$config['style'] = array(
			'accentColor' => sanitize_hex_color( $config['style']['accentColor'] ?? '#3525cd' ) ?: '#3525cd',
			'density'     => 'compact' === ( $config['style']['density'] ?? 'comfortable' ) ? 'compact' : 'comfortable',
			'imageShape'  => 'square' === ( $config['style']['imageShape'] ?? 'rounded' ) ? 'square' : 'rounded',
		);

		return $config;
	}

	private function guess_roles( array $columns, string $display_type ): array {
		$by_type  = $this->columns_by_type( $columns );
		$by_label = $this->columns_by_label( $columns );
		$price    = $by_type['price'] ?? $by_label['price'] ?? $by_label['cost'] ?? $by_label['rate'] ?? $by_label['fee'] ?? '';

		if ( in_array( $display_type, array( 'team_directory', 'event_schedule', 'simple_data_table' ), true ) && empty( $by_type['price'] ) ) {
			$price = '';
		}

		return array(
			'title'    => $by_label['name'] ?? $by_label['title'] ?? $by_label['student_name'] ?? $by_label['instructor_name'] ?? $this->first_public_key( $columns ),
			'subtitle' => $by_label['building'] ?? $by_label['location'] ?? $by_label['role'] ?? '',
			'image'    => $by_type['image'] ?? '',
			'price'    => $price,
			'badge'    => $by_type['status'] ?? $by_label['status'] ?? $by_label['category'] ?? $by_label['role'] ?? '',
		);
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
		$name = $source['name'] ?? __( 'Published Display', 'publishly' );
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
			foreach ( array( 'name', 'title', 'building', 'location', 'role', 'price', 'cost', 'rate', 'fee', 'status', 'category' ) as $token ) {
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
