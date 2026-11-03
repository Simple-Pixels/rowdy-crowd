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

use RowdyCrowd\Modules\Store_Finder\Stockists;

defined( 'ABSPATH' ) || exit;

$finder_id = (string) $args['id'];
$stockists = $args['stockists'];
$states    = $args['states'];
$counts    = $args['counts'];
$total     = count( $stockists );

?>
<div
	id="<?php echo esc_attr( $finder_id ); ?>"
	class="<?php echo esc_attr( $args['classes'] ); ?>"
	style="--rowdy-crowd-store-finder-height: <?php echo esc_attr( $args['height'] ); ?>;"
	data-rowdy-crowd-store-finder
	data-map-id="<?php echo esc_attr( $args['map_id'] ); ?>"
	data-pin-color="<?php echo esc_attr( $args['pin_color'] ); ?>"
	data-units="<?php echo esc_attr( $args['units'] ); ?>"
	data-count-one="<?php echo esc_attr__( '1 store', 'rowdy-crowd' ); ?>"
	data-count-many="<?php /* translators: %d: number of stores. */ echo esc_attr__( '%d stores', 'rowdy-crowd' ); ?>"
	data-msg-locating="<?php echo esc_attr__( 'Finding stores near you…', 'rowdy-crowd' ); ?>"
	data-msg-nearest="<?php /* translators: %s: distance to the nearest store, e.g. "4.2 km". */ echo esc_attr__( 'Nearest store: %s away', 'rowdy-crowd' ); ?>"
	data-msg-denied="<?php echo esc_attr__( 'Your browser is blocking location for this site. Allow it, or search by suburb or postcode.', 'rowdy-crowd' ); ?>"
	data-msg-failed="<?php echo esc_attr__( 'We couldn\'t find your location. Try again, or search by suburb or postcode.', 'rowdy-crowd' ); ?>"
	data-msg-unavailable="<?php echo esc_attr__( 'Your browser can\'t share its location. Search by suburb or postcode instead.', 'rowdy-crowd' ); ?>"
	data-label-you="<?php echo esc_attr__( 'Your location', 'rowdy-crowd' ); ?>"
	data-label-close="<?php echo esc_attr__( 'Close', 'rowdy-crowd' ); ?>"
>
	<?php echo $args['notice']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Shortcode::admin_notice(). ?>

	<div class="rowdy-crowd-store-finder__controls" role="search">
		<div class="rowdy-crowd-store-finder__bar">
			<label class="rowdy-crowd-store-finder__sr" for="<?php echo esc_attr( $finder_id ); ?>-search"><?php esc_html_e( 'Search stores', 'rowdy-crowd' ); ?></label>
			<input
				type="search"
				id="<?php echo esc_attr( $finder_id ); ?>-search"
				class="rowdy-crowd-store-finder__search"
				placeholder="<?php esc_attr_e( 'Search by store, suburb or postcode', 'rowdy-crowd' ); ?>"
				autocomplete="off"
			>

			<button type="button" class="rowdy-crowd-store-finder__near-me" aria-pressed="false">
				<svg class="rowdy-crowd-store-finder__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="6" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2" fill="currentColor"/><path d="M12 1.5v4M12 18.5v4M1.5 12h4M18.5 12h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
				<span><?php esc_html_e( 'Stores near me', 'rowdy-crowd' ); ?></span>
			</button>
		</div>

		<div class="rowdy-crowd-store-finder__filter-row">
			<?php if ( $args['use_chips'] ) : ?>
				<div class="rowdy-crowd-store-finder__chips" role="group" aria-label="<?php esc_attr_e( 'Filter by state', 'rowdy-crowd' ); ?>">
					<button
						type="button"
						class="rowdy-crowd-store-finder__chip rowdy-crowd-store-finder__chip--all"
						data-state=""
						aria-pressed="true"
						<?php /* translators: %d: number of stores. */ ?>
						aria-label="<?php echo esc_attr( sprintf( _n( 'All states, %d store', 'All states, %d stores', $total, 'rowdy-crowd' ), $total ) ); ?>"
					>
						<?php esc_html_e( 'All', 'rowdy-crowd' ); ?>
						<span class="rowdy-crowd-store-finder__chip-count"><?php echo esc_html( (string) $total ); ?></span>
					</button>

					<?php foreach ( $states as $state_code => $state_name ) : ?>
						<button
							type="button"
							class="rowdy-crowd-store-finder__chip rowdy-crowd-store-finder__chip--<?php echo esc_attr( Stockists::chip_colour( $state_code ) ); ?>"
							data-state="<?php echo esc_attr( $state_code ); ?>"
							aria-pressed="false"
							<?php /* translators: 1: state code, 2: state name, 3: number of stores. */ ?>
							aria-label="<?php echo esc_attr( sprintf( _n( '%1$s, %2$s, %3$d store', '%1$s, %2$s, %3$d stores', $counts[ $state_code ], 'rowdy-crowd' ), $state_code, $state_name, $counts[ $state_code ] ) ); ?>"
							title="<?php echo esc_attr( $state_name ); ?>"
						>
							<?php echo esc_html( $state_code ); ?>
							<span class="rowdy-crowd-store-finder__chip-count"><?php echo esc_html( (string) $counts[ $state_code ] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php elseif ( count( $states ) > 1 ) : ?>
				<div class="rowdy-crowd-store-finder__select-wrap">
					<label class="rowdy-crowd-store-finder__sr" for="<?php echo esc_attr( $finder_id ); ?>-state"><?php esc_html_e( 'Filter by state', 'rowdy-crowd' ); ?></label>
					<select id="<?php echo esc_attr( $finder_id ); ?>-state" class="rowdy-crowd-store-finder__state">
						<option value=""><?php esc_html_e( 'All states', 'rowdy-crowd' ); ?></option>
						<?php foreach ( $states as $state_code => $state_name ) : ?>
							<option value="<?php echo esc_attr( $state_code ); ?>"><?php echo esc_html( $state_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<p class="rowdy-crowd-store-finder__count" aria-live="polite">
				<?php
				/* translators: %d: number of stores. */
				echo esc_html( sprintf( _n( '%d store', '%d stores', $total, 'rowdy-crowd' ), $total ) );
				?>
			</p>
		</div>

		<?php // A toast at the bottom of the screen. The script moves it to <body>. ?>
		<p class="rowdy-crowd-store-finder__message" role="status" hidden></p>
	</div>

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
					<p class="rowdy-crowd-store-finder__name">
						<?php if ( $args['has_map'] ) : ?>
							<?php // The whole card is clickable. This button is what keyboard and screen reader users reach. ?>
							<button type="button" class="rowdy-crowd-store-finder__select"><?php echo esc_html( $stockist->name ); ?></button>
						<?php else : ?>
							<span class="rowdy-crowd-store-finder__title"><?php echo esc_html( $stockist->name ); ?></span>
						<?php endif; ?>
					</p>
					<p class="rowdy-crowd-store-finder__address"><?php echo esc_html( $stockist->address() ); ?></p>

					<?php if ( '' !== $stockist->phone ) : ?>
						<p class="rowdy-crowd-store-finder__phone"><a href="<?php echo esc_url( $stockist->phone_href() ); ?>"><?php echo esc_html( $stockist->phone ); ?></a></p>
					<?php endif; ?>

					<?php if ( '' !== $stockist->website ) : ?>
						<p class="rowdy-crowd-store-finder__website"><a href="<?php echo esc_url( $stockist->website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $stockist->website_label() ); ?></a></p>
					<?php endif; ?>

					<p class="rowdy-crowd-store-finder__actions">
						<a class="rowdy-crowd-store-finder__directions" href="<?php echo esc_url( $stockist->directions_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Directions', 'rowdy-crowd' ); ?></a>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php // Sits in the list's place when nothing matches, so the map doesn't move. ?>
		<div class="rowdy-crowd-store-finder__empty" hidden>
			<p class="rowdy-crowd-store-finder__empty-title"><?php esc_html_e( 'No stores match your search.', 'rowdy-crowd' ); ?></p>
			<p><?php esc_html_e( 'Try a different suburb or postcode, or look at every store.', 'rowdy-crowd' ); ?></p>
			<button type="button" class="rowdy-crowd-store-finder__clear"><?php esc_html_e( 'Clear search and filters', 'rowdy-crowd' ); ?></button>
		</div>

		<?php if ( $args['has_map'] ) : ?>
			<div class="rowdy-crowd-store-finder__map" role="region" aria-label="<?php esc_attr_e( 'Map of stores', 'rowdy-crowd' ); ?>"></div>
		<?php endif; ?>
	</div>
</div>
