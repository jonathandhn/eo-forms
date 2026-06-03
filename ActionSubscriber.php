<?php

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Classes\Action_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EOF_Subscribe_Action_After_Submit extends Action_Base {

	const API_BASE = 'https://api.emailoctopus.com';

	public function get_name() {
		return 'EOF_Subscribe';
	}

	public function get_label() {
		return __( 'EmailOctopus Subscribe', 'eo-forms' );
	}

	public function run( $record, $ajax_handler ) {
		if ( ! defined( 'EMAILOCTOPUS_API_KEY' ) ) {
			error_log( 'EO Forms: EMAILOCTOPUS_API_KEY not defined.' );
			$this->add_error( $ajax_handler, __( 'EmailOctopus API key is not configured.', 'eo-forms' ) );
			return;
		}

		$settings = $record->get( 'form_settings' );

		if ( empty( $settings['EOF_listID'] ) ) {
			$this->add_error( $ajax_handler, __( 'EmailOctopus list is not configured.', 'eo-forms' ) );
			return;
		}

		$fields = $this->get_form_fields( $record );
		$email  = $this->get_email_from_fields( $fields );

		if ( empty( $email ) ) {
			$this->add_error( $ajax_handler, __( 'A valid email field is required. Use the field ID "email" or provide one valid email value in the form.', 'eo-forms' ) );
			return;
		}

		$data  = [
			'email_address' => $email,
			'status'        => $this->get_contact_status( $settings ),
		];

		$contact_fields = $this->get_contact_fields( $fields, $settings );

		if ( ! empty( $contact_fields ) ) {
			$invalid_fields = $this->get_invalid_contact_fields( $settings['EOF_listID'], array_keys( $contact_fields ) );

			if ( ! empty( $invalid_fields ) ) {
				$this->add_error(
					$ajax_handler,
					sprintf(
						/* translators: %s: comma-separated EmailOctopus field tags. */
						__( 'Unknown EmailOctopus field mapping: %s', 'eo-forms' ),
						implode( ', ', $invalid_fields )
					)
				);
				return;
			}

			$data['fields'] = $contact_fields;
		}

		$tags = $this->get_tags( $settings );

		if ( ! empty( $tags ) ) {
			$data['tags'] = array_fill_keys( $tags, true );
		}

		$response = $this->request(
			'PUT',
			'/lists/' . rawurlencode( $settings['EOF_listID'] ) . '/contacts',
			$data
		);

		if ( ! $this->is_successful_response( $response ) ) {
			$this->add_error( $ajax_handler, $this->get_response_error_message( $response ) );
			return;
		}

		if ( 'pending' === $data['status'] && $this->is_successful_response( $response ) ) {
			$mail_sent = EOF_Confirmation_Handler::send_confirmation_email( $email, $settings );

			if ( ! $mail_sent ) {
				$this->add_error( $ajax_handler, __( 'The confirmation email could not be sent. Please try again later.', 'eo-forms' ) );
			}
		}
	}

	public function register_settings_section( $widget ) {
		$list_options = $this->get_list_options();

		$widget->start_controls_section(
			'section_EOF',
			[
				'label'     => __( 'EmailOctopus', 'eo-forms' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		if ( ! empty( $list_options ) ) {
			$widget->add_control(
				'EOF_listID',
				[
					'label'       => __( 'EmailOctopus list', 'eo-forms' ),
					'type'        => Controls_Manager::SELECT,
					'separator'   => 'before',
					'options'     => $list_options,
					'description' => __( 'Select the EmailOctopus list to subscribe the user to.', 'eo-forms' ),
				]
			);
		} else {
			$widget->add_control(
				'EOF_listID',
				[
					'label'       => __( 'EmailOctopus List ID', 'eo-forms' ),
					'type'        => Controls_Manager::TEXT,
					'separator'   => 'before',
					'description' => __( 'Insert the list ID manually. Automatic list loading requires a valid EMAILOCTOPUS_API_KEY and API access from WordPress.', 'eo-forms' ),
				]
			);
		}

		$widget->add_control(
			'EOF_status',
			[
				'label'       => __( 'Subscription status', 'eo-forms' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'PENDING',
				'options'     => [
					'PENDING'    => __( 'Pending', 'eo-forms' ),
					'SUBSCRIBED' => __( 'Subscribed', 'eo-forms' ),
				],
				'description' => __( 'Use Pending to send a WordPress confirmation email. The confirmation link will then mark the contact as Subscribed.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_confirmation_subject',
			[
				'label'       => __( 'Confirmation email subject', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Confirm your subscription', 'eo-forms' ),
				'condition'   => [
					'EOF_status' => 'PENDING',
				],
				'description' => __( 'Plain text email sent by WordPress.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_confirmation_message',
			[
				'label'       => __( 'Confirmation email message', 'eo-forms' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => __( "Hello,\n\nPlease confirm your subscription by clicking this link:\n\n{confirmation_url}\n\nThank you.", 'eo-forms' ),
				'condition'   => [
					'EOF_status' => 'PENDING',
				],
				'description' => __( 'Available placeholders: {confirmation_url}, {email}, {site_name}.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_confirmation_success_url',
			[
				'label'       => __( 'Confirmation success URL', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'condition'   => [
					'EOF_status' => 'PENDING',
				],
				'description' => __( 'Optional URL to redirect to after confirmation.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_field_mappings',
			[
				'label'       => __( 'Field mappings', 'eo-forms' ),
				'type'        => Controls_Manager::TEXTAREA,
				'separator'   => 'before',
				'description' => __( 'Map Elementor field IDs to EmailOctopus field tags, one per line. Example: firstname:FirstName', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_firstname_field',
			[
				'label'       => __( 'EmailOctopus FirstName field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Legacy shortcut. Prefer Field mappings above for new forms.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_name_field',
			[
				'label'       => __( 'EmailOctopus LastName field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Legacy shortcut. Prefer Field mappings above for new forms.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_phone_field',
			[
				'label'       => __( 'EmailOctopus Phone field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Legacy shortcut. Prefer Field mappings above for new forms.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_custom_field',
			[
				'label'       => __( 'EmailOctopus Custom field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Legacy shortcut. Prefer Field mappings above for new forms.', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_tags',
			[
				'label'       => __( 'EmailOctopus Tags', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Optional comma-separated tags to add to the contact.', 'eo-forms' ),
			]
		);

		$widget->end_controls_section();
	}

	public function on_export( $element ) {
		unset(
			$element['EOF_listID'],
			$element['EOF_status'],
			$element['EOF_confirmation_subject'],
			$element['EOF_confirmation_message'],
			$element['EOF_confirmation_success_url'],
			$element['EOF_field_mappings'],
			$element['EOF_firstname_field'],
			$element['EOF_name_field'],
			$element['EOF_phone_field'],
			$element['EOF_custom_field'],
			$element['EOF_tags']
		);

		return $element;
	}

	private function get_form_fields( $record ) {
		$raw_fields = $record->get( 'fields' );
		$fields     = [];

		foreach ( $raw_fields as $id => $field ) {
			$fields[ $id ] = isset( $field['value'] ) ? $field['value'] : '';
		}

		return $fields;
	}

	private function get_email_from_fields( $fields ) {
		if ( ! empty( $fields['email'] ) && is_email( $fields['email'] ) ) {
			return sanitize_email( $fields['email'] );
		}

		foreach ( $fields as $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			if ( is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		return '';
	}

	private function get_contact_fields( $fields, $settings ) {
		$contact_fields = $this->get_mapped_contact_fields( $fields, $settings );
		$field_map = [
			'firstname' => 'EOF_firstname_field',
			'name'      => 'EOF_name_field',
			'phone'     => 'EOF_phone_field',
			'custom'    => 'EOF_custom_field',
		];

		foreach ( $field_map as $form_field => $setting_key ) {
			if ( empty( $fields[ $form_field ] ) || empty( $settings[ $setting_key ] ) ) {
				continue;
			}

			$contact_fields[ sanitize_text_field( $settings[ $setting_key ] ) ] = $this->sanitize_field_value( $fields[ $form_field ] );
		}

		return $contact_fields;
	}

	private function get_mapped_contact_fields( $fields, $settings ) {
		if ( empty( $settings['EOF_field_mappings'] ) ) {
			return [];
		}

		$contact_fields = [];
		$lines          = preg_split( '/\r\n|\r|\n/', $settings['EOF_field_mappings'] );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $form_field, $emailoctopus_field ) = array_map( 'trim', explode( ':', $line, 2 ) );

			if ( '' === $form_field || '' === $emailoctopus_field || empty( $fields[ $form_field ] ) ) {
				continue;
			}

			$contact_fields[ sanitize_text_field( $emailoctopus_field ) ] = $this->sanitize_field_value( $fields[ $form_field ] );
		}

		return $contact_fields;
	}

	private function sanitize_field_value( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_map( [ $this, 'sanitize_field_value' ], $value ) );
		}

		return sanitize_text_field( $value );
	}

	private function get_tags( $settings ) {
		if ( empty( $settings['EOF_tags'] ) ) {
			return [];
		}

		$tags = array_map( 'trim', explode( ',', $settings['EOF_tags'] ) );
		$tags = array_filter( $tags );

		return array_values( array_map( 'sanitize_text_field', $tags ) );
	}

	private function get_contact_status( $settings ) {
		if ( ! empty( $settings['EOF_status'] ) && in_array( $settings['EOF_status'], [ 'SUBSCRIBED', 'PENDING' ], true ) ) {
			return strtolower( $settings['EOF_status'] );
		}

		return 'pending';
	}

	private function get_list_options() {
		if ( ! defined( 'EMAILOCTOPUS_API_KEY' ) ) {
			return [];
		}

		$cache_key = 'eof_emailoctopus_lists_' . md5( EMAILOCTOPUS_API_KEY );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::API_BASE . '/lists?limit=100',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . EMAILOCTOPUS_API_KEY,
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'EO Forms: Could not retrieve EmailOctopus lists - ' . $response->get_error_message() );
			return [];
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			error_log( 'EO Forms: EmailOctopus list retrieval returned HTTP ' . $status_code . ' - ' . wp_remote_retrieve_body( $response ) );
			return [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return [];
		}

		$options = [];

		foreach ( $body['data'] as $list ) {
			if ( empty( $list['id'] ) || empty( $list['name'] ) ) {
				continue;
			}

			$options[ sanitize_text_field( $list['id'] ) ] = sanitize_text_field( $list['name'] );
		}

		set_transient( $cache_key, $options, 10 * MINUTE_IN_SECONDS );

		return $options;
	}

	private function get_invalid_contact_fields( $list_id, $field_tags ) {
		$available_field_tags = $this->get_available_field_tags( $list_id );

		if ( null === $available_field_tags ) {
			return [];
		}

		return array_values( array_diff( $field_tags, $available_field_tags ) );
	}

	private function get_available_field_tags( $list_id ) {
		if ( ! defined( 'EMAILOCTOPUS_API_KEY' ) ) {
			return null;
		}

		$cache_key = 'eof_emailoctopus_fields_' . md5( EMAILOCTOPUS_API_KEY . ':' . $list_id );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			self::API_BASE . '/lists/' . rawurlencode( $list_id ),
			[
				'headers' => [
					'Authorization' => 'Bearer ' . EMAILOCTOPUS_API_KEY,
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'EO Forms: Could not retrieve EmailOctopus fields - ' . $response->get_error_message() );
			return null;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			error_log( 'EO Forms: EmailOctopus field retrieval returned HTTP ' . $status_code . ' - ' . wp_remote_retrieve_body( $response ) );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['fields'] ) || ! is_array( $body['fields'] ) ) {
			return null;
		}

		$field_tags = [];

		foreach ( $body['fields'] as $field ) {
			if ( empty( $field['tag'] ) ) {
				continue;
			}

			$field_tags[] = sanitize_text_field( $field['tag'] );
		}

		set_transient( $cache_key, $field_tags, 10 * MINUTE_IN_SECONDS );

		return $field_tags;
	}

	private function request( $method, $path, $data ) {
		$curl_headers = function( $handle ) {
			curl_setopt(
				$handle,
				CURLOPT_HTTPHEADER,
				[
					'Authorization: Bearer ' . EMAILOCTOPUS_API_KEY,
					'Content-Type: application/json',
				]
			);
		};

		add_action( 'http_api_curl', $curl_headers );

		$response = wp_remote_request(
			self::API_BASE . $path,
			[
				'method'  => $method,
				'headers' => [
					'Authorization' => 'Bearer ' . EMAILOCTOPUS_API_KEY,
					'Content-Type'  => 'application/json',
				],
				'body'        => wp_json_encode( $data ),
				'data_format' => 'body',
				'timeout'     => 15,
			]
		);

		remove_action( 'http_api_curl', $curl_headers );

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

	private function is_successful_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		return 200 <= $status_code && 300 > $status_code;
	}

	private function get_response_error_message( $response ) {
		if ( is_wp_error( $response ) ) {
			return sprintf(
				/* translators: %s: WordPress error message. */
				__( 'EmailOctopus subscription failed: %s', 'eo-forms' ),
				$response->get_error_message()
			);
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$details = [];

		if ( ! empty( $body['detail'] ) ) {
			$details[] = sanitize_text_field( $body['detail'] );
		}

		if ( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
			foreach ( $body['errors'] as $error ) {
				if ( empty( $error['detail'] ) ) {
					continue;
				}

				$pointer   = ! empty( $error['pointer'] ) ? sanitize_text_field( $error['pointer'] ) . ': ' : '';
				$details[] = $pointer . sanitize_text_field( $error['detail'] );
			}
		}

		if ( empty( $details ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			$details[]   = sprintf(
				/* translators: %d: HTTP status code. */
				__( 'HTTP %d returned by EmailOctopus.', 'eo-forms' ),
				$status_code
			);
		}

		return sprintf(
			/* translators: %s: EmailOctopus API error details. */
			__( 'EmailOctopus subscription failed: %s', 'eo-forms' ),
			implode( ' ', $details )
		);
	}

	private function add_error( $ajax_handler, $message ) {
		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_error_message' ) ) {
			$ajax_handler->add_error_message( $message );
		}
	}
}
