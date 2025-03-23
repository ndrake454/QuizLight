/**
 * Adaptive Quiz Progress Chart
 * 
 * Self-contained visualization of the user's progress through an adaptive quiz
 * No external dependencies required
 */

document.addEventListener('DOMContentLoaded', function() {
    const chartContainer = document.getElementById('adaptive-progress-chart');
    if (!chartContainer || !window.adaptiveQuizData) return;
    
    // Format data for the chart
    const quizData = window.adaptiveQuizData;
    const chartData = quizData.map((question, index) => ({
        questionNumber: index + 1,
        difficulty: parseFloat(question.difficulty_value || 3.0),
        isCorrect: question.is_correct ? true : false
    }));
    
    // Calculate statistics
    const totalQuestions = chartData.length;
    const averageDifficulty = chartData.reduce((sum, item) => sum + item.difficulty, 0) / totalQuestions;
    const maxDifficulty = Math.max(...chartData.map(item => item.difficulty));
    const correctAnswers = chartData.filter(item => item.isCorrect).length;
    const correctPercentage = Math.round((correctAnswers / totalQuestions) * 100);
    
    // Create stats display
    const statsHtml = `
        <div class="flex flex-wrap justify-between mb-6 gap-2">
            <div class="bg-blue-50 p-4 rounded-lg shadow-sm flex-1 text-center">
                <p class="text-sm text-gray-600 mb-1">Average Difficulty:</p>
                <p class="text-2xl font-bold text-blue-600">${averageDifficulty.toFixed(1)}</p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg shadow-sm flex-1 text-center">
                <p class="text-sm text-gray-600 mb-1">Correct Answers:</p>
                <p class="text-2xl font-bold text-green-600">${correctPercentage}%</p>
            </div>
            <div class="bg-indigo-50 p-4 rounded-lg shadow-sm flex-1 text-center">
                <p class="text-sm text-gray-600 mb-1">Max Difficulty:</p>
                <p class="text-2xl font-bold text-indigo-600">${maxDifficulty.toFixed(1)}</p>
            </div>
        </div>
    `;
    
    // Create SVG chart
    const svgWidth = 600;
    const svgHeight = 300;
    const margin = { top: 30, right: 30, bottom: 50, left: 50 };
    const width = svgWidth - margin.left - margin.right;
    const height = svgHeight - margin.top - margin.bottom;
    
    // X and Y scales
    const xScale = value => margin.left + (width / (totalQuestions - 1 || 1)) * (value - 1);
    const yScale = value => margin.top + height - (height / 4) * (value - 1); // Scale from 1-5
    
    // Create line path for difficulty
    let linePath = `M ${xScale(1)} ${yScale(chartData[0].difficulty)}`;
    for (let i = 1; i < chartData.length; i++) {
        linePath += ` L ${xScale(i+1)} ${yScale(chartData[i].difficulty)}`;
    }
    
    // Create SVG content
    const svgHtml = `
        <svg width="100%" height="${svgHeight}" viewBox="0 0 ${svgWidth} ${svgHeight}" style="overflow: visible;">
            <!-- Grid lines -->
            <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            <line x1="${margin.left}" y1="${margin.top + height}" x2="${margin.left + width}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            
            <!-- Y-axis difficulty labels -->
            <text x="${margin.left - 10}" y="${yScale(1)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">1</text>
            <text x="${margin.left - 10}" y="${yScale(2)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">2</text>
            <text x="${margin.left - 10}" y="${yScale(3)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">3</text>
            <text x="${margin.left - 10}" y="${yScale(4)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">4</text>
            <text x="${margin.left - 10}" y="${yScale(5)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">5</text>
            
            <!-- X-axis question labels -->
            ${chartData.map((d, i) => 
                `<text x="${xScale(i+1)}" y="${margin.top + height + 20}" text-anchor="middle" fill="#666" font-size="12">Q${i+1}</text>`
            ).join('')}
            
            <!-- Axis labels -->
            <text x="${margin.left + width/2}" y="${margin.top + height + 40}" text-anchor="middle" fill="#666" font-size="14">Question Number</text>
            <text x="${margin.left - 35}" y="${margin.top + height/2}" text-anchor="middle" transform="rotate(-90, ${margin.left - 35}, ${margin.top + height/2})" fill="#666" font-size="14">Difficulty</text>
            
            <!-- Reference line at 3.0 -->
            <line x1="${margin.left}" y1="${yScale(3)}" x2="${margin.left + width}" y2="${yScale(3)}" stroke="#aaa" stroke-width="1" stroke-dasharray="5,5" />
            <text x="${margin.left - 10}" y="${yScale(3)}" text-anchor="end" alignment-baseline="middle" fill="#888" font-size="10">avg</text>
            
            <!-- Difficulty line -->
            <path d="${linePath}" fill="none" stroke="#6366F1" stroke-width="3" />
            
            <!-- Result dots -->
            ${chartData.map((d, i) => 
                `<circle cx="${xScale(i+1)}" cy="${yScale(d.difficulty)}" r="6" fill="${d.isCorrect ? '#10B981' : '#EF4444'}" />`
            ).join('')}
        </svg>
    `;
    
    // Create legend
    const legendHtml = `
        <div class="flex justify-center items-center gap-4 mt-4 mb-2">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-blue-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-700">Difficulty Level</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-700">Correct</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-red-500 rounded-full mr-2"></div>
                <span class="text-sm text-gray-700">Incorrect</span>
            </div>
        </div>
    `;
    
    // Description
    const descriptionHtml = `
        <div class="text-sm text-gray-600 mt-4">
            <p>This chart shows how question difficulty adapted during your quiz, reflecting your performance.</p>
            <p>Green dots represent correct answers; red dots represent incorrect answers.</p>
        </div>
    `;
    
    // Add content to container
    chartContainer.innerHTML = `
        <div class="p-6 rounded-lg shadow-md border border-gray-200 bg-white">
            <h3 class="text-xl font-semibold mb-4">Your Adaptive Quiz Progress</h3>
            ${statsHtml}
            <div class="chart-container" style="position: relative; height: ${svgHeight}px; width: 100%;">
                ${svgHtml}
            </div>
            ${legendHtml}
            ${descriptionHtml}
        </div>
    `;
});