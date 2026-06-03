# EO Forms

Manage your EmailOctopus subscribers with Elementor forms.

## Description

This plugin adds an **EmailOctopus Subscribe** action to Elementor Pro forms.

It subscribes users to an EmailOctopus list, maps common Elementor form fields to EmailOctopus fields, and can add tags.

By default, the contact is sent to EmailOctopus with the `PENDING` status. WordPress then sends a plain text confirmation email. When the user clicks the confirmation link, WordPress updates the EmailOctopus contact to `SUBSCRIBED`.

## Installation

1. Upload the `eo-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Add your EmailOctopus API key to `wp-config.php`:

```php
define( 'EMAILOCTOPUS_API_KEY', 'your_api_key' );
```

## Usage

1. Edit a page with Elementor.
2. Add or edit a Form widget.
3. In **Actions After Submit**, select **EmailOctopus Subscribe**.
4. Open the new **EmailOctopus** section.
5. Enter your **EmailOctopus List ID**.
6. Keep **Subscription status** as **Pending** to let WordPress send the confirmation email.
7. Customize the plain text confirmation email:
   - **Confirmation email subject**
   - **Confirmation email message**
   - **Confirmation success URL**, optional
8. Map optional field tags:
   - **EmailOctopus FirstName field**: e.g. `FirstName`
   - **EmailOctopus LastName field**: e.g. `LastName`
   - **EmailOctopus Phone field**: e.g. `Phone`
   - **EmailOctopus Custom field**: any custom EmailOctopus field tag
9. Add optional comma-separated tags.

## WordPress Confirmation Flow

1. The Elementor form is submitted.
2. EO Forms creates or updates the contact in EmailOctopus with `PENDING`.
3. WordPress sends a plain text email with a confirmation link.
4. The confirmation link calls:

```text
/wp-admin/admin-post.php?action=eof_confirm_subscription&token=...
```

5. WordPress validates the token and updates the EmailOctopus contact to `SUBSCRIBED`.

Confirmation tokens are stored as WordPress site transients and expire after 7 days.

Available email placeholders:

- `{confirmation_url}`
- `{email}`
- `{site_name}`

## Elementor Field IDs

The form should use these field IDs:

- `email` for the email address, mandatory
- `firstname` for the first name
- `name` for the last name
- `phone` for the phone number
- `custom` for one custom value

## Requirements

- PHP 7.4 or higher
- Elementor Pro
- Valid EmailOctopus API key via `wp-config.php`
