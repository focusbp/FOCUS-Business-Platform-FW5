<?php

/** Execution state belongs to the server, outside release-owned cron definitions. */
final class CronExecutionLog {
    private static function identity(array $job): array {
        return ['job_id'=>(int)$job['id'], 'class_name'=>(string)$job['class_name'], 'function_name'=>(string)$job['function_name']];
    }
    public static function save(FFM $logs, array $job, string $message): void {
        $identity = self::identity($job);
        $rows = $logs->select(array_keys($identity), array_values($identity), false);
        $row = $identity + ['last_log'=>mb_strcut($message,0,1000,'UTF-8')];
        if ($rows) { $row['id']=$rows[0]['id']; $logs->update($row); }
        else $logs->insert($row);
    }
    public static function overlay(FFM $logs, array $jobs): array {
        $messages=[];
        foreach ($logs->getall() as $row) $messages[json_encode([$row['job_id'],$row['class_name'],$row['function_name']])]=$row['last_log'];
        foreach ($jobs as &$job) {
            $key=json_encode(array_values(self::identity($job)));
            if (isset($messages[$key])) $job['last_log']=$messages[$key];
        }
        return $jobs;
    }
}
