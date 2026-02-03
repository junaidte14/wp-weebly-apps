<?php
/**
 * Installation script for migration tables
 * Run this ONCE before starting migration
 */

if (!defined('ABSPATH')) exit;

function wpwa_install_migration_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Archived Orders Table
    $sql1 = "CREATE TABLE `{$wpdb->prefix}wpwa_archived_orders` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `wc_order_id` BIGINT(20) UNSIGNED NOT NULL,
      `order_number` VARCHAR(50) NOT NULL,
      `customer_email` VARCHAR(255) NOT NULL,
      `customer_name` VARCHAR(255) DEFAULT NULL,
      `weebly_user_id` VARCHAR(255) DEFAULT NULL,
      `weebly_site_id` VARCHAR(255) DEFAULT NULL,
      `product_id` BIGINT(20) UNSIGNED NOT NULL,
      `product_name` VARCHAR(255) NOT NULL,
      `amount` DECIMAL(10,2) NOT NULL,
      `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
      `status` VARCHAR(50) NOT NULL,
      `weebly_notification_status` VARCHAR(50) DEFAULT NULL,
      `access_token` TEXT DEFAULT NULL,
      `final_url` TEXT DEFAULT NULL,
      `payment_method` VARCHAR(50) DEFAULT NULL,
      `transaction_id` VARCHAR(255) DEFAULT NULL,
      `order_date` DATETIME NOT NULL,
      `completed_date` DATETIME DEFAULT NULL,
      `order_metadata` LONGTEXT DEFAULT NULL,
      `order_notes` LONGTEXT DEFAULT NULL,
      `migrated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_wc_order_id` (`wc_order_id`),
      KEY `idx_customer_email` (`customer_email`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_product_id` (`product_id`),
      KEY `idx_status` (`status`),
      KEY `idx_order_date` (`order_date`)
    ) $charset_collate;";

    dbDelta($sql1);

    // Archived Subscriptions Table
    $sql2 = "CREATE TABLE `{$wpdb->prefix}wpwa_archived_subscriptions` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `wc_order_id` BIGINT(20) UNSIGNED NOT NULL,
      `customer_email` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) DEFAULT NULL,
      `weebly_site_id` VARCHAR(255) DEFAULT NULL,
      `product_id` BIGINT(20) UNSIGNED NOT NULL,
      `product_name` VARCHAR(255) NOT NULL,
      `cycle_length` INT NOT NULL,
      `cycle_unit` VARCHAR(20) NOT NULL,
      `cycle_price` DECIMAL(10,2) NOT NULL,
      `status` VARCHAR(50) NOT NULL,
      `access_token` TEXT DEFAULT NULL,
      `expiry_date` DATETIME DEFAULT NULL,
      `grace_until` DATETIME DEFAULT NULL,
      `renewal_count` INT NOT NULL DEFAULT 0,
      `subscription_metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL,
      `migrated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_wc_order` (`wc_order_id`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_status` (`status`),
      KEY `idx_expiry` (`expiry_date`)
    ) $charset_collate;";

    dbDelta($sql2);

    // Migration Log Table
    $sql3 = "CREATE TABLE `{$wpdb->prefix}wpwa_migration_log` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `batch_number` INT NOT NULL,
      `order_id` BIGINT(20) UNSIGNED NOT NULL,
      `migration_type` VARCHAR(50) NOT NULL,
      `status` VARCHAR(20) NOT NULL,
      `error_message` TEXT DEFAULT NULL,
      `migrated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_batch` (`batch_number`),
      KEY `idx_status` (`status`)
    ) $charset_collate;";

    dbDelta($sql3);

    update_option('wpwa_migration_tables_version', '1.0');
}

wpwa_install_migration_tables();
// Run on plugin activation
//register_activation_hook(WPWA_BASE_FILE, 'wpwa_install_migration_tables');