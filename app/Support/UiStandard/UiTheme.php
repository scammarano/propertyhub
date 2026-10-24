<?php
declare(strict_types=1);

final class UiTheme
{
    public static function defaults(): array
    {
        return [
            'preset'=>'violet','primary'=>'#5a40d6','sidebar'=>'#15182b',
            'topbar'=>'#ffffff','background'=>'#f4f6f9','appearance'=>'light',
            'density'=>'comfortable','radius'=>12,'font_scale'=>100,
            'table_density'=>'normal','chart_palette'=>'casamia',
            'kpi_style'=>'soft','chart_labels'=>1,'sidebar_default'=>'expanded',
        ];
    }

    public static function sanitize(array $input): array
    {
        $d=self::defaults();
        $color=static fn($value,$fallback)=>is_string($value)&&preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;
        return [
            'preset'=>preg_replace('/[^a-z0-9_-]/i','',(string)($input['preset']??$d['preset']))?:$d['preset'],
            'primary'=>$color($input['primary']??null,$d['primary']),
            'sidebar'=>$color($input['sidebar']??null,$d['sidebar']),
            'topbar'=>$color($input['topbar']??null,$d['topbar']),
            'background'=>$color($input['background']??null,$d['background']),
            'appearance'=>in_array($input['appearance']??'', ['light','dark','system'],true)?$input['appearance']:$d['appearance'],
            'density'=>in_array($input['density']??'', ['compact','comfortable','spacious'],true)?$input['density']:$d['density'],
            'radius'=>max(4,min(24,(int)($input['radius']??$d['radius']))),
            'font_scale'=>max(85,min(120,(int)($input['font_scale']??$d['font_scale']))),
            'table_density'=>in_array($input['table_density']??'', ['compact','normal','spacious'],true)?$input['table_density']:$d['table_density'],
            'chart_palette'=>preg_replace('/[^a-z0-9_-]/i','',(string)($input['chart_palette']??$d['chart_palette']))?:$d['chart_palette'],
            'kpi_style'=>in_array($input['kpi_style']??'', ['soft','solid','minimal'],true)?$input['kpi_style']:$d['kpi_style'],
            'chart_labels'=>!empty($input['chart_labels'])?1:0,
            'sidebar_default'=>($input['sidebar_default']??'')==='collapsed'?'collapsed':'expanded',
        ];
    }

    public static function css(array $theme, array $palette=[]): string
    {
        $t=self::sanitize($theme);
        $palette=$palette?:['#5a40d6','#1aa374','#e2a20f','#dc4b55','#087ea4','#9b59b6','#64748b','#ea580c'];
        $vars=[
            '--primary'=>$t['primary'],
            '--primary-rgb'=>implode(',',sscanf(ltrim($t['primary'],'#'),'%02x%02x%02x')),
            '--primary-strong'=>'color-mix(in srgb,'.$t['primary'].' 82%,#000000)',
            '--sidebar-bg'=>$t['sidebar'],
            '--sidebar-active'=>'color-mix(in srgb,'.$t['primary'].' 30%,'.$t['sidebar'].')',
            '--topbar-bg'=>$t['topbar'],
            '--bg'=>$t['background'],
            '--radius'=>$t['radius'].'px',
            '--font-scale'=>$t['font_scale'].'%',
            '--cam-primary'=>$t['primary'],'--cam-sidebar'=>$t['sidebar'],
            '--cam-topbar'=>$t['topbar'],'--cam-background'=>$t['background'],
            '--cam-radius'=>$t['radius'].'px','--cam-font-scale'=>$t['font_scale'].'%',
        ];
        foreach(array_values($palette) as $i=>$color)$vars['--chart-'.($i+1)]=$color;
        return ':root{'.implode('',array_map(static fn($k,$v)=>$k.':'.$v.';',array_keys($vars),$vars)).'}';
    }
}
