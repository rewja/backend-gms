<?php

return [
    // General
    'title' => 'Riwayat Aktivitas',
    'description' => 'Lihat dan kelola log aktivitas pengguna',
    'no_activities' => 'Tidak ada aktivitas ditemukan',
    'loading' => 'Memuat aktivitas...',
    
    // Actions
    'actions' => [
        'create' => 'Membuat',
        'update' => 'Memperbarui',
        'delete' => 'Menghapus',
        'login' => 'Masuk',
        'logout' => 'Keluar',
        'view' => 'Melihat',
        'export' => 'Mengekspor',
        'import' => 'Mengimpor',
        'failed_login' => 'Gagal Masuk',
    ],
    
    // Models
    'models' => [
        'todo' => 'Tugas',
        'user' => 'Pengguna',
        'request_item' => 'Permintaan',
        'asset' => 'Aset',
        'meeting' => 'Rapat',
        'visitor' => 'Tamu',
        'procurement' => 'Pengadaan',
    ],
    
    // Descriptions
    'descriptions' => [
        'created_todo' => 'Membuat Tugas #{id}',
        'updated_todo' => 'Memperbarui Tugas #{id}',
        'deleted_todo' => 'Menghapus Tugas #{id}',
        'created_user' => 'Membuat Pengguna #{id}',
        'updated_user' => 'Memperbarui Pengguna #{id}',
        'deleted_user' => 'Menghapus Pengguna #{id}',
        'created_request' => 'Membuat Permintaan #{id}',
        'updated_request' => 'Memperbarui Permintaan #{id}',
        'deleted_request' => 'Menghapus Permintaan #{id}',
        'created_asset' => 'Membuat Aset #{id}',
        'updated_asset' => 'Memperbarui Aset #{id}',
        'deleted_asset' => 'Menghapus Aset #{id}',
        'created_meeting' => 'Membuat Rapat #{id}',
        'updated_meeting' => 'Memperbarui Rapat #{id}',
        'deleted_meeting' => 'Menghapus Rapat #{id}',
        'created_visitor' => 'Membuat Tamu #{id}',
        'updated_visitor' => 'Memperbarui Tamu #{id}',
        'deleted_visitor' => 'Menghapus Tamu #{id}',
        'created_procurement' => 'Membuat Pengadaan #{id}',
        'updated_procurement' => 'Memperbarui Pengadaan #{id}',
        'deleted_procurement' => 'Menghapus Pengadaan #{id}',
        'user_login' => 'Pengguna {name} masuk',
        'user_logout' => 'Pengguna {name} keluar',
        'failed_login_attempt' => 'Percobaan masuk gagal untuk email: {email}',
        'exported_data' => 'Mengekspor data {type}',
        'imported_data' => 'Mengimpor {count} record {type}',
    ],
    
    // Statistics
    'stats' => [
        'title' => 'Statistik Aktivitas',
        'total_activities' => 'Total Aktivitas',
        'active_users' => 'Pengguna Aktif',
        'total_logins' => 'Total Masuk',
        'failed_logins' => 'Gagal Masuk',
        'creates' => 'Dibuat',
        'updates' => 'Diperbarui',
        'deletes' => 'Dihapus',
        'exports' => 'Diekspor',
        'daily_activities' => 'Aktivitas Harian',
        'activities_by_action' => 'Aktivitas Berdasarkan Aksi',
        'activities_by_user' => 'Aktivitas Berdasarkan Pengguna',
        'recent_activities' => 'Aktivitas Terbaru',
        'system_stats' => 'Statistik Sistem',
        'user_stats' => 'Statistik Pengguna',
    ],
    
    // Filters
    'filters' => [
        'title' => 'Filter',
        'all_actions' => 'Semua Aksi',
        'all_users' => 'Semua Pengguna',
        'all_models' => 'Semua Model',
        'date_from' => 'Dari Tanggal',
        'date_to' => 'Sampai Tanggal',
        'search' => 'Cari dalam deskripsi...',
        'ip_address' => 'Alamat IP',
        'apply' => 'Terapkan Filter',
        'clear' => 'Hapus Filter',
    ],
    
    // Export
    'export' => [
        'title' => 'Ekspor Aktivitas',
        'button' => 'Ekspor Data',
        'success' => 'Aktivitas berhasil diekspor',
        'error' => 'Gagal mengekspor aktivitas',
    ],
    
    // Messages
    'messages' => [
        'retrieved_successfully' => 'Log aktivitas berhasil diambil',
        'stats_retrieved_successfully' => 'Statistik aktivitas berhasil diambil',
        'exported_successfully' => 'Log aktivitas berhasil diekspor',
        'cleared_successfully' => 'Log aktivitas lama berhasil dihapus',
        'unauthorized_access' => 'Akses tidak diizinkan',
        'personal_activities' => 'Log aktivitas pribadi berhasil diambil',
        'user_summary_retrieved' => 'Ringkasan aktivitas pengguna berhasil diambil',
    ],
    
    // Time
    'time' => [
        'just_now' => 'Baru saja',
        'minutes_ago' => '{count} menit yang lalu',
        'hours_ago' => '{count} jam yang lalu',
        'days_ago' => '{count} hari yang lalu',
        'weeks_ago' => '{count} minggu yang lalu',
        'months_ago' => '{count} bulan yang lalu',
        'years_ago' => '{count} tahun yang lalu',
    ],
    
    // Admin specific
    'admin' => [
        'title' => 'Riwayat Aktivitas Sistem',
        'description' => 'Pantau semua aktivitas pengguna di seluruh sistem',
        'clear_old_logs' => 'Hapus Log Lama',
        'clear_old_logs_description' => 'Hapus log aktivitas yang lebih lama dari hari yang ditentukan',
        'days_to_keep' => 'Hari untuk disimpan',
        'confirm_clear' => 'Apakah Anda yakin ingin menghapus log aktivitas lama? Tindakan ini tidak dapat dibatalkan.',
        'logs_cleared' => '{count} log aktivitas lama telah dihapus',
    ],
    
    // User specific
    'user' => [
        'title' => 'Riwayat Aktivitas Saya',
        'description' => 'Lihat riwayat aktivitas pribadi Anda',
        'my_stats' => 'Statistik Saya',
        'my_activities' => 'Aktivitas Saya',
    ],
];


