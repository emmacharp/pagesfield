<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/class.pagemanager.php');

	Class fieldPages extends Field{

		function __construct(){
			parent::__construct();
			$this->_name = 'Page Select Box';
			$this->_required = true;

			// Set default
			$this->set('required', 'yes');
			$this->set('show_column', 'no');
		}

		function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}

		function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggle();
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canFilter(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		function isSortable(){
			return true;
		}

		function appendFormattedElement(&$wrapper, $data, $encode=false){

			if(!is_array($data) || empty($data)) return;

			$list = new XMLElement($this->get('element_name'));

			if(!is_array($data['handle'])) $data['handle'] = array($data['handle']);
			if(!is_array($data['page_id'])) $data['page_id'] = array($data['page_id']);
			if(!is_array($data['title'])) $data['title'] = array($data['title']);

			for($ii = 0; $ii < count($data['handle']); $ii++){
				$list->appendChild(new XMLElement('page', General::sanitize($data['title'][$ii]), array('handle' => $data['handle'][$ii], 'id' => $data['page_id'][$ii])));
			}

			$wrapper->appendChild($list);
		}

		function getParameterPoolValue(array $data, $entry_id=NULL){
			return $data['page_id'];
		}

		function getToggleStates($include_parent_titles=true){

			$negate = self::isFilterNegation($this->get('page_types'));
			$types = ($negate ? preg_replace('/^not:\s*/i', null, $this->get('page_types')) : $this->get('page_types'));
			$andOperation = self::isAndOperation($types);

			$types = explode(($andOperation ? '+' : ','), $types);
			$types = array_map('trim', $types);
			$types = array_filter($types);

			$pages = self::fetchPageByTypes($types, $andOperation, $negate);
			// Make sure that $pages is an array of pages.
			// PageManager::fetchPageByID() returns an array of page properties for a single page.
			if (!is_array(current($pages))) {
				$pages = array($pages);
			}

			$result = array();
			foreach($pages as $p){
				$title = ($include_parent_titles ? PageManager::resolvePageTitle($p['id']) : $p['title']);
				$result[$p['id']] = $title;
			}

			return $result;
		}

		function toggleFieldData($data, $newState){

			$page = PageManager::fetchPageByID($newState, array('handle', 'title', 'id'));

			$data['handle'] = $page['handle'];
			$data['title'] = $page['title'];
			$data['page_id'] = $page['id'];

			return $data;
		}

		function __sortTitlesAscending($t1, $t2){
			return strcmp(strtolower($t1[2]), strtolower($t2[2]));
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			$states = $this->getToggleStates();

			if(!is_array($data['handle'])) $data['handle'] = array($data['handle']);
			if(!is_array($data['page_id'])) $data['page_id'] = array($data['page_id']);
			if(!is_array($data['title'])) $data['title'] = array($data['title']);

			$options = array();

			if($this->get('required') != 'yes' && $this->get('allow_multiple_selection') != 'yes') $options[] = array(NULL, false, NULL);

			foreach($states as $id => $title){
				$options[] = array($id, in_array($id, $data['page_id']), General::sanitize($title));
			}

			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array('multiple' => 'multiple') : NULL)));

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);

			$data = preg_split('/,\s*/i', $data);
			$data = array_map('trim', $data);

			$existing_options = $this->getToggleStates(false);

			if(is_array($existing_options) && !empty($existing_options)){
				$optionlist = new XMLElement('ul');
				$optionlist->setAttribute('class', 'tags');

				foreach($existing_options as $option) $optionlist->appendChild(new XMLElement('li', $option));

				$wrapper->appendChild($optionlist);
			}

		}

		function prepareTableValue($data, XMLElement $link=NULL){
			// stop when no page is set
			if(!isset($data['page_id'])) return;

			$pages = PageManager::fetchPageByID($data['page_id'], array('id'));
			// Make sure that $pages is an array of pages.
			// PageManager::fetchPageByID() returns an array of page properties for a single page.
			if (!is_array(current($pages))) {
				$pages = array($pages);
			}

			$result = array();
			foreach($pages as $p){
				$title = PageManager::resolvePageTitle($p['id']);
				$result[$p['id']] = $title;
			}

			$value = implode(', ', $result);

			return parent::prepareTableValue(array('value' => General::sanitize($value)), $link);
		}

		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::__OK__;

			if(empty($data)) return NULL;

			if(!is_array($data)) $data = array($data);

			$result = array('title' => array(), 'handle' => array(), 'page_id' => array());
			foreach($data as $page_id){

				$page = PageManager::fetchPageByID($page_id, array('handle', 'title'));

				$result['handle'][] = $page['handle'];
				$result['title'][] = $page['title'];
				$result['page_id'][] = $page_id;
			}

			return $result;
		}

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if(self::isFilterRegex($data[0])):

				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (`t$field_id`.title REGEXP '$pattern' OR `t$field_id`.handle REGEXP '$pattern') ";


			elseif($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (`t$field_id$key`.page_id = '$bit' OR `t$field_id$key`.handle = '$bit' OR `t$field_id$key`.title = '$bit') ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (`t$field_id`.page_id IN ('".@implode("', '", $data)."') OR `t$field_id`.handle IN ('".@implode("', '", $data)."') OR `t$field_id`.title IN ('".@implode("', '", $data)."')) ";

			endif;

			return true;

		}

		function commit(){

			if(!parent::commit()) return false;

			$id = $this->get('id');
			$page_types = $this->get('page_types'); // TODO safe

			if($id === false) return false;

			$fields = array();

			$fields['field_id'] = $id;
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			$fields['page_types'] = $page_types;

			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");

			if(!Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			return true;

		}

		function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
		}

		function displaySettingsPanel(&$wrapper, $errors=NULL){

			parent::displaySettingsPanel($wrapper, $errors);

			## Page types filter
			$label = new XMLElement('label', __('Filter pages by type'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][page_types]', $this->get('page_types')));
			$wrapper->appendChild($label);
			$tags = new XMLElement('ul');
			$tags->setAttribute('class', 'tags');
			$types = PageManager::fetchPageTypes();
			if(is_array($types) && !empty($types)) {
				foreach($types as $type) $tags->appendChild(new XMLElement('li', $type));
			}
			$wrapper->appendChild($tags);

			// Allow selection of multiple items
			$label = Widget::Label();
			$label->setAttribute('class', 'column');
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));

			$div = new XMLElement('div', NULL, array('class' => 'two columns'));
			$div->appendChild($label);

			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));

				$handle = $data['handle'];

				if(!isset($groups[$this->get('element_name')][$handle])){
					$groups[$this->get('element_name')][$handle] = array('attr' => array('handle' => $handle, 'name' => General::sanitize($data['title'])),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->get('element_name')][$handle]['records'][] = $r;

			}

			return $groups;
		}

		function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  `title` varchar(255) default NULL,
				  `handle` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `handle` (`handle`),
				  KEY `page_id` (`page_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}

		public function getExampleFormMarkup(){
			$states = $this->getToggleStates();

			$options = array();

			foreach($states as $handle => $v){
				$options[] = array($handle, NULL, $v);
			}

			$fieldname = 'fields['.$this->get('element_name').']';
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Select($fieldname, $options, ($this->get('allow_multiple_selection') == 'yes' ? array('multiple' => 'multiple') : NULL)));

			return $label;
		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC', $useIDFieldForSorting=false){
			$sort_field = (!$useIDFieldForSorting ? 'ed' : 't' . $this->get('id'));

			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `$sort_field` ON (`e`.`id` = `$sort_field`.`entry_id`) ";
			$sort .= 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "`$sort_field`.`handle` $order");
		}

		/**
		 * Returns Pages that match the given `$types`. If no `$types` is provided
		 * the function returns the result of `PageManager::fetch`.
		 *
		 * @param array $types
		 *  An array of some of the available Page Types.
		 * @param boolean $negate (optional)
		 *  If true, the logic gets inversed to return Pages that don't match the given `$types`.
		 * @return array|null
		 *  An associative array of Page information with the key being the column
		 *  name from `tbl_pages` and the value being the data. If multiple Pages
		 *  are found, an array of Pages will be returned. If no Pages are found
		 *  null is returned.
		 */
		public static function fetchPageByTypes(array $types = array(), $andOperation = false, $negate = false) {
			// Don't filter when not types are set
			if(empty($types)) return PageManager::fetch(false);

			$types = array_map(array('MySQL', 'cleanValue'), $types);

			// Build SQL parts depending on query parameters. There are four possibilities.
			// 1. Without negation and with OR filter
			if (!$andOperation && !$negate) {
				$join = "LEFT JOIN `tbl_pages_types` AS `pt` ON (p.id = pt.page_id)";
				$where = sprintf("
						AND `pt`.type IN ('%s')
					",
					implode("', '", $types)
				);
			}
			// 2. Without negation and with AND filter
			elseif ($andOperation && !$negate) {
				$join = "";
				$where = "";
				foreach($types as $index => $type) {
					$join .= " LEFT JOIN `tbl_pages_types` AS `pt_{$index}` ON (p.id = pt_{$index}.page_id)";
					$where .= " AND pt_{$index}.type = '" . $type . "'";
				}
			}
			// 3. With negation and with OR filter
			elseif (!$andOperation && $negate) {
				$join = sprintf("
						LEFT JOIN `tbl_pages_types` AS `pt` ON (p.id = pt.page_id AND pt.type IN ('%s'))
					",
					implode("', '", $types)
				);
				$where = "AND `pt`.type IS NULL";
			}
			// 4. With negation and with AND filter
			elseif ($andOperation && $negate) {
				$join = "";
				$where = "AND (";
				foreach($types as $index => $type) {
					$join .= sprintf("
							LEFT JOIN `tbl_pages_types` AS `pt_%s` ON (p.id = pt_%s.page_id AND pt_%s.type IN ('%s'))
						",
						$index, $index, $index,
						$type
					);
					$where .= ($index === 0 ? "" : " OR ") . "pt_{$index}.type IS NULL";
				}
				$where .= ")";
			}

			$pages = Symphony::Database()->fetch(sprintf("
					SELECT
						`p`.*
					FROM
						`tbl_pages` AS `p`
					%s
					WHERE 1
						%s
				",
				$join,
				$where
			));

			return count($pages) == 1 ? array_pop($pages) : $pages;
		}

		/**
		 * Test whether the input string has a negation filter modifier, by searching
		 * for the prefix of `not:` in the given `$string`.
		 *
		 * @param string $string
		 *  The string to test.
		 * @return boolean
		 *  True if the string is prefixed with `not:`, false otherwise.
		 */
		public static function isFilterNegation($string){
			return (preg_match('/^not:/i', $string)) ? true : false;
		}

		public static function isAndOperation($string){
			return (strpos($string, '+') === false) ? false : true;
		}

	}

