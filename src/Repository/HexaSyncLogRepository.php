<?php
/**
 * HexaSyncLogRepository - Repository for managing HexaSync Log data
 *
 * @package Beehexa\Repository
 */

namespace Beehexa\Repository;

class HexaSyncLogRepository {


    /**
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'hexasync_log';
    }

    /**
     * Get all logs
     *
     * @param array $args Optional query arguments
     * @return \Beehexa\DTO\HexaSyncLogDTO[]
     */
    public function getAll($args = []) {
        global $wpdb;
        $defaults = [
            'order' => 'DESC',
            'orderby' => 'log_id',
            'limit' => -1,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);
        $orderBy = esc_sql($args['orderby']);
        $order = esc_sql($args['order']);
        $limit = esc_sql($args['limit']);
        $offset = esc_sql($args['offset']);
        $query = "SELECT * FROM %i ORDER BY {$orderBy} {$order}";

        if ($args['limit'] > 0) {
            $query .= " LIMIT {$limit}";
            if ($args['offset'] > 0) {
                $query .= " OFFSET {$offset}";
            }
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results($wpdb->prepare($query, $this->table_name));

        if (empty($results)) {
            return [];
        }

        return array_map(function($row) {
            return $this->hydrateDTO((array)$row);
        }, $results);
    }

    /**
     * Get log by ID
     *
     * @param int $id
     * @return \Beehexa\DTO\HexaSyncLogDTO|null
     */
    public function getById($id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM %i WHERE log_id = %d",
            $this->table_name, $id
        ));
        if (empty($result)) {
            return null;
        }

        return $this->hydrateDTO((array)$result);
    }

    /**
     * Get logs by profile name
     *
     * @param string $profile_name
     * @return \Beehexa\DTO\HexaSyncLogDTO[]
     */
    public function getByProfileName($profile_name) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder,WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE profile_name like %s order by log_id desc",
                    $this->table_name, '%' . $profile_name . '%'
                ));
        if (empty($results)) {
            return [];
        }

        return array_map(function($row) {
            return $this->hydrateDTO((array)$row);
        }, $results);
    }

    /**
     * Get logs by profile name
     *
     * @param string $profile_name
     * @return \Beehexa\DTO\HexaSyncLogDTO[]
     */
    public function getByProfileAndTaskName($profile_name, $task_name) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder,WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE profile_name like %s and task_name like %s order by log_id desc",
                    $this->table_name, '%' . $profile_name . '%', '%' . $task_name . '%'
                ));

        if (empty($results)) {
            return [];
        }

        return array_map(function($row) {
            return $this->hydrateDTO((array)$row);
        }, $results);
    }

    /**
     * Get logs by task name
     *
     * @param string $task_name
     * @return \Beehexa\DTO\HexaSyncLogDTO[]
     */
    public function getByTaskName($task_name) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder,WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE task_name like %s order by log_id desc",
                    $this->table_name, '%' . $task_name . '%'
                ));

        if (empty($results)) {
            return [];
        }

        return array_map(function($row) {
            return $this->hydrateDTO((array)$row);
        }, $results);
    }

    /**
     * Save log (insert or update)
     *
     * @param \Beehexa\DTO\HexaSyncLogDTO $dto
     * @return int|false Log ID on success, false on failure
     */
    public function save(\Beehexa\DTO\HexaSyncLogDTO $dto) {
        global $wpdb;
        $data = [
            'message' => $dto->getMessage(),
            'profile_id' => $dto->getProfileId(),
            'profile_name' => $dto->getProfileName(),
            'action_type' => $dto->getActionType(),
            'log_detail_id' => $dto->getLogDetailId(),
            'reference_info' => $dto->getReferenceInfo(),
            'error' => $dto->getError(),
            'task_id' => $dto->getTaskId(),
            'task_name' => $dto->getTaskName(),
            'task_status' => $dto->getTaskStatus(),
            'executed_at' => $dto->getExecutedAt(),
            'execute_at' => $dto->getExecuteAt(),
            'retry_count' => $dto->getRetryCount(),
            'push_note' => $dto->getPushNote(),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'];

        if ($dto->getId()) {
            // Update existing
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->update(
                $this->table_name,
                $data,
                ['log_id' => $dto->getId()],
                $format,
                ['%d']
            );

            return $result !== false ? $dto->getId() : false;
        } else {
            // Insert new
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                $format
            );

            return $result !== false ? $wpdb->insert_id : false;
        }
    }

    /**
     * Delete log by ID
     *
     * @param int $id
     * @return int|false Number of rows deleted, false on failure
     */
    public function delete($id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
        return $wpdb->delete(
            $this->table_name,
            ['log_id' => $id],
            ['%d']
        );
    }

    /**
     * Get total count of logs
     *
     * @return int
     */
    public function getCount() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder,WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM %i", $this->table_name);
    }

    /**
     * Hydrate DTO from array
     *
     * @param array $data
     * @return \Beehexa\DTO\HexaSyncLogDTO
     */
    private function hydrateDTO($data) {
        return \Beehexa\DTO\HexaSyncLogDTO::fromArray($data);
    }
}

