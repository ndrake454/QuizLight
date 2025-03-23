/**
 * Live Quiz JavaScript Functions
 * 
 * This file contains client-side functionality for the live quiz feature
 * with dynamic updates via AJAX/JSON to avoid full page refreshes.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize core components
    initializeQuizComponents();
    
    // Start polling for updates if in an active quiz
    if (document.getElementById('session-id')) {
        startLiveUpdates();
    }
});

/**
 * Initialize all quiz components based on what's on the page
 */
function initializeQuizComponents() {
    // Timer components
    const timerBarElement = document.getElementById('timer-bar');
    if (timerBarElement) {
        initializeTimer(timerBarElement);
    }
    
    // Answer buttons
    const answerButtons = document.querySelectorAll('.answer-button');
    if (answerButtons.length > 0) {
        initializeAnswerButtons(answerButtons);
    }
    
    // Written answer form
    const writtenAnswerForm = document.getElementById('written-answer-form');
    if (writtenAnswerForm) {
        initializeWrittenAnswerForm(writtenAnswerForm);
    }
    
    // Host controls
    const hostControls = document.getElementById('host-controls');
    if (hostControls) {
        initializeHostControls();
    }
    
    // Join session form
    const joinForm = document.getElementById('join-session-form');
    if (joinForm) {
        initializeJoinForm(joinForm);
    }
}

/**
 * Timer functionality
 */
let timerInterval;
let timeRemaining;

function initializeTimer(timerBar) {
    const timePerQuestion = parseInt(timerBar.getAttribute('data-time') || 20);
    timeRemaining = timePerQuestion;
    
    // Start at 100% width
    timerBar.style.width = '100%';
    
    // Update every second
    timerInterval = setInterval(() => {
        timeRemaining--;
        
        // Update progress bar
        const percentLeft = (timeRemaining / timePerQuestion) * 100;
        timerBar.style.width = `${percentLeft}%`;
        
        // Change color as time runs out
        if (percentLeft < 25) {
            timerBar.classList.remove('bg-indigo-600', 'bg-yellow-500');
            timerBar.classList.add('bg-red-500');
        } else if (percentLeft < 50) {
            timerBar.classList.remove('bg-indigo-600', 'bg-red-500');
            timerBar.classList.add('bg-yellow-500');
        }
        
        // Handle timeout
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            submitTimeUp();
        }
    }, 1000);
}

/**
 * Submit a timeout response
 */
function submitTimeUp() {
    const questionId = document.getElementById('question-id').value;
    const sessionId = document.getElementById('session-id').value;
    
    fetch('live_quiz_answer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            session_id: sessionId,
            question_id: questionId,
            answer_id: null,
            time_taken: parseInt(document.getElementById('timer-bar').getAttribute('data-time') || 20),
            timed_out: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showWaitingMessage();
            updateScore(data.score);
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Error submitting timeout:', error);
        showError('Network error. Please refresh the page.');
    });
}

/**
 * Initialize multiple choice answer buttons
 */
function initializeAnswerButtons(buttons) {
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Stop the timer
            clearInterval(timerInterval);
            
            // Get answer data
            const answerId = this.getAttribute('data-answer-id');
            const questionId = document.getElementById('question-id').value;
            const sessionId = document.getElementById('session-id').value;
            const timeTaken = parseInt(document.getElementById('timer-bar').getAttribute('data-time') || 20) - timeRemaining;
            
            // Disable all buttons
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-70');
            });
            
            // Style selected button
            this.classList.add('ring-4', 'ring-white');
            
            // Submit answer
            fetch('live_quiz_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    question_id: questionId,
                    answer_id: answerId,
                    time_taken: timeTaken,
                    timed_out: false
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showWaitingMessage();
                    updateScore(data.score);
                    
                    // Mark that user has answered
                    document.getElementById('has-answered').value = 'true';
                    
                    // Add visual feedback
                    if (data.is_correct) {
                        this.classList.add('bg-green-500');
                    } else {
                        this.classList.add('bg-red-500');
                    }
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error submitting answer:', error);
                showError('Network error. Please refresh the page.');
            });
        });
    });
}

/**
 * Initialize written answer form
 */
function initializeWrittenAnswerForm(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Stop the timer
        clearInterval(timerInterval);
        
        // Get form data
        const questionId = document.getElementById('question-id').value;
        const sessionId = document.getElementById('session-id').value;
        const writtenAnswer = document.getElementById('written-answer').value;
        const timeTaken = parseInt(document.getElementById('timer-bar').getAttribute('data-time') || 20) - timeRemaining;
        
        // Disable the form
        const formElements = this.elements;
        for (let i = 0; i < formElements.length; i++) {
            formElements[i].disabled = true;
        }
        
        // Submit answer
        fetch('live_quiz_answer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_id: sessionId,
                question_id: questionId,
                written_answer: writtenAnswer,
                time_taken: timeTaken,
                timed_out: false
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showWaitingMessage();
                updateScore(data.score);
                
                // Mark that user has answered
                document.getElementById('has-answered').value = 'true';
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error submitting written answer:', error);
            showError('Network error. Please refresh the page.');
        });
    });
}

/**
 * Show waiting message after submitting answer
 */
function showWaitingMessage() {
    const questionContainer = document.getElementById('question-container');
    if (questionContainer) {
        const waitingMessage = document.createElement('div');
        waitingMessage.className = 'mt-6 flex items-center justify-center';
        waitingMessage.innerHTML = `
            <div class="bg-blue-100 text-blue-700 px-4 py-3 rounded-md">
                <p class="font-medium text-center">Your answer has been submitted.</p>
                <p class="text-sm text-center">Waiting for results...</p>
            </div>
        `;
        questionContainer.appendChild(waitingMessage);
    }
}

/**
 * Update score display with animation
 */
function updateScore(newScore) {
    const scoreElement = document.getElementById('user-score');
    if (scoreElement && newScore) {
        // Animate score change
        const currentScore = parseInt(scoreElement.textContent);
        const scoreDiff = newScore - currentScore;
        
        if (scoreDiff > 0) {
            // Show score increase animation
            const scoreAnimation = document.createElement('div');
            scoreAnimation.className = 'absolute -mt-8 font-bold text-lg text-green-500 animate-bounce';
            scoreAnimation.textContent = `+${scoreDiff}`;
            scoreElement.parentNode.appendChild(scoreAnimation);
            
            // Remove animation after 2 seconds
            setTimeout(() => {
                scoreAnimation.remove();
            }, 2000);
        }
        
        // Update score value
        scoreElement.textContent = newScore;
    }
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.getElementById('error-container');
    if (container) {
        container.innerHTML = `
            <div class="bg-red-100 text-red-700 p-4 rounded-md mb-4">
                ${message}
            </div>
        `;
    } else {
        console.error('Error:', message);
    }
}

/**
 * Start live updates polling
 */
function startLiveUpdates() {
    // Poll for question updates every 2 seconds
    setInterval(checkForQuizUpdates, 2000);
    
    // Poll for leaderboard updates every 5 seconds
    setInterval(updateLeaderboard, 5000);
    
    // If host view, update participant list
    if (document.getElementById('participant-list')) {
        updateParticipantList();
        setInterval(updateParticipantList, 5000);
    }
}

/**
 * Check for session/question updates
 */
function checkForQuizUpdates() {
    const sessionIdElement = document.getElementById('session-id');
    
    if (sessionIdElement) {
        const sessionId = sessionIdElement.value;
        const currentQuestionId = document.getElementById('question-id')?.value;
        const hasAnswered = document.getElementById('has-answered')?.value === 'true';
        
        fetch(`live_quiz_data.php?action=check_question&session_id=${sessionId}&current_question_id=${currentQuestionId}&answered=${hasAnswered}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.reload) {
                        // Instead of full page reload, update the quiz content
                        updateQuizContent(data);
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for updates:', error);
            });
    }
}

/**
 * Update quiz content dynamically
 */
function updateQuizContent(data) {
    const questionContainer = document.getElementById('question-container');
    if (!questionContainer) return;
    
    if (data.session_status === 'completed') {
        // Show quiz completion screen
        showQuizCompleted(data);
    } else if (data.session_status === 'waiting') {
        // Show waiting screen
        showWaitingScreen();
    } else if (data.active_question && !data.has_answered) {
        // Show new question
        loadNewQuestion(data.active_question);
    } else if (data.active_question && data.has_answered) {
        // Show waiting for next question screen
        showAnsweredState();
    }
}

/**
 * Load a new question dynamically
 */
function loadNewQuestion(question) {
    fetch(`live_quiz_data.php?action=get_question&question_id=${question.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const questionContainer = document.getElementById('question-container');
                if (!questionContainer) return;
                
                // Build the question HTML
                let html = `
                    <input type="hidden" id="question-id" value="${data.question.id}">
                    <input type="hidden" id="has-answered" value="false">
                    
                    <!-- Timer -->
                    <div class="mb-4 w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div id="timer-bar" class="h-full bg-indigo-600 transition-all duration-1000" data-time="${data.time_per_question}"></div>
                    </div>
                    
                    <div class="mb-6 text-center">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">${data.question.question_text}</h3>
                `;
                
                // Add image if available
                if (data.question.image_path) {
                    html += `
                        <div class="mb-4 flex justify-center">
                            <img src="uploads/questions/${data.question.image_path}" alt="Question image" class="max-h-60 rounded">
                        </div>
                    `;
                }
                
                html += `</div>`;
                
                // Add answers based on question type
                if (data.question.question_type === 'multiple_choice') {
                    html += `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                    `;
                    
                    const colors = [
                        ['bg-red-500 hover:bg-red-600', 'border-red-600'],
                        ['bg-blue-500 hover:bg-blue-600', 'border-blue-600'],
                        ['bg-yellow-500 hover:bg-yellow-600', 'border-yellow-600'],
                        ['bg-green-500 hover:bg-green-600', 'border-green-600']
                    ];
                    
                    data.question.answers.forEach((answer, index) => {
                        const colorIndex = index % colors.length;
                        const colorClasses = colors[colorIndex];
                        
                        html += `
                            <button type="button" 
                                class="answer-button p-4 rounded-md text-white text-center font-bold shadow-md border-b-4 transition transform hover:scale-105 ${colorClasses[0]} ${colorClasses[1]}"
                                data-answer-id="${answer.id}">
                                ${answer.answer_text}
                            </button>
                        `;
                    });
                    
                    html += `</div>`;
                } else {
                    html += `
                        <form id="written-answer-form" class="mt-6">
                            <div class="mb-4">
                                <label for="written-answer" class="block text-sm font-medium text-gray-700 mb-2">Your Answer:</label>
                                <input type="text" id="written-answer" name="written_answer" required 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Type your answer here (1-3 words)">
                            </div>
                            
                            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 px-4 rounded hover:bg-indigo-700 transition">
                                Submit Answer
                            </button>
                        </form>
                    `;
                }
                
                // Add the HTML to the container
                questionContainer.innerHTML = html;
                
                // Reinitialize components
                initializeTimer(document.getElementById('timer-bar'));
                
                const newAnswerButtons = document.querySelectorAll('.answer-button');
                if (newAnswerButtons.length > 0) {
                    initializeAnswerButtons(newAnswerButtons);
                }
                
                const newWrittenAnswerForm = document.getElementById('written-answer-form');
                if (newWrittenAnswerForm) {
                    initializeWrittenAnswerForm(newWrittenAnswerForm);
                }
            }
        })
        .catch(error => {
            console.error('Error loading question:', error);
        });
}

/**
 * Show waiting screen
 */
function showWaitingScreen() {
    const questionContainer = document.getElementById('question-container');
    if (!questionContainer) return;
    
    questionContainer.innerHTML = `
        <div class="mb-6 text-center">
            <div class="animate-pulse inline-flex items-center justify-center h-24 w-24 rounded-full bg-indigo-100 text-indigo-500 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Waiting for Quiz to Start</h3>
            <p class="text-gray-600">The host will start the quiz when everyone is ready.</p>
        </div>
    `;
}

/**
 * Show already answered state
 */
function showAnsweredState() {
    const questionContainer = document.getElementById('question-container');
    if (!questionContainer) return;
    
    questionContainer.innerHTML = `
        <div class="flex items-center justify-center">
            <div class="bg-blue-100 text-blue-700 px-4 py-3 rounded-md">
                <p class="font-medium text-center">Your answer has been submitted.</p>
                <p class="text-sm text-center">Waiting for next question...</p>
            </div>
        </div>
    `;
}

/**
 * Show quiz completed screen
 */
function showQuizCompleted(data) {
    const questionContainer = document.getElementById('question-container');
    if (!questionContainer) return;
    
    fetch(`live_quiz_data.php?action=get_results&session_id=${data.session_id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                questionContainer.innerHTML = `
                    <div class="mb-6 text-center">
                        <div class="inline-flex items-center justify-center h-24 w-24 rounded-full bg-green-100 text-green-500 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Quiz Completed!</h3>
                        <p class="text-gray-600 mb-4">Thank you for participating in this live quiz.</p>
                        
                        <div class="inline-block bg-indigo-100 text-indigo-700 px-4 py-2 rounded-md">
                            <p class="font-medium">Your final score: <span class="font-bold text-xl">${data.user_score}</span></p>
                        </div>
                    </div>
                `;
                
                // Update the leaderboard one last time
                updateLeaderboard();
            }
        })
        .catch(error => {
            console.error('Error loading results:', error);
        });
}

/**
 * Update leaderboard
 */
function updateLeaderboard() {
    const leaderboardContainer = document.getElementById('leaderboard-container');
    if (!leaderboardContainer) return;
    
    const sessionId = document.getElementById('session-id').value;
    
    fetch(`live_quiz_data.php?action=get_leaderboard&session_id=${sessionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.leaderboard) {
                let html = `<h3 class="text-lg font-medium text-gray-800 mb-4">Leaderboard</h3>`;
                
                if (data.leaderboard.length > 0) {
                    html += `<div class="bg-indigo-50 rounded-lg p-4"><div class="space-y-2">`;
                    
                    data.leaderboard.forEach((player, index) => {
                        const isCurrentUser = player.display_name === document.getElementById('display-name')?.value;
                        
                        html += `
                            <div class="bg-white rounded-md p-3 flex items-center justify-between
                                        ${isCurrentUser ? 'ring-2 ring-indigo-500' : ''}">
                                <div class="flex items-center">
                                    <div class="mr-3 w-8 h-8 flex items-center justify-center rounded-full
                                                ${index === 0 ? 'bg-yellow-100 text-yellow-800' : 
                                                (index === 1 ? 'bg-gray-100 text-gray-800' : 
                                                (index === 2 ? 'bg-yellow-50 text-yellow-700' : 'bg-blue-50 text-blue-700'))}">
                                        ${index + 1}
                                    </div>
                                    <span class="font-medium">${player.display_name}</span>
                                    
                                    ${isCurrentUser ? '<span class="ml-2 text-xs bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full">You</span>' : ''}
                                </div>
                                <span class="font-bold">${player.score}</span>
                            </div>
                        `;
                    });
                    
                    html += `</div></div>`;
                } else {
                    html += `<p class="text-center text-gray-500">No scores yet.</p>`;
                }
                
                leaderboardContainer.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error updating leaderboard:', error);
        });
}

/**
 * Update participant list in host view
 */
function updateParticipantList() {
    const participantList = document.getElementById('participant-list');
    if (!participantList) return;
    
    const sessionId = document.getElementById('session-id').value;
    
    fetch(`live_quiz_data.php?action=get_participants&session_id=${sessionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.participants) {
                if (data.participants.length > 0) {
                    let html = '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">';
                    
                    data.participants.forEach(participant => {
                        // Calculate accuracy percentage
                        let accuracy = 0;
                        if (participant.total_answers > 0) {
                            accuracy = Math.round((participant.correct_answers / participant.total_answers) * 100);
                        }
                        
                        html += `
                            <div class="bg-indigo-50 rounded-lg p-3 flex items-center">
                                <div class="mr-3 flex-shrink-0 h-8 w-8 rounded-full bg-indigo-200 flex items-center justify-center text-indigo-600 font-bold">
                                    ${participant.display_name.charAt(0).toUpperCase()}
                                </div>
                                <div class="overflow-hidden">
                                    <div class="font-medium text-sm truncate">${participant.display_name}</div>
                                    <div class="text-xs text-gray-500">
                                        Score: ${participant.score} · Accuracy: ${accuracy}%
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    participantList.innerHTML = html;
                } else {
                    participantList.innerHTML = '<p class="text-gray-500 text-center">No participants have joined yet.</p>';
                }
            }
        })
        .catch(error => {
            console.error('Error updating participant list:', error);
        });
}

/**
 * Initialize host controls
 */
function initializeHostControls() {
    const startButton = document.getElementById('start-quiz-btn');
    if (startButton) {
        startButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const sessionId = document.getElementById('session-id').value;
            
            fetch('live_quiz_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'start_session',
                    session_id: sessionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI to reflect started session
                    updateHostControls('in_progress');
                }
            })
            .catch(error => {
                console.error('Error starting session:', error);
            });
        });
    }
    
    const nextButton = document.getElementById('next-question-btn');
    if (nextButton) {
        nextButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            const sessionId = document.getElementById('session-id').value;
            
            fetch('live_quiz_control.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'next_question',
                    session_id: sessionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update question status
                    updateQuestionStatus(data.current_question);
                }
            })
            .catch(error => {
                console.error('Error advancing to next question:', error);
            });
        });
    }
    
    const endButton = document.getElementById('end-quiz-btn');
    if (endButton) {
        endButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to end this quiz session?')) {
                const sessionId = document.getElementById('session-id').value;
                
                fetch('live_quiz_control.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'end_session',
                        session_id: sessionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI to reflect ended session
                        updateHostControls('completed');
                    }
                })
                .catch(error => {
                    console.error('Error ending session:', error);
                });
            }
        });
    }
}

/**
 * Update host controls based on session status
 */
function updateHostControls(status) {
    const controlsContainer = document.getElementById('host-controls');
    if (!controlsContainer) return;
    
    if (status === 'waiting') {
        controlsContainer.innerHTML = `
            <button id="start-quiz-btn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Start Quiz
            </button>
        `;
    } else if (status === 'in_progress') {
        controlsContainer.innerHTML = `
            <button id="next-question-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                Next Question
            </button>
            <button id="end-quiz-btn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                End Quiz
            </button>
        `;
    } else if (status === 'completed') {
        controlsContainer.innerHTML = `
            <div class="p-4 bg-gray-100 text-gray-700 rounded-md mb-4">
                This quiz session has ended.
            </div>
            <button id="close-session-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                Close Session & Create New Quiz
            </button>
        `;
    }
    
    // Reinitialize controls
    initializeHostControls();
}

/**
 * Update question status in host view
 */
function updateQuestionStatus(questionId) {
    const questionRows = document.querySelectorAll('.question-row');
    
    questionRows.forEach(row => {
        const rowId = row.getAttribute('data-question-id');
        const statusBadge = row.querySelector('.status-badge');
        
        if (rowId == questionId) {
            // Set as active
            if (statusBadge) {
                statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 status-badge';
                statusBadge.textContent = 'Active';
            }
        } else if (statusBadge && statusBadge.textContent === 'Active') {
            // Mark previously active as completed
            statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 status-badge';
            statusBadge.textContent = 'Completed';
        }
    });
}

/**
 * Initialize join session form
 */
function initializeJoinForm(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const sessionCode = document.getElementById('session_code').value;
        const displayName = document.getElementById('display_name').value;
        
        if (!sessionCode || !displayName) {
            showError('Please enter both session code and display name.');
            return;
        }
        
        fetch('live_quiz_join.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_code: sessionCode,
                display_name: displayName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store session data and update UI
                document.getElementById('session-id').value = data.session_id;
                document.getElementById('display-name').value = data.display_name;
                
                // Update page to show waiting screen
                updateJoinSession(data);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error joining session:', error);
            showError('Network error. Please try again.');
        });
    });
}

/**
 * Update join session page after successful join
 */
function updateJoinSession(data) {
    const joinFormContainer = document.getElementById('join-form-container');
    const quizContainer = document.getElementById('quiz-container');
    
    if (joinFormContainer && quizContainer) {
        // Hide join form
        joinFormContainer.classList.add('hidden');
        
        // Show quiz container
        quizContainer.classList.remove('hidden');
        
        // Initialize quiz based on session status
        if (data.session_status === 'waiting') {
            showWaitingScreen();
        } else if (data.session_status === 'in_progress') {
            loadNewQuestion(data.active_question);
        } else if (data.session_status === 'completed') {
            showQuizCompleted(data);
        }
        
        // Start live updates
        startLiveUpdates();
    }
}

/**
 * Handle socket.io-like event system for real-time updates
 * This simulates socket.io with polling but could be replaced with WebSockets
 */
function setupEventListeners() {
    // Poll for various events
    setInterval(() => {
        const sessionId = document.getElementById('session-id')?.value;
        if (!sessionId) return;
        
        fetch(`live_quiz_events.php?session_id=${sessionId}`)
            .then(response => response.json())
            .then(events => {
                // Process each event
                events.forEach(event => {
                    switch(event.type) {
                        case 'session_started':
                            showWaitingScreen();
                            break;
                        case 'question_started':
                            loadNewQuestion(event.data);
                            break;
                        case 'question_ended':
                            if (document.getElementById('has-answered')?.value === 'true') {
                                showAnsweredState();
                            }
                            break;
                        case 'session_ended':
                            showQuizCompleted(event.data);
                            break;
                        case 'score_updated':
                            updateScore(event.data.score);
                            break;
                        case 'leaderboard_updated':
                            updateLeaderboard();
                            break;
                    }
                });
            })
            .catch(error => {
                console.error('Error polling for events:', error);
            });
    }, 1000);
}

/**
 * Initialize everything when DOM is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeQuizComponents();
    
    // Setup polling updates if in active session
    if (document.getElementById('session-id')) {
        startLiveUpdates();
        setupEventListeners();
    }
});