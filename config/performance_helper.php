<?php
// config/performance_helper.php

function updateInternPerformance($conn, $intern_id) {
    if (!$intern_id) return false;

    // Fetch all tasks for this intern
    $perf_sql = "SELECT status, deadline, submitted_date, rating FROM tasks WHERE assigned_to = $intern_id";
    $perf_res = mysqli_query($conn, $perf_sql);
    
    $total_tasks = 0;
    $completed_tasks = 0;
    $on_time_tasks = 0;
    $total_rating = 0;
    $rated_tasks_count = 0;
    $tasks_submitted_count = 0;
    $tasks_pending_count = 0;
    $tasks_not_completed_count = 0;
    
    while ($t = mysqli_fetch_assoc($perf_res)) {
        $total_tasks++;
        
        if ($t['status'] == 'completed') {
            $completed_tasks++;
        } elseif ($t['status'] == 'submitted') {
            $tasks_submitted_count++;
        } elseif ($t['status'] == 'pending' && strtotime($t['deadline']) >= strtotime(date('Y-m-d'))) {
            $tasks_pending_count++;
        } else {
            // Past deadline or marked 'not_completed'
            $tasks_not_completed_count++;
        }
        
        // On-time check
        if (!empty($t['submitted_date']) && !empty($t['deadline'])) {
            $sub_date = strtotime($t['submitted_date']);
            $dead_date = strtotime($t['deadline']);
            if ($sub_date <= $dead_date) {
                $on_time_tasks++;
            }
        }
        
        // Rating
        if (!empty($t['rating']) && $t['rating'] > 0) {
            $total_rating += $t['rating'];
            $rated_tasks_count++;
        }
    }
    
    // Calculate Metrics
    $completion_rate = ($total_tasks > 0) ? ($completed_tasks / $total_tasks) * 100 : 0;
    $on_time_rate = ($total_tasks > 0) ? ($on_time_tasks / $total_tasks) * 100 : 0;
    
    $avg_rating = ($rated_tasks_count > 0) ? ($total_rating / $rated_tasks_count) : 0;
    $normalized_rating_score = ($avg_rating / 5) * 100;
    
    // Performance Formula:
    // 70% Quality (Rating points)
    // 20% Reliability (Completion rate)
    // 10% Punctuality (On-time rate)
    $performance_score = ($normalized_rating_score * 0.70) + ($completion_rate * 0.20) + ($on_time_rate * 0.10);
    
    // Determine Eligibility
    $comp_sql = "SELECT min_score_for_job FROM company_details LIMIT 1";
    $comp_res = mysqli_query($conn, $comp_sql);
    $comp_data = mysqli_fetch_assoc($comp_res);
    $min_score_req = $comp_data['min_score_for_job'] ?? 70;
    
    $eligibility = ($performance_score >= $min_score_req) ? 'eligible' : 'not_eligible';
    
    // Update Performance Table
    $check_perf = "SELECT intern_id FROM performance WHERE intern_id = $intern_id";
    $check_res = mysqli_query($conn, $check_perf);
    
    if (mysqli_num_rows($check_res) > 0) {
        $update_perf = "UPDATE performance SET 
                        total_tasks_assigned = $total_tasks,
                        tasks_completed = $completed_tasks,
                        tasks_not_completed = $tasks_not_completed_count,
                        tasks_submitted = $tasks_submitted_count,
                        tasks_pending = $tasks_pending_count,
                        on_time_submissions = $on_time_tasks,
                        performance_score = $performance_score,
                        eligibility = '$eligibility',
                        last_updated = NOW()
                        WHERE intern_id = $intern_id";
        return mysqli_query($conn, $update_perf);
    } else {
        $insert_perf = "INSERT INTO performance 
                        (intern_id, total_tasks_assigned, tasks_completed, tasks_not_completed, tasks_submitted, tasks_pending, on_time_submissions, performance_score, eligibility, last_updated)
                        VALUES 
                        ($intern_id, $total_tasks, $completed_tasks, $tasks_not_completed_count, $tasks_submitted_count, $tasks_pending_count, $on_time_tasks, $performance_score, '$eligibility', NOW())";
        return mysqli_query($conn, $insert_perf);
    }
}
?>
