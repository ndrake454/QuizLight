<?php
require_once 'config.php';
$pageTitle = 'About';
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Hero Section -->
    <div class="bg-gradient-to-br from-blue-900 via-indigo-800 to-purple-800 text-white py-12 px-6 rounded-lg shadow-xl mb-12">
        <h1 class="text-3xl md:text-4xl font-bold mb-4 text-center">About <?php echo $site_name; ?></h1>
        <p class="text-xl text-center max-w-3xl mx-auto">
            
        </p>
    </div>
    
    <!-- Features Section -->
    <div class="mb-16">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Key Features</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Feature 1 -->
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Adaptive Learning</h3>
                <p class="text-gray-600">QuizLight adapts to your skill level in real-time, providing questions that are challenging but achievable to maximize your learning potential.</p>
            </div>
            
            <!-- Feature 2 -->
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Multiple Question Types</h3>
                <p class="text-gray-600">From multiple choice to written response, you'll get diverse question formats keep learning engaging and test different aspects of your knowledge.</p>
            </div>
            
            <!-- Feature 3 -->
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Detailed Analytics</h3>
                <p class="text-gray-600">Track your progress with comprehensive analytics and insights, helping you understand your strengths and focus on areas needing improvement.</p>
            </div>
            
            <!-- Feature 4 -->
            <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Spaced Repetition</h3>
                <p class="text-gray-600">I've implemented scientifically-proven spaced repetition techniques to help you retain information.</p>
            </div>
        </div>
    </div>
    
    <!-- Technology Section -->
    <div class="mb-16">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Technology</h2>
        
        <div class="bg-white p-6 rounded-lg shadow-md quiz-card">
                <h3 class="text-xl font-semibold mb-4">This platform is built using modern web technologies to ensure a fast, responsive, and secure experience:</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Tech 1 -->
                <div class="p-4 bg-gray-50 rounded-lg text-center">
                    <div class="text-indigo-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                    </div>
                    <h5 class="font-medium">PHP</h5>
                </div>
                
                <!-- Tech 2 -->
                <div class="p-4 bg-gray-50 rounded-lg text-center">
                    <div class="text-indigo-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    <h5 class="font-medium">Alpine.js</h5>
                </div>
                
                <!-- Tech 3 -->
                <div class="p-4 bg-gray-50 rounded-lg text-center">
                    <div class="text-indigo-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </div>
                    <h5 class="font-medium">Tailwind CSS</h5>
                </div>
                
                <!-- Tech 4 -->
                <div class="p-4 bg-gray-50 rounded-lg text-center">
                    <div class="text-indigo-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                    </div>
                    <h5 class="font-medium">MySQL</h5>
                </div>
            </div>
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg shadow-sm border border-indigo-100 my-8">
    <div class="flex items-center">
        <div class="flex-shrink-0 mr-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
            </svg>
        </div>
        <div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Open Source Project</h3>
            <p class="text-gray-700">
                The entirety of this site is free and open source. If you would like to repurpose the technology behind QuizLight, you can find the source code on my GitHub.
            </p>
            <a href="https://github.com/ndrake454/QuizLight" target="_blank" class="inline-flex items-center mt-3 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors duration-150 shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                </svg>
                View on GitHub
            </a>
        </div>
    </div>
</div>
            <div class="bg-white p-6 rounded-lg shadow-md quiz-card">
    <h3 class="text-xl font-semibold mb-4">Scientific Principles Behind QuizLight</h3>
    
    <div class="space-y-4">       
        <div class="flex">
        <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-600 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <h4 class="font-bold text-red-800">Leitner System</h4>
                <p class="text-gray-700">Developed by Sebastian Leitner in the 1970s, this method uses spaced repetition with different "boxes" or levels of material. Items answered correctly move to higher difficulty levels, while incorrect items return to earlier levels. My adaptive algorithm implements a similar concept by adjusting difficulty based on performance.</p>
                                                    <p>
                    <a href="https://subjectguides.york.ac.uk/study-revision/leitner-system" 
                    target="_blank" 
                    class="text-indigo-600 hover:text-indigo-800 underline">
                        The Leitner System
                    </a>
                </p>
            </div>
        </div><br>

<div class="flex">
    <div class="flex-shrink-0 w-12 h-12 bg-teal-100 rounded-full flex items-center justify-center text-teal-600 mr-4">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
    </div>
    <div>
        <h4 class="font-bold text-teal-800">Metacognition</h4>
        <p class="text-gray-700">Metacognition is the awareness and regulation of your own thinking processes. By monitoring, evaluating, and adjusting how you learn, metacognition refines comprehension, problem-solving, and self-directed learning</p>
        <p>
            <a href="https://www.lifescied.org/doi/10.1187/cbe.12-03-0033" 
            target="_blank" 
            class="text-indigo-600 hover:text-indigo-800 underline">
                Promoting Student Metacognition
            </a>
        </p>
    </div>
</div><br>

        <div class="flex">
            <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
            <div>
                <h4 class="font-bold text-purple-800">Zone of Proximal Development (ZPD)</h4>
                <p class="text-gray-700">Originally proposed by Vygotsky, this theory suggests optimal learning occurs when challenges are just beyond a learner's current ability level. QuizLight demonstrates this by adjusting difficulty upward (by 0.5) when answers are correct and downward when incorrect.</p>
                            <p>
                    <a href="https://eric.ed.gov/?id=EJ1081990" 
                    target="_blank" 
                    class="text-indigo-600 hover:text-indigo-800 underline">
                        Vygotsky's Zone of Proximal Development: Instructional Implications...
                    </a>
                </p>
            </div>
        </div><br>
        
        <div class="flex">
            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h4 class="font-bold text-blue-800">Spaced Repetition</h4>
                <p class="text-gray-700">The QuizLight system implements aspects of spaced repetition by tracking user performance and adapting future content accordingly, which leverages the spacing effect in memory formation.</p>
                                        <p>
                    <a href="https://www.pnas.org/doi/abs/10.1073/pnas.1815156116" 
                    target="_blank" 
                    class="text-indigo-600 hover:text-indigo-800 underline">
                        Enhancing human learning via spaced repetition optimization
                    </a>
                </p>
            </div>
        </div><br>
        
        <div class="flex">
            <div class="flex-shrink-0 w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 mr-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </div>
            <div>
                <h4 class="font-bold text-yellow-800">Dynamic Assessment</h4>
                <p class="text-gray-700">QuizLight continually reassesses user ability and adjusts accordingly, rather than using static, fixed assessments.</p>
                                                    <p>
                    <a href="https://www.tandfonline.com/doi/abs/10.1080/00131910303253" 
                    target="_blank" 
                    class="text-indigo-600 hover:text-indigo-800 underline">
                        Dynamic Assessment in Educational Settings: Realising potential
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
    
    <!-- Team Section -->
    <div class="mb-16">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Creator</h2>
        
        <div class="bg-white p-6 rounded-lg shadow-md quiz-card">      
                <!-- Team Member 2 -->
                <div class="text-center">
                    <div class="w-24 h-24 bg-indigo-100 rounded-full mx-auto mb-3 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold">Nathan Drake</h4>
                      <p class="text-gray-700 mb-6">
            Everything available here is made by me, if anyone else joins in the future I'll add them here.    
            </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact CTA -->
    <div class="mb-16">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-8 rounded-lg shadow-xl text-center">
            <h2 class="text-2xl font-bold mb-4">Have Questions or Feedback?</h2>
            <p class="mb-6">I'd love to hear from you!</p>
            <a href="mailto:admin@quizlight.org" class="inline-block bg-white text-indigo-700 font-bold py-3 px-6 rounded-lg hover:bg-gray-100 transition duration-200">
                Contact Me
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>