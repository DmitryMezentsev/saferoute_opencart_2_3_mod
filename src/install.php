<?php

/**
 * @param $db string
 * @param $name string
 * @return bool
 */
function checkCol($db, $name)
{
    return (bool) $db->query("SHOW COLUMNS FROM `".DB_PREFIX."order` LIKE '$name'")->num_rows;
}


if (!checkCol($this->db, 'saferoute_id'))
    $this->db->query("ALTER TABLE `".DB_PREFIX."order` ADD `saferoute_id` char(48) NOT NULL");

if (!checkCol($this->db, 'in_saferoute_cabinet'))
    $this->db->query("ALTER TABLE `".DB_PREFIX."order` ADD `in_saferoute_cabinet` TINYINT(1) DEFAULT 0");