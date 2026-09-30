<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Legacy PublishPress Taxonomies file: keep behavior unchanged while documenting existing PHPCS exceptions.

class SimpleTags_Client_Schedule
{
    public static $instance;

    public function __construct()
    {

        add_action('taxopress_cron_autoterms_weekly', [$this, 'taxopress_cron_autoterms_weekly_execution']);
        add_filter('cron_schedules', [$this, 'taxopress_weekly_cron_schedule']);
        add_action('init', [$this, 'schedule_taxopress_cron_events']);
    }

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function schedule_taxopress_cron_events()
    {

        $autoterms_schedule = taxopress_get_autoterms_schedule_data();
        $cron_schedule = isset($autoterms_schedule['cron_schedule']) ? $autoterms_schedule['cron_schedule'] : 'disable';

        if ($cron_schedule === 'weekly' && !wp_next_scheduled('taxopress_cron_autoterms_weekly')) {
            wp_schedule_event(time(), 'weekly', 'taxopress_cron_autoterms_weekly');
        }
    }

    public function taxopress_weekly_cron_schedule($schedules)
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => 604800,
                'display'  => esc_html__('Once Weekly', 'simple-tags')
            );
        }
        return $schedules;
    }

    public function taxopress_cron_autoterms_weekly_execution()
    {
        $this->taxopress_cron_autoterms_execution('weekly');
    }

    public function taxopress_cron_autoterms_execution($frequency = 'weekly')
    {
        global $wpdb;

        $autoterms_schedule = taxopress_get_autoterms_schedule_data();
        $autoterms = taxopress_get_autoterm_data();
        $autoterm_schedule_ids = isset($autoterms_schedule['autoterm_id']) ? (array)$autoterms_schedule['autoterm_id'] : [];
        $autoterm_data = [];

        foreach ($autoterm_schedule_ids as $autoterm_schedule_id) {
            if (!empty($autoterms[$autoterm_schedule_id])) {
                $autoterm_data[$autoterm_schedule_id] = $autoterms[$autoterm_schedule_id];
            }
        }
        if (empty($autoterm_data)) {
            return;
        }

        $autoterms_to_run = [];
        foreach ($autoterm_data as $id => $a) {
            if (isset($a['cron_schedule'])) {
                if ($a['cron_schedule'] === $frequency) {
                    $autoterms_to_run[$id] = $a;
                }
                continue;
            }
            $global_cron = isset($autoterms_schedule['cron_schedule']) ? $autoterms_schedule['cron_schedule'] : 'disable';
            if ($global_cron === $frequency) {
                $autoterms_to_run[$id] = $a;
            }
        }

        if (empty($autoterms_to_run)) {
            return;
        }

        $autoterm_schedule_exclude = isset($autoterms_schedule['autoterm_schedule_exclude']) ? (int)$autoterms_schedule['autoterm_schedule_exclude'] : 0;
        $limit = (isset($autoterms_schedule['schedule_terms_batches']) && (int)$autoterms_schedule['schedule_terms_batches'] > 0) ? (int)$autoterms_schedule['schedule_terms_batches'] : 20;
        $sleep = (isset($autoterms_schedule['schedule_terms_sleep']) && (int)$autoterms_schedule['schedule_terms_sleep'] > 0) ? (int)$autoterms_schedule['schedule_terms_sleep'] : 0;
        $schedule_terms_limit_days = !empty($autoterms_schedule['schedule_terms_limit_days']) ? (int)$autoterms_schedule['schedule_terms_limit_days'] : 0;

        $post_types = [];
        $post_status = [];
        foreach ($autoterms_to_run as $a) {
            if (!empty($a['post_types']) && is_array($a['post_types'])) {
                $post_types = array_unique(array_merge($post_types, $a['post_types']));
            }
            if (!empty($a['post_status']) && is_array($a['post_status'])) {
                $post_status = array_unique(array_merge($post_status, $a['post_status']));
            }
        }
        if (empty($post_types)) {
            return;
        }
        if (empty($post_status)) {
            $post_status = ['publish'];
        }

        $schedule_terms_limit_days_sql = '';
        if ($schedule_terms_limit_days > 0) {
            $schedule_terms_limit_days_sql = 'AND post_date > "' . gmdate('Y-m-d H:i:s', time() - $schedule_terms_limit_days * 86400) . '"';
        }

        // Use a per-frequency cursor so the schedule walks all eligible posts over time
        $cursor_option_name = 'taxopress_autoterms_schedule_last_id_' . $frequency;
        $last_id = (int) get_option($cursor_option_name, 0);

        $post_types = array_map('sanitize_key', (array) $post_types);
        $post_status = array_map('sanitize_key', (array) $post_status);
        $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $post_status_placeholders = implode(',', array_fill(0, count($post_status), '%s'));

        if ($autoterm_schedule_exclude > 0) {
            $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts} ";
            $sql .= "LEFT JOIN {$wpdb->postmeta} ON ( ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_taxopress_autotermed' ) ";
            $sql .= "WHERE post_type IN ({$post_types_placeholders}) ";
            $sql .= "AND {$wpdb->postmeta}.post_id IS NULL ";
            $sql .= "AND post_status IN ({$post_status_placeholders}) ";
            if (!empty($schedule_terms_limit_days_sql)) {
                $sql .= " {$schedule_terms_limit_days_sql} ";
            }
            if ($last_id > 0) {
                $sql .= " AND ID < %d";
            }
            $sql .= " ORDER BY ID DESC LIMIT %d";

            $prepare_args = array_merge($post_types, $post_status);
            if ($last_id > 0) {
                $prepare_args[] = $last_id;
            }
            $prepare_args[] = $limit;

            $objects = (array) $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
        } else {
            $sql = "SELECT ID, post_title, post_content FROM {$wpdb->posts} ";
            $sql .= "WHERE post_type IN ({$post_types_placeholders}) ";
            $sql .= "AND post_status IN ({$post_status_placeholders}) ";
            if (!empty($schedule_terms_limit_days_sql)) {
                $sql .= " {$schedule_terms_limit_days_sql} ";
            }
            if ($last_id > 0) {
                $sql .= " AND ID < %d";
            }
            $sql .= " ORDER BY ID DESC LIMIT %d";

            $prepare_args = array_merge($post_types, $post_status);
            if ($last_id > 0) {
                $prepare_args[] = $last_id;
            }
            $prepare_args[] = $limit;

            $objects = (array) $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
        }

        if (empty($objects)) {
            delete_option($cursor_option_name);
            return;
        }

        $current_post = 0;
        $min_id_in_batch = null;
        foreach ($objects as $object) {
            $current_post++;
            update_post_meta($object->ID, '_taxopress_autotermed', 1);
            $post_object = get_post($object->ID);
            if ($min_id_in_batch === null || (int) $object->ID < $min_id_in_batch) {
                $min_id_in_batch = (int) $object->ID;
            }

            foreach ($autoterms_to_run as $autoterm) {
                if (empty($autoterm['autoterm_for_schedule'])) {
                    continue;
                }

                $applied = $autoterm;
                if (isset($autoterm['schedule_terms_limit'])) {
                    $applied['terms_limit'] = $autoterm['schedule_terms_limit'];
                }
                if (isset($autoterm['schedule_autoterm_target'])) {
                    $applied['autoterm_target'] = $autoterm['schedule_autoterm_target'];
                }
                if (isset($autoterm['schedule_autoterm_word'])) {
                    $applied['autoterm_word'] = $autoterm['schedule_autoterm_word'];
                }
                if (isset($autoterm['schedule_autoterm_hash'])) {
                    $applied['autoterm_hash'] = $autoterm['schedule_autoterm_hash'];
                }
                if (isset($autoterm['schedule_replace_type'])) {
                    $applied['replace_type'] = $autoterm['schedule_replace_type'];
                }

                SimpleTags_Client_Autoterms::auto_terms_post(
                    $post_object,
                    isset($applied['taxonomy']) ? $applied['taxonomy'] : '',
                    $applied,
                    true,
                    $frequency . '_cron_schedule',
                    'st_autoterms'
                );
            }

            unset($object);

            if ($sleep > 0 && $current_post % $limit == 0) {
                sleep($sleep);
            }
        }

        if (!empty($min_id_in_batch)) {
            update_option($cursor_option_name, (int) $min_id_in_batch);
        }
    }
}
