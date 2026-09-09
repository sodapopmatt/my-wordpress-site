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
			<?php if ( $date_label ) : ?>
				<span class="nnr-event-card__date"><?php echo esc_html( $date_label ); ?></span>
			<?php endif; ?>
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
					<?php esc_html_e( 'Get Tickets', 'nnr-events' ); ?> &#8599;
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
