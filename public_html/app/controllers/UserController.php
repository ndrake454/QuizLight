<?php
/**
 * User Controller
 * 
 * Handles user profile and settings
 */
class UserController extends BaseController {
    private $userModel;
    private $quizModel;
    
    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
        $this->quizModel = new QuizModel();
    }
    
    /**
     * Display user profile
     * 
     * @return void
     */
    public function profile() {
        // Ensure user is logged in
        requireLogin();
        
        $userId = $_SESSION['user_id'];
        
        // Get user data
        $user = $this->userModel->find($userId);
        
        // Get quiz history
        $quizHistory = $this->quizModel->getUserHistory($userId, 5);
        
        // Get category performance
        $categoryPerformance = $this->quizModel->getUserCategoryPerformance($userId);
        
        // Get recommendations
        $recommendationService = new RecommendationService();
        $recommendations = $recommendationService->generateRecommendations($userId);
        
        // Get achievement data
        $achievementService = new AchievementService();
        $achievements = $achievementService->getUserAchievements($userId);
        
        // Get performance data for charts
        $analyticsService = new AnalyticsService();
        $performanceData = $analyticsService->getPerformanceData($userId);
        
        // Calculate mastery scores
        $leaderboardService = new LeaderboardService();
        $masteryData = $leaderboardService->getUserMasteryScores($userId);
        
        $this->render('user/profile', [
            'pageTitle' => 'My Profile',
            'user' => $user,
            'quizHistory' => $quizHistory,
            'categoryPerformance' => $categoryPerformance,
            'recommendations' => $recommendations,
            'achievements' => $achievements,
            'performanceData' => $performanceData,
            'weeklyMasteryScore' => $masteryData['weekly'] ?? 0,
            'allTimeMasteryScore' => $masteryData['allTime'] ?? 0,
            'extraScripts' => ['/assets/js/profile-analytics.js']
        ]);
    }
    
    /**
     * Display edit profile form
     * 
     * @return void
     */
    public function editProfile() {
        // Ensure user is logged in
        requireLogin();
        
        $userId = $_SESSION['user_id'];
        
        // Get user data
        $user = $this->userModel->find($userId);
        
        $this->render('user/edit-profile', [
            'pageTitle' => 'Edit Profile',
            'user' => $user
        ]);
    }
    
    /**
     * Process edit profile form
     * 
     * @return void
     */
    public function updateProfile() {
        // Ensure user is logged in
        requireLogin();
        
        $userId = $_SESSION['user_id'];
        
        // Validate input
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Get user data
        $user = $this->userModel->find($userId);
        
        // Check for missing name fields
        if (empty($firstName) || empty($lastName)) {
            setFlashMessage('First name and last name are required', 'error');
            $this->redirect('/edit-profile');
            return;
        }
        
        // Update user profile data
        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
        
        // Update password if provided
        if (!empty($currentPassword) && !empty($newPassword)) {
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                setFlashMessage('Current password is incorrect', 'error');
                $this->redirect('/edit-profile');
                return;
            }
            
            // Check new password length
            if (strlen($newPassword) < 8) {
                setFlashMessage('New password must be at least 8 characters long', 'error');
                $this->redirect('/edit-profile');
                return;
            }
            
            // Check if passwords match
            if ($newPassword !== $confirmPassword) {
                setFlashMessage('New passwords do not match', 'error');
                $this->redirect('/edit-profile');
                return;
            }
            
            // Update password
            $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        
        // Update user
        $updated = $this->userModel->update($userId, $data);
        
        if ($updated) {
            // Update session data
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            
            setFlashMessage('Profile updated successfully', 'success');
        } else {
            setFlashMessage('Failed to update profile', 'error');
        }
        
        $this->redirect('/profile');
    }
}