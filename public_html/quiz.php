<?php
/**
 * Quiz Taking Page
 * 
 * This file serves as the main entry point for all quiz functionality,
 * now refactored to be more maintainable with separated concerns.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
requireLogin(); // Ensure user is logged in

// Include utility functions and classes
require_once 'includes/SpacedRepetition.php';
require_once 'includes/quiz-functions.php';

$pageTitle = 'Quiz';
$extraScripts = ['/js/quiz-functions.js']; // Custom JS for quiz functionality

// Error variable for templates
$error = '';

/**
 * Process quiz settings from POST or session
 * Initialize the quiz if it's a new one
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quiz_type'])) {
    if (initializeQuiz()) {
        // Redirect to avoid form resubmission
        header("Location: quiz.php");
        exit;
    } else {
        // Validation failed, redirect to selection with error message
        header("Location: quiz_select.php");
        exit;
    }
}

// Check if we have an active quiz
if (!isset($_SESSION['quiz_type']) || !isset($_SESSION['questions']) || empty($_SESSION['questions'])) {
    header("Location: quiz_select.php");
    exit;
}

/**
 * Handle answer submission
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_answer'])) {
    $questionId = $_POST['question_id'];
    
    // Determine the answer type and get the answer
    if (isset($_POST['selected_answer'])) { 
        // Multiple choice
        $answer = $_POST['selected_answer'];
    } else {
        // Written response
        $answer = trim($_POST['written_answer'] ?? '');
    }
    
    // Process the answer submission
    $result = processAnswerSubmission($questionId, $answer);
    
    // Check result
    if ($result['success']) {
        if ($result['redirect']) {
            // Redirect to avoid resubmission
            header("Location: quiz.php");
            exit;
        }
    } else {
        $error = $result['message'];
    }
}

/**
 * Handle question rating and move to next question
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rate_question'])) {
    $questionId = $_POST['question_id'];
    $difficulty = $_POST['difficulty_rating'];
    
    if (processQuestionRating($questionId, $difficulty)) {
        // Redirect to avoid form resubmission
        header("Location: quiz.php");
        exit;
    } else {
        $error = "There was an error processing your response. Please try again.";
    }
}

// Include the header
include 'includes/header.php';
?>

<div class="max-w-3xl mx-auto">
    <?php if (isset($_SESSION['quiz_completed']) && $_SESSION['quiz_completed']): ?>
        <!-- Quiz Results -->
        <?php include 'includes/quiz-templates/results.php'; ?>
    <?php else: ?>
        <!-- Active Quiz Question -->
        <?php include 'includes/quiz-templates/question.php'; ?>
    <?php endif; ?>
</div>

<?php
// Include adaptive quiz chart if needed
if (isset($_SESSION['quiz_completed']) && $_SESSION['quiz_completed'] && 
    ($_SESSION['quiz_type'] === 'adaptive' || $_SESSION['quiz_type'] === 'spaced_repetition')) {
    $extraScripts[] = '/js/adaptive-chart.js';
}

include 'includes/footer.php';
?>