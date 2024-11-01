<?php
/**
 * @var PoloPagPayments\Gateway\PoloPixGateway $this
 */
defined( 'ABSPATH' ) || exit;

$expiration_options = [ 
	600 => '10 minutos',
	900 => '15 minutos',
	1800 => '30 minutos',
	3600 => '1 hora',
	7200 => '2 horas',
	14400 => '4 horas',
	28800 => '8 horas',
	43200 => '12 horas',
	57600 => '16 horas',
	64800 => '18 horas',
	86400 => '24 horas',
];

$apply_discount_amount = preg_replace( '/\./i', ',', sanitize_text_field( $this->apply_discount_amount ) );

?>
<h2>
	<?php echo esc_html( $current_tab_name ); ?>
</h2>
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'check_payment_interval' ) ); ?>">Intervalo de
					tempo
					para
					verificar pagamento em segundos</label>
			</th>
			<td class="forminp">
				<fieldset>
					<input class="input-text regular-input " type="number"
						name="<?php echo esc_html( $this->get_field_name( 'check_payment_interval' ) ) ?>"
						id="<?php echo esc_html( $this->get_field_name( 'check_payment_interval' ) ) ?>"
						value="<?php echo esc_html( $this->check_payment_interval ); ?>" placeholder=""
						required="required" />
				</fieldset>
				<p class="description">O plugin faz requisições HTTP em um determinado intervalo de tempo para verificar
					se o
					pedido foi pago e mostrar a animação de concluído para o cliente sem ele precisar atualizar a pagina
					(isso só
					ocorre na pagina do QR Code para pagamento). Isso não influência na alteração do status para 'pago'
					do pedido,
					pois ela é instantânea. Isso só influência para o cliente.</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'expiration_seconds' ) ); ?>">
					<?php esc_html_e( 'Expiração do QR Code', 'wc-polo-payments' ); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<select class="select"
						name="<?php echo esc_html( $this->get_field_name( 'expiration_seconds' ) ); ?>"
						id="<?php echo esc_html( $this->get_field_name( 'expiration_seconds' ) ); ?>"
						style="width: 200px;" required="required">
						<?php foreach ( $expiration_options as $key => $value ) : ?>
							<option value="<?php echo esc_html( sprintf( "%d", $key ) ); ?>" <?php echo esc_html( $this->expiration_seconds == $key ? ' selected' : '' ); ?>>
								<?php echo esc_html( sprintf( "%s", $value ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'Tempo que o QR Code expira.', 'wc-polo-payments' ); ?>
				</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'apply_discount' ) ); ?>">Desconto ao pagar com
					PIX</label>
			</th>
			<td class="forminp">
				<fieldset>
					<label for="<?php echo esc_html( $this->get_field_name( 'apply_discount' ) ); ?>">
						<input class="" type="checkbox"
							name="<?php echo esc_html( $this->get_field_name( 'apply_discount' ) ); ?>"
							id="<?php echo esc_html( $this->get_field_name( 'apply_discount' ) ); ?>" <?php echo esc_html( $this->apply_discount == 'yes' ? 'checked' : '' ); ?>>Aplicar desconto ao selecionar
						o
						PIX como
						pagamento</label>
				</fieldset>
				<fieldset>
					<select class="select"
						name="<?php echo esc_html( $this->get_field_name( 'apply_discount_type' ) ); ?>"
						id="<?php echo esc_html( $this->get_field_name( 'apply_discount_type' ) ); ?>"
						style="width: 200px;" required="required">
						<option value="fixed" <?php echo esc_html( $this->apply_discount_type == 'fixed' ? ' selected' : '' ); ?>>Fixo
						</option>
						<option value="percentage" <?php echo esc_html( $this->apply_discount_type == 'percentage' ? ' selected' : '' ); ?>>
							Porcentagem</option>
					</select>
				</fieldset>
				<fieldset>
					<input class="input-text regular-input" type="text" pattern="[0-9]+([\,][0-9]+)?"
						title="Só aceita um número inteiro ou então separado por virgula com 2 casas decimais: ex 2,50"
						style="width: 200px;"
						name="<?php echo esc_html( $this->get_field_name( 'apply_discount_amount' ) ) ?>"
						id="<?php echo esc_html( $this->get_field_name( 'apply_discount_amount' ) ) ?>"
						value="<?php echo esc_html( $apply_discount_amount ) ?>" required="required" />
				</fieldset>
				<p class="description">Quando o usuário selecionar o pix como pagamento, será aplicado um desconto.</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'auto_cancel' ) ); ?>">Cancelar ao
					expirar</label>
			</th>
			<td class="forminp">
				<fieldset>
					<label for="<?php echo esc_html( $this->get_field_name( 'auto_cancel' ) ); ?>">
						<input class="" type="checkbox"
							name="<?php echo esc_html( $this->get_field_name( 'auto_cancel' ) ); ?>"
							id="<?php echo esc_html( $this->get_field_name( 'auto_cancel' ) ); ?>" <?php echo esc_html( $this->auto_cancel == 'yes' ? 'checked' : '' ); ?>>Cancelar pedidos automaticamente
						após
						expiração do QR
						Code</label>

				</fieldset>
				<p class="description">Quando o QR Code expirar, o pedido será cancelado automaticamente.</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'adicional_infos' ) ); ?>">Exibir informações
					dos produtos</label>
			</th>
			<td class="forminp">
				<fieldset>
					<label for="<?php echo esc_html( $this->get_field_name( 'adicional_infos' ) ); ?>">
						<input class="" type="checkbox"
							name="<?php echo esc_html( $this->get_field_name( 'adicional_infos' ) ); ?>"
							id="<?php echo esc_html( $this->get_field_name( 'adicional_infos' ) ); ?>" <?php echo esc_html( $this->adicional_infos == 'yes' ? 'checked' : '' ); ?>>Exibir informações dos
						produtos
						comprados</label>

				</fieldset>
				<p class="description">Quando o cliente escanear o QR Code, aparecerá os nomes e valores do produtos da
					compra como uma descrição adicional no aplicativo do banco dele (Funciona para alguns bancos)</p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_html( $this->get_field_name( 'show_instructions' ) ); ?>">Exibir
					instruções</label>
			</th>
			<td class="forminp">
				<fieldset>
					<label for="<?php echo esc_html( $this->get_field_name( 'show_instructions' ) ); ?>">
						<input class="" type="checkbox"
							name="<?php echo esc_html( $this->get_field_name( 'show_instructions' ) ); ?>"
							id="<?php echo esc_html( $this->get_field_name( 'show_instructions' ) ); ?>" <?php echo esc_html( $this->show_instructions == 'yes' ? 'checked' : '' ); ?>>Exibir instruções na página
						de obrigado</label>

				</fieldset>
				<p class="description">Quando o cliente finaliza a compra, na página de obrigado que exibe o QRCode,
					essa opçao adiciona instruções mais detalhadas para um cliente leigo que precise de um passo a
					passo.
				</p>
			</td>
		</tr>
	</tbody>
</table>