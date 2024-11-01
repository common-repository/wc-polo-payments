<?php
/**
 * @var PoloPagPayments\Gateway\PoloPixGateway $this
 */

defined( 'ABSPATH' ) || exit;
?>
<h2>
	<?php echo esc_html( $current_tab_name ); ?>
</h2>
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'email_instruction' ) ); ?>">Customizar o e-mail
					de
					pedido
					recebido para pagamento PIX</label>
				<p class="wc-polo-payments-info">
					<?php
					/* translators: %1$s: qrcode */
					/* translators: %2$s: copy button */
					/* translators: %3$s: text code */
					/* translators: %4$s: expiration text */
					echo wp_kses( sprintf( __( '%1$s para inserir a imagem do QR Code %2$s para inserir o código QR Code em texto %3$s para inserir o link do site que conterá o botão copiar %4$s para inserir a data que expira o código', 'wc-polo-payments' ), '<code>[qr_code]</code>', '<br><br><code>[text_code]</code>', '<br><br><code>[link text="Clique aqui"]</code>', '<br><br><code>[expiration_date]</code>' ), [ "code" => "", "br" => "" ] );
					?>
				</p>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>Mensagem pix dentro do e-mail de pedido recebido</span>
					</legend>
					<?php
					wp_editor(
						wp_kses_post( wptexturize( $this->email_instruction ) ),
						"email_instruction",
						[ 
							'editor_class' => 'wc-polo-payments-editor',
							'textarea_name' => esc_html( $this->get_field_name( 'email_instruction' ) )
						]
					);
					?>
				</fieldset>
			</td>
		</tr>
	</tbody>
</table>