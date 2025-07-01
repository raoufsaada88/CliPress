<?php
/*
Plugin Name: CliPress
Description: Run basic WP-CLI commands directly from wp-admin, with secure role-based access and command logging.
Version: 1.1.1
Author: ITRS Consulting
Author URI: https://www.itrsconsulting.com
Text Domain: clipress
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class CliPress {
    private $allowed_roles = ['administrator'];
    private $log_option_key = 'clipress_command_logs';
    private $max_logs = 50; // Limit stored logs to avoid bloating options table

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_post_clipress_run_command', [$this, 'run_command']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_clipress' ) {
            return;
        }
        wp_enqueue_style('dashicons');
    }

    public function print_custom_styles() {
        ?>
        <style>
            #clipress-command-output {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 1em;
                font-family: Menlo, Monaco, monospace;
                white-space: pre-wrap;
                word-wrap: break-word;
                max-height: 300px;
                overflow-y: auto;
                margin-top: 1em;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-radius: 4px;
            }
            .clipress-command-table {
                margin-top: 2em;
                width: 100%;
                border-collapse: collapse;
            }
            .clipress-command-table th,
            .clipress-command-table td {
                padding: 0.75em 1em;
                text-align: left;
                vertical-align: middle;
                border-bottom: 1px solid #ddd;
            }
            .clipress-command-table code {
                font-family: Menlo, Monaco, monospace;
                background: #f7f7f7;
                padding: 2px 5px;
                border-radius: 3px;
                white-space: nowrap;
            }
        </style>
        <?php
    }

    public function add_admin_page() {
        if ( $this->user_can_access() ) {
            add_menu_page(
                __('CliPress', 'clipress'),
                __('CliPress', 'clipress'),
                'manage_options',
                'clipress',
                [$this, 'render_admin_page'],
                'dashicons-editor-code',
                90
            );
        }
    }

    private function user_can_access() {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $user = wp_get_current_user();
        foreach ( $this->allowed_roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                return true;
            }
        }
        return false;
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'clipress' ) );
        }

        $this->print_custom_styles();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'CliPress', 'clipress' ) . '</h1>';
        echo '<p>' . esc_html__( 'Run basic WP-CLI commands securely from your WordPress admin.', 'clipress' ) . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width: 700px;">';
        echo '<input type="hidden" name="action" value="clipress_run_command">';
        wp_nonce_field( 'clipress_command_run' );
        echo '<textarea name="command" rows="5" style="width: 100%; font-family: monospace;" placeholder="' . esc_attr__( 'Enter WP-CLI command (e.g., wp plugin list)', 'clipress' ) . '" required></textarea>';
        echo '<br><br>';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__( 'Run Command', 'clipress' ) . '">';
        echo '</form>';

        if ( isset( $_GET['output'] ) ) {
            // Sanitize and decode output carefully
            $decoded_output = base64_decode( wp_unslash( $_GET['output'] ), true );
            if ( $decoded_output === false ) {
                $decoded_output = esc_html__( 'Unable to decode command output.', 'clipress' );
            } else {
                // Escape output for safe display
                $decoded_output = esc_html( $decoded_output );
            }
            echo '<h2>' . esc_html__( 'Command Output:', 'clipress' ) . '</h2>';
            echo '<pre id="clipress-command-output">' . $decoded_output . '</pre>';
        }

        $logs = get_option( $this->log_option_key, [] );
        if ( ! empty( $logs ) && is_array( $logs ) ) {
            echo '<h2>' . esc_html__( 'Recent Commands:', 'clipress' ) . '</h2>';
            echo '<table class="clipress-command-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Time', 'clipress' ) . '</th>';
            echo '<th>' . esc_html__( 'User', 'clipress' ) . '</th>';
            echo '<th>' . esc_html__( 'Command', 'clipress' ) . '</th>';
            echo '<th>' . esc_html__( 'Output (truncated)', 'clipress' ) . '</th>';
            echo '</tr></thead><tbody>';

            $recent_logs = array_slice( array_reverse( $logs ), 0, 10 );
            foreach ( $recent_logs as $log ) {
                $time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), intval( $log['timestamp'] ) );
                $user_info = get_userdata( intval( $log['user_id'] ) );
                $username = $user_info ? esc_html( $user_info->user_login ) : esc_html__( 'Unknown', 'clipress' );
                $command = esc_html( $log['command'] );
                $output = esc_html( mb_strimwidth( $log['output'], 0, 80, '...' ) );

                echo "<tr>
                    <td>{$time}</td>
                    <td>{$username}</td>
                    <td><code>{$command}</code></td>
                    <td><pre style='white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>{$output}</pre></td>
                </tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__( 'No command history yet.', 'clipress' ) . '</p>';
        }

        echo '</div>';
    }

    public function run_command() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'clipress' ) );
        }
        check_admin_referer( 'clipress_command_run' );

        if ( empty( $_POST['command'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=clipress&output=' . urlencode( base64_encode( esc_html__( 'Error: No command entered.', 'clipress' ) ) ) ) );
            exit;
        }

        // Sanitize and trim command input
        $command = trim( sanitize_text_field( wp_unslash( $_POST['command'] ) ) );

        // Security: Allow only commands starting with 'wp ' (case-insensitive)
        if ( stripos( $command, 'wp ' ) !== 0 ) {
            wp_redirect( admin_url( 'admin.php?page=clipress&output=' . urlencode( base64_encode( esc_html__( 'Error: Only WP-CLI commands starting with "wp " are allowed.', 'clipress' ) ) ) ) );
            exit;
        }

        // Escape shell command properly (note: escapeshellcmd may be over-restrictive)
        // For better security, consider whitelisting allowed commands or using WP-CLI PHP API if available.
        $escaped_command = escapeshellcmd( $command );

        // Execute command - WARNING: shell_exec can be dangerous. Host environment must allow and secure it.
        $output = @shell_exec( $escaped_command );

        if ( $output === null ) {
            $output = esc_html__( 'Command execution failed or no output.', 'clipress' );
        }

        // Log command with limit
        $logs = get_option( $this->log_option_key, [] );
        $logs[] = [
            'timestamp' => time(),
            'user_id'   => get_current_user_id(),
            'command'   => $command,
            'output'    => $output,
        ];

        // Trim logs to max size
        if ( count( $logs ) > $this->max_logs ) {
            $logs = array_slice( $logs, -$this->max_logs );
        }

        update_option( $this->log_option_key, $logs );

        wp_redirect( admin_url( 'admin.php?page=clipress&output=' . urlencode( base64_encode( $output ) ) ) );
        exit;
    }
}

new CliPress();
