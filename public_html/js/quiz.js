/**
 * Quiz Functionality Script
 * 
 * This script contains Alpine.js components for quiz selection and quiz taking.
 * It manages tab navigation, category selection, and quiz interaction.
 */

document.addEventListener('alpine:init', () => {
    // Quiz Selection Component
    Alpine.data('quizSelect', () => ({
        // Active tab (quick, spaced, test, adaptive)
        activeTab: 'quick',
        // Selected categories for quiz
        selectedCategories: [],
        // Expanded categories in the UI
        openCategories: [],
        
        /**
         * Toggle category expansion
         */
        toggleCategory(categoryId) {
            if (this.openCategories.includes(categoryId)) {
                this.openCategories = this.openCategories.filter(id => id !== categoryId);
            } else {
                this.openCategories.push(categoryId);
            }
        },
        
        /**
         * Initialize the component
         */
        init() {
            // Check if there's a recommended tab from URL
            const urlParams = new URLSearchParams(window.location.search);
            const recTab = urlParams.get('rec');
            
            if (recTab) {
                if (['quick', 'spaced', 'test', 'adaptive'].includes(recTab)) {
                    this.activeTab = recTab;
                }
            }
        }
    }));
    
    // Quiz Component for the actual quiz interface
    Alpine.data('quiz', () => ({
        // Currently selected answer
        selectedAnswer: '',
        // Whether to show the review panel
        showReview: false,
        
        /**
         * Initialize the quiz component
         */
        init() {
            // Check if coming back from review (used for test mode)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('review') === 'true') {
                this.showReview = true;
            }
        },
        
        /**
         * Handle answer selection
         */
        selectAnswer(answerId) {
            this.selectedAnswer = answerId;
            document.getElementById('selected-answer').value = answerId;
        },
        
        /**
         * Confirm quiz abandonment
         */
        quitQuiz() {
            if (confirm('Are you sure you want to quit this quiz? Your progress will be lost.')) {
                window.location.href = 'quiz_select.php';
            }
        }
    }));
});