<?php

require dirname(__DIR__) . '/app/bootstrap.php';
use app\Services\Repository;
use app\Services\CharacterService;
use app\Rules\Engine;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
foreach (glob(ROOT . '/database/migrations/*.php') as $f) {
    (require $f)($db);
}
$count = 0;
function check(bool $ok, string $name): void
{
    global $count;
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $count++;
    echo "PASS $name\n";
}
function rejects(callable $fn, string $name, int $code = 0): void
{
    try {
        $fn();
    } catch (DomainException $e) {
        check(!$code || $e->getCode() === $code, $name);
        return;
    }
    throw new RuntimeException('FAIL expected rejection: ' . $name);
}
$db->prepare('INSERT INTO users(email,password,created_at) VALUES (?,?,?)')->execute(['one@example.test',password_hash('test-password-long', PASSWORD_DEFAULT),date('c')]);
$one = (int)$db->lastInsertId();
$db->prepare('INSERT INTO users(email,password,created_at) VALUES (?,?,?)')->execute(['two@example.test','unusable',date('c')]);
$two = (int)$db->lastInsertId();
$r = new Repository($db, $one);
$s = new CharacterService($r);
$id = $r->create(['name' => 'Éléonore <script>','class' => 'Guerrière','level' => 4,'attributes' => ['str' => 4,'dex' => 2,'con' => 3]]);
$c = $r->get($id);
function act(string $action, array $d = []): array
{
    global $s,$r,$id;
    return $s->run($id, $action, array_merge(['revision' => $r->get($id)['revision']], $d));
}
check($c['computed']['hp_max'] === 52, 'creation and HP formula');
check(Engine::proficiency(7, 0) === 0 && Engine::proficiency(7, 2) === 11, 'proficiency');
check(Engine::stack([['value' => 2,'type' => 'status'],['value' => 1,'type' => 'status'],['value' => -1,'type' => 'status'],['value' => -2,'type' => 'status'],['value' => 3,'type' => 'item']])['total'] === 3, 'typed stacking positive and negative');
$c = act('hp', ['mode' => 'temporary','amount' => 5]);
$c = act('hp', ['mode' => 'damage','amount' => 17]);
check($c['hp'] == 40 && $c['temp_hp'] == 0, 'temporary HP absorbs damage');
$audit = $c['audit_history'][0]['id'];
$c = act('undo_hp', ['audit_id' => $audit]);
check($c['hp'] == 52 && $c['temp_hp'] == 5, 'HP undo');
$c = act('hp', ['mode' => 'damage','amount' => 17]);
$c = act('hp', ['mode' => 'heal','amount' => 8]);
check($c['hp'] == 48, 'healing');
$c = act('skill', ['name' => 'Athlétisme','attribute' => 'str','rank' => 2,'misc' => 1]);
check($c['computed']['stats']['Athlétisme']['total'] === 13, 'skill calculation');
$c = act('entry', ['collection' => 'conditions','name' => 'Effrayé','data' => ['value' => 2]]);
check($c['computed']['stats']['Athlétisme']['total'] === 11, 'frightened mechanics');
$c = act('entry', ['collection' => 'items','name' => 'Épée longue','data' => ['type' => 'arme','quantity' => 1,'bulk' => 1,'equipped' => true,'rank' => 2,'dice' => 2,'die' => 8,'potency' => 1,'traits' => 'agile','damage_type' => 'tranchants']]);
check($c['computed']['attacks'][0]['map'] === [11,7,3], 'weapon and agile MAP');
check($db->query('SELECT quantity FROM items')->fetchColumn() == 1, 'inventory relational column');
$item = $c['items'][0];
$c = act('consume', ['collection' => 'items','entry_id' => $item['id']]);
check($c['items'][0]['data']['quantity'] === 0, 'consume inventory');
rejects(fn () => act('consume', ['collection' => 'items','entry_id' => $item['id']]), 'no negative quantity', 422);
$c = act('currency', ['amount' => 50,'unit' => 'po','reason' => 'Quête']);
$c = act('currency', ['amount' => -15,'unit' => 'po','reason' => 'Potion']);
check($c['copper'] === 3500, 'currency exact integer arithmetic');
$tx = end($c['currency_transactions']);
$c = act('currency_undo', ['transaction' => $tx['id']]);
check($c['copper'] === 5000, 'currency undo');
rejects(fn () => act('currency', ['amount' => -100,'unit' => 'po']), 'insufficient funds', 422);
$c = act('entry', ['collection' => 'spell_slots','name' => 'Arcane rang 1','data' => ['spell_rank' => 1,'current' => 2,'max' => 3]]);
$slot = $c['spell_slots'][0]['id'];
$c = act('consume', ['collection' => 'spell_slots','entry_id' => $slot]);
check($c['spell_slots'][0]['data']['current'] === 1, 'consume spell slot');
$c = act('entry', ['collection' => 'spells','name' => 'Sort personnel','data' => ['spell_rank' => 1,'tradition' => 'arcane','casting' => 'prepared','used' => false]]);
$spell = $c['spells'][0]['id'];
$c = act('consume', ['collection' => 'spells','entry_id' => $spell]);
check($c['spells'][0]['data']['used'] === 1, 'spell usage');
$c = act('rest', ['slots' => true,'spells' => true]);
check($c['spell_slots'][0]['data']['current'] === 3 && !$c['spells'][0]['data']['used'], 'rest choices');
$c = act('level_up', ['boosts' => ['str','dex','con','wis'],'choices' => 'Don personnel']);
check($c['level'] == 5 && $c['computed']['attributes']['str'] === 4 && $c['computed']['attributes']['con'] === 4, 'level and partial boosts');
check(count($c['level_history']) === 2, 'level history');
check(Engine::boost(4, 1) === [5,0], 'second partial boost');
rejects(fn () => (new Repository($db, $two))->get($id), 'ownership isolation', 404);
rejects(fn () => $s->run($id, 'hp', ['revision' => 1,'mode' => 'heal','amount' => 1]), 'stale revision rejected', 409);
$document = $r->export($id);
$new = $s->import($document);
$copy = $r->get($new);
check(count($copy['items']) === 1 && $copy['copper'] === $c['copper'] && $copy['computed']['attributes'] === $c['computed']['attributes'], 'JSON round trip');
rejects(fn () => $s->import(['format' => 'pf2-character','version' => 2,'character' => []]), 'unknown import version');
$c = act('entry', ['collection' => 'items','name' => 'Bouclier acier','data' => ['type' => 'bouclier','equipped' => true,'quantity' => 1,'hp' => 20,'hp_max' => 20,'broken' => 10,'hardness' => 5,'ac' => 2]]);
$c = act('update', ['shield_raised' => 1]);
$hp = $c['hp'];
$c = act('shield_block', ['amount' => 8]);
check($c['hp'] === $hp - 3, 'shield block damage');

$c = act('entry', ['collection' => 'resources','name' => 'Bombes','data' => ['current' => 2,'max' => 3,'reset' => 'daily']]);
$resource = end($c['resources']);
$c = act('consume', ['collection' => 'resources','entry_id' => $resource['id']]);
check(end($c['resources'])['data']['current'] === 1, 'custom resource');
$c = act('entry', ['collection' => 'modifiers','name' => 'Rapide bonus','data' => ['target' => 'Armure','type' => 'status','value' => 1,'active' => true,'rounds' => 1]]);
$c = act('next_round');
check(!end($c['modifiers'])['data']['active'], 'modifier expiration');
$before = $c['copper'];
$c = act('transfer', ['target_id' => $new,'copper' => 100]);
check($c['copper'] === $before - 100 && $r->get($new)['copper'] === $copy['copper'] + 100, 'atomic currency transfer');
rejects(fn () => act('update', ['focus' => 3,'focus_max' => 2]), 'focus maximum validation', 422);
rejects(fn () => act('entry', ['collection' => 'items','name' => 'Invalid','data' => ['equipped' => 'false']]), 'boolean validation', 422);
$c = act('entry', ['collection' => 'spells','name' => 'Focus test','data' => ['casting' => 'focus','spell_rank' => 1]]);
$focusSpell = end($c['spells']);
rejects(fn () => act('cast', ['spell_id' => $focusSpell['id']]), 'focus spell resource rejection', 422);
$c = act('update', ['focus_max' => 2,'focus' => 2]);
$c = act('cast', ['spell_id' => $focusSpell['id']]);
check($c['focus'] == 1, 'focus spell consumption');
$c = act('entry', ['collection' => 'items','name' => 'Potion test','data' => ['type' => 'consommable','quantity' => 1]]);
$potion = end($c['items']);
$c = act('use_item', ['entry_id' => $potion['id'],'heal' => 2]);
check(end($c['items'])['data']['quantity'] === 0, 'consumable operation');
// Equipment bonuses follow the item and survive JSON export/import by name.
$c = act('entry', ['collection' => 'items','name' => 'Anneau test','data' => ['type' => 'magique','quantity' => 1,'equipped' => true,'invested' => true]]);
$ring = end($c['items']);
$baseArcana = $c['computed']['stats']['Arcanes']['total'];
$c = act('entry', ['collection' => 'modifiers','name' => 'Anneau test — Arcanes','data' => ['target' => 'Arcanes','type' => 'item','value' => 2,'active' => true,'equipment' => 'Anneau test','requires_investment' => true]]);
check($c['computed']['stats']['Arcanes']['total'] === $baseArcana + 2, 'equipped invested item bonus');
$c = act('entry', ['collection' => 'items','entry_id' => $ring['id'],'name' => $ring['name'],'data' => array_merge($ring['data'], ['invested' => false])]);
check($c['computed']['stats']['Arcanes']['total'] === $baseArcana, 'uninvesting removes item bonus');
$c = act('entry', ['collection' => 'items','entry_id' => $ring['id'],'name' => $ring['name'],'data' => array_merge($ring['data'], ['container' => 'Sac'])]);
check($c['computed']['stats']['Arcanes']['total'] === $baseArcana, 'stored equipment has no active bonus');
$inventory = [
    ['name' => 'Sac','data' => ['quantity' => 1,'bulk' => 1,'extradimensional' => true,'capacity' => 5]],
    ['name' => 'Réserve','data' => ['quantity' => 30,'bulk' => .1,'container' => 'Sac']],
];
check(app\Rules\InventoryCalculator::calculate($inventory)['bulk'] === 1.0, 'dimensional contents excluded from carried bulk');
$inventory[0]['data']['capacity'] = 2;
check(app\Rules\InventoryCalculator::calculate($inventory)['bulk'] === 4.0, 'overfilled container retains carried weight');
$inventory[0]['name'] = 'Autre sac';
check(app\Rules\InventoryCalculator::calculate($inventory)['bulk'] === 4.0, 'missing container retains carried weight');
rejects(fn () => act('entry', ['collection' => 'items','name' => 'Sac invalide','data' => ['capacity' => -1]]), 'negative capacity rejected', 422);
$c = act('entry', ['collection' => 'spells','name' => 'Grimoire seulement','data' => ['casting' => 'prepared','spell_rank' => 1,'prepared' => false]]);
$bookSpell = end($c['spells']);
rejects(fn () => act('cast', ['spell_id' => $bookSpell['id'],'slot_id' => $slot]), 'unprepared spell cannot consume a slot', 422);
$c = act('update', ['name' => 'Test modifié']);
check($c['name'] === 'Test modifié', 'update character');
act('delete');
rejects(fn () => $r->get($id), 'delete cascade', 404);
check($db->query('SELECT COUNT(*) FROM items WHERE character_id=' . $id)->fetchColumn() == 0, 'cascade cleanup');
echo "$count tests passed\n";
