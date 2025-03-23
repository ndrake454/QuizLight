<!-- app/views/admin/dashboard.php -->
<div class="py-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Users Stat -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <div class="text-gray-500 text-sm">Total Users</div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $totalUsers; ?></div>
                </div>
            </div>
            <div class="mt-4">
                <a href="/admin/users" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    View All Users &rarr;
                </a>
            </div>
        </div>
        
        <!-- Categories Stat -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <div>
                    <div class="text-gray-500 text-sm">Total Categories</div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $totalCategories; ?></div>
                </div>
            </div>
            <div class="mt-4">
                <a href="/admin/categories" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    Manage Categories &rarr;
                </a>
            </div>
        </div>
        
        <!-- Questions Stat -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-gray-500 text-sm">Total Questions</div>
                    <div class="text-2xl font-bold text-gray-800"><?php echo $totalQuestions; ?></div>
                </div>
            </div>
            <div class="mt-4">
                <a href="/admin/questions" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    Manage Questions &rarr;
                </a>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Quizzes -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Recent Quiz Attempts</h2>
            </div>
            <div class="px-6 py-4">
                <?php if (empty($recentQuizzes)): ?>
                    <p class="text-gray-500 text-center py-4">No recent quiz attempts found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categories</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentQuizzes as $quiz): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($quiz['first_name'] . ' ' . $quiz['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($quiz['email']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($quiz['category_names'] ?? ''); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                  <?php
                                                  $accuracy = ($quiz['correct_answers'] / $quiz['total_questions']) * 100;
                                                  if ($accuracy >= 80) {
                                                      echo 'bg-green-100 text-green-800';
                                                  } elseif ($accuracy >= 50) {
                                                      echo 'bg-yellow-100 text-yellow-800';
                                                  } else {
                                                      echo 'bg-red-100 text-red-800';
                                                  }
                                                  ?>">
                                                <?php echo $quiz['correct_answers'] . '/' . $quiz['total_questions']; ?>
                                                (<?php echo round($accuracy); ?>%)
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($quiz['completed_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- New Users and Top Categories -->
        <div class="grid grid-rows-2 gap-6">
            <!-- New Users -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">New Users</h2>
                </div>
                <div class="px-6 py-4">
                    <?php if (empty($recentUsers)): ?>
                        <p class="text-gray-500 text-center py-4">No recent users found.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentUsers as $user): ?>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                    </div>
                                    <div class="ml-auto text-xs text-gray-500">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Categories -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Top Categories</h2>
                </div>
                <div class="px-6 py-4">
                    <?php if (empty($topCategories)): ?>
                        <p class="text-gray-500 text-center py-4">No categories data available.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($topCategories as $category): ?>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <div class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($category['name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $category['num_questions']; ?> questions</div>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <?php 
                                        // Calculate percentage for the progress bar (based on max answers)
                                        $maxAnswers = max(array_column($topCategories, 'num_answers'));
                                        $percentage = $maxAnswers > 0 ? ($category['num_answers'] / $maxAnswers) * 100 : 0;
                                        ?>
                                        <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo $category['num_answers']; ?> answers</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>