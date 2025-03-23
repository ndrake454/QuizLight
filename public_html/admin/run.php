<?php
/**
 * Database Migration for Enhanced Leaderboard
 * 
 * This script ensures the database has the necessary structure to support
 * the enhanced mastery score calculations, which factor in question difficulty.
 * 
 * Instructions:
 * 1. Place this file in a secure location (e.g., admin directory)
 * 2. Run it once to apply the changes
 * 3. Remove or restrict access afterward
 */

// Include configuration
require_once '../config.php';

// Make sure only admins can run this script
requireAdmin();

// Initialize results array
$results = [];

// 1. Add difficulty_value column to questions table if it doesn't exist
try {
    // First check if the column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM questions LIKE 'difficulty_value'");
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, so add it
        $sql = "ALTER TABLE questions ADD COLUMN difficulty_value FLOAT DEFAULT 3.0";
        $pdo->exec($sql);
        $results[] = [
            'sql' => $sql,
            'success' => true,
            'message' => 'Added difficulty_value column to questions table'
        ];
    } else {
        $results[] = [
            'sql' => "Check for difficulty_value column",
            'success' => true,
            'message' => 'Column already exists'
        ];
    }
} catch (PDOException $e) {
    $results[] = [
        'sql' => "Add difficulty_value column to questions",
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// 2. Add question_id column to quiz_answers table if it doesn't exist
try {
    // First check if the column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM quiz_answers LIKE 'question_id'");
    if ($stmt->rowCount() == 0) {
        // Column doesn't exist, so add it
        $sql = "ALTER TABLE quiz_answers ADD COLUMN question_id INT";
        $pdo->exec($sql);
        $results[] = [
            'sql' => $sql,
            'success' => true,
            'message' => 'Added question_id column to quiz_answers table'
        ];
    } else {
        $results[] = [
            'sql' => "Check for question_id column",
            'success' => true,
            'message' => 'Column already exists'
        ];
    }
} catch (PDOException $e) {
    $results[] = [
        'sql' => "Add question_id column to quiz_answers",
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// 3. Add idx_question_id index to quiz_answers table
try {
    // Check if the index exists
    $stmt = $pdo->query("SHOW INDEX FROM quiz_answers WHERE Key_name = 'idx_question_id'");
    if ($stmt->rowCount() == 0) {
        // Index doesn't exist, so add it
        $sql = "ALTER TABLE quiz_answers ADD INDEX idx_question_id (question_id)";
        $pdo->exec($sql);
        $results[] = [
            'sql' => $sql,
            'success' => true,
            'message' => 'Added idx_question_id index to quiz_answers table'
        ];
    } else {
        $results[] = [
            'sql' => "Check for idx_question_id index",
            'success' => true,
            'message' => 'Index already exists'
        ];
    }
} catch (PDOException $e) {
    $results[] = [
        'sql' => "Add idx_question_id index to quiz_answers",
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// 4. Add idx_user_created index to user_attempts table
try {
    // Check if the index exists
    $stmt = $pdo->query("SHOW INDEX FROM user_attempts WHERE Key_name = 'idx_user_created'");
    if ($stmt->rowCount() == 0) {
        // Index doesn't exist, so add it
        $sql = "ALTER TABLE user_attempts ADD INDEX idx_user_created (user_id, created_at)";
        $pdo->exec($sql);
        $results[] = [
            'sql' => $sql,
            'success' => true,
            'message' => 'Added idx_user_created index to user_attempts table'
        ];
    } else {
        $results[] = [
            'sql' => "Check for idx_user_created index in user_attempts",
            'success' => true,
            'message' => 'Index already exists'
        ];
    }
} catch (PDOException $e) {
    $results[] = [
        'sql' => "Add idx_user_created index to user_attempts",
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// 5. Add idx_user_created index to quiz_answers table
try {
    // Check if the index exists
    $stmt = $pdo->query("SHOW INDEX FROM quiz_answers WHERE Key_name = 'idx_user_created'");
    if ($stmt->rowCount() == 0) {
        // Index doesn't exist, so add it
        $sql = "ALTER TABLE quiz_answers ADD INDEX idx_user_created (user_id, created_at)";
        $pdo->exec($sql);
        $results[] = [
            'sql' => $sql,
            'success' => true,
            'message' => 'Added idx_user_created index to quiz_answers table'
        ];
    } else {
        $results[] = [
            'sql' => "Check for idx_user_created index in quiz_answers",
            'success' => true,
            'message' => 'Index already exists'
        ];
    }
} catch (PDOException $e) {
    $results[] = [
        'sql' => "Add idx_user_created index to quiz_answers",
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// 6. Update question difficulty values if they haven't been set
try {
    // Check if any questions have default difficulty value
    $stmt = $pdo->query("SELECT COUNT(*) FROM questions WHERE difficulty_value = 3.0");
    $defaultDifficultyCount = $stmt->fetchColumn();
    
    if ($defaultDifficultyCount > 0) {
        // Update difficulty values based on intended_difficulty
        $sql = "
            UPDATE questions 
            SET difficulty_value = 
                CASE 
                    WHEN intended_difficulty = 'easy' THEN 1.5
                    WHEN intended_difficulty = 'challenging' THEN 3.0
                    WHEN intended_difficulty = 'hard' THEN 4.5
                    ELSE 3.0
                END
            WHERE difficulty_value = 3.0
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $updatedCount = $stmt->rowCount();
        
        $results[] = [
            'sql' => 'Update difficulty values based on intended_difficulty',
            'success' => true,
            'message' => "Updated difficulty values for $updatedCount questions"
        ];
    } else {
        $results[] = [
            'sql' => 'Check for questions needing difficulty updates',
            'success' => true,
            'message' => 'No questions need difficulty value updates'
        ];
    }
} catch (PDOException $e) {
    $results[] = [
        'sql' => 'Update difficulty values',
        'success' => false,
        'message' => $e->getMessage()
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Migration for Enhanced Leaderboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-6">Database Migration for Enhanced Leaderboard</h1>
            
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-2">Migration Results</h2>
                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 overflow-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 px-4">SQL</th>
                                <th class="text-left py-2 px-4">Status</th>
                                <th class="text-left py-2 px-4">Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 px-4 font-mono text-sm">
                                        <?php echo htmlspecialchars($result['sql']); ?>
                                    </td>
                                    <td class="py-2 px-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                               <?php echo $result['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $result['success'] ? 'Success' : 'Failed'; ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-4 text-sm">
                                        <?php echo htmlspecialchars($result['message']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-2">Next Steps</h2>
                <ul class="list-disc list-inside space-y-2 text-gray-700">
                    <li>The database should now be ready for the enhanced leaderboard functionality</li>
                    <li>Verify that the difficulty_value column is properly populated in the questions table</li>
                    <li>Test the leaderboard page with some sample quiz completions</li>
                    <li><strong>Important:</strong> Remove this migration script once complete</li>
                </ul>
            </div>
            
            <div class="flex justify-end">
                <a href="../index.php" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                    Return to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>