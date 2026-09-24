<?php
return function (PDO $db): void {
 $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
 $id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
 $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
 $tables=[
 'users'=>"id $id, email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, created_at VARCHAR(30) NOT NULL",
 'characters'=>"id $id, user_id INTEGER NOT NULL, name VARCHAR(190) NOT NULL, ancestry VARCHAR(100) NOT NULL DEFAULT '', heritage VARCHAR(100) NOT NULL DEFAULT '', background VARCHAR(100) NOT NULL DEFAULT '', class VARCHAR(100) NOT NULL DEFAULT '', player VARCHAR(100) NOT NULL DEFAULT '', campaign VARCHAR(100) NOT NULL DEFAULT '', level INTEGER NOT NULL DEFAULT 1, xp INTEGER NOT NULL DEFAULT 0, xp_target INTEGER NOT NULL DEFAULT 1000, milestone INTEGER NOT NULL DEFAULT 0, ancestry_hp INTEGER NOT NULL DEFAULT 8, class_hp INTEGER NOT NULL DEFAULT 8, hp INTEGER NOT NULL DEFAULT 16, temp_hp INTEGER NOT NULL DEFAULT 0, hp_bonus INTEGER NOT NULL DEFAULT 0, focus INTEGER NOT NULL DEFAULT 0, focus_max INTEGER NOT NULL DEFAULT 0, speed INTEGER NOT NULL DEFAULT 25, key_attribute VARCHAR(10) NOT NULL DEFAULT 'str', spell_attribute VARCHAR(10) NOT NULL DEFAULT 'int', archived INTEGER NOT NULL DEFAULT 0, shield_raised INTEGER NOT NULL DEFAULT 0, revision INTEGER NOT NULL DEFAULT 1, portrait VARCHAR(100) NOT NULL DEFAULT '', details TEXT NOT NULL, created_at VARCHAR(30) NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id)",
 'character_attributes'=>"id $id, character_id INTEGER NOT NULL, name VARCHAR(20) NOT NULL, value INTEGER NOT NULL DEFAULT 0, partial INTEGER NOT NULL DEFAULT 0, UNIQUE(character_id,name), FOREIGN KEY(character_id) REFERENCES characters(id) ON DELETE CASCADE",
 'character_skills'=>"id $id, character_id INTEGER NOT NULL, name VARCHAR(100) NOT NULL, attribute VARCHAR(10) NOT NULL, `rank` INTEGER NOT NULL DEFAULT 0, misc INTEGER NOT NULL DEFAULT 0, UNIQUE(character_id,name), FOREIGN KEY(character_id) REFERENCES characters(id) ON DELETE CASCADE",
 'currencies'=>"id $id, character_id INTEGER NOT NULL UNIQUE, copper INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(character_id) REFERENCES characters(id) ON DELETE CASCADE",
 ];
 foreach(['items','spells','feats','conditions','resources','notes','journal_entries','modifiers','level_history','currency_transactions','audit_history','abilities','actions','spell_slots'] as $table) {
  $tables[$table]="id $id, character_id INTEGER NOT NULL, name VARCHAR(190) NOT NULL, data TEXT NOT NULL, created_at VARCHAR(30) NOT NULL, FOREIGN KEY(character_id) REFERENCES characters(id) ON DELETE CASCADE";
 }
 foreach($tables as $name=>$columns) { $db->exec("CREATE TABLE $name ($columns)$suffix"); if(!in_array($name,['users','characters'])) $db->exec("CREATE INDEX idx_{$name}_character ON $name(character_id)"); }
 $db->exec('CREATE INDEX idx_characters_owner ON characters(user_id,archived)');
};
