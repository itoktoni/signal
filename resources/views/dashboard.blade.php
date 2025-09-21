<x-template-layout title="Dashboard">
    <div class="dashboard-content">
        <!-- Welcome Section -->
        <div class="card">
            <div class="page-header">
                <h2>Welcome to Asset Management</h2>
                <p class="text-muted">Manage your assets efficiently with our comprehensive system</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ \App\Models\User::count() }}</h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-content">
                    <h3>0</h3>
                    <p>Total Assets</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>0</h3>
                    <p>Active Assets</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>0</h3>
                    <p>Maintenance Due</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="page-header">
                <h2>Recent Activity</h2>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div class="activity-content">
                        <p><strong>{{ auth()->user()->name }}</strong> logged into the system</p>
                        <small>{{ now()->format('M d, Y H:i') }}</small>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <div class="activity-content">
                        <p>System initialized successfully</p>
                        <small>{{ now()->format('M d, Y H:i') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="page-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="quick-actions">
                <a href="{{ route('user.index') }}" class="action-button">
                    <i class="bi bi-people"></i>
                    <span>Manage Users</span>
                </a>
                <a href="#" class="action-button">
                    <i class="bi bi-box-seam"></i>
                    <span>View Assets</span>
                </a>
                <a href="#" class="action-button">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reports</span>
                </a>
                <a href="#" class="action-button">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <style>
        .dashboard-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .text-muted {
            color: #666;
            margin: 0;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .stat-content h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .stat-content p {
            margin: 4px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #495057;
        }

        .activity-content p {
            margin: 0;
            color: #333;
        }

        .activity-content small {
            color: #666;
            font-size: 12px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
        }

        .action-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 24px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .action-button:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
        }

        .action-button i {
            font-size: 32px;
            color: #667eea;
        }

        .action-button span {
            font-weight: 500;
            text-align: center;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</x-template-layout>
