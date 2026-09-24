<?php

namespace app\Services;

final class Validator
{
    public static function text(mixed $v, int $max = 10000, bool $required = false): string
    {
        if (!is_string($v) || mb_strlen($v) > $max || ($required && trim($v) === '')) {
            throw new \DomainException('Texte manquant ou trop long.', 422);
        }
        return trim($v);
    }

    public static function integer(mixed $v, int $min, int $max): int
    {
        if (filter_var($v, FILTER_VALIDATE_INT) === false || $v < $min || $v > $max) {
            throw new \DomainException("Valeur entière attendue entre $min et $max.", 422);
        }
        return (int)$v;
    }

    public static function collection(string $table): void
    {
        if (!in_array($table, ['items','spells','feats','conditions','resources','notes','journal_entries','modifiers','abilities','actions','spell_slots'])) {
            throw new \DomainException('Collection non modifiable.', 422);
        }
    }

    public static function data(string $table, array $d): array
    {
        if (count($d) > 80 || strlen(json_encode($d)) > 60000) {
            throw new \DomainException('Contenu trop volumineux.', 422);
        }
        $bounds = ['quantity' => [0,100000],'rank' => [0,4],'value' => [-100,100],'hp' => [0,100000],'hp_max' => [0,100000],'current' => [0,100000],'max' => [0,100000],'level' => [0,25],'potency' => [0,4],'dice' => [1,10],'die' => [2,12],'ac' => [0,20],'dex_cap' => [0,99],'strength' => [-5,10],'penalty' => [0,20],'speed_penalty' => [0,100],'hardness' => [0,100],'broken' => [0,100000],'charges' => [0,100000],'actions' => [0,3],'spell_rank' => [0,10],'rounds' => [0,100000]];
        foreach ($d as $key => $value) {
            if (isset($bounds[$key])) {
                $d[$key] = self::integer($value, ...$bounds[$key]);
            } elseif (is_string($value)) {
                $d[$key] = self::text($value, 30000);
            } elseif (!is_bool($value) && !is_numeric($value)) {
                throw new \DomainException('Champ invalide.', 422);
            }
        }
        if (isset($d['bulk']) && (!is_numeric($d['bulk']) || $d['bulk'] < 0 || $d['bulk'] > 100000)) {
            throw new \DomainException('Encombrement invalide.', 422);
        }
        if ($table === 'modifiers' && (!in_array($d['type'] ?? '', ['item','status','circumstance','untyped']))) {
            throw new \DomainException('Type de modificateur invalide.', 422);
        }
        if (isset($d['current'],$d['max']) && $d['current'] > $d['max']) {
            throw new \DomainException('La ressource dépasse son maximum.', 422);
        }
        foreach (\app\Database\Fields::MAP[$table] ?? [] as $key => $type) {
            if (isset($d[$key]) && preg_match('/VARCHAR\((\d+)\)/', $type, $match)) {
                $d[$key] = self::text((string)$d[$key], (int)$match[1]);
            }
        }
        foreach (['equipped','invested','consumable','prepared','used','active','ranged','attack'] as $key) {
            if (isset($d[$key]) && !in_array($d[$key], [true,false,0,1], true)) {
                throw new \DomainException('Champ booléen invalide.', 422);
            }
        }
        if ($table === 'items' && isset($d['type']) && !in_array($d['type'], ['arme','armure','bouclier','consommable','outil','porte','tenu','tresor','magique','divers'])) {
            throw new \DomainException('Catégorie d’objet invalide.', 422);
        }
        if ($table === 'spells' && isset($d['casting']) && !in_array($d['casting'], ['prepared','spontaneous','innate','focus','cantrip'])) {
            throw new \DomainException('Mode d’incantation invalide.', 422);
        }
        return $d;
    }
}
