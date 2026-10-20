<?php
/**
 * Plugin Name: WCBR 2026 — Certificado em português
 * Description: O sensei-certificates não tem tradução pt_BR; traduz os textos que aparecem para quem faz o curso.
 */

add_filter(
	'gettext_sensei-certificates',
	function ( $translation, $text ) {
		$pt = array(
			'View Certificate'  => 'Baixar o certificado (PDF)',
			'View certificate'  => 'Baixar o certificado (PDF)',
			'Certificate'       => 'Certificado',
			'Certificates'      => 'Certificados',
			'Download Certificate' => 'Baixar o certificado (PDF)',
		);
		return $pt[ $text ] ?? $translation;
	},
	10,
	2
);
