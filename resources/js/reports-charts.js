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

// Sales Department Report Charts
let salesDeptRevenueChartInstance = null;
let salesDeptBarChartInstance = null;

export function initSalesDeptRevenueChart(monthlyData) {
    const months = monthlyData.map(item => item.month_name);
    const revenue = monthlyData.map(item => item.revenue);
    const salesCount = monthlyData.map(item => item.sales_count);

    const ctx = document.getElementById('salesDeptRevenueChart');
    if (!ctx) return;

    if (salesDeptRevenueChartInstance) {
        salesDeptRevenueChartInstance.destroy();
        salesDeptRevenueChartInstance = null;
    }

    salesDeptRevenueChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Sales',
                    data: salesCount,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
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
                                return 'Sales: ' + context.parsed.y;
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
                        text: 'Sales Count'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

export function initSalesDeptMonthlyBarChart(monthlyData) {
    const months = monthlyData.map(item => item.month_name);
    const revenue = monthlyData.map(item => item.revenue);

    const ctx = document.getElementById('salesDeptMonthlyBarChart');
    if (!ctx) return;

    if (salesDeptBarChartInstance) {
        salesDeptBarChartInstance.destroy();
        salesDeptBarChartInstance = null;
    }

    salesDeptBarChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Monthly Revenue',
                    data: revenue,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1,
                    borderRadius: 4
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
                            return 'Revenue: RM ' + context.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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

export function initializeSalesDeptCharts(monthlyData) {
    initSalesDeptRevenueChart(monthlyData);
    initSalesDeptMonthlyBarChart(monthlyData);
}

window.initializeSalesDeptCharts = initializeSalesDeptCharts;

// Product Report Charts
let productMonthlyTrendChartInstance = null;
let productRevenueBarChartInstance = null;

export function initProductMonthlyTrendChart(monthlyProductData) {
    const months = monthlyProductData.map(item => item.month_name);
    const revenue = monthlyProductData.map(item => item.revenue);
    const units = monthlyProductData.map(item => item.units_sold);

    const ctx = document.getElementById('productMonthlyTrendChart');
    if (!ctx) return;

    if (productMonthlyTrendChartInstance) {
        productMonthlyTrendChartInstance.destroy();
        productMonthlyTrendChartInstance = null;
    }

    productMonthlyTrendChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Units Sold',
                    data: units,
                    borderColor: 'rgb(20, 184, 166)',
                    backgroundColor: 'rgba(20, 184, 166, 0.1)',
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
                                return 'Units: ' + context.parsed.y;
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
                        text: 'Units Sold'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

export function initProductRevenueBarChart(topProducts) {
    if (!topProducts || topProducts.length === 0) return;

    const labels = topProducts.map(p => {
        const name = p.display_name || p.product_name;
        return name.length > 20 ? name.substring(0, 20) + '...' : name;
    });
    const revenue = topProducts.map(p => p.revenue);

    const ctx = document.getElementById('productRevenueBarChart');
    if (!ctx) return;

    if (productRevenueBarChartInstance) {
        productRevenueBarChartInstance.destroy();
        productRevenueBarChartInstance = null;
    }

    const colors = [
        'rgba(99, 102, 241, 0.8)',
        'rgba(20, 184, 166, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(34, 197, 94, 0.8)',
        'rgba(168, 85, 247, 0.8)',
        'rgba(59, 130, 246, 0.8)',
        'rgba(236, 72, 153, 0.8)',
        'rgba(251, 146, 60, 0.8)',
        'rgba(14, 165, 233, 0.8)',
    ];

    productRevenueBarChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    backgroundColor: colors.slice(0, revenue.length),
                    borderWidth: 0,
                    borderRadius: 4
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: RM ' + context.parsed.x.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                x: {
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

export function initializeProductReportCharts(monthlyProductData, topProducts) {
    initProductMonthlyTrendChart(monthlyProductData);
    initProductRevenueBarChart(topProducts);
}

window.initializeProductReportCharts = initializeProductReportCharts;

// Agent Product Insights Charts
let agentProductTrendInstance = null;
let agentProductBarInstance = null;

export function initAgentProductMonthlyTrend(monthlyProductData) {
    const months = monthlyProductData.map(item => item.month_name);
    const revenue = monthlyProductData.map(item => item.revenue);
    const units = monthlyProductData.map(item => item.units_sold);

    const ctx = document.getElementById('agentProductMonthlyTrendChart');
    if (!ctx) return;

    if (agentProductTrendInstance) {
        agentProductTrendInstance.destroy();
        agentProductTrendInstance = null;
    }

    agentProductTrendInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Units Sold',
                    data: units,
                    borderColor: 'rgb(20, 184, 166)',
                    backgroundColor: 'rgba(20, 184, 166, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return 'Revenue: RM ' + context.parsed.y.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                            return 'Units: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear', display: true, position: 'left', beginAtZero: true,
                    title: { display: true, text: 'Revenue (RM)' },
                    ticks: { callback: function(value) { return 'RM ' + value.toLocaleString('en-MY'); } }
                },
                y1: {
                    type: 'linear', display: true, position: 'right', beginAtZero: true,
                    title: { display: true, text: 'Units Sold' },
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

export function initAgentProductRevenueBar(topProducts) {
    if (!topProducts || topProducts.length === 0) return;

    const labels = topProducts.map(p => {
        const name = p.product_name || '';
        return name.length > 20 ? name.substring(0, 20) + '...' : name;
    });
    const revenue = topProducts.map(p => p.total_revenue);

    const ctx = document.getElementById('agentProductRevenueBarChart');
    if (!ctx) return;

    if (agentProductBarInstance) {
        agentProductBarInstance.destroy();
        agentProductBarInstance = null;
    }

    const colors = [
        'rgba(99, 102, 241, 0.8)', 'rgba(20, 184, 166, 0.8)', 'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)', 'rgba(34, 197, 94, 0.8)', 'rgba(168, 85, 247, 0.8)',
        'rgba(59, 130, 246, 0.8)', 'rgba(236, 72, 153, 0.8)', 'rgba(251, 146, 60, 0.8)',
        'rgba(14, 165, 233, 0.8)',
    ];

    agentProductBarInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: revenue,
                backgroundColor: colors.slice(0, revenue.length),
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: RM ' + context.parsed.x.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return 'RM ' + value.toLocaleString('en-MY'); } }
                }
            }
        }
    });
}

export function initializeAgentProductCharts(monthlyProductData, topProducts) {
    requestAnimationFrame(() => {
        initAgentProductMonthlyTrend(monthlyProductData);
        initAgentProductRevenueBar(topProducts);
    });
}

export function destroyAgentProductCharts() {
    if (agentProductTrendInstance) { agentProductTrendInstance.destroy(); agentProductTrendInstance = null; }
    if (agentProductBarInstance) { agentProductBarInstance.destroy(); agentProductBarInstance = null; }
}

window.initializeAgentProductCharts = initializeAgentProductCharts;
window.destroyAgentProductCharts = destroyAgentProductCharts;

// Agent Leaderboard Charts
let agentLeaderboardTrendInstance = null;
let agentLeaderboardBarInstance = null;

export function initAgentLeaderboardTrend(monthlyAgentData) {
    const months = monthlyAgentData.map(item => item.month_name);
    const agentRevenue = monthlyAgentData.map(item => item.agent_revenue);
    const companyRevenue = monthlyAgentData.map(item => item.company_revenue);

    const ctx = document.getElementById('agentLeaderboardTrendChart');
    if (!ctx) return;

    if (agentLeaderboardTrendInstance) {
        agentLeaderboardTrendInstance.destroy();
        agentLeaderboardTrendInstance = null;
    }

    agentLeaderboardTrendInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Agent Revenue',
                    data: agentRevenue,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Company Revenue',
                    data: companyRevenue,
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
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top' },
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
                    ticks: { callback: function(value) { return 'RM ' + value.toLocaleString('en-MY'); } }
                }
            }
        }
    });
}

export function initAgentLeaderboardBar(topAgents) {
    if (!topAgents || topAgents.length === 0) return;

    const labels = topAgents.map(a => {
        const name = a.name || '';
        return name.length > 20 ? name.substring(0, 20) + '...' : name;
    });
    const revenue = topAgents.map(a => a.total_revenue);

    const ctx = document.getElementById('agentLeaderboardBarChart');
    if (!ctx) return;

    if (agentLeaderboardBarInstance) {
        agentLeaderboardBarInstance.destroy();
        agentLeaderboardBarInstance = null;
    }

    const colors = topAgents.map(a =>
        a.type === 'company' ? 'rgba(168, 85, 247, 0.8)' : 'rgba(59, 130, 246, 0.8)'
    );

    agentLeaderboardBarInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: revenue,
                backgroundColor: colors,
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: RM ' + context.parsed.x.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return 'RM ' + value.toLocaleString('en-MY'); } }
                }
            }
        }
    });
}

export function initializeAgentLeaderboardCharts(monthlyAgentData, topAgents) {
    requestAnimationFrame(() => {
        initAgentLeaderboardTrend(monthlyAgentData);
        initAgentLeaderboardBar(topAgents);
    });
}

export function destroyAgentLeaderboardCharts() {
    if (agentLeaderboardTrendInstance) { agentLeaderboardTrendInstance.destroy(); agentLeaderboardTrendInstance = null; }
    if (agentLeaderboardBarInstance) { agentLeaderboardBarInstance.destroy(); agentLeaderboardBarInstance = null; }
}

window.initializeAgentLeaderboardCharts = initializeAgentLeaderboardCharts;
window.destroyAgentLeaderboardCharts = destroyAgentLeaderboardCharts;
