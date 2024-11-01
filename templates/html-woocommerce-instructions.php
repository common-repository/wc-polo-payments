<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wc-polopag-pix-message" style="">
	<?php
	if ( $show_pix_image ) :
		?>
		<img src="<?php echo esc_html( sprintf( "%sassets/images/pix-banco-central.png", POLOPAGPAYMENTS_PLUGIN_URL ) ); ?>"
			alt="Pix Banco Central" height="90" />
	<?php endif; ?>
	<?php
	echo wp_kses_post( wptexturize( $checkout_message ) );
	?>
</div>