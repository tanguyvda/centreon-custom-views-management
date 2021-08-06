--
-- Remove topology
--
DELETE FROM topology where `topology_parent`=644;
DELETE FROM topology where `topology_page`=644;

--
-- Remove module tables
--
DROP TABLE mod_ccvm_custom_view_ownership;