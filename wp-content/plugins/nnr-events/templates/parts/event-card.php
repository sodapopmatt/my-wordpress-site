<?php
/**
 * Single event card. Expects $event (array from NNR_Shortcode::get_event_data) in scope.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$date_label  = NNR_Shortcode::format_date_label( $event );
$show_image  = ( 'hide' !== $atts['image'] ) && $event['image'];
?>
<div class="nnr-event-card">
	<?php if ( $show_image ) : ?>
		<div class="nnr-event-card__image" style="background-image:url('<?php echo esc_url( $event['image'] ); ?>');"></div>
	<?php endif; ?>

	<div class="nnr-event-card__body">
		<div class="nnr-event-card__row">
			<span class="nnr-event-card__when">
				<?php if ( $date_label ) : ?>
					<span class="nnr-event-card__date"><?php echo esc_html( $date_label ); ?></span>
				<?php endif; ?>
				<?php if ( $event['start_date'] ) : ?>
					<a class="nnr-event-card__ics" href="<?php echo esc_url( NNR_ICS::get_url( $event['id'] ) ); ?>" title="<?php esc_attr_e( 'Add to Calendar', 'nnr-events' ); ?>" aria-label="<?php esc_attr_e( 'Add to Calendar', 'nnr-events' ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
							<rect x="3" y="4" width="18" height="18" rx="2"></rect>
							<line x1="16" y1="2" x2="16" y2="6"></line>
							<line x1="8" y1="2" x2="8" y2="6"></line>
							<line x1="3" y1="10" x2="21" y2="10"></line>
							<line x1="12" y1="14" x2="12" y2="18"></line>
							<line x1="10" y1="16" x2="14" y2="16"></line>
						</svg>
					</a>
				<?php endif; ?>
			</span>
			<?php if ( $event['recurring'] ) : ?>
				<span class="nnr-event-card__badge"><?php esc_html_e( 'Weekly', 'nnr-events' ); ?></span>
			<?php endif; ?>
		</div>

		<h3 class="nnr-event-card__title"><?php echo esc_html( $event['title'] ); ?></h3>

		<?php if ( $event['venue'] || $event['address'] ) : ?>
			<div class="nnr-event-card__venue">
				<?php echo esc_html( $event['venue'] ); ?>
				<?php if ( $event['address'] ) : ?>
					<span class="nnr-event-card__addr">&mdash; <?php echo esc_html( $event['address'] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $event['excerpt'] ) : ?>
			<p class="nnr-event-card__excerpt"><?php echo esc_html( $event['excerpt'] ); ?></p>
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
