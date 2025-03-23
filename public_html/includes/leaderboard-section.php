<?php
// Get top performers for this week and last week
$thisWeekPerformers = getTopPerformers($pdo, 0, 3);
$lastWeekPerformers = getTopPerformers($pdo, 1, 3);

// Get date ranges for display
$thisWeekDateRange = date('M j', strtotime('monday this week')) . ' - ' . date('M j, Y', strtotime('sunday this week'));
$lastWeekDateRange = date('M j', strtotime('monday last week')) . ' - ' . date('M j, Y', strtotime('sunday last week'));

// Get current user's performance comparison if logged in
$userComparison = null;
if (isLoggedIn()) {
    $userComparison = compareUserWeeklyPerformance($pdo, $_SESSION['user_id']);
}
?>

<!-- Top Performers Container - With max-width to not span full screen -->
<div class="flex flex-col md:flex-row gap-6 justify-center mt-8">
    <!-- This Week's Top Performers Section -->
    <div class="max-w-xl w-full rounded-lg shadow-md overflow-hidden quiz-card">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-4 px-6 text-white">
            <h2 class="text-xl font-bold">This Week's Top Performers</h2>
            <p class="text-sm opacity-75">Leaderboard for <?php echo $thisWeekDateRange; ?></p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RANK</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NAME</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            MASTERY SCORE
                            <button class="text-xs text-gray-500 ml-1 hover:text-indigo-600 underline cursor-help" @click="showMasteryModal = true">(How?)</button>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($thisWeekPerformers) > 0): ?>
                        <?php foreach ($thisWeekPerformers as $index => $performer): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'bg-gray-50' : 'bg-white'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($index === 0): ?>
                                        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 font-bold">1st</span>
                                    <?php elseif ($index === 1): ?>
                                        <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800 font-bold">2nd</span>
                                    <?php elseif ($index === 2): ?>
                                        <span class="px-3 py-1 rounded-full bg-yellow-50 text-yellow-700 font-bold">3rd</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($performer['first_name'] . ' ' . substr($performer['last_name'], 0, 1) . '.'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-medium text-indigo-600"><?php echo number_format($performer['mastery_score']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                                No data available for this week yet
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isLoggedIn() && $userComparison && $userComparison['current']): ?>
            <div class="p-4 text-center bg-indigo-50 border-t border-indigo-100">
                <?php
                // User's current rank information
                $currentRank = $userComparison['current']['rank'];
                $currentScore = $userComparison['current']['mastery_score'];
                
                if ($currentRank <= 3):
                ?>
                    <p class="text-indigo-700 font-medium">Congratulations! You're currently ranked #<?php echo $currentRank; ?> with a mastery score of <?php echo number_format($currentScore); ?>!</p>
                <?php else: ?>
                    <p class="text-indigo-700">Your current rank: #<?php echo $currentRank; ?> with a mastery score of <?php echo number_format($currentScore); ?>.</p>
                    <p class="text-gray-700 mt-1">Keep practicing to climb the leaderboard!</p>
                <?php endif; ?>
            </div>
            
            <!-- Performance Comparison -->
            <div class="px-4 pb-4 text-center">
                <div class="mt-2 text-sm">
                    <?php 
                    $scoreChange = $userComparison['score_change'];
                    $rankChange = $userComparison['rank_change'];
                    
                    if ($userComparison['previous']): 
                    ?>
                        <?php if ($scoreChange > 0): ?>
                            <span class="text-green-600 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                                You're up <?php echo number_format($scoreChange); ?> points from last week!
                            </span>
                        <?php elseif ($scoreChange < 0): ?>
                            <span class="text-yellow-600 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                                </svg>
                                You're down <?php echo number_format(abs($scoreChange)); ?> points from last week.
                            </span>
                        <?php else: ?>
                            <span class="text-blue-600 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                                </svg>
                                You're maintaining the same score as last week.
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($rankChange > 0): ?>
                            <span class="ml-2 text-green-600 font-medium">
                                (Moved up <?php echo abs($rankChange); ?> <?php echo abs($rankChange) === 1 ? 'position' : 'positions'; ?>)
                            </span>
                        <?php elseif ($rankChange < 0): ?>
                            <span class="ml-2 text-yellow-600 font-medium">
                                (Moved down <?php echo abs($rankChange); ?> <?php echo abs($rankChange) === 1 ? 'position' : 'positions'; ?>)
                            </span>
                        <?php else: ?>
                            <span class="ml-2 text-blue-600 font-medium">
                                (Same position)
                            </span>
                        <?php endif; ?>
                        
                    <?php elseif ($userComparison['current']): ?>
                        <span class="text-gray-600">
                            This is your first week on the leaderboard. Keep up the good work!
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (isLoggedIn()): ?>
            <div class="p-4 text-center bg-indigo-50 border-t border-indigo-100">
                <p class="text-gray-700">Take quizzes to appear on the leaderboard!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Last Week's Champion - Redesigned as a trophy card -->
    <div class="max-w-sm w-full">
        <?php if (count($lastWeekPerformers) > 0): ?>
            <div class="bg-gradient-to-b from-amber-50 to-amber-100 rounded-lg shadow-md overflow-hidden border border-amber-200">
                <!-- Champion header -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 py-4 px-6 text-white">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold">Last Week's Champion</h2>
                    </div>
                    <p class="text-sm opacity-75"><?php echo $lastWeekDateRange; ?></p>
                </div>
                
                <!-- Champion content -->
                <div class="p-6 text-center">
                    <!-- Trophy icon -->
                    <div class="mb-4 flex justify-center">
                        <div class="w-24 h-24 rounded-full bg-amber-200 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z" />
                            </svg>
                        </div>
                    </div>
                    
                    <!-- Champion name -->
                    <h3 class="text-2xl font-bold text-amber-800 mb-1">
                        <?php echo htmlspecialchars($lastWeekPerformers[0]['first_name'] . ' ' . substr($lastWeekPerformers[0]['last_name'], 0, 1) . '.'); ?>
                    </h3>
                    
                    <!-- Score badge -->
                    <div class="inline-block px-4 py-2 bg-amber-200 rounded-full text-amber-800 font-bold text-lg mb-4">
                        <?php echo number_format($lastWeekPerformers[0]['mastery_score']); ?> points
                    </div>
                    
                    <!-- Congratulatory message -->
                    <p class="text-amber-700">
                        <?php 
                        $firstName = $lastWeekPerformers[0]['first_name'];
                        
                        if ($lastWeekPerformers[0]['id'] == $_SESSION['user_id'] ?? 0): 
                        ?>
                            Congratulations on your victory last week!
                        <?php else: ?>
                            <?php echo $firstName; ?> topped the leaderboard with exceptional performance!
                        <?php endif; ?>
                    </p>
                </div>
                
                
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden p-6 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-700 mb-2">Last Week's Champion</h3>
                <p class="text-gray-500">No data available for last week</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mastery Score Information Modal (hidden by default) -->
<div x-show="showMasteryModal" 
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
     x-cloak>
    <div class="bg-white rounded-lg max-w-md w-full p-6 relative">
        <button @click="showMasteryModal = false" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        <h3 class="text-xl font-bold text-gray-800 mb-4">How Mastery Score Works</h3>
        <p class="text-gray-600 mb-4">
            The mastery score is calculated using three factors:
        </p>
        <div class="space-y-3 mb-4">
            <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5 text-indigo-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-medium text-gray-800">Number of Questions</span>
                    <p class="text-sm text-gray-600">The total number of questions you've answered.</p>
                </div>
            </div>
            <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5 text-indigo-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-medium text-gray-800">Accuracy</span>
                    <p class="text-sm text-gray-600">The percentage of questions you answered correctly.</p>
                </div>
            </div>
            <div class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5 text-indigo-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-medium text-gray-800">Difficulty Multiplier</span>
                    <p class="text-sm text-gray-600">Higher difficulty questions contribute more to your score.</p>
                </div>
            </div>
        </div>
        <p class="text-gray-700 p-3 bg-indigo-50 rounded-md">
            Mastery Score = Questions × Accuracy × Difficulty Multiplier
        </p>
    </div>
</div>