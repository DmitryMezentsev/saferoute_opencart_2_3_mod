<?php

function checkCol($db, $name)
{
    return $db->query("SELECT *
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '".DB_DATABASE."'
        AND TABLE_NAME = '".DB_PREFIX."order'
        AND COLUMN_NAME = '$name'")->num_rows;
}


if (!checkCol($this->db,'ddelivery_id'))
    $this->db->query("ALTER TABLE `".DB_PREFIX."order` ADD `ddelivery_id` char(48) NOT NULL");

if (!checkCol($this->db, 'in_ddelivery_cabinet'))
    $this->db->query("ALTER TABLE `".DB_PREFIX."order` ADD `in_ddelivery_cabinet` TINYINT(1) DEFAULT 0");