<?php

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Classes\Action_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EOF_Subscribe_Action_After_Submit extends Action_Base {

	const API_BASE = 'https://emailoctopus.com/api/1.6';

	public function get_name() {
		return 'EOF_Subscribe';
	}

	public function get_label() {
		return __( 'EmailOctopus Subscribe', 'eo-forms' );
	}

	public function run( $record, $ajax_handler ) {
		if ( ! defined( 'EMAILOCTOPUS_API_KEY' ) ) {
			error_log( 'EO Forms: EMAILOCTOPUS_API_KEY not defined.' );
			return;
		}

		$settings = $record->get( 'form_settings' );

		if ( empty( $settings['EOF_listID'] ) ) {
			return;
		}

		$fields = $this->get_form_fields( $record );

		if ( empty( $fields['email'] ) || ! is_email( $fields['email'] ) ) {
			return;
		}

		$email = sanitize_email( $fields['email'] );
		$data  = [
			'api_key'       => EMAILOCTOPUS_API_KEY,
			'email_address' => $email,
			'status'        => $this->get_contact_status( $settings ),
		];

		$contact_fields = $this->get_contact_fields( $fields, $settings );

		if ( ! empty( $contact_fields ) ) {
			$data['fields'] = $contact_fields;
		}

		$tags = $this->get_tags( $settings );

		if ( ! empty( $tags ) ) {
			$data['tags'] = $tags;
		}

		$response = $this->request(
			'POST',
			'/lists/' . rawurlencode( $settings['EOF_listID'] ) . '/contacts',
			$data
		);

		if ( 'MEMBER_EXISTS_WITH_EMAIL_ADDRESS' === $this->get_error_code( $response ) ) {
			$response = $this->update_contact( $settings['EOF_listID'], $email, $data, $tags );
		}

		if ( 'PENDING' === $data['status'] && $this->is_successful_response( $response ) ) {
			EOF_Confirmation_Handler::send_confirmation_email( $email, $settings );
		}
	}

	public function register_settings_section( $widget ) {
		$widget->start_controls_section(
			'section_EOF',
			[
				'label'     => __( 'EmailOctopus', 'eo-forms' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'EOF_listID',
			[
				'label'       => __( 'EmailOctopus List ID', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Insert the list ID you want to subscribe a user to.', 'eo-forms' ),
			]
		);

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
			'EOF_firstname_field',
			[
				'label'       => __( 'EmailOctopus FirstName field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Set your EmailOctopus first name field tag, e.g. "FirstName".', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_name_field',
			[
				'label'       => __( 'EmailOctopus LastName field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Set your EmailOctopus last name field tag, e.g. "LastName".', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_phone_field',
			[
				'label'       => __( 'EmailOctopus Phone field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Set your EmailOctopus phone field tag, e.g. "Phone".', 'eo-forms' ),
			]
		);

		$widget->add_control(
			'EOF_custom_field',
			[
				'label'       => __( 'EmailOctopus Custom field', 'eo-forms' ),
				'type'        => Controls_Manager::TEXT,
				'separator'   => 'before',
				'description' => __( 'Set your own EmailOctopus custom field tag.', 'eo-forms' ),
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

	private function get_contact_fields( $fields, $settings ) {
		$field_map = [
			'firstname' => 'EOF_firstname_field',
			'name'      => 'EOF_name_field',
			'phone'     => 'EOF_phone_field',
			'custom'    => 'EOF_custom_field',
		];

		$contact_fields = [];

		foreach ( $field_map as $form_field => $setting_key ) {
			if ( empty( $fields[ $form_field ] ) || empty( $settings[ $setting_key ] ) ) {
				continue;
			}

			$contact_fields[ sanitize_text_field( $settings[ $setting_key ] ) ] = sanitize_text_field( $fields[ $form_field ] );
		}

		return $contact_fields;
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
			return $settings['EOF_status'];
		}

		return 'PENDING';
	}

	private function update_contact( $list_id, $email, $data, $tags ) {
		if ( ! empty( $tags ) ) {
			$data['tags'] = array_fill_keys( $tags, true );
		}

		return $this->request(
			'PUT',
			'/lists/' . rawurlencode( $list_id ) . '/contacts/' . md5( strtolower( $email ) ),
			$data
		);
	}

	private function request( $method, $path, $data ) {
		$response = wp_remote_request(
			self::API_BASE . $path,
			[
				'method'  => $method,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15,
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

	private function get_error_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['code'] ) ) {
			return '';
		}

		return $body['code'];
	}

	private function is_successful_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $response );
	}
}
