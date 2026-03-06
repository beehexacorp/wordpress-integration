<?php
/**
 * HexaSyncLogAPI - REST API for managing HexaSync Logs
 *
 * @package Beehexa\API
 */

namespace Beehexa\API;

use Beehexa\Repository\HexaSyncLogRepository;
use Beehexa\DTO\HexaSyncLogDTO;

class HexaSyncLogAPI {

    /**
     * @var HexaSyncLogRepository
     */
    private $repository;

    /**
     * API namespace
     */
    const NAMESPACE = 'beehexa/v1/hexasync';

    /**
     * API endpoint base
     */
    const ENDPOINT = '/log';

    /**
     * Constructor
     */
    public function __construct() {
        $this->repository = new HexaSyncLogRepository();
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Health check endpoint
        register_rest_route(
            self::NAMESPACE,
            '/health',
            [
                'methods' => 'GET',
                'callback' => [$this, 'health_check'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        );
        // Get all logs
        register_rest_route(
            self::NAMESPACE,
            self::ENDPOINT,
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_logs'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'page' => [
                        'description' => 'Current page of the collection',
                        'type' => 'integer',
                        'default' => 1,
                    ],
                    'per_page' => [
                        'description' => 'Maximum number of items to be returned',
                        'type' => 'integer',
                        'default' => 10,
                    ],
                    'profile_name' => [
                        'description' => 'Filter by profile name',
                        'type' => 'string',
                    ],
                    'task_name' => [
                        'description' => 'Filter by task name',
                        'type' => 'string',
                    ],
                ]
            ]
        );

        // Get single log
        register_rest_route(
            self::NAMESPACE,
            self::ENDPOINT . '/(?P<id>\d+)',
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_log'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        );

        // Create log
        register_rest_route(
            self::NAMESPACE,
            self::ENDPOINT,
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_log'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'log' => [
                        'description' => 'Log object containing all log data',
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'description' => 'Log message',
                            ],
                            'profile_id' => [
                                'type' => 'string',
                                'description' => 'Profile ID (UUID)',
                            ],
                            'profile_name' => [
                                'type' => 'string',
                                'description' => 'Profile name',
                                'required' => true,
                            ],
                            'action_type' => [
                                'type' => 'string',
                                'description' => 'Action type',
                            ],
                            'log_detail_id' => [
                                'type' => 'string',
                                'description' => 'Log detail ID (UUID)',
                            ],
                            'reference_info' => [
                                'type' => 'string',
                                'description' => 'Reference information',
                            ],
                            'error' => [
                                'type' => 'string',
                                'description' => 'Error message',
                            ],
                            'task_id' => [
                                'type' => 'string',
                                'description' => 'Task ID (UUID)',
                            ],
                            'task_name' => [
                                'type' => 'string',
                                'description' => 'Task name',
                                'required' => true,
                            ],
                            'task_status' => [
                                'type' => 'string',
                                'description' => 'Task status',
                            ],
                            'executed_at' => [
                                'type' => 'string',
                                'description' => 'Executed at timestamp',
                            ],
                            'execute_at' => [
                                'type' => 'string',
                                'description' => 'Execute at timestamp',
                            ],
                            'retry_count' => [
                                'type' => ['integer', 'string'],
                                'description' => 'Retry count',
                            ],
                            'push_note' => [
                                'type' => 'string',
                                'description' => 'Push note',
                            ],
                        ],
                    ],
                ]
            ]
        );

        // Update log
        register_rest_route(
            self::NAMESPACE,
            self::ENDPOINT . '/(?P<id>\d+)',
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_log'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'log' => [
                        'description' => 'Log object with fields to update',
                        'type' => 'object',
                    ],
                ]
            ]
        );

        // Delete log
        register_rest_route(
            self::NAMESPACE,
            self::ENDPOINT . '/(?P<id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_log'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        );
    }

    /**
     * Health check endpoint
     *
     * @return \WP_REST_Response
     */
    public function health_check() {
        return new \WP_REST_Response([
            'status' => 'ok',
            'message' => 'HexaSync API is running',
        ], 200);
    }

    /**
     * Get all logs
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_logs($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $profile_name = $request->get_param('profile_name');
        $task_name = $request->get_param('task_name');

        // Get logs based on filters
        if ($profile_name) {
            $logs = $this->repository->getByProfileName($profile_name);
        } elseif ($task_name) {
            $logs = $this->repository->getByTaskName($task_name);
        } else {
            $logs = $this->repository->getAll();
        }

        // Apply pagination
        $total = count($logs);
        $offset = ($page - 1) * $per_page;
        $logs = array_slice($logs, $offset, $per_page);

        $response = new \WP_REST_Response([
            'data' => array_map(function($log) {
                return $log->toArray();
            }, $logs),
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page,
        ]);

        $response->set_status(200);
        return $response;
    }

    /**
     * Get single log
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_log($request) {
        $id = $request->get_param('id');
        $log = $this->repository->getById($id);

        if (!$log) {
            return new \WP_REST_Response([
                'error' => 'Log not found',
            ], 404);
        }

        return new \WP_REST_Response([
            'data' => $log->toArray(),
        ], 200);
    }

    /**
     * Create log
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_log($request) {
        // Get the log object from request
        $log_data = $request->get_param('log');

        if (empty($log_data)) {
            return new \WP_REST_Response([
                'error' => 'Missing required parameter: log object',
            ], 400);
        }

        // Extract required fields
        $profile_name = $log_data['profile_name'] ?? '';
        $task_name = $log_data['task_name'] ?? '';

        if (empty($profile_name) || empty($task_name)) {
            return new \WP_REST_Response([
                'error' => 'Missing required fields in log object: profile_name, task_name',
            ], 400);
        }

        // Create DTO with required fields
        $dto = new HexaSyncLogDTO($profile_name, $task_name);

        // Set all optional fields
        if (!empty($log_data['message'])) {
            $dto->setMessage($log_data['message']);
        }
        if (!empty($log_data['profile_id'])) {
            $dto->setProfileId($log_data['profile_id']);
        }
        if (!empty($log_data['action_type'])) {
            $dto->setActionType($log_data['action_type']);
        }
        if (!empty($log_data['log_detail_id'])) {
            $dto->setLogDetailId($log_data['log_detail_id']);
        }
        if (!empty($log_data['reference_info'])) {
            $dto->setReferenceInfo($log_data['reference_info']);
        }
        if (!empty($log_data['error'])) {
            $dto->setError($log_data['error']);
        }
        if (!empty($log_data['task_id'])) {
            $dto->setTaskId($log_data['task_id']);
        }
        if (!empty($log_data['task_status'])) {
            $dto->setTaskStatus($log_data['task_status']);
        }
        if (!empty($log_data['executed_at'])) {
            $dto->setExecutedAt($log_data['executed_at']);
        }
        if (!empty($log_data['execute_at'])) {
            $dto->setExecuteAt($log_data['execute_at']);
        }
        if (isset($log_data['retry_count']) && $log_data['retry_count'] !== '') {
            $dto->setRetryCount($log_data['retry_count']);
        }
        if (!empty($log_data['push_note'])) {
            $dto->setPushNote($log_data['push_note']);
        }

        // Save to database
        $id = $this->repository->save($dto);

        if (!$id) {
            return new \WP_REST_Response([
                'error' => 'Failed to create log',
            ], 500);
        }

        $log = $this->repository->getById($id);

        return new \WP_REST_Response($log->toArray(), 201);
    }

    /**
     * Update log
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_log($request) {
        $id = $request->get_param('id');
        $log = $this->repository->getById($id);

        if (!$log) {
            return new \WP_REST_Response([
                'error' => 'Log not found',
            ], 404);
        }

        $log_data = $request->get_param('log');

        if (empty($log_data)) {
            return new \WP_REST_Response([
                'error' => 'Missing required parameter: log object',
            ], 400);
        }

        // Update fields if provided
        if (!empty($log_data['message'])) {
            $log->setMessage($log_data['message']);
        }
        if (!empty($log_data['profile_id'])) {
            $log->setProfileId($log_data['profile_id']);
        }
        if (!empty($log_data['profile_name'])) {
            $log->setProfileName($log_data['profile_name']);
        }
        if (!empty($log_data['action_type'])) {
            $log->setActionType($log_data['action_type']);
        }
        if (!empty($log_data['log_detail_id'])) {
            $log->setLogDetailId($log_data['log_detail_id']);
        }
        if (!empty($log_data['reference_info'])) {
            $log->setReferenceInfo($log_data['reference_info']);
        }
        if (!empty($log_data['error'])) {
            $log->setError($log_data['error']);
        }
        if (!empty($log_data['task_id'])) {
            $log->setTaskId($log_data['task_id']);
        }
        if (!empty($log_data['task_name'])) {
            $log->setTaskName($log_data['task_name']);
        }
        if (!empty($log_data['task_status'])) {
            $log->setTaskStatus($log_data['task_status']);
        }
        if (!empty($log_data['executed_at'])) {
            $log->setExecutedAt($log_data['executed_at']);
        }
        if (!empty($log_data['execute_at'])) {
            $log->setExecuteAt($log_data['execute_at']);
        }
        if (isset($log_data['retry_count']) && $log_data['retry_count'] !== '') {
            $log->setRetryCount($log_data['retry_count']);
        }
        if (!empty($log_data['push_note'])) {
            $log->setPushNote($log_data['push_note']);
        }

        $result = $this->repository->save($log);

        if (!$result) {
            return new \WP_REST_Response([
                'error' => 'Failed to update log',
            ], 500);
        }

        $updated_log = $this->repository->getById($id);

        return new \WP_REST_Response($updated_log->toArray(), 200);
    }

    /**
     * Delete log
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_log($request) {
        $id = $request->get_param('id');
        $log = $this->repository->getById($id);

        if (!$log) {
            return new \WP_REST_Response([
                'error' => 'Log not found',
            ], 404);
        }

        $result = $this->repository->delete($id);

        if ($result === false) {
            return new \WP_REST_Response([
                'error' => 'Failed to delete log',
            ], 500);
        }

        return new \WP_REST_Response([
            'message' => 'Log deleted successfully',
        ], 200);
    }

    /**
     * Check if user has permission to access API
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'manage_options' );
    }
}

