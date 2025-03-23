/**
 * Enhanced Profile Analytics
 * 
 * Provides advanced visualizations and insights for user's learning progress
 */

document.addEventListener('DOMContentLoaded', function() {
    // Debug: Check if performance data is available
    console.log("Performance data:", window.performanceData);
    
    // Initialize all charts and visualizations
    initStrengthWeaknessChart();
});

/**
 * Strength & Weakness Chart
 * Radar chart showing category performance
 */
function initStrengthWeaknessChart() {
    const container = document.getElementById('strength-weakness-chart');
    if (!container) {
        console.error("Cannot find strength-weakness-chart container");
        return;
    }
    
    if (!window.performanceData || !window.performanceData.categories) {
        console.error("Category performance data is not available");
        container.innerHTML = '<p class="text-gray-500 text-center p-4">No category data available. Take some quizzes first.</p>';
        return;
    }
    
    const categories = window.performanceData.categories;
    console.log("Category data:", categories);
    
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
        const distance = radius * (parseFloat(cat.percentage) / 100);
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
        const distance = radius * (parseFloat(cat.percentage) / 100);
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
    const sortedCategories = [...categories].sort((a, b) => parseFloat(b.percentage) - parseFloat(a.percentage));
    const strongest = sortedCategories[0];
    const weakest = sortedCategories[sortedCategories.length - 1];
    
    // Create strength/weakness insights
    const insightsHtml = `
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-green-50 p-4 rounded-lg">
                <h4 class="font-medium text-green-700 mb-1">Strongest Category</h4>
                <p class="text-sm text-gray-700"><span class="font-bold">${strongest.name}</span> (${parseFloat(strongest.percentage).toFixed(1)}%)</p>
                <p class="text-xs text-gray-600 mt-1">Keep up the good work in this area!</p>
            </div>
            <div class="bg-amber-50 p-4 rounded-lg">
                <h4 class="font-medium text-amber-700 mb-1">Area for Improvement</h4>
                <p class="text-sm text-gray-700"><span class="font-bold">${weakest.name}</span> (${parseFloat(weakest.percentage).toFixed(1)}%)</p>
                <p class="text-xs text-gray-600 mt-1">Try focusing more on this topic.</p>
            </div>
        </div>
    `;
    
    // Assemble the whole section
    container.innerHTML = `
        <div class="flex justify-center">
            ${svgContent}
        </div>
        ${insightsHtml}
    `;
}