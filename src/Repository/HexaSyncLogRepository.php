<?php
/**
 * HexaSyncLogRepository - Repository for managing HexaSync Log data
 *
 * @package Beehexa\Repository
 */

namespace Beehexa\Repository;

class HexaSyncLogRepository {

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'hexasync_log';
    }

    /**
     * Get all logs
     *
     * @param array $args Optional query arguments
     * @return \Beehexa\DTO\HexaSyncLogDTO[]
     */
    public function getAll($args = []) {
        $defaults = [
            'order' => 'DESC',
            'orderby' => 'log_id',
            'limit' => -1,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $query = "SELECT log_id, message, profile_id, profile_name, action_type, log_detail_id, reference_info, error, task_id, task_name, task_status, executed_at, execute_at, retry_count, push_note, created_at, updated_at FROM {$this->table_name}";
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";

        if ($args['limit'] > 0) {
            $query .= " LIMIT {$args['limit']}";
            if ($args['offset'] > 0) {
                $query .= " OFFSET {$args['offset']}";
            }
        }

        $results = $this->wpdb->get_results($query);

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
        $query = $this->wpdb->prepare(
            "SELECT log_id, message, profile_id, profile_name, action_type, log_detail_id, reference_info, error, task_id, task_name, task_status, executed_at, execute_at, retry_count, push_note, created_at, updated_at FROM {$this->table_name} WHERE log_id = %d",
            $id
        );

        $result = $this->wpdb->get_row($query);

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
        $query = $this->wpdb->prepare(
            "SELECT log_id, message, profile_id, profile_name, action_type, log_detail_id, reference_info, error, task_id, task_name, task_status, executed_at, execute_at, retry_count, push_note, created_at, updated_at FROM {$this->table_name} WHERE profile_name = %s",
            $profile_name
        );

        $results = $this->wpdb->get_results($query);

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
        $query = $this->wpdb->prepare(
            "SELECT log_id, message, profile_id, profile_name, action_type, log_detail_id, reference_info, error, task_id, task_name, task_status, executed_at, execute_at, retry_count, push_note, created_at, updated_at FROM {$this->table_name} WHERE task_name = %s",
            $task_name
        );

        $results = $this->wpdb->get_results($query);

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
            $result = $this->wpdb->update(
                $this->table_name,
                $data,
                ['log_id' => $dto->getId()],
                $format,
                ['%d']
            );

            return $result !== false ? $dto->getId() : false;
        } else {
            // Insert new
            $result = $this->wpdb->insert(
                $this->table_name,
                $data,
                $format
            );

            return $result !== false ? $this->wpdb->insert_id : false;
        }
    }

    /**
     * Delete log by ID
     *
     * @param int $id
     * @return int|false Number of rows deleted, false on failure
     */
    public function delete($id) {
        return $this->wpdb->delete(
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
        $query = "SELECT COUNT(*) FROM {$this->table_name}";
        return (int)$this->wpdb->get_var($query);
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

