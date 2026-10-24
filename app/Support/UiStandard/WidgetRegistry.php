<?php
declare(strict_types=1);

/**
 * Registro de widgets y variantes visuales.
 * La fuente de datos se identifica por data_key; cada variante solo cambia
 * la presentación. Nunca debe volver a ejecutar consultas diferentes para
 * representar el mismo indicador.
 */
final class WidgetRegistry
{
    private array $definitions=[];
    public function register(array $definition): void
    {
        foreach(['code','project_code','data_key','name','variants'] as $required){
            if(empty($definition[$required]))throw new InvalidArgumentException('Widget incompleto: '.$required);
        }
        $code=(string)$definition['code'];
        if(isset($this->definitions[$code]))throw new RuntimeException('Widget duplicado: '.$code);
        $this->definitions[$code]=$definition;
    }
    public function forProject(string $projectCode): array
    {
        $projectCode=strtoupper(trim($projectCode));
        return array_filter($this->definitions,static fn($d)=>strtoupper((string)$d['project_code'])===$projectCode);
    }
    public function variants(string $widgetCode,string $projectCode): array
    {
        $def=$this->definitions[$widgetCode]??null;
        if(!$def||strtoupper((string)$def['project_code'])!==strtoupper($projectCode))return [];
        return (array)$def['variants'];
    }
    public function resolve(string $widgetCode,string $variantCode,string $projectCode): array
    {
        $def=$this->definitions[$widgetCode]??null;
        if(!$def||strtoupper((string)$def['project_code'])!==strtoupper($projectCode)){
            throw new RuntimeException('El widget no pertenece a este proyecto.');
        }
        foreach((array)$def['variants'] as $variant){
            if((string)$variant['code']===$variantCode)return ['widget'=>$def,'variant'=>$variant];
        }
        throw new RuntimeException('Variante de widget no encontrada.');
    }
}
