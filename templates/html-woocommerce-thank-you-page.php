<?php
defined( 'ABSPATH' ) || exit;

if ( $order ) {
	$paid = $order->get_meta( '_polopagpayments_paid' ) === 'yes' ? true : false;
}

$expiration_time = strtotime( $expiration_date );

$allowed_html = [ 
	"code" => [],
	"br" => [],
	"p" => [ 
		"style" => [

		],
		"class" => []
	],
	"i" => [ 
		"class" => []
	],
	"input" => [ 
		"class" => [],
		"type" => [],
		"readonly" => [],
		"value" => [],
		"id" => []
	],
	"button" => [ 
		"class" => []
	],
	"img" => [ 
		"src" => []
	],
	"div" => [ 
		"class" => [],
		"style" => []
	],
];

ob_start();
?>
<input class="wc-polopag-qrcode-input" type="text" readonly value="<?php echo esc_html( $qr_code ); ?>"
	id="pixQrCodeInput" />
<button class="button copy-qr-code"><i class="fa fa-copy fa-lg pr-3"></i>Clique aqui para copiar o código</button>
<div class="text-success wc-polopag-qrcode-copyed" style="text-align:center;margin-top:15px;">
	<p>
		Código
		copiado com
		sucesso!<br>Vá até o aplicativo do seu banco e cole o código.
	</p>

</div>

<?php
$copy_button_html = ob_get_clean();

ob_start();
?>
<img src="<?php echo esc_html( $qr_code_image ); ?>" />
<?php
$qr_code_html = ob_get_clean();

?>

<?php if ( $expiration_time <= current_time( 'timestamp' ) ) : ?>
	<div>
		<p>Seu QR Code Expirou, clique abaixo para fazer o pagamento.</p>
		<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"
			class="woocommerce-button wp-element-button button">Fazer o pagamento</a>
	</div>
<?php else : ?>
	<div class="wc-polopag-paybox text-center">
		<div id="successPixPaymentBox" style="display: <?php echo esc_html( $paid ? 'block' : 'none' ); ?>;">
			<h4>Obrigado pelo pagamento!</h4>
			<svg id="successAnimation" class="animated" xmlns="http://www.w3.org/2000/svg" width="180" height="180"
				viewBox="0 0 70 70">
				<path id="successAnimationResult" fill="#D8D8D8"
					d="M35,60 C21.1928813,60 10,48.8071187 10,35 C10,21.1928813 21.1928813,10 35,10 C48.8071187,10 60,21.1928813 60,35 C60,48.8071187 48.8071187,60 35,60 Z M23.6332378,33.2260427 L22.3667622,34.7739573 L34.1433655,44.40936 L47.776114,27.6305926 L46.223886,26.3694074 L33.8566345,41.59064 L23.6332378,33.2260427 Z" />
				<circle id="successAnimationCircle" cx="35" cy="35" r="24" stroke="#979797" stroke-width="2"
					stroke-linecap="round" fill="transparent" />
				<polyline id="successAnimationCheck" stroke="#979797" stroke-width="2" points="23 34 34 43 47 27"
					fill="transparent" />
			</svg>
			<?php echo wp_kses_post( nl2br( $thank_you_message ) ); ?>
		</div>
		<div id="watingPixPaymentBox" style="display: <?php echo esc_html( $paid ? 'none' : 'block' ); ?>;">
			<?php
			if ( preg_match( '/\[copy_button\]/i', $order_recived_message ) ) {
				$order_recived_message = preg_replace( '/\[copy_button\]/i', $copy_button_html, $order_recived_message, 1 );
			} else {
				$order_recived_message .= sprintf( '<p>%s</p>', $copy_button_html );
			}

			if ( preg_match( '/\[qr_code\]/i', $order_recived_message ) ) {
				$order_recived_message = preg_replace( '/\[qr_code\]/i', $qr_code_html, $order_recived_message, 1 );
			} else {
				$order_recived_message .= sprintf( '<p>%s</p>', $qr_code_html );
			}

			if ( preg_match( '/\[text_code\]/i', $order_recived_message ) ) {
				$order_recived_message = preg_replace( '/\[text_code\]/i', esc_html( $qr_code ), $order_recived_message, 1 );
			}

			if ( preg_match( '/\[expiration_date\]/i', $order_recived_message ) ) {
				$order_recived_message = preg_replace( '/\[expiration_date\]/i', gmdate( 'd/m/Y H:i:s', strtotime( $expiration_date ) ), $order_recived_message, 1 );
			}

			echo wp_kses( $order_recived_message, $allowed_html );

			if ( $show_instructions ) :
				?>
				<div class="polopag-instructions">
					<div class="polopag-instruction-item">
						<div class="polopag-instruction-number">1</div>
						<div class="polopag-instruction-text">Abra o aplicativo do seu banco para o pagamento.</div>
					</div>
					<div class="polopag-instruction-item">
						<div class="polopag-instruction-number">2</div>
						<div class="polopag-instruction-text">Selecione a opção de pagamento com PIX e QR Code.</div>
					</div>
					<div class="polopag-instruction-item">
						<div class="polopag-instruction-number">3</div>
						<div class="polopag-instruction-text">Aponte o celular para a esta tela na imagem do QR Code.</div>
					</div>
					<div class="polopag-instruction-item">
						<div class="polopag-instruction-number">4</div>
						<div class="polopag-instruction-text">Faça o pagamento e aguarde a confirmação automática nessa tela.
						</div>
					</div>
				</div>
				<?php
			endif;
			?>

			<input type="hidden" name="polopagpayments_order_key"
				value="<?php echo esc_html( sanitize_text_field( $order_key ) ); ?>" />
		</div>
	</div>
<?php endif; ?>