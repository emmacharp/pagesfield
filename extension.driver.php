<?php

	Class extension_pagesfield extends Extension{

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_pages`");
		}

		public function install(){
			return Symphony::Database()->query("CREATE TABLE `tbl_fields_pages` (
			  `id` int(11) unsigned NOT NULL auto_increment,
			  `field_id` int(11) unsigned NOT NULL,
			  `allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
			  `page_types` varchar(255) default NULL,
			  PRIMARY KEY  (`id`),
			  UNIQUE KEY `field_id` (`field_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
		}

		public function update($previousVersion) {
			if(version_compare($previousVersion, '1.3', '<')){
				$updated = Symphony::Database()->query(
					"ALTER TABLE `tbl_fields_pages`
						ADD `page_types` varchar(255) default NULL"
				);
				if(!$updated) return false;
			}
			return true;
		}

	}

