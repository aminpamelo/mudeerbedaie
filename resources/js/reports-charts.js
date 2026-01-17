import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

// Function to initialize revenue trend chart
export function initRevenueTrendChart(monthlyData) {
    const months = Object.values(monthlyData).map(item => item.month_name);
    const packageRevenue = Object.values(monthlyData).map(item => item.packages.revenue);
    const orderRevenue = Object.values(monthlyData).map(item => item.orders.revenue);

    const ctx = document.getElementById('revenueTrendChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Package Sales Revenue',
                    data: packageRevenue,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Product Orders Revenue',
                    data: orderRevenue,
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': RM ' + context.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'RM ' + value.toLocaleString('en-MY');
                        }
                    }
                }
            }
        }
    });
}

// Function to initialize sales comparison chart
export function initSalesComparisonChart(monthlyData) {
    const months = Object.values(monthlyData).map(item => item.month_name);
    const packageCount = Object.values(monthlyData).map(item => item.packages.count);
    const orderCount = Object.values(monthlyData).map(item => item.orders.count);

    const ctx = document.getElementById('salesComparisonChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Package Sales',
                    data: packageCount,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Product Orders',
                    data: orderCount,
                    backgroundColor: 'rgba(168, 85, 247, 0.8)',
                    borderColor: 'rgb(168, 85, 247)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' sales';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Initialize all charts
export function initializeCharts(monthlyData) {
    initRevenueTrendChart(monthlyData);
    initSalesComparisonChart(monthlyData);
}

// Make it globally available
window.initializeCharts = initializeCharts;

// Student Order Report Charts
export function initStudentRevenueTrendChart(monthlyData) {
    const months = monthlyData.map(item => item.month_name);
    const revenue = monthlyData.map(item => item.revenue);
    const orderCount = monthlyData.map(item => item.order_count);

    const ctx = document.getElementById('revenueTrendChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: orderCount,
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return 'Revenue: RM ' + context.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            } else {
                                return 'Orders: ' + context.parsed.y;
                            }
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (RM)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'RM ' + value.toLocaleString('en-MY');
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Orders'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

export function initStudentActivityChart(monthlyData) {
    const months = monthlyData.map(item => item.month_name);
    const studentCount = monthlyData.map(item => item.student_count);

    const ctx = document.getElementById('studentActivityChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Active Students',
                    data: studentCount,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Students: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Students'
                    }
                }
            }
        }
    });
}

// Initialize student order charts
export function initializeStudentOrderCharts(monthlyData) {
    initStudentRevenueTrendChart(monthlyData);
    initStudentActivityChart(monthlyData);
}

// Make it globally available
window.initializeStudentOrderCharts = initializeStudentOrderCharts;

// Agent Performance Report Charts
let agentRevenueChart = null;
let agentOrdersChart = null;

export function initAgentRevenueTrendChart(monthlyData) {
    const months = Object.values(monthlyData).map(item => item.month_name);
    const revenue = Object.values(monthlyData).map(item => item.total_revenue);
    const orders = Object.values(monthlyData).map(item => item.total_orders);

    const ctx = document.getElementById('agentRevenueTrendChart');
    if (!ctx) return null;

    // Destroy existing chart if exists
    if (agentRevenueChart) {
        agentRevenueChart.destroy();
        agentRevenueChart = null;
    }

    agentRevenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue (RM)',
                    data: revenue,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Orders',
                    data: orders,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 600,
                easing: 'easeOutQuart',
                onComplete: function() {
                    // Chart animation completed
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return 'Revenue: RM ' + context.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            } else {
                                return 'Orders: ' + context.parsed.y;
                            }
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (RM)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'RM ' + value.toLocaleString('en-MY');
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Orders'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });

    return agentRevenueChart;
}

export function initAgentOrdersByTypeChart(monthlyData) {
    const months = Object.values(monthlyData).map(item => item.month_name);
    const agentOrders = Object.values(monthlyData).map(item => item.by_type.agent.orders);
    const companyOrders = Object.values(monthlyData).map(item => item.by_type.company.orders);
    const bookstoreOrders = Object.values(monthlyData).map(item => item.by_type.bookstore.orders);

    const ctx = document.getElementById('agentOrdersByTypeChart');
    if (!ctx) return null;

    // Destroy existing chart if exists
    if (agentOrdersChart) {
        agentOrdersChart.destroy();
        agentOrdersChart = null;
    }

    agentOrdersChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Agent',
                    data: agentOrders,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Company',
                    data: companyOrders,
                    backgroundColor: 'rgba(168, 85, 247, 0.8)',
                    borderColor: 'rgb(168, 85, 247)',
                    borderWidth: 1
                },
                {
                    label: 'Bookstore',
                    data: bookstoreOrders,
                    backgroundColor: 'rgba(249, 115, 22, 0.8)',
                    borderColor: 'rgb(249, 115, 22)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 600,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' orders';
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    },
                    title: {
                        display: true,
                        text: 'Number of Orders'
                    }
                }
            }
        }
    });

    return agentOrdersChart;
}

// Initialize agent performance charts
export function initializeAgentPerformanceCharts(monthlyData) {
    // Use requestAnimationFrame to ensure DOM is ready
    requestAnimationFrame(() => {
        initAgentRevenueTrendChart(monthlyData);
        initAgentOrdersByTypeChart(monthlyData);
    });
}

// Destroy agent performance charts (useful for cleanup)
export function destroyAgentPerformanceCharts() {
    if (agentRevenueChart) {
        agentRevenueChart.destroy();
        agentRevenueChart = null;
    }
    if (agentOrdersChart) {
        agentOrdersChart.destroy();
        agentOrdersChart = null;
    }
}

// Make it globally available
window.initializeAgentPerformanceCharts = initializeAgentPerformanceCharts;
window.destroyAgentPerformanceCharts = destroyAgentPerformanceCharts;
