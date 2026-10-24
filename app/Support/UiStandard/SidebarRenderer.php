<?php
declare(strict_types=1);

require_once __DIR__ . '/SidebarLayout.php';

final class SidebarRenderer
{
    public static function render(array $config, array $groups, string $currentUri = '', ?string $activeCode = null): string
    {
        $groups = SidebarLayout::withDefaultState($groups);
        $home = (string)($config['home_url'] ?? 'index.php');
        $brand = (string)($config['brand_name'] ?? 'Aplicación');
        $company = (string)($config['company_name'] ?? '');
        $logo = (string)($config['logo_url'] ?? '');
        $sidebarId = (string)($config['sidebar_id'] ?? 'cam-main-sidebar');

        $html = '<aside id="' . self::e($sidebarId) . '" class="app-sidebar shadow" data-bs-theme="dark"';
        $html .= ' data-enable-persistence="true" data-sidebar-breakpoint="' . self::e((string)($config['breakpoint'] ?? '991.98')) . '" data-cam-sidebar="1">';
        $html .= '<div class="sidebar-brand"><a href="' . self::e($home) . '" class="brand-link">';
        if ($logo !== '') {
            $html .= '<img src="' . self::e($logo) . '" alt="' . self::e($company !== '' ? $company : $brand) . '" class="brand-image cam-company-logo">';
        } else {
            $html .= '<span class="brand-image cam-brand-mark">' . self::e((string)($config['brand_mark'] ?? '2C')) . '</span>';
        }
        $html .= '<span class="brand-text"><strong>' . self::e($brand) . '</strong><small>' . self::e($company) . '</small></span></a></div>';
        $html .= '<div class="sidebar-wrapper"><nav class="mt-2"><ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false" role="menu">';

        foreach ($groups as $group) {
            if (!SidebarLayout::isEntryActive($group)) continue;
            $html .= self::renderGroup($group, $currentUri, $activeCode);
        }

        $html .= '</ul></nav></div>';
        $html .= '<div class="cam-sidebar-footer"><span>' . self::e((string)($config['site_code'] ?? '')) . '</span><small>' . self::e((string)($config['role'] ?? '')) . '</small></div>';
        $html .= '</aside>';
        return $html;
    }

    public static function earlyRestoreScript(): string
    {
        return '<script>(function(){try{var k="lte.sidebar.state",l=localStorage.getItem("2cam:sidebar-collapsed");if(localStorage.getItem(k)===null&&l!==null)localStorage.setItem(k,l==="1"?"sidebar-collapse":"sidebar-open");if(innerWidth>991.98&&localStorage.getItem(k)==="sidebar-collapse")document.body.classList.add("sidebar-collapse");}catch(e){}})();</script>';
    }

    private static function renderGroup(array $group, string $currentUri, ?string $activeCode): string
    {
        $code = (string)$group['code'];
        $children = self::groupChildren($group);
        $active = self::nodeIsActive($group, $currentUri, $activeCode);
        $open = $active || self::childrenHaveActive($children, $currentUri, $activeCode);

        if (!$children && !empty($group['href'])) return self::renderLeaf($group, $currentUri, $activeCode);

        $html = '<li class="nav-item' . ($open ? ' menu-open' : '') . '" data-nav-group="' . self::e($code) . '" data-cam-menu-key="' . self::e($code) . '">';
        $html .= '<a href="#" class="nav-link' . ($active ? ' active' : '') . '" data-nav-group-toggle aria-expanded="' . ($open ? 'true' : 'false') . '">';
        $html .= self::icon($group) . '<p>' . self::e((string)($group['label'] ?? 'Módulo')) . '<span class="nav-arrow">›</span></p></a>';
        $html .= '<ul class="nav nav-treeview cam-level-two" aria-hidden="' . ($open ? 'false' : 'true') . '">';
        foreach ($children as $child) {
            if (!SidebarLayout::isEntryActive($child)) continue;
            $html .= self::renderSecondLevel($child, $currentUri, $activeCode);
        }
        $html .= '</ul></li>';
        return $html;
    }

    private static function renderSecondLevel(array $node, string $currentUri, ?string $activeCode): string
    {
        $items = (array)($node['items'] ?? []);
        if (!$items) return self::renderLeaf($node, $currentUri, $activeCode);

        $active = self::nodeIsActive($node, $currentUri, $activeCode);
        $open = $active || self::childrenHaveActive($items, $currentUri, $activeCode);
        $code = (string)$node['code'];
        $html = '<li class="nav-item' . ($open ? ' menu-open' : '') . '" data-nav-group="' . self::e($code) . '" data-cam-menu-key="' . self::e($code) . '">';
        $html .= '<a href="#" class="nav-link' . ($active ? ' active' : '') . '" data-nav-group-toggle aria-expanded="' . ($open ? 'true' : 'false') . '">';
        $html .= self::icon($node) . '<p>' . self::e((string)($node['label'] ?? 'Sección')) . '<span class="nav-arrow">›</span></p></a>';
        $html .= '<ul class="nav nav-treeview cam-level-three" aria-hidden="' . ($open ? 'false' : 'true') . '">';
        foreach ($items as $item) {
            if (SidebarLayout::isEntryActive($item)) $html .= self::renderLeaf($item, $currentUri, $activeCode);
        }
        $html .= '</ul></li>';
        return $html;
    }

    private static function renderLeaf(array $item, string $currentUri, ?string $activeCode): string
    {
        $active = self::nodeIsActive($item, $currentUri, $activeCode);
        $href = (string)($item['href'] ?? '#');
        return '<li class="nav-item"><a class="nav-link' . ($active ? ' active' : '') . '" href="' . self::e($href) . '"' .
            (!empty($item['target']) ? ' target="' . self::e((string)$item['target']) . '"' : '') . '>' .
            self::icon($item) . '<p>' . self::e((string)($item['label'] ?? 'Opción')) . '</p></a></li>';
    }

    private static function groupChildren(array $group): array
    {
        return array_merge((array)($group['sections'] ?? []), (array)($group['items'] ?? []));
    }

    private static function childrenHaveActive(array $children, string $currentUri, ?string $activeCode): bool
    {
        foreach ($children as $child) {
            if (self::nodeIsActive($child, $currentUri, $activeCode)) return true;
            if (self::childrenHaveActive((array)($child['items'] ?? []), $currentUri, $activeCode)) return true;
        }
        return false;
    }

    private static function nodeIsActive(array $node, string $currentUri, ?string $activeCode): bool
    {
        $code = (string)($node['code'] ?? '');
        if ($activeCode !== null && $activeCode !== '') return hash_equals($code, $activeCode);
        $href = trim((string)($node['href'] ?? ''));
        if ($href === '' || $href === '#') return false;
        $target = parse_url($href);
        $current = parse_url($currentUri);
        $rawTargetPath = (string)($target['path'] ?? '');
        $targetPath = self::normalizedPath($rawTargetPath);
        $currentPath = self::normalizedPath((string)($current['path'] ?? ''));
        if ($targetPath === '') return false;
        $absoluteTarget = str_starts_with($rawTargetPath, '/');
        $pathMatches = $absoluteTarget
            ? $targetPath === $currentPath
            : ($targetPath === ltrim($currentPath, '/') || str_ends_with($currentPath, '/' . ltrim($targetPath, '/')));
        if (!$pathMatches) return false;
        if (!isset($target['query']) || $target['query'] === '') return true;
        parse_str((string)$target['query'], $targetQuery);
        parse_str((string)($current['query'] ?? ''), $currentQuery);
        foreach ($targetQuery as $key => $value) {
            if (!array_key_exists($key, $currentQuery) || (string)$currentQuery[$key] !== (string)$value) return false;
        }
        return true;
    }

    private static function normalizedPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return rtrim($path, '/');
    }

    private static function icon(array $node): string
    {
        // icon_html pertenece al código de la aplicación, nunca a datos editables del usuario.
        return '<span class="nav-icon">' . ($node['icon_html'] ?? '<span class="cam-child-dot"></span>') . '</span>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
