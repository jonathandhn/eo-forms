# EO Forms

Manage your EmailOctopus subscribers with Elementor forms.

## Description

This plugin adds an **EmailOctopus Subscribe** action to Elementor Pro forms.

It subscribes users to an EmailOctopus list, maps common Elementor form fields to EmailOctopus fields, and can add tags.

By default, the contact is sent to EmailOctopus with the `PENDING` status. WordPress then sends a plain text confirmation email. When the user clicks the confirmation link, WordPress updates the EmailOctopus contact to `SUBSCRIBED`.

## Installation

1. Upload the `eo-forms` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Add your EmailOctopus API v2 key to `wp-config.php`:

```php
define( 'EMAILOCTOPUS_API_KEY', 'your_api_key' );
```

The plugin sends this key as a Bearer token to `https://api.emailoctopus.com`.

When the key is available, EO Forms retrieves EmailOctopus lists from `GET /lists` and shows them in Elementor. The list selector is cached for 10 minutes.

## Usage

1. Edit a page with Elementor.
2. Add or edit a Form widget.
3. In **Actions After Submit**, select **EmailOctopus Subscribe**.
4. Open the new **EmailOctopus** section.
5. Select your **EmailOctopus list**. If the API key is missing or WordPress cannot reach EmailOctopus, enter the list ID manually.
6. Keep **Subscription status** as **Pending** to let WordPress send the confirmation email.
7. Customize the plain text confirmation email:
   - **Confirmation email subject**
   - **Confirmation email message**
   - **Confirmation success URL**, optional
8. Map optional fields in **Field mappings**:

```text
firstname:FirstName
name:LastName
phone:Phone
company:Company
```

The value before `:` is the Elementor form field ID. The value after `:` is the EmailOctopus field tag.

Legacy shortcut fields are still available for `firstname`, `name`, `phone`, and `custom`, but **Field mappings** should be preferred for new forms.

9. Add optional comma-separated tags.

When WordPress can retrieve the selected EmailOctopus list, EO Forms verifies mapped EmailOctopus field tags before submitting the contact. Unknown mapped tags are shown as an Elementor error.

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

The form should preferably use these field IDs:

- `email` for the email address
- `firstname` for the first name
- `name` for the last name
- `phone` for the phone number
- `custom` for one custom value

If the email field ID is not `email`, EO Forms will use the first valid email value found in the submitted Elementor fields.

Any other Elementor field ID can be mapped through **Field mappings**.

## Requirements

- PHP 7.4 or higher
- Elementor Pro
- Valid EmailOctopus API key via `wp-config.php`
