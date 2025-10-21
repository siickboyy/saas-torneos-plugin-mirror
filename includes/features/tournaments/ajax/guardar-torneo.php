<?php
// includes/features/tournaments/ajax/guardar-torneo.php
if ( ! defined('ABSPATH') ) exit;

/** Helpers */
if ( ! function_exists('saas_tr_first') ) {
  function saas_tr_first(array $keys, $default = '') {
    foreach ($keys as $k) {
      if (isset($_POST[$k]) && $_POST[$k] !== '') return wp_unslash($_POST[$k]);
      if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') return wp_unslash($_REQUEST[$k]);
    }
    return $default;
  }
}

/** Registra el mismo callback bajo varios nombres (compatibilidad) */
$__saas_tr_actions = [
  'str_guardar_torneo_editado', // existente
  'saas_tr_torneo_guardar',     // el que envía el front actual
  'saas_guardar_torneo',
  'guardar_torneo',
];

foreach ($__saas_tr_actions as $__a) {
  add_action("wp_ajax_${__a}",        'str_guardar_torneo_editado_cb');
  add_action("wp_ajax_nopriv_${__a}", 'str_guardar_torneo_editado_cb');
}

/** Callback unificado */
function str_guardar_torneo_editado_cb() {
  if ( ! function_exists('wp_send_json_error') ) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'data'=>['code'=>'wp_missing']]);
    exit;
  }

  // 1) Requiere usuario autenticado
  if ( ! is_user_logged_in() ) {
    wp_send_json_error([
      'code'    => 'auth_required',
      'message' => 'Permisos insuficientes. Debes iniciar sesión.',
    ], 403);
  }

  // 2) Nonce tolerante (si viene)
  $nonce = saas_tr_first(['nonce','security','_wpnonce','saas_nonce'], '');
  $nonce_ok = false;
  if ($nonce !== '') {
    $salts = ['str_nonce','saas_tr_nonce','saas_nonce','wp_rest'];
    foreach ($salts as $salt) {
      if (wp_verify_nonce($nonce, $salt)) { $nonce_ok = true; break; }
    }
    if ( ! $nonce_ok ) {
      wp_send_json_error([
        'code'       => 'nonce_invalid',
        'message'    => 'Nonce inválido o caducado.',
        'new_nonce'  => wp_create_nonce('str_nonce'),
      ], 403);
    }
  }

  // 3) ID del torneo
  $torneo_id = intval( saas_tr_first(['torneo_id','post_id','id'], 0) );
  if ( ! $torneo_id || get_post_type($torneo_id) !== 'competicion' ) {
    wp_send_json_error([
      'code'    => 'bad_request',
      'message' => 'ID de torneo inválido.',
    ], 400);
  }

  // 4) Capacidades cuando no hay nonce válido
  if ( ! $nonce_ok && ! current_user_can('edit_post', $torneo_id) ) {
    wp_send_json_error([
      'code'    => 'cap_required',
      'message' => 'Permisos insuficientes para editar este torneo.',
    ], 403);
  }

  // 5) Campos (compatibles con varias UIs)
  $titulo      = saas_tr_first(['titulo','post_title','nombre_torneo'], '');
  $descripcion = saas_tr_first(['descripcion','content','descripcion_competicion','desc'], '');
  $fecha       = saas_tr_first(['fecha_torneo','fecha'], '');
  $hora_inicio = saas_tr_first(['hora_inicio','inicio','horaIni'], '');
  $hora_fin    = saas_tr_first(['hora_fin','fin','horaFin'], '');

  // 6) Guardado
  if ( $titulo !== '' ) {
    wp_update_post(['ID'=>$torneo_id, 'post_title'=>wp_strip_all_tags($titulo)]);
  }

  if ( function_exists('update_field') ) {
    update_field('descripcion_competicion', wp_kses_post($descripcion), $torneo_id);
  } else {
    update_post_meta($torneo_id, 'descripcion_competicion', wp_kses_post($descripcion));
  }

  $grupo = [
    'fecha_torneo' => sanitize_text_field($fecha),
    'hora_inicio'  => sanitize_text_field($hora_inicio),
    'hora_fin'     => sanitize_text_field($hora_fin),
  ];

  if ( function_exists('update_field') ) {
    update_field('tipo_competicion_torneo', $grupo, $torneo_id); // ACF group
  } else {
    update_post_meta($torneo_id, 'fecha',       $grupo['fecha_torneo']);
    update_post_meta($torneo_id, 'hora_inicio', $grupo['hora_inicio']);
    update_post_meta($torneo_id, 'hora_fin',    $grupo['hora_fin']);
  }

  do_action('saas_tr/torneo_editado', $torneo_id, $grupo);

  wp_send_json_success([
    'message' => 'Torneo actualizado',
    'post_id' => $torneo_id,
    'data'    => $grupo,
  ]);
}
