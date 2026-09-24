<?php

namespace app\Rules;

final class Engine
{
    public static function proficiency(int $level, int $rank): int
    {
        return $rank === 0 ? 0 : $level + 2 * $rank;
    }

    public static function stack(array $modifiers): array
    {
        $groups = [];
        $used = [];
        foreach ($modifiers as $m) {
            $v = (int)$m['value'];
            $type = $m['type'] ?? 'untyped';
            if ($type === 'untyped') {
                $used[] = $m;
                continue;
            } $key = $type . ($v < 0 ? '-' : '+');
            if (!isset($groups[$key]) || ($v < 0 ? $v < $groups[$key]['value'] : $v > $groups[$key]['value'])) {
                $groups[$key] = $m;
            }
        }
        $used = array_merge($used, array_values($groups));
        return ['total' => array_sum(array_column($used, 'value')),'parts' => $used];
    }

    public static function calculate(array $c): array
    {
        $a = [];
        foreach ($c['attributes'] as $r) {
            $a[$r['name']] = (int)$r['value'];
        }
        $conditions = [];
        foreach ($c['conditions'] as $r) {
            $conditions[$r['name']] = max($conditions[$r['name']] ?? 0, (int)($r['data']['value'] ?? 1));
        }
        $mods = [];
        foreach ($c['modifiers'] as $r) {
            if (($r['data']['active'] ?? true)) {
                $mods[] = array_merge($r['data'], ['label' => $r['name']]);
            }
        }
        foreach (['Effrayé','Malade'] as $name) {
            if (isset($conditions[$name])) {
                $mods[] = ['target' => 'all','type' => 'status','value' => -$conditions[$name],'label' => $name];
            }
        }
        foreach (['Maladroit' => 'dex','Affaibli' => 'str','Drainé' => 'con','Stupéfié' => 'mental'] as $name => $target) {
            if (isset($conditions[$name])) {
                $mods[] = ['target' => $target,'type' => 'status','value' => -$conditions[$name],'label' => $name];
            }
        }
        if (isset($conditions['Fatigué'])) {
            foreach (['Armure','Vigueur','Réflexes','Volonté'] as $target) {
                $mods[] = ['target' => $target,'type' => 'status','value' => -1,'label' => 'Fatigué'];
            }
        }
        if (array_intersect(['À terre','Pris au dépourvu','Agrippé','Entravé','Inconscient'], array_keys($conditions))) {
            $mods[] = ['target' => 'Armure','type' => 'circumstance','value' => -2,'label' => 'Pris au dépourvu'];
        }
        if (isset($conditions['Inconscient'])) {
            foreach (['Armure','Perception','Réflexes'] as $target) {
                $mods[] = ['target' => $target,'type' => 'status','value' => -4,'label' => 'Inconscient'];
            }
        }
        $armor = null;
        $shield = null;
        $bulk = 0;
        foreach ($c['items'] as $item) {
            $d = $item['data'];
            $bulk += (float)($d['bulk'] ?? 0) * (int)($d['quantity'] ?? 1);
            if (!empty($d['equipped']) && (int)($d['quantity'] ?? 1) > 0) {
                if (($d['type'] ?? '') === 'armure') {
                    $armor = $d;
                }
                if (($d['type'] ?? '') === 'bouclier') {
                    $shield = $d;
                }
            }
        }
        $encumbered = floor(round($bulk, 6)) > 5 + $a['str'];
        if ($encumbered) {
            $mods[] = ['target' => 'dex','type' => 'status','value' => -1,'label' => 'Encombré'];
        }
        $stats = [];
        foreach ($c['skills'] as $s) {
            $name = $s['name'];
            $attr = $name === 'Sorts' ? $c['spell_attribute'] : ($name === 'Classe' ? $c['key_attribute'] : $s['attribute']);
            $attribute = $a[$attr] ?? 0;
            $base = in_array($name, ['Armure','Classe']) ? 10 : 0;
            if ($name === 'Armure' && $armor) {
                $attribute = min($attribute, (int)($armor['dex_cap'] ?? 99));
            }
            $parts = [['label' => 'Base','value' => $base,'type' => 'untyped'],['label' => Catalog::ATTRIBUTES[$attr] ?? $attr,'value' => $attribute,'type' => 'untyped'],['label' => Catalog::RANKS[(int)$s['rank']] . ' (niveau ' . $c['level'] . ')','value' => self::proficiency((int)$c['level'], (int)$s['rank']),'type' => 'untyped'],['label' => 'Divers','value' => (int)$s['misc'],'type' => 'untyped']];
            foreach ($mods as $m) {
                if (in_array($m['target'] ?? '', [$name,'all',$attr]) || (($m['target'] ?? '') === 'mental' && in_array($attr, ['int','wis','cha']))) {
                    $parts[] = $m;
                }
            }
            if ($name === 'Armure') {
                if ($armor) {
                    $parts[] = ['label' => 'Armure et rune','type' => 'item','value' => (int)($armor['ac'] ?? 0) + (int)($armor['potency'] ?? 0)];
                } if ($shield && $c['shield_raised'] && (int)($shield['hp'] ?? 0) > (int)($shield['broken'] ?? 0)) {
                    $parts[] = ['label' => 'Bouclier levé','type' => 'circumstance','value' => (int)($shield['ac'] ?? 2)];
                }
            }
            if ($armor && ($a['str'] < (int)($armor['strength'] ?? 0)) && in_array($attr, ['str','dex']) && !in_array($name, ['Armure','Attaque','Réflexes'])) {
                $parts[] = ['label' => 'Pénalité armure','type' => 'untyped','value' => -(int)($armor['penalty'] ?? 0)];
            }
            $stats[$name] = self::stack($parts);
        }
        $attacks = [];
        foreach ($c['items'] as $item) {
            $d = $item['data'];
            if (($d['type'] ?? '') !== 'arme' || empty($d['equipped']) || (int)($d['quantity'] ?? 1) === 0) {
                continue;
            }
            $attr = ($d['ranged'] ?? false) ? 'dex' : ((str_contains(mb_strtolower($d['traits'] ?? ''), 'finesse') && $a['dex'] > $a['str']) ? 'dex' : 'str');
            $parts = [['label' => Catalog::ATTRIBUTES[$attr],'type' => 'untyped','value' => $a[$attr]],['label' => 'Maîtrise arme','type' => 'untyped','value' => self::proficiency((int)$c['level'], (int)($d['rank'] ?? 1))],['label' => 'Rune de puissance','type' => 'item','value' => (int)($d['potency'] ?? 0)]];
            foreach ($mods as $m) {
                if (in_array($m['target'] ?? '', ['all','Attaque',$attr])) {
                    $parts[] = $m;
                }
            }
            $attack = self::stack($parts);
            $agile = str_contains(mb_strtolower($d['traits'] ?? ''), 'agile');
            $damage = ($d['ranged'] ?? false) ? 0 : $a['str'];
            if (!($d['ranged'] ?? false)) {
                $damage -= ($conditions['Affaibli'] ?? 0);
            }
            $attacks[] = ['id' => $item['id'],'name' => $item['name'],'attack' => $attack,'map' => [$attack['total'],$attack['total'] - ($agile ? 4 : 5),$attack['total'] - ($agile ? 8 : 10)],'damage' => max(1, (int)($d['dice'] ?? 1)) . 'd' . ($d['die'] ?? 8) . sprintf('%+d', $damage) . ' ' . ($d['damage_type'] ?? '') . ' ' . ($d['extra_damage'] ?? '')];
        }
        $max = max(1, (int)$c['ancestry_hp'] + (int)$c['level'] * ((int)$c['class_hp'] + $a['con']) + (int)$c['hp_bonus'] - ($conditions['Drainé'] ?? 0) * (int)$c['level']);
        $hpDetails = ['total' => $max,'parts' => [['label' => 'PV d’ascendance','value' => (int)$c['ancestry_hp']],['label' => 'PV de classe × niveau','value' => (int)$c['class_hp'] * (int)$c['level']],['label' => 'Constitution × niveau','value' => $a['con'] * (int)$c['level']],['label' => 'PV divers','value' => (int)$c['hp_bonus']],['label' => 'Drainé × niveau','value' => -($conditions['Drainé'] ?? 0) * (int)$c['level']]]];
        return ['hp_details' => $hpDetails,'stats' => $stats,'attributes' => $a,'hp_max' => $max,'bulk' => floor($bulk * 10) / 10,'bulk_limit' => 5 + $a['str'],'bulk_max' => 10 + $a['str'],'encumbered' => $encumbered,'speed' => max(0, (int)$c['speed'] - ($encumbered ? 10 : 0) - ($armor ? max(0, (int)($armor['speed_penalty'] ?? 0) - ($a['str'] >= (int)($armor['strength'] ?? 0) ? 5 : 0)) : 0)),'attacks' => $attacks,'spell_dc' => 10 + ($stats['Sorts']['total'] ?? 0)];
    }

    public static function boost(int $value, int $partial): array
    {
        if ($value < 4) {
            return [$value + 1,0];
        }
        return $partial ? [$value + 1,0] : [$value,1];
    }
}
