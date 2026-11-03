<?php
/**
 * Store finder markup.
 *
 * Included by Shortcode::render_template(), which provides $args.
 *
 * @package RowdyCrowd
 *
 * @var array<string, mixed> $args Template values.
 */

defined( 'ABSPATH' ) || exit;

$finder_id = (string) $args['id'];
$stockists = $args['stockists'];
$states    = $args['states'];
$total     = count( $stockists );

?>
<div
	id="<?php echo esc_attr( $finder_id ); ?>"
	class="<?php echo esc_attr( $args['classes'] ); ?>"
	style="--rowdy-crowd-store-finder-height: <?php echo esc_attr( $args['height'] ); ?>; --rowdy-crowd-store-finder-accent: <?php echo esc_attr( $args['accent'] ); ?>;"
	data-rowdy-crowd-store-finder
	data-map-id="<?php echo esc_attr( $args['map_id'] ); ?>"
	data-pin-color="<?php echo esc_attr( $args['accent'] ); ?>"
	data-count-one="<?php echo esc_attr__( '1 store', 'rowdy-crowd' ); ?>"
	data-count-many="<?php /* translators: %d: number of stores. */ echo esc_attr__( '%d stores', 'rowdy-crowd' ); ?>"
>
	<?php echo $args['notice']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Shortcode::admin_notice(). ?>

	<div class="rowdy-crowd-store-finder__controls" role="search">
		<label class="rowdy-crowd-store-finder__sr" for="<?php echo esc_attr( $finder_id ); ?>-search"><?php esc_html_e( 'Search stores', 'rowdy-crowd' ); ?></label>
		<input
			type="search"
			id="<?php echo esc_attr( $finder_id ); ?>-search"
			class="rowdy-crowd-store-finder__search"
			placeholder="<?php esc_attr_e( 'Search by store, suburb or postcode', 'rowdy-crowd' ); ?>"
			autocomplete="off"
		>

		<?php if ( count( $states ) > 1 ) : ?>
			<label class="rowdy-crowd-store-finder__sr" for="<?php echo esc_attr( $finder_id ); ?>-state"><?php esc_html_e( 'Filter by state', 'rowdy-crowd' ); ?></label>
			<select id="<?php echo esc_attr( $finder_id ); ?>-state" class="rowdy-crowd-store-finder__state">
				<option value=""><?php esc_html_e( 'All states', 'rowdy-crowd' ); ?></option>
				<?php foreach ( $states as $state_code => $state_name ) : ?>
					<option value="<?php echo esc_attr( $state_code ); ?>"><?php echo esc_html( $state_name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>

		<p class="rowdy-crowd-store-finder__count" aria-live="polite">
			<?php
			/* translators: %d: number of stores. */
			echo esc_html( sprintf( _n( '%d store', '%d stores', $total, 'rowdy-crowd' ), $total ) );
			?>
		</p>
	</div>

	<p class="rowdy-crowd-store-finder__empty" hidden><?php esc_html_e( 'No stores match your search. Try a different suburb or postcode.', 'rowdy-crowd' ); ?></p>

	<div class="rowdy-crowd-store-finder__body">
		<ul class="rowdy-crowd-store-finder__list">
			<?php foreach ( $stockists as $stockist ) : ?>
				<li
					class="rowdy-crowd-store-finder__item"
					data-lat="<?php echo esc_attr( (string) $stockist->lat ); ?>"
					data-lng="<?php echo esc_attr( (string) $stockist->lng ); ?>"
					data-state="<?php echo esc_attr( $stockist->state_code ); ?>"
					data-search="<?php echo esc_attr( $stockist->search_text() ); ?>"
				>
					<p class="rowdy-crowd-store-finder__name"><?php echo esc_html( $stockist->name ); ?></p>
					<p class="rowdy-crowd-store-finder__address"><?php echo esc_html( $stockist->address() ); ?></p>

					<?php if ( '' !== $stockist->phone ) : ?>
						<p class="rowdy-crowd-store-finder__phone"><a href="<?php echo esc_url( $stockist->phone_href() ); ?>"><?php echo esc_html( $stockist->phone ); ?></a></p>
					<?php endif; ?>

					<?php if ( '' !== $stockist->website ) : ?>
						<p class="rowdy-crowd-store-finder__website"><a href="<?php echo esc_url( $stockist->website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $stockist->website_label() ); ?></a></p>
					<?php endif; ?>

					<p class="rowdy-crowd-store-finder__actions">
						<button type="button" class="rowdy-crowd-store-finder__locate"><?php esc_html_e( 'Show on map', 'rowdy-crowd' ); ?></button>
						<a class="rowdy-crowd-store-finder__directions" href="<?php echo esc_url( $stockist->directions_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Directions', 'rowdy-crowd' ); ?></a>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $args['has_map'] ) : ?>
			<div class="rowdy-crowd-store-finder__map" role="region" aria-label="<?php esc_attr_e( 'Map of stores', 'rowdy-crowd' ); ?>"></div>
		<?php endif; ?>
	</div>
</div>
