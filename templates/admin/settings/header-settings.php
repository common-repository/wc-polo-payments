<?php
/**
 * @var PoloPagPayments\Gateway\PoloPixGateway $this
 */

defined( 'ABSPATH' ) || exit;
?>
<h1>PoloPag - Pix</h1>
<p>
	<?php echo wp_kses_post( wpautop( $this->method_description ) ); ?>
</p>
<ul class="wc-polo-payments">
	<?php
	foreach ( $this->get_tabs() as $key => $value ) {
		$current_class = $current_tab == $key ? ' class="current"' : '';
		echo wp_kses( sprintf( '<li%s><a href="%s" aria-current="%s">%s</a></li>', $current_class, add_query_arg( 'mgn_tab', $key, $baseUrl ), $key, $value ), [ "li" => [ "class" => [] ], "a" => [ "href" => [], "aria-current" => [] ] ] );
	}
	?>
</ul>
<br class="clear">