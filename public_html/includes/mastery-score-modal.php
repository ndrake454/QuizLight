<!-- Mastery Score Modal 
     Note: This file should be included within a div with x-data="{ showMasteryModal: false }" -->

<!-- Modal container with backdrop - this entire div handles the outside click -->
<div 
    x-show="showMasteryModal" 
    @click="showMasteryModal = false"
    class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center"
    style="background-color: rgba(0, 0, 0, 0.5);"
    x-transition:enter="ease-out duration-300" 
    x-transition:enter-start="opacity-0" 
    x-transition:enter-end="opacity-100" 
    x-transition:leave="ease-in duration-200" 
    x-transition:leave-start="opacity-100" 
    x-transition:leave-end="opacity-0">
    
    <!-- Modal panel - stop propagation to prevent closing when clicking inside the modal -->
    <div 
        @click.stop
        class="relative bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full sm:p-6 mx-4"
        x-transition:enter="ease-out duration-300" 
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
        x-transition:leave="ease-in duration-200" 
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        
        <!-- Modal Content -->
        <div>
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div class="mt-3 text-center sm:mt-5">
                <h3 class="text-lg font-medium leading-6 text-gray-900">How Mastery Score is Calculated</h3>
                <div class="mt-4 text-left">
                    <p class="text-sm text-gray-500 mb-4">
                        The mastery score is a measure of your quiz performance that takes into account three key factors:
                    </p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-6 w-6 text-indigo-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-gray-900">Question Volume</h4>
                                <p class="mt-1 text-xs text-gray-500">The total number of questions you've answered during the time period.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-6 w-6 text-green-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-gray-900">Accuracy</h4>
                                <p class="mt-1 text-xs text-gray-500">The percentage of questions you answered correctly (higher accuracy = higher score).</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-6 w-6 text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-gray-900">Types of Quizzes</h4>
                                <p class="mt-1 text-xs text-gray-500">Utilizing a more diverse set of test taking features, with a variety of questions will also boost your score.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Score Range Interpretations -->
                    <div class="mt-6 bg-gray-50 p-4 rounded-md">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">What Your Mastery Score Means</h4>
                        <div class="space-y-3 text-xs">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-green-400 mr-2"></div>
                                <div class="flex-1">
                                    <span class="font-medium">> 750,000</span>: 
                                    <span class="text-gray-600">Exceptional dedication - you're in the top tier of learners!</span>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-yellow-400 mr-2"></div>
                                <div class="flex-1">
                                    <span class="font-medium">100,000 - 750,000</span>: 
                                    <span class="text-gray-600">Regular studying with good engagement</span>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-gray-300 mr-2"></div>
                                <div class="flex-1">
                                    <span class="font-medium">< 100,000</span>: 
                                    <span class="text-gray-600">Beginner level - welcome to your learning journey!</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action buttons -->
        <div class="mt-5 sm:mt-6">
            <button type="button" 
                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:text-sm"
                    @click="showMasteryModal = false">
                Got it, thanks!
            </button>
        </div>
        
        <!-- Close button in top-right corner -->
        <button type="button" 
                class="absolute top-3 right-3 text-red-500 hover:text-red-700"
                @click="showMasteryModal = false">
            <span class="sr-only">Close</span>
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>