<?php

return [
    // General
    'title' => 'Activity History',
    'description' => 'View and manage user activity logs',
    'no_activities' => 'No activities found',
    'loading' => 'Loading activities...',
    
    // Actions
    'actions' => [
        'create' => 'Created',
        'update' => 'Updated',
        'delete' => 'Deleted',
        'login' => 'Logged In',
        'logout' => 'Logged Out',
        'view' => 'Viewed',
        'export' => 'Exported',
        'import' => 'Imported',
        'failed_login' => 'Failed Login',
    ],
    
    // Models
    'models' => [
        'todo' => 'Todo',
        'user' => 'User',
        'request_item' => 'Request',
        'asset' => 'Asset',
        'meeting' => 'Meeting',
        'visitor' => 'Visitor',
        'procurement' => 'Procurement',
    ],
    
    // Descriptions
    'descriptions' => [
        'created_todo' => 'Created Todo #{id}',
        'updated_todo' => 'Updated Todo #{id}',
        'deleted_todo' => 'Deleted Todo #{id}',
        'created_user' => 'Created User #{id}',
        'updated_user' => 'Updated User #{id}',
        'deleted_user' => 'Deleted User #{id}',
        'created_request' => 'Created Request #{id}',
        'updated_request' => 'Updated Request #{id}',
        'deleted_request' => 'Deleted Request #{id}',
        'created_asset' => 'Created Asset #{id}',
        'updated_asset' => 'Updated Asset #{id}',
        'deleted_asset' => 'Deleted Asset #{id}',
        'created_meeting' => 'Created Meeting #{id}',
        'updated_meeting' => 'Updated Meeting #{id}',
        'deleted_meeting' => 'Deleted Meeting #{id}',
        'created_visitor' => 'Created Visitor #{id}',
        'updated_visitor' => 'Updated Visitor #{id}',
        'deleted_visitor' => 'Deleted Visitor #{id}',
        'created_procurement' => 'Created Procurement #{id}',
        'updated_procurement' => 'Updated Procurement #{id}',
        'deleted_procurement' => 'Deleted Procurement #{id}',
        'user_login' => 'User {name} logged in',
        'user_logout' => 'User {name} logged out',
        'failed_login_attempt' => 'Failed login attempt for email: {email}',
        'exported_data' => 'Exported {type} data',
        'imported_data' => 'Imported {count} {type} records',
    ],
    
    // Statistics
    'stats' => [
        'title' => 'Activity Statistics',
        'total_activities' => 'Total Activities',
        'active_users' => 'Active Users',
        'total_logins' => 'Total Logins',
        'failed_logins' => 'Failed Logins',
        'creates' => 'Created',
        'updates' => 'Updated',
        'deletes' => 'Deleted',
        'exports' => 'Exported',
        'daily_activities' => 'Daily Activities',
        'activities_by_action' => 'Activities by Action',
        'activities_by_user' => 'Activities by User',
        'recent_activities' => 'Recent Activities',
        'system_stats' => 'System Statistics',
        'user_stats' => 'User Statistics',
    ],
    
    // Filters
    'filters' => [
        'title' => 'Filters',
        'all_actions' => 'All Actions',
        'all_users' => 'All Users',
        'all_models' => 'All Models',
        'date_from' => 'From Date',
        'date_to' => 'To Date',
        'search' => 'Search in description...',
        'ip_address' => 'IP Address',
        'apply' => 'Apply Filters',
        'clear' => 'Clear Filters',
    ],
    
    // Export
    'export' => [
        'title' => 'Export Activities',
        'button' => 'Export Data',
        'success' => 'Activities exported successfully',
        'error' => 'Failed to export activities',
    ],
    
    // Messages
    'messages' => [
        'retrieved_successfully' => 'Activity logs retrieved successfully',
        'stats_retrieved_successfully' => 'Activity statistics retrieved successfully',
        'exported_successfully' => 'Activity logs exported successfully',
        'cleared_successfully' => 'Old activity logs cleared successfully',
        'unauthorized_access' => 'Unauthorized access',
        'personal_activities' => 'Personal activity logs retrieved successfully',
        'user_summary_retrieved' => 'User activity summary retrieved successfully',
    ],
    
    // Time
    'time' => [
        'just_now' => 'Just now',
        'minutes_ago' => '{count} minutes ago',
        'hours_ago' => '{count} hours ago',
        'days_ago' => '{count} days ago',
        'weeks_ago' => '{count} weeks ago',
        'months_ago' => '{count} months ago',
        'years_ago' => '{count} years ago',
    ],
    
    // Admin specific
    'admin' => [
        'title' => 'System Activity History',
        'description' => 'Monitor all user activities across the system',
        'clear_old_logs' => 'Clear Old Logs',
        'clear_old_logs_description' => 'Remove activity logs older than specified days',
        'days_to_keep' => 'Days to keep',
        'confirm_clear' => 'Are you sure you want to clear old activity logs? This action cannot be undone.',
        'logs_cleared' => '{count} old activity logs have been cleared',
    ],
    
    // User specific
    'user' => [
        'title' => 'My Activity History',
        'description' => 'View your personal activity history',
        'my_stats' => 'My Statistics',
        'my_activities' => 'My Activities',
    ],
];






