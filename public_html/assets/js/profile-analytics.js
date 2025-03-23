// assets/js/profile-analytics.js
document.addEventListener('DOMContentLoaded', function() {
    // Performance Charts initialization
    if (document.getElementById('accuracy-chart')) {
        renderAccuracyChart();
    }
    
    if (document.getElementById('category-performance-chart')) {
        renderCategoryChart();
    }
    
    if (document.getElementById('progress-chart')) {
        renderProgressChart();
    }
});

function renderAccuracyChart() {
    const ctx = document.getElementById('accuracy-chart').getContext('2d');
    
    // Fetch the data from the data attributes
    const chartElement = document.getElementById('accuracy-chart');
    const data = JSON.parse(chartElement.dataset.chartData || '[]');
    
    // Create the chart
    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Correct', 'Incorrect'],
            datasets: [{
                data: [data.correct || 0, data.total - data.correct || 0],
                backgroundColor: ['#4F46E5', '#E5E7EB'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = data.total || 1;
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function renderCategoryChart() {
    const ctx = document.getElementById('category-performance-chart').getContext('2d');
    
    // Fetch the data from the data attributes
    const chartElement = document.getElementById('category-performance-chart');
    const data = JSON.parse(chartElement.dataset.chartData || '[]');
    
    // Extract data for the chart
    const labels = data.map(item => item.name);
    const accuracyData = data.map(item => item.accuracy);
    
    // Create a color array (gradient from green to red based on accuracy)
    const colors = accuracyData.map(accuracy => {
        const hue = (accuracy / 100) * 120; // 0 = red, 120 = green
        return `hsl(${hue}, 80%, 45%)`;
    });
    
    // Create the chart
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Accuracy %',
                data: accuracyData,
                backgroundColor: colors,
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const dataIndex = context.dataIndex;
                            const total = data[dataIndex].total || 0;
                            const correct = data[dataIndex].correct || 0;
                            return [
                                `Accuracy: ${context.raw.toFixed(1)}%`,
                                `Correct: ${correct}/${total}`
                            ];
                        }
                    }
                }
            }
        }
    });
}

function renderProgressChart() {
    const ctx = document.getElementById('progress-chart').getContext('2d');
    
    // Fetch the data from the data attributes
    const chartElement = document.getElementById('progress-chart');
    const data = JSON.parse(chartElement.dataset.chartData || '[]');
    
    // Extract data for the chart
    const labels = data.map(item => item.week);
    const questionsData = data.map(item => item.questions);
    const accuracyData = data.map(item => item.accuracy);
    
    // Create the chart
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Questions Answered',
                    data: questionsData,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                    yAxisID: 'y'
                },
                {
                    label: 'Accuracy %',
                    data: accuracyData,
                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                    borderColor: 'rgba(245, 158, 11, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(245, 158, 11, 1)',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Questions'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    max: 100,
                    title: {
                        display: true,
                        text: 'Accuracy %'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
}