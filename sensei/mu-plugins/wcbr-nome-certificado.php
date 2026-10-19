<?php
/**
 * Plugin Name: WCBR 2026 — Nome no certificado
 * Description: Na página do curso, pede o nome completo de quem está fazendo o treinamento enquanto o perfil não tiver nome e sobrenome. O certificado do Sensei usa esses campos.
 */

add_action(
	'init',
	function () {
		if ( empty( $_POST['wcbr_nome_completo'] ) || ! is_user_logged_in() ) {
			return;
		}
		check_admin_referer( 'wcbr_nome_certificado' );

		$nome  = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( wp_unslash( $_POST['wcbr_nome_completo'] ) ) ) );
		$parts = explode( ' ', $nome, 2 );
		if ( count( $parts ) < 2 ) {
			return;
		}

		wp_update_user(
			array(
				'ID'           => get_current_user_id(),
				'first_name'   => $parts[0],
				'last_name'    => $parts[1],
				'display_name' => $nome,
				'nickname'     => $nome,
			)
		);
		wp_safe_redirect( remove_query_arg( 'wcbr_nome' ) );
		exit;
	}
);

add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'course' ) || ! in_the_loop() || ! is_main_query() || ! is_user_logged_in() ) {
			return $content;
		}
		$user = wp_get_current_user();
		if ( $user->first_name && $user->last_name ) {
			return $content;
		}

		$erro = ! empty( $_POST['wcbr_nome_completo'] ) ? '<p style="color:#b32d2e;margin:0 0 .5rem">Escreva nome e sobrenome.</p>' : '';
		$form = '<form method="post" style="border:2px solid currentColor;border-radius:8px;padding:1.25rem;margin:0 0 2rem">'
			. '<p style="margin:0 0 .5rem"><strong>Antes de começar: qual é o seu nome completo?</strong></p>'
			. '<p style="margin:0 0 1rem">Ele vai aparecer no seu certificado de conclusão.</p>'
			. $erro
			. '<label for="wcbr_nome_completo" style="display:block;margin-bottom:.25rem">Nome completo</label>'
			. '<input type="text" id="wcbr_nome_completo" name="wcbr_nome_completo" required autocomplete="name" style="width:100%;max-width:28rem;padding:.5rem;margin-bottom:1rem">'
			. wp_nonce_field( 'wcbr_nome_certificado', '_wpnonce', true, false )
			. '<br><button type="submit" class="wp-element-button">Salvar nome</button>'
			. '</form>';

		return $form . $content;
	},
	5
);
