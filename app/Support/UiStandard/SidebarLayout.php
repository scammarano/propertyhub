<?php
declare(strict_types=1);

/**
 * Normaliza la barra lateral global preservando permisos funcionales.
 *
 * Estructura admitida:
 * - GROUP en el primer nivel.
 * - SECTION opcional dentro de GROUP.
 * - ITEM dentro de GROUP o SECTION.
 *
 * Compatibilidad: los proyectos 1.1.x pueden continuar usando solamente
 * groups[].items[].
 */
final class SidebarLayout
{
    private const TYPES = ['GROUP', 'SECTION', 'ITEM'];

    public static function withDefaultState(array $groups): array
    {
        $normalized = [];
        foreach (array_values($groups) as $gIndex => $group) {
            $group = self::normalizeNode($group, 'GROUP', ($gIndex + 1) * 100);
            $sections = [];
            foreach (array_values((array)($group['sections'] ?? [])) as $sIndex => $section) {
                $section = self::normalizeNode($section, 'SECTION', ($sIndex + 1) * 100);
                $section['items'] = self::normalizeItems((array)($section['items'] ?? []));
                $sections[] = $section;
            }
            $group['sections'] = $sections;
            $group['items'] = self::normalizeItems((array)($group['items'] ?? []));
            $normalized[] = $group;
        }
        return $normalized;
    }

    private static function normalizeItems(array $items): array
    {
        $result = [];
        foreach (array_values($items) as $index => $item) {
            $result[] = self::normalizeNode($item, 'ITEM', ($index + 1) * 100);
        }
        return $result;
    }

    private static function normalizeNode(array $node, string $type, int $defaultOrder): array
    {
        $node['entry_type'] = $type;
        $node['active'] = array_key_exists('active', $node) ? (int)$node['active'] : 1;
        $node['sort_order'] = (int)($node['sort_order'] ?? $defaultOrder);
        $node['code'] = trim((string)($node['code'] ?? ''));
        if ($node['code'] === '') {
            throw new InvalidArgumentException("Toda entrada {$type} debe tener un código estable.");
        }
        return $node;
    }

    public static function isEntryActive(array $entry): bool
    {
        return (int)($entry['active'] ?? 1) === 1;
    }

    public static function baseline(array $groups): array
    {
        $groups = self::withDefaultState($groups);
        foreach ($groups as $gIndex => &$group) {
            $group['sort_order'] = ($gIndex + 1) * 100;
            $group['active'] = 1;
            foreach ($group['sections'] as $sIndex => &$section) {
                $section['sort_order'] = ($sIndex + 1) * 100;
                $section['active'] = 1;
                foreach ($section['items'] as $iIndex => &$item) {
                    $item['sort_order'] = ($iIndex + 1) * 100;
                    $item['active'] = 1;
                }
                unset($item);
            }
            unset($section);
            foreach ($group['items'] as $iIndex => &$item) {
                $item['sort_order'] = ($iIndex + 1) * 100;
                $item['active'] = 1;
            }
            unset($item);
        }
        unset($group);
        return $groups;
    }

    public static function baselineRows(array $groups, ?int $updatedBy = null): array
    {
        $rows = [];
        foreach (self::baseline($groups) as $group) {
            $groupCode = (string)$group['code'];
            $rows[] = self::row('GROUP', $group, null, $updatedBy);
            foreach ($group['sections'] as $section) {
                $sectionCode = (string)$section['code'];
                $rows[] = self::row('SECTION', $section, $groupCode, $updatedBy);
                foreach ($section['items'] as $item) {
                    $rows[] = self::row('ITEM', $item, $sectionCode, $updatedBy);
                }
            }
            foreach ($group['items'] as $item) {
                $rows[] = self::row('ITEM', $item, $groupCode, $updatedBy);
            }
        }
        return $rows;
    }

    private static function row(string $type, array $node, ?string $parent, ?int $updatedBy): array
    {
        return [
            'entry_type' => $type,
            'entry_code' => (string)$node['code'],
            'parent_code' => $parent,
            'sort_order' => (int)$node['sort_order'],
            'active' => 1,
            'label_override' => null,
            'updated_by' => $updatedBy,
        ];
    }

    public static function normalize(array $groups, array $stored, bool $initialized = true): array
    {
        $baseline = self::baseline($groups);
        if (!$initialized || !$stored) return $baseline;

        $preferences = [];
        foreach ($stored as $row) {
            $type = strtoupper(trim((string)($row['entry_type'] ?? '')));
            $code = trim((string)($row['entry_code'] ?? ''));
            if (in_array($type, self::TYPES, true) && $code !== '') {
                $preferences[$type . ':' . $code] = $row;
            }
        }

        $groupsByCode = [];
        $sectionsByCode = [];
        $items = [];

        foreach ($baseline as $group) {
            $groupCode = (string)$group['code'];
            $group = self::applyPreference($group, 'GROUP', $preferences['GROUP:' . $groupCode] ?? [], null);
            $group['sections'] = [];
            $group['items'] = [];
            $groupsByCode[$groupCode] = $group;

            foreach ((array)($baselineGroup = self::findNode($baseline, $groupCode, 'GROUP'))['sections'] ?? [] as $section) {
                $sectionCode = (string)$section['code'];
                $section = self::applyPreference($section, 'SECTION', $preferences['SECTION:' . $sectionCode] ?? [], $groupCode);
                $section['items'] = [];
                $sectionsByCode[$sectionCode] = $section;
            }
            foreach ((array)$baselineGroup['items'] as $item) {
                $items[] = self::prepareItem($item, $groupCode, $preferences);
            }
            foreach ((array)$baselineGroup['sections'] as $section) {
                foreach ((array)$section['items'] as $item) {
                    $items[] = self::prepareItem($item, (string)$section['code'], $preferences);
                }
            }
        }

        // Coloca secciones. Una sección puede cambiar de grupo si el destino existe.
        foreach ($sectionsByCode as $sectionCode => &$section) {
            $baselineParent = (string)($section['_baseline_parent'] ?? '');
            $parent = (string)($section['_preferred_parent'] ?? $baselineParent);
            if (!isset($groupsByCode[$parent]) || !empty($section['protected'])) $parent = $baselineParent;
            unset($section['_baseline_parent'], $section['_preferred_parent']);
            if (isset($groupsByCode[$parent])) $groupsByCode[$parent]['sections'][] = $section;
        }
        unset($section);

        // Índice de secciones ya colocadas para asignar ítems.
        $sectionParents = [];
        foreach ($groupsByCode as $groupCode => $group) {
            foreach ($group['sections'] as $section) $sectionParents[(string)$section['code']] = $groupCode;
        }

        foreach ($items as $item) {
            $baselineParent = (string)$item['_baseline_parent'];
            $parent = (string)($item['_preferred_parent'] ?? $baselineParent);
            if (!empty($item['protected'])) $parent = $baselineParent;
            unset($item['_baseline_parent'], $item['_preferred_parent']);

            if (isset($sectionParents[$parent])) {
                $groupCode = $sectionParents[$parent];
                foreach ($groupsByCode[$groupCode]['sections'] as &$section) {
                    if ((string)$section['code'] === $parent) {
                        $section['items'][] = $item;
                        break;
                    }
                }
                unset($section);
            } elseif (isset($groupsByCode[$parent])) {
                $groupsByCode[$parent]['items'][] = $item;
            } elseif (isset($sectionParents[$baselineParent])) {
                $groupCode = $sectionParents[$baselineParent];
                foreach ($groupsByCode[$groupCode]['sections'] as &$section) {
                    if ((string)$section['code'] === $baselineParent) {
                        $section['items'][] = $item;
                        break;
                    }
                }
                unset($section);
            } elseif (isset($groupsByCode[$baselineParent])) {
                $groupsByCode[$baselineParent]['items'][] = $item;
            }
        }

        foreach ($groupsByCode as &$group) {
            self::sortNodes($group['items']);
            foreach ($group['sections'] as &$section) self::sortNodes($section['items']);
            unset($section);
            self::sortNodes($group['sections']);
        }
        unset($group);

        $result = array_values($groupsByCode);
        self::sortNodes($result);
        $result = self::filterVisible($result);

        return self::countVisibleItems($result) > 0 ? $result : $baseline;
    }

    private static function findNode(array $groups, string $code, string $type): array
    {
        foreach ($groups as $group) {
            if ($type === 'GROUP' && (string)$group['code'] === $code) return $group;
            foreach ((array)$group['sections'] as $section) {
                if ($type === 'SECTION' && (string)$section['code'] === $code) return $section;
            }
        }
        return [];
    }

    private static function prepareItem(array $item, string $baselineParent, array $preferences): array
    {
        $code = (string)$item['code'];
        return self::applyPreference($item, 'ITEM', $preferences['ITEM:' . $code] ?? [], $baselineParent);
    }

    private static function applyPreference(array $node, string $type, array $preference, ?string $baselineParent): array
    {
        $node['entry_type'] = $type;
        $node['sort_order'] = (int)($preference['sort_order'] ?? $node['sort_order'] ?? 100);
        $node['active'] = !empty($node['protected']) ? 1 : (int)($preference['active'] ?? 1);
        $label = trim((string)($preference['label_override'] ?? ''));
        if ($label !== '') $node['label'] = $label;
        if ($baselineParent !== null) {
            $node['_baseline_parent'] = $baselineParent;
            $node['_preferred_parent'] = trim((string)($preference['parent_code'] ?? $baselineParent));
        }
        return $node;
    }

    private static function sortNodes(array &$nodes): void
    {
        usort($nodes, static fn(array $a, array $b): int =>
            ((int)($a['sort_order'] ?? 100) <=> (int)($b['sort_order'] ?? 100))
            ?: strcmp((string)($a['code'] ?? ''), (string)($b['code'] ?? ''))
        );
    }

    private static function filterVisible(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            if (!self::isEntryActive($group)) continue;
            $group['items'] = array_values(array_filter((array)$group['items'], [self::class, 'isEntryActive']));
            $sections = [];
            foreach ((array)$group['sections'] as $section) {
                if (!self::isEntryActive($section)) continue;
                $section['items'] = array_values(array_filter((array)$section['items'], [self::class, 'isEntryActive']));
                if ($section['items'] || !empty($section['href'])) $sections[] = $section;
            }
            $group['sections'] = $sections;
            if ($group['items'] || $group['sections'] || !empty($group['href'])) $result[] = $group;
        }
        return $result;
    }

    private static function countVisibleItems(array $groups): int
    {
        $count = 0;
        foreach ($groups as $group) {
            $count += count((array)($group['items'] ?? []));
            foreach ((array)($group['sections'] ?? []) as $section) $count += count((array)($section['items'] ?? []));
        }
        return $count;
    }

    public static function assertValidSubmission(array $activeGroups, array $activeItems, array $activeSections = []): void
    {
        if (count(array_unique(array_map('strval', $activeGroups))) < 1 || count(array_unique(array_map('strval', $activeItems))) < 1) {
            throw new RuntimeException('La barra lateral no puede quedar vacía.');
        }
        if ($activeSections && count(array_unique(array_map('strval', $activeSections))) < 1) {
            throw new RuntimeException('Debe existir al menos una sección visible cuando el diseño usa secciones.');
        }
    }

    public static function assertSuperadmin(string $roleCode): void
    {
        if (strtoupper(trim($roleCode)) !== 'SUPERADMIN') {
            throw new RuntimeException('Solo el superadministrador puede modificar la barra lateral global.');
        }
    }
}
