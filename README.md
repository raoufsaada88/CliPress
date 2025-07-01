# CliPress - SSH Center

Run basic WP-CLI commands securely from the WordPress admin dashboard.

---

## Description

CliPress allows WordPress administrators to execute basic WP-CLI commands directly from the wp-admin area without needing SSH access to the server. It includes security restrictions to only allow commands starting with `wp`, role-based access control (administrators only), and logs all commands with timestamps and user IDs for audit purposes.

---

## Features

- Execute WP-CLI commands securely via a simple admin interface.  
- Restricts commands to those starting with `wp` for safety.  
- Command history log with timestamps and user info.  
- Styled cleanly using WordPress admin styles and dashicons.  
- Lightweight, no external dependencies.

---

## Installation

1. Download or clone this repository.  
2. Upload the `clipress` folder to your WordPress `wp-content/plugins/` directory.  
3. Activate the plugin through the 'Plugins' menu in WordPress.  
4. Access CliPress from the admin sidebar.

---

## Usage

- Navigate to the **CliPress** menu in the WordPress admin.  
- Enter a WP-CLI command in the text area (e.g., `wp plugin list`).  
- Click **Run Command** to execute.  
- View the output and recent command history on the page.

---

## Requirements

- WordPress 5.0+  
- PHP 7.4+ (recommended PHP 8.x)  
- Administrator user role to access the plugin

---

## Security

This plugin restricts command execution to only those starting with `wp` and limits access to administrators. However, running shell commands via PHP carries risks, so use cautiously and only on trusted environments.

---

## License

MIT License — see the LICENSE file for details.

---

## Author

ITRS Consulting — [https://www.itrsconsulting.com](https://www.itrsconsulting.com)
