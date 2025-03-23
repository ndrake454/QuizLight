<?php
/**
 * Home Controller
 * 
 * Handles the main landing page and other static pages
 */
class HomeController extends BaseController {
    /**
     * Display the home page
     * 
     * @return void
     */
    public function index() {
        // Get top performers for the leaderboard
        $thisWeekPerformers = [];
        $lastWeekPerformers = [];
        
        if (isLoggedIn()) {
            // Get top performers
            $leaderboardService = new LeaderboardService();
            $thisWeekPerformers = $leaderboardService->getTopPerformers(0, 3);
            $lastWeekPerformers = $leaderboardService->getTopPerformers(1, 3);
            
            // Get user's comparison
            $userComparison = $leaderboardService->compareUserWeeklyPerformance($_SESSION['user_id']);
        }
        
        $this->render('home/index', [
            'pageTitle' => 'Home',
            'thisWeekPerformers' => $thisWeekPerformers,
            'lastWeekPerformers' => $lastWeekPerformers,
            'userComparison' => $userComparison ?? null
        ]);
    }
    
    /**
     * Display the about page
     * 
     * @return void
     */
    public function about() {
        $this->render('home/about', [
            'pageTitle' => 'About Us'
        ]);
    }
    
    /**
     * Display the privacy policy
     * 
     * @return void
     */
    public function privacy() {
        $this->render('home/privacy', [
            'pageTitle' => 'Privacy Policy'
        ]);
    }
}