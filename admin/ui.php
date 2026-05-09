<?php

if (!function_exists('adminPageStyles')) {
    function adminPageStyles(): string
    {
        return <<<'HTML'
<style>
    body {
        background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 100%);
    }
    .admin-shell {
        min-height: 100vh;
    }
    .admin-topbar {
        background: linear-gradient(135deg, #1d4ed8 0%, #0f766e 100%);
        box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
    }
    .admin-hero {
        background: linear-gradient(135deg, rgba(29, 78, 216, .95) 0%, rgba(15, 118, 110, .95) 100%);
        border: 0;
        border-radius: 1rem;
        color: #fff;
        box-shadow: 0 14px 36px rgba(15, 23, 42, .10);
    }
    .admin-hero .text-white-50 {
        color: rgba(255,255,255,.72) !important;
    }
    .admin-card {
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
    }
    .admin-section-title {
        letter-spacing: -.01em;
    }
    .metric-card {
        border: 0;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
        border-radius: 1rem;
    }
    .table thead th {
        white-space: nowrap;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        .admin-card, .metric-card, .admin-hero {
            box-shadow: none !important;
        }
    }
</style>
HTML;
    }
}

if (!function_exists('renderAdminHeader')) {
    function renderAdminHeader(string $active, string $title, string $subtitle, array $actions = []): string
    {
        $actionsHtml = '';
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $label = htmlspecialchars((string) ($action['label'] ?? 'Aksi'));
            $href = htmlspecialchars((string) ($action['href'] ?? '#'));
            $class = htmlspecialchars((string) ($action['class'] ?? 'btn btn-light'));
            $icon = trim((string) ($action['icon'] ?? ''));
            $iconHtml = $icon !== '' ? '<i class="fas ' . htmlspecialchars($icon) . ' me-1"></i>' : '';
            $actionsHtml .= '<a href="' . $href . '" class="' . $class . '">' . $iconHtml . $label . '</a>';
        }

        return '
        <nav class="navbar navbar-dark admin-topbar no-print">
            <div class="container py-2">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 w-100">
                    <div>
                        <a class="navbar-brand fw-semibold" href="dashboard.php">Dasbor Admin</a>
                        <div class="text-white-50 small">Fokus pengelolaan ' . htmlspecialchars(ucfirst($active)) . '</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ' . ($active !== 'dashboard' ? '<a class="btn btn-outline-light btn-sm" href="dashboard.php"><i class="fas fa-house me-1"></i>Dashboard</a>' : '') . '
                        <a class="btn btn-light btn-sm" href="../proses/logout.php"><i class="fas fa-right-from-bracket me-1"></i>Logout</a>
                    </div>
                </div>
            </div>
        </nav>
        <div class="container mt-4 no-print">
            <div class="card admin-hero mb-3">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div class="pe-lg-4">
                            <h2 class="mb-1">' . htmlspecialchars($title) . '</h2>
                            <p class="mb-0 text-white-50">' . htmlspecialchars($subtitle) . '</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">' . $actionsHtml . '</div>
                    </div>
                </div>
            </div>
        </div>';
    }
}

if (!function_exists('renderAdminSectionCard')) {
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
