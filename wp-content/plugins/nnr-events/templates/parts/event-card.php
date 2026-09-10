<?php
/**
 * Single event card. Expects $event (array from NNR_Shortcode::get_event_data) in scope.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$date_label  = NNR_Shortcode::format_date_label( $event );
$range_note  = NNR_Shortcode::format_date_range_note( $event );
$show_image  = ( 'hide' !== $atts['image'] ) && $event['image'];
?>
<div class="nnr-event-card">
	<?php if ( $show_image ) : ?>
		<div
			class="nnr-event-card__image"
			style="background-image:url('<?php echo esc_url( $event['image'] ); ?>');"
			role="button"
			tabindex="0"
			data-nnr-lightbox="<?php echo esc_url( $event['image'] ); ?>"
			aria-label="<?php echo esc_attr( sprintf( /* translators: %s: event title */ __( 'View larger image for %s', 'nnr-events' ), $event['title'] ) ); ?>"
		></div>
	<?php endif; ?>

	<div class="nnr-event-card__body">
		<div class="nnr-event-card__row">
			<span class="nnr-event-card__when">
				<?php if ( $date_label ) : ?>
					<span class="nnr-event-card__date"><?php echo wp_kses( $date_label, array( 'span' => array( 'class' => true ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $event['start_date'] ) : ?>
					<?php $google_url = NNR_ICS::get_google_url( $event['id'] ); ?>
					<span class="nnr-event-card__cal">
						<button type="button" class="nnr-event-card__ics" aria-haspopup="true" aria-expanded="false" title="<?php esc_attr_e( 'Add to Calendar', 'nnr-events' ); ?>" aria-label="<?php esc_attr_e( 'Add to Calendar', 'nnr-events' ); ?>">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
								<rect x="3" y="4" width="18" height="18" rx="2"></rect>
								<line x1="16" y1="2" x2="16" y2="6"></line>
								<line x1="8" y1="2" x2="8" y2="6"></line>
								<line x1="3" y1="10" x2="21" y2="10"></line>
								<line x1="12" y1="14" x2="12" y2="18"></line>
								<line x1="10" y1="16" x2="14" y2="16"></line>
							</svg>
						</button>
						<span class="nnr-event-card__cal-menu" hidden>
							<?php if ( $google_url ) : ?>
								<a href="<?php echo esc_url( $google_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Google Calendar', 'nnr-events' ); ?></a>
							<?php endif; ?>
							<a href="<?php echo esc_url( NNR_ICS::get_url( $event['id'] ) ); ?>"><?php esc_html_e( 'Apple / Outlook (.ics)', 'nnr-events' ); ?></a>
						</span>
					</span>
				<?php endif; ?>
			</span>
			<?php if ( $event['recurring'] ) : ?>
				<span class="nnr-event-card__badge"><?php esc_html_e( 'Weekly', 'nnr-events' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $range_note ) : ?>
			<div class="nnr-event-card__range"><?php echo $range_note; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped in format_date_range_note() */ ?></div>
		<?php endif; ?>

		<h3 class="nnr-event-card__title"><?php echo esc_html( $event['title'] ); ?></h3>

		<?php if ( $event['venue'] || $event['address'] ) : ?>
			<div class="nnr-event-card__venue">
				<?php echo esc_html( $event['venue'] ); ?>
				<?php if ( $event['address'] ) : ?>
					<span class="nnr-event-card__addr">&mdash; <?php echo esc_html( $event['address'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $event['description'] ) : ?>
			<div class="nnr-event-card__excerpt"><?php echo wp_kses_post( $event['description'] ); ?></div>
			<button type="button" class="nnr-event-card__read-more" data-nnr-title="<?php echo esc_attr( $event['title'] ); ?>" hidden><?php esc_html_e( 'Read more', 'nnr-events' ); ?></button>
		<?php endif; ?>

		<div class="nnr-event-card__foot">
			<?php if ( $event['price'] ) : ?>
				<span class="nnr-event-card__price"><?php echo esc_html( $event['price'] ); ?></span>
			<?php endif; ?>

			<?php if ( $event['ticket_url'] ) : ?>
				<a class="nnr-event-card__cta" href="<?php echo esc_url( $event['ticket_url'] ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( $event['button_text'] ? $event['button_text'] : __( 'Get Tickets', 'nnr-events' ) ); ?> &#8599;
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
