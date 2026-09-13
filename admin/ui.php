<?php
// Komponen UI bersama untuk halaman admin agar tampilan konsisten di semua modul.

if (!function_exists('adminPageStyles')) {
    // Variabel style global untuk shell, kartu, tabel, dan perilaku responsif admin.
    function adminPageStyles(): string
    {
        return <<<'HTML'
<style>
    :root {
        --admin-page-bg: #f5f7fb;
        --admin-card-bg: #ffffff;
        --admin-border: #e7edf7;
        --admin-text: #16213d;
        --admin-muted: #69789a;
        --admin-blue: #2f7df6;
        --admin-green: #16c784;
        --admin-purple: #8358e8;
        --admin-orange: #ffae1f;
        --admin-red: #f0525f;
        --admin-shadow: 0 18px 42px rgba(31, 45, 78, .09);
    }
    body {
        min-height: 100vh;
        background:
            radial-gradient(circle at 18% -10%, rgba(47, 125, 246, .13), transparent 30%),
            radial-gradient(circle at 94% 8%, rgba(22, 199, 132, .10), transparent 26%),
            var(--admin-page-bg);
        color: var(--admin-text);
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .admin-shell {
        min-height: 100vh;
        width: min(100% - 32px, 1480px);
        padding-bottom: 34px;
    }
    .admin-header-shell {
        width: min(100% - 32px, 1480px);
        margin: 0 auto;
        padding: 22px 0 0;
    }
    .admin-top-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 22px;
    }
    .admin-page-title {
        color: var(--admin-text);
        font-size: clamp(1.55rem, 2.1vw, 2rem);
        font-weight: 850;
        letter-spacing: 0;
        margin: 0;
    }
    .admin-page-subtitle {
        color: var(--admin-muted);
        font-weight: 500;
        margin: .35rem 0 0;
    }
    .admin-date-pill {
        display: inline-flex;
        align-items: center;
        gap: .65rem;
        background: #fff;
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(31, 45, 78, .08);
        padding: .82rem 1rem;
        color: var(--admin-text);
        font-weight: 750;
        white-space: nowrap;
    }
    .admin-nav-strip {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        margin-bottom: 22px;
    }
    .admin-nav-strip a {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        text-decoration: none;
        color: #526386;
        border: 1px solid var(--admin-border);
        background: rgba(255, 255, 255, .82);
        border-radius: 8px;
        padding: .58rem .78rem;
        font-weight: 750;
        font-size: .88rem;
        transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
    }
    .admin-nav-strip a.active,
    .admin-nav-strip a:hover {
        color: #fff;
        background: var(--admin-blue);
        border-color: var(--admin-blue);
        transform: translateY(-1px);
    }
    .admin-action-band {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: var(--admin-card-bg);
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        box-shadow: var(--admin-shadow);
        padding: 18px 20px;
        margin-bottom: 22px;
    }
    .admin-action-band:empty {
        display: none;
    }
    .admin-card,
    .section-card,
    .criteria-card {
        border: 1px solid var(--admin-border) !important;
        border-radius: 8px !important;
        background: var(--admin-card-bg);
        box-shadow: var(--admin-shadow) !important;
    }
    .admin-card .table-responsive,
    .section-card .table-responsive,
    .criteria-card .table-responsive {
        border-radius: 8px;
    }
    .admin-card .card-body,
    .metric-card .card-body,
    .section-card .card-body,
    .criteria-card .card-body {
        padding: clamp(1rem, 1.8vw, 1.5rem);
    }
    .admin-section-title {
        color: var(--admin-text);
        letter-spacing: 0;
        font-weight: 850;
    }
    .metric-card {
        border: 1px solid var(--admin-border);
        box-shadow: var(--admin-shadow);
        border-radius: 8px;
        min-height: 100%;
    }
    .btn {
        border-radius: 8px;
        font-weight: 700;
    }
    .btn-primary {
        background: var(--admin-blue);
        border-color: var(--admin-blue);
        box-shadow: 0 8px 18px rgba(47, 125, 246, .20);
    }
    .btn-outline-primary {
        border-color: #cfe0ff;
        color: #28508f;
        background: #fff;
    }
    .btn-outline-primary:hover {
        background: var(--admin-blue);
        border-color: var(--admin-blue);
        color: #fff;
    }
    .form-control,
    .form-select,
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        border-color: #dbe4f1;
        border-radius: 8px;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: var(--admin-blue);
        box-shadow: 0 0 0 .2rem rgba(47, 125, 246, .13);
    }
    .table {
        color: #273553;
    }
    .table thead th {
        white-space: nowrap;
        background: #f8fbff;
        color: #526386;
        font-size: .84rem;
        border-bottom: 1px solid #e8eef7;
        padding: .9rem .75rem;
    }
    .table td,
    .table th {
        vertical-align: middle;
    }
    .table tbody td {
        border-color: #edf2f8;
        padding: .85rem .75rem;
        font-weight: 600;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        --bs-table-bg-type: #fbfdff;
    }
    .badge {
        border-radius: 6px;
        font-weight: 800;
    }
    .alert {
        border-radius: 8px;
    }
    .modal-dialog {
        margin: 1rem auto;
    }
    .modal-content {
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        box-shadow: var(--admin-shadow);
    }
    .modal-header,
    .modal-footer {
        border-color: #edf2f8;
    }
    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }
    div.dataTables_wrapper div.dataTables_length label,
    div.dataTables_wrapper div.dataTables_filter label,
    div.dataTables_wrapper div.dataTables_info {
        color: var(--admin-muted);
        font-weight: 650;
    }
    .pagination .page-link {
        border-color: #dbe4f1;
        color: #28508f;
        border-radius: 8px;
        margin: 0 .15rem;
        font-weight: 700;
    }
    .pagination .page-item.active .page-link {
        background: var(--admin-blue);
        border-color: var(--admin-blue);
    }
    @media (max-width: 991.98px) {
        .admin-shell,
        .admin-header-shell {
            width: min(100% - 18px, 1480px);
        }
    }
    @media (max-width: 575.98px) {
        .admin-shell,
        .admin-header-shell {
            width: min(100% - 14px, 1480px);
        }
        .admin-top-row,
        .admin-action-band {
            flex-direction: column;
            align-items: stretch;
        }
        .admin-date-pill {
            justify-content: center;
        }
        .admin-card .card-body,
        .metric-card .card-body,
        .section-card .card-body,
        .criteria-card .card-body {
            padding: 1rem;
        }
        .d-flex.justify-content-between.align-items-center.mb-3 {
            flex-direction: column;
            align-items: stretch !important;
            gap: .75rem;
        }
        .d-flex.justify-content-between.align-items-center.mb-3 .btn {
            width: 100%;
        }
        .d-flex.gap-2.flex-wrap {
            width: 100%;
        }
        .d-flex.gap-2.flex-wrap .btn,
        .d-flex.gap-2.flex-wrap .badge {
            flex: 1 1 auto;
        }
    }
    @media print {
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        .admin-card, .metric-card, .section-card, .criteria-card {
            box-shadow: none !important;
        }
    }
</style>
HTML;
    }
}

if (!function_exists('renderAdminHeader')) {
    // Header admin reusable berisi toolbar yang sama seperti dashboard.
    function renderAdminHeader(string $active, string $title, string $subtitle, array $actions = []): string
    {
        $actionsHtml = '';
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $label = htmlspecialchars((string) ($action['label'] ?? 'Aksi'));
            $href = htmlspecialchars((string) ($action['href'] ?? '#'));
            $class = htmlspecialchars((string) ($action['class'] ?? 'btn btn-primary'));
            $icon = trim((string) ($action['icon'] ?? ''));
            $iconHtml = $icon !== '' ? '<i class="fas ' . htmlspecialchars($icon) . ' me-1"></i>' : '';
            $actionsHtml .= '<a href="' . $href . '" class="' . $class . '">' . $iconHtml . $label . '</a>';
        }

        $navItems = [
            ['key' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-house', 'label' => 'Dashboard'],
            ['key' => 'users', 'href' => 'users.php', 'icon' => 'fa-user-gear', 'label' => 'Staf'],
            ['key' => 'members', 'href' => 'members.php', 'icon' => 'fa-users', 'label' => 'Anggota'],
            ['key' => 'applications', 'href' => 'applications.php', 'icon' => 'fa-file-lines', 'label' => 'Pengajuan'],
            ['key' => 'criteria', 'href' => 'criteria.php', 'icon' => 'fa-sliders', 'label' => 'Kriteria'],
            ['key' => 'saw', 'href' => 'saw_results.php', 'icon' => 'fa-chart-simple', 'label' => 'Hasil SAW'],
            ['key' => 'reports', 'href' => 'reports.php', 'icon' => 'fa-file-export', 'label' => 'Laporan'],
            ['key' => 'logout', 'href' => '../proses/logout.php', 'icon' => 'fa-right-from-bracket', 'label' => 'Logout'],
        ];

        $navHtml = '';
        foreach ($navItems as $item) {
            $isActive = $active === $item['key'] ? ' active' : '';
            $navHtml .= '<a class="' . $isActive . '" href="' . htmlspecialchars($item['href']) . '"><i class="fas ' . htmlspecialchars($item['icon']) . '"></i>' . htmlspecialchars($item['label']) . '</a>';
        }

        $actionsBlock = $actionsHtml !== ''
            ? '<div class="admin-action-band no-print"><div><h2 class="admin-section-title h5 mb-1">' . htmlspecialchars($title) . '</h2><p class="text-muted mb-0">' . htmlspecialchars($subtitle) . '</p></div><div class="d-flex gap-2 flex-wrap">' . $actionsHtml . '</div></div>'
            : '';

        return '
        <header class="admin-header-shell no-print">
            <div class="admin-top-row">
                <div>
                    <h1 class="admin-page-title">' . htmlspecialchars($title) . '</h1>
                    <p class="admin-page-subtitle">' . htmlspecialchars($subtitle) . '</p>
                </div>
                <div class="admin-date-pill">
                    <i class="fas fa-calendar-day"></i>
                    <span>' . date('d M Y') . '</span>
                </div>
            </div>
            <nav class="admin-nav-strip" aria-label="Navigasi admin">' . $navHtml . '</nav>
            ' . $actionsBlock . '
        </header>';
    }
}

if (!function_exists('renderAdminSectionCard')) {
    // Blok judul section yang dipakai di dashboard dan halaman admin lain.
    function renderAdminSectionCard(string $title, string $subtitle, array $actions = []): string
    {
        $actionsHtml = '';
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $label = htmlspecialchars((string) ($action['label'] ?? 'Aksi'));
            $href = htmlspecialchars((string) ($action['href'] ?? '#'));
            $class = htmlspecialchars((string) ($action['class'] ?? 'btn btn-primary'));
            $icon = trim((string) ($action['icon'] ?? ''));
            $iconHtml = $icon !== '' ? '<i class="fas ' . htmlspecialchars($icon) . ' me-1"></i>' : '';
            $actionsHtml .= '<a href="' . $href . '" class="' . $class . '">' . $iconHtml . $label . '</a>';
        }

        return '
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
                <h4 class="admin-section-title mb-1">' . htmlspecialchars($title) . '</h4>
                <p class="text-muted mb-0">' . htmlspecialchars($subtitle) . '</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">' . $actionsHtml . '</div>
        </div>';
    }
}
