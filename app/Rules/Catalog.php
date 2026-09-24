<?php
namespace app\Rules;
final class Catalog {
 const ATTRIBUTES=['str'=>'Force','dex'=>'Dextérité','con'=>'Constitution','int'=>'Intelligence','wis'=>'Sagesse','cha'=>'Charisme'];
 const RANKS=['Non qualifié','Qualifié','Expert','Maître','Légendaire'];
 const SKILLS=['Acrobaties'=>'dex','Arcanes'=>'int','Athlétisme'=>'str','Artisanat'=>'int','Diplomatie'=>'cha','Discrétion'=>'dex','Intimidation'=>'cha','Médecine'=>'wis','Nature'=>'wis','Occultisme'=>'int','Religion'=>'wis','Représentation'=>'cha','Société'=>'int','Survie'=>'wis','Tromperie'=>'cha','Vol'=>'dex','Perception'=>'wis','Vigueur'=>'con','Réflexes'=>'dex','Volonté'=>'wis','Armure'=>'dex','Attaque'=>'str','Sorts'=>'int','Classe'=>'str'];
 const CONDITIONS=['Effrayé','Malade','Étourdi','Ralenti','Rapide','À terre','Aveuglé','Assourdi','Enchevêtré','Fatigué','Inconscient','Mourant','Blessé','Condamné','Maladroit','Affaibli','Drainé','Stupéfié','Pris au dépourvu','Immobilisé','Agrippé','Entravé','Paralysé','Pétrifié','Fasciné','Confus','Contrôlé','Ébloui','Invisible','Caché','Non détecté','Inaperçu','En fuite','Encombré','Dégâts persistants'];
}
