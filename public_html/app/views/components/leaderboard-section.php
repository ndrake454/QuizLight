<!-- app/views/components/leaderboard-section.php -->
<!-- Top Performers Container - With max-width to not span full screen -->
<div class="flex flex-col md:flex-row gap-6 justify-center mt-8">
    <!-- This Week's Top Performers Section -->
    <div class="max-w-xl w-full rounded-lg shadow-md overflow-hidden quiz-card">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-4 px-6 text-white">
            <h2 class="text-xl font-bold">This Week's Top Performers</h2>
            <p class="text-sm opacity-75">Leaderboard for <?php echo isset($thisWeekPerformers[0]['date_range']) ? $thisWeekPerformers[0]['date_range'] : date('M j', strtotime('monday this week')) . ' - ' . date('M j, Y', strtotime('sunday this week')); ?></p>
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
                    <?php if (!empty($thisWeekPerformers)): ?>
                        <?php foreach ($thisWeekPerformers as $index => $performer): ?>
                            <tr class="<?php echo $index % 2 === 0 ? 'bg-gray-50' : 'bg-white'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($index === 0): ?>
                                        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 font-bold">1st</span>
                                    <?php elseif ($index === 1): ?>
                                        <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800 font-bold">2nd</span>
                                    <?php elseif ($index === 2): ?>
                                        <span class="px-3 py-1 rounded-full bg-yellow-50 text-yellow-700 font-bold">3rd</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-600 font-bold"><?php echo $index + 1; ?>th</span>
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
        
        <?php if (isLoggedIn() && isset($userComparison) && isset($userComparison['current'])): ?>
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
        <?php elseif (isLoggedIn()): ?>
            <div class="p-4 text-center bg-indigo-50 border-t border-indigo-100">
                <p class="text-gray-700">Take quizzes to appear on the leaderboard!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Last Week's Champion - Redesigned as a trophy card -->
    <div class="max-w-sm w-full">
        <?php if (!empty($lastWeekPerformers)): ?>
            <div class="bg-gradient-to-b from-amber-50 to-amber-100 rounded-lg shadow-md overflow-hidden border border-amber-200">
                <!-- Champion header -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 py-4 px-6 text-white">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold">Last Week's Champion</h2>
                    </div>
                    <p class="text-sm opacity-75"><?php echo isset($lastWeekPerformers[0]['date_range']) ? $lastWeekPerformers[0]['date_range'] : date('M j', strtotime('monday last week')) . ' - ' . date('M j, Y', strtotime('sunday last week')); ?></p>
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
                        
                        if (isLoggedIn() && $lastWeekPerformers[0]['id'] == $_SESSION['user_id']): 
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