<?php

namespace gp\admin\Menu;

defined('is_running') or die('Not an entry point...');

class Tools{


	/**
	 * Get a list of pages that are not in the main menu
	 * @return array
	 */
	public static function GetAvailable(){
		global $gp_index, $gp_menu;

		$avail = array();
		foreach( $gp_index as $title => $index ){
			if( !isset($gp_menu[$index]) ){
				$avail[$index] = $title;
			}
		}
		return $avail;
	}

	/**
	 * Return array with info about inherited layouts and number of children for all pages in $gp_menu
	 *
	 */
	public static function Inheritance_Info(){
		global $gp_menu, $gp_titles;

		$current_par_info = array();
		$prev_level = 0;
		$inherit_info = array();


		foreach($gp_menu as $id => $titleInfo){

			$level = $titleInfo['level'];
			$inherit_info[$id] = array();

			//no longer parents
			if( $prev_level >= $level ){

				$temp_level = $prev_level;
				while( $temp_level >= $level ){
					unset($current_par_info[$temp_level]);
					$temp_level--;
				}
			}

			foreach($current_par_info as $parent_level => $parent_info){
				$parent_id = $parent_info['id'];

				if( $parent_level < $level ){
					if( isset($inherit_info[$parent_id]['children']) ){
						$inherit_info[$parent_id]['children']++;
					}else{
						$inherit_info[$parent_id]['children'] = 1;
					}
				}

				if( isset($parent_info['gpLayout']) ){
					$inherit_info[$id]['parent_layout'] = $parent_info['gpLayout'];
				}
			}

			$array = array();
			$array['id'] = $id;
			if( isset($gp_titles[$id]['gpLayout']) ){
				$array['gpLayout'] = $gp_titles[$id]['gpLayout'];
			}

			$current_par_info[$level] = $array;

			$prev_level = $level;
		}

		return $inherit_info;
	}


	/**
	 * Get the current layout setting for the page give by it's $index
	 *
	 */
	public static function CurrentLayout($index){
		global $config, $gp_titles,$gpLayouts;
		static $Inherit_Info;

		if( is_null($Inherit_Info) ){
			$Inherit_Info = self::Inheritance_Info();
		}

		if( isset($gp_titles[$index]['gpLayout']) ){
			$layout = $gp_titles[$index]['gpLayout'];
			if( isset($gpLayouts[$layout]) ){
				return $layout;
			}
		}

		if( isset($Inherit_Info[$index]['parent_layout']) ){
			$layout = $Inherit_Info[$index]['parent_layout'];
			if( isset($gpLayouts[$layout]) ){
				return $layout;
			}
		}

		return $config['gpLayout'];
	}


	/**
	 * Get the css class representing the current page's visibility
	 *
	 */
	public static function VisibilityClass($class, $index){
		global $gp_menu, $gp_titles;

		if( isset($gp_titles[$index]['vis']) ){
			$class .= ' private-list';
			return $class;
		}

		$parents = \gp\tool::Parents($index,$gp_menu);
		foreach($parents as $parent_index){
			if( isset($gp_titles[$parent_index]['vis']) ){
				$class .= ' private-inherited';
				break;
			}
		}

		return $class;
	}


	/**
	 * Output Sortable Menu Link and data about the title or external link
	 *
	 */
	public static function MenuLink($data, $class = ''){

		$class	= 'gp_label sort '.$class;
		$json	= \gp\tool::JsonEncode($data);

		echo '<a class="'.$class.'" data-cmd="menu_info" data-arg="'.str_replace('&','&amp;',$data['key']).'" data-json=\''.htmlspecialchars($json,ENT_QUOTES & ~ENT_COMPAT).'\'>';
	}


	/**
	 * Make sure the homepage has a value
	 *
	 */
	public static function ResetHomepage(){
		global $config, $gp_menu, $gp_titles;

		if( !isset($gp_titles[$config['homepath_key']]) ){
			$config['homepath_key'] = key($gp_menu);
			$config['homepath']		= \gp\tool::IndexToTitle($config['homepath_key']);
		}
	}


	/**
	 * Create a scrollable title list
	 *
	 * @param array $list
	 * @param string $name
	 * @pearm string $type
	 * @param bool $index_as_value
	 */
	public static function ScrollList($list, $name = 'from_title', $type = 'radio', $index_as_value = false ){
		global $langmessage;

		$list_out = array();
		foreach($list as $title => $index){
			ob_start();
			echo '<label>';
			if( $index_as_value ){
				echo '<input type="'.$type.'" name="'.$name.'" value="'.htmlspecialchars($index).'" />';
			}else{
				echo '<input type="'.$type.'" name="'.$name.'" value="'.htmlspecialchars($title).'" />';
			}
			echo '<span>';
			$label = \gp\tool::GetLabel($title);
			echo \gp\tool::LabelSpecialChars($label);
			echo '<span class="slug">';
			echo '/'.$title;
			echo '</span>';
			echo '</span>';
			echo '</label>';

			$list_out[$title] = ob_get_clean();
		}

		uksort($list_out,'strnatcasecmp');
		echo '<div class="gp_scrolllist"><div>';
		echo '<input type="text" name="search" value="" class="gpsearch" placeholder="'.$langmessage['Search'].'" autocomplete="off" />';
		echo implode('',$list_out);
		echo '</div></div>';
	}


	/**
	 * Create a new page from a user post
	 *
	 */
	public static function CreateNew(){
		global $gp_index, $gp_titles, $langmessage, $gpAdmin;


		//check title
		$title		= $_POST['title'];
		$title		= \gp\admin\Tools::CheckPostedNewPage($title,$message);
		if( $title === false ){
			msg($message);
			return false;
		}


		//multiple section types
		$type		= $_POST['content_type'];
		if( strpos($type,'{') === 0 ){
			$types = json_decode($type,true);
			if( $types ){

				$types								+= array('wrapper_class'=>'gpRow');
				$content							= array();

				//wrapper section
				$section							= \gp\tool\Editing::DefaultContent('wrapper_section');
				$section['contains_sections']		= count($types['types']);
				$section['attributes']['class']		= $types['wrapper_class'];
				$content[]							= $section;


				//nested sections
				foreach($types['types'] as $type){

					if( strpos($type,'.') ){
						list($type,$class)			= explode('.',$type,2);
					}else{
						$class						= '';
					}

					$section						= \gp\tool\Editing::DefaultContent($type);
					$section['attributes']['class']	.= ' '.$class;
					$content[]						= $section;
				}
			}

		//single section type
		}else{
			$content	= \gp\tool\Editing::DefaultContent($type, $_POST['title']);
			if( $content['content'] === false ){
				return false;
			}
		}


		//add to $gp_index first!
		$index							= \gp\tool::NewFileIndex();
		$gp_index[$title]				= $index;

		if( !\gp\tool\Files::NewTitle($title,$content,$type) ){
			msg($langmessage['OOPS'].' (cn1)');
			unset($gp_index[$title]);
			return false;
		}

		//add to gp_titles
		$new_titles						= array();
		$new_titles[$index]['label']	= \gp\admin\Tools::PostedLabel($_POST['title']);
		$new_titles[$index]['type']		= $type;
		$gp_titles						+= $new_titles;


		//add to users editing
		if( $gpAdmin['editing'] != 'all' ){
			$gpAdmin['editing'] = rtrim($gpAdmin['editing'],',').','.$index.',';


			$users		= \gp\tool\Files::Get('_site/users');
			$users[$gpAdmin['username']]['editing'] = $gpAdmin['editing'];
			\gp\tool\Files::SaveData('_site/users','users',$users);

		}


		return $index;
	}


	/**
	 * Get the level of the first page in a menu
	 *
	 */
	public static function GetRootLevel($menu){
		reset($menu);
		$info = current($menu);
		if( isset($info['level']) ){
			return $info['level'];
		}
		return 0;
	}


	/**
	 * Is the menu an alternate menu
	 *
	 */
	public static function IsAltMenu($id){
		global $config;
		return isset($config['menus'][$id]);
	}


	/**
	 * Generate menu data with a single file
	 *
	 */
	public static function AltMenu_New(){
		global $gp_menu, $gp_titles;

		if( count($gp_menu) ){
			reset($gp_menu);
			$first_index = key($gp_menu);
		}elseif( count($gp_titles ) ){
			reset($gp_titles);
			$first_index = key($gp_titles);
		}

		$new_menu[$first_index] = array('level'=>0);
		return $new_menu;
	}

}
