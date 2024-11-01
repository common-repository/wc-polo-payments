<?php
defined( 'ABSPATH' ) || exit;

ob_start();
?>
<img src="<?php echo esc_html( $qr_code_image ); ?>" />
<?php
$qr_code_html = ob_get_clean();

if ( preg_match( '/\[qr_code\]/i', $email_instruction ) ) {
	$email_instruction = preg_replace( '/\[qr_code\]/i', $qr_code_html, $email_instruction, 1 );
}

if ( preg_match( '/\[(link)\s{0,}(text=[\"\”](.+)[\"\”])?\s{0,}\]/i', $email_instruction, $matches ) ) {
	$email_instruction = preg_replace( '/\[link.+\]/i', '<a href="' . $order_url . '">' . ( isset( $matches[3] ) ? $matches[3] : 'Clique aqui' ) . '</a>', $email_instruction, 1 );
}

if ( preg_match( '/\[text_code\]/i', $email_instruction ) ) {
	$email_instruction = preg_replace( '/\[text_code\]/i', $qr_code, $email_instruction, 1 );
}

if ( preg_match( '/\[expiration_date\]/i', $email_instruction ) ) {
	$email_instruction = preg_replace( '/\[expiration_date\]/i', gmdate( 'd/m/Y H:i:s', strtotime( $expiration_date ) ), $email_instruction );
}

echo wp_kses_post( $email_instruction );

?>