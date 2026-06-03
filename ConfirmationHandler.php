<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EOF_Confirmation_Handler {

	const API_BASE = 'https://api.emailoctopus.com';
	const TRANSIENT_PREFIX = 'eof_confirmation_';
	const CONFIRMATION_TTL = 7 * DAY_IN_SECONDS;

	public static function send_confirmation_email( $email, $settings ) {
		$token = wp_generate_password( 40, false, false );
		$link  = add_query_arg(
			[
				'action' => 'eof_confirm_subscription',
				'token'  => $token,
			],
			admin_url( 'admin-post.php' )
		);

		$success_url = '';

		if ( ! empty( $settings['EOF_confirmation_success_url'] ) ) {
			$success_url = esc_url_raw( $settings['EOF_confirmation_success_url'] );
		}

		set_site_transient(
			self::TRANSIENT_PREFIX . hash( 'sha256', $token ),
			[
				'email'       => sanitize_email( $email ),
				'list_id'     => sanitize_text_field( $settings['EOF_listID'] ),
				'success_url' => $success_url,
			],
			self::CONFIRMATION_TTL
		);

		$subject = ! empty( $settings['EOF_confirmation_subject'] )
			? sanitize_text_field( $settings['EOF_confirmation_subject'] )
			: __( 'Confirm your subscription', 'eo-forms' );

		$message = ! empty( $settings['EOF_confirmation_message'] )
			? sanitize_textarea_field( $settings['EOF_confirmation_message'] )
			: __( "Hello,\n\nPlease confirm your subscription by clicking this link:\n\n{confirmation_url}\n\nThank you.", 'eo-forms' );

		$replacements = [
			'{confirmation_url}' => esc_url_raw( $link ),
			'{email}'            => sanitize_email( $email ),
			'{site_name}'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		];

		$mail_sent = wp_mail( sanitize_email( $email ), $subject, strtr( $message, $replacements ) );

		if ( ! $mail_sent ) {
			error_log( 'EO Forms: WordPress could not send the confirmation email to ' . sanitize_email( $email ) . '.' );
		}

		return $mail_sent;
	}

	public static function confirm_subscription() {
		if ( ! defined( 'EMAILOCTOPUS_API_KEY' ) ) {
			wp_die( esc_html__( 'EmailOctopus API key is not configured.', 'eo-forms' ) );
		}

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( empty( $token ) ) {
			wp_die( esc_html__( 'Invalid confirmation link.', 'eo-forms' ) );
		}

		$transient_key = self::TRANSIENT_PREFIX . hash( 'sha256', $token );
		$confirmation  = get_site_transient( $transient_key );

		if ( empty( $confirmation['email'] ) || empty( $confirmation['list_id'] ) ) {
			wp_die( esc_html__( 'This confirmation link is invalid or has expired.', 'eo-forms' ) );
		}

		$response = self::request(
			'PUT',
			'/lists/' . rawurlencode( $confirmation['list_id'] ) . '/contacts/' . md5( strtolower( $confirmation['email'] ) ),
			[
				'email_address' => $confirmation['email'],
				'status'        => 'subscribed',
			]
		);

		if ( ! self::is_successful_response( $response ) ) {
			wp_die( esc_html__( 'Unable to confirm your subscription. Please try again later.', 'eo-forms' ) );
		}

		delete_site_transient( $transient_key );

		if ( ! empty( $confirmation['success_url'] ) ) {
			wp_safe_redirect( esc_url_raw( $confirmation['success_url'] ) );
			exit;
		}

		wp_die( esc_html__( 'Your subscription has been confirmed.', 'eo-forms' ) );
	}

	private static function request( $method, $path, $data ) {
		$response = wp_remote_request(
			self::API_BASE . $path,
			[
				'method'  => $method,
				'headers' => [
					'Authorization' => 'Bearer ' . EMAILOCTOPUS_API_KEY,
					'content-type'  => 'application/json; charset=utf-8',
				],
				'body'        => wp_json_encode( $data ),
				'data_format' => 'body',
				'timeout'     => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'EO Forms: ' . $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			error_log( 'EO Forms: EmailOctopus API returned HTTP ' . $status_code . ' - ' . wp_remote_retrieve_body( $response ) );
		}

		return $response;
	}

	private static function is_successful_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return 200 <= $status_code && 300 > $status_code;
	}
}
