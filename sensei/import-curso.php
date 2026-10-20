<?php
/**
 * Importa o curso de voluntários (modulos.json) no Sensei LMS.
 * Uso: studio wp eval-file import-curso.php
 */

wp_set_current_user( 1 );

$mods = json_decode( file_get_contents( __DIR__ . '/modulos.json' ), true );

$course_title = 'Treinamento de Voluntários — WordCamp Brasil 2026';
$existing     = get_posts( array( 'post_type' => 'course', 'title' => $course_title, 'post_status' => 'any', 'numberposts' => -1 ) );
foreach ( $existing as $old ) {
	if ( ! in_array( 'recriar', $args ?? array(), true ) ) {
		WP_CLI::error( "O curso já existe (ID {$old->ID}). Rode com o argumento recriar para apagar e importar de novo." );
	}
	foreach ( get_posts( array( 'post_type' => array( 'lesson', 'quiz', 'question' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
		wp_delete_post( $p->ID, true );
	}
	foreach ( get_terms( array( 'taxonomy' => 'module', 'hide_empty' => false ) ) as $term ) {
		wp_delete_term( $term->term_id, 'module' );
	}
	wp_delete_post( $old->ID, true );
	WP_CLI::log( "Curso antigo {$old->ID} apagado." );
}

$intro = '<!-- wp:paragraph {"className":"sensei-content-description"} -->
<p class="sensei-content-description">Boas-vindas à equipe de voluntários do WordCamp Brasil 2026! Este curso reúne o que você precisa saber antes de 30 e 31 de outubro, na Universidade FUMEC, em Belo Horizonte: o papel de quem é voluntário, a logística, o Código de Conduta, o que fazer diante de um relato e as tarefas de cada função. São 9 lições curtas, cada uma com um quiz rápido, e uma avaliação final. Ao passar na avaliação final, você recebe o certificado de conclusão: envie-o para a coordenação como comprovante.</p>
<!-- /wp:paragraph -->

<!-- wp:sensei-lms/button-take-course -->
<div class="wp-block-sensei-lms-button-take-course is-style-default wp-block-sensei-button wp-block-button has-text-align-left"><button class="wp-block-button__link">Começar o curso</button></div>
<!-- /wp:sensei-lms/button-take-course -->

<!-- wp:sensei-lms/course-progress /-->

<!-- wp:sensei-lms/course-outline /-->';

$course_id = wp_insert_post(
	array(
		'post_type'    => 'course',
		'post_status'  => 'publish',
		'post_title'   => $course_title,
		'post_content' => $intro,
	),
	true
);
if ( is_wp_error( $course_id ) ) {
	WP_CLI::error( $course_id );
}

$groups = array(
	array( 'Para toda a equipe', array( 1, 2, 3, 4, 8 ) ),
	array( 'Por função', array( 5, 6, 7 ) ),
	array( 'Encerramento', array( 9, 10 ) ),
);
$by_n   = array();
foreach ( $mods as $m ) {
	$by_n[ $m['n'] ] = $m;
}

$structure = array();
foreach ( $groups as $g ) {
	$lessons = array();
	foreach ( $g[1] as $n ) {
		$lessons[] = array(
			'type'  => 'lesson',
			'title' => $by_n[ $n ]['n'] . '. ' . $by_n[ $n ]['title'],
			'draft' => false,
		);
	}
	$structure[] = array(
		'type'        => 'module',
		'title'       => $g[0],
		'description' => '',
		'lessons'     => $lessons,
	);
}

$req = new WP_REST_Request( 'POST', '/sensei-internal/v1/course-structure/' . $course_id );
$req->set_header( 'Content-Type', 'application/json' );
$req->set_body( wp_json_encode( array( 'structure' => $structure ) ) );
$res = rest_do_request( $req );
if ( $res->is_error() ) {
	WP_CLI::error( 'Estrutura: ' . wp_json_encode( $res->get_data() ) );
}

$saved = $res->get_data();
$ids   = array();
foreach ( $saved as $module ) {
	foreach ( $module['lessons'] as $lesson ) {
		$n         = (int) strtok( $lesson['title'], '.' );
		$ids[ $n ] = $lesson['id'];
	}
}

$actions = '<!-- wp:sensei-lms/lesson-actions -->
<div class="wp-block-sensei-lms-lesson-actions"><div class="sensei-buttons-container"><!-- wp:sensei-lms/button-view-quiz {"inContainer":true} -->
<div class="wp-block-sensei-lms-button-view-quiz is-style-default sensei-buttons-container__button-block wp-block-sensei-lms-button-view-quiz__wrapper"><div class="wp-block-sensei-lms-button-view-quiz is-style-default wp-block-sensei-button wp-block-button has-text-align-left"><button class="wp-block-button__link">Fazer o quiz</button></div></div>
<!-- /wp:sensei-lms/button-view-quiz -->

<!-- wp:sensei-lms/button-next-lesson {"inContainer":true} -->
<div class="wp-block-sensei-lms-button-next-lesson is-style-default sensei-buttons-container__button-block wp-block-sensei-lms-button-next-lesson__wrapper"><div class="wp-block-sensei-lms-button-next-lesson is-style-default wp-block-sensei-button wp-block-button has-text-align-left"><button class="wp-block-button__link">Próxima lição</button></div></div>
<!-- /wp:sensei-lms/button-next-lesson --></div></div>
<!-- /wp:sensei-lms/lesson-actions -->';

$quiz_options = array(
	'pass_required'               => true,
	'quiz_passmark'               => 60,
	'auto_grade'                  => true,
	'allow_retakes'               => true,
	'show_questions'              => null,
	'random_question_order'       => false,
	'failed_indicate_incorrect'   => true,
	'failed_show_correct_answers' => true,
	'failed_show_answer_feedback' => true,
);

foreach ( $ids as $n => $lesson_id ) {
	$m = $by_n[ $n ];

	$questions = array();
	foreach ( $m['questions'] as $q ) {
		$questions[] = array(
			'title'   => $q['title'],
			'type'    => 'multiple-choice',
			'answer'  => array( 'answers' => $q['answers'] ),
			'options' => array( 'randomOrder' => true, 'grade' => 1 ),
		);
	}

	$opts = $quiz_options;
	if ( 10 === $n ) {
		$opts['quiz_passmark'] = 70;
	}
	$qreq = new WP_REST_Request( 'POST', '/sensei-internal/v1/lesson-quiz/' . $lesson_id );
	$qreq->set_header( 'Content-Type', 'application/json' );
	$qreq->set_body( wp_json_encode( array( 'options' => $opts, 'questions' => $questions ) ) );
	$qres = rest_do_request( $qreq );
	if ( $qres->is_error() ) {
		WP_CLI::warning( "Quiz da lição $n: " . wp_json_encode( $qres->get_data() ) );
		continue;
	}
	$qdata = $qres->get_data();

	$qblocks = '';
	foreach ( $qdata['questions'] as $saved_q ) {
		$qblocks .= '<!-- wp:sensei-lms/quiz-question ' . wp_json_encode( array( 'id' => $saved_q['id'] ) ) . " /-->\n";
	}
	$quiz_block = '<!-- wp:sensei-lms/quiz ' . wp_json_encode( array( 'id' => $qdata['id'] ?? null ) ) . " -->\n" . $qblocks . '<!-- /wp:sensei-lms/quiz -->';

	$content = '<p><em>Leitura de cerca de ' . (int) $m['minutes'] . " minutos.</em></p>\n\n" . $m['html'] . "\n\n" . $actions . "\n\n" . $quiz_block;

	wp_update_post(
		array(
			'ID'           => $lesson_id,
			'post_content' => $content,
			'post_status'  => 'publish',
		)
	);
	WP_CLI::log( sprintf( 'Lição %d (ID %d): %d perguntas, quiz %s', $n, $lesson_id, count( $qdata['questions'] ), $qdata['id'] ?? '?' ) );
}

// Certificado de conclusão. O sensei-certificates cria o modelo por cron, que não roda no
// blueprint do Playground; sem modelo, o curso não gera certificado nem mostra o botão.
if ( ! get_posts( array( 'post_type' => 'certificate_template', 'post_status' => 'any', 'numberposts' => 1 ) ) && class_exists( 'WooThemes_Sensei_Certificates' ) ) {
	WooThemes_Sensei_Certificates::instance()->install();
}
$template = get_posts( array( 'post_type' => 'certificate_template', 'post_status' => 'any', 'numberposts' => 1 ) );
if ( $template ) {
	$tid    = $template[0]->ID;
	$fields = get_post_meta( $tid, '_certificate_template_fields', true );
	$fields['certificate_heading']['text']            = 'Certificado de conclusão';
	$fields['certificate_course']['text']             = '{{learner}}';
	$fields['certificate_course']['font']['style']    = 'BC';
	$fields['certificate_course']['font']['size']     = '40';
	$fields['certificate_message']['text']            = "concluiu o Treinamento de Voluntários\ndo WordCamp Brasil 2026";
	$fields['certificate_message']['font']['style']   = 'C';
	$fields['certificate_message']['font']['size']    = '26';
	$fields['certificate_completion']['text']         = 'Belo Horizonte, {{completion_date}}';
	update_post_meta( $tid, '_certificate_template_fields', $fields );
	update_post_meta( $tid, '_certificate_heading', 'Certificado de conclusão' );
	wp_update_post( array( 'ID' => $tid, 'post_title' => 'Certificado de voluntário WCBR 2026' ) );
	update_post_meta( $course_id, '_course_certificate_template', $tid );
	WP_CLI::log( "Certificado: modelo $tid ligado ao curso." );
} else {
	WP_CLI::warning( 'Nenhum modelo de certificado. Instale e ative o sensei-certificates.' );
}

// Páginas do Sensei em português.
foreach ( array( 'courses-overview' => array( 'Cursos', 'cursos' ), 'my-courses' => array( 'Meus cursos', 'meus-cursos' ), 'course-completed' => array( 'Curso concluído', 'curso-concluido' ) ) as $slug => $pt ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_title' => $pt[0], 'post_name' => $pt[1] ) );
	}
}
// A página "Curso concluído" é criada na ativação do Sensei, antes do idioma: texto em inglês.
$completed = get_page_by_path( 'curso-concluido' );
if ( $completed ) {
	wp_update_post(
		array(
			'ID'           => $completed->ID,
			'post_content' => '<!-- wp:paragraph {"align":"center","fontSize":"large"} --><p class="has-text-align-center has-large-font-size">Parabéns, você concluiu o treinamento! 🥳</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Baixe o seu certificado em PDF e envie para a coordenação do voluntariado como comprovante.</p><!-- /wp:paragraph -->'
				. '<!-- wp:buttons {"contentJustification":"center","anchor":"course-completed-actions"} --><div class="wp-block-buttons is-content-justification-center" id="course-completed-actions">'
				. '<!-- wp:button {"className":"view-certificate"} --><div class="wp-block-button view-certificate"><a class="wp-block-button__link">Baixar o certificado (PDF)</a></div><!-- /wp:button -->'
				. '</div><!-- /wp:buttons --><!-- wp:sensei-lms/course-results /-->',
		)
	);
}
update_option( 'sensei_suggest_setup_wizard', 0 );
flush_rewrite_rules();

WP_CLI::success( "Curso criado: ID $course_id — " . get_permalink( $course_id ) );
