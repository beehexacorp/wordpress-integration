<?php
/**
 * HexaSyncLogDTO - Data Transfer Object for HexaSync Log entries
 *
 * @package Beehexa\DTO
 */

namespace Beehexa\DTO;

class HexaSyncLogDTO {

    /**
     * @var int|null
     */
    private $log_id;

    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $profile_id;

    /**
     * @var string
     */
    private $profile_name;

    /**
     * @var string
     */
    private $action_type;

    /**
     * @var string
     */
    private $log_detail_id;

    /**
     * @var string
     */
    private $reference_info;

    /**
     * @var string
     */
    private $error;

    /**
     * @var string
     */
    private $task_id;

    /**
     * @var string
     */
    private $task_name;

    /**
     * @var string
     */
    private $task_status;

    /**
     * @var string
     */
    private $executed_at;

    /**
     * @var string
     */
    private $execute_at;

    /**
     * @var int
     */
    private $retry_count;

    /**
     * @var string
     */
    private $push_note;

    /**
     * @var string
     */
    private $created_at;

    /**
     * @var string
     */
    private $updated_at;

    /**
     * Constructor
     *
     * @param string $profile_name
     * @param string $task_name
     * @param int|null $log_id
     */
    public function __construct($profile_name, $task_name, $log_id = null) {
        $this->profile_name = $profile_name;
        $this->task_name = $task_name;
        $this->log_id = $log_id;
        $this->retry_count = 0;
    }

    /**
     * Get ID
     *
     * @return int|null
     */
    public function getId() {
        return $this->log_id;
    }

    /**
     * Set ID
     *
     * @param int $log_id
     * @return $this
     */
    public function setId($log_id) {
        $this->log_id = $log_id;
        return $this;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage() {
        return $this->message ?? '';
    }

    /**
     * Set message
     *
     * @param string $message
     * @return $this
     */
    public function setMessage($message) {
        $this->message = $message;
        return $this;
    }

    /**
     * Get profile ID
     *
     * @return string
     */
    public function getProfileId() {
        return $this->profile_id ?? '';
    }

    /**
     * Set profile ID
     *
     * @param string $profile_id
     * @return $this
     */
    public function setProfileId($profile_id) {
        $this->profile_id = $profile_id;
        return $this;
    }

    /**
     * Get profile name
     *
     * @return string
     */
    public function getProfileName() {
        return $this->profile_name;
    }

    /**
     * Set profile name
     *
     * @param string $profile_name
     * @return $this
     */
    public function setProfileName($profile_name) {
        $this->profile_name = $profile_name;
        return $this;
    }

    /**
     * Get action type
     *
     * @return string
     */
    public function getActionType() {
        return $this->action_type ?? '';
    }

    /**
     * Set action type
     *
     * @param string $action_type
     * @return $this
     */
    public function setActionType($action_type) {
        $this->action_type = $action_type;
        return $this;
    }

    /**
     * Get log detail ID
     *
     * @return string
     */
    public function getLogDetailId() {
        return $this->log_detail_id ?? '';
    }

    /**
     * Set log detail ID
     *
     * @param string $log_detail_id
     * @return $this
     */
    public function setLogDetailId($log_detail_id) {
        $this->log_detail_id = $log_detail_id;
        return $this;
    }

    /**
     * Get reference info
     *
     * @return string
     */
    public function getReferenceInfo() {
        return $this->reference_info ?? '';
    }

    /**
     * Set reference info
     *
     * @param string $reference_info
     * @return $this
     */
    public function setReferenceInfo($reference_info) {
        $this->reference_info = $reference_info;
        return $this;
    }

    /**
     * Get error
     *
     * @return string
     */
    public function getError() {
        return $this->error ?? '';
    }

    /**
     * Set error
     *
     * @param string $error
     * @return $this
     */
    public function setError($error) {
        $this->error = $error;
        return $this;
    }

    /**
     * Get task ID
     *
     * @return string
     */
    public function getTaskId() {
        return $this->task_id ?? '';
    }

    /**
     * Set task ID
     *
     * @param string $task_id
     * @return $this
     */
    public function setTaskId($task_id) {
        $this->task_id = $task_id;
        return $this;
    }

    /**
     * Get task name
     *
     * @return string
     */
    public function getTaskName() {
        return $this->task_name;
    }

    /**
     * Set task name
     *
     * @param string $task_name
     * @return $this
     */
    public function setTaskName($task_name) {
        $this->task_name = $task_name;
        return $this;
    }

    /**
     * Get task status
     *
     * @return string
     */
    public function getTaskStatus() {
        return $this->task_status ?? '';
    }

    /**
     * Set task status
     *
     * @param string $task_status
     * @return $this
     */
    public function setTaskStatus($task_status) {
        $this->task_status = $task_status;
        return $this;
    }

    /**
     * Get executed at
     *
     * @return string
     */
    public function getExecutedAt() {
        return $this->executed_at ?? '';
    }

    /**
     * Set executed at
     *
     * @param string $executed_at
     * @return $this
     */
    public function setExecutedAt($executed_at) {
        $this->executed_at = $executed_at;
        return $this;
    }

    /**
     * Get execute at
     *
     * @return string
     */
    public function getExecuteAt() {
        return $this->execute_at ?? '';
    }

    /**
     * Set execute at
     *
     * @param string $execute_at
     * @return $this
     */
    public function setExecuteAt($execute_at) {
        $this->execute_at = $execute_at;
        return $this;
    }

    /**
     * Get retry count
     *
     * @return int
     */
    public function getRetryCount() {
        return $this->retry_count ?? 0;
    }

    /**
     * Set retry count
     *
     * @param int $retry_count
     * @return $this
     */
    public function setRetryCount($retry_count) {
        $this->retry_count = (int)$retry_count;
        return $this;
    }

    /**
     * Get push note
     *
     * @return string
     */
    public function getPushNote() {
        return $this->push_note ?? '';
    }

    /**
     * Set push note
     *
     * @param string $push_note
     * @return $this
     */
    public function setPushNote($push_note) {
        $this->push_note = $push_note;
        return $this;
    }

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt() {
        return $this->created_at ?? '';
    }

    /**
     * Set created at
     *
     * @param string $created_at
     * @return $this
     */
    public function setCreatedAt($created_at) {
        $this->created_at = $created_at;
        return $this;
    }

    /**
     * Get updated at
     *
     * @return string
     */
    public function getUpdatedAt() {
        return $this->updated_at ?? '';
    }

    /**
     * Set updated at
     *
     * @param string $updated_at
     * @return $this
     */
    public function setUpdatedAt($updated_at) {
        $this->updated_at = $updated_at;
        return $this;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray() {
        return [
            'log_id' => $this->log_id,
            'message' => $this->getMessage(),
            'profile_id' => $this->getProfileId(),
            'profile_name' => $this->profile_name,
            'action_type' => $this->getActionType(),
            'log_detail_id' => $this->getLogDetailId(),
            'reference_info' => $this->getReferenceInfo(),
            'error' => $this->getError(),
            'task_id' => $this->getTaskId(),
            'task_name' => $this->task_name,
            'task_status' => $this->getTaskStatus(),
            'executed_at' => $this->getExecutedAt(),
            'execute_at' => $this->getExecuteAt(),
            'retry_count' => $this->getRetryCount(),
            'push_note' => $this->getPushNote(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }

    /**
     * Create from array
     *
     * @param array $data
     * @return self
     */
    public static function fromArray($data) {
        $dto = new self(
            $data['profile_name'] ?? '',
            $data['task_name'] ?? '',
            $data['log_id'] ?? null
        );

        $dto->setMessage($data['message'] ?? '')
            ->setProfileId($data['profile_id'] ?? '')
            ->setActionType($data['action_type'] ?? '')
            ->setLogDetailId($data['log_detail_id'] ?? '')
            ->setReferenceInfo($data['reference_info'] ?? '')
            ->setError($data['error'] ?? '')
            ->setTaskId($data['task_id'] ?? '')
            ->setTaskStatus($data['task_status'] ?? '')
            ->setExecutedAt($data['executed_at'] ?? '')
            ->setExecuteAt($data['execute_at'] ?? '')
            ->setRetryCount($data['retry_count'] ?? 0)
            ->setPushNote($data['push_note'] ?? '')
            ->setCreatedAt($data['created_at'] ?? '')
            ->setUpdatedAt($data['updated_at'] ?? '');

        return $dto;
    }
}

