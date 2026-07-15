<?php
/**
 * Plugin Name:       WooCommerce Order Status Change Log
 * Plugin URI:        https://github.com/amirrezashf/WooCommerce-Order-Status-Change-Log
 * Description:       Logs WooCommerce order status changes and displays recent activity in the WordPress admin panel.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Amirreza Shayesteh Far
 * Author URI:        https://amirrezaa.ir/
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woocommerce-order-status-change-log
 *
 * @package WooCommerceOrderStatusChangeLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Order_Status_Change_Log' ) ) {

	/**
	 * Main plugin class.
	 */
	final class WC_Order_Status_Change_Log {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		private const VERSION = '1.0.0';

		/**
		 * Database version.
		 *
		 * @var string
		 */
		private const DB_VERSION = '1.0.0';

		/**
		 * Database version option.
		 *
		 * @var string
		 */
		private const DB_VERSION_OPTION = 'wc_oscl_db_version';

		/**
		 * Admin menu slug.
		 *
		 * @var string
		 */
		private const MENU_SLUG = 'wc-order-status-change-log';

		/**
		 * Cleanup cron hook.
		 *
		 * @var string
		 */
		private const CLEANUP_HOOK = 'wc_oscl_cleanup_old_logs';

		/**
		 * Admin style handle.
		 *
		 * @var string
		 */
		private const STYLE_HANDLE = 'wc-order-status-change-log-admin';

		/**
		 * Singleton instance.
		 *
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * Admin page hook.
		 *
		 * @var string
		 */
		private $page_hook = '';

		/**
		 * Get plugin instance.
		 *
		 * @return self
		 */
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
			register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
			add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		}

		/**
		 * Activate plugin.
		 *
		 * @return void
		 */
		public static function activate(): void {
			self::create_database_table();

			if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
				wp_schedule_event(
					time() + HOUR_IN_SECONDS,
					'daily',
					self::CLEANUP_HOOK
				);
			}
		}

		/**
		 * Deactivate plugin.
		 *
		 * @return void
		 */
		public static function deactivate(): void {
			$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
			}
		}

		/**
		 * Create or update database table.
		 *
		 * @return void
		 */
		private static function create_database_table(): void {
			global $wpdb;

			$table_name      = self::get_table_name();
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL,
				old_status varchar(100) NOT NULL DEFAULT '',
				new_status varchar(100) NOT NULL DEFAULT '',
				changed_at datetime NOT NULL,
				changed_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				changed_by_name varchar(190) NOT NULL DEFAULT '',
				changed_by_type varchar(30) NOT NULL DEFAULT 'system',
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY new_status (new_status),
				KEY changed_at (changed_at),
				KEY changed_by_user_id (changed_by_user_id)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta( $sql );

			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}

		/**
		 * Get table name.
		 *
		 * @return string
		 */
		private static function get_table_name(): string {
			global $wpdb;

			return $wpdb->prefix . 'wc_order_status_logs';
		}

		/**
		 * Declare WooCommerce HPOS compatibility.
		 *
		 * @return void
		 */
		public function declare_hpos_compatibility(): void {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					__FILE__,
					true
				);
			}
		}

		/**
		 * Initialize plugin.
		 *
		 * @return void
		 */
		public function init(): void {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
				return;
			}

			$this->maybe_upgrade_database();

			add_action(
				'woocommerce_order_status_changed',
				array( $this, 'log_order_status_change' ),
				10,
				4
			);

			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
			add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_logs' ) );
		}

		/**
		 * Upgrade database when required.
		 *
		 * @return void
		 */
		private function maybe_upgrade_database(): void {
			$installed_version = (string) get_option( self::DB_VERSION_OPTION, '' );

			if ( self::DB_VERSION !== $installed_version ) {
				self::create_database_table();
			}
		}

		/**
		 * Render missing WooCommerce notice.
		 *
		 * @return void
		 */
		public function render_missing_woocommerce_notice(): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					echo esc_html__(
						'WooCommerce Order Status Change Log requires WooCommerce to be installed and active.',
						'woocommerce-order-status-change-log'
					);
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Register admin submenu.
		 *
		 * @return void
		 */
		public function register_admin_menu(): void {
			$this->page_hook = add_submenu_page(
				'woocommerce',
				__( 'گزارش تغییر وضعیت سفارش‌ها', 'woocommerce-order-status-change-log' ),
				__( 'لاگ وضعیت سفارش‌ها', 'woocommerce-order-status-change-log' ),
				$this->get_required_capability(),
				self::MENU_SLUG,
				array( $this, 'render_admin_page' )
			);
		}

		/**
		 * Enqueue admin styles.
		 *
		 * @param string $hook_suffix Current admin page hook.
		 *
		 * @return void
		 */
		public function enqueue_admin_styles( string $hook_suffix ): void {
			if ( $hook_suffix !== $this->page_hook ) {
				return;
			}

			wp_register_style(
				self::STYLE_HANDLE,
				false,
				array(),
				self::VERSION
			);

			wp_enqueue_style( self::STYLE_HANDLE );
			wp_add_inline_style( self::STYLE_HANDLE, $this->get_admin_css() );
		}

		/**
		 * Log order status change.
		 *
		 * @param int      $order_id  Order ID.
		 * @param string   $old_status Previous status.
		 * @param string   $new_status New status.
		 * @param WC_Order $order      Order object.
		 *
		 * @return void
		 */
		public function log_order_status_change(
			int $order_id,
			string $old_status,
			string $new_status,
			$order
		): void {
			global $wpdb;

			$order_id = absint( $order_id );

			if ( ! $order_id || $old_status === $new_status ) {
				return;
			}

			$actor = $this->get_change_actor();

			$data = array(
				'order_id'          => $order_id,
				'old_status'        => sanitize_key( $old_status ),
				'new_status'        => sanitize_key( $new_status ),
				'changed_at'        => current_time( 'mysql', true ),
				'changed_by_user_id'=> $actor['user_id'],
				'changed_by_name'   => $actor['name'],
				'changed_by_type'   => $actor['type'],
			);

			/**
			 * Filters order status log data before insertion.
			 *
			 * @param array<string,mixed> $data       Log data.
			 * @param WC_Order|null       $order      Order object.
			 * @param string              $old_status Previous status.
			 * @param string              $new_status New status.
			 */
			$data = (array) apply_filters(
				'wc_oscl_log_data',
				$data,
				$order,
				$old_status,
				$new_status
			);

			$wpdb->insert(
				self::get_table_name(),
				$data,
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
				)
			);
		}

		/**
		 * Determine who changed the order status.
		 *
		 * @return array{user_id:int,name:string,type:string}
		 */
		private function get_change_actor(): array {
			$user = wp_get_current_user();

			if ( $user instanceof WP_User && $user->exists() ) {
				return array(
					'user_id' => absint( $user->ID ),
					'name'    => sanitize_text_field(
						$user->display_name ?: $user->user_login
					),
					'type'    => 'user',
				);
			}

			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return array(
					'user_id' => 0,
					'name'    => __( 'REST API / Webhook', 'woocommerce-order-status-change-log' ),
					'type'    => 'api',
				);
			}

			if ( wp_doing_cron() ) {
				return array(
					'user_id' => 0,
					'name'    => __( 'وظیفه زمان‌بندی‌شده', 'woocommerce-order-status-change-log' ),
					'type'    => 'cron',
				);
			}

			return array(
				'user_id' => 0,
				'name'    => __( 'سیستم', 'woocommerce-order-status-change-log' ),
				'type'    => 'system',
			);
		}

		/**
		 * Render admin report page.
		 *
		 * @return void
		 */
		public function render_admin_page(): void {
			if ( ! current_user_can( $this->get_required_capability() ) ) {
				wp_die(
					esc_html__(
						'شما اجازه دسترسی به این صفحه را ندارید.',
						'woocommerce-order-status-change-log'
					)
				);
			}

			$hours        = $this->get_selected_hours();
			$current_page = $this->get_current_page_number();
			$per_page     = $this->get_items_per_page();
			$offset       = ( $current_page - 1 ) * $per_page;

			$total_logs = $this->get_logs_count( $hours );
			$logs       = $this->get_logs( $hours, $per_page, $offset );
			$total_pages = max( 1, (int) ceil( $total_logs / $per_page ) );
			?>
			<div class="wrap wc-oscl-wrap" dir="rtl">
				<div class="wc-oscl-header">
					<div>
						<h1>
							<?php
							echo esc_html__(
								'گزارش تغییر وضعیت سفارش‌ها',
								'woocommerce-order-status-change-log'
							);
							?>
						</h1>

						<p>
							<?php
							echo esc_html__(
								'آخرین تغییرات وضعیت سفارش‌های ووکامرس را همراه با زمان و عامل تغییر مشاهده کنید.',
								'woocommerce-order-status-change-log'
							);
							?>
						</p>
					</div>

					<div class="wc-oscl-count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: Number of logs. */
								__( '%s تغییر ثبت‌شده', 'woocommerce-order-status-change-log' ),
								number_format_i18n( $total_logs )
							)
						);
						?>
					</div>
				</div>

				<form method="get" class="wc-oscl-filters">
					<input
						type="hidden"
						name="page"
						value="<?php echo esc_attr( self::MENU_SLUG ); ?>"
					>

					<label for="wc-oscl-hours">
						<?php
						echo esc_html__(
							'بازه زمانی',
							'woocommerce-order-status-change-log'
						);
						?>
					</label>

					<select id="wc-oscl-hours" name="hours">
						<option value="24" <?php selected( $hours, 24 ); ?>>
							<?php esc_html_e( '۲۴ ساعت اخیر', 'woocommerce-order-status-change-log' ); ?>
						</option>

						<option value="72" <?php selected( $hours, 72 ); ?>>
							<?php esc_html_e( '۷۲ ساعت اخیر', 'woocommerce-order-status-change-log' ); ?>
						</option>

						<option value="168" <?php selected( $hours, 168 ); ?>>
							<?php esc_html_e( '۷ روز اخیر', 'woocommerce-order-status-change-log' ); ?>
						</option>

						<option value="720" <?php selected( $hours, 720 ); ?>>
							<?php esc_html_e( '۳۰ روز اخیر', 'woocommerce-order-status-change-log' ); ?>
						</option>
					</select>

					<button type="submit" class="button button-primary">
						<?php
						echo esc_html__(
							'اعمال فیلتر',
							'woocommerce-order-status-change-log'
						);
						?>
					</button>
				</form>

				<div class="wc-oscl-table-wrap">
					<table class="widefat striped wc-oscl-table">
						<thead>
							<tr>
								<th class="wc-oscl-number">
									<?php esc_html_e( 'ردیف', 'woocommerce-order-status-change-log' ); ?>
								</th>

								<th>
									<?php esc_html_e( 'سفارش', 'woocommerce-order-status-change-log' ); ?>
								</th>

								<th>
									<?php esc_html_e( 'وضعیت قبلی', 'woocommerce-order-status-change-log' ); ?>
								</th>

								<th>
									<?php esc_html_e( 'وضعیت جدید', 'woocommerce-order-status-change-log' ); ?>
								</th>

								<th>
									<?php esc_html_e( 'زمان تغییر', 'woocommerce-order-status-change-log' ); ?>
								</th>

								<th>
									<?php esc_html_e( 'توسط', 'woocommerce-order-status-change-log' ); ?>
								</th>
							</tr>
						</thead>

						<tbody>
							<?php if ( empty( $logs ) ) : ?>
								<tr>
									<td colspan="6" class="wc-oscl-empty">
										<?php
										echo esc_html__(
											'در بازه زمانی انتخاب‌شده تغییری ثبت نشده است.',
											'woocommerce-order-status-change-log'
										);
										?>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $logs as $index => $log ) : ?>
									<?php
									$this->render_log_row(
										$log,
										$offset + $index + 1
									);
									?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php
				$this->render_pagination(
					$current_page,
					$total_pages,
					$hours
				);
				?>
			</div>
			<?php
		}

		/**
		 * Render one log row.
		 *
		 * @param object $log    Database log.
		 * @param int    $number Row number.
		 *
		 * @return void
		 */
		private function render_log_row( object $log, int $number ): void {
			$order_id = isset( $log->order_id )
				? absint( $log->order_id )
				: 0;

			$old_status = isset( $log->old_status )
				? sanitize_key( $log->old_status )
				: '';

			$new_status = isset( $log->new_status )
				? sanitize_key( $log->new_status )
				: '';

			$changed_at = isset( $log->changed_at )
				? (string) $log->changed_at
				: '';

			$user_id = isset( $log->changed_by_user_id )
				? absint( $log->changed_by_user_id )
				: 0;

			$changed_by = isset( $log->changed_by_name )
				? (string) $log->changed_by_name
				: '';

			$order_url = $this->get_order_edit_url( $order_id );
			$user_url  = $user_id ? get_edit_user_link( $user_id ) : '';

			$local_date = $changed_at
				? get_date_from_gmt( $changed_at )
				: '';

			$formatted_date = $local_date
				? date_i18n(
					get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
					strtotime( $local_date )
				)
				: '—';
			?>
			<tr>
				<td class="wc-oscl-number">
					<?php echo esc_html( number_format_i18n( $number ) ); ?>
				</td>

				<td>
					<?php if ( $order_url ) : ?>
						<a
							href="<?php echo esc_url( $order_url ); ?>"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: Order ID. */
									__( 'سفارش #%d', 'woocommerce-order-status-change-log' ),
									$order_id
								)
							);
							?>
						</a>
					<?php else : ?>
						<?php echo esc_html( '#' . $order_id ); ?>
					<?php endif; ?>
				</td>

				<td>
					<span class="wc-oscl-status wc-oscl-status-old">
						<?php echo esc_html( wc_get_order_status_name( $old_status ) ); ?>
					</span>
				</td>

				<td>
					<span class="wc-oscl-status wc-oscl-status-new">
						<?php echo esc_html( wc_get_order_status_name( $new_status ) ); ?>
					</span>
				</td>

				<td>
					<?php echo esc_html( $formatted_date ); ?>
				</td>

				<td>
					<?php if ( $user_url ) : ?>
						<a
							href="<?php echo esc_url( $user_url ); ?>"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php echo esc_html( $changed_by ?: __( 'کاربر', 'woocommerce-order-status-change-log' ) ); ?>
						</a>
					<?php else : ?>
						<?php
						echo esc_html(
							$changed_by ?: __( 'نامشخص', 'woocommerce-order-status-change-log' )
						);
						?>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}

		/**
		 * Get logs.
		 *
		 * @param int $hours    Report period in hours.
		 * @param int $per_page Rows per page.
		 * @param int $offset   Query offset.
		 *
		 * @return array<int,object>
		 */
		private function get_logs(
			int $hours,
			int $per_page,
			int $offset
		): array {
			global $wpdb;

			$since = gmdate(
				'Y-m-d H:i:s',
				time() - ( $hours * HOUR_IN_SECONDS )
			);

			$sql = $wpdb->prepare(
				'SELECT
					id,
					order_id,
					old_status,
					new_status,
					changed_at,
					changed_by_user_id,
					changed_by_name,
					changed_by_type
				FROM ' . self::get_table_name() . '
				WHERE changed_at >= %s
				ORDER BY changed_at DESC, id DESC
				LIMIT %d OFFSET %d',
				$since,
				$per_page,
				$offset
			);

			$results = $wpdb->get_results( $sql );

			return is_array( $results ) ? $results : array();
		}

		/**
		 * Get total logs count.
		 *
		 * @param int $hours Report period in hours.
		 *
		 * @return int
		 */
		private function get_logs_count( int $hours ): int {
			global $wpdb;

			$since = gmdate(
				'Y-m-d H:i:s',
				time() - ( $hours * HOUR_IN_SECONDS )
			);

			$sql = $wpdb->prepare(
				'SELECT COUNT(*)
				FROM ' . self::get_table_name() . '
				WHERE changed_at >= %s',
				$since
			);

			return absint( $wpdb->get_var( $sql ) );
		}

		/**
		 * Render pagination.
		 *
		 * @param int $current_page Current page.
		 * @param int $total_pages  Total pages.
		 * @param int $hours        Selected period.
		 *
		 * @return void
		 */
		private function render_pagination(
			int $current_page,
			int $total_pages,
			int $hours
		): void {
			if ( $total_pages <= 1 ) {
				return;
			}

			$links = paginate_links(
				array(
					'base'      => add_query_arg(
						array(
							'page'  => self::MENU_SLUG,
							'hours' => $hours,
							'paged' => '%#%',
						),
						admin_url( 'admin.php' )
					),
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'list',
					'prev_text' => __( 'قبلی', 'woocommerce-order-status-change-log' ),
					'next_text' => __( 'بعدی', 'woocommerce-order-status-change-log' ),
				)
			);

			if ( ! $links ) {
				return;
			}

			echo '<nav class="wc-oscl-pagination">';
			echo wp_kses_post( $links );
			echo '</nav>';
		}

		/**
		 * Get order edit URL for HPOS or legacy storage.
		 *
		 * @param int $order_id Order ID.
		 *
		 * @return string
		 */
		private function get_order_edit_url( int $order_id ): string {
			if ( ! $order_id ) {
				return '';
			}

			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				return \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url(
					$order_id
				);
			}

			return admin_url(
				'post.php?post=' . $order_id . '&action=edit'
			);
		}

		/**
		 * Remove old logs.
		 *
		 * @return void
		 */
		public function cleanup_old_logs(): void {
			global $wpdb;

			$retention_days = $this->get_retention_days();

			if ( $retention_days <= 0 ) {
				return;
			}

			$before = gmdate(
				'Y-m-d H:i:s',
				time() - ( $retention_days * DAY_IN_SECONDS )
			);

			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM ' . self::get_table_name() . '
					WHERE changed_at < %s',
					$before
				)
			);
		}

		/**
		 * Get required capability.
		 *
		 * @return string
		 */
		private function get_required_capability(): string {
			/**
			 * Filters required capability.
			 *
			 * @param string $capability Required capability.
			 */
			return (string) apply_filters(
				'wc_oscl_required_capability',
				'manage_woocommerce'
			);
		}

		/**
		 * Get selected report period.
		 *
		 * @return int
		 */
		private function get_selected_hours(): int {
			$allowed = array( 24, 72, 168, 720 );

			$hours = isset( $_GET['hours'] )
				? absint( $_GET['hours'] )
				: 72;

			return in_array( $hours, $allowed, true ) ? $hours : 72;
		}

		/**
		 * Get current page number.
		 *
		 * @return int
		 */
		private function get_current_page_number(): int {
			return isset( $_GET['paged'] )
				? max( 1, absint( $_GET['paged'] ) )
				: 1;
		}

		/**
		 * Get items per page.
		 *
		 * @return int
		 */
		private function get_items_per_page(): int {
			/**
			 * Filters report rows per page.
			 *
			 * @param int $per_page Rows per page.
			 */
			$per_page = (int) apply_filters(
				'wc_oscl_items_per_page',
				50
			);

			return min( 200, max( 10, $per_page ) );
		}

		/**
		 * Get log retention duration.
		 *
		 * @return int
		 */
		private function get_retention_days(): int {
			/**
			 * Filters log retention in days.
			 *
			 * Return zero to disable automatic cleanup.
			 *
			 * @param int $days Retention days.
			 */
			$days = (int) apply_filters(
				'wc_oscl_retention_days',
				180
			);

			return max( 0, $days );
		}

		/**
		 * Get admin CSS.
		 *
		 * @return string
		 */
		private function get_admin_css(): string {
			return '
.wc-oscl-wrap {
	max-width: 1500px;
}

.wc-oscl-header {
	align-items: center;
	display: flex;
	gap: 20px;
	justify-content: space-between;
	margin: 18px 0;
}

.wc-oscl-header h1 {
	margin-bottom: 6px;
}

.wc-oscl-header p {
	color: #646970;
	margin: 0;
}

.wc-oscl-count {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	font-weight: 600;
	padding: 9px 13px;
	white-space: nowrap;
}

.wc-oscl-filters {
	align-items: center;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin-bottom: 18px;
	padding: 12px;
}

.wc-oscl-filters label {
	font-weight: 600;
}

.wc-oscl-table-wrap {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	overflow-x: auto;
}

.wc-oscl-table {
	border: 0;
	min-width: 900px;
}

.wc-oscl-table th,
.wc-oscl-table td {
	padding: 12px;
	text-align: right;
	vertical-align: middle;
}

.wc-oscl-number {
	text-align: center !important;
	width: 70px;
}

.wc-oscl-status {
	border-radius: 999px;
	display: inline-flex;
	font-size: 12px;
	font-weight: 600;
	padding: 5px 10px;
}

.wc-oscl-status-old {
	background: #f0f0f1;
	color: #50575e;
}

.wc-oscl-status-new {
	background: #edfaef;
	color: #157d00;
}

.wc-oscl-empty {
	color: #646970;
	padding: 30px !important;
	text-align: center !important;
}

.wc-oscl-pagination {
	margin: 18px 0;
}

.wc-oscl-pagination .page-numbers {
	display: flex;
	flex-wrap: wrap;
	gap: 5px;
	margin: 0;
}

.wc-oscl-pagination .page-numbers li {
	margin: 0;
}

.wc-oscl-pagination a,
.wc-oscl-pagination span {
	align-items: center;
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 5px;
	display: inline-flex;
	justify-content: center;
	min-height: 34px;
	min-width: 34px;
	padding: 0 8px;
	text-decoration: none;
}

.wc-oscl-pagination .current {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}

@media (max-width: 782px) {
	.wc-oscl-header {
		align-items: stretch;
		flex-direction: column;
	}

	.wc-oscl-count {
		white-space: normal;
	}

	.wc-oscl-filters {
		align-items: stretch;
		flex-direction: column;
	}

	.wc-oscl-filters select,
	.wc-oscl-filters .button {
		width: 100%;
	}
}
';
		}
	}
}

WC_Order_Status_Change_Log::instance();
