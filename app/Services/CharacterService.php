<?php

namespace app\Services;

use app\Rules\Engine;

final class CharacterService
{
    public function __construct(private Repository $r)
    {
    }

    public function run(int $id, string $action, array $d): array
    {
        $this->r->db->beginTransaction();
        try {
            $c = $this->r->get($id);
            if (!isset($d['revision']) || (int)$d['revision'] !== (int)$c['revision']) {
                throw new \DomainException('La fiche a changé. Rechargez avant de réessayer.', 409);
            }
            $q = $this->r->query('UPDATE characters SET revision=revision+1 WHERE id=? AND revision=?', [$id,$c['revision']]);
            if ($q->rowCount() !== 1) {
                throw new \DomainException('Modification concurrente.', 409);
            }
            $result = $this->mutate($id, $action, $d, $c);
            if ($action !== 'delete') {
                $this->r->audit($id, $result['message'] ?? $action, $result['audit'] ?? []);
            }
            $this->r->db->commit();
            if ($action === 'delete' && preg_match('/^[a-f0-9]{48}\.jpg$/', $c['portrait']) && is_file(ROOT . '/storage/uploads/' . $c['portrait'])) {
                unlink(ROOT . '/storage/uploads/' . $c['portrait']);
            }
            return $action === 'delete' ? ['deleted' => true] : $this->r->get($id);
        } catch (\Throwable $e) {
            if ($this->r->db->inTransaction()) {
                $this->r->db->rollBack();
            }
            throw $e;
        }
    }

    private function update(int $id, array $values): void
    {
        $sets = array_map(fn ($key) => "$key=?", array_keys($values));
        $this->r->query('UPDATE characters SET ' . implode(',', $sets) . ' WHERE id=?', [...array_values($values),$id]);
    }

    private function mutate(int $id, string $action, array $d, array $c): array
    {
        return match($action) {
            'cast' => $this->handleCast($id, $d, $c),
            'use_item' => $this->handleUseItem($id, $d, $c),
            'next_round' => $this->handleNextRound($id, $d, $c),
            'transfer' => $this->handleTransfer($id, $d, $c),
            'shield_block' => $this->handleShieldBlock($id, $d, $c),
            'delete' => $this->handleDelete($id, $d, $c),
            'update' => $this->handleUpdate($id, $d, $c),
            'attributes' => $this->handleAttributes($id, $d, $c),
            'skill' => $this->handleSkill($id, $d, $c),
            'hp' => $this->handleHp($id, $d, $c),
            'currency' => $this->handleCurrency($id, $d, $c),
            'currency_undo' => $this->handleCurrencyUndo($id, $d, $c),
            'entry' => $this->handleEntry($id, $d, $c),
            'remove_entry' => $this->handleRemoveEntry($id, $d, $c),
            'consume' => $this->handleConsume($id, $d, $c),
            'xp' => $this->handleXp($id, $d, $c),
            'level_up' => $this->handleLevelUp($id, $d, $c),
            'rest' => $this->handleRest($id, $d, $c),
            'undo_hp' => $this->handleUndoHp($id, $d, $c),
            default => throw new \DomainException('Action inconnue.', 404),
        };
    }

    private function handleCast(int $id, array $d, array $c): array
    {
        $spell = null;
        foreach ($c['spells'] as $row) {
            if ($row['id'] == ($d['spell_id'] ?? 0)) {
                $spell = $row;
            }
        }
        if (!$spell) {
            throw new \DomainException('Sort introuvable.', 404);
        }
        $sd = $spell['data'];
        $casting = $sd['casting'] ?? 'prepared';
        if ($casting === 'focus') {
            if ($c['focus'] < 1) {
                throw new \DomainException('Aucun point de focus.', 422);
            }
            $this->update($id, ['focus' => $c['focus'] - 1]);
        } elseif (!in_array($casting, ['cantrip','innate'])) {
            $slot = null;
            foreach ($c['spell_slots'] as $row) {
                if ($row['id'] == ($d['slot_id'] ?? 0)) {
                    $slot = $row;
                }
            }
            if (!$slot || ($slot['data']['current'] ?? 0) < 1 || ($slot['data']['spell_rank'] ?? 0) < ($sd['spell_rank'] ?? 0)) {
                throw new \DomainException('Emplacement disponible de rang suffisant requis.', 422);
            }
            if ($casting === 'prepared' && !empty($sd['used'])) {
                throw new \DomainException('Cette préparation a déjà été utilisée.', 422);
            }
            $slots = $slot['data'];
            $slots['current']--;
            $this->r->updateEntry('spell_slots', (int)$slot['id'], $slots);
        }
        if (in_array($casting, ['prepared','innate'])) {
            if (!empty($sd['used'])) {
                throw new \DomainException('Sort déjà utilisé.', 422);
            }
            $sd['used'] = true;
            $this->r->updateEntry('spells', (int)$spell['id'], $sd);
        }
        return ['message' => 'Sort lancé : ' . $spell['name']];
    }

    private function handleUseItem(int $id, array $d, array $c): array
    {
        $item = null;
        foreach ($c['items'] as $row) {
            if ($row['id'] == ($d['entry_id'] ?? 0)) {
                $item = $row;
            }
        }
        if (!$item || ($item['data']['quantity'] ?? 0) < 1) {
            throw new \DomainException('Objet indisponible.', 422);
        }
        $data = $item['data'];
        $data['quantity']--;
        $this->r->updateEntry('items', (int)$item['id'], $data);
        $heal = Validator::integer($d['heal'] ?? 0, 0, 100000);
        if ($heal) {
            $this->update($id, ['hp' => min($c['computed']['hp_max'], $c['hp'] + $heal)]);
        }
        return ['message' => 'Objet consommé : ' . $item['name'] . ($heal ? ' (+' . $heal . ' soins)' : '')];
    }

    private function handleNextRound(int $id, array $d, array $c): array
    {
        foreach ($c['modifiers'] as $row) {
            $m = $row['data'];
            if (!empty($m['active']) && ($m['rounds'] ?? 0) > 0) {
                $m['rounds']--;
                if (!$m['rounds']) {
                    $m['active'] = false;
                }
                $this->r->updateEntry('modifiers', (int)$row['id'], $m);
            }
        }
        $this->update($id, ['shield_raised' => 0]);
        return ['message' => 'Nouveau tour : bouclier abaissé et durées avancées'];
    }

    private function handleTransfer(int $id, array $d, array $c): array
    {
        $target = Validator::integer($d['target_id'] ?? 0, 1, 2147483647);
        if ($target === $id) {
            throw new \DomainException('Choisissez un autre personnage.', 422);
        }
        $other = $this->r->owned($target);
        $amount = Validator::integer($d['copper'] ?? 0, 1, 1000000000);
        if ($amount > $c['copper']) {
            throw new \DomainException('Solde insuffisant.', 422);
        }
        $this->r->query('UPDATE characters SET revision=revision+1 WHERE id=?', [$target]);
        $this->r->query('UPDATE currencies SET copper=copper-? WHERE character_id=?', [$amount,$id]);
        $this->r->query('UPDATE currencies SET copper=copper+? WHERE character_id=?', [$amount,$target]);
        $balance = (int)$this->r->query('SELECT copper FROM currencies WHERE character_id=?', [$target])->fetchColumn();
        if ($balance > 1000000000) {
            throw new \DomainException('Solde destinataire excessif.', 422);
        }
        $this->r->entry($id, 'currency_transactions', 'Transfert vers ' . $other['name'], ['delta' => -$amount,'balance' => $c['copper'] - $amount,'reversed' => true]);
        $this->r->entry($target, 'currency_transactions', 'Transfert depuis ' . $c['name'], ['delta' => $amount,'balance' => $balance,'reversed' => true]);
        $this->r->audit($target, 'Transfert reçu : ' . $amount . ' pc');
        return ['message' => 'Transfert : ' . $amount . ' pc vers ' . $other['name']];
    }

    private function handleShieldBlock(int $id, array $d, array $c): array
    {
        $amount = Validator::integer($d['amount'] ?? 0, 0, 100000);
        $shield = null;
        foreach ($c['items'] as $item) {
            if (($item['data']['type'] ?? '') === 'bouclier' && !empty($item['data']['equipped'])) {
                $shield = $item;
            }
        }
        if (!$shield || !$c['shield_raised'] || ($shield['data']['hp'] ?? 0) <= ($shield['data']['broken'] ?? 0)) {
            throw new \DomainException('Un bouclier intact et levé est nécessaire.', 422);
        }
        $remaining = max(0, $amount - (int)($shield['data']['hardness'] ?? 0));
        $sd = $shield['data'];
        $sd['hp'] = max(0, (int)$sd['hp'] - $remaining);
        $this->r->updateEntry('items', (int)$shield['id'], $sd);
        $temp = max(0, $c['temp_hp'] - $remaining);
        $hp = max(0, $c['hp'] - max(0, $remaining - $c['temp_hp']));
        $this->update($id, ['hp' => $hp,'temp_hp' => $temp,'shield_raised' => $sd['hp'] <= ($sd['broken'] ?? 0) ? 0 : 1]);
        return ['message' => "Blocage : $remaining dégâts après dureté"];
    }

    private function handleDelete(int $id, array $d, array $c): array
    {
        $this->r->query('DELETE FROM characters WHERE id=?', [$id]);
        return [];
    }

    private function handleUpdate(int $id, array $d, array $c): array
    {
        $v = [];
        foreach (['name' => 190,'ancestry' => 100,'heritage' => 100,'background' => 100,'class' => 100,'player' => 100,'campaign' => 100] as $k => $max) {
            if (array_key_exists($k, $d)) {
                $v[$k] = Validator::text($d[$k], $max, $k === 'name');
            }
        }
        foreach (['ancestry_hp' => [1,100],'class_hp' => [1,100],'hp_bonus' => [-1000,10000],'speed' => [0,1000],'focus_max' => [0,3],'focus' => [0,3],'xp_target' => [1,100000],'archived' => [0,1],'milestone' => [0,1],'shield_raised' => [0,1]] as $k => $range) {
            if (isset($d[$k])) {
                $v[$k] = Validator::integer($d[$k], ...$range);
            }
        }
        foreach (['key_attribute','spell_attribute'] as $k) {
            if (isset($d[$k])) {
                if (!isset(\app\Rules\Catalog::ATTRIBUTES[$d[$k]])) {
                    throw new \DomainException('Attribut invalide.', 422);
                }
                $v[$k] = $d[$k];
            }
        }
        if (isset($d['details'])) {
            if (!is_array($d['details'])) {
                throw new \DomainException('Détails invalides.', 422);
            }
            foreach ($d['details'] as $x) {
                Validator::text($x, 10000);
            }
            $v['details'] = json_encode($d['details'], JSON_THROW_ON_ERROR);
        }
        if (isset($v['focus']) && $v['focus'] > ($v['focus_max'] ?? $c['focus_max'])) {
            throw new \DomainException('Focus supérieur au maximum.', 422);
        }
        if (isset($v['focus_max'])) {
            $v['focus'] = min($v['focus'] ?? $c['focus'], $v['focus_max']);
        }
        if ($v) {
            $this->update($id, $v);
        }
        return ['message' => 'Informations mises à jour'];
    }

    private function handleAttributes(int $id, array $d, array $c): array
    {
        foreach ($d['attributes'] ?? [] as $key => $value) {
            if (!isset(\app\Rules\Catalog::ATTRIBUTES[$key])) {
                throw new \DomainException('Attribut invalide.', 422);
            }
            $this->r->query('UPDATE character_attributes SET value=? WHERE character_id=? AND name=?', [Validator::integer($value, -5, 10),$id,$key]);
        }
        return ['message' => 'Attributs modifiés'];
    }

    private function handleSkill(int $id, array $d, array $c): array
    {
        $name = Validator::text($d['name'] ?? '', 100, true);
        $attr = $d['attribute'] ?? 'int';
        if (!isset(\app\Rules\Catalog::ATTRIBUTES[$attr])) {
            throw new \DomainException('Attribut invalide.', 422);
        }
        $rank = Validator::integer($d['rank'] ?? 0, 0, 4);
        $misc = Validator::integer($d['misc'] ?? 0, -100, 100);
        $row = $this->r->query('SELECT id FROM character_skills WHERE character_id=? AND name=?', [$id,$name])->fetch();
        if ($row) {
            $this->r->query('UPDATE character_skills SET `rank`=?,misc=?,attribute=? WHERE id=?', [$rank,$misc,$attr,$row['id']]);
        } else {
            $this->r->insert('character_skills', ['character_id' => $id,'name' => $name,'attribute' => $attr,'rank' => $rank,'misc' => $misc]);
        }
        return ['message' => 'Maîtrise : ' . $name];
    }

    private function handleHp(int $id, array $d, array $c): array
    {
        $amount = Validator::integer($d['amount'] ?? 0, 0, 100000);
        $hp = (int)$c['hp'];
        $temp = (int)$c['temp_hp'];
        if (($d['mode'] ?? '') === 'damage') {
            $absorbed = min($temp, $amount);
            $temp -= $absorbed;
            $hp = max(0, $hp - ($amount - $absorbed));
        } elseif (($d['mode'] ?? '') === 'heal') {
            $hp = min($c['computed']['hp_max'], $hp + $amount);
        } elseif (($d['mode'] ?? '') === 'temporary') {
            $temp = max($temp, $amount);
        } else {
            throw new \DomainException('Opération PV invalide.', 422);
        }
        $this->update($id, ['hp' => $hp,'temp_hp' => $temp]);
        return ['message' => "PV {$c['hp']} → $hp ; temporaires $temp",'audit' => ['undo' => ['hp' => $c['hp'],'temp_hp' => $c['temp_hp']],'after' => ['hp' => $hp,'temp_hp' => $temp]]];
    }

    private function handleCurrency(int $id, array $d, array $c): array
    {
        $amount = Validator::integer($d['amount'] ?? 0, -100000000, 100000000);
        $unit = $d['unit'] ?? 'po';
        $factor = ['pc' => 1,'pa' => 10,'po' => 100,'pp' => 1000][$unit] ?? null;
        if (!$factor) {
            throw new \DomainException('Monnaie invalide.', 422);
        }
        $next = $c['copper'] + $amount * $factor;
        if ($next < 0 || $next > 1000000000) {
            throw new \DomainException('Solde insuffisant ou montant excessif.', 422);
        }
        $this->r->query('UPDATE currencies SET copper=? WHERE character_id=?', [$next,$id]);
        $tid = $this->r->entry($id, 'currency_transactions', Validator::text($d['reason'] ?? 'Transaction', 190), ['delta' => $amount * $factor,'balance' => $next]);
        return ['message' => sprintf('%+d %s', $amount, $unit),'audit' => ['transaction' => $tid]];
    }

    private function handleCurrencyUndo(int $id, array $d, array $c): array
    {
        $tx = $this->r->query('SELECT * FROM currency_transactions WHERE character_id=? AND id=?', [$id,(int)($d['transaction'] ?? 0)])->fetch();
        if (!$tx) {
            throw new \DomainException('Transaction introuvable.', 404);
        }
        $td = \app\Database\Fields::hydrate('currency_transactions', $tx);
        if (!empty($td['reversed'])) {
            throw new \DomainException('Transaction déjà annulée.', 422);
        }
        $next = $c['copper'] - $td['delta'];
        if ($next < 0) {
            throw new \DomainException('Solde insuffisant pour annuler.', 422);
        }
        $td['reversed'] = true;
        $this->r->updateEntry('currency_transactions', (int)$tx['id'], $td);
        $this->r->query('UPDATE currencies SET copper=? WHERE character_id=?', [$next,$id]);
        $this->r->entry($id, 'currency_transactions', 'Annulation : ' . $tx['name'], ['delta' => -$td['delta'],'balance' => $next,'reversed' => true]);
        return ['message' => 'Transaction annulée'];
    }

    private function handleEntry(int $id, array $d, array $c): array
    {
        $table = (string)($d['collection'] ?? '');
        Validator::collection($table);
        $name = Validator::text($d['name'] ?? '', 190, true);
        $input = $d['data'] ?? [];
        if (!is_array($input)) {
            throw new \DomainException('Données invalides.', 422);
        }
        $data = Validator::data($table, $input);
        if ($table === 'items' && !empty($data['equipped']) && in_array($data['type'] ?? '', ['armure','bouclier'])) {
            foreach ($c['items'] ?? [] as $other) {
                if (($other['data']['type'] ?? '') === $data['type'] && $other['id'] != ($d['entry_id'] ?? 0) && !empty($other['data']['equipped'])) {
                    $od = $other['data'];
                    $od['equipped'] = false;
                    $this->r->updateEntry('items', (int)$other['id'], $od);
                }
            }
        }
        if ($table === 'conditions' && ($data['value'] ?? 1) < 1) {
            throw new \DomainException('Une condition active doit avoir une valeur positive.', 422);
        }
        if (isset($d['entry_id'])) {
            $entry = $this->r->query("SELECT id FROM $table WHERE id=? AND character_id=?", [(int)$d['entry_id'],$id])->fetch();
            if (!$entry) {
                throw new \DomainException('Élément introuvable.', 404);
            }
            $this->r->updateEntry($table, (int)$entry['id'], $data, $name);
        } else {
            $this->r->entry($id, $table, $name, $data);
        }
        return ['message' => 'Enregistré : ' . $name];
    }

    private function handleRemoveEntry(int $id, array $d, array $c): array
    {
        $table = (string)($d['collection'] ?? '');
        Validator::collection($table);
        $this->r->query("DELETE FROM $table WHERE id=? AND character_id=?", [(int)($d['entry_id'] ?? 0),$id]);
        return ['message' => 'Élément supprimé : ' . $table];
    }

    private function handleConsume(int $id, array $d, array $c): array
    {
        $table = (string)($d['collection'] ?? '');
        if (!in_array($table, ['items','spells','spell_slots','resources'])) {
            throw new \DomainException('Consommation invalide.', 422);
        }
        $row = $this->r->query("SELECT * FROM $table WHERE id=? AND character_id=?", [(int)($d['entry_id'] ?? 0),$id])->fetch();
        if (!$row) {
            throw new \DomainException('Élément introuvable.', 404);
        }
        $v = \app\Database\Fields::hydrate($table, $row);
        $key = $table === 'items' ? 'quantity' : ($table === 'spells' ? 'used' : 'current');
        $delta = Validator::integer($d['delta'] ?? -1, -1, 1);
        if ($table === 'spells') {
            $v['used'] = !($v['used'] ?? false);
        } else {
            $v[$key] = (int)($v[$key] ?? 0) + $delta;
            if ($v[$key] < 0 || ($key === 'current' && $v[$key] > (int)($v['max'] ?? 0))) {
                throw new \DomainException('Ressource indisponible.', 422);
            }
        }
        $this->r->updateEntry($table, (int)$row['id'], $v);
        return ['message' => 'Utilisation : ' . $row['name']];
    }

    private function handleXp(int $id, array $d, array $c): array
    {
        $xp = (int)$c['xp'] + Validator::integer($d['amount'] ?? 0, -100000, 100000);
        if ($xp < 0) {
            throw new \DomainException('XP insuffisants.', 422);
        }
        $this->update($id, ['xp' => $xp]);
        return ['message' => "XP {$c['xp']} → $xp"];
    }

    private function handleLevelUp(int $id, array $d, array $c): array
    {
        if ($c['level'] >= 20) {
            throw new \DomainException('Niveau maximum atteint.', 422);
        }
        $next = (int)$c['level'] + 1;
        $boosts = $d['boosts'] ?? [];
        if (!is_array($boosts) || count(array_unique($boosts)) !== count($boosts) || count($boosts) > 4 || ($boosts && !in_array($next, [5,10,15,20]))) {
            throw new \DomainException('Boosts invalides pour ce niveau.', 422);
        }
        $before = $this->r->export($id);
        foreach ($boosts as $attr) {
            $found = false;
            foreach ($c['attributes'] as $r) {
                if ($r['name'] === $attr) {
                    [$value,$partial] = Engine::boost((int)$r['value'], (int)$r['partial']);
                    $this->r->query('UPDATE character_attributes SET value=?,partial=? WHERE id=?', [$value,$partial,$r['id']]);
                    $found = true;
                }
            }
            if (!$found) {
                throw new \DomainException('Boost invalide.', 422);
            }
        }
        $choices = Validator::text($d['choices'] ?? '', 20000);
        foreach ($d['skills'] ?? [] as $skill) {
            $rank = Validator::integer($skill['rank'] ?? 0, 0, 4);
            if (($rank === 3 && $next < 7) || ($rank === 4 && $next < 15)) {
                throw new \DomainException('Maîtrise inaccessible à ce niveau.', 422);
            }
            $this->mutate($id, 'skill', $skill, $c);
        }
        foreach ($d['entries'] ?? [] as $entry) {
            if (!in_array($entry['collection'] ?? '', ['feats','abilities','spells','spell_slots'])) {
                throw new \DomainException('Choix de progression invalide.', 422);
            }
            $this->mutate($id, 'entry', $entry, $c);
        }
        $choices .= "\n" . json_encode(['skills' => $d['skills'] ?? [],'entries' => $d['entries'] ?? []], JSON_UNESCAPED_UNICODE);
        $this->update($id, ['level' => $next,'xp' => !empty($d['spend_xp']) ? max(0, $c['xp'] - $c['xp_target']) : $c['xp']]);
        $after = $this->r->get($id);
        $gain = $after['computed']['hp_max'] - $c['computed']['hp_max'];
        $this->update($id, ['hp' => max(0, $c['hp'] + $gain)]);
        $this->r->entry($id, 'level_history', 'Niveau ' . $next, ['boosts' => $boosts,'choices' => $choices,'hp_gain' => $gain,'before' => $before]);
        return ['message' => "Niveau {$c['level']} → $next (+$gain PV)"];
    }

    private function handleRest(int $id, array $d, array $c): array
    {
        $v = [];
        if (!empty($d['hp'])) {
            $v['hp'] = min($c['computed']['hp_max'], $c['hp'] + max(1, $c['computed']['attributes']['con']) * (int)$c['level']);
        }
        if (!empty($d['focus'])) {
            $v['focus'] = $c['focus_max'];
        }
        if (!empty($d['temporary'])) {
            $v['temp_hp'] = 0;
        }
        if ($v) {
            $this->update($id, $v);
        }
        foreach (['slots' => 'spell_slots','resources' => 'resources'] as $flag => $table) {
            if (!empty($d[$flag])) {
                foreach ($c[$table] as $r) {
                    $data = $r['data'];
                    if ($table === 'resources' && ($data['reset'] ?? '') !== 'daily') {
                        continue;
                    }
                    $data['current'] = $data['max'] ?? 0;
                    $this->r->updateEntry($table, (int)$r['id'], $data);
                }
            }
        }
        if (!empty($d['spells'])) {
            foreach ($c['spells'] as $r) {
                $data = $r['data'];
                $data['used'] = false;
                $this->r->updateEntry('spells', (int)$r['id'], $data);
            }
        }
        return ['message' => 'Repos : choix appliqués','audit' => ['selection' => $d]];
    }

    private function handleUndoHp(int $id, array $d, array $c): array
    {
        $row = $this->r->query('SELECT * FROM audit_history WHERE id=? AND character_id=?', [(int)($d['audit_id'] ?? 0),$id])->fetch();
        $data = $row ? json_decode($row['data'], true) : [];
        if (empty($data['undo']) || !empty($data['undone']) || $data['after']['hp'] != $c['hp'] || $data['after']['temp_hp'] != $c['temp_hp']) {
            throw new \DomainException('Annulation impossible après une autre modification des PV.', 409);
        }
        $this->update($id, $data['undo']);
        $data['undone'] = true;
        $this->r->query('UPDATE audit_history SET data=? WHERE id=?', [json_encode($data),$row['id']]);
        return ['message' => 'Modification de PV annulée'];
    }

    public function configureNew(int $id, array $data): void
    {
        $c = $this->r->get($id);
        $this->mutate($id, 'update', $data, $c);
        foreach ($data['skills'] ?? [] as $skill) {
            $this->mutate($id, 'skill', $skill, $c);
        }
        foreach ($data['entries'] ?? [] as $entry) {
            $this->mutate($id, 'entry', $entry, $this->r->get($id));
        }
        $copper = Validator::integer($data['copper'] ?? 0, 0, 1000000000);
        $this->r->query('UPDATE currencies SET copper=? WHERE character_id=?', [$copper,$id]);
        $c = $this->r->get($id);
        $this->update($id, ['hp' => $c['computed']['hp_max']]);
        $this->r->audit($id, 'Création guidée terminée');
    }

    public function import(array $document): int
    {
        if (($document['format'] ?? '') !== 'pf2-character' || ($document['version'] ?? 0) !== 1 || !is_array($document['character'] ?? null)) {
            throw new \DomainException('Format JSON non pris en charge.', 422);
        }
        $c = $document['character'];
        $this->r->db->beginTransaction();
        try {
            $id = $this->r->create(['name' => mb_substr(Validator::text($c['name'] ?? '', 190, true), 0, 175) . ' (import)','level' => $c['level'] ?? 1,'ancestry' => $c['ancestry'] ?? '','class' => $c['class'] ?? '']);
            $this->mutate($id, 'update', $c, $this->r->get($id));
            foreach ($c['attributes'] ?? [] as $a) {
                if (!isset(\app\Rules\Catalog::ATTRIBUTES[$a['name'] ?? ''])) {
                    throw new \DomainException('Attribut importé invalide.', 422);
                }
                $this->r->query('UPDATE character_attributes SET value=?,partial=? WHERE character_id=? AND name=?', [Validator::integer($a['value'] ?? 0, -5, 10),Validator::integer($a['partial'] ?? 0, 0, 1),$id,$a['name']]);
            }
            foreach ($c['skills'] ?? [] as $s) {
                $this->mutate($id, 'skill', $s, []);
            }
            foreach (['items','spells','feats','conditions','resources','notes','journal_entries','modifiers','abilities','actions','spell_slots'] as $table) {
                if (count($c[$table] ?? []) > 1000) {
                    throw new \DomainException('Import trop volumineux.', 422);
                }
                foreach ($c[$table] ?? [] as $r) {
                    $this->mutate($id, 'entry', ['collection' => $table,'name' => $r['name'],'data' => $r['data']], []);
                }
            }
            $this->r->query('UPDATE currencies SET copper=? WHERE character_id=?', [Validator::integer($c['copper'] ?? 0, 0, 1000000000),$id]);
            $this->update($id, ['hp' => Validator::integer($c['hp'] ?? 1, 0, 100000),'temp_hp' => Validator::integer($c['temp_hp'] ?? 0, 0, 100000),'xp' => Validator::integer($c['xp'] ?? 0, 0, 1000000)]);
            foreach ($c['level_history'] ?? [] as $row) {
                $this->r->entry($id, 'level_history', Validator::text($row['name'], 190), ['choices' => Validator::text(is_string($row['data']['choices'] ?? null) ? $row['data']['choices'] : json_encode($row['data']['choices'] ?? 'Historique importé', JSON_UNESCAPED_UNICODE), 20000)]);
            }
            foreach ($c['currency_transactions'] ?? [] as $row) {
                $data = $row['data'] ?? [];
                $this->r->entry($id, 'currency_transactions', Validator::text($row['name'] ?? 'Transaction importée', 190), ['delta' => Validator::integer($data['delta'] ?? 0, -1000000000, 1000000000),'balance' => Validator::integer($data['balance'] ?? 0, 0, 1000000000),'reversed' => true]);
            }
            $this->r->audit($id, 'Import JSON');
            $this->r->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->r->db->rollBack();
            throw $e;
        }
    }
}
