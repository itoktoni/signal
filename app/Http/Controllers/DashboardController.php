<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        // Prepare chart data in PHP for JavaScript rendering
        $chartData = [
            'userRegistrations' => [
                'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'data' => [12, 19, 15, 25, 22, 30]
            ],
            'assetStatus' => [
                'data' => [
                    ['name' => 'Active', 'value' => 45],
                    ['name' => 'Maintenance', 'value' => 15],
                    ['name' => 'Inactive', 'value' => 20],
                    ['name' => 'Retired', 'value' => 10]
                ]
            ],
            'monthlyRevenue' => [
                'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'series' => [
                    ['name' => 'Service Revenue', 'data' => [8000, 10000, 12000, 9000, 15000, 13000]],
                    ['name' => 'Product Sales', 'data' => [4000, 5000, 6000, 5000, 7000, 6000]]
                ]
            ],
            'departmentDistribution' => [
                'data' => [
                    ['name' => 'IT', 'value' => 35],
                    ['name' => 'HR', 'value' => 20],
                    ['name' => 'Finance', 'value' => 15],
                    ['name' => 'Operations', 'value' => 18],
                    ['name' => 'Marketing', 'value' => 12]
                ]
            ],
            'assetPerformance' => [
                'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                'series' => [
                    ['name' => 'Efficiency', 'data' => [85, 88, 82, 90, 87, 92]],
                    ['name' => 'Utilization', 'data' => [75, 80, 78, 85, 82, 88]]
                ]
            ],
            'maintenancePriority' => [
                'data' => [
                    ['name' => 'Critical', 'value' => 95],
                    ['name' => 'High', 'value' => 75],
                    ['name' => 'Medium', 'value' => 45],
                    ['name' => 'Low', 'value' => 25]
                ]
            ],
            'dailyUsage' => [
                'categories' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'data' => [65, 75, 80, 85, 70, 60, 55]
            ],
            'assetRanking' => [
                'categories' => ['Server A', 'Desktop E', 'Laptop B', 'Router D', 'Printer C'],
                'data' => [95, 91, 87, 82, 76]
            ],
            'performanceMetrics' => [
                'data' => [
                    [10, 20], [15, 25], [20, 30], [25, 35], [30, 40], [35, 45]
                ]
            ],
            'skillAssessment' => [
                'indicators' => [
                    ['name' => 'Technical Skills', 'max' => 100],
                    ['name' => 'Communication', 'max' => 100],
                    ['name' => 'Leadership', 'max' => 100],
                    ['name' => 'Problem Solving', 'max' => 100],
                    ['name' => 'Teamwork', 'max' => 100]
                ],
                'data' => [
                    ['name' => 'Current', 'value' => [85, 75, 80, 90, 85]],
                    ['name' => 'Target', 'value' => [95, 85, 90, 95, 90]]
                ]
            ],
            'usageHeatmap' => [
                'xAxis' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'yAxis' => ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                'data' => [
                    [10, 15, 20, 25, 30, 35, 40],
                    [12, 18, 22, 28, 32, 38, 42],
                    [8, 12, 18, 22, 28, 32, 36],
                    [15, 20, 25, 30, 35, 40, 45],
                    [18, 25, 30, 35, 40, 45, 50],
                    [20, 28, 35, 40, 45, 50, 55]
                ]
            ],
            'systemHealth' => [
                'value' => 78,
                'unit' => '%'
            ],
            'salesFunnel' => [
                'data' => [
                    ['name' => 'Website Visits', 'value' => 100],
                    ['name' => 'Product Views', 'value' => 80],
                    ['name' => 'Add to Cart', 'value' => 60],
                    ['name' => 'Checkout', 'value' => 40],
                    ['name' => 'Purchase', 'value' => 25]
                ]
            ],
            'stockData' => [
                'categories' => ['2024-01', '2024-02', '2024-03', '2024-04', '2024-05', '2024-06'],
                'data' => [
                    [20, 34, 10, 38], [31, 42, 25, 48], [28, 35, 22, 40],
                    [35, 48, 30, 52], [32, 45, 28, 50], [38, 52, 35, 58]
                ]
            ],
            'organizationTree' => [
                'name' => 'CEO',
                'children' => [
                    [
                        'name' => 'CTO',
                        'children' => [
                            ['name' => 'Engineering Manager'],
                            ['name' => 'Product Manager']
                        ]
                    ],
                    [
                        'name' => 'CFO',
                        'children' => [
                            ['name' => 'Finance Manager'],
                            ['name' => 'Accounting Manager']
                        ]
                    ]
                ]
            ],
            'networkGraph' => [
                'nodes' => [
                    ['name' => 'Server A', 'value' => 20, 'category' => 'Server'],
                    ['name' => 'Server B', 'value' => 15, 'category' => 'Server'],
                    ['name' => 'Database', 'value' => 25, 'category' => 'Database'],
                    ['name' => 'Web App', 'value' => 30, 'category' => 'Application'],
                    ['name' => 'API', 'value' => 20, 'category' => 'Service']
                ],
                'links' => [
                    ['source' => 'Web App', 'target' => 'Server A'],
                    ['source' => 'Web App', 'target' => 'Database'],
                    ['source' => 'API', 'target' => 'Server B'],
                    ['source' => 'API', 'target' => 'Database']
                ]
            ]
        ];

        return view('dashboard', compact('chartData'));
    }
}