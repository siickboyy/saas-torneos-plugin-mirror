<?php
/**
 * Matches REST API (skeleton)
 *
 * @package SaaS_Torneos_Raqueta
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/model.php';

add_action( 'rest_api_init', function () {

	$ns = 'saas/v1';

	/**
	 * GET /matches
	 */
	register_rest_route( $ns, '/matches', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => function( $request ) {
			$user = wp_get_current_user();
			$cid  = (int) $request->get_param( 'competicion_id' );
			$ok   = str_matches_user_can( $user, 'read', $cid );
			if ( ! $ok ) {
				return new WP_Error( 'forbidden', 'No autorizado para ver estos partidos', array( 'status' => 403 ) );
			}
			return true;
		},
		'callback'            => function( WP_REST_Request $request ) {
			$params = $request->get_params();
			str_matches_log( 'API', array( 'op' => 'GET /matches', 'params' => $params ) );

			$page     = max( 1, (int) ( $params['page'] ?? 1 ) );
			$per_page = min( 200, max( 1, (int) ( $params['per_page'] ?? 50 ) ) );

			// Skeleton: lista vacía con paginación
			$response = array(
				'items'      => array(),
				'pagination' => array(
					'page'     => $page,
					'per_page' => $per_page,
					'total'    => 0,
				),
			);

			return rest_ensure_response( $response );
		},
	) );

	/**
	 * POST /matches
	 */
	register_rest_route( $ns, '/matches', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => function( $request ) {
			$user = wp_get_current_user();
			$cid  = (int) $request->get_param( 'competicion_id' );
			$ok   = str_matches_user_can( $user, 'write', $cid );
			if ( ! $ok ) {
				return new WP_Error( 'forbidden', 'No autorizado para crear partidos', array( 'status' => 403 ) );
			}
			return true;
		},
		'callback'            => function( WP_REST_Request $request ) {
			$body = $request->get_json_params();
			str_matches_log( 'API', array( 'op' => 'POST /matches', 'body' => $body ) );

			$val = str_matches_validate_payload( $body, 'create' );
			if ( ! $val['ok'] ) {
				return new WP_Error( $val['error']['code'], $val['error']['message'], array( 'status' => $val['error']['status'] ) );
			}

			// Skeleton: no insertamos aún; devolvemos mock normalizado.
			$mock = str_matches_normalize_match( rand( 1000, 9999 ), $body );
			return new WP_REST_Response( $mock, 201 );
		},
	) );

	/**
	 * PATCH /matches/{id}
	 */
	register_rest_route( $ns, '/matches/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => function( $request ) {
			$user = wp_get_current_user();
			// En skeleton no resolvemos el match real; tomamos competicion_id si viene en body para permisos.
			$body = $request->get_json_params();
			$cid  = (int) ( $body['competicion_id'] ?? 0 );
			$ok   = str_matches_user_can( $user, 'write', $cid );
			if ( ! $ok ) {
				return new WP_Error( 'forbidden', 'No autorizado para editar partidos', array( 'status' => 403 ) );
			}
			return true;
		},
		'callback'            => function( WP_REST_Request $request ) {
			$id   = (int) $request['id'];
			$body = $request->get_json_params();
			str_matches_log( 'API', array( 'op' => 'PATCH /matches/{id}', 'id' => $id, 'body' => $body ) );

			// Skeleton: validación superficial
			$mock = str_matches_normalize_match( $id, $body );
			return rest_ensure_response( $mock );
		},
	) );

	/**
	 * PATCH /matches/{id}/result
	 */
	register_rest_route( $ns, '/matches/(?P<id>\d+)/result', array(
		'methods'             => WP_REST_Server::EDITABLE,
		'permission_callback' => function( $request ) {
			$user = wp_get_current_user();
			$body = $request->get_json_params();
			$cid  = (int) ( $body['competicion_id'] ?? 0 );
			$ok   = str_matches_user_can( $user, 'write', $cid ); // en FASE C afinaremos (jugadores implicados)
			if ( ! $ok ) {
				return new WP_Error( 'forbidden', 'No autorizado para reportar resultados', array( 'status' => 403 ) );
			}
			return true;
		},
		'callback'            => function( WP_REST_Request $request ) {
			$id   = (int) $request['id'];
			$body = $request->get_json_params();
			str_matches_log( 'API', array( 'op' => 'PATCH /matches/{id}/result', 'id' => $id, 'body' => $body ) );

			// Skeleton: marcamos estado mock “pendiente_confirmacion”.
			$body['estado']               = 'pendiente_confirmacion';
			$body['resultado_confirmado'] = false;

			$mock = str_matches_normalize_match( $id, $body );
			return rest_ensure_response( $mock );
		},
	) );

	/**
	 * POST /matches/{id}/confirm
	 */
	register_rest_route( $ns, '/matches/(?P<id>\d+)/confirm', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => function( $request ) {
			$user = wp_get_current_user();
			$cid  = (int) $request->get_param( 'competicion_id' ); // puede llegar por query o body
			if ( ! $cid ) {
				$body = $request->get_json_params();
				$cid  = (int) ( $body['competicion_id'] ?? 0 );
			}
			$ok = str_matches_user_can( $user, 'write', $cid ); // en FASE C afinaremos (oponente/organizador/admin)
			if ( ! $ok ) {
				return new WP_Error( 'forbidden', 'No autorizado para confirmar resultados', array( 'status' => 403 ) );
			}
			return true;
		},
		'callback'            => function( WP_REST_Request $request ) {
			$id     = (int) $request['id'];
			$params = $request->get_params();
			str_matches_log( 'API', array( 'op' => 'POST /matches/{id}/confirm', 'id' => $id, 'params' => $params ) );

			// Skeleton: devolvemos estado confirmado y flag de recálculo de standings.
			$mock = str_matches_normalize_match( $id, array(
				'estado'               => 'confirmado',
				'resultado_confirmado' => true,
			) );

			$mock['_side_effects'] = array(
				'standings' => 'recalculate_requested',
			);

			return rest_ensure_response( $mock );
		},
	) );

	/**
	 * DELETE /matches/{id}
	 */
	register_rest_route( $ns, '/matches/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::DELETABLE,
		'permission_callback' => function( $request ) {
			$user = wp_get_current_user();
			$cid  = (int) $request->get_param( 'competicion_id' );
			$ok   = str_matches_user_can( $user, 'write', $cid );
			if ( ! $ok ) {
				return new WP_Error( 'forbidden', 'No autorizado para eliminar partidos', array( 'status' => 403 ) );
			}
			return true;
		},
		'callback'            => function( WP_REST_Request $request ) {
			$id     = (int) $request['id'];
			$params = $request->get_params();
			str_matches_log( 'API', array( 'op' => 'DELETE /matches/{id}', 'id' => $id, 'params' => $params ) );

			// Skeleton: no eliminamos aún; reportamos resultado mock.
			return rest_ensure_response( array(
				'deleted'        => false,
				'id'             => $id,
				'note'           => 'Skeleton: eliminación sin efectos (FASE B/C implementará persistencia).',
				'_side_effects'  => array( 'standings' => 'invalidate_if_confirmed' ),
			) );
		},
	) );

} );
