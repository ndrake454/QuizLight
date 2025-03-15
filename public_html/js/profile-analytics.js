/**
 * Enhanced Profile Analytics
 * 
 * Provides advanced visualizations and insights for user's learning progress
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all charts and visualizations
    initPerformanceOverTime();
    initStrengthWeaknessChart();
    initDifficultyDistribution();
    initLearningGrowthChart();
});

/**
 * Performance Over Time Chart
 * Shows the user's quiz scores trended over time
 */
function initPerformanceOverTime() {
    const container = document.getElementById('performance-trend-chart');
    if (!container || !window.performanceData || !window.performanceData.history) return;
    
    const data = window.performanceData.history;
    if (data.length < 2) {
        container.innerHTML = '<p class="text-gray-500 text-center p-4">Take more quizzes to see your performance trends.</p>';
        return;
    }
    
    // Create SVG for the chart
    const svgWidth = 600;
    const svgHeight = 250;
    const margin = { top: 20, right: 30, bottom: 40, left: 50 };
    const width = svgWidth - margin.left - margin.right;
    const height = svgHeight - margin.top - margin.bottom;
    
    // Find min and max dates for scaling
    const dates = data.map(d => new Date(d.date));
    const minDate = new Date(Math.min.apply(null, dates));
    const maxDate = new Date(Math.max.apply(null, dates));
    
    // Date formatting function
    const formatDate = date => {
        const d = new Date(date);
        return `${d.getMonth()+1}/${d.getDate()}`;
    };
    
    // X and Y scales
    const xScale = date => {
        const range = maxDate - minDate;
        const normalized = new Date(date) - minDate;
        return margin.left + (normalized / range) * width;
    };
    
    const yScale = score => margin.top + height - (height / 100) * score;
    
    // Create line for scores
    let scorePath = `M ${xScale(data[0].date)} ${yScale(data[0].score)}`;
    for (let i = 1; i < data.length; i++) {
        scorePath += ` L ${xScale(data[i].date)} ${yScale(data[i].score)}`;
    }
    
    // Generate the X-axis ticks (we'll show 5 dates evenly distributed)
    const xTicks = [];
    const dateRange = maxDate - minDate;
    for (let i = 0; i <= 4; i++) {
        const tickDate = new Date(minDate.getTime() + (dateRange * (i / 4)));
        xTicks.push({
            x: xScale(tickDate),
            label: formatDate(tickDate)
        });
    }
    
    // Generate SVG content
    const svgContent = `
        <svg width="100%" height="${svgHeight}" viewBox="0 0 ${svgWidth} ${svgHeight}">
            <!-- Grid lines -->
            <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            <line x1="${margin.left}" y1="${margin.top + height}" x2="${margin.left + width}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            
            <!-- Horizontal grid lines -->
            <line x1="${margin.left}" y1="${yScale(25)}" x2="${margin.left + width}" y2="${yScale(25)}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            <line x1="${margin.left}" y1="${yScale(50)}" x2="${margin.left + width}" y2="${yScale(50)}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            <line x1="${margin.left}" y1="${yScale(75)}" x2="${margin.left + width}" y2="${yScale(75)}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            
            <!-- Y-axis labels -->
            <text x="${margin.left - 10}" y="${yScale(0)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">0%</text>
            <text x="${margin.left - 10}" y="${yScale(25)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">25%</text>
            <text x="${margin.left - 10}" y="${yScale(50)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">50%</text>
            <text x="${margin.left - 10}" y="${yScale(75)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">75%</text>
            <text x="${margin.left - 10}" y="${yScale(100)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">100%</text>
            
            <!-- X-axis labels -->
            ${xTicks.map(tick => `
                <text x="${tick.x}" y="${margin.top + height + 20}" text-anchor="middle" fill="#666" font-size="12">${tick.label}</text>
            `).join('')}
            
            <!-- Axis titles -->
            <text x="${margin.left + width/2}" y="${margin.top + height + 35}" text-anchor="middle" fill="#666" font-size="14">Date</text>
            <text x="${margin.left - 35}" y="${margin.top + height/2}" text-anchor="middle" transform="rotate(-90, ${margin.left - 35}, ${margin.top + height/2})" fill="#666" font-size="14">Score</text>
            
            <!-- Score line and area -->
            <path d="${scorePath} L ${xScale(data[data.length-1].date)} ${yScale(0)} L ${xScale(data[0].date)} ${yScale(0)} Z" fill="rgba(79, 70, 229, 0.1)" />
            <path d="${scorePath}" fill="none" stroke="#4F46E5" stroke-width="3" />
            
            <!-- Data points with tooltips -->
            ${data.map((d, i) => `
                <circle cx="${xScale(d.date)}" cy="${yScale(d.score)}" r="5" fill="#4F46E5">
                    <title>${new Date(d.date).toLocaleDateString()}: ${d.score}%</title>
                </circle>
            `).join('')}
        </svg>
    `;
    
    // Create trend info
    const firstScore = data[0].score;
    const lastScore = data[data.length-1].score;
    const scoreDiff = lastScore - firstScore;
    const isImproving = scoreDiff > 0;
    
    const trendInfo = `
        <div class="mt-2 text-sm ${isImproving ? 'text-green-600' : 'text-amber-600'}">
            <p class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isImproving ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'}" />
                </svg>
                ${isImproving 
                    ? `You've improved by ${scoreDiff.toFixed(1)}% over this period!` 
                    : `Your recent scores are ${Math.abs(scoreDiff).toFixed(1)}% lower than earlier. Keep practicing!`}
            </p>
        </div>
    `;
    
    // Assemble the whole chart section
    container.innerHTML = `
        <h3 class="text-lg font-semibold mb-3">Performance Trends</h3>
        <div class="chart-container mb-2">
            ${svgContent}
        </div>
        ${trendInfo}
    `;
}

/**
 * Strength & Weakness Chart
 * Radar chart showing category performance
 */
function initStrengthWeaknessChart() {
    const container = document.getElementById('strength-weakness-chart');
    if (!container || !window.performanceData || !window.performanceData.categories) return;
    
    const categories = window.performanceData.categories;
    if (categories.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center p-4">Take quizzes in more categories to see your strengths and weaknesses.</p>';
        return;
    }

    // Create circle chart
    const svgWidth = 400;
    const svgHeight = 400;
    const centerX = svgWidth / 2;
    const centerY = svgHeight / 2;
    const radius = Math.min(centerX, centerY) - 60;
    
    // Function to convert polar to cartesian coordinates
    const polarToCartesian = (angle, distance) => {
        const radians = (angle - 90) * Math.PI / 180;
        return {
            x: centerX + (distance * Math.cos(radians)),
            y: centerY + (distance * Math.sin(radians))
        };
    };
    
    // Calculate polygon points
    const angleStep = 360 / categories.length;
    const points = categories.map((cat, i) => {
        const angle = i * angleStep;
        const distance = radius * (cat.percentage / 100);
        const point = polarToCartesian(angle, distance);
        return `${point.x},${point.y}`;
    });
    
    // Create radar polygon
    const polygonPoints = points.join(' ');
    
    // Create level circles and axis lines
    const levels = [0.25, 0.5, 0.75, 1];
    const axisLines = categories.map((cat, i) => {
        const angle = i * angleStep;
        const point = polarToCartesian(angle, radius);
        return `<line x1="${centerX}" y1="${centerY}" x2="${point.x}" y2="${point.y}" stroke="#ddd" stroke-width="1" />`;
    }).join('');
    
    const levelCircles = levels.map(level => {
        return `<circle cx="${centerX}" cy="${centerY}" r="${radius * level}" fill="none" stroke="#ddd" stroke-width="1" stroke-dasharray="5,5" />`;
    }).join('');
    
    // Create category labels
    const labels = categories.map((cat, i) => {
        const angle = i * angleStep;
        const point = polarToCartesian(angle, radius + 30);
        return `
            <text x="${point.x}" y="${point.y}" text-anchor="middle" alignment-baseline="middle" fill="#666" font-size="12">
                ${cat.name}
            </text>
        `;
    }).join('');
    
    // Create marker dots
    const dots = categories.map((cat, i) => {
        const angle = i * angleStep;
        const distance = radius * (cat.percentage / 100);
        const point = polarToCartesian(angle, distance);
        return `<circle cx="${point.x}" cy="${point.y}" r="5" fill="#4F46E5" />`;
    }).join('');
    
    // Create the SVG
    const svgContent = `
        <svg width="100%" height="${svgHeight}" viewBox="0 0 ${svgWidth} ${svgHeight}">
            <!-- Background elements -->
            ${levelCircles}
            ${axisLines}
            
            <!-- Data polygon -->
            <polygon points="${polygonPoints}" fill="rgba(79, 70, 229, 0.2)" stroke="#4F46E5" stroke-width="2" />
            
            <!-- Marker dots -->
            ${dots}
            
            <!-- Category labels -->
            ${labels}
            
            <!-- Center dot -->
            <circle cx="${centerX}" cy="${centerY}" r="3" fill="#666" />
            
            <!-- Level labels -->
            <text x="${centerX}" y="${centerY - radius * 0.25}" text-anchor="middle" alignment-baseline="middle" fill="#999" font-size="10">25%</text>
            <text x="${centerX}" y="${centerY - radius * 0.5}" text-anchor="middle" alignment-baseline="middle" fill="#999" font-size="10">50%</text>
            <text x="${centerX}" y="${centerY - radius * 0.75}" text-anchor="middle" alignment-baseline="middle" fill="#999" font-size="10">75%</text>
            <text x="${centerX}" y="${centerY - radius}" text-anchor="middle" alignment-baseline="middle" fill="#999" font-size="10">100%</text>
        </svg>
    `;
    
    // Find strongest and weakest categories
    const sortedCategories = [...categories].sort((a, b) => b.percentage - a.percentage);
    const strongest = sortedCategories[0];
    const weakest = sortedCategories[sortedCategories.length - 1];
    
    // Create strength/weakness insights
    const insightsHtml = `
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-green-50 p-4 rounded-lg">
                <h4 class="font-medium text-green-700 mb-1">Strongest Category</h4>
                <p class="text-sm text-gray-700"><span class="font-bold">${strongest.name}</span> (${strongest.percentage}%)</p>
                <p class="text-xs text-gray-600 mt-1">Keep up the good work in this area!</p>
            </div>
            <div class="bg-amber-50 p-4 rounded-lg">
                <h4 class="font-medium text-amber-700 mb-1">Area for Improvement</h4>
                <p class="text-sm text-gray-700"><span class="font-bold">${weakest.name}</span> (${weakest.percentage}%)</p>
                <p class="text-xs text-gray-600 mt-1">Try focusing more on this topic.</p>
            </div>
        </div>
    `;
    
    // Assemble the whole section
    container.innerHTML = `
        <h3 class="text-lg font-semibold mb-3">Strengths & Weaknesses</h3>
        <div class="flex justify-center">
            ${svgContent}
        </div>
        ${insightsHtml}
    `;
}

/**
 * Difficulty Distribution Chart
 * Shows how user performs across difficulty levels
 */
function initDifficultyDistribution() {
    const container = document.getElementById('difficulty-distribution');
    if (!container || !window.performanceData || !window.performanceData.difficultyLevels) return;
    
    const data = window.performanceData.difficultyLevels;
    if (data.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center p-4">Take more quizzes to see your performance by difficulty level.</p>';
        return;
    }
    
    // Create the bar chart
    const svgWidth = 500;
    const svgHeight = 250;
    const margin = { top: 20, right: 20, bottom: 40, left: 60 };
    const width = svgWidth - margin.left - margin.right;
    const height = svgHeight - margin.top - margin.bottom;
    
    // Bar dimensions
    const barWidth = width / data.length - 10;
    
    // Function to generate a bar
    const createBar = (item, index) => {
        const x = margin.left + (index * (width / data.length)) + (width / data.length - barWidth) / 2;
        const barHeight = (height / 100) * item.percentage;
        const y = margin.top + height - barHeight;
        
        // Color based on difficulty
        let color;
        if (item.difficulty === 'easy') color = '#10B981'; // green
        else if (item.difficulty === 'challenging') color = '#F59E0B'; // amber
        else color = '#EF4444'; // red
        
        return `
            <g>
                <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="${color}" rx="3" />
                <text x="${x + barWidth/2}" y="${y - 5}" text-anchor="middle" fill="#666" font-size="12">${item.percentage}%</text>
                <text x="${x + barWidth/2}" y="${margin.top + height + 20}" text-anchor="middle" fill="#666" font-size="12">${item.difficulty}</text>
            </g>
        `;
    };
    
    // Generate SVG content
    const svgContent = `
        <svg width="100%" height="${svgHeight}" viewBox="0 0 ${svgWidth} ${svgHeight}">
            <!-- Background grid -->
            <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            <line x1="${margin.left}" y1="${margin.top + height}" x2="${margin.left + width}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            
            <!-- Horizontal grid lines and labels -->
            <line x1="${margin.left}" y1="${margin.top + height * 0.25}" x2="${margin.left + width}" y2="${margin.top + height * 0.25}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            <line x1="${margin.left}" y1="${margin.top + height * 0.5}" x2="${margin.left + width}" y2="${margin.top + height * 0.5}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            <line x1="${margin.left}" y1="${margin.top + height * 0.75}" x2="${margin.left + width}" y2="${margin.top + height * 0.75}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            
            <text x="${margin.left - 10}" y="${margin.top + height}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">0%</text>
            <text x="${margin.left - 10}" y="${margin.top + height * 0.75}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">25%</text>
            <text x="${margin.left - 10}" y="${margin.top + height * 0.5}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">50%</text>
            <text x="${margin.left - 10}" y="${margin.top + height * 0.25}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">75%</text>
            <text x="${margin.left - 10}" y="${margin.top}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">100%</text>
            
            <!-- Axis titles -->
            <text x="${margin.left + width/2}" y="${margin.top + height + 35}" text-anchor="middle" fill="#666" font-size="14">Difficulty Level</text>
            <text x="${margin.left - 40}" y="${margin.top + height/2}" text-anchor="middle" transform="rotate(-90, ${margin.left - 40}, ${margin.top + height/2})" fill="#666" font-size="14">Success Rate</text>
            
            <!-- Bars -->
            ${data.map((item, index) => createBar(item, index)).join('')}
        </svg>
    `;
    
    // Create insights
    const highestSuccessLevel = [...data].sort((a, b) => b.percentage - a.percentage)[0];
    const lowestSuccessLevel = [...data].sort((a, b) => a.percentage - b.percentage)[0];
    
    const insightHtml = `
        <div class="mt-3 text-sm text-gray-700">
            <p>You perform best on <strong>${highestSuccessLevel.difficulty}</strong> questions (${highestSuccessLevel.percentage}% success rate).</p>
            <p class="mt-1">For <strong>${lowestSuccessLevel.difficulty}</strong> questions, your success rate is ${lowestSuccessLevel.percentage}%.</p>
        </div>
    `;
    
    // Assemble the section
    container.innerHTML = `
        <h3 class="text-lg font-semibold mb-3">Performance by Difficulty</h3>
        <div class="chart-container mb-2">
            ${svgContent}
        </div>
        ${insightHtml}
    `;
}

/**
 * Learning Growth Chart
 * Shows the user's improvement on repeated attempts of similar questions
 */
function initLearningGrowthChart() {
    const container = document.getElementById('learning-growth');
    if (!container || !window.performanceData || !window.performanceData.growth) return;
    
    const data = window.performanceData.growth;
    if (data.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center p-4">Keep practicing to see your learning growth.</p>';
        return;
    }
    
    // Create the line chart
    const svgWidth = 500;
    const svgHeight = 250;
    const margin = { top: 20, right: 30, bottom: 40, left: 50 };
    const width = svgWidth - margin.left - margin.right;
    const height = svgHeight - margin.top - margin.bottom;
    
    // Scales
    const xScale = attempt => margin.left + ((width) / (data.length - 1)) * (attempt - 1);
    const yScale = score => margin.top + height - (height / 100) * score;
    
    // Create the line path
    let linePath = `M ${xScale(1)} ${yScale(data[0].score)}`;
    for (let i = 1; i < data.length; i++) {
        linePath += ` L ${xScale(i+1)} ${yScale(data[i].score)}`;
    }
    
    // Generate SVG content
    const svgContent = `
        <svg width="100%" height="${svgHeight}" viewBox="0 0 ${svgWidth} ${svgHeight}">
            <!-- Background grid -->
            <line x1="${margin.left}" y1="${margin.top}" x2="${margin.left}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            <line x1="${margin.left}" y1="${margin.top + height}" x2="${margin.left + width}" y2="${margin.top + height}" stroke="#ddd" stroke-width="1" />
            
            <!-- Horizontal grid lines -->
            <line x1="${margin.left}" y1="${yScale(25)}" x2="${margin.left + width}" y2="${yScale(25)}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            <line x1="${margin.left}" y1="${yScale(50)}" x2="${margin.left + width}" y2="${yScale(50)}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            <line x1="${margin.left}" y1="${yScale(75)}" x2="${margin.left + width}" y2="${yScale(75)}" stroke="#eee" stroke-width="1" stroke-dasharray="5,5" />
            
            <!-- Y-axis labels -->
            <text x="${margin.left - 10}" y="${yScale(0)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">0%</text>
            <text x="${margin.left - 10}" y="${yScale(25)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">25%</text>
            <text x="${margin.left - 10}" y="${yScale(50)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">50%</text>
            <text x="${margin.left - 10}" y="${yScale(75)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">75%</text>
            <text x="${margin.left - 10}" y="${yScale(100)}" text-anchor="end" alignment-baseline="middle" fill="#666" font-size="12">100%</text>
            
            <!-- X-axis labels (attempts) -->
            ${data.map((d, i) => `
                <text x="${xScale(i+1)}" y="${margin.top + height + 20}" text-anchor="middle" fill="#666" font-size="12">Try ${i+1}</text>
            `).join('')}
            
            <!-- Axis titles -->
            <text x="${margin.left + width/2}" y="${margin.top + height + 35}" text-anchor="middle" fill="#666" font-size="14">Attempt Number</text>
            <text x="${margin.left - 35}" y="${margin.top + height/2}" text-anchor="middle" transform="rotate(-90, ${margin.left - 35}, ${margin.top + height/2})" fill="#666" font-size="14">Score</text>
            
            <!-- Growth line and area -->
            <path d="${linePath} L ${xScale(data.length)} ${yScale(0)} L ${xScale(1)} ${yScale(0)} Z" fill="rgba(79, 70, 229, 0.1)" />
            <path d="${linePath}" fill="none" stroke="#4F46E5" stroke-width="3" />
            
            <!-- Data points -->
            ${data.map((d, i) => `
                <circle cx="${xScale(i+1)}" cy="${yScale(d.score)}" r="5" fill="#4F46E5" />
            `).join('')}
        </svg>
    `;
    
    // Calculate growth metrics
    const initialScore = data[0].score;
    const currentScore = data[data.length-1].score;
    const improvement = currentScore - initialScore;
    const improvementPercentage = initialScore > 0 ? (improvement / initialScore) * 100 : 0;
    
    // Create insights
    const insightHtml = `
        <div class="mt-3 text-sm">
            <p class="text-gray-700">Your score improved from <strong>${initialScore}%</strong> to <strong>${currentScore}%</strong> over ${data.length} attempts.</p>
            <p class="text-green-600 font-medium mt-1">
                That's a ${improvementPercentage.toFixed(1)}% improvement in performance!
            </p>
        </div>
    `;
    
    // Assemble the section
    container.innerHTML = `
        <h3 class="text-lg font-semibold mb-3">Learning Growth</h3>
        <div class="chart-container mb-2">
            ${svgContent}
        </div>
        ${insightHtml}
    `;
}