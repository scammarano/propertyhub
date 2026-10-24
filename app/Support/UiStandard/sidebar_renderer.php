<?php
declare(strict_types=1);
require_once __DIR__ . '/SidebarRenderer.php';

function npvc_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function npvc_render_sidebar(array $config, array $groups, string $currentUrl): void
{
    echo SidebarRenderer::render($config, $groups, $currentUrl, $config['active_code'] ?? null);
}
