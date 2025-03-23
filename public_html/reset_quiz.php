<?php
/**
 * Reset Quiz Page
 * 
 * This simple utility page clears all quiz-related session variables 
 * and redirects to the quiz selection page. It's used when a user wants
 * to start a new quiz.
 */

require_once 'config.php';

// Clear all quiz-related session variables
unset($_SESSION['quiz_type']);
unset($_SESSION['quiz_categories']);
unset($_SESSION['quiz_subcategories']);
unset($_SESSION['quiz_num_questions']);
unset($_SESSION['quiz_max_questions']);
unset($_SESSION['quiz_difficulty']);
unset($_SESSION['quiz_show_results_at_end']);
unset($_SESSION['quiz_adaptive']);
unset($_SESSION['current_question']);
unset($_SESSION['correct_answers']);
unset($_SESSION['questions']);
unset($_SESSION['show_explanation']);
unset($_SESSION['current_answer_correct']);
unset($_SESSION['selected_answer_id']);
unset($_SESSION['quiz_completed']);
unset($_SESSION['quiz_started_at']);
unset($_SESSION['category_difficulty']); // Clear category-based difficulty tracking

// Redirect to quiz selection page
header("Location: quiz_select.php");
exit;
?>