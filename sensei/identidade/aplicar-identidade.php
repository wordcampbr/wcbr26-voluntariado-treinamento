<?php
/**
 * Aplica a identidade visual do site do WordCamp Brasil 2026 ao site do curso.
 *
 * Usa identidade.json (estilos globais, cabeçalho e overlay de navegação lidos
 * de brasil.wordcamp.org/2026 pela API) e os arquivos em uploads/wcbr/.
 * Uso: studio wp eval-file aplicar-identidade.php
 */

wp_set_current_user( 1 );

$dir   = __DIR__;
$id    = json_decode( file_get_contents( $dir . '/identidade.json' ), true );
$theme = 'twentytwentyfive';
$evento = 'https://brasil.wordcamp.org/2026/';

if ( get_stylesheet() !== $theme ) {
	switch_theme( $theme );
}

// Fontes locais no lugar das URLs de produção.
$fonts_url = content_url( 'uploads/wcbr/fonts/' );
$json      = wp_json_encode(
	array(
		'version'                     => 3,
		'isGlobalStylesUserThemeJSON' => true,
		'settings'                    => $id['settings'],
		'styles'                      => $id['styles'],
	)
);
$json = str_replace( 'https:\/\/brasil.wordcamp.org\/2026\/files\/fonts\/', str_replace( '/', '\/', $fonts_url ), $json );

$gs_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
wp_update_post( array( 'ID' => $gs_id, 'post_content' => wp_slash( $json ) ) );
WP_CLI::log( "Estilos globais: post $gs_id" );

// Logo e ícone.
require_once ABSPATH . 'wp-admin/includes/image.php';
function wcbr_anexo( $file, $title ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'title' => $title, 'numberposts' => 1, 'post_status' => 'inherit' ) );
	if ( $found ) {
		return $found[0]->ID;
	}
	$upload = wp_upload_dir();
	$id     = wp_insert_attachment(
		array(
			'post_title'     => $title,
			'post_mime_type' => wp_check_filetype( $file )['type'],
			'post_status'    => 'inherit',
			'guid'           => $upload['baseurl'] . '/wcbr/' . basename( $file ),
		),
		$file
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	return $id;
}
$up   = wp_upload_dir()['basedir'] . '/wcbr/';
$logo = wcbr_anexo( $up . 'logo.avif', 'Logo WordCamp Brasil 2026' );
$icon = wcbr_anexo( $up . 'icon.png', 'Ícone WordCamp Brasil 2026' );
update_option( 'site_logo', $logo );
update_option( 'site_icon', $icon );
set_theme_mod( 'custom_logo', $logo );

// Menu do curso.
$curso = get_posts( array( 'post_type' => 'course', 'numberposts' => 1 ) );
$links = array(
	array( 'Curso', $curso ? get_permalink( $curso[0] ) : home_url( '/' ) ),
	array( 'Meus cursos', home_url( '/meus-cursos/' ) ),
);
$nav_content = '';
foreach ( $links as $l ) {
	$nav_content .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $l[0], 'url' => $l[1], 'kind' => 'custom', 'isTopLevelLink' => true ) ) . " /-->\n";
}
$nav = get_posts( array( 'post_type' => 'wp_navigation', 'title' => 'Treinamento', 'numberposts' => 1, 'post_status' => 'any' ) );
$nav_id = $nav ? $nav[0]->ID : 0;
$nav_id = wp_insert_post(
	array( 'ID' => $nav_id, 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Treinamento', 'post_content' => $nav_content )
);
WP_CLI::log( "Menu: $nav_id" );

// Partes do template.
function wcbr_parte( $slug, $area, $title, $content, $theme ) {
	$existing = get_posts(
		array(
			'post_type'   => 'wp_template_part',
			'name'        => $slug,
			'numberposts' => 1,
			'post_status' => 'any',
			'tax_query'   => array( array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme ) ),
		)
	);
	$pid = wp_insert_post(
		array(
			'ID'           => $existing ? $existing[0]->ID : 0,
			'post_type'    => 'wp_template_part',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => wp_slash( $content ),
		)
	);
	wp_set_object_terms( $pid, $theme, 'wp_theme' );
	wp_set_object_terms( $pid, $area, 'wp_template_part_area' );
	return $pid;
}

$fix = function ( $content ) use ( $nav_id, $evento ) {
	$content = preg_replace( '/"ref":\d+/', '"ref":' . $nav_id, $content );
	$content = str_replace(
		array( 'href="https://brasil.wordcamp.org/2026/ingressos/"', '>Ingressos</a>' ),
		array( 'href="' . $evento . '"', '>Site do evento</a>' ),
		$content
	);
	return $content;
};

wcbr_parte( 'header', 'header', 'Header', $fix( $id['parts']['header']['content'] ), $theme );
wcbr_parte( 'navigation-overlay', 'navigation-overlay', 'Navigation Overlay', $fix( $id['parts']['navigation-overlay']['content'] ), $theme );

$footer = '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}},"elements":{"link":{"color":{"text":"var:preset|color|base"}}}},"backgroundColor":"custom-cyan-900","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-base-color has-custom-cyan-900-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:paragraph {"fontFamily":"poppins"} -->
<p class="has-poppins-font-family"><strong>WordCamp Brasil 2026</strong> · 30 e 31 de outubro · Universidade FUMEC, Belo Horizonte (MG)</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size">Treinamento da equipe de voluntários · <a href="' . $evento . '">Site do evento</a> · <a href="' . $evento . 'codigo-de-conduta/">Código de Conduta</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->';
wcbr_parte( 'footer', 'footer', 'Footer', $footer, $theme );

// Modelo da página do curso: cabeçalho, título, conteúdo e rodapé (sem a linha de autor e data).
$single_course = '<!-- wp:template-part {"slug":"header","theme":"twentytwentyfive","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:var(--wp--preset--spacing--50)"><!-- wp:post-title {"level":1,"fontSize":"x-large"} /-->

<!-- wp:post-content {"layout":{"type":"constrained"}} /--></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","theme":"twentytwentyfive","tagName":"footer"} /-->';
$existing = get_posts( array( 'post_type' => 'wp_template', 'name' => 'single-course', 'numberposts' => 1, 'post_status' => 'any' ) );
$tid = wp_insert_post( array( 'ID' => $existing ? $existing[0]->ID : 0, 'post_type' => 'wp_template', 'post_status' => 'publish', 'post_name' => 'single-course', 'post_title' => 'Curso', 'post_content' => wp_slash( $single_course ) ) );
wp_set_object_terms( $tid, $theme, 'wp_theme' );
WP_CLI::log( "Modelo single-course: $tid" );
// Página inicial no mesmo modelo dos guias do site (topo com imagem e degradê).
$capa = wcbr_anexo( $up . 'capa.webp', 'Capa WordCamp Brasil 2025' );
$capa_url = wp_get_attachment_url( $capa );
$curso_url = $curso ? get_permalink( $curso[0] ) : home_url( '/' );
$home = '<!-- wp:cover {"url":"' . $capa_url . '","id":' . $capa . ',"dimRatio":80,"isUserOverlayColor":true,"customGradient":"linear-gradient(135deg,rgb(245,101,48) 0%,rgb(0,89,93) 100%)","tagName":"section","align":"full","style":{"elements":{"link":{"color":{"text":"var:preset|color|base"}}},"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"textColor":"base","layout":{"type":"constrained"}} -->
<section class="wp-block-cover alignfull has-base-color has-text-color has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)"><img class="wp-block-cover__image-background wp-image-' . $capa . '" alt="" src="' . $capa_url . '" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-80 has-background-dim wp-block-cover__gradient-background has-background-gradient" style="background:linear-gradient(135deg,rgb(245,101,48) 0%,rgb(0,89,93) 100%)"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"className":"is-style-text-annotation"} -->
<p class="is-style-text-annotation"><strong>Equipe de voluntários</strong></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"is-style-text-display","style":{"typography":{"textAlign":"center"}},"fontSize":"x-large"} -->
<h1 class="wp-block-heading has-text-align-center is-style-text-display has-x-large-font-size">Treinamento de Voluntários</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size"><strong>WordCamp Brasil 2026 · 30 e 31 de outubro · Belo Horizonte</strong></p>
<!-- /wp:paragraph --></div></section>
<!-- /wp:cover -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained","contentSize":"42rem"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:paragraph -->
<p>Boas-vindas à equipe! Este treinamento reúne o que você precisa saber antes do evento: o papel de quem é voluntário, a logística, o Código de Conduta, o que fazer diante de um relato e as tarefas de cada função.</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"is-style-checkmark-list"} -->
<ul class="wp-block-list is-style-checkmark-list"><!-- wp:list-item -->
<li>10 lições curtas, cada uma com um quiz rápido</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Avaliação final com 10 perguntas</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Certificado de conclusão para enviar à coordenação</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"custom-orange-600","borderColor":"custom-orange-600"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-custom-orange-600-background-color has-background has-border-color has-custom-orange-600-border-color wp-element-button" href="' . $curso_url . '">Começar o treinamento</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->';
$inicio = get_page_by_path( 'inicio' );
$home_id = wp_insert_post( array( 'ID' => $inicio ? $inicio->ID : 0, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Início', 'post_name' => 'inicio', 'post_content' => wp_slash( $home ), 'page_template' => 'page-no-title' ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );
WP_CLI::log( "Página inicial: $home_id" );

WP_CLI::success( 'Identidade aplicada.' );
