/**
 * Quiz Functions JavaScript
 * 
 * Contains all the JavaScript functions used on the quiz page.
 */

// Function to handle answer selection for multiple choice
function selectAnswer(answerId) {
    // Update hidden input
    document.getElementById('selected-answer').value = answerId;
    
    // Remove selection from all options
    document.querySelectorAll('.answer-option').forEach(option => {
        const optionId = option.getAttribute('data-answer-id');
        document.getElementById('answer-option-' + optionId).classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50', 'border-indigo-300');
        document.getElementById('answer-circle-' + optionId).classList.remove('border-indigo-500');
        document.getElementById('answer-circle-' + optionId).classList.add('border-gray-300');
        document.getElementById('answer-dot-' + optionId).classList.add('hidden');
    });
    
    // Add selection to the clicked option
    document.getElementById('answer-option-' + answerId).classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50', 'border-indigo-300');
    document.getElementById('answer-circle-' + answerId).classList.remove('border-gray-300');
    document.getElementById('answer-circle-' + answerId).classList.add('border-indigo-500');
    document.getElementById('answer-dot-' + answerId).classList.remove('hidden');
}

// Initialize word counter for written responses
function initWordCounter() {
    const writtenAnswerInput = document.getElementById('written-answer');
    const wordCounter = document.getElementById('word-counter');

    if (writtenAnswerInput && wordCounter) {
        writtenAnswerInput.addEventListener('input', function() {
            const text = this.value.trim();
            const words = text.split(/\s+/).filter(word => word.length > 0);
            const wordCount = Math.min(words.length, 3);
            
            // Update counter
            wordCounter.textContent = `${wordCount}/3`;
            
            // Change color if over limit
            if (wordCount > 3) {
                wordCounter.classList.add('text-red-500');
                wordCounter.classList.remove('text-gray-500');
            } else {
                wordCounter.classList.remove('text-red-500');
                wordCounter.classList.add('text-gray-500');
            }
            
            // Limit to 3 words
            if (wordCount > 3) {
                this.value = words.slice(0, 3).join(' ');
            }
        });
    }
}

// Toggle review section in results page
function initReviewToggle() {
    const toggleReviewBtn = document.getElementById('toggleReviewBtn');
    const reviewSection = document.getElementById('reviewSection');
    
    if (toggleReviewBtn && reviewSection) {
        toggleReviewBtn.addEventListener('click', function() {
            if (reviewSection.style.display === 'none') {
                reviewSection.style.display = 'block';
                toggleReviewBtn.textContent = 'Hide Review';
            } else {
                reviewSection.style.display = 'none';
                toggleReviewBtn.textContent = 'Review Answers';
            }
        });
    }
}

// Initialize all quiz functionality
document.addEventListener('DOMContentLoaded', function() {
    initWordCounter();
    initReviewToggle();
});