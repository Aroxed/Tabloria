<?php

namespace Tabloria\Services;

use Tabloria\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FrontendRenderer {
	private Database $database;
	private SpreadsheetSanitizer $sanitizer;

	public function __construct( ?Database $database = null, ?SpreadsheetSanitizer $sanitizer = null ) {
		$this->database  = $database ?: new Database();
		$this->sanitizer = $sanitizer ?: new SpreadsheetSanitizer();
	}

	public function render( int $display_id, array $attributes = array() ): string {
		$display = $this->database->get_display( $display_id );
		if ( ! $display || 'published' !== ( $display['status'] ?? 'draft' ) ) {
			return '<div class="tabloria-notice">' . esc_html__( 'This Tabloria Display is not available.', 'tabloria-searchable-displays' ) . '</div>';
		}

		$source = $this->database->get_source( absint( $display['source_id'] ) );
		if ( ! $source ) {
			return '<div class="tabloria-notice">' . esc_html__( 'The source spreadsheet for this Display could not be found.', 'tabloria-searchable-displays' ) . '</div>';
		}

		$config = $display['config'] ?? array();
		$rows   = $this->prepare_rows( $this->database->get_rows( absint( $source['id'] ) ), $config );
		$uid    = 'tabloria-display-' . $display_id . '-' . wp_generate_uuid4();

		wp_enqueue_style( 'tabloria-frontend' );
		wp_enqueue_script( 'tabloria-frontend' );

		$accent = sanitize_hex_color( $config['style']['accentColor'] ?? '#3525cd' ) ?: '#3525cd';
		$density = 'compact' === ( $config['style']['density'] ?? '' ) ? 'is-compact' : 'is-comfortable';
		$shape = 'square' === ( $config['style']['imageShape'] ?? '' ) ? 'is-square' : 'is-rounded';
		$per_page = max( 1, absint( $config['pagination']['perPage'] ?? 12 ) );
		$display_type = sanitize_key( $display['display_type'] ?? ( $config['displayType'] ?? 'searchable_listings' ) );
		$type_class = 'tabloria-display--' . sanitize_html_class( str_replace( '_', '-', $display_type ) );
		$is_contained = ! empty( $attributes['contained'] );
		$display_width = sanitize_key( $config['style']['displayWidth'] ?? 'normal' );
		$display_width = in_array( $display_width, array( 'normal', 'wide', 'full' ), true ) ? $display_width : 'normal';
		if ( $is_contained ) {
			$display_width = 'normal';
		}
		$width_class = 'simple_data_table' === $display_type ? 'is-width-' . $display_width : '';
		$contained_class = $is_contained ? 'is-contained' : '';

		ob_start();
		?>
		<section
			id="<?php echo esc_attr( $uid ); ?>"
			class="tabloria-display <?php echo esc_attr( trim( $density . ' ' . $shape . ' ' . $type_class . ' ' . $width_class . ' ' . $contained_class ) ); ?>"
			style="--tabloria-accent: <?php echo esc_attr( $accent ); ?>"
			data-per-page="<?php echo esc_attr( $per_page ); ?>"
			data-pagination="<?php echo ! empty( $config['pagination']['enabled'] ) ? 'true' : 'false'; ?>"
			data-display-type="<?php echo esc_attr( $display_type ); ?>"
			data-contained="<?php echo esc_attr( $is_contained ? 'true' : 'false' ); ?>"
		>
			<?php if ( ! isset( $attributes['showTitle'] ) || $attributes['showTitle'] ) : ?>
				<header class="tabloria-display__header">
					<h2><?php echo esc_html( $attributes['overrideTitle'] ?? $config['title'] ?? $display['name'] ); ?></h2>
				</header>
			<?php endif; ?>

			<?php $this->render_controls( $rows, $config, $source ); ?>

			<?php $this->render_rows( $rows, $config, $source, $display_type ); ?>

			<div class="tabloria-display__empty" hidden>
				<?php esc_html_e( 'No rows match the current search or filters.', 'tabloria-searchable-displays' ); ?>
			</div>

			<footer class="tabloria-display__footer">
				<p class="tabloria-display__status" aria-live="polite"></p>
				<nav class="tabloria-display__pagination" aria-label="<?php esc_attr_e( 'Display pagination', 'tabloria-searchable-displays' ); ?>"></nav>
			</footer>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	public function render_grid( array $attributes = array() ): string {
		$display_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) ( $attributes['displayIds'] ?? array() ) )
				)
			)
		);

		if ( empty( $display_ids ) ) {
			return '<div class="tabloria-notice">' . esc_html__( 'Choose Published Displays for this Tabloria Grid.', 'tabloria-searchable-displays' ) . '</div>';
		}

		wp_enqueue_style( 'tabloria-frontend' );
		wp_enqueue_script( 'tabloria-frontend' );

		$columns = min( 4, max( 1, absint( $attributes['columns'] ?? 2 ) ) );
		$gap = sanitize_key( $attributes['gap'] ?? 'normal' );
		$gap = in_array( $gap, array( 'compact', 'normal', 'spacious' ), true ) ? $gap : 'normal';
		$show_titles = ! isset( $attributes['showTitles'] ) || (bool) $attributes['showTitles'];

		ob_start();
		?>
		<section
			class="tabloria-display-grid is-gap-<?php echo esc_attr( $gap ); ?>"
			style="--tabloria-grid-columns: <?php echo esc_attr( (string) $columns ); ?>"
			data-tabloria-display-grid
		>
			<?php foreach ( $display_ids as $display_id ) : ?>
				<div class="tabloria-display-grid__item">
					<?php
					$display_html = $this->render(
						$display_id,
						array(
							'showTitle' => $show_titles,
							'contained' => true,
						)
					);
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested display output is rendered through this class and escaped at each field boundary.
					echo $display_html;
					?>
				</div>
			<?php endforeach; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	public function render_preview_data( int $display_id ): array {
		$display = $this->database->get_display( $display_id );
		if ( ! $display ) {
			return array();
		}

		$source = $this->database->get_source( absint( $display['source_id'] ) );
		if ( ! $source ) {
			return array();
		}

		return array(
			'display' => $display,
			'source'  => $source,
			'rows'    => array_slice( $this->prepare_rows( $this->database->get_rows( absint( $source['id'] ) ), $display['config'] ?? array() ), 0, 12 ),
		);
	}

	private function render_controls( array $rows, array $config, array $source ): void {
		$search_enabled  = ! empty( $config['search']['enabled'] );
		$filters_enabled = ! empty( $config['filters']['enabled'] ) && ! empty( $config['visitorFilters'] );

		if ( ! $search_enabled && ! $filters_enabled ) {
			return;
		}

		?>
		<div class="tabloria-display__controls">
			<?php if ( $search_enabled ) : ?>
				<label class="tabloria-display__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search all fields in this Display', 'tabloria-searchable-displays' ); ?></span>
					<input type="search" data-tabloria-search placeholder="<?php esc_attr_e( 'Search all fields...', 'tabloria-searchable-displays' ); ?>" />
				</label>
			<?php endif; ?>

			<?php if ( $filters_enabled ) : ?>
				<div class="tabloria-display__filters">
					<?php foreach ( (array) $config['visitorFilters'] as $field ) : ?>
						<?php
						$label  = $this->column_label( $source, $field );
						$values = $this->distinct_values( $rows, $field, $source );
						if ( empty( $values ) ) {
							continue;
						}
						$filter_label = sprintf(
							/* translators: %s: column label. */
							__( 'Filter by %s', 'tabloria-searchable-displays' ),
							$label
						);
						$all_label = sprintf(
							/* translators: %s: column label. */
							__( 'All %s', 'tabloria-searchable-displays' ),
							$label
						);
						?>
						<label>
							<span class="screen-reader-text"><?php echo esc_html( $filter_label ); ?></span>
							<select data-tabloria-filter="<?php echo esc_attr( $field ); ?>" aria-label="<?php echo esc_attr( $filter_label ); ?>">
								<option value=""><?php echo esc_html( $all_label ); ?></option>
								<?php foreach ( $values as $value ) : ?>
									<?php
									$value_label = sprintf(
										/* translators: 1: column label, 2: filter value. */
										__( '%1$s: %2$s', 'tabloria-searchable-displays' ),
										$label,
										$value
									);
									?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_rows( array $rows, array $config, array $source, string $display_type ): void {
		if ( 'simple_data_table' === $display_type ) {
			$this->render_data_table( $rows, $config, $source );
			return;
		}

		?>
		<div class="tabloria-display__grid" aria-live="polite">
			<?php foreach ( $rows as $row ) : ?>
				<?php $this->render_card( $row, $config, $source, $display_type ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_data_table( array $rows, array $config, array $source ): void {
		$fields = $this->table_fields( $config, $source );
		$display_width = sanitize_key( $config['style']['displayWidth'] ?? 'normal' );
		$column_width = match ( $display_width ) {
			'full' => 96,
			'wide' => 120,
			default => 160,
		};
		$table_min_width = max( 640, count( $fields ) * $column_width );
		?>
		<div class="tabloria-table-scroll" aria-live="polite" style="--tabloria-table-min-width: <?php echo esc_attr( $table_min_width . 'px' ); ?>">
			<table class="tabloria-data-table">
				<thead>
					<tr>
						<?php foreach ( $fields as $field ) : ?>
							<th><?php echo esc_html( $this->column_label( $source, $field ) ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr<?php echo $this->item_data_attributes( $row, $config, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php foreach ( $fields as $field ) : ?>
								<td><?php echo $this->render_table_field_value( $row, $field, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_card( array $row, array $config, array $source, string $display_type ): void {
		$roles = $config['fieldRoles'] ?? array();
		$title = $this->typed_field_value( $row, $roles['title'] ?? '', $source );
		$title = '' !== $title ? $title : $this->best_title_value( $row, $source );
		$subtitle = $this->typed_field_value( $row, $roles['subtitle'] ?? '', $source );
		$subtitle = '' !== $subtitle ? $subtitle : $this->best_subtitle_value( $row, $title, $source );
		$image = $this->typed_field_value( $row, $roles['image'] ?? '', $source );
		$image_url = $this->sanitizer->sanitize_image_url( $image );
		$price = $this->typed_field_value( $row, $roles['price'] ?? '', $source );
		$badge = $this->typed_field_value( $row, $roles['badge'] ?? '', $source );
		$card_class = 'tabloria-card tabloria-card--' . sanitize_html_class( str_replace( '_', '-', $display_type ) );
		$field_rows = array();
		$url_actions = array();

		foreach ( (array) ( $config['visibleFields'] ?? array() ) as $field ) {
			$field = sanitize_key( $field );
			$value = $this->typed_field_value( $row, $field, $source );
			if ( '' === $field || '' === $value || in_array( $field, $roles, true ) || $this->is_technical_field( $field ) ) {
				continue;
			}

			if ( 'url' === $this->column_type( $source, $field ) ) {
				$url_actions[] = array(
					'url'        => $value,
					'label'      => $this->url_button_label( $source, $field ),
					'aria_label' => $this->url_aria_label( $source, $field, $title ),
				);
				continue;
			}

			$field_rows[] = array(
				'label' => $this->column_label( $source, $field ),
				'value' => $value,
			);
		}
		?>
		<article class="<?php echo esc_attr( $card_class ); ?>"<?php echo $this->item_data_attributes( $row, $config, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $image_url ) : ?>
				<div class="tabloria-card__image">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy" />
					<?php if ( $badge ) : ?>
						<span class="tabloria-card__badge"><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
				</div>
			<?php elseif ( $badge ) : ?>
				<span class="tabloria-card__badge tabloria-card__badge--plain"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>

			<div class="tabloria-card__body">
				<?php if ( $title ) : ?>
					<h3><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>
				<?php if ( $subtitle ) : ?>
					<p class="tabloria-card__subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
				<?php if ( $price ) : ?>
					<p class="tabloria-card__price"><?php echo esc_html( $price ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $field_rows ) ) : ?>
					<dl class="tabloria-card__fields">
						<?php foreach ( $field_rows as $field_row ) : ?>
							<div>
								<dt><?php echo esc_html( $field_row['label'] ); ?></dt>
								<dd><?php echo esc_html( $field_row['value'] ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if ( ! empty( $url_actions ) ) : ?>
					<div class="tabloria-card__actions">
						<?php foreach ( $url_actions as $action ) : ?>
							<?php echo $this->render_url_link( $action['url'], $action['label'], 'tabloria-card__link', $action['aria_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private function item_data_attributes( array $row, array $config, array $source ): string {
		$search_text = $this->normalize_search_text(
			implode(
				' ',
				array_map(
					function ( $field ) use ( $row, $source ): string {
						return $this->typed_field_value( $row, (string) $field, $source );
					},
					array_keys( $row )
				)
			)
		);
		$attributes = ' data-tabloria-item data-search="' . esc_attr( $search_text ) . '"';

		foreach ( (array) ( $config['visitorFilters'] ?? array() ) as $field ) {
			$field = sanitize_key( $field );
			if ( $field ) {
				$attributes .= ' data-filter-' . esc_attr( $field ) . '="' . esc_attr( $this->typed_field_value( $row, $field, $source ) ) . '"';
			}
		}

		return $attributes;
	}

	private function normalize_search_text( string $value ): string {
		$value = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) );
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $value, 'UTF-8' );
		}

		return strtolower( $value );
	}

	private function table_fields( array $config, array $source ): array {
		$fields = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) ( $config['visibleFields'] ?? array() ) ),
				fn( string $field ): bool => '' !== $field && ! $this->is_technical_field( $field )
			)
		);

		if ( $fields ) {
			return $fields;
		}

		return array_values(
			array_filter(
				array_map(
					static fn( array $column ): string => sanitize_key( $column['key'] ?? '' ),
					(array) ( $source['columns'] ?? array() )
				),
				fn( string $field ): bool => '' !== $field && ! $this->is_technical_field( $field )
			)
		);
	}

	private function prepare_rows( array $rows, array $config ): array {
		$rows = array_values(
			array_filter(
				$rows,
				function ( array $row ) use ( $config ): bool {
					foreach ( (array) ( $config['defaultFilters'] ?? array() ) as $filter ) {
						if ( ! $this->row_matches_default_filter( $row, (array) $filter ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);

		$sort_field = sanitize_key( $config['sort']['field'] ?? '' );
		if ( $sort_field ) {
			$direction = 'desc' === ( $config['sort']['direction'] ?? 'asc' ) ? -1 : 1;
			usort(
				$rows,
				static function ( array $a, array $b ) use ( $sort_field, $direction ): int {
					$a_value = (string) ( $a[ $sort_field ] ?? '' );
					$b_value = (string) ( $b[ $sort_field ] ?? '' );
					$a_raw = preg_replace( '/[^0-9.-]/', '', $a_value );
					$b_raw = preg_replace( '/[^0-9.-]/', '', $b_value );

					if ( '' !== $a_raw && '' !== $b_raw && is_numeric( $a_raw ) && is_numeric( $b_raw ) ) {
						return $direction * ( (float) $a_raw <=> (float) $b_raw );
					}

					return $direction * strnatcasecmp( $a_value, $b_value );
				}
			);
		}

		return $rows;
	}

	private function row_matches_default_filter( array $row, array $filter ): bool {
		$field = sanitize_key( $filter['field'] ?? '' );
		$value = (string) ( $filter['value'] ?? '' );
		if ( '' === $field || '' === $value ) {
			return true;
		}

		$row_value   = (string) ( $row[ $field ] ?? '' );
		$operator    = sanitize_key( $filter['operator'] ?? 'equals' );
		$row_lower   = strtolower( $row_value );
		$value_lower = strtolower( $value );

		if ( 'contains' === $operator ) {
			return str_contains( $row_lower, $value_lower );
		}

		if ( 'not_equals' === $operator ) {
			return $row_lower !== $value_lower;
		}

		if ( in_array( $operator, array( 'less_than', 'greater_than' ), true ) ) {
			$row_number   = $this->numeric_filter_value( $row_value );
			$filter_value = $this->numeric_filter_value( $value );

			if ( null === $row_number || null === $filter_value ) {
				return false;
			}

			return 'less_than' === $operator ? $row_number < $filter_value : $row_number > $filter_value;
		}

		return $row_lower === $value_lower;
	}

	private function numeric_filter_value( string $value ): ?float {
		$raw = preg_replace( '/[^0-9.-]/', '', $value );
		if ( ! is_string( $raw ) || '' === $raw || '-' === $raw || '.' === $raw || '-.' === $raw || ! is_numeric( $raw ) ) {
			return null;
		}

		return (float) $raw;
	}

	private function field_value( array $row, string $field ): string {
		return sanitize_text_field( (string) ( $row[ $field ] ?? '' ) );
	}

	private function typed_field_value( array $row, string $field, array $source ): string {
		$field = sanitize_key( $field );
		if ( '' === $field ) {
			return '';
		}

		return $this->sanitizer->sanitize_value( $row[ $field ] ?? '', $this->column_type( $source, $field ) );
	}

	private function render_table_field_value( array $row, string $field, array $source ): string {
		$field = sanitize_key( $field );
		$value = $this->typed_field_value( $row, $field, $source );
		if ( '' === $value ) {
			return '';
		}

		if ( 'url' === $this->column_type( $source, $field ) ) {
			return $this->render_url_link(
				$value,
				$this->url_button_label( $source, $field ),
				'tabloria-table-link',
				$this->url_aria_label( $source, $field )
			);
		}

		return esc_html( $value );
	}

	private function render_url_link( string $url, string $label, string $class_name, string $aria_label = '' ): string {
		$url = $this->sanitizer->sanitize_url_value( $url );
		if ( '' === $url ) {
			return '';
		}

		$aria = '' !== $aria_label ? ' aria-label="' . esc_attr( $aria_label ) . '"' : '';

		return sprintf(
			'<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer"%3$s>%4$s</a>',
			esc_attr( $class_name ),
			esc_url( $url ),
			$aria,
			esc_html( $label )
		);
	}

	private function url_button_label( array $source, string $field ): string {
		$label = $this->column_label( $source, $field );
		$normalized_label = strtolower( trim( $label ) );

		if ( in_array( $normalized_label, array( 'url', 'link' ), true ) ) {
			return __( 'Open link', 'tabloria-searchable-displays' );
		}

		return $label;
	}

	private function url_aria_label( array $source, string $field, string $title = '' ): string {
		$label = $this->column_label( $source, $field );

		if ( '' !== $title ) {
			return sprintf(
				/* translators: 1: link column label, 2: row title. */
				__( 'Open %1$s for %2$s', 'tabloria-searchable-displays' ),
				$label,
				$title
			);
		}

		return sprintf(
			/* translators: %s: link column label. */
			__( 'Open %s', 'tabloria-searchable-displays' ),
			$label
		);
	}

	private function column_type( array $source, string $field ): string {
		foreach ( (array) ( $source['columns'] ?? array() ) as $column ) {
			if ( $field === ( $column['key'] ?? '' ) ) {
				return sanitize_key( $column['type'] ?? 'text' );
			}
		}

		return 'text';
	}

	private function column_label( array $source, string $field ): string {
		foreach ( (array) ( $source['columns'] ?? array() ) as $column ) {
			if ( $field === ( $column['key'] ?? '' ) ) {
				return $this->humanize_label( (string) ( $column['label'] ?? $field ) );
			}
		}

		return $this->humanize_label( $field );
	}

	private function humanize_label( string $label ): string {
		$label = trim( str_replace( array( '_', '-' ), ' ', $label ) );
		$label = preg_replace( '/\s+/', ' ', $label );

		return sanitize_text_field( ucwords( strtolower( $label ?: __( 'Field', 'tabloria-searchable-displays' ) ) ) );
	}

	private function best_title_value( array $row, array $source ): string {
		foreach ( $row as $field => $value ) {
			$field = sanitize_key( $field );
			$value = $this->typed_field_value( $row, $field, $source );
			if ( '' === $value || $this->is_technical_field( $field ) ) {
				continue;
			}
			if ( preg_match( '/(^|_)(name|title)($|_)/', $field ) && ! preg_match( '/email|code|number|status|type/', $field ) ) {
				return $value;
			}
		}

		foreach ( $row as $field => $value ) {
			$field = sanitize_key( $field );
			$value = $this->typed_field_value( $row, $field, $source );
			if ( '' !== $value && ! $this->is_technical_field( $field ) ) {
				return $value;
			}
		}

		return '';
	}

	private function best_subtitle_value( array $row, string $title, array $source ): string {
		foreach ( $row as $field => $value ) {
			$field = sanitize_key( $field );
			$value = $this->typed_field_value( $row, $field, $source );
			if ( '' === $value || $value === $title || $this->is_technical_field( $field ) ) {
				continue;
			}
			if ( preg_match( '/(^|_)(role|category|location|building|course|department|type)($|_)/', $field ) ) {
				return $value;
			}
		}

		return '';
	}

	private function is_technical_field( string $field ): bool {
		return in_array( sanitize_key( $field ), array( 'entity', 'object_type', 'row_type', 'record_type' ), true );
	}

	private function distinct_values( array $rows, string $field, array $source ): array {
		$values = array();
		foreach ( $rows as $row ) {
			$value = $this->typed_field_value( $row, $field, $source );
			if ( '' !== $value ) {
				$values[ $value ] = true;
			}
		}

		$values = array_keys( $values );
		natcasesort( $values );

		return array_values( array_slice( $values, 0, 50 ) );
	}
}
