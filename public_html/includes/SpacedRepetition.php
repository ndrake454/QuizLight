<?php
/**
 * SpacedRepetition Class
 * 
 * Implements the SuperMemo 2 algorithm for spaced repetition
 * with adaptations for quiz application.
 */
class SpacedRepetition {
    private $pdo;
    
    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Initialize a new card for a user
     * 
     * @param int $userId The user ID
     * @param int $questionId The question ID
     * @return int The card ID
     */
    public function initializeCard($userId, $questionId) {
        try {
            // Check if card already exists
            $stmt = $this->pdo->prepare("SELECT id FROM sr_cards WHERE user_id = ? AND question_id = ?");
            $stmt->execute([$userId, $questionId]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetchColumn();
            }
            
            // Calculate next review date (1 day from now for new cards)
            $nextReview = date('Y-m-d H:i:s', strtotime('+1 day'));
            
            // Insert new card
            $stmt = $this->pdo->prepare("
                INSERT INTO sr_cards (user_id, question_id, ease_factor, `interval`, repetitions, next_review)
                VALUES (?, ?, 2.5, 1, 0, ?)
            ");
            $stmt->execute([$userId, $questionId, $nextReview]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error initializing SR card: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process a review for a card
     * 
     * @param int $userId The user ID
     * @param int $questionId The question ID
     * @param int $quality Answer quality (0-5)
     * @return bool Success or failure
     */
    public function processReview($userId, $questionId, $quality) {
        try {
            // Begin transaction
            $this->pdo->beginTransaction();
            
            // Get card data or initialize if not exists
            $stmt = $this->pdo->prepare("SELECT id, ease_factor, `interval`, repetitions FROM sr_cards WHERE user_id = ? AND question_id = ?");
            $stmt->execute([$userId, $questionId]);
            
            if ($stmt->rowCount() === 0) {
                $cardId = $this->initializeCard($userId, $questionId);
                
                // Fetch the newly created card data
                $stmt = $this->pdo->prepare("SELECT id, ease_factor, `interval`, repetitions FROM sr_cards WHERE id = ?");
                $stmt->execute([$cardId]);
            }
            
            $card = $stmt->fetch();
            $cardId = $card['id'];
            
            // Calculate new parameters using SM-2 algorithm
            $easeFactor = $card['ease_factor'];
            $interval = $card['interval'];
            $repetitions = $card['repetitions'];
            
            // Quality should be between 0 and 5
            $quality = max(0, min(5, $quality));
            
            // Update based on quality of response
            if ($quality < 3) {
                // If answer was wrong, reset repetitions
                $repetitions = 0;
                $interval = 1;
            } else {
                // If answer was correct, increase repetitions and interval
                $repetitions++;
                
                if ($repetitions == 1) {
                    $interval = 1;
                } elseif ($repetitions == 2) {
                    $interval = 6;
                } else {
                    $interval = round($interval * $easeFactor);
                }
            }
            
            // Adjust ease factor based on performance
            $easeFactor = $easeFactor + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
            
            // Ensure ease factor doesn't go below 1.3
            $easeFactor = max(1.3, $easeFactor);
            
            // Calculate next review date
            $nextReview = date('Y-m-d H:i:s', strtotime("+{$interval} days"));
            
            // Update card in database
            $stmt = $this->pdo->prepare("
                UPDATE sr_cards 
                SET ease_factor = ?, `interval` = ?, repetitions = ?, next_review = ?, last_review = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$easeFactor, $interval, $repetitions, $nextReview, $cardId]);
            
            // Log the review
            $stmt = $this->pdo->prepare("
                INSERT INTO sr_review_log (card_id, user_id, question_id, quality, ease_factor, `interval`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cardId, $userId, $questionId, $quality, $easeFactor, $interval]);
            
            // Commit transaction
            $this->pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->pdo->rollBack();
            error_log("Error processing SR review: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get due cards for a user
     * 
     * @param int $userId The user ID
     * @param array $categories Optional array of category IDs to filter by
     * @param int $limit Maximum number of cards to return
     * @return array Array of question IDs that are due for review
     */
    public function getDueCards($userId, $categories = [], $limit = 20) {
        try {
            $sql = "
                SELECT c.question_id, q.category_id, q.difficulty_value
                FROM sr_cards c
                JOIN questions q ON c.question_id = q.id
                WHERE c.user_id = ? AND c.next_review <= NOW()
            ";
            
            $params = [$userId];
            
            // Add category filter if specified
            if (!empty($categories)) {
                $placeholders = implode(',', array_fill(0, count($categories), '?'));
                $sql .= " AND q.category_id IN ($placeholders)";
                $params = array_merge($params, $categories);
            }
            
            // Sort by overdue cards first, then by difficulty (easier cards first)
            $sql .= " ORDER BY c.next_review ASC, q.difficulty_value ASC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting due SR cards: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get new cards for a user (cards they haven't seen before)
     * 
     * @param int $userId The user ID
     * @param array $categories Optional array of category IDs to filter by
     * @param int $limit Maximum number of cards to return
     * @return array Array of question IDs for new cards
     */
    public function getNewCards($userId, $categories = [], $limit = 10) {
        try {
            $sql = "
                SELECT q.id as question_id, q.category_id, q.difficulty_value
                FROM questions q
                WHERE q.id NOT IN (
                    SELECT question_id FROM sr_cards WHERE user_id = ?
                )
            ";
            
            $params = [$userId];
            
            // Add category filter if specified
            if (!empty($categories)) {
                $placeholders = implode(',', array_fill(0, count($categories), '?'));
                $sql .= " AND q.category_id IN ($placeholders)";
                $params = array_merge($params, $categories);
            }
            
            // Sort by difficulty (easier cards first)
            $sql .= " ORDER BY q.difficulty_value ASC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting new SR cards: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get spaced repetition statistics for a user
     * 
     * @param int $userId The user ID
     * @return array Statistics about the user's spaced repetition usage
     */
    public function getUserStats($userId) {
        try {
            $stats = [
                'total_cards' => 0,
                'cards_due_today' => 0,
                'cards_due_tomorrow' => 0,
                'cards_due_this_week' => 0,
                'average_ease_factor' => 0,
                'mastered_cards' => 0, // Cards with interval > 30 days
            ];
            
            // Total cards
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sr_cards WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['total_cards'] = $stmt->fetchColumn();
            
            if ($stats['total_cards'] == 0) {
                return $stats;
            }
            
            // Cards due today
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM sr_cards 
                WHERE user_id = ? AND next_review <= DATE_ADD(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute([$userId]);
            $stats['cards_due_today'] = $stmt->fetchColumn();
            
            // Cards due tomorrow
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM sr_cards 
                WHERE user_id = ? 
                AND next_review > DATE_ADD(NOW(), INTERVAL 1 DAY)
                AND next_review <= DATE_ADD(NOW(), INTERVAL 2 DAY)
            ");
            $stmt->execute([$userId]);
            $stats['cards_due_tomorrow'] = $stmt->fetchColumn();
            
            // Cards due this week
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM sr_cards 
                WHERE user_id = ? 
                AND next_review > NOW()
                AND next_review <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$userId]);
            $stats['cards_due_this_week'] = $stmt->fetchColumn();
            
            // Average ease factor
            $stmt = $this->pdo->prepare("
                SELECT AVG(ease_factor) FROM sr_cards WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats['average_ease_factor'] = round($stmt->fetchColumn(), 2);
            
            // Mastered cards (interval > 30 days)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM sr_cards WHERE user_id = ? AND `interval` > 30
            ");
            $stmt->execute([$userId]);
            $stats['mastered_cards'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting SR stats: " . $e->getMessage());
            return $stats;
        }
    }
    
    /**
     * Map question correctness to quality score for SM-2 algorithm
     * 
     * @param bool $isCorrect Whether the answer was correct
     * @param float $timeFactor Factor based on answer time (0-1)
     * @return int Quality score (0-5) for the SM-2 algorithm
     */
    public function calculateQuality($isCorrect, $timeFactor = 1.0) {
        if (!$isCorrect) {
            // Wrong answers get a score of 0-2 depending on timing
            return round($timeFactor * 2);
        } else {
            // Correct answers get a score of 3-5 depending on timing
            return 3 + round($timeFactor * 2);
        }
    }
}