<?php

namespace Publishly\Services;

use Publishly\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FrontendRenderer {
	private Database $database;

	public function __construct( ?Database $database = null ) {
		$this->database = $database ?: new Database();
	}

	public function render( int $display_id, array $attributes = array() ): string {
		$display = $this->database->get_display( $display_id );
		if ( ! $display || 'published' !== ( $display['status'] ?? 'draft' ) ) {
			return '<div class="publishly-notice">' . esc_html__( 'This Publishly Display is not available.', 'publishly' ) . '</div>';
		}

		$source = $this->database->get_source( absint( $display['source_id'] ) );
		if ( ! $source ) {
			return '<div class="publishly-notice">' . esc_html__( 'The source spreadsheet for this Display could not be found.', 'publishly' ) . '</div>';
		}

		$config = $display['config'] ?? array();
		$rows   = $this->prepare_rows( $this->database->get_rows( absint( $source['id'] ) ), $config );
		$uid    = 'publishly-display-' . $display_id . '-' . wp_generate_uuid4();

		wp_enqueue_style( 'publishly-frontend' );
		wp_enqueue_script( 'publishly-frontend' );

		$accent = sanitize_hex_color( $config['style']['accentColor'] ?? '#3525cd' ) ?: '#3525cd';
		$density = 'compact' === ( $config['style']['density'] ?? '' ) ? 'is-compact' : 'is-comfortable';
		$shape = 'square' === ( $config['style']['imageShape'] ?? '' ) ? 'is-square' : 'is-rounded';
		$per_page = max( 1, absint( $config['pagination']['perPage'] ?? 12 ) );

		ob_start();
		?>
		<section
			id="<?php echo esc_attr( $uid ); ?>"
			class="publishly-display <?php echo esc_attr( $density . ' ' . $shape ); ?>"
			style="--publishly-accent: <?php echo esc_attr( $accent ); ?>"
			data-per-page="<?php echo esc_attr( $per_page ); ?>"
			data-pagination="<?php echo ! empty( $config['pagination']['enabled'] ) ? 'true' : 'false'; ?>"
		>
			<?php if ( ! isset( $attributes['showTitle'] ) || $attributes['showTitle'] ) : ?>
				<header class="publishly-display__header">
					<h2><?php echo esc_html( $attributes['overrideTitle'] ?? $config['title'] ?? $display['name'] ); ?></h2>
				</header>
			<?php endif; ?>

			<?php $this->render_controls( $rows, $config, $source ); ?>

			<div class="publishly-display__grid" aria-live="polite">
				<?php foreach ( $rows as $row ) : ?>
					<?php $this->render_card( $row, $config, $source ); ?>
				<?php endforeach; ?>
			</div>

			<div class="publishly-display__empty" hidden>
				<?php esc_html_e( 'No rows match the current search or filters.', 'publishly' ); ?>
			</div>

			<footer class="publishly-display__footer">
				<p class="publishly-display__status" aria-live="polite"></p>
				<nav class="publishly-display__pagination" aria-label="<?php esc_attr_e( 'Display pagination', 'publishly' ); ?>"></nav>
			</footer>
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
		<div class="publishly-display__controls">
			<?php if ( $search_enabled ) : ?>
				<label class="publishly-display__search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search Display', 'publishly' ); ?></span>
					<input type="search" data-publishly-search placeholder="<?php esc_attr_e( 'Search this Display...', 'publishly' ); ?>" />
				</label>
			<?php endif; ?>

			<?php if ( $filters_enabled ) : ?>
				<div class="publishly-display__filters">
					<?php foreach ( (array) $config['visitorFilters'] as $field ) : ?>
						<?php
						$label  = $this->column_label( $source, $field );
						$values = $this->distinct_values( $rows, $field );
						if ( empty( $values ) ) {
							continue;
						}
						?>
						<label>
							<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
							<select data-publishly-filter="<?php echo esc_attr( $field ); ?>">
								<option value=""><?php echo esc_html( $label ); ?></option>
								<?php foreach ( $values as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_card( array $row, array $config, array $source ): void {
		$roles = $config['fieldRoles'] ?? array();
		$title = $this->field_value( $row, $roles['title'] ?? '' );
		$title = '' !== $title ? $title : $this->best_title_value( $row );
		$subtitle = $this->field_value( $row, $roles['subtitle'] ?? '' );
		$subtitle = '' !== $subtitle ? $subtitle : $this->best_subtitle_value( $row, $title );
		$image = $this->field_value( $row, $roles['image'] ?? '' );
		$price = $this->field_value( $row, $roles['price'] ?? '' );
		$badge = $this->field_value( $row, $roles['badge'] ?? '' );

		$search_text = strtolower( implode( ' ', array_map( 'wp_strip_all_tags', $row ) ) );
		$filter_attrs = '';
		foreach ( (array) ( $config['visitorFilters'] ?? array() ) as $field ) {
			$filter_attrs .= ' data-filter-' . esc_attr( $field ) . '="' . esc_attr( $this->field_value( $row, $field ) ) . '"';
		}
		?>
		<article class="publishly-card" data-search="<?php echo esc_attr( $search_text ); ?>"<?php echo $filter_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $image ) : ?>
				<div class="publishly-card__image">
					<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" />
					<?php if ( $badge ) : ?>
						<span class="publishly-card__badge"><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
				</div>
			<?php elseif ( $badge ) : ?>
				<span class="publishly-card__badge publishly-card__badge--plain"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>

			<div class="publishly-card__body">
				<?php if ( $title ) : ?>
					<h3><?php echo esc_html( $title ); ?></h3>
				<?php endif; ?>
				<?php if ( $subtitle ) : ?>
					<p class="publishly-card__subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
				<?php if ( $price ) : ?>
					<p class="publishly-card__price"><?php echo esc_html( $price ); ?></p>
				<?php endif; ?>
				<dl class="publishly-card__fields">
					<?php foreach ( (array) ( $config['visibleFields'] ?? array() ) as $field ) : ?>
						<?php
						$value = $this->field_value( $row, $field );
						if ( '' === $value || in_array( $field, $roles, true ) || $this->is_technical_field( $field ) ) {
							continue;
						}
						?>
						<div>
							<dt><?php echo esc_html( $this->column_label( $source, $field ) ); ?></dt>
							<dd><?php echo esc_html( $value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>
		</article>
		<?php
	}

	private function prepare_rows( array $rows, array $config ): array {
		$rows = array_values(
			array_filter(
				$rows,
				function ( array $row ) use ( $config ): bool {
					foreach ( (array) ( $config['defaultFilters'] ?? array() ) as $filter ) {
						$field = sanitize_key( $filter['field'] ?? '' );
						$value = (string) ( $filter['value'] ?? '' );
						if ( '' === $field || '' === $value ) {
							continue;
						}

						$row_value = (string) ( $row[ $field ] ?? '' );
						$operator  = sanitize_key( $filter['operator'] ?? 'equals' );

						if ( 'contains' === $operator && ! str_contains( strtolower( $row_value ), strtolower( $value ) ) ) {
							return false;
						}
						if ( 'not_equals' === $operator && strtolower( $row_value ) === strtolower( $value ) ) {
							return false;
						}
						if ( ! in_array( $operator, array( 'contains', 'not_equals' ), true ) && strtolower( $row_value ) !== strtolower( $value ) ) {
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

	private function field_value( array $row, string $field ): string {
		return sanitize_text_field( (string) ( $row[ $field ] ?? '' ) );
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

		return sanitize_text_field( ucwords( strtolower( $label ?: __( 'Field', 'publishly' ) ) ) );
	}

	private function best_title_value( array $row ): string {
		foreach ( $row as $field => $value ) {
			$field = sanitize_key( $field );
			$value = $this->field_value( $row, $field );
			if ( '' === $value || $this->is_technical_field( $field ) ) {
				continue;
			}
			if ( preg_match( '/(^|_)(name|title)($|_)/', $field ) && ! preg_match( '/email|code|number|status|type/', $field ) ) {
				return $value;
			}
		}

		foreach ( $row as $field => $value ) {
			$field = sanitize_key( $field );
			$value = $this->field_value( $row, $field );
			if ( '' !== $value && ! $this->is_technical_field( $field ) ) {
				return $value;
			}
		}

		return '';
	}

	private function best_subtitle_value( array $row, string $title ): string {
		foreach ( $row as $field => $value ) {
			$field = sanitize_key( $field );
			$value = $this->field_value( $row, $field );
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

	private function distinct_values( array $rows, string $field ): array {
		$values = array();
		foreach ( $rows as $row ) {
			$value = $this->field_value( $row, $field );
			if ( '' !== $value ) {
				$values[ $value ] = true;
			}
		}

		$values = array_keys( $values );
		natcasesort( $values );

		return array_values( array_slice( $values, 0, 50 ) );
	}
}
