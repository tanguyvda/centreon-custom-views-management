--
-- topology of the module
--
INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`,
`topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`,
`topology_style_class`, `topology_style_id`, `topology_OnClick`) VALUES
('Custom Views Management', 6, 644, 70, 1, './modules/centreon-custom-views-management/core/index.php', NULL, '0', '1', '1', NULL, NULL, NULL),
('Administration', 644, 64401, 71, 1, './modules/centreon-custom-views-management/core/index.php', NULL, NULL, '1', '1', NULL, NULL, NULL);

-- 
-- tables for the module
--
CREATE TABLE `mod_ccvm_custom_view_ownership` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_view_id` int(11) NOT NULL,
  `new_owner` int(11) NOT NULL,
  `old_owner`int(11),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 
-- unique key
-- 
ALTER TABLE mod_ccvm_custom_view_ownership
ADD CONSTRAINT uk_viewowner UNIQUE (custom_view_id, new_owner);

-- 
-- foreign keys
--
ALTER TABLE mod_ccvm_custom_view_ownership
ADD CONSTRAINT fk_customviewid FOREIGN KEY (custom_view_id) REFERENCES custom_views(custom_view_id)
ON DELETE CASCADE;

ALTER TABLE mod_ccvm_custom_view_ownership
ADD CONSTRAINT fk_newowner FOREIGN KEY (new_owner) REFERENCES contact(contact_id)
ON DELETE CASCADE;

ALTER TABLE mod_ccvm_custom_view_ownership
ADD CONSTRAINT fk_oldowner FOREIGN KEY (old_owner) REFERENCES contact(contact_id)
ON DELETE SET NULL;