<?php
/**
 * Plugin Name:      Crypto Wallet & Mining Generator - Enhanced
 * Description:      A modern crypto wallet with enhanced deposit/withdraw functionality, mining rewards, and transaction history.
 * Version:          20.0.0
 * Author:           YourName & Enhanced
 * License:          GPL-2.0-or-later
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      crypto-wallet-complete
 */

if (!defined('ABSPATH')) exit;

define('CWM_VERSION', '20.0.0');
define('CWM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CWM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CWM_REWARDS_TABLE', $GLOBALS['wpdb']->prefix . 'mining_rewards');
define('CWM_PURCHASES_TABLE', $GLOBALS['wpdb']->prefix . 'wallet_purchases');
define('CWM_DEPOSITS_TABLE', $GLOBALS['wpdb']->prefix . 'wallet_deposits');
define('CWM_WITHDRAWALS_TABLE', $GLOBALS['wpdb']->prefix . 'wallet_withdrawals');

function cwm_install_and_update() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Original tables
    $sql_rewards = "CREATE TABLE " . CWM_REWARDS_TABLE . " ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        user_id bigint(20) NOT NULL, 
        reward_date date NOT NULL, 
        transaction_id varchar(20) NOT NULL, 
        package_name varchar(50) NOT NULL, 
        coin_name varchar(10) NOT NULL, 
        coin_amount decimal(20, 8) NOT NULL, 
        details text NOT NULL, 
        status varchar(20) NOT NULL, 
        PRIMARY KEY (id), 
        KEY user_id (user_id), 
        KEY reward_date (reward_date) 
    ) $charset_collate;";
    dbDelta($sql_rewards);

    $sql_purchases = "CREATE TABLE " . CWM_PURCHASES_TABLE . " ( 
        id mediumint(9) NOT NULL AUTO_INCREMENT, 
        user_id bigint(20) UNSIGNED NOT NULL, 
        item_id bigint(20) UNSIGNED NOT NULL, 
        price decimal(10, 2) NOT NULL, 
        currency varchar(10) NOT NULL DEFAULT 'USD', 
        paid_with_coin varchar(10) NOT NULL, 
        paid_coin_amount decimal(20, 8) NOT NULL, 
        type varchar(20) NOT NULL, 
        item_name varchar(255) NOT NULL, 
        status varchar(20) NOT NULL DEFAULT 'pending', 
        transaction_date datetime NOT NULL, 
        PRIMARY KEY (id), 
        KEY user_id (user_id), 
        KEY type (type) 
    ) $charset_collate;";
    dbDelta($sql_purchases);

    // New tables for deposits and withdrawals
    $sql_deposits = "CREATE TABLE " . CWM_DEPOSITS_TABLE . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        coin_name varchar(10) NOT NULL,
        network varchar(50) NOT NULL,
        amount decimal(20, 8) NOT NULL,
        deposit_address varchar(255) NOT NULL,
        txid varchar(255) DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        created_date datetime NOT NULL,
        updated_date datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_deposits);

    $sql_withdrawals = "CREATE TABLE " . CWM_WITHDRAWALS_TABLE . " (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        withdraw_type varchar(20) NOT NULL,
        coin_name varchar(10) NOT NULL,
        network varchar(50) DEFAULT NULL,
        amount decimal(20, 8) NOT NULL,
        withdraw_address varchar(255) DEFAULT NULL,
        bank_details text DEFAULT NULL,
        exchange_details text DEFAULT NULL,
        txid varchar(255) DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        created_date datetime NOT NULL,
        updated_date datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql_withdrawals);

    update_option('cwm_version', CWM_VERSION);
}
register_activation_hook(__FILE__, 'cwm_install_and_update');

function cwm_update_check() {
    if (get_option('cwm_version') != CWM_VERSION) {
        cwm_install_and_update();
    }
}
add_action('plugins_loaded', 'cwm_update_check');

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

final class Crypto_Wallet_Merged {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_cwm_generate_rewards', array($this, 'handle_generate_rewards'));
        add_action('admin_post_cwm_manual_balance', array($this, 'handle_manual_balance'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('crypto_wallet_dashboard', array($this, 'render_dashboard_shortcode'));
        add_action('init', array($this, 'handle_wallet_purchase'));
        add_action('admin_post_cwm_save_purchase', array($this, 'handle_save_purchase'));
        
        // New deposit/withdrawal handlers
        add_action('wp_ajax_cwm_submit_deposit', array($this, 'handle_deposit_request'));
        add_action('wp_ajax_nopriv_cwm_submit_deposit', array($this, 'handle_deposit_request'));
        add_action('wp_ajax_cwm_submit_withdrawal', array($this, 'handle_withdrawal_request'));
        add_action('wp_ajax_nopriv_cwm_submit_withdrawal', array($this, 'handle_withdrawal_request'));
        add_action('wp_ajax_cwm_get_networks', array($this, 'ajax_get_networks'));
        add_action('wp_ajax_nopriv_cwm_get_networks', array($this, 'ajax_get_networks'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_cwm_update_deposit_status', array($this, 'ajax_update_deposit_status'));
        add_action('wp_ajax_cwm_update_withdrawal_status', array($this, 'ajax_update_withdrawal_status'));

        // Original handlers
        add_action('wp_ajax_cwm_update_purchase_status', array($this, 'ajax_update_purchase_status'));
        add_action('wp_ajax_cwm_update_purchase_date', array($this, 'ajax_update_purchase_date'));
        add_action('wp_ajax_cwm_manual_refund', array($this, 'ajax_manual_refund'));
        add_action('wp_ajax_cwm_get_chart_data', array($this, 'ajax_get_chart_data'));
        add_action('wp_ajax_nopriv_cwm_get_chart_data', array($this, 'ajax_get_chart_data'));
    }

    public function add_admin_menu() {
        add_menu_page('Crypto Wallet', 'Crypto Wallet', 'manage_options', 'cwm-wallet-admin', array($this, 'render_admin_page'), 'dashicons-chart-line', 6);
        add_submenu_page('cwm-wallet-admin', 'Purchase History', 'Purchase History', 'manage_options', 'cwm-purchase-history', array($this, 'render_purchase_history_page'));
        add_submenu_page('cwm-wallet-admin', 'Deposit Management', 'Deposits', 'manage_options', 'cwm-deposit-management', array($this, 'render_deposit_management_page'));
        add_submenu_page('cwm-wallet-admin', 'Withdrawal Management', 'Withdrawals', 'manage_options', 'cwm-withdrawal-management', array($this, 'render_withdrawal_management_page'));
        add_submenu_page(null, 'Add/Edit Purchase', 'Add/Edit Purchase', 'manage_options', 'cwm-purchase-form', array($this, 'render_purchase_form_page'));
    }

    // NEW: Deposit Management Page
    public function render_deposit_management_page() {
        global $wpdb;
        $deposits = $wpdb->get_results("SELECT d.*, u.display_name FROM " . CWM_DEPOSITS_TABLE . " d LEFT JOIN {$wpdb->users} u ON d.user_id = u.ID ORDER BY d.created_date DESC");
        ?>
        <div class="wrap">
            <h1>Deposit Management</h1>
            <div class="tablenav">
                <div class="alignleft actions">
                    <select id="bulk-action-selector">
                        <option value="">Bulk Actions</option>
                        <option value="approve">Approve</option>
                        <option value="decline">Decline</option>
                    </select>
                    <button class="button action" id="doaction">Apply</button>
                </div>
            </div>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox" /></th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Coin</th>
                        <th>Network</th>
                        <th>Amount</th>
                        <th>Address</th>
                        <th>TXID</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deposits as $deposit): ?>
                    <tr>
                        <th class="check-column"><input type="checkbox" name="deposit[]" value="<?php echo $deposit->id; ?>" /></th>
                        <td><?php echo esc_html($deposit->display_name); ?></td>
                        <td><?php echo esc_html($deposit->created_date); ?></td>
                        <td><?php echo esc_html($deposit->coin_name); ?></td>
                        <td><?php echo esc_html($deposit->network); ?></td>
                        <td><?php echo esc_html($deposit->amount); ?></td>
                        <td><code><?php echo esc_html(substr($deposit->deposit_address, 0, 20) . '...'); ?></code></td>
                        <td>
                            <input type="text" class="deposit-txid-input" data-id="<?php echo $deposit->id; ?>" 
                                   value="<?php echo esc_attr($deposit->txid); ?>" placeholder="Enter TXID" />
                        </td>
                        <td>
                            <select class="deposit-status-select" data-id="<?php echo $deposit->id; ?>">
                                <option value="pending" <?php selected($deposit->status, 'pending'); ?>>Pending</option>
                                <option value="approved" <?php selected($deposit->status, 'approved'); ?>>Approved</option>
                                <option value="declined" <?php selected($deposit->status, 'declined'); ?>>Declined</option>
                            </select>
                        </td>
                        <td>
                            <button class="button button-primary save-deposit-btn" data-id="<?php echo $deposit->id; ?>">Save</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.save-deposit-btn').click(function() {
                var id = $(this).data('id');
                var txid = $('.deposit-txid-input[data-id="' + id + '"]').val();
                var status = $('.deposit-status-select[data-id="' + id + '"]').val();
                
                $.post(ajaxurl, {
                    action: 'cwm_update_deposit_status',
                    id: id,
                    txid: txid,
                    status: status,
                    _ajax_nonce: '<?php echo wp_create_nonce("cwm_deposit_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Deposit updated successfully!');
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // NEW: Withdrawal Management Page
    public function render_withdrawal_management_page() {
        global $wpdb;
        $withdrawals = $wpdb->get_results("SELECT w.*, u.display_name FROM " . CWM_WITHDRAWALS_TABLE . " w LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID ORDER BY w.created_date DESC");
        ?>
        <div class="wrap">
            <h1>Withdrawal Management</h1>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Coin</th>
                        <th>Amount</th>
                        <th>Details</th>
                        <th>TXID</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                    <tr>
                        <td><?php echo esc_html($withdrawal->display_name); ?></td>
                        <td><?php echo esc_html($withdrawal->created_date); ?></td>
                        <td><span class="dashicons dashicons-<?php echo $withdrawal->withdraw_type == 'bank' ? 'bank' : ($withdrawal->withdraw_type == 'onchain' ? 'admin-links' : 'chart-line'); ?>"></span> <?php echo esc_html(ucfirst($withdrawal->withdraw_type)); ?></td>
                        <td><?php echo esc_html($withdrawal->coin_name); ?></td>
                        <td><?php echo esc_html($withdrawal->amount); ?></td>
                        <td>
                            <?php if ($withdrawal->withdraw_type == 'bank' && $withdrawal->bank_details): ?>
                                <?php $bank = json_decode($withdrawal->bank_details, true); ?>
                                <strong><?php echo esc_html($bank['bank_name']); ?></strong><br>
                                <?php echo esc_html($bank['account_number']); ?>
                            <?php elseif ($withdrawal->withdraw_type == 'onchain' && $withdrawal->withdraw_address): ?>
                                <code><?php echo esc_html(substr($withdrawal->withdraw_address, 0, 20) . '...'); ?></code>
                            <?php elseif ($withdrawal->withdraw_type == 'cex' && $withdrawal->exchange_details): ?>
                                <?php $exchange = json_decode($withdrawal->exchange_details, true); ?>
                                <strong><?php echo esc_html($exchange['exchange']); ?></strong><br>
                                <?php echo esc_html($exchange['email_uid']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" class="withdrawal-txid-input" data-id="<?php echo $withdrawal->id; ?>" 
                                   value="<?php echo esc_attr($withdrawal->txid); ?>" placeholder="Enter TXID" />
                        </td>
                        <td>
                            <select class="withdrawal-status-select" data-id="<?php echo $withdrawal->id; ?>">
                                <option value="pending" <?php selected($withdrawal->status, 'pending'); ?>>Pending</option>
                                <option value="approved" <?php selected($withdrawal->status, 'approved'); ?>>Approved</option>
                                <option value="declined" <?php selected($withdrawal->status, 'declined'); ?>>Declined</option>
                            </select>
                        </td>
                        <td>
                            <button class="button button-primary save-withdrawal-btn" data-id="<?php echo $withdrawal->id; ?>">Save</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.save-withdrawal-btn').click(function() {
                var id = $(this).data('id');
                var txid = $('.withdrawal-txid-input[data-id="' + id + '"]').val();
                var status = $('.withdrawal-status-select[data-id="' + id + '"]').val();
                
                $.post(ajaxurl, {
                    action: 'cwm_update_withdrawal_status',
                    id: id,
                    txid: txid,
                    status: status,
                    _ajax_nonce: '<?php echo wp_create_nonce("cwm_withdrawal_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Withdrawal updated successfully!');
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // NEW: Handle Deposit Request
    public function handle_deposit_request() {
        check_ajax_referer('cwm_deposit_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Please log in to make a deposit.');
        }

        $coin = sanitize_text_field($_POST['coin']);
        $network = sanitize_text_field($_POST['network']);
        $amount = floatval($_POST['amount']);

        if (empty($coin) || empty($network) || $amount <= 0) {
            wp_send_json_error('Please fill all required fields.');
        }

        // Generate deposit address
        $deposit_address = $this->generate_deposit_address($coin, $network);

        global $wpdb;
        $result = $wpdb->insert(
            CWM_DEPOSITS_TABLE,
            array(
                'user_id' => $user_id,
                'coin_name' => $coin,
                'network' => $network,
                'amount' => $amount,
                'deposit_address' => $deposit_address,
                'status' => 'pending',
                'created_date' => current_time('mysql')
            )
        );

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Deposit request submitted successfully!',
                'address' => $deposit_address,
                'qr_code' => $this->generate_qr_code($deposit_address)
            ));
        } else {
            wp_send_json_error('Failed to submit deposit request.');
        }
    }

    // NEW: Handle Withdrawal Request
    public function handle_withdrawal_request() {
        check_ajax_referer('cwm_withdrawal_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Please log in to make a withdrawal.');
        }

        // Show activation modal instead of processing
        wp_send_json_error('Please Activate Event Package');
    }

    // NEW: Get Networks for Coin
    public function ajax_get_networks() {
        $coin = sanitize_text_field($_POST['coin']);
        $networks = $this->get_network_options($coin);
        wp_send_json_success($networks);
    }

    // NEW: Update Deposit Status (Admin)
    public function ajax_update_deposit_status() {
        check_ajax_referer('cwm_deposit_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;
        $id = intval($_POST['id']);
        $txid = sanitize_text_field($_POST['txid']);
        $status = sanitize_text_field($_POST['status']);

        $result = $wpdb->update(
            CWM_DEPOSITS_TABLE,
            array(
                'txid' => $txid,
                'status' => $status,
                'updated_date' => current_time('mysql')
            ),
            array('id' => $id)
        );

        if ($result !== false) {
            // If approved, credit user balance
            if ($status === 'approved') {
                $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . CWM_DEPOSITS_TABLE . " WHERE id = %d", $id));
                if ($deposit) {
                    $current_balance = floatval(get_user_meta($deposit->user_id, 'wcw_balance_' . $deposit->coin_name, true));
                    update_user_meta($deposit->user_id, 'wcw_balance_' . $deposit->coin_name, $current_balance + $deposit->amount);
                }
            }
            wp_send_json_success('Deposit updated successfully');
        } else {
            wp_send_json_error('Failed to update deposit');
        }
    }

    // NEW: Update Withdrawal Status (Admin)
    public function ajax_update_withdrawal_status() {
        check_ajax_referer('cwm_withdrawal_nonce', '_ajax_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;
        $id = intval($_POST['id']);
        $txid = sanitize_text_field($_POST['txid']);
        $status = sanitize_text_field($_POST['status']);

        $result = $wpdb->update(
            CWM_WITHDRAWALS_TABLE,
            array(
                'txid' => $txid,
                'status' => $status,
                'updated_date' => current_time('mysql')
            ),
            array('id' => $id)
        );

        if ($result !== false) {
            wp_send_json_success('Withdrawal updated successfully');
        } else {
            wp_send_json_error('Failed to update withdrawal');
        }
    }

    // NEW: Generate Deposit Address
    private function generate_deposit_address($coin, $network) {
        $addresses = array(
            'BTC' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'BNB' => '0x742d35Cc3bF24C4e6c8E5a8Ae8d8E4B4c45f2F0e',
            'LTC' => 'ltc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
            'SOL' => '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM'
        );
        return $addresses[$coin] ?? 'default_address';
    }

    // NEW: Generate QR Code
    private function generate_qr_code($address) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($address);
    }

    // NEW: Get Network Options
    private function get_network_options($coin) {
        $networks = array(
            'BTC' => array('bitcoin' => 'Bitcoin Network'),
            'BNB' => array('bep20' => 'BEP20 (BSC)'),
            'LTC' => array('litecoin' => 'Litecoin Network'),
            'SOL' => array('solana' => 'Solana Network')
        );
        return $networks[$coin] ?? array();
    }

    // Rest of your original functions remain exactly the same...
    public function render_admin_page() {
        $available_coins = ['BTC', 'ETH', 'BNB', 'SOL', 'LTC'];
        ?>
        <div class="wrap">
            <h1>Crypto Wallet Admin</h1>
            <?php if (isset($_GET['status'])): ?>
            <div id="message" class="<?php echo $_GET['status'] == 'success' ? 'updated' : 'error'; ?> notice is-dismissible"><p><?php echo esc_html(urldecode($_GET['message'])); ?></p></div>
            <?php endif; ?>
            <hr>
            <h2>Generate Mining Rewards for User</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cwm_generate_rewards">
                <?php wp_nonce_field('cwm_generate_rewards_nonce'); ?>
                <table class="form-table">
                    <tr><th scope="row"><label for="user_id_generate">Select User</label></th><td><?php wp_dropdown_users(['name' => 'user_id', 'id' => 'user_id_generate', 'show_option_none' => '— Select a User —']); ?></td></tr>
                    <tr><th scope="row"><label for="start_date">Start Date</label></th><td><input name="start_date" type="date" id="start_date" value="2020-01-01" class="regular-text" required></td></tr>
                    <tr><th scope="row"><label for="end_date">End Date</label></th><td><input name="end_date" type="date" id="end_date" value="<?php echo date('Y-m-d'); ?>" class="regular-text" required></td></tr>
                    <tr><th scope="row">Coins & Amounts</th><td><?php foreach ($available_coins as $coin): ?><div style="margin-bottom:10px;"><label><input type="checkbox" name="coins[]" value="<?php echo esc_attr($coin); ?>"> <span style="display:inline-block;width:50px;"><?php echo esc_html($coin); ?></span></label><input name="coin_amounts[<?php echo esc_attr($coin); ?>]" type="number" step="any" placeholder="Total <?php echo esc_attr($coin); ?> amount to add" class="regular-text"></div><?php endforeach; ?></td></tr>
                </table>
                <?php submit_button('Generate Rewards'); ?>
            </form>
            <hr>
            <h2>Manual Balance Adjustment</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cwm_manual_balance">
                <?php wp_nonce_field('cwm_manual_balance_nonce'); ?>
                <table class="form-table">
                    <tr><th scope="row"><label for="user_id_manual">Select User</label></th><td><?php wp_dropdown_users(['name' => 'user_identifier', 'id' => 'user_id_manual', 'show_option_none' => '— Select a User —']); ?></td></tr>
                    <tr><th scope="row"><label for="manual_coin">Coin</label></th><td><select name="coin" id="manual_coin"><?php foreach ($available_coins as $coin): ?><option value="<?php echo esc_attr($coin); ?>"><?php echo esc_html($coin); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th scope="row"><label for="manual_balance">New Total Balance</label></th><td><input type="number" step="0.00000001" name="balance" id="manual_balance" placeholder="Set new total balance" class="regular-text"></td></tr>
                </table>
                <?php submit_button('Set Balance'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_generate_rewards() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cwm_generate_rewards_nonce')) wp_die('Nonce verification failed.');
        if (!current_user_can('manage_options')) wp_die('You do not have sufficient permissions.');
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $start_date_str = sanitize_text_field($_POST['start_date']);
        $end_date_str = sanitize_text_field($_POST['end_date']);
        $selected_coins = isset($_POST['coins']) ? array_map('sanitize_text_field', (array)$_POST['coins']) : [];
        $coin_amounts = isset($_POST['coin_amounts']) ? (array)$_POST['coin_amounts'] : [];
        if (empty($user_id) || empty($start_date_str) || empty($end_date_str) || empty($selected_coins)) { wp_redirect(admin_url('admin.php?page=cwm-wallet-admin&status=error&message=' . urlencode('Missing required fields.'))); exit; }
        $start_date = new DateTime($start_date_str);
        $end_date = new DateTime($end_date_str);
        if ($start_date > $end_date) { wp_redirect(admin_url('admin.php?page=cwm-wallet-admin&status=error&message=' . urlencode('Start date cannot be after end date.'))); exit; }
        $period_end_date = (clone $end_date)->modify('+1 day');
        $interval = new DateInterval('P1D');
        $total_days = iterator_count(new DatePeriod($start_date, $interval, $period_end_date));
        if ($total_days <= 0) { wp_redirect(admin_url('admin.php?page=cwm-wallet-admin&status=error&message=' . urlencode('Invalid date range.'))); exit; }
        global $wpdb;
        foreach ($selected_coins as $coin) {
            $amount_to_add = isset($coin_amounts[$coin]) ? floatval($coin_amounts[$coin]) : 0;
            if ($amount_to_add <= 0) continue;
            $current_balance = floatval(get_user_meta($user_id, 'wcw_balance_' . $coin, true));
            update_user_meta($user_id, 'wcw_balance_' . $coin, $current_balance + $amount_to_add);
            $daily_amount = $amount_to_add / $total_days;
            foreach (new DatePeriod($start_date, $interval, $period_end_date) as $date) {
                $wpdb->insert(CWM_REWARDS_TABLE, ['user_id' => $user_id, 'reward_date' => $date->format('Y-m-d'), 'transaction_id' => 'TX' . mt_rand(10000000, 99999999), 'package_name' => 'Early User Special', 'coin_name' => $coin, 'coin_amount' => $daily_amount, 'details' => 'Mining reward', 'status' => 'Withdrawable']);
            }
        }
        wp_redirect(admin_url('admin.php?page=cwm-wallet-admin&status=success&message=' . urlencode('Rewards generated.'))); exit;
    }

    public function handle_manual_balance() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cwm_manual_balance_nonce')) wp_die('Nonce failed.');
        if (!current_user_can('manage_options')) wp_die('No permissions.');
        $user_id = intval($_POST['user_identifier']);
        $coin = strtoupper(sanitize_text_field($_POST['coin']));
        $balance = floatval($_POST['balance']);
        if ($user_id && !empty($coin)) {
            update_user_meta($user_id, 'wcw_balance_' . $coin, $balance);
            wp_redirect(admin_url('admin.php?page=cwm-wallet-admin&status=success&message=' . urlencode('Balance updated.')));
        } else {
            wp_redirect(admin_url('admin.php?page=cwm-wallet-admin&status=error&message=' . urlencode('User or coin not specified.')));
        }
        exit;
    }

    public function render_purchase_history_page() {
        if (isset($_GET['action']) && in_array($_GET['action'], ['add', 'edit'])) { $this->render_purchase_form_page(); return; }
        $purchase_list_table = new CWM_Purchase_History_List_Table();
        $purchase_list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Wallet Purchase History</h1>
            <a href="<?php echo admin_url('admin.php?page=cwm-purchase-history&action=add'); ?>" class="page-title-action">Add New</a><hr class="wp-header-end">
            <?php if (isset($_GET['message'])): ?><div id="message" class="updated notice is-dismissible"><p><?php echo esc_html(urldecode($_GET['message'])); ?></p></div><?php endif; ?>
            <?php $purchase_list_table->views(); ?>
            <form method="get"><input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"><?php $purchase_list_table->search_box('Search by Item Name', 'item_search'); $purchase_list_table->display(); ?></form>
        </div>
        <?php
        $this->admin_purchase_history_js();
    }

    public function render_purchase_form_page() {
        global $wpdb;
        $item = null;
        $page_title = 'Add New Purchase Record';
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . CWM_PURCHASES_TABLE . " WHERE id = %d", $id));
            if ($item) $page_title = 'Edit Purchase Record';
        }
        if (!$item) { $item = (object) [ 'id' => 0, 'user_id' => 0, 'transaction_date' => current_time('mysql'), 'item_name' => '', 'type' => 'product', 'price' => '', 'paid_with_coin' => 'BTC', 'paid_coin_amount' => '', 'status' => 'pending' ]; }
        $available_coins = ['BTC', 'ETH', 'BNB', 'SOL', 'LTC']; $types = ['product', 'subscription']; $statuses = ['pending', 'delivered', 'active', 'expired', 'refunded'];
        ?>
        <div class="wrap"><h1><?php echo esc_html($page_title); ?></h1><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="cwm_save_purchase"><input type="hidden" name="purchase_id" value="<?php echo esc_attr($item->id); ?>"><?php wp_nonce_field('cwm_save_purchase_nonce'); ?><table class="form-table"><tbody>
        <tr><th scope="row"><label for="user_id">User</label></th><td><?php wp_dropdown_users(['name' => 'user_id', 'selected' => $item->user_id, 'show_option_none' => '— Select —', 'id' => 'user_id', 'class' => 'regular-text']); ?></td></tr>
        <tr><th scope="row"><label for="transaction_date">Date</label></th><td><input type="datetime-local" id="transaction_date" name="transaction_date" value="<?php echo esc_attr(date('Y-m-d\TH:i', strtotime($item->transaction_date))); ?>" class="regular-text" required></td></tr>
        <tr><th scope="row"><label for="item_name">Item Name</label></th><td><input type="text" id="item_name" name="item_name" value="<?php echo esc_attr($item->item_name); ?>" class="regular-text" required></td></tr>
        <tr><th scope="row"><label for="type">Type</label></th><td><select id="type" name="type"><?php foreach ($types as $type): ?><option value="<?php echo esc_attr($type); ?>" <?php selected($item->type, $type); ?>><?php echo esc_html(ucfirst($type)); ?></option><?php endforeach; ?></select></td></tr>
        <tr><th scope="row"><label for="price">Price (USD)</label></th><td><input type="number" step="0.01" id="price" name="price" value="<?php echo esc_attr($item->price); ?>" class="regular-text" required></td></tr>
        <tr><th scope="row"><label for="paid_with_coin">Paid With Coin</label></th><td><select id="paid_with_coin" name="paid_with_coin"><?php foreach ($available_coins as $coin): ?><option value="<?php echo esc_attr($coin); ?>" <?php selected($item->paid_with_coin, $coin); ?>><?php echo esc_html($coin); ?></option><?php endforeach; ?></select></td></tr>
        <tr><th scope="row"><label for="paid_coin_amount">Paid Coin Amount</label></th><td><input type="number" step="0.00000001" id="paid_coin_amount" name="paid_coin_amount" value="<?php echo esc_attr($item->paid_coin_amount); ?>" class="regular-text" required></td></tr>
        <tr><th scope="row"><label for="status">Status</label></th><td><select id="status" name="status"><?php foreach ($statuses as $status): ?><option value="<?php echo esc_attr($status); ?>" <?php selected($item->status, $status); ?>><?php echo esc_html(ucfirst($status)); ?></option><?php endforeach; ?></select></td></tr>
        </tbody></table><?php submit_button( $item->id ? 'Update Purchase' : 'Add Purchase' ); ?></form></div>
        <?php
    }

    public function handle_save_purchase() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cwm_save_purchase_nonce')) wp_die('Nonce failed.');
        if (!current_user_can('manage_options')) wp_die('No permissions.');
        global $wpdb;
        $id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
        $data = [ 'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : 0, 'transaction_date' => isset($_POST['transaction_date']) ? sanitize_text_field($_POST['transaction_date']) : current_time('mysql'), 'item_name' => isset($_POST['item_name']) ? sanitize_text_field($_POST['item_name']) : '', 'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'product', 'price' => isset($_POST['price']) ? floatval($_POST['price']) : 0, 'paid_with_coin' => isset($_POST['paid_with_coin']) ? sanitize_text_field($_POST['paid_with_coin']) : 'BTC', 'paid_coin_amount' => isset($_POST['paid_coin_amount']) ? floatval($_POST['paid_coin_amount']) : 0, 'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending', 'item_id' => 0, 'currency' => 'USD', ];
        if (empty($data['user_id']) || empty($data['item_name'])) { wp_redirect(admin_url('admin.php?page=cwm-purchase-history&message=' . urlencode('Error: User and Item Name are required.'))); exit; }
        if ($id > 0) { $wpdb->update(CWM_PURCHASES_TABLE, $data, ['id' => $id]); $message = 'Purchase record updated successfully.'; } else { $wpdb->insert(CWM_PURCHASES_TABLE, $data); $message = 'Purchase record added successfully.'; }
        wp_redirect(admin_url('admin.php?page=cwm-purchase-history&message=' . urlencode($message)));
        exit;
    }

    public function handle_wallet_purchase() {
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'cwm_purchase_with_wallet' || ! isset( $_POST['cwm_purchase_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['cwm_purchase_nonce'], 'cwm_purchase_action' ) ) wp_die( 'Security check failed!' );
        global $wpdb;
        $user_id = get_current_user_id(); if ( ! $user_id ) return;
        $item_id = isset( $_POST['item_id'] ) ? intval( $_POST['item_id'] ) : 0; $price_usd = isset( $_POST['item_price'] ) ? floatval( $_POST['item_price'] ) : 0.0; $item_name = isset( $_POST['item_name'] ) ? sanitize_text_field( $_POST['item_name'] ) : 'N/A'; $type = isset( $_POST['item_type'] ) && in_array($_POST['item_type'], ['product', 'subscription']) ? $_POST['item_type'] : 'product'; $coin_to_pay_with = isset( $_POST['pay_with_coin'] ) ? strtoupper(sanitize_text_field($_POST['pay_with_coin'])) : ''; $redirect = isset( $_POST['redirect_url'] ) ? esc_url_raw( $_POST['redirect_url'] ) : home_url();
        if ( ! $item_id || ! $price_usd || ! $coin_to_pay_with ) { wp_redirect( add_query_arg( 'purchase_error', 'invalid_data', $redirect ) ); exit; }
        $live_prices = $this->get_live_prices(); if ( ! isset( $live_prices[$coin_to_pay_with] ) || $live_prices[$coin_to_pay_with]['usd'] <= 0 ) { wp_redirect( add_query_arg( 'purchase_error', 'price_unavailable', $redirect ) ); exit; }
        $coin_price_usd = $live_prices[$coin_to_pay_with]['usd'];
        $required_coin_amount = $price_usd / $coin_price_usd; $user_coin_balance = (float) get_user_meta( $user_id, 'wcw_balance_' . $coin_to_pay_with, true );
        if ( $user_coin_balance < $required_coin_amount ) { wp_redirect( add_query_arg( 'purchase_error', 'insufficient_funds', $redirect ) ); exit; }
        $new_balance = $user_coin_balance - $required_coin_amount; update_user_meta( $user_id, 'wcw_balance_' . $coin_to_pay_with, $new_balance );
        $wpdb->insert( CWM_PURCHASES_TABLE, [ 'user_id' => $user_id, 'item_id' => $item_id, 'price' => $price_usd, 'currency' => 'USD', 'paid_with_coin' => $coin_to_pay_with, 'paid_coin_amount' => $required_coin_amount, 'type' => $type, 'item_name' => $item_name, 'status' => ( $type === 'product' ) ? 'pending' : 'active', 'transaction_date' => current_time( 'mysql' ), ], [ '%d', '%d', '%f', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ] );
        wp_redirect( add_query_arg( 'purchase_success', 'true', $redirect ) );
        exit;
    }

    public function ajax_update_purchase_status() { check_ajax_referer('cwm_update_purchase_status_nonce', '_ajax_nonce'); if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Permission denied.']); global $wpdb; $id = isset($_POST['id']) ? intval($_POST['id']) : 0; $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : ''; if (!$id || !$status) wp_send_json_error(['message' => 'Invalid data.']); if (false === $wpdb->update(CWM_PURCHASES_TABLE, ['status' => $status], ['id' => $id])) wp_send_json_error(['message' => 'DB error.']); wp_send_json_success(); }
    public function ajax_update_purchase_date() { check_ajax_referer('cwm_update_purchase_date_nonce', '_ajax_nonce'); if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Permission denied.']); global $wpdb; $id = isset($_POST['id']) ? intval($_POST['id']) : 0; $date_string = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : ''; if (!$id || !$date_string) wp_send_json_error(['message' => 'Invalid data.']); if (false === $wpdb->update(CWM_PURCHASES_TABLE, ['transaction_date' => date('Y-m-d H:i:s', strtotime($date_string))], ['id' => $id])) wp_send_json_error(['message' => 'DB error.']); wp_send_json_success(); }
    public function ajax_manual_refund() { check_ajax_referer('cwm_refund_nonce', '_ajax_nonce'); if ( ! current_user_can('manage_options') ) wp_send_json_error(['message' => 'Permission denied.']); global $wpdb; $id = isset($_POST['id']) ? intval($_POST['id']) : 0; if (!$id) wp_send_json_error(['message' => 'Invalid ID.']); $transaction = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . CWM_PURCHASES_TABLE . " WHERE id = %d", $id)); if (!$transaction) wp_send_json_error(['message' => 'Not found.']); if ($transaction->status === 'refunded') wp_send_json_error(['message' => 'Already refunded.']); $coin_to_refund = $transaction->paid_with_coin; $amount_to_refund = (float) $transaction->paid_coin_amount; $current_balance = (float) get_user_meta($transaction->user_id, 'wcw_balance_' . $coin_to_refund, true); update_user_meta($transaction->user_id, 'wcw_balance_' . $coin_to_refund, $current_balance + $amount_to_refund); $wpdb->update(CWM_PURCHASES_TABLE, ['status' => 'refunded'], ['id' => $id]); wp_send_json_success(['message' => 'Refunded.']); }
    private function admin_purchase_history_js() { ?><script>jQuery(document).ready(function($) { $(document).on('change', '.cwm-status-selector', function() { $.post(ajaxurl, { action: 'cwm_update_purchase_status', id: $(this).data('id'), status: $(this).val(), _ajax_nonce: $(this).data('nonce') }); }); $(document).on('change', '.cwm-date-selector', function() { $.post(ajaxurl, { action: 'cwm_update_purchase_date', id: $(this).data('id'), date: $(this).val(), _ajax_nonce: $(this).data('nonce') }); }); $(document).on('click', '.cwm-refund-button', function(e) { e.preventDefault(); if (!confirm('Refund?')) return; const button = $(this); $.post(ajaxurl, { action: 'cwm_manual_refund', id: button.data('id'), _ajax_nonce: button.data('nonce') }, function(r) { if (r.success) { button.closest('tr').find('.cwm-status-selector').val('refunded').prop('disabled', true); button.replaceWith('<span>Refunded</span>'); } else { alert('Error: ' + r.data.message); } }); }); });</script><?php }
    
    public function enqueue_assets() { 
        if (is_admin()) return; 
        wp_enqueue_script('cwm-chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true); 
        wp_enqueue_script('cwm-chart-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js', array('cwm-chart-js'), null, true);
        wp_localize_script('cwm-chart-js', 'cwm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'deposit_nonce' => wp_create_nonce('cwm_deposit_nonce'),
            'withdrawal_nonce' => wp_create_nonce('cwm_withdrawal_nonce')
        ));
    }
    public function get_user_balances($user_id) { $coins = ['BTC', 'SOL', 'LTC', 'BNB', 'ETH']; $balances = []; foreach ($coins as $coin) { $balances[$coin] = floatval(get_user_meta($user_id, 'wcw_balance_' . $coin, true)); } return $balances; }
    
    public function get_live_prices() {
        $transient_name = 'cwm_live_prices_v4_markets';
        if (false === ($prices = get_transient($transient_name))) {
            $coin_ids = 'bitcoin,solana,litecoin,binancecoin,ethereum';
            $api_url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids={$coin_ids}&order=market_cap_desc&per_page=10&page=1&sparkline=true&price_change_percentage=1h,7d";
            $response = wp_remote_get($api_url);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return [];
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $prices = [];
            if (is_array($data)) {
                foreach($data as $coin_data) {
                    $symbol = strtoupper($coin_data['symbol']);
                    $prices[$symbol] = [ 'id' => $coin_data['id'], 'usd' => $coin_data['current_price'] ?? 0, 'change_1h' => $coin_data['price_change_percentage_1h_in_currency'] ?? 0, 'change_24h' => $coin_data['price_change_percentage_24h'] ?? 0, 'change_7d' => $coin_data['price_change_percentage_7d_in_currency'] ?? 0, 'market_cap' => $coin_data['market_cap'] ?? 0, 'volume_24h' => $coin_data['total_volume'] ?? 0, 'sparkline' => $coin_data['sparkline_in_7d']['price'] ?? [], ];
                }
            }
            set_transient($transient_name, $prices, 5 * MINUTE_IN_SECONDS);
        }
        return $prices;
    }
    
    public function ajax_get_chart_data() {
        check_ajax_referer('cwm_chart_nonce', 'nonce');
        $coin_id = sanitize_text_field($_POST['coin_id']);
        $days = sanitize_text_field($_POST['days']);
        if (empty($coin_id) || empty($days)) {
            wp_send_json_error('Missing parameters.');
        }
        $api_url = "https://api.coingecko.com/api/v3/coins/{$coin_id}/market_chart?vs_currency=usd&days={$days}";
        $response = wp_remote_get($api_url);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            wp_send_json_error('Failed to fetch data from API.');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success($data['prices']);
    }

    public function get_coin_icon_svg($coin) { $icons = [ 'BTC' => '<svg class="cwm-coin-icon" viewBox="0 0 24 24" fill="#F7931A" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1.55 14.86c-.4.18-.88.29-1.45.29-.69 0-1.37-.15-1.95-.44l.6-2.42c.42.21.9.33 1.35.33.58 0 .93-.23.93-.61 0-.32-.23-.5-.88-.73l-1.4-.49c-1.2-.42-2-.98-2-2.31 0-1.09.73-1.98 1.88-2.32l.6 2.41c-.42-.17-.8-.26-1.28-.26-.53 0-.85.22-.85.56 0 .3.24.47.8.69l1.4.49c1.35.47 2.15 1.02 2.15 2.38 0 1.25-.81 2.1-2.05 2.51zm5.1-1.04c.3-.26.5-.6.5-1.08 0-.6-.35-1.11-.93-1.39l.52-2.09h-2.2l-.5 2.01h-.1v-.01h-.08l-.5-2h-2.2l-.88 3.52c-.17-.03-.33-.06-.5-.08l.18-.73h-2.19l-.36 1.43c.21.05.43.09.64.13l-.18.72c-.24-.05-.48-.1-.72-.15l.55 2.18h.83l.25-1.01h.1c.32.74 1.15 1.29 2.11 1.29.69 0 1.28-.25 1.7-.68l.46 1.83h2.18l-1.01-4.07zM14.65 14.4c-.4.36-.93.58-1.53.58-.65 0-1.13-.28-1.35-.79l.58-2.31c.15.1.33.18.53.18.45 0 .8-.26.93-.8l.4-1.59h.44l-.56 2.24c.48.17.8.5.8.99 0 .43-.2.78-.58 1.06z"/></svg>', 'BNB' => '<svg class="cwm-coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" fill="#F3BA2F"/><path d="M6.343 12l2.829-2.828L12 6.343l2.828 2.829L17.657 12l-2.829 2.828L12 17.657l-2.828-2.829L6.343 12zM12 15.556l1.778-1.778L15.556 12l-1.778-1.778L12 8.444 10.222 10.222 8.444 12l1.778 1.778L12 15.556z" fill="#fff"/><path d="M12 9.272l.828-.828L12 7.616l-.828.828.828.828zM9.172 12l.828-.828-.828-.829-.828.829.828.828zM14.828 12l-.828.828.828.828.828-.828-.828-.828zM12 14.728l-.828.828L12 16.384l.828-.828-.828-.828z" fill="#fff" opacity="0.3"/></svg>', 'LTC' => '<svg class="cwm-coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="#345D9D"/><path d="M8.28 16.52h1.54l.4-1.29h2.93l.21 1.29h1.53l2.21-8.54h-1.5l-.94 3.52L13.43 7.48h-1.52l-1.51 7.23h-2.1l2.09-7.27z" fill="#fff"/></svg>', 'SOL' => '<svg class="cwm-coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="solana-gradient-v2" x1="4" y1="18.232" x2="20" y2="5.768" gradientUnits="userSpaceOnUse"><stop stop-color="#9945FF"/><stop offset="1" stop-color="#14F195"/></linearGradient></defs><path d="M4 18.232h16v-2.4H4v2.4zM4 8.168h16V5.768H4v2.4zM4 13.2h16v-2.4H4v2.4z" fill="url(#solana-gradient-v2)"/></svg>', 'ETH' => '<svg class="cwm-coin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 22.25C6.34 22.25 1.75 17.66 1.75 12S6.34 1.75 12 1.75 22.25 6.34 22.25 12 17.66 22.25 12 22.25z" fill="#627EEA"/><path d="M12.025 2.93v8.53l5.05 2.96-5.05-11.49z" fill="#fff" opacity=".602"/><path d="M12.025 2.93L6.975 11.46l5.05-2.96v-8.53z" fill="#fff"/><path d="M12.025 21.071v-6.601l5.05 1.42-5.05 5.181z" fill="#fff" opacity=".602"/><path d="M12.025 21.071l-5.05-5.181 5.05-1.42v6.601z" fill="#fff"/><path d="M17.075 11.51L12.025 14.47l-5.05-2.96 5.05-1.87 5.05 1.87z" fill="#fff" opacity=".2"/></svg>' ]; return isset($icons[$coin]) ? $icons[$coin] : ''; }

    public function render_dashboard_shortcode() {
        ob_start();
        $user_id = get_current_user_id();
        if (!$user_id) { echo '<p>Please log in to see your wallet.</p>'; return ob_get_clean(); }
        
        $viewing_coin = isset($_GET['view_coin']) ? strtoupper(sanitize_key($_GET['view_coin'])) : false;
        
        $balances = $this->get_user_balances($user_id);
        $market_data = $this->get_live_prices();
        $all_coin_data = [];
        foreach ($balances as $coin => $amount) {
            $price_data = $market_data[$coin] ?? [];
            $coin_value = $amount * ($price_data['usd'] ?? 0);
            $change_24h = $price_data['change_24h'] ?? 0;
            $coin_pnl_usd = 0;
            if($change_24h != 0 && ($price_data['usd'] ?? 0) != 0) { $price_yesterday = $price_data['usd'] / (1 + ($change_24h / 100)); $coin_pnl_usd = ($coin_value - ($amount * $price_yesterday)); }
            $all_coin_data[$coin] = array_merge($price_data, [ 'amount' => $amount, 'value_usd' => $coin_value, 'pnl_usd' => $coin_pnl_usd ]);
        }
        
        $this->render_dashboard_styles();
        
        echo '<div class="cwm-dashboard-container">';
        if ($viewing_coin && isset($all_coin_data[$viewing_coin])) {
            $this->render_coin_detail_view($user_id, $viewing_coin, $all_coin_data[$viewing_coin]);
        } else {
            $this->render_portfolio_view($user_id, $all_coin_data, $balances);
        }
        echo '</div>';
        $this->render_dashboard_js($all_coin_data, $viewing_coin);
        return ob_get_clean();
    }

    public function render_portfolio_view($user_id, $all_coin_data, $balances) {
        $total_value = 0; $total_pnl_usd = 0;
        foreach($all_coin_data as $data) { $total_value += $data['value_usd'] ?? 0; $total_pnl_usd += $data['pnl_usd'] ?? 0; }
        uasort($all_coin_data, function($a, $b) { return ($b['value_usd'] ?? 0) <=> ($a['value_usd'] ?? 0); });
        $total_pnl_percent = ($total_value > 0 && ($total_value - $total_pnl_usd) != 0) ? ($total_pnl_usd / ($total_value - $total_pnl_usd)) * 100 : 0;
        $pnl_class = $total_pnl_usd >= 0 ? 'positive' : 'negative';
        $top_performer = ['coin' => 'N/A', 'pnl' => -INF];
        foreach ($all_coin_data as $coin => $data) { if (($data['pnl_usd'] ?? 0) > $top_performer['pnl']) { $top_performer = ['pnl' => $data['pnl_usd'], 'coin' => $coin]; } }
        ?>
            <?php if(isset($_GET['purchase_error'])) { $error_msg = 'An unknown error occurred.'; if($_GET['purchase_error'] === 'insufficient_funds') $error_msg = 'You do not have enough funds.'; if($_GET['purchase_error'] === 'price_unavailable') $error_msg = 'Could not retrieve price.'; if($_GET['purchase_error'] === 'invalid_data') $error_msg = 'Invalid purchase data.'; echo '<div class="cwm-notice cwm-error"><p>' . esc_html($error_msg) . '</p></div>'; } ?>
            <?php if(isset($_GET['purchase_success'])) { echo '<div class="cwm-notice cwm-success"><p>Purchase successful!</p></div>'; } ?>
            <div class="cwm-summary-bar">
                <div class="cwm-summary-card"><span class="cwm-card-label">Current Balance</span><span class="cwm-card-value">$<?php echo number_format($total_value, 2); ?></span></div>
                <div class="cwm-summary-card"><span class="cwm-card-label">24h Portfolio Change</span><span class="cwm-card-value <?php echo $pnl_class; ?>"><?php echo ($total_pnl_usd >= 0 ? '+' : '-') . '$' . number_format(abs($total_pnl_usd), 2); ?><span class="cwm-card-percent">(<?php echo number_format($total_pnl_percent, 2); ?>%)</span></span></div>
                <div class="cwm-summary-card"><span class="cwm-card-label">Total Profit / Loss (24h)</span><span class="cwm-card-value positive"><?php echo ($total_pnl_usd >= 0 ? '+' : '-') . '$' . number_format(abs($total_pnl_usd), 2); ?></span></div>
                <div class="cwm-summary-card"><span class="cwm-card-label">Top Performer (24h)</span><span class="cwm-card-value"><?php if ($top_performer['coin'] !== 'N/A' && isset($all_coin_data[$top_performer['coin']])): ?><?php echo $this->get_coin_icon_svg($top_performer['coin']); ?><?php echo esc_html($top_performer['coin']); ?><span class="positive">+<?php echo number_format(abs($all_coin_data[$top_performer['coin']]['pnl_usd']), 2); ?></span><?php else: ?>N/A<?php endif; ?></span></div>
            </div>
            <div class="cwm-main-content-grid">
                <div class="cwm-panel"><h3>Holdings</h3><div class="cwm-chart-container"><canvas id="cwm-holdings-donut-chart"></canvas></div></div>
                <div class="cwm-panel"><div class="cwm-panel-header"><h3>Performance</h3></div><div class="cwm-chart-container"><canvas id="cwm-performance-line-chart"></canvas></div></div>
            </div>
            <div class="cwm-panel">
                <h3>Assets</h3>
                <div class="cwm-table-container">
                    <table class="cwm-assets-table">
                        <thead><tr><th>Coin</th><th>Balance</th><th>PNL (24h)</th><th>1h</th><th>24h</th><th>7d</th><th>Price</th><th>24h Volume</th><th>Market Cap</th><th>Last 7 Days</th></tr></thead>
                        <tbody>
                            <?php foreach ($all_coin_data as $coin => $data): if(empty($data['amount'])) continue; ?>
                            <tr>
                                <td class="cwm-coin-cell"><a href="<?php echo esc_url(add_query_arg('view_coin', $coin)); ?>"><?php echo $this->get_coin_icon_svg($coin); ?><div><strong><?php echo esc_html($coin); ?></strong></div></a></td>
                                <td class="text-right"><div>$<?php echo number_format($data['value_usd'] ?? 0, 2); ?></div><div class="cwm-sub-text"><?php echo rtrim(rtrim(number_format($data['amount'], 8), '0'), '.'); ?> <?php echo esc_html($coin); ?></div></td>
                                <td class="text-right <?php echo ($data['pnl_usd'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>"><div><?php echo ($data['pnl_usd'] >= 0 ? '+' : '-') . '$' . number_format(abs($data['pnl_usd'] ?? 0), 2); ?></div><div class="cwm-sub-text"><?php echo number_format($data['change_24h'] ?? 0, 2); ?>%</div></td>
                                <td class="<?php echo ($data['change_1h'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($data['change_1h'] ?? 0, 2); ?>%</td>
                                <td class="<?php echo ($data['change_24h'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($data['change_24h'] ?? 0, 2); ?>%</td>
                                <td class="<?php echo ($data['change_7d'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>"><?php echo number_format($data['change_7d'] ?? 0, 2); ?>%</td>
                                <td>$<?php echo number_format($data['usd'] ?? 0, 4); ?></td>
                                <td>$<?php echo number_format($data['volume_24h'] ?? 0); ?></td>
                                <td>$<?php echo number_format($data['market_cap'] ?? 0); ?></td>
                                <td><div class="cwm-sparkline-container" data-sparkline='<?php echo json_encode($data['sparkline'] ?? []); ?>' data-color="<?php echo ($data['change_7d'] ?? 0) >= 0 ? '#03a66d' : '#d94d4d'; ?>"></div></td>
                            </tr>
                            <?php endforeach; if (empty($all_coin_data) || !array_filter($all_coin_data, function($d){ return !empty($d['amount']); })): ?><tr><td colspan="10" style="text-align:center;">You currently have no assets.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'mining'; ?>
            <div class="cwm-tabs-nav">
                <button class="cwm-tab-link <?php echo $active_tab === 'mining' ? 'active' : ''; ?>" data-tab="mining">Mining History</button>
                <button class="cwm-tab-link <?php echo $active_tab === 'purchase' ? 'active' : ''; ?>" data-tab="purchase">Purchase History</button>
                <button class="cwm-tab-link <?php echo $active_tab === 'deposit' ? 'active' : ''; ?>" data-tab="deposit">Deposit</button>
                <button class="cwm-tab-link <?php echo $active_tab === 'withdraw' ? 'active' : ''; ?>" data-tab="withdraw">Withdraw</button>
            </div>
            <div class="cwm-tabs-content">
                <div id="cwm-tab-panel-mining" class="cwm-tab-panel <?php echo $active_tab === 'mining' ? 'active' : ''; ?>"><?php $this->render_mining_rewards_table($user_id); ?></div>
                <div id="cwm-tab-panel-purchase" class="cwm-tab-panel <?php echo $active_tab === 'purchase' ? 'active' : ''; ?>"><?php $this->render_purchase_history_table($user_id); ?></div>
                <div id="cwm-tab-panel-deposit" class="cwm-tab-panel <?php echo $active_tab === 'deposit' ? 'active' : ''; ?>">
                    <?php $this->render_deposit_form($user_id); ?>
                    <?php $this->render_deposit_history_table($user_id); ?>
                </div>
                <div id="cwm-tab-panel-withdraw" class="cwm-tab-panel <?php echo $active_tab === 'withdraw' ? 'active' : ''; ?>">
                    <?php $this->render_withdraw_form($user_id); ?>
                    <?php $this->render_withdraw_history_table($user_id); ?>
                </div>
            </div>
            <div id="cwm-notification-modal" class="cwm-modal-overlay">
                <div class="cwm-modal-card">
                    <h3>Package Activation Required</h3>
                    <p>Please Activate Event Package</p>
                    <button class="cwm-modal-close-btn">CLOSE</button>
                </div>
            </div>
        <?php
    }

    // NEW: Render Deposit Form
    private function render_deposit_form($user_id) {
        ?>
        <div class="cwm-form-container">
            <h3>Deposit Crypto</h3>
            <form id="cwm-deposit-form">
                <?php wp_nonce_field('cwm_deposit_nonce', 'deposit_nonce'); ?>
                
                <div class="cwm-form-row">
                    <label for="deposit-coin">Select Coin:</label>
                    <select id="deposit-coin" name="coin" required>
                        <option value="">-- Select Coin --</option>
                        <option value="BTC">Bitcoin (BTC)</option>
                        <option value="BNB">Binance Coin (BNB)</option>
                        <option value="LTC">Litecoin (LTC)</option>
                        <option value="SOL">Solana (SOL)</option>
                    </select>
                </div>

                <div class="cwm-form-row">
                    <label for="deposit-network">Select Network:</label>
                    <select id="deposit-network" name="network" required>
                        <option value="">-- Select Network --</option>
                    </select>
                </div>

                <div class="cwm-form-row">
                    <label for="deposit-amount">Enter Amount:</label>
                    <input type="number" id="deposit-amount" name="amount" step="0.00000001" placeholder="0.00000000" required>
                </div>

                <div id="deposit-address-section" style="display:none;">
                    <div class="cwm-deposit-address">
                        <label>Deposit Address:</label>
                        <div class="cwm-address-row">
                            <input type="text" id="deposit-address-display" readonly>
                            <button type="button" id="copy-deposit-address">Copy</button>
                        </div>
                    </div>
                    
                    <div class="cwm-qr-code-section">
                        <div id="deposit-qr-code"></div>
                        <p class="cwm-caution"><strong>⚠️ Caution: Please verify your Deposit Address</strong></p>
                    </div>
                </div>

                <button type="submit" class="cwm-submit-btn">Submit Deposit Request</button>
            </form>
        </div>
        <?php
    }

    // NEW: Render Withdraw Form
    private function render_withdraw_form($user_id) {
        ?>
        <div class="cwm-form-container">
            <h3>Withdraw Crypto</h3>
            
            <div class="cwm-withdraw-type-tabs">
                <button type="button" class="cwm-withdraw-tab active" data-type="bank">Bank</button>
                <button type="button" class="cwm-withdraw-tab" data-type="onchain">Onchain</button>
                <button type="button" class="cwm-withdraw-tab" data-type="cex">CEX</button>
            </div>

            <!-- Bank Withdrawal Form -->
            <form id="cwm-withdraw-bank-form" class="cwm-withdraw-form active">
                <?php wp_nonce_field('cwm_withdrawal_nonce', 'withdrawal_nonce'); ?>
                <input type="hidden" name="withdraw_type" value="bank">
                
                <div class="cwm-form-grid">
                    <div class="cwm-form-row">
                        <label>First Name:</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>Last Name:</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="cwm-form-row cwm-full-width">
                        <label>Full Address:</label>
                        <textarea name="address" rows="3" required></textarea>
                    </div>
                    <div class="cwm-form-row">
                        <label>Email:</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>Mobile Number:</label>
                        <input type="tel" name="mobile" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>Bank Name:</label>
                        <input type="text" name="bank_name" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>Branch Name:</label>
                        <input type="text" name="branch_name" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>SWIFT Code:</label>
                        <input type="text" name="swift_code" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>Bank Account Number:</label>
                        <input type="text" name="account_number" required>
                    </div>
                    <div class="cwm-form-row">
                        <label>Select Coin:</label>
                        <select name="coin" required>
                            <option value="">-- Select Coin --</option>
                            <option value="BTC">Bitcoin (BTC)</option>
                            <option value="BNB">Binance Coin (BNB)</option>
                            <option value="LTC">Litecoin (LTC)</option>
                            <option value="SOL">Solana (SOL)</option>
                        </select>
                    </div>
                    <div class="cwm-form-row">
                        <label>Enter Amount:</label>
                        <input type="number" name="amount" step="0.00000001" required>
                    </div>
                </div>
                <button type="submit" class="cwm-submit-btn">Submit Bank Withdrawal</button>
            </form>

            <!-- Onchain Withdrawal Form -->
            <form id="cwm-withdraw-onchain-form" class="cwm-withdraw-form">
                <?php wp_nonce_field('cwm_withdrawal_nonce', 'withdrawal_nonce'); ?>
                <input type="hidden" name="withdraw_type" value="onchain">
                
                <div class="cwm-form-row">
                    <label>Select Coin:</label>
                    <select name="coin" class="onchain-coin-select" required>
                        <option value="">-- Select Coin --</option>
                        <option value="BTC">Bitcoin (BTC)</option>
                        <option value="BNB">Binance Coin (BNB)</option>
                        <option value="LTC">Litecoin (LTC)</option>
                        <option value="SOL">Solana (SOL)</option>
                    </select>
                </div>

                <div class="cwm-form-row">
                    <label>Network/Chain:</label>
                    <select name="network" class="onchain-network-select" required>
                        <option value="">-- Select Network --</option>
                    </select>
                </div>

                <div class="cwm-form-row">
                    <label>Withdraw Address:</label>
                    <input type="text" name="withdraw_address" placeholder="Enter wallet address" required>
                </div>

                <div class="cwm-form-row">
                    <label>Enter Amount:</label>
                    <input type="number" name="amount" step="0.00000001" required>
                </div>

                <button type="submit" class="cwm-submit-btn">Submit Onchain Withdrawal</button>
            </form>

            <!-- CEX Withdrawal Form -->
            <form id="cwm-withdraw-cex-form" class="cwm-withdraw-form">
                <?php wp_nonce_field('cwm_withdrawal_nonce', 'withdrawal_nonce'); ?>
                <input type="hidden" name="withdraw_type" value="cex">
                
                <div class="cwm-form-row">
                    <label>Select Exchange:</label>
                    <select name="exchange" class="cex-exchange-select" required>
                        <option value="">-- Select Exchange --</option>
                        <option value="binance">Binance</option>
                        <option value="kucoin">KuCoin</option>
                        <option value="bybit">Bybit</option>
                        <option value="bitget">Bitget</option>
                        <option value="coinbase">Coinbase</option>
                    </select>
                </div>

                <div class="cwm-form-row">
                    <label class="cex-email-label">Your Email/UID:</label>
                    <input type="text" name="email_uid" class="cex-email-input" placeholder="Enter your exchange email or UID" required>
                </div>

                <div class="cwm-form-row">
                    <label>Select Coin:</label>
                    <select name="coin" required>
                        <option value="">-- Select Coin --</option>
                        <option value="BTC">Bitcoin (BTC)</option>
                        <option value="BNB">Binance Coin (BNB)</option>
                        <option value="LTC">Litecoin (LTC)</option>
                        <option value="SOL">Solana (SOL)</option>
                    </select>
                </div>

                <div class="cwm-form-row">
                    <label>Enter Amount:</label>
                    <input type="number" name="amount" step="0.00000001" required>
                </div>

                <button type="submit" class="cwm-submit-btn">Submit CEX Withdrawal</button>
            </form>
        </div>
        <?php
    }

    // NEW: Render Deposit History
    private function render_deposit_history_table($user_id) {
        global $wpdb;
        $deposits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWM_DEPOSITS_TABLE . " WHERE user_id = %d ORDER BY created_date DESC LIMIT 10",
            $user_id
        ));
        ?>
        <div class="cwm-panel">
            <h3>Deposit History</h3>
            <div class="cwm-table-container">
                <table class="cwm-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Coin</th>
                            <th>Network</th>
                            <th>Amount</th>
                            <th>TXID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($deposits): ?>
                            <?php foreach ($deposits as $deposit): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($deposit->created_date))); ?></td>
                                <td class="cwm-coin-cell">
                                    <?php echo $this->get_coin_icon_svg($deposit->coin_name); ?>
                                    <?php echo esc_html($deposit->coin_name); ?>
                                </td>
                                <td><?php echo esc_html($deposit->network); ?></td>
                                <td><?php echo esc_html($deposit->amount); ?></td>
                                <td><?php echo $deposit->txid ? esc_html($deposit->txid) : '-'; ?></td>
                                <td class="cwm-status-<?php echo esc_attr($deposit->status); ?>">
                                    <?php echo esc_html(ucfirst($deposit->status)); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center;">No deposits found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // NEW: Render Withdraw History
    private function render_withdraw_history_table($user_id) {
        global $wpdb;
        $withdrawals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . CWM_WITHDRAWALS_TABLE . " WHERE user_id = %d ORDER BY created_date DESC LIMIT 10",
            $user_id
        ));
        ?>
        <div class="cwm-panel">
            <h3>Withdrawal History</h3>
            <div class="cwm-table-container">
                <table class="cwm-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Coin</th>
                            <th>Amount</th>
                            <th>Address/Details</th>
                            <th>TXID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($withdrawals): ?>
                            <?php foreach ($withdrawals as $withdrawal): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($withdrawal->created_date))); ?></td>
                                <td>
                                    <span class="cwm-withdraw-type-<?php echo esc_attr($withdrawal->withdraw_type); ?>">
                                        <?php echo esc_html(ucfirst($withdrawal->withdraw_type)); ?>
                                    </span>
                                </td>
                                <td class="cwm-coin-cell">
                                    <?php echo $this->get_coin_icon_svg($withdrawal->coin_name); ?>
                                    <?php echo esc_html($withdrawal->coin_name); ?>
                                </td>
                                <td><?php echo esc_html($withdrawal->amount); ?></td>
                                <td>
                                    <?php if ($withdrawal->withdraw_type == 'onchain' && $withdrawal->withdraw_address): ?>
                                        <code><?php echo esc_html(substr($withdrawal->withdraw_address, 0, 20) . '...'); ?></code>
                                    <?php else: ?>
                                        <?php echo esc_html(ucfirst($withdrawal->withdraw_type)); ?> Details
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $withdrawal->txid ? esc_html($withdrawal->txid) : '-'; ?></td>
                                <td class="cwm-status-<?php echo esc_attr($withdrawal->status); ?>">
                                    <?php echo esc_html(ucfirst($withdrawal->status)); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center;">No withdrawals found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // Rest of the original functions (coin detail view, mining rewards, purchase history, etc.)
    public function render_coin_detail_view($user_id, $coin_symbol, $coin_data) {
        $pnl_class = ($coin_data['pnl_usd'] ?? 0) >= 0 ? 'positive' : 'negative';
        ?>
        <div class="cwm-detail-view">
            <div class="cwm-detail-header">
                <a href="<?php echo esc_url(remove_query_arg('view_coin')); ?>" class="cwm-back-link">&larr; Back to Full Portfolio</a>
                <h2><?php echo $this->get_coin_icon_svg($coin_symbol); ?> <?php echo esc_html($coin_symbol); ?> Details</h2>
            </div>
            <div class="cwm-summary-bar">
                <div class="cwm-summary-card"><span class="cwm-card-label">Total <?php echo esc_html($coin_symbol); ?> Value</span><span class="cwm-card-value">$<?php echo number_format($coin_data['value_usd'] ?? 0, 2); ?></span></div>
                <div class="cwm-summary-card"><span class="cwm-card-label">Current Balance</span><span class="cwm-card-value"><?php echo rtrim(rtrim(number_format($coin_data['amount'], 8), '0'), '.'); ?> <?php echo esc_html($coin_symbol); ?></span></div>
                <div class="cwm-summary-card"><span class="cwm-card-label">24h PNL</span><span class="cwm-card-value <?php echo $pnl_class; ?>"><?php echo ($coin_data['pnl_usd'] >= 0 ? '+' : '-') . '$' . number_format(abs($coin_data['pnl_usd'] ?? 0), 2); ?></span></div>
                <div class="cwm-summary-card"><span class="cwm-card-label">Live Price</span><span class="cwm-card-value">$<?php echo number_format($coin_data['usd'] ?? 0, 4); ?></span></div>
            </div>
            <div class="cwm-panel">
                <div class="cwm-panel-header">
                    <h3>Price History</h3>
                    <div class="cwm-chart-filters" data-coin-id="<?php echo esc_attr($coin_data['id']); ?>">
                        <button data-range="1">1D</button>
                        <button class="active" data-range="7">7D</button>
                        <button data-range="30">1M</button>
                        <button data-range="max">All</button>
                    </div>
                </div>
                <div class="cwm-chart-container" style="height: 300px;">
                    <canvas id="cwm-coin-detail-chart"></canvas>
                    <div class="cwm-chart-loader" style="display: none;"><div></div></div>
                </div>
            </div>
            <?php $this->render_single_coin_mining_history($user_id, $coin_symbol); ?>
        </div>
        <?php
    }
    
    private function render_purchase_history_table($user_id) {
        global $wpdb; $results = $wpdb->get_results($wpdb->prepare( "SELECT * FROM " . CWM_PURCHASES_TABLE . " WHERE user_id = %d ORDER BY transaction_date DESC LIMIT 100", $user_id ));
        ?>
        <div class="cwm-panel"><h2>Purchase History</h2><div class="cwm-table-container"><table class="cwm-history-table"><thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Price</th><th>Paid With</th><th>Status</th></tr></thead><tbody>
        <?php if ($results): foreach ($results as $row): ?><tr><td data-label="Date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($row->transaction_date))); ?></td><td data-label="Item"><?php echo esc_html($row->item_name); ?></td><td data-label="Type"><?php echo esc_html(ucfirst($row->type)); ?></td><td data-label="Price">$<?php echo esc_html(number_format($row->price, 2)); ?></td><td data-label="Paid With" class="cwm-coin-cell"><?php echo $this->get_coin_icon_svg($row->paid_with_coin); ?><span><?php echo rtrim(rtrim(number_format($row->paid_coin_amount, 8), '0'), '.'); ?> <?php echo esc_html($row->paid_with_coin); ?></span></td><td data-label="Status" class="cwm-status-<?php echo esc_attr($row->status); ?>"><?php echo esc_html(ucfirst($row->status)); ?></td></tr><?php endforeach; else: ?><tr><td colspan="6" style="text-align:center;">No purchases found.</td></tr><?php endif; ?>
        </tbody></table></div></div><?php
    }
    private function render_mining_rewards_table($user_id) {
        global $wpdb;
        $per_page = 10;
        $current_page = max(1, get_query_var('paged'));
        $offset = ($current_page - 1) * $per_page;
        $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM " . CWM_REWARDS_TABLE . " WHERE user_id = %d", $user_id));
        $results = $wpdb->get_results($wpdb->prepare( "SELECT * FROM " . CWM_REWARDS_TABLE . " WHERE user_id = %d ORDER BY reward_date DESC, id DESC LIMIT %d OFFSET %d", $user_id, $per_page, $offset ));
        ?>
        <div class="cwm-panel"><h2>Mining Rewards History</h2><div class="cwm-table-container"><table class="cwm-history-table"><thead><tr><th>Date</th><th>Transaction ID</th><th>Package Name</th><th>Coin Name</th><th>Coin Amount</th><th>Details</th><th>Status</th></tr></thead><tbody>
        <?php if ($results): foreach ($results as $row): ?><tr><td data-label="Date"><?php echo esc_html(date("Y-m-d", strtotime($row->reward_date))); ?></td><td data-label="Transaction ID"><?php echo esc_html($row->transaction_id); ?></td><td data-label="Package Name"><?php echo esc_html($row->package_name); ?></td><td data-label="Coin Name" class="cwm-coin-cell"><?php echo $this->get_coin_icon_svg($row->coin_name); ?> <?php echo esc_html($row->coin_name); ?></td><td data-label="Coin Amount" class="positive"><?php echo rtrim(rtrim(number_format($row->coin_amount, 8), '0'), '.'); ?> <?php echo esc_html($row->coin_name); ?></td><td data-label="Details"><?php echo esc_html($row->details); ?></td><td data-label="Status" class="cwm-status-withdrawable"><?php echo esc_html($row->status); ?></td></tr><?php endforeach; else: ?><tr><td colspan="7" style="text-align:center;">No mining rewards found.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($total_items > $per_page): ?>
            <div class="cwm-pagination">
                <?php
                echo paginate_links(array( 'base' => add_query_arg('paged', '%#%'), 'format' => '?paged=%#%', 'current' => $current_page, 'total' => ceil($total_items / $per_page), 'add_args' => array('tab' => 'mining'), 'prev_text' => __('&laquo; Previous'), 'next_text' => __('Next &raquo;'), ));
                ?>
            </div>
        <?php endif; ?>
        </div><?php
    }
    private function render_single_coin_mining_history($user_id, $coin_symbol) {
        global $wpdb;
        $per_page = 10;
        $current_page = max(1, get_query_var('paged'));
        $offset = ($current_page - 1) * $per_page;
        $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM " . CWM_REWARDS_TABLE . " WHERE user_id = %d AND coin_name = %s", $user_id, $coin_symbol));
        $results = $wpdb->get_results($wpdb->prepare( "SELECT * FROM " . CWM_REWARDS_TABLE . " WHERE user_id = %d AND coin_name = %s ORDER BY reward_date DESC, id DESC LIMIT %d OFFSET %d", $user_id, $coin_symbol, $per_page, $offset ));
        ?>
        <div class="cwm-panel"><h3><?php echo esc_html($coin_symbol); ?> Mining History</h3><div class="cwm-table-container"><table class="cwm-history-table"><thead><tr><th>Date</th><th>Transaction ID</th><th>Package Name</th><th>Coin Amount</th><th>Details</th><th>Status</th></tr></thead><tbody>
        <?php if ($results): foreach ($results as $row): ?><tr><td data-label="Date"><?php echo esc_html(date("Y-m-d", strtotime($row->reward_date))); ?></td><td data-label="Transaction ID"><?php echo esc_html($row->transaction_id); ?></td><td data-label="Package Name"><?php echo esc_html($row->package_name); ?></td><td data-label="Coin Amount" class="positive"><?php echo rtrim(rtrim(number_format($row->coin_amount, 8), '0'), '.'); ?> <?php echo esc_html($row->coin_name); ?></td><td data-label="Details"><?php echo esc_html($row->details); ?></td><td data-label="Status" class="cwm-status-withdrawable"><?php echo esc_html($row->status); ?></td></tr><?php endforeach; else: ?><tr><td colspan="6" style="text-align:center;">No <?php echo esc_html($coin_symbol); ?> mining rewards found.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($total_items > $per_page): ?>
            <div class="cwm-pagination">
                <?php
                echo paginate_links(array( 'base' => add_query_arg('paged', '%#%'), 'format' => '?paged=%#%', 'current' => $current_page, 'total' => ceil($total_items / $per_page), 'add_args' => array('view_coin' => $coin_symbol), 'prev_text' => __('&laquo; Previous'), 'next_text' => __('Next &raquo;'), ));
                ?>
            </div>
        <?php endif; ?>
        </div><?php
    }
    
    private function render_dashboard_styles() { 
        ?>
        <style>
        :root { 
            --cwm-text-primary: #1e2329; 
            --cwm-text-secondary: #707a8a; 
            --cwm-bg-primary: #ffffff; 
            --cwm-bg-secondary: #f8f9fa; 
            --cwm-border-color: #e9ecef; 
            --cwm-positive: #03a66d; 
            --cwm-negative: #d94d4d; 
            --cwm-brand: #f0b90b; 
            --cwm-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
        } 
        .cwm-dashboard-container { 
            font-family: var(--cwm-font-family); 
            color: var(--cwm-text-primary); 
        } 
        .cwm-summary-bar { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
            border-bottom: 1px solid var(--cwm-border-color); 
            padding-bottom: 20px; 
        } 
        .cwm-summary-card { 
            padding: 5px; 
        } 
        .cwm-card-label { 
            font-size: 14px; 
            color: var(--cwm-text-secondary); 
            margin-bottom: 8px; 
        } 
        .cwm-card-value { 
            font-size: 20px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        } 
        .cwm-card-percent { 
            font-size: 14px; 
            font-weight: normal; 
        } 
        .positive { 
            color: var(--cwm-positive); 
        } 
        .negative { 
            color: var(--cwm-negative); 
        } 
        .cwm-main-content-grid { 
            display: grid; 
            grid-template-columns: 35% 1fr; 
            gap: 20px; 
            margin-bottom: 20px; 
            border-bottom: 1px solid var(--cwm-border-color); 
            padding-bottom: 20px; 
        } 
        @media (max-width: 900px) { 
            .cwm-main-content-grid { 
                grid-template-columns: 1fr; 
            } 
        } 
        .cwm-panel { 
            margin-bottom:20px; 
        } 
        .cwm-panel h3 { 
            margin: 0 0 15px 0; 
            font-size: 18px; 
        } 
        .cwm-panel-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 10px;
        } 
        .cwm-chart-container { 
            position: relative; 
            height: 250px; 
        } 
        .cwm-chart-filters button { 
            background: var(--cwm-bg-secondary); 
            border: 1px solid var(--cwm-border-color); 
            padding: 5px 10px; 
            border-radius: 4px; 
            font-size: 12px; 
            cursor: pointer; 
            margin-left: 5px; 
        } 
        .cwm-chart-filters button.active { 
            background-color: var(--cwm-brand); 
            border-color: var(--cwm-brand); 
            color: #fff; 
        } 
        .cwm-table-container { 
            overflow-x: auto; 
        } 
        .cwm-assets-table, .cwm-history-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 14px; 
        } 
        .cwm-assets-table th, .cwm-assets-table td, .cwm-history-table th, .cwm-history-table td { 
            padding: 16px 12px; 
            text-align: left; 
            border: none; 
            border-bottom: 1px solid var(--cwm-border-color); 
            vertical-align: middle; 
            white-space: nowrap; 
        } 
        .cwm-assets-table td a { 
            text-decoration: none; 
            color: inherit; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        } 
        .cwm-assets-table th.text-right, .cwm-assets-table td.text-right { 
            text-align: right; 
        } 
        .cwm-assets-table th, .cwm-history-table th { 
            font-weight: 500; 
            font-size: 12px; 
            color: var(--cwm-text-secondary); 
            text-transform: uppercase; 
            background-color: #fff; 
        } 
        .cwm-coin-icon { 
            width: 28px; 
            height: 28px; 
        } 
        .cwm-coin-cell { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        } 
        .cwm-sub-text { 
            font-size: 12px; 
            color: var(--cwm-text-secondary); 
        } 
        .cwm-sparkline-container { 
            width: 150px; 
            height: 40px; 
        } 
        .cwm-tabs-nav { 
            display: flex; 
            gap: 10px; 
            border-bottom: 1px solid var(--cwm-border-color); 
            margin-bottom: 0; 
        } 
        .cwm-tab-link { 
            background: none; 
            border: none; 
            padding: 10px 15px; 
            cursor: pointer; 
            font-size: 16px; 
            color: var(--cwm-text-secondary); 
            border-bottom: 2px solid transparent; 
            margin-bottom: -1px; 
        } 
        .cwm-tab-link.active { 
            color: var(--cwm-text-primary); 
            border-bottom-color: var(--cwm-brand); 
        } 
        .cwm-tab-panel { 
            display: none; 
            padding-top: 20px;
        } 
        .cwm-tab-panel .cwm-panel { 
            border: 1px solid var(--cwm-border-color); 
            border-radius: 8px; 
        } 
        .cwm-tab-panel.active { 
            display: block; 
        } 
        .cwm-form-container { 
            max-width: 600px; 
            padding: 20px; 
            background: var(--cwm-bg-secondary); 
            border-radius: 8px; 
            margin-bottom: 20px;
        } 
        .cwm-form-container form { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
        }
        .cwm-form-row {
            display: flex;
            flex-direction: column;
        }
        .cwm-form-row label {
            margin-bottom: 5px;
            font-weight: 500;
        }
        .cwm-form-row input, .cwm-form-row select, .cwm-form-row textarea {
            padding: 10px;
            border: 1px solid var(--cwm-border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        .cwm-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .cwm-form-grid .cwm-full-width {
            grid-column: 1 / -1;
        }
        .cwm-withdraw-type-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--cwm-border-color);
        }
        .cwm-withdraw-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: var(--cwm-text-secondary);
        }
        .cwm-withdraw-tab.active {
            color: var(--cwm-text-primary);
            border-bottom-color: var(--cwm-brand);
        }
        .cwm-withdraw-form {
            display: none;
        }
        .cwm-withdraw-form.active {
            display: block;
        }
        .cwm-submit-btn {
            background: var(--cwm-brand);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        .cwm-submit-btn:hover {
            opacity: 0.9;
        }
        .cwm-address-row {
            display: flex;
            gap: 10px;
        }
        .cwm-address-row input {
            flex: 1;
        }
        .cwm-caution {
            color: var(--cwm-negative);
            font-weight: bold;
            text-align: center;
            margin-top: 10px;
        }
        .cwm-qr-code-section {
            text-align: center;
            margin-top: 15px;
        }
        .cwm-qr-code-section img {
            border: 1px solid var(--cwm-border-color);
            padding: 10px;
            border-radius: 8px;
        }
        .cwm-status-pending { color: #f39c12; }
        .cwm-status-approved { color: var(--cwm-positive); }
        .cwm-status-declined { color: var(--cwm-negative); }
        .cwm-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .cwm-modal-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .cwm-modal-close-btn {
            background: var(--cwm-brand);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
        }
        .cwm-pagination .page-numbers { 
            padding: 8px 12px; 
            border: 1px solid var(--cwm-border-color); 
            text-decoration: none; 
            color: var(--cwm-text-primary); 
            margin: 0 2px; 
            border-radius: 4px; 
        } 
        .cwm-pagination .page-numbers.current { 
            background: var(--cwm-brand); 
            color: #fff; 
            border-color: var(--cwm-brand); 
        } 
        .cwm-detail-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            margin-bottom: 20px; 
            border-bottom: 1px solid var(--cwm-border-color); 
            padding-bottom: 20px; 
        } 
        .cwm-detail-header h2 { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin: 0; 
        } 
        .cwm-back-link { 
            text-decoration: none; 
            color: var(--cwm-text-secondary); 
            font-size: 14px; 
        } 
        .cwm-chart-loader { 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(255,255,255,0.7); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 14px; 
            color: var(--cwm-text-secondary); 
            z-index: 10; 
        }
        @media (max-width: 768px) {
            .cwm-form-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }

    private function render_dashboard_js($all_coin_data, $viewing_coin) {
        $js_data = ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('cwm_chart_nonce') ];
        if($viewing_coin) {
            $js_data['detail_coin'] = $all_coin_data[$viewing_coin] ?? [];
            $js_data['detail_coin']['color'] = ($js_data['detail_coin']['change_7d'] ?? 0) >= 0 ? '#03a66d' : '#d94d4d';
        } else {
            $holdings_data = [];
            $total_value = 0;
            foreach ($all_coin_data as $coin => $data) { 
                $total_value += $data['value_usd'] ?? 0;
                if (($data['value_usd'] ?? 0) > 0) { $holdings_data['labels'][] = $coin; $holdings_data['values'][] = $data['value_usd']; } 
            }
            $js_data['portfolio'] = ['holdings' => $holdings_data, 'total_value' => $total_value];
        }
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            const chartColors = ['#E67E22', '#3498DB', '#F1C40F', '#2ECC71', '#9B59B6'];
            const cwmData = <?php echo json_encode($js_data); ?>;
            let detailChartInstance;

            // Portfolio charts
            if (cwmData.portfolio) {
                const holdingsCtx = document.getElementById('cwm-holdings-donut-chart');
                if(holdingsCtx && typeof Chart !== 'undefined') {
                    if (cwmData.portfolio.holdings.labels && cwmData.portfolio.holdings.labels.length > 0) { 
                        new Chart(holdingsCtx, { 
                            type: 'doughnut', 
                            data: { 
                                labels: cwmData.portfolio.holdings.labels, 
                                datasets: [{ 
                                    data: cwmData.portfolio.holdings.values, 
                                    backgroundColor: chartColors, 
                                    borderWidth: 0 
                                }] 
                            }, 
                            options: { 
                                responsive: true, 
                                maintainAspectRatio: false, 
                                plugins: { 
                                    legend: { 
                                        position: 'right' 
                                    } 
                                }, 
                                cutout: '70%' 
                            } 
                        });
                    } else { 
                        holdingsCtx.parentElement.innerHTML = '<p style="text-align:center;padding-top:80px;">No assets to display.</p>'; 
                    }
                }
                const performanceCtx = document.getElementById('cwm-performance-line-chart');
                if(performanceCtx && typeof Chart !== 'undefined') {
                    const totalValue = cwmData.portfolio.total_value;
                    const gradient = performanceCtx.getContext('2d').createLinearGradient(0, 0, 0, 250);
                    gradient.addColorStop(0, 'rgba(3, 166, 109, 0.2)'); gradient.addColorStop(1, 'rgba(3, 166, 109, 0)');
                    new Chart(performanceCtx, { 
                        type: 'line', 
                        data: { 
                            labels: ['Day 1','Day 2','Day 3','Day 4','Day 5','Day 6','Today'], 
                            datasets: [{ 
                                label: 'Value', 
                                data: [totalValue*0.95, totalValue*0.92, totalValue*0.98, totalValue*0.96, totalValue*1.02, totalValue*0.99, totalValue], 
                                borderColor: '#03a66d', 
                                backgroundColor: gradient, 
                                tension: 0.3, 
                                pointRadius: 0, 
                                fill: true 
                            }] 
                        }, 
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            plugins: { 
                                legend: { 
                                    display: false 
                                } 
                            }, 
                            scales: { 
                                y: { 
                                    display: false 
                                }, 
                                x: { 
                                    display: false 
                                } 
                            } 
                        } 
                    }); 
                }
            } else if (cwmData.detail_coin) { 
                const detailCtx = document.getElementById('cwm-coin-detail-chart');
                if (detailCtx && typeof Chart !== 'undefined') {
                    const initialData = cwmData.detail_coin.sparkline || [];
                    const gradient = detailCtx.getContext('2d').createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, 'rgba(3, 166, 109, 0.2)'); gradient.addColorStop(1, 'rgba(3, 166, 109, 0)');
                    detailChartInstance = new Chart(detailCtx, { 
                        type: 'line', 
                        data: { 
                            datasets: [{ 
                                label: 'Price', 
                                data: initialData.map((price, index) => ({x: Date.now() - (initialData.length - index) * 3600 * 1000 * 24, y: price})), 
                                borderColor: cwmData.detail_coin.color, 
                                backgroundColor: gradient, 
                                tension: 0.1, 
                                pointRadius: 0, 
                                fill: true 
                            }] 
                        }, 
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            plugins: { 
                                legend: { 
                                    display: false 
                                }, 
                                tooltip: { 
                                    mode: 'index', 
                                    intersect: false 
                                } 
                            }, 
                            scales: { 
                                x: { 
                                    type: 'time', 
                                    time: { 
                                        unit: 'day' 
                                    } 
                                }, 
                                y: { 
                                    display: true 
                                } 
                            } 
                        } 
                    });
                }
            }

            // Sparklines
            function drawSparklines() {
                document.querySelectorAll('.cwm-sparkline-container').forEach(container => {
                    try { 
                        const data = JSON.parse(container.dataset.sparkline); 
                        if (!data || data.length < 2) return; 
                        const color = container.dataset.color; 
                        const width = 150, height = 40; 
                        const max = Math.max(...data), min = Math.min(...data), range = max - min; 
                        if (range === 0) { 
                            container.innerHTML = `<svg viewbox="0 0 ${width} ${height}" style="overflow: visible;"><line x1="0" y1="${height/2}" x2="${width}" y2="${height/2}" stroke="${color}" stroke-width="2"/></svg>`; 
                            return; 
                        }; 
                        const points = data.map((d, i) => `${(i / (data.length - 1)) * width},${height - ((d - min) / range) * height}`).join(' '); 
                        container.innerHTML = `<svg viewbox="0 0 ${width} ${height}" style="overflow: visible;"><polyline points="${points}" fill="none" stroke="${color}" stroke-width="2"/></svg>`; 
                    } catch(e) {}
                });
            }
            drawSparklines();
            
            // Chart filters
            const chartFilters = document.querySelector('.cwm-chart-filters');
            if (chartFilters && detailChartInstance) {
                const loader = document.querySelector('.cwm-chart-loader');
                chartFilters.addEventListener('click', function (e) {
                    if (e.target.tagName === 'BUTTON' && !e.target.classList.contains('active')) {
                        chartFilters.querySelector('.active').classList.remove('active');
                        e.target.classList.add('active');
                        loader.style.display = 'flex';
                        const coinId = chartFilters.dataset.coinId;
                        const range = e.target.dataset.range;
                        
                        const postData = new URLSearchParams();
                        postData.append('action', 'cwm_get_chart_data');
                        postData.append('nonce', cwmData.nonce);
                        postData.append('coin_id', coinId);
                        postData.append('days', range);

                        fetch(cwmData.ajax_url, { method: 'POST', body: postData })
                            .then(response => response.json())
                            .then(result => {
                                if (result.success) {
                                    const newData = result.data.map(item => ({ x: item[0], y: item[1] }));
                                    detailChartInstance.data.datasets[0].data = newData;
                                    detailChartInstance.options.scales.x.time.unit = (range === '1') ? 'hour' : 'day';
                                    detailChartInstance.update();
                                }
                                loader.style.display = 'none';
                            }).catch(() => loader.style.display = 'none');
                    }
                });
            }

            // Tab navigation
            const tabNav = document.querySelector('.cwm-tabs-nav');
            if (tabNav) {
                tabNav.addEventListener('click', function(e) {
                    if (e.target.matches('.cwm-tab-link') && !e.target.classList.contains('active')) {
                        e.preventDefault();
                        const currentURL = new URL(window.location.href);
                        currentURL.searchParams.set('tab', e.target.dataset.tab);
                        currentURL.searchParams.delete('paged');
                        window.history.pushState({}, '', currentURL.toString());
                        tabNav.querySelector('.cwm-tab-link.active').classList.remove('active');
                        document.querySelector('.cwm-tab-panel.active').classList.remove('active');
                        e.target.classList.add('active');
                        document.getElementById('cwm-tab-panel-' + e.target.dataset.tab).classList.add('active');
                    }
                });
            }

            // Network data
            const networkData = {
                'BTC': { 'bitcoin': 'Bitcoin Network' },
                'BNB': { 'bep20': 'BEP20 (BSC)' },
                'LTC': { 'litecoin': 'Litecoin Network' },
                'SOL': { 'solana': 'Solana Network' }
            };

            // Deposit form handling
            const depositCoin = document.getElementById('deposit-coin');
            const depositNetwork = document.getElementById('deposit-network');
            const depositForm = document.getElementById('cwm-deposit-form');

            if (depositCoin && depositNetwork) {
                depositCoin.addEventListener('change', function() {
                    const networks = networkData[this.value] || {};
                    depositNetwork.innerHTML = '<option value="">-- Select Network --</option>';
                    
                    Object.entries(networks).forEach(([key, value]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = value;
                        depositNetwork.appendChild(option);
                    });
                });
            }

            if (depositForm) {
                depositForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'cwm_submit_deposit');
                    formData.append('nonce', cwm_ajax.deposit_nonce);

                    fetch(cwm_ajax.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // Show deposit address and QR code
                            document.getElementById('deposit-address-display').value = result.data.address;
                            document.getElementById('deposit-qr-code').innerHTML = 
                                `<img src="${result.data.qr_code}" alt="QR Code" style="max-width: 200px;">`;
                            document.getElementById('deposit-address-section').style.display = 'block';
                            
                            alert(result.data.message);
                        } else {
                            alert('Error: ' + result.data);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
                });
            }

            // Copy address functionality
            const copyBtn = document.getElementById('copy-deposit-address');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    const addressField = document.getElementById('deposit-address-display');
                    addressField.select();
                    document.execCommand('copy');
                    this.textContent = 'Copied!';
                    setTimeout(() => {
                        this.textContent = 'Copy';
                    }, 2000);
                });
            }

            // Withdrawal form handling
            const withdrawTabs = document.querySelectorAll('.cwm-withdraw-tab');
            const withdrawForms = document.querySelectorAll('.cwm-withdraw-form');

            withdrawTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const type = this.dataset.type;
                    
                    // Update tabs
                    withdrawTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update forms
                    withdrawForms.forEach(f => f.classList.remove('active'));
                    document.getElementById(`cwm-withdraw-${type}-form`).classList.add('active');
                });
            });

            // Onchain network selection
            const onchainCoinSelect = document.querySelector('.onchain-coin-select');
            const onchainNetworkSelect = document.querySelector('.onchain-network-select');

            if (onchainCoinSelect && onchainNetworkSelect) {
                onchainCoinSelect.addEventListener('change', function() {
                    const networks = networkData[this.value] || {};
                    onchainNetworkSelect.innerHTML = '<option value="">-- Select Network --</option>';
                    
                    Object.entries(networks).forEach(([key, value]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = value;
                        onchainNetworkSelect.appendChild(option);
                    });
                });
            }

            // CEX exchange label update
            const cexExchangeSelect = document.querySelector('.cex-exchange-select');
            const cexEmailLabel = document.querySelector('.cex-email-label');
            const cexEmailInput = document.querySelector('.cex-email-input');

            if (cexExchangeSelect && cexEmailLabel && cexEmailInput) {
                cexExchangeSelect.addEventListener('change', function() {
                    const exchange = this.value;
                    const labels = {
                        'binance': 'Your Binance Email/UID:',
                        'kucoin': 'Your KuCoin Email/UID:',
                        'bybit': 'Your Bybit Email/UID:',
                        'bitget': 'Your Bitget Email/UID:',
                        'coinbase': 'Your Coinbase Email:'
                    };
                    const placeholders = {
                        'binance': 'your-binance@email.com or UID',
                        'kucoin': 'your-kucoin@email.com or UID',
                        'bybit': 'your-bybit@email.com or UID',
                        'bitget': 'your-bitget@email.com or UID',
                        'coinbase': 'your-coinbase@email.com'
                    };
                    
                    cexEmailLabel.textContent = labels[exchange] || 'Your Email/UID:';
                    cexEmailInput.placeholder = placeholders[exchange] || 'Enter your exchange email or UID';
                });
            }

            // Withdrawal form submission (shows modal)
            withdrawForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    document.getElementById('cwm-notification-modal').style.display = 'flex';
                });
            });

            // Modal close
            const modal = document.getElementById('cwm-notification-modal');
            const closeBtn = document.querySelector('.cwm-modal-close-btn');
            
            if (modal && closeBtn) {
                closeBtn.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            }
        });
        </script>
        <?php
    }
}

// Admin List Table for Purchase History
if (is_admin()) {
    class CWM_Purchase_History_List_Table extends WP_List_Table {
        public function __construct() { parent::__construct(['singular' => 'Wallet Purchase', 'plural' => 'Wallet Purchases', 'ajax' => false]); }
        public function get_columns() { return [ 'item_name' => 'Item', 'user_id' => 'User', 'type' => 'Type', 'price' => 'Price (USD)', 'paid_with_coin' => 'Paid With', 'transaction_date' => 'Date', 'status' => 'Status', 'actions' => 'Actions', ]; }
        protected function get_sortable_columns() { return [ 'item_name' => ['item_name', false], 'user_id' => ['user_id', false], 'price' => ['price', false], 'transaction_date' => ['transaction_date', true], ]; }
        protected function get_views() { global $wpdb; $current = isset($_GET['type_filter']) ? $_GET['type_filter'] : ''; $total_count = $wpdb->get_var("SELECT COUNT(*) FROM " . CWM_PURCHASES_TABLE); $product_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . CWM_PURCHASES_TABLE . " WHERE type = %s", 'product')); $sub_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . CWM_PURCHASES_TABLE . " WHERE type = %s", 'subscription')); $views = [ 'all' => sprintf('<a href="%s" class="%s">All <span class="count">(%d)</span></a>', remove_query_arg('type_filter'), $current === '' ? 'current' : '', $total_count), 'product' => sprintf('<a href="%s" class="%s">Products <span class="count">(%d)</span></a>', add_query_arg('type_filter', 'product'), $current === 'product' ? 'current' : '', $product_count), 'subscription' => sprintf('<a href="%s" class="%s">Subscriptions <span class="count">(%d)</span></a>', add_query_arg('type_filter', 'subscription'), $current === 'subscription' ? 'current' : '', $sub_count) ]; return $views; }
        public function prepare_items() { global $wpdb; $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()]; $per_page = 20; $current_page = $this->get_pagenum(); $offset = ($current_page - 1) * $per_page; $where_clauses = []; if ( ! empty( $_GET['user_filter'] ) ) $where_clauses[] = $wpdb->prepare( "t.user_id = %d", intval( $_GET['user_filter'] ) ); if ( ! empty( $_GET['type_filter'] ) ) $where_clauses[] = $wpdb->prepare( "t.type = %s", sanitize_text_field( $_GET['type_filter'] ) ); if ( ! empty( $_REQUEST['s'] ) ) { $search_term = '%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%'; $where_clauses[] = $wpdb->prepare( "t.item_name LIKE %s", $search_term ); } $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : ''; $order_by = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'transaction_date'; $order = !empty($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? $_GET['order'] : 'DESC'; $total_items = $wpdb->get_var("SELECT COUNT(*) FROM " . CWM_PURCHASES_TABLE . " t $where_sql"); $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]); $query = "SELECT t.*, u.display_name FROM " . CWM_PURCHASES_TABLE . " t LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID $where_sql ORDER BY $order_by $order LIMIT %d OFFSET %d"; $this->items = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset)); }
        protected function extra_tablenav($which) { if ($which == 'top') { $users = get_users(['fields' => ['ID', 'display_name']]); $current_user = !empty($_GET['user_filter']) ? intval($_GET['user_filter']) : ''; echo '<div class="alignleft actions"><select name="user_filter"><option value="">All Users</option>'; foreach ($users as $user) { printf('<option value="%d" %s>%s</option>', $user->ID, selected($current_user, $user->ID, false), esc_html($user->display_name)); } echo '</select>'; submit_button('Filter', 'secondary', 'filter_action', false); echo '</div>'; } }
        protected function column_default( $item, $column_name ) { switch ( $column_name ) { case 'type': return esc_html( ucfirst( $item->$column_name ) ); case 'price': return '$' . number_format( $item->price, 2 ); default: return esc_html($item->$column_name); } }
        protected function column_user_id($item) { return esc_html($item->display_name ?: 'N/A'); }
        protected function column_paid_with_coin($item) { return number_format($item->paid_coin_amount, 6) . ' ' . esc_html($item->paid_with_coin); }
        protected function column_item_name($item) { $edit_url = admin_url('admin.php?page=cwm-purchase-history&action=edit&id=' . $item->id); $actions = [ 'edit' => sprintf('<a href="%s">Edit</a>', esc_url($edit_url)) ]; return esc_html($item->item_name) . $this->row_actions($actions); }
        protected function column_transaction_date($item) { $nonce = wp_create_nonce('cwm_update_purchase_date_nonce'); return sprintf('<input type="datetime-local" class="cwm-date-selector" data-id="%d" data-nonce="%s" value="%s">', $item->id, $nonce, date('Y-m-d\TH:i', strtotime($item->transaction_date))); }
        protected function column_status($item) { $nonce = wp_create_nonce('cwm_update_purchase_status_nonce'); $options = ['pending', 'delivered', 'active', 'expired', 'refunded']; $html = sprintf('<select class="cwm-status-selector" data-id="%d" data-nonce="%s" %s>', $item->id, $nonce, disabled($item->status, 'refunded', false)); foreach ($options as $option) { $html .= sprintf('<option value="%s" %s>%s</option>', $option, selected($item->status, $option, false), ucfirst($option)); } $html .= '</select>'; return $html; }
        protected function column_actions($item) { if ($item->status === 'refunded') return '<span>Refunded</span>'; $nonce = wp_create_nonce('cwm_refund_nonce'); return sprintf('<button class="button button-secondary cwm-refund-button" data-id="%d" data-nonce="%s">Refund</button>', $item->id, $nonce); }
    }
}

Crypto_Wallet_Merged::get_instance();
?>
