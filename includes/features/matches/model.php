<?php
/**
 * Matches Model (skeleton)
 *
 * @package SaaS_Torneos_Raqueta
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'str_matches_log' ) ) {
	/**
	 * Logger central del módulo de partidos.
	 */
	function str_matches_log( $tag, $data = null ) {
		$prefix = '[MATCHES:' . strtoupper( $tag ) . '] ';
		if ( is_array( $data ) || is_object( $data ) ) {
			$data = wp_json_encode( $data );
		}
		@error_log( $prefix . ( $data ?? '' ) );
	}
}

/**
 * Normaliza un partido a la forma “UI-friendly”.
 * Por ahora devuelve un mock estable para probar el contrato REST.
 *
 * @param int   $post_id
 * @param array $meta_overrides (opcional) para forzar valores en tests.
 * @return array
 */
function str_matches_normalize_match( $post_id, $meta_overrides = array() ) {
	$now_iso = gmdate( 'c' );

	$mock = array(
		'id'                   => (int) $post_id,
		'competicion_id'       => (int) ( $meta_overrides['competicion_id'] ?? 0 ),
		'fase'                 => (string) ( $meta_overrides['fase'] ?? 'grupos' ),
		'grupo_id'             => isset( $meta_overrides['grupo_id'] ) ? (int) $meta_overrides['grupo_id'] : null,
		'pareja_a_id'          => (int) ( $meta_overrides['pareja_a_id'] ?? 0 ),
		'pareja_b_id'          => (int) ( $meta_overrides['pareja_b_id'] ?? 0 ),
		'pareja_a_nombre'      => (string) ( $meta_overrides['pareja_a_nombre'] ?? 'Pareja A' ),
		'pareja_b_nombre'      => (string) ( $meta_overrides['pareja_b_nombre'] ?? 'Pareja B' ),
		'fecha'                => (string) ( $meta_overrides['fecha'] ?? '' ),
		'hora'                 => (string) ( $meta_overrides['hora'] ?? '' ),
		'pista'                => isset( $meta_overrides['pista'] ) ? (string) $meta_overrides['pista'] : null,
		'estado'               => (string) ( $meta_overrides['estado'] ?? 'programado' ),
		'sets'                 => (array)  ( $meta_overrides['sets'] ?? array() ),
		'ganador_pareja_id'    => isset( $meta_overrides['ganador_pareja_id'] ) ? (int) $meta_overrides['ganador_pareja_id'] : null,
		'resultado_confirmado' => (bool)   ( $meta_overrides['resultado_confirmado'] ?? false ),
		'resumen'              => 'Pareja A vs Pareja B — ' . ( $meta_overrides['fecha'] ?? 'TBD' ) . ' ' . ( $meta_overrides['hora'] ?? '' ),
		'created_at'           => $now_iso,
		'updated_at'           => $now_iso,
	);

	return $mock;
}

/**
 * Verifica permisos mínimos por rol/ownership.
 * Skeleton: admin siempre OK; resto: lectura pública y escritura denegada (se ampliará en FASE A.3).
 *
 * @param WP_User $user
 * @param string  $scope 'read'|'write'
 * @param int     $competicion_id
 * @return bool
 */
function str_matches_user_can( $user, $scope, $competicion_id = 0 ) {
	if ( user_can( $user, 'manage_options' ) ) {
		return true; // Admin total
	}
	// TODO: organizador (owner de la competición) y jugadores implicados (FASE A.3).
	if ( 'read' === $scope ) {
		return true;
	}
	return false;
}

/**
 * Validación básica del payload de creación/edición (skeleton).
 * Devuelve array( 'ok' => bool, 'error' => array|null ).
 */
function str_matches_validate_payload( $data, $context = 'create' ) {
	$required = array( 'competicion_id', 'fase', 'pareja_a_id', 'pareja_b_id' );
	if ( 'grupos' === ( $data['fase'] ?? '' ) ) {
		$required[] = 'grupo_id';
	}
	foreach ( $required as $key ) {
		if ( empty( $data[ $key ] ) && 0 !== $data[ $key ] ) {
			return array(
				'ok'    => false,
				'error' => array( 'code' => 'missing_' . $key, 'message' => 'Falta ' . $key, 'status' => 400 ),
			);
		}
	}
	// TODO: pertenencia (parejas/grupo/competición), duplicados, etc.
	return array( 'ok' => true, 'error' => null );
}
