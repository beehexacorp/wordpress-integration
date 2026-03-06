<?php
/**
 *
 * Created By Hidro Le
 * Date: 3/5/26
 *
 */

namespace Beehexa\UI;

class AdminSyncLogPage
{

    /**
     * Display the HexaSync Logs grid page
     */
    public static function hexasync_logs_page() {
        $repository = new \Beehexa\Repository\HexaSyncLogRepository();

        // Get filter parameters from query string
        $filter_profile = isset($_GET['filter_profile']) ? sanitize_text_field($_GET['filter_profile']) : '';
        $filter_task = isset($_GET['filter_task']) ? sanitize_text_field($_GET['filter_task']) : '';
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

        // Set up pagination
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;

        // Get logs based on filters
        if ($filter_profile) {
            $logs = $repository->getByProfileName($filter_profile);
        } elseif ($filter_task) {
            $logs = $repository->getByTaskName($filter_task);
        } else {
            $logs = $repository->getAll();
        }

        // Calculate pagination
        $total_logs = count($logs);
        $total_pages = ceil($total_logs / $per_page);
        $paginated_logs = array_slice($logs, $offset, $per_page);

        // Helper function to truncate text
        $truncate_text = function($text, $limit = 150) {
            if (strlen($text) > $limit) {
                return substr($text, 0, $limit) . '...';
            }
            return $text;
        };

        // Helper function to format date
        $format_date = function($date) {
            if (empty($date)) return '-';
            return $date;
        };

        // Build filter URL
        $filter_url_params = [];
        if ($filter_profile) {
            $filter_url_params['filter_profile'] = urlencode($filter_profile);
        }
        if ($filter_task) {
            $filter_url_params['filter_task'] = urlencode($filter_task);
        }
        $filter_query_string = !empty($filter_url_params) ? '&' . implode('&', array_map(function($k, $v) { return "$k=$v"; }, array_keys($filter_url_params), $filter_url_params)) : '';

        ?>
        <div class="wrap">
            <h1>HexaSync Logs</h1>

            <!-- Filters -->
            <div style="background: #f1f1f1; padding: 15px; margin: 20px 0; border-radius: 3px;">
                <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="page" value="hexasync-logs" />

                    <div style="flex: 1; min-width: 200px;">
                        <label for="filter_profile" style="display: block; margin-bottom: 5px; font-weight: bold;">
                            Filter by Profile Name:
                        </label>
                        <input type="text" name="filter_profile" id="filter_profile" value="<?php echo esc_attr($filter_profile); ?>" placeholder="Enter profile name..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <label for="filter_task" style="display: block; margin-bottom: 5px; font-weight: bold;">
                            Filter by Task Name:
                        </label>
                        <input type="text" name="filter_task" id="filter_task" value="<?php echo esc_attr($filter_task); ?>" placeholder="Enter task name..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                    </div>

                    <button type="submit" class="button button-primary" style="margin-top: 23px;">Filter</button>
                    <?php if ($filter_profile || $filter_task): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=hexasync-logs')); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Results info -->
            <p style="margin: 10px 0; color: #666;">
                Showing <strong><?php echo ($offset + 1); ?></strong> to <strong><?php echo min($offset + $per_page, $total_logs); ?></strong> of <strong><?php echo $total_logs; ?></strong> logs
                <?php if ($filter_profile || $filter_task): ?>
                    <em>(Filtered)</em>
                <?php endif; ?>
            </p>

            <style>
                .hexasync-logs-table {
                    font-size: 12px;
                }
                .hexasync-logs-table td {
                    padding: 8px;
                    vertical-align: top;
                }
                .hexasync-logs-table .truncated {
                    max-width: 200px;
                    word-break: break-word;
                    white-space: normal;
                }
                .hexasync-logs-table .hexasync-link {
                    color: #0073aa;
                    text-decoration: none;
                    font-weight: bold;
                }
                .hexasync-logs-table .hexasync-link:hover {
                    color: #005a87;
                    text-decoration: underline;
                }
                .hexasync-logs-table .status-failed {
                    color: #d63638;
                    font-weight: bold;
                }
                .hexasync-logs-table .status-completed {
                    color: #00a32a;
                    font-weight: bold;
                }
                .hexasync-logs-table .status-pending {
                    color: #f39c12;
                    font-weight: bold;
                }
            </style>

            <table class="wp-list-table widefat fixed striped hexasync-logs-table">
                <thead>
                <tr>
                    <th width="6%">Log ID</th>
                    <th width="8%">Profile Name</th>
                    <th width="8%">Task Name</th>
                    <th width="6%">Action Type</th>
                    <th width="8%">Reference Info</th>
                    <th width="8%">Message</th>
                    <th width="8%">Error</th>
                    <th width="8%">Push Note</th>
                    <th width="6%">Status</th>
                    <th width="7%">Execute At</th>
                    <th width="7%">Executed At</th>
                    <th width="5%">Retries</th>
                    <th width="7%">Created At</th>
                    <th width="8%">HexaSync</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($paginated_logs)): ?>
                    <?php foreach ($paginated_logs as $log): ?>
                        <?php
                        $status_class = '';
                        $status = strtolower($log->getTaskStatus());
                        if (strpos($status, 'failed') !== false) {
                            $status_class = 'status-failed';
                        } elseif (strpos($status, 'completed') !== false || strpos($status, 'success') !== false) {
                            $status_class = 'status-completed';
                        } else {
                            $status_class = 'status-pending';
                        }

                        // Build HexaSync URL
                        $hexasync_url = sprintf(
                            'https://beta.hexasync.com/monitoring/profiles/%s/jobs?type=sync-history&id=%s',
                            urlencode($log->getProfileId()),
                            urlencode($log->getLogDetailId())
                        );
                        ?>
                        <tr>
                            <td><?php echo esc_html($log->getId()); ?></td>
                            <td><?php echo esc_html($log->getProfileName()); ?></td>
                            <td><?php echo esc_html($log->getTaskName()); ?></td>
                            <td><?php echo esc_html($log->getActionType()); ?></td>
                            <td class="truncated" title="<?php echo esc_attr($log->getReferenceInfo()); ?>">
                                <?php echo esc_html($truncate_text($log->getReferenceInfo(), 150)); ?>
                            </td>
                            <td class="truncated" title="<?php echo esc_attr($log->getMessage()); ?>">
                                <?php echo esc_html($truncate_text($log->getMessage(), 150)); ?>
                            </td>
                            <td class="truncated" title="<?php echo esc_attr($log->getError()); ?>">
                                <?php echo esc_html($truncate_text($log->getError(), 150)); ?>
                            </td>
                            <td class="truncated" title="<?php echo esc_attr($log->getPushNote()); ?>">
                                <?php echo esc_html($truncate_text($log->getPushNote(), 150)); ?>
                            </td>
                            <td class="<?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($log->getTaskStatus()); ?>
                            </td>
                            <td><?php echo esc_html($format_date($log->getExecuteAt())); ?></td>
                            <td><?php echo esc_html($format_date($log->getExecutedAt())); ?></td>
                            <td><?php echo esc_html($log->getRetryCount()); ?></td>
                            <td><?php echo esc_html($format_date($log->getCreatedAt())); ?></td>
                            <td>
                                <?php if (!empty($log->getLogDetailId()) && !empty($log->getProfileId())): ?>
                                    <a href="<?php echo esc_url($hexasync_url); ?>" target="_blank" class="hexasync-link">
                                        View on HexaSync
                                    </a>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="14" style="text-align: center;">No logs found</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px; align-items: center; flex-wrap: wrap;">
                    <?php
                    // Previous button
                    if ($current_page > 1) {
                        $prev_page = $current_page - 1;
                        echo '<a href="' . esc_url(admin_url('admin.php?page=hexasync-logs&paged=' . $prev_page . $filter_query_string)) . '" class="button">← Previous</a>';
                    }

                    // Page numbers
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<a href="' . esc_url(admin_url('admin.php?page=hexasync-logs' . $filter_query_string)) . '" class="button">1</a>';
                        if ($start_page > 2) {
                            echo '<span style="padding: 0 5px;">...</span>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i === $current_page) {
                            echo '<span class="button" style="background: #0073aa; color: white; cursor: default;">' . $i . '</span>';
                        } else {
                            echo '<a href="' . esc_url(admin_url('admin.php?page=hexasync-logs&paged=' . $i . $filter_query_string)) . '" class="button">' . $i . '</a>';
                        }
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span style="padding: 0 5px;">...</span>';
                        }
                        echo '<a href="' . esc_url(admin_url('admin.php?page=hexasync-logs&paged=' . $total_pages . $filter_query_string)) . '" class="button">' . $total_pages . '</a>';
                    }

                    // Next button
                    if ($current_page < $total_pages) {
                        $next_page = $current_page + 1;
                        echo '<a href="' . esc_url(admin_url('admin.php?page=hexasync-logs&paged=' . $next_page . $filter_query_string)) . '" class="button">Next →</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
