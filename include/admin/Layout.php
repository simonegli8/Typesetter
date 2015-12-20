<?php

namespace gp\admin;

defined('is_running') or die('Not an entry point...');

/*
what can be moved?
	* .editable_area

How do we position elements?
	* above, below in relation to another editable_area

How do we do locate them programatically
	* We need to know the calling functions that output the areas
		then be able to organize a list of output functions within each of the calling functions
		!each area is represented by a list, either a default value if an override hasn't been defined, or the custom list created by the user

How To Identify the Output Functions for the Output Lists?
	* Gadgets have:
		$info['script']
		$info['data']
		$info['class']


$gpOutConf = array() of output functions/classes.. to use with the theme content
	==potential values==
	$gpOutConf[-ident-]['script'] = -path relative to datadir or rootdir?
	$gpOutConf[-ident-]['data'] = -path relative to datadir-
	$gpOutConf[-ident-]['class'] = -path relative to datadir or rootdir?
	$gpOutConf[-ident-]['method'] = string or array: string=name of function, array(class,method)


	$gpLayout['Loyout_Name']['handlers'][-ident-] = array(0=>-ident-,1=>-ident-)
	$gpLayout['Loyout_Name']['color'] = '#123456'
	$gpLayout['Loyout_Name']['theme'] = 'One_Point_5/Blue'

*/

//includeFile('admin/admin_menu_tools.php');
includeFile('admin/admin_addon_install.php');


class Layout extends \admin_addon_install{

	protected $layout_request		= false;
	protected $curr_layout			= false;
	protected $LayoutArray;
	protected $scriptUrl			= 'Admin_Theme_Content';
	protected $versions				= array();


	//remote install variables
	public $config_index			= 'themes';
	public $code_folder_name		= '_themes';
	public $path_remote				= 'Admin_Theme_Content/Remote';
	public $can_install_links		= false;


	public function __construct(){
		global $page;

		parent::__construct();

		$page->head_js[] = '/include/js/theme_content.js';
		$page->head_js[] = '/include/js/dragdrop.js';
		$page->css_admin[] = '/include/css/theme_content.less';
		\common::LoadComponents('resizable');


		$this->GetPossible();

		//layout requests
		if( strpos($page->requested,'/') ){
			$parts = explode('/',$page->requested);
			$layout_part = $parts[1];

			if( gp_remote_themes && strtolower($layout_part) == 'remote' ){
				$this->RemoteBrowse();
				return;
			}

			if( strtolower($layout_part) == 'available' ){
				$this->ShowAvailable();
				return;
			}
		}
	}


	public function RunScript(){
		global $config, $gpLayouts, $langmessage;

		$cmd = \common::GetCommand();

		//set current layout
		$this->curr_layout = $config['gpLayout'];
		if( isset($_REQUEST['layout']) ){
			$this->curr_layout = $_REQUEST['layout'];
		}
		if( !array_key_exists($this->curr_layout,$gpLayouts) ){
			message($langmessage['OOPS'].' (Invalid Layout)');
			$cmd = '';
		}

		$this->SetLayoutArray();

		switch($cmd){


			//remote themes
			case 'remote_install':
				$this->RemoteInstall();
			return;
			case 'remote_install_confirmed':
				$installer = $this->RemoteInstallConfirmed('theme');
				$this->GetPossible();
				$this->UpdateLayouts( $installer );
			break;


			case 'deletetheme':
				$this->DeleteTheme();
				$this->GetPossible();
			break;


			//adminlayout
			case 'adminlayout':
				$this->AdminLayout();
			return;




			//theme ratings
			case 'Update Review';
			case 'Send Review':
			case 'rate':
				$this->admin_addon_rating('theme','Admin_Theme_Content');
				if( $this->ShowRatingText ){
					return;
				}
			break;


			//new layouts
			case 'preview':
			case 'preview_iframe':
			case 'newlayout':
			case 'addlayout':
				if( $this->NewLayout($cmd) ){
					return;
				}
			break;
			case 'updatetheme':
				$this->UpdateTheme($_REQUEST['source']);
			break;




			//copy
			case 'copy':
				$this->CopyLayoutPrompt();
			return;
			case 'copylayout';
				$this->CopyLayout();
			break;

			//layout options
			case 'deletelayout':
				$this->DeleteLayoutConfirmed();
			break;

			//text
			case 'edittext':
				$this->EditText();
			return;
			case 'savetext':
				$this->SaveText();
			break;


			case 'saveaddontext':
				$this->SaveAddonText();
			break;
			case 'addontext':
				$this->AddonText();
			return;

		}

		if( $this->LayoutCommands($cmd) ){
			return;
		}


		$this->ShowLayouts();
	}


	/**
	 * Perform various layout commands
	 *
	 */
	public function LayoutCommands($cmd){

		switch($cmd){


			// CSS editing
			case 'restore':
				$this->Restore();
			break;

			case 'css_preferences':
				$this->CSSPreferences();
			break;

			case 'makedefault':
				$this->MakeDefault();
			break;

			case 'layout_label':
				$this->LayoutLabel();
			return true;

			case 'rmgadget':
				$this->RmGadget();
			break;
			case 'gadgets':
				$this->ShowGadgets();
			return true;

			case 'titles':
				$this->ShowTitles();
			return true;
		}

		return false;
	}


	public function AdminLayout(){
		global $langmessage;

		echo '<div class="inline_box">';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';

		$admin_layout = $langmessage['default'];
		echo '<h2>'.'Admin Layout'.'</h2>';

		echo '<select name="">';
			echo '<option value="">'.$langmessage['default'].'</option>';
		echo '</select>';
		echo ' <input type="submit" name="" value="'.$langmessage['continue'].'" />';

		echo '</form>';
		echo '</div>';
	}



	/**
	 * Display a list of all the titles using the current layout
	 *
	 */
	public function ShowTitles(){
		global $langmessage;

		//affected titles
		$titles_count = $this->TitlesCount($this->curr_layout);

		echo '<h2>'.$langmessage['titles_using_layout'];
		echo ': '.$titles_count;
		echo '</h2>';

		if( $titles_count > 0 ){
			echo '<ul class="titles_using">';

			foreach( $this->LayoutArray as $index => $layout_comparison ){
				if( $this->curr_layout == $layout_comparison ){

					$title = \common::IndexToTitle($index);
					if( empty($title) ){
						continue; //may be external link
					}

					echo "\n<li>";
					$label = \common::GetLabel($title);
					$label = \common::LabelSpecialChars($label);
					echo \common::Link($title,$label);
					echo '</li>';
				}
			}

			echo '</ul>';
			echo '<div class="clear"></div>';
		}

	}


	/**
	 * Display gadgets and their status for the current layout
	 *
	 */
	public function ShowGadgets(){
		global $langmessage, $config;

		$gadget_info = \gpOutput::WhichGadgets($this->curr_layout);

		echo '<h2>'.$langmessage['gadgets'].'</h2>';
		echo '<table class="bordered full_width">';
		echo '<tr><th colspan="2">&nbsp;</th></tr>';

		if( !isset($config['gadgets']) || count($config['gadgets']) == 0 ){
			echo '<tr><td colspan="2">';
			echo $langmessage['Empty'];
			echo '</td></tr>';
		}else{
			foreach($config['gadgets'] as $gadget => $temp){
				echo '<tr><td>';
				echo str_replace('_',' ',$gadget);
				echo '</td><td>';
				if( isset($gadget_info[$gadget]) ){
					echo $this->LayoutLink( $this->curr_layout, $langmessage['remove'], 'cmd=rmgadget&gadget='.urlencode($gadget), array('data-cmd'=>'cnreq') );
				}else{
					echo $langmessage['disabled'];
				}
				echo '</td></tr>';
			}
		}
		echo '</table>';
	}



	/**
	 * Create a drop-down menu for the layout options
	 *
	 */
	public function LayoutOptions($layout,$info){
		global $langmessage, $config;


		//get handler count
		$handlers_count = 0;
		if( isset($info['handlers']) && is_array($info['handlers']) ){
			foreach($info['handlers'] as $val){
				$int = count($val);
				if( $int === 0){
					$handlers_count++;
				}
				$handlers_count += $int;
			}
		}

		//theme name
		echo '<li>';
		echo '<span>'.$langmessage['theme'].': '.$this->ThemeLabel($info['theme_name']).'</span>';
		echo '</li>';




		//default
		echo '<li>';
		if( $config['gpLayout'] == $layout ){
			echo '<span><b>'.$langmessage['default'].'</b></span>';
		}else{
			echo \common::Link('Admin_Theme_Content',$langmessage['make_default'],'cmd=makedefault&layout='.rawurlencode($layout),array('data-cmd'=>'creq','title'=>$langmessage['make_default']));
		}
		echo '</li>';


		//gadgets
		echo '<li>';
		echo $this->LayoutLink( $layout, $langmessage['gadgets'], 'cmd=gadgets', 'data-cmd="gpabox"' );
		echo '</li>';


		//titles using layout
		echo '<li>';
		$titles_count = $this->TitlesCount($layout);
		$label = sprintf($langmessage['%s Pages'],$titles_count);
		if( $titles_count ){
			//$label = $langmessage['titles_using_layout'].': '.$label;
			echo $this->LayoutLink( $layout, $label, 'cmd=titles', 'data-cmd="gpabox"' );
		}else{
			echo '<span>'.$label.'</span>';
		}
		echo '</li>';


		//content arrangement
		echo '<li>';
		if( $handlers_count ){
			echo $this->LayoutLink( $layout, $langmessage['restore_defaults'], 'cmd=restore', array('data-cmd'=>'creq') );
			//echo $this->LayoutLink( $layout, $langmessage['content_arrangement'].': '.$langmessage['restore_defaults'], 'cmd=restore', array('data-cmd'=>'creq') );
		}else{
			echo '<span>'.$langmessage['content_arrangement'].': '.$langmessage['default'].'</span>';
		}
		echo '</li>';


		//copy
		echo '<li>';
		$query = 'cmd=copy&layout='.$layout;
		echo \common::Link('Admin_Theme_Content',$langmessage['Copy'],$query,'data-cmd="gpabox"');
		echo '</li>';

		//delete
		if( $config['gpLayout'] != $layout ){
			echo '<li>';
			$attr = array( 'data-cmd'=>'creq','class'=>'gpconfirm','title'=>sprintf($langmessage['generic_delete_confirm'],$info['label']) );
			echo \common::Link('Admin_Theme_Content',$langmessage['delete'],'cmd=deletelayout&layout='.$layout,$attr);
			echo '</li>';
		}
	}


	/**
	 * Display links for selecting style variations of a theme
	 *
	 */
	public function StyleOptions($layout, $layout_info){
		global $langmessage;

		$theme_colors = $this->GetThemeColors($layout_info['dir']);
		if( !count($theme_colors) ){
			return;
		}

		echo $langmessage['style'];
		echo '<select name="color">';
		foreach($theme_colors as $color){
			$color_label = str_replace('_',' ',$color);
			if( $color == $layout_info['theme_color'] ){
				echo '<option value="'.htmlspecialchars($color).'" selected="selected">';
			}else{
				echo '<option value="'.htmlspecialchars($color).'">';
			}
			echo htmlspecialchars($color_label);
			echo '</option>';
		}
		echo '</select>';
	}


	/**
	 * Display all the layouts available in a <select>
	 *
	 */
	public function LayoutSelect($curr_layout=false,$curr_info=false){
		global $gpLayouts, $langmessage, $config;

		$display = $langmessage['available_layouts'];
		if( $curr_layout ){
			$display = '<span class="layout_color_id" style="background-color:'.$curr_info['color'].';"></span> &nbsp; '
					. $curr_info['label'];
		}

		echo '<div><div class="dd_menu">';
		echo '<a data-cmd="dd_menu">'.$display.'</a>';

		echo '<div class="dd_list"><ul>';
		foreach($gpLayouts as $layout => $info){
			$attr = '';
			if( $layout == $curr_layout){
				$attr = ' class="selected"';
			}
			echo '<li'.$attr.'>';

			$display = '<span class="layout_color_id" style="background-color:'.$info['color'].';"></span> &nbsp; '. $info['label'];
			if( $config['gpLayout'] == $layout ){
				$display .= ' <span class="layout_default"> ('.$langmessage['default'].')</span>';
			}
			echo \common::Link('Admin_Theme_Content/Edit/'.rawurlencode($layout),$display);
			echo '</li>';
		}
		echo '</ul></div>';
		echo '</div></div>';
	}



	/**
	 * Get the custom css for a layout if it exists
	 *
	 */
	public function LayoutCSS($layout){
		global $dataDir, $gpLayouts;

		$layout_info = $gpLayouts[$layout];
		if( !isset($layout_info['css']) || !$layout_info['css'] ){
			return '';
		}

		$path = $dataDir.'/data/_layouts/'.$layout.'/custom.css';
		if( file_exists($path) ){
			return file_get_contents($path);
		}

		return '';
	}


	/**
	 * Remove the custom css file for a layout
	 *
	 */
	public function RemoveCSS($layout){
		global $dataDir;
		$dir = $dataDir.'/data/_layouts/'.$layout;
		$path = $dir.'/custom.css';
		if( file_exists($path) ){
			unlink($path);
		}

		$path = $dir.'/index.html';
		if( file_exists($path) ){
			unlink($path);
		}

		if( file_exists($dir) ){
			\gpFiles::RmDir($dir);
		}
	}

	/**
	 * Save changes to the css settings for a layout
	 *
	 */
	public function CSSPreferences(){
		global $langmessage, $gpLayouts, $page;

		$old_info = $new_info = $gpLayouts[$this->curr_layout];

		if( isset($_POST['menu_css_ordered']) ){
			if( $_POST['menu_css_ordered'] === 'off' ){
				$new_info['menu_css_ordered'] = false;
			}else{
				unset($new_info['menu_css_ordered']);
			}
		}

		if( isset($_POST['menu_css_indexed']) ){
			if( $_POST['menu_css_indexed'] === 'off' ){
				$new_info['menu_css_indexed'] = false;
			}else{
				unset($new_info['menu_css_indexed']);
			}
		}

		$gpLayouts[$this->curr_layout] = $new_info;

		if( !\admin_tools::SavePagesPHP() ){
			$gpLayouts[$this->curr_layout] = $old_info;
			message($langmessage['OOPS'].' (Not Saved)');
			return;
		}

		if( $this->layout_request || $page->gpLayout == $this->curr_layout ){
			$page->SetTheme($this->curr_layout);
		}


		$content = $this->CSSPreferenceForm($this->curr_layout,$new_info);
		$page->ajaxReplace = array();
		$page->ajaxReplace[] = array('replace','#layout_css_ul_'.$this->curr_layout,$content);
	}


	/**
	 * Remove a gadget from a layout
	 * @return null
	 *
	 */
	public function RmGadget(){
		global $page,$langmessage;

		$gadget =& $_REQUEST['gadget'];

		$handlers = $this->GetAllHandlers($this->curr_layout);
		$this->PrepContainerHandlers($handlers,'GetAllGadgets','GetAllGadgets'); //make sure GetAllGadgets is set

		$changed = false;
		foreach($handlers as $container => $container_info){
			foreach($container_info as $key => $gpOutCmd){
				if( $gpOutCmd == $gadget ){
					$changed = true;
					unset($handlers[$container][$key]);
				}
			}
		}

		if( !$changed ){
			message($langmessage['OOPS'].' (Not Changed)');
			return;
		}

		$this->SaveHandlersNew($handlers,$this->curr_layout);
	}


	public static function GetRandColor(){
		$colors = self::GetColors();
		$color_key = array_rand($colors);
		return $colors[$color_key];
	}

	public static function GetColors(){

		return array(
			'#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#0000ff', '#9900ff', '#ff00ff',
			'#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#cfe2f3', '#d9d2e9', '#ead1dc',
			'#ea9999', '#f9cb9c', '#ffe599', '#b6d7a8', '#a2c4c9', '#9fc5e8', '#b4a7d6', '#d5a6bd',
			'#e06666', '#f6b26b', '#ffd966', '#93c47d', '#76a5af', '#6fa8dc', '#8e7cc3', '#c27ba0',
			'#cc0000', '#e69138', '#f1c232', '#6aa84f', '#45818e', '#3d85c6', '#674ea7', '#a64d79',
			'#990000', '#b45f06', '#bf9000', '#38761d', '#134f5c', '#0b5394', '#351c75', '#741b47',
		);
	}



	/**
	 * Manage adding new layouts
	 *
	 */
	public function NewLayout($cmd){
		global $langmessage;

		//check the requested theme
		$theme =& $_REQUEST['theme'];
		$theme_info = $this->ThemeInfo($theme);
		if( $theme_info === false ){
			message($langmessage['OOPS'].' (Invalid Theme)');
			return false;
		}


		// three steps of installation
		switch($cmd){

			case 'preview':
				if( $this->PreviewTheme($theme, $theme_info) ){
					return true;
				}
			break;

			case 'preview_iframe':
				$this->PreviewThemeIframe($theme,$theme_info);
			return true;

			case 'newlayout':
				$this->NewLayoutPrompt($theme, $theme_info);
			return true;

			case 'addlayout':
				$this->AddLayout($theme_info);
			break;
		}
		return false;
	}



	/**
	 * Preview a theme and give users the option of creating a new layout
	 *
	 */
	public function PreviewTheme($theme, $theme_info){
		global $langmessage,$config,$page;

		$theme_id = dirname($theme);
		$color = $theme_info['color'];


		$_REQUEST += array('gpreq' => 'body'); //force showing only the body as a complete html document
		$page->get_theme_css = false;
		$page->show_admin_content = false;
		$page->get_theme_css = false;

		$page->head_js[] = '/include/js/auto_width.js';


		ob_start();

		//new
		echo '<div id="theme_editor">';
		echo '<div class="gp_scroll_area">';


		echo '<div>';
		echo \common::Link('Admin_Theme_Content/Available','&#171; '.$langmessage['available_themes']);
		echo \common::Link('Admin_Theme_Content',$langmessage['use_this_theme'],'cmd=newlayout&theme='.rawurlencode($theme),'data-cmd="gpabox" class="add_layout"');
		echo '</div>';


		echo '<div class="separator"></div>';


		$this->searchUrl = 'Admin_Theme_Content/Available';
		$this->AvailableList( false );

		//search options
		$this->searchQuery .= '&cmd=preview&theme='.rawurlencode($theme);
		$this->SearchOptions( false );

		echo '</div>';


		//show site in iframe
		echo '<div id="gp_iframe_wrap">';
		$url = \common::GetUrl('Admin_Theme_Content','cmd=preview_iframe&theme='.rawurlencode($theme));
		echo '<iframe src="'.$url.'" id="gp_layout_iframe" name="gp_layout_iframe"></iframe>';
		echo '</div>';

		echo '</div>';
		$page->admin_html = ob_get_clean();
		return true;
	}

	public function PreviewThemeIframe($theme, $theme_info){
		global $langmessage,$config,$page;

		\admin_tools::$show_toolbar = false;

		$theme_id = dirname($theme);
		$template = $theme_info['folder'];
		$color = $theme_info['color'];
		$display = htmlspecialchars($theme_info['name'].' / '.$theme_info['color']);
		$display = str_replace('_',' ',$display);
		$this->LoremIpsum();
		$page->gpLayout = false;
		$page->theme_name = $template;
		$page->theme_color = $color;
		$page->theme_dir = $theme_info['full_dir'];
		$page->theme_rel = $theme_info['rel'].'/'.$color;

		if( isset($theme_info['id']) ){
			$page->theme_addon_id = $theme_info['id'];
		}

		$page->theme_path = \common::GetDir($theme_info['rel'].'/'.$color);

		$page->show_admin_content = false;
	}



	/**
	 * Give users a few options before creating the new layout
	 *
	 */
	public function NewLayoutPrompt($theme, $theme_info ){
		global $langmessage;


		$label = substr($theme_info['name'].'/'.$theme_info['color'],0,25);

		echo '<h2>'.$langmessage['new_layout'].'</h2>';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		echo '<table class="bordered full_width">';

		echo '<tr><th colspan="2">';
		echo $langmessage['options'];
		echo '</th></tr>';

		echo '<tr><td>';
		echo $langmessage['label'];
		echo '</td><td>';
		echo '<input type="text" name="label" value="'.htmlspecialchars($label).'" class="gpinput" />';
		echo '</td></tr>';

		echo '<tr><td>';
		echo $langmessage['make_default'];
		echo '</td><td>';
		echo '<input type="checkbox" name="default" value="default" />';
		echo '</td></tr>';

		echo '</table>';

		echo '<p>';
		echo ' <input type="hidden" name="theme" value="'.htmlspecialchars($theme).'" />';
		echo ' <input type="hidden" name="cmd" value="addlayout" />';
		echo ' <input type="submit" name="" value="'.$langmessage['save'].'" class="gpsubmit"/>';
		echo ' <input type="button" name="" value="Cancel" class="admin_box_close gpcancel"/>';
		echo '</p>';
		echo '</form>';
	}


	/**
	 * Add a new layout to the installation
	 *
	 */
	public function AddLayout($theme_info){
		global $gpLayouts, $langmessage, $config, $page;

		$new_layout = array();
		$new_layout['theme'] = $theme_info['folder'].'/'.$theme_info['color'];
		$new_layout['color'] = self::GetRandColor();
		$new_layout['label'] = htmlspecialchars($_POST['label']);
		if( $theme_info['is_addon'] ){
			$new_layout['is_addon'] = true;
		}


		includeFile('admin/admin_addon_installer.php');
		$installer = new admin_addon_installer();
		$installer->addon_folder_rel = dirname($theme_info['rel']);
		$installer->code_folder_name = '_themes';
		$installer->source = $theme_info['full_dir'];
		$installer->new_layout = $new_layout;
		if( !empty($_POST['default']) && $_POST['default'] != 'false' ){
			$installer->default_layout = true;
		}

		$success = $installer->Install();
		$installer->OutputMessages();

		if( $success && $installer->default_layout ){
			$page->SetTheme();
			$this->SetLayoutArray();
		}
	}


	/**
	 * Update theme hooks and references in any related layouts
	 *
	 *
	 */
	public function UpdateTheme($theme){
		global $langmessage, $dataDir, $gpLayouts;

		$theme_info = $this->ThemeInfo($theme);

		if( !$theme_info ){
			message($langmessage['OOPS'].'(Invalid Source)');
			return false;
		}


		//install addon
		includeFile('admin/admin_addon_installer.php');
		$installer = new admin_addon_installer();
		$installer->addon_folder_rel = dirname($theme_info['rel']);
		$installer->code_folder_name = '_themes';
		$installer->source = $theme_info['full_dir'];

		$success = $installer->Install();
		$installer->OutputMessages();

		if( !$success ){
			return;
		}

		$this->UpdateLayouts( $installer );
	}


	/**
	 * Update related layouts with new $theme_info
	 *
	 */
	public function UpdateLayouts( $installer ){
		global $gpLayouts;

		$ini_contents = $installer->ini_contents;

		$theme_folder = basename($installer->dest);


		$new_layout_info = array();
		if( strpos($installer->dest,'/data/_themes') !== false ){
			$new_layout_info['is_addon'] = true;
		}else{
			$new_layout_info['is_addon'] = false;
		}

		if( $installer->has_hooks ){
			$new_layout_info['addon_key'] = $installer->config_key;
		}

		if( isset($ini_contents['Addon_Unique_ID']) ){
			$new_layout_info['addon_id'] = $ini_contents['Addon_Unique_ID'];
		}

		if( isset($ini_contents['Addon_Version']) ){
			$new_layout_info['version'] = $ini_contents['Addon_Version'];
		}

		if( isset($ini_contents['Addon_Name']) ){
			$new_layout_info['name'] = $ini_contents['Addon_Name'];
		}

		// update each layout
		foreach($gpLayouts as $layout => $layout_info){

			if( isset($ini_contents['Addon_Unique_ID']) && isset($layout_info['addon_id']) && $layout_info['addon_id'] == $ini_contents['Addon_Unique_ID'] ){
				//update
			}elseif( ( !isset($layout_info['is_addon']) || !$layout_info['is_addon']) && strpos($installer->dest,'/themes/'.dirname($layout_info['theme'])) !== false ){
				//update
			}else{
				continue;
			}

			unset( $layout_info['is_addon'], $layout_info['addon_id'], $layout_info['version'], $layout_info['name'], $layout_info['addon_key'] );

			$layout_info += $new_layout_info;

			$layout_info['theme'] = $theme_folder.'/'.basename($layout_info['theme']);;

			$gpLayouts[$layout] = $layout_info;
		}

		if( !\admin_tools::SavePagesPHP() ){
			message($langmessage['OOPS'].'(Layout Info Not Saved)');
		}
	}



	/**
	 * Display some options before copying a layout
	 *
	 */
	public function CopyLayoutPrompt(){
		global $langmessage, $gpLayouts;

		$layout = $_REQUEST['layout'];
		if( empty($layout) || !isset($gpLayouts[$layout]) ){
			message($langmessage['OOPS'].'(Invalid Request)');
			return;
		}

		$label = self::NewLabel($gpLayouts[$layout]['label']);

		echo '<h2>'.$langmessage['new_layout'].'</h2>';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		echo '<table class="bordered full_width">';

		echo '<tr><th colspan="2">';
		echo $langmessage['options'];
		echo '</th></tr>';

		echo '<tr><td>';
		echo $langmessage['label'];
		echo '</td><td>';
		echo '<input type="text" name="label" value="'.htmlspecialchars($label).'" class="gpinput" />';
		echo '</td></tr>';

		echo '<tr><td>';
		echo $langmessage['make_default'];
		echo '</td><td>';
		echo '<input type="checkbox" name="default" value="default" />';
		echo '</td></tr>';

		echo '</table>';

		echo '<p>';
		echo ' <input type="hidden" name="layout" value="'.htmlspecialchars($layout).'" />';
		echo ' <input type="hidden" name="cmd" value="copylayout" />';
		echo ' <input type="submit" name="" value="'.$langmessage['save'].'" class="gpsubmit"/>';
		echo ' <input type="button" name="" value="Cancel" class="admin_box_close gpcancel"/>';
		echo '</p>';
		echo '</form>';

	}

	/**
	 * Copy a layout
	 *
	 */
	public function CopyLayout(){
		global $gpLayouts,$langmessage,$config,$page,$dataDir;

		$copy_id =& $_REQUEST['layout'];

		if( empty($copy_id) || !isset($gpLayouts[$copy_id]) ){
			message($langmessage['OOPS'].'(Invalid Request)');
			return;
		}
		if( empty($_POST['label']) ){
			message($langmessage['OOPS'].'(Empty Label)');
			return;
		}

		$newLayout = $gpLayouts[$copy_id];
		$newLayout['color'] = self::GetRandColor();
		$newLayout['label'] = htmlspecialchars($_POST['label']);

		//get new unique layout id
		do{
			$layout_id = rand(1000,9999);
		}while( isset($gpLayouts[$layout_id]) );


		$gpLayoutsBefore = $gpLayouts;
		$gpLayouts[$layout_id] = $newLayout;

		if( !\gpFiles::ArrayInsert($copy_id,$layout_id,$newLayout,$gpLayouts,1) ){
			message($langmessage['OOPS'].'(Not Inserted)');
			return;
		}


		//copy any css
		$css = $this->layoutCSS($copy_id);
		if( !empty($css) ){
			$path = $dataDir.'/data/_layouts/'.$layout_id.'/custom.css';
			if( !\gpFiles::Save($path,$css) ){
				message($langmessage['OOPS'].' (CSS not saved)');
				return false;
			}
		}


		if( \admin_tools::SavePagesPHP() ){
			message($langmessage['SAVED']);
		}else{
			$gpLayouts = $gpLayoutsBefore;
			message($langmessage['OOPS'].'(Not Saved)');
		}

	}


	/**
	 * Create a new unique layout label
	 * @static
	 */
	public function NewLabel($label){
		global $gpLayouts;
		$labels = array();

		foreach($gpLayouts as $info){
			$labels[$info['label']] = true;
		}

		$len = strlen($label);
		if( $len > 25 ){
			$label = substr($label,0,$len-2);
		}
		if( substr($label,$len-2,1) === '_' && is_numeric(substr($label,$len-1,1)) ){
			$int = substr($label,$len-1,1);
			$label = substr($label,0,$len-2);
		}


		$int = 1;
		do{
			$new_label = $label.'_'.$int;
			$int++;
		}while( isset($labels[$new_label]) );

		return $new_label;
	}


	public function LoremIpsum(){
		global $page, $langmessage, $gp_titles, $gp_menu;
		ob_start();
		echo '<h2>Lorem Ipsum H2</h2>';
		echo '<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
		quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
		Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. </p>';
		echo '<h3>Lorem Ipsum H3</h3>';
		echo '<p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>';


		echo '<table>';
		echo '<tr><th>Lorem Ipsum Table Heading</th></tr>';
		echo '<tr><td>Lorem Ipsum Table Cell</td></tr>';
		echo '</table>';

		echo '<h4>Lorem Ipsum H4</h4>';
		echo '<blockquote>';
		echo 'Lorem Ipsum Blockquote';
		echo '</blockquote>';


		$page->non_admin_content = ob_get_clean();
	}




	public function ThemeInfo($theme){

		$template = dirname($theme);
		$color = basename($theme);

		if( !isset($this->avail_addons[$template]) || !isset($this->avail_addons[$template]['colors'][$color]) ){
			return false;
		}

		$theme_info = $this->avail_addons[$template];
		$theme_info['color'] = $color;

		return $theme_info;
	}

	/**
	 * Return an array of available themes
	 * @return array
	 *
	 */
	public function GetPossible(){
		global $dataDir,$dirPrefix;

		$themes = array();
		$this->versions = array();

		//local themes
		$dir = $dataDir.'/themes';
		$layouts = \gpFiles::readDir($dir,1);
		foreach($layouts as $name){

			$full_dir	= $dir.'/'.$name;
			$index		= $name.'(local)';
			$addon_id	= false;
			$version	= false;
			$ini_info	= $this->GetAvailInstall($full_dir);

			if( $ini_info === false ){
				continue;
			}

			//check version
			if( isset($ini_info['Addon_Version']) && isset($ini_info['Addon_Unique_ID']) ){
				$addon_id = $ini_info['Addon_Unique_ID'];
				$version = $ini_info['Addon_Version'];

				if( !isset($this->versions[$addon_id]) ){
					$this->versions[$addon_id] = array('version'=>$version,'index'=>$index);
				}elseif( version_compare($this->versions[$addon_id]['version'],$version,'<') ){
					$this->versions[$addon_id] = array('version'=>$version,'index'=>$index);
				}

			}


			$themes[$index]['name']			= $name;
			$themes[$index]['folder']		= $name;
			$themes[$index]['colors']		= $this->GetThemeColors($full_dir);
			$themes[$index]['is_addon']		= false;
			$themes[$index]['full_dir']		= $full_dir;
			$themes[$index]['rel']			= '/themes/'.$name;

			if( isset($ini_info['Addon_Name']) ){
				$themes[$index]['name']		= $ini_info['Addon_Name'];
			}
			if( $version ){
				$themes[$index]['version']	= $ini_info['Addon_Version'];
			}
			if( $addon_id ){
				$themes[$index]['id']		= $ini_info['Addon_Unique_ID'];
			}
		}


		//downloaded themes
		$dir = $dataDir.'/data/_themes';
		$layouts = \gpFiles::readDir($dir,1);
		asort($layouts);
		foreach($layouts as $folder){

			$full_dir	= $dir.'/'.$folder;
			$addon_id	= false;
			$version	= false;
			$ini_info	= $this->GetAvailInstall($full_dir);

			if( $ini_info === false ){
				continue;
			}

			$index		= $ini_info['Addon_Name'].'(remote)';

			//check version
			if( isset($ini_info['Addon_Version']) && isset($ini_info['Addon_Unique_ID']) ){
				$addon_id = $ini_info['Addon_Unique_ID'];
				$version = $ini_info['Addon_Version'];

				if( !isset($this->versions[$addon_id]) ){
					$this->versions[$addon_id] = array('version'=>$version,'index'=>$index);
				}elseif( version_compare($this->versions[$addon_id]['version'],$version,'<') ){
					$this->versions[$addon_id] = array('version'=>$version,'index'=>$index);
				}
			}


			$themes[$index]['name']			= $ini_info['Addon_Name'];
			$themes[$index]['colors']		= $this->GetThemeColors($full_dir);
			$themes[$index]['folder']		= $folder;
			$themes[$index]['is_addon']		= true;
			$themes[$index]['full_dir']		= $full_dir;
			$themes[$index]['id']			= $ini_info['Addon_Unique_ID'];
			$themes[$index]['rel']			= '/data/_themes/'.$folder;
			if( isset($ini_info['Addon_Version']) ){
				$themes[$index]['version'] = $ini_info['Addon_Version'];
			}
		}


		if( !gp_unique_addons ){
			$this->avail_addons = $themes;

		}else{


			//remove older versions
			$this->avail_addons = array();
			foreach($themes as $index => $info){

				if( !isset($info['id']) || !isset($info['version']) ){
					$this->avail_addons[$index] = $info;
					continue;
				}

				if( version_compare($this->versions[$info['id']]['version'], $info['version'],'>') ){
					continue;
				}

				$this->avail_addons[$index] = $info;
			}

			uksort($this->avail_addons,'strnatcasecmp');
		}

		$this->avail_count = count($this->avail_addons);
	}


	/**
	 * Return ini info if the addon is installable
	 *
	 */
	public function GetAvailInstall($dir){
		global $langmessage;

		$iniFile		= $dir.'/Addon.ini';
		$template_file	= $dir.'/template.php';
		$dirname		= basename($dir);

		if( !is_readable($dir) ){
			$this->invalid_folders[$dirname]	= 'Directory is not readable';
			return false;
		}

		if( !file_exists($template_file) ){
			$this->invalid_folders[$dirname]	= 'template.php is not readable or does not exist';
			return false;
		}

		if( !file_exists($iniFile) ){
			return array();
		}

		$array = \gp_ini::ParseFile($iniFile);
		if( $array === false ){
			return array();
		}

		$array += array('Addon_Version'=>'');
		return $array;
	}


	/**
	 * Get a list of theme subfolders that have style.css files
	 *
	 */
	public function GetThemeColors($dir){
		$subdirs = \gpFiles::readDir($dir,1);
		$colors = array();
		asort($subdirs);
		foreach($subdirs as $subdir){
			$css_path = $dir.'/'.$subdir.'/style.css';
			$less_path = $dir.'/'.$subdir.'/style.less';
			if( file_exists($css_path) || file_exists($less_path) ){
				$colors[$subdir] = $subdir;
			}

		}
		return $colors;
	}


	/**
	 * Save $layout as the default layout for the site
	 * @param string $layout
	 *
	 */
	public function MakeDefault(){
		global $config,$langmessage,$page;


		$oldConfig = $config;
		$config['gpLayout'] = $this->curr_layout;

		if( \admin_tools::SaveConfig() ){

			$page->SetTheme();
			$this->SetLayoutArray();

			message($langmessage['SAVED']);
		}else{
			$config = $oldConfig;
			message($langmessage['OOPS']);
		}
	}


	/**
	 * Save the color and label of a layout
	 *
	 */
	public function LayoutLabel(){
		global $gpLayouts, $langmessage, $page;
		$page->ajaxReplace = array();

		$gpLayoutsBefore = $gpLayouts;

		$layout =& $_POST['layout'];
		if( !isset( $gpLayouts[$layout]) ){
			message($langmessage['OOPS']);
			return false;
		}

		if( !empty($_POST['color']) && (strlen($_POST['color']) == 7) && $_POST['color']{0} == '#' ){
			$gpLayouts[$layout]['color'] = $_POST['color'];
		}

		$gpLayouts[$layout]['label'] = htmlspecialchars($_POST['layout_label']);


		if( !\admin_tools::SavePagesPHP() ){
			$gpLayouts = $gpLayoutsBefore;
			message($langmessage['OOPS'].' (s1)');
			return;
		}

		//send new label
		$layout_info = \common::LayoutInfo($layout,false);
		$replace = $this->GetLayoutLabel($layout, $layout_info);
		$page->ajaxReplace[] = array( 'replace', '.layout_label_'.$layout, $replace);
	}

	/**
	 * Show all layouts and themes
	 *
	 */
	public function ShowLayouts(){
		global $config, $page, $langmessage, $gpLayouts;

		$page->head_js[] = '/include/js/auto_width.js';

		$this->ShowHeader();

		$default_layout = $config['gpLayout'];

		echo '<div id="adminlinks2">';

		//all other layouts
		foreach($gpLayouts as $layout => $info){
			$this->LayoutDiv($layout,$info);
		}
		echo '</div>';

		echo '<p class="admin_note">';
		echo $langmessage['see_also'].' '.\common::Link('Admin_Menu',$langmessage['file_manager']);
		echo '</p>';


		$this->ColorSelector();
	}

	/**
	 * Display the color selector for
	 * @param string $layout The layout being edited
	 *
	 */
	public function ColorSelector($layout = false){

		$colors = self::GetColors();
		echo '<div id="layout_ident" class="gp_floating_area">';
		echo '<div>';

		if( $layout ){
			echo '<form action="'.\common::GetUrl('Admin_Theme_Content/Edit/'.$layout).'" method="post">';
		}else{
			echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		}
		echo '<input type="hidden" name="layout" value="" />';
		echo '<input type="hidden" name="color" value="" />';
		echo '<input type="hidden" name="cmd" value="layout_label" />';

		echo '<table>';


		echo '<tr><td>';
		echo ' <a class="layout_color_id" id="current_color"></a> ';
		echo '<input type="text" name="layout_label" value="" maxlength="25"/>';
		echo '</td></tr>';

		echo '<tr><td>';
		echo '<div class="colors">';
		foreach($colors as $color){
			echo '<a class="color" style="background-color:'.$color.'" title="'.$color.'" data-arg="'.$color.'"></a>';
		}
		echo '</div>';
		echo '</td></tr>';

		echo '<tr><td>';
		echo ' <input type="submit" name="" value="Ok" class="gpajax close_color_dialog gpsubmit" />';
		echo ' <input type="button" class="close_color_dialog gpcancel" name="" value="Cancel" />';
		echo '</td></tr>';

		echo '</table>';
		echo '</form>';
		echo '</div>';
		echo '</div>';

	}


	/**
	 * Show available themes and style variations
	 *
	 */
	public function ShowAvailable(){
		global $langmessage, $config, $page;


		$cmd = \common::GetCommand();

		switch($cmd){
			case 'preview':
			case 'preview_iframe':
			case 'newlayout':
			case 'addlayout':
				if( $this->NewLayout($cmd) ){
					return;
				}
			break;
		}


		$page->head_js[] = '/include/js/auto_width.js';
		$this->GetAddonData();

		$this->ShowHeader();
		$this->searchUrl = 'Admin_Theme_Content/Available';

		$this->AvailableList();

		$this->InvalidFolders();
	}


	public function AvailableList( $show_options = true ){
		global $langmessage, $config;

		//search settings
		$this->searchPerPage = 10;
		$this->searchOrderOptions = array();
		$this->searchOrderOptions['modified']		= $langmessage['Recently Updated'];
		$this->searchOrderOptions['rating_score']	= $langmessage['Highest Rated'];
		$this->searchOrderOptions['downloads']		= $langmessage['Most Downloaded'];

		$this->SearchOrder();


		// get addon information for ordering
		\admin_tools::VersionData($version_data);
		$version_data = $version_data['packages'];

		// combine remote addon information
		foreach($this->avail_addons as $theme_id => $info){

			if( isset($info['id']) ){
				$id = $info['id'];

				if( isset($version_data[$id]) ){
					$info = array_merge($info,$version_data[$id]);
					$info['rt'] *= 5;
				}

				//use local rating
				if( isset($this->addonReviews[$id]) ){
					$info['rt'] = $this->addonReviews[$id]['rating'];
				}
			}else{
				$info['rt'] = 6; //give local themes a high rating to make them appear first, rating won't actually display
			}

			$info += array( 'dn'=>0, 'rt'=>0 );

			//modified time
			if( !isset($info['tm']) ){
				$info['tm'] = self::ModifiedTime( $info['full_dir'] );
			}


			$this->avail_addons[$theme_id] = $info;
		}


		// sort by
		uasort( $this->avail_addons, array('admin_theme_content','SortUpdated') );
		switch($this->searchOrder){

			case 'downloads':
				uasort( $this->avail_addons, array('admin_theme_content','SortDownloads') );
			break;

			case 'modified':
				uasort( $this->avail_addons, array('admin_theme_content','SortRating') );
				uasort( $this->avail_addons, array('admin_theme_content','SortUpdated') );
			break;

			case 'rating_score':
			default:
				uasort( $this->avail_addons, array('admin_theme_content','SortRating') );
			break;
		}

		// pagination
		$this->searchMax = count($this->avail_addons);
		if( isset($_REQUEST['page']) && ctype_digit($_REQUEST['page']) ){
			$this->searchPage = $_REQUEST['page'];
		}

		$start = $this->searchPage * $this->searchPerPage;
		$possible = array_slice( $this->avail_addons, $start, $this->searchPerPage, true);


		if( $show_options ){
			$this->SearchOptions();
		}


		// show themes
		echo '<div id="gp_avail_themes">';
		foreach($possible as $theme_id => $info){
			$theme_label = str_replace('_',' ',$info['name']);
			$version = '';
			$id = false;
			if( isset($info['version']) ){
				$version = $info['version'];
			}
			if( isset($info['id']) && is_numeric($info['id']) ){
				$id = $info['id'];
			}

			$has_screenshot = file_exists($info['full_dir'].'/screenshot.png');

			//screenshot
			if( $has_screenshot ){
				echo '<div class="expand_child_click">';
				echo '<b class="gp_theme_head">'.$theme_label.' '.$version.'</b>';
				echo '<div style="background-image:url(\''.\common::GetDir($info['rel'].'/screenshot.png').'\')">';
			}else{
				echo '<div>';
				echo '<b class="gp_theme_head">'.$theme_label.' '.$version.'</b>';
				echo '<div>';
			}

			//options
			echo '<div class="gp_theme_options">';

				//colors
				echo '<b>'.$langmessage['preview'].'</b>';
				echo '<ul>';
				foreach($info['colors'] as $color){
					echo '<li>';
					$q = 'cmd=preview&theme='.rawurlencode($theme_id.'/'.$color).$this->searchQuery;
					if( $this->searchPage ){
						$q .= '&page='.$this->searchPage;
					}
					echo \common::Link('Admin_Theme_Content/Available',str_replace('_','&nbsp;',$color),$q);
					echo '</li>';
				}
				echo '</ul>';



				ob_start();
				if( $id ){

					//more info
					echo '<li>'.$this->DetailLink('theme', $id,'More Info...').'</li>';


					//support
					$forum_id = 1000 + $id;
					echo '<li><a href="'.addon_browse_path.'/Forum?show=f'.$forum_id.'" target="_blank">'.$langmessage['Support'].'</a></li>';

					//rating
					$rating = 0;
					if( $info['rt'] > 0 ){
						$rating = $info['rt'];
					}
					echo '<li><span class="nowrap">'.$langmessage['rate'].' '.$this->ShowRating($info['rel'],$rating).'</span></li>';


					//downloads
					if( $info['dn'] > 0 ){
						echo '<li><span class="nowrap">Downloads: '.number_format($info['dn']).'</span></li>';
					}
				}

				//last updated
				if( $info['tm'] > 0 ){
					echo '<li><span class="nowrap">'.$langmessage['Modified'].': ';
					echo \common::date($langmessage['strftime_datetime'],$info['tm']);
					echo '</span></li>';
				}



				if( $info['is_addon'] ){

					//delete
					$folder = $info['folder'];
					$title = sprintf($langmessage['generic_delete_confirm'], $theme_label );
					$attr = array( 'data-cmd'=>'cnreq','class'=>'gpconfirm','title'=> $title );
					echo '<li>'.\common::Link('Admin_Theme_Content',$langmessage['delete'],'cmd=deletetheme&folder='.rawurlencode($folder),$attr).'</li>';

					//order
					if( isset($config['themes'][$folder]['order']) ){
						echo '<li>Order: '.$config['themes'][$folder]['order'].'</li>';
					}
				}


				$options = ob_get_clean();

				if( !empty($options) ){
					echo '<b>'.$langmessage['options'].'</b>';
					echo '<ul>';
					echo $options;
					echo '</ul>';
				}

			echo '</div></div>';

			//remote upgrade
			if( gp_remote_themes && $id && isset(\admin_tools::$new_versions[$id]) && version_compare(\admin_tools::$new_versions[$id]['version'], $version ,'>') ){
				$version_info = \admin_tools::$new_versions[$id];
				echo \common::Link('Admin_Theme_Content',$langmessage['new_version'],'cmd=remote_install&id='.$id.'&name='.rawurlencode($version_info['name']));
			}


			echo '</div>';
		}
		echo '</div>';


 		if( $show_options ){
			$this->SearchNavLinks();
		}

	}

	public static function ModifiedTime($directory){

		$files = scandir( $directory );
		$time = filemtime( $directory );
		foreach($files as $file){
			if( $file == '..' || $file == '.' ){
				continue;
			}

			$full_path = $directory.'/'.$file;

			if( is_dir($full_path) ){
				$time = max( $time, self::ModifiedTime( $full_path ) );
			}else{
				$time = max( $time, filemtime( $full_path ) );
			}
		}
		return $time;
	}

	public static function SortDownloads($a,$b){
		return $b['dn'] > $a['dn'];
	}
	public static function SortRating($a,$b){
		return $b['rt'] > $a['rt'];
	}
	public static function SortUpdated($a,$b){
		return $b['tm'] > $a['tm'];
	}
	public static function SortName($a,$b){
		return strnatcasecmp($a['name'],$b['name']);
	}


	/**
	 * Display layout label and options
	 *
	 */
	public function LayoutDiv($layout,$info){
		global $page, $langmessage;

		$layout_info = \common::LayoutInfo($layout,false);


		echo '<div class="panelgroup" id="panelgroup_'.md5($layout).'">';
		echo $this->GetLayoutLabel($layout, $info);


		echo '<div class="panelgroup2">';
		echo '<ul class="submenu">';

		echo '<li>';
		echo \common::Link('Admin_Theme_Content/Edit/'.rawurlencode($layout),$langmessage['edit_this_layout'],'',' title="'.htmlspecialchars($langmessage['Arrange Content']).'" ');
		echo '</li>';



		//layout options
		echo '<li class="expand_child_click">';
		echo '<a>'.$langmessage['Layout Options'].'</a>';
		echo '<ul>';
		$this->LayoutOptions($layout,$layout_info);
		echo '</ul>';



		//css options
		echo '<li class="expand_child_click">';
		echo '<a>CSS</a>';
		echo $this->CSSPreferenceForm($layout,$layout_info);
		echo '</li>';


		// layouts with hooks
		ob_start();
		$addon_config = false;
		if( isset($layout_info['addon_key']) ){
			$addon_key = $layout_info['addon_key'];
			$addon_config = \gpPlugin::GetAddonConfig($addon_key);
			echo '<li>';
			echo \common::link('Admin_Addons/'.\admin_tools::encode64($addon_key),'<span class="gpicon_plug"></span> '.$addon_config['name']);
			echo '</li>';

			//hooks
			$this->AddonPanelGroup($addon_key, false );
		}


		//version
		if( !empty($layout_info['version']) ){
			echo '<li><a>'.$langmessage['Your_version'].' '.$layout_info['version']. '</a></li>';
		}elseif( $addon_config && !empty($addon_config['version']) ){
			echo '<li><a>'.$langmessage['Your_version'].' '.$addon_config['version']. '</a></li>';
		}

		//upgrade
		echo '<li>';
		if( $layout_info['is_addon'] ){
			$source = $layout_info['name'].'(remote)/'.$layout_info['theme_color'];
		}else{
			$source = $layout_info['theme_name'].'(local)/'.$layout_info['theme_color'];
		}
		echo \common::Link('Admin_Theme_Content',$langmessage['upgrade'],'cmd=updatetheme&source='.rawurlencode($source),array('data-cmd'=>'creq'));
		echo '</li>';


		$options = ob_get_clean();

		if( !empty($options) ){
			echo '<li class="expand_child_click">';
			echo '<a>'.$langmessage['options'].'</a>';
			echo '<ul>';

			echo $options;

			echo '</ul></li>';
		}

		//new versions
		if( isset($layout_info['addon_id']) ){
			$addon_id = $layout_info['addon_id'];
			$version =& $layout_info['version'];

			//local or already downloaded
			if( isset($this->versions[$addon_id]) && version_compare($this->versions[$addon_id]['version'],$version,'>') ){
				$version_info = $this->versions[$addon_id];
				$label = $langmessage['upgrade'].' &nbsp; '.$version_info['version'];
				$source = $version_info['index'].'/'.$layout_info['theme_color']; //could be different folder
				echo '<div class="gp_notice">';
				echo \common::Link('Admin_Theme_Content',$label,'cmd=updatetheme&source='.$source,array('data-cmd'=>'creq'));
				echo '</div>';


			//remote version
			}elseif( gp_remote_themes && isset(\admin_tools::$new_versions[$addon_id]) && version_compare(\admin_tools::$new_versions[$addon_id]['version'],$version,'>') ){
				$version_info = \admin_tools::$new_versions[$addon_id];
				$label = $langmessage['new_version'].' &nbsp; '.$version_info['version'].' &nbsp; (gpEasy.com)';
				echo '<div class="gp_notice">';
				echo \common::Link('Admin_Theme_Content',$label,'cmd=remote_install&id='.$addon_id.'&name='.rawurlencode($version_info['name']).'&layout='.$layout);
				echo '</div>';
			}

		}


		echo '</ul>';

		echo '</div>';
		echo '</div>';
	}


	/**
	 * Return the layout name and id color
	 *
	 */
	public function GetLayoutLabel( $layout, $layout_info ){
		global $config, $langmessage, $config;

		ob_start();
		echo '<span class="layout_label_'.$layout.' layout_label">';
		echo '<a data-cmd="layout_id" title="'.$layout_info['color'].'" data-arg="'.$layout_info['color'].'">';
		echo '<input type="hidden" name="layout" value="'.htmlspecialchars($layout).'"  /> ';
		echo '<input type="hidden" name="layout_label" value="'.$layout_info['label'].'"  /> ';
		echo '<span class="layout_color_id" style="background-color:'.$layout_info['color'].';"></span> ';
		if( $config['gpLayout'] == $layout ){
			echo ' <span class="layout_default"> ('.$langmessage['default'].')</span>';
			echo '&nbsp;';
		}
		echo $layout_info['label'];

		echo '</a>';
		echo '</span>';
		return ob_get_clean();
	}



	/**
	 * Return form for name based menu classes and ordered menu classes
	 *
	 */
	public function CSSPreferenceForm($layout,$layout_info){
		global $langmessage;

		ob_start();
		echo '<ul id="layout_css_ul_'.$layout.'">';


		// name based menu classes
		echo '<li>';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		echo '<input type="hidden" name="layout" value="'.$layout.'" />';
		echo '<input type="hidden" name="cmd" value="css_preferences" />';
		$checked = '';
		$value = 'on';
		if( !isset($layout_info['menu_css_ordered']) ){
			$checked = 'checked="checked"';
			$value = 'off';
		}
		echo '<input type="hidden" name="menu_css_ordered" value="'.$value.'" />';
		echo '<label>';
		echo '<input type="checkbox" name="none" value="" '.$checked.' class="gpajax" />';
		echo ' Name Based Menu Classes';
		echo '</label>';
		echo '</form>';
		echo '</li>';

		//ordered menu classes
		echo '<li>';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		echo '<input type="hidden" name="layout" value="'.$layout.'" />';
		echo '<input type="hidden" name="cmd" value="css_preferences" />';
		$checked = '';
		$value = 'on';
		if( !isset($layout_info['menu_css_indexed']) ){
			$checked = 'checked="checked"';
			$value = 'off';
		}
		echo '<input type="hidden" name="menu_css_indexed" value="'.$value.'" />';
		echo '<label>';
		echo '<input type="checkbox" name="none" value="" '.$checked.' class="gpajax" />';
		echo ' Ordered Menu Classes';
		echo '</label>';
		echo '</form>';
		echo '</li>';
		echo '</ul>';
		return ob_get_clean();
	}

	public function ThemeLabel($theme_color){

		$theme = $theme_color;
		$color = false;
		if( strpos($theme_color,'/') ){
			list($theme,$color) = explode('/',$theme_color);
		}

		foreach($this->avail_addons as $info){

			if( $info['folder'] == $theme ){
				$theme = $info['name'];
				break;
			}
		}

		if( $color ){
			return $theme.'/'.$color;
		}
		return $theme;
	}

	public function TitlesCount($layout){
		$titles_count = 0;
		foreach( $this->LayoutArray as $layout_comparison ){
			if( $layout == $layout_comparison ){
				$titles_count++;
			}
		}
		return $titles_count;
	}

	/**
	 * Restore a layout to it's default content arrangement
	 */
	public function Restore(){
		$this->SaveHandlersNew(array(),$this->curr_layout);
	}

	public function SaveHandlersNew($handlers,$layout=false){
		global $config,$page,$langmessage,$gpLayouts;

		//make sure the keys are sequential
		foreach($handlers as $container => $container_info){
			if( is_array($container_info) ){
				$handlers[$container] = array_values($container_info);
			}
		}

		if( $layout == false ){
			$layout = $this->curr_layout;
		}

		if( !isset( $gpLayouts[$layout] )  ){
			message($langmessage['OOPS']);
			return false;
		}

		$gpLayoutsBefore = $gpLayouts;
		if( count($handlers) === 0 ){
			unset($gpLayouts[$layout]['handlers']);
		}else{
			$gpLayouts[$layout]['handlers'] = $handlers;
		}

		if( \admin_tools::SavePagesPHP() ){

			message($langmessage['SAVED']);

		}else{
			$gpLayouts = $gpLayoutsBefore;
			message($langmessage['OOPS'].' (s1)');
		}
	}


	public function ParseHandlerInfo($str,&$info){
		global $config,$gpOutConf;

		if( substr_count($str,'|') !== 1 ){
			return false;
		}


		list($container,$fullKey) = explode('|',$str);

		$arg = '';
		$pos = strpos($fullKey,':');
		$key = $fullKey;
		if( $pos > 0 ){
			$arg = substr($fullKey,$pos+1);
			$key = substr($fullKey,0,$pos);
		}

		if( !isset($gpOutConf[$key]) && !isset($config['gadgets'][$key]) ){
			return false;
		}

		$info = array();
		$info['gpOutCmd'] = trim($fullKey,':');
		$info['container'] = $container;
		$info['key'] = $key;
		$info['arg'] = $arg;

		return true;

	}


	public function GetAllHandlers($layout=false){
		global $page,$gpLayouts, $config;

		if( $layout === false ){
			$layout = $this->curr_layout;
		}

		$handlers =& $gpLayouts[$layout]['handlers'];

		if( !is_array($handlers) || count($handlers) < 1 ){
			$gpLayouts[$layout]['hander_v'] = '2';
			$handlers = array();
		}

		//clean : characters for backwards compat
		foreach($handlers as $container => $container_info){
			if( is_string($container_info) ){
				$handlers[$container] = trim($container_info,':');
				continue;
			}
			if( !is_array($container_info) ){
				continue;
			}
			foreach($container_info as $key => $gpOutCmd){
				$handlers[$container][$key] = trim($gpOutCmd,':');
			}
		}

		return $handlers;
	}


	//set default values if not set
	public function PrepContainerHandlers(&$handlers,$container,$gpOutCmd){
		if( isset($handlers[$container]) && is_array($handlers[$container]) ){
			return;
		}
		$handlers[$container] = $this->GetDefaultList($container,$gpOutCmd);
	}



	public function GetDefaultList($container,$gpOutCmd){
		global $config;

		if( $container !== 'GetAllGadgets' ){

			//Just a container that doesn't have content by default
			// ex: 		gpOutput::Get('AfterContent');
			if( empty($gpOutCmd) ){
				return array();
			}

			return array($gpOutCmd);
		}

		$result = array();
		if( isset($config['gadgets']) && is_array($config['gadgets']) ){
			foreach($config['gadgets'] as $gadget => $info){
				if( isset($info['addon']) ){
					$result[] = $gadget;
				}
			}
		}
		return $result;
	}

	public function GetValues($a,&$container,&$gpOutCmd){
		if( substr_count($a,'|') !== 1 ){
			return false;
		}

		list($container,$gpOutCmd) = explode('|',$a);
		return true;
	}

	public function AddToContainer(&$container,$to_gpOutCmd,$new_gpOutCmd,$replace=true,$offset=0){
		global $langmessage;

		//unchanged?
		if( $replace && ($to_gpOutCmd == $new_gpOutCmd) ){
			return true;
		}


		//add to to_container in front of $to_gpOutCmd
		if( !isset($container) || !is_array($container) ){
			message($langmessage['OOPS'].' (a1)');
			return false;
		}

		//can't have two identical outputs in the same container
		$check = array_search($new_gpOutCmd,$container);
		if( ($check !== null) && ($check !== false) ){
			message($langmessage['OOPS']. ' (Area already in container)');
			return false;
		}

		//if empty, just add
		if( count($container) === 0 ){
			$container[] = $new_gpOutCmd;
			return true;
		}

		$length = 1;
		if( $replace === false ){
			$length = 0;
		}

		//insert
		$where = array_search($to_gpOutCmd,$container);
		if( ($where === null) || ($where === false) ){
			message($langmessage['OOPS']. ' (Destination Container Not Found)');
			return false;
		}
		$where += $offset;

		array_splice($container,$where,$length,$new_gpOutCmd);

		return true;
	}



	public function NewCustomMenu(){

		$upper_bound =& $_POST['upper_bound'];
		$lower_bound =& $_POST['lower_bound'];
		$expand_bound =& $_POST['expand_bound'];
		$expand_all =& $_POST['expand_all'];
		$source_menu =& $_POST['source_menu'];

		$this->CleanBounds($upper_bound,$lower_bound,$expand_bound,$expand_all,$source_menu);

		$arg = $upper_bound.','.$lower_bound.','.$expand_bound.','.$expand_all.','.$source_menu;
		return 'CustomMenu:'.$arg;
	}

	public function NewPresetMenu(){
		global $gpOutConf;

		$new_gpOutCmd =& $_POST['new_handle'];
		if( !isset($gpOutConf[$new_gpOutCmd]) || !isset($gpOutConf[$new_gpOutCmd]['link']) ){
			return false;
		}

		return rtrim($new_gpOutCmd.':'.$this->CleanMenu($_POST['source_menu']),':');
	}

	public function CleanBounds(&$upper_bound,&$lower_bound,&$expand_bound,&$expand_all,&$source_menu){

		$upper_bound = (int)$upper_bound;
		$upper_bound = max(0,$upper_bound);
		$upper_bound = min(4,$upper_bound);

		$lower_bound = (int)$lower_bound;
		$lower_bound = max(-1,$lower_bound);
		$lower_bound = min(4,$lower_bound);

		$expand_bound = (int)$expand_bound;
		$expand_bound = max(-1,$expand_bound);
		$expand_bound = min(4,$expand_bound);

		if( $expand_all ){
			$expand_all = 1;
		}else{
			$expand_all = 0;
		}

		$source_menu = $this->CleanMenu($source_menu);
	}
	public function CleanMenu($menu){
		global $config;

		if( empty($menu) ){
			return '';
		}
		if( !isset($config['menus'][$menu]) ){
			return '';
		}
		return $menu;
	}

	public function PresetMenuForm($args = array()){
		global $gpOutConf,$langmessage;

		$current_function =& $args['current_function'];
		$current_menu =& $args['source_menu'];

		$this->MenuSelect($current_menu);


		echo '<tr><th colspan="2">';
			echo $langmessage['Menu Output'];
		echo '</th></tr>';


		$i = 0;
		foreach($gpOutConf as $outKey => $info){

			if( !isset($info['link']) ){
				continue;
			}
			echo '<tr>';
			echo '<td>';
			echo '<label for="new_handle_'.$i.'">';
			if( isset($langmessage[$info['link']]) ){
				echo str_replace(' ','&nbsp;',$langmessage[$info['link']]);
			}else{
				echo str_replace(' ','&nbsp;',$info['link']);
			}
			echo '</label>';
			echo '</td>';
			echo '<td class="add">';

			if( $current_function == $outKey ){
				echo '<input id="new_handle_'.$i.'" type="radio" name="new_handle" value="'.$outKey.'" checked="checked"/>';
			}else{
				echo '<input id="new_handle_'.$i.'" type="radio" name="new_handle" value="'.$outKey.'" />';
			}
			echo '</td>';
			echo '</tr>';
			$i++;
		}
	}


	public function MenuArgs($curr_info){

		$menu_args = array();

		if( $curr_info['key'] == 'CustomMenu' ){
			$showCustom = true;

			$args = explode(',',$curr_info['arg']);
			$args += array( 0=>0, 1=>-1, 2=>-1, 3=>0, 4=>'' ); //defaults
			list($upper_bound,$lower_bound,$expand_bound,$expand_all,$source_menu) = $args;

			$this->CleanBounds($upper_bound,$lower_bound,$expand_bound,$expand_all,$source_menu);


			$menu_args['upper_bound'] = $upper_bound;
			$menu_args['lower_bound'] = $lower_bound;
			$menu_args['expand_bound'] = $expand_bound;
			$menu_args['expand_all'] = $expand_all;
			$menu_args['source_menu'] = $source_menu;


		}else{

			$menu_args['current_function'] = $curr_info['key'];
			$menu_args['source_menu'] = $this->CleanMenu($curr_info['arg']);
		}


		return $menu_args;

	}


	/**
	 * Output form elements for setting custom menu settings
	 *
	 * @param string $arg
	 * @param array $menu_args
	 */
	public function CustomMenuForm($arg = '',$menu_args = array()){
		global $langmessage;


		$upper_bound	=& $menu_args['upper_bound'];
		$lower_bound	=& $menu_args['lower_bound'];
		$expand_bound	=& $menu_args['expand_bound'];
		$expand_all		=& $menu_args['expand_all'];
		$source_menu	=& $menu_args['source_menu'];


		echo '<table class="bordered">';

		$this->MenuSelect($source_menu);

		echo '<tr><th colspan="2">';
		echo $langmessage['Show Titles...'];
		echo '</th></tr>';

		$this->CustomMenuSection($langmessage['... Below Level'], 'upper_bound', $upper_bound);
		$this->CustomMenuSection($langmessage['... At And Above Level'], 'lower_bound', $lower_bound);


		echo '<tr><th colspan="2">';
		echo $langmessage['Expand Menu...'];
		echo '</th></tr>';

		$this->CustomMenuSection($langmessage['... Below Level'], 'expand_bound', $expand_bound);


		echo '<tr><td>';
		echo $langmessage['... Expand All'];
		echo '</td><td class="add">';
		$attr = $expand_all ? 'checked' : '';
		echo '<input type="checkbox" name="expand_all" '.$attr.'>';
		echo '</td></tr>';

	}


	/**
	 * Output section for custom menu form
	 *
	 * @param string $label
	 * @param string $name
	 * @param int $value
	 */
	public function CustomMenuSection($label, $name, $value){
		echo '<tr><td>';
		echo $label;
		echo '</td><td class="add">';
		echo '<select name="'.$name.'" class="gpselect">';
		for($i=0;$i<=4;$i++){

			$label		= ($i === 0) ? '' : $i;
			$selected	= ($i === $value) ? 'selected' : '';

			echo '<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
		}

		echo '</select>';
		echo '</td></tr>';
	}


	public function MenuSelect($source_menu){
		global $config, $langmessage;

		echo '<tr><th colspan="2">';
			echo $langmessage['Source Menu'];
		echo '</th>';
		echo '</tr>';
		echo '<tr><td>';
		echo $langmessage['Menu'];
		echo '</td><td class="add">';
		echo '<select name="source_menu" class="gpselect">';
		echo '<option value="">'.$langmessage['Main Menu'].'</option>';
		if( isset($config['menus']) && count($config['menus']) > 0 ){
			foreach($config['menus'] as $id => $menu ){
				$attr = '';
				if( $source_menu == $id ){
					$attr = ' selected="selected"';
				}
				echo '<option value="'.htmlspecialchars($id).'" '.$attr.'>'.htmlspecialchars($menu).'</option>';
			}
		}
		echo '</select>';
		echo '</td></tr>';
	}


	public function ReturnHeader(){

		if( empty($_POST['return']) ){
			return;
		}


		$return = trim($_POST['return']);
		if( strpos($return,'http') !== 0 ){
			$return = \common::GetUrl($return,'',false);
		}
		\common::Redirect($return,302);
	}



	public function GetAddonTexts($addon){
		global $langmessage,$config;


		$addon_config = \gpPlugin::GetAddonConfig($addon);
		$addonDir = $addon_config['code_folder_full'];
		if( !is_dir($addonDir) ){
			return false;
		}

		//not set up correctly
		if( !isset($config['addons'][$addon]['editable_text']) ){
			return false;
		}

		$file = $addonDir.'/'.$config['addons'][$addon]['editable_text'];
		if( !file_exists($file) ){
			return false;
		}

		$texts = array();
		include($file);

		if( !$texts ){
			return false;
		}

		return $texts;
	}


	public function SaveAddonText(){
		global $langmessage,$config;

		$addon = \gp_edit::CleanArg($_REQUEST['addon']);
		$texts = $this->GetAddonTexts($addon);
		//not set up correctly
		if( $texts === false ){
			message($langmessage['OOPS'].' (0)');
			return;
		}

		$configBefore = $config;
		foreach($texts as $text){
			if( !isset($_POST['values'][$text]) ){
				continue;
			}


			$default = $text;
			if( isset($langmessage[$text]) ){
				$default = $langmessage[$text];
			}

			$value = htmlspecialchars($_POST['values'][$text]);

			if( ($value === $default) || (htmlspecialchars($default) == $value) ){
				unset($config['customlang'][$text]);
			}else{
				$config['customlang'][$text] = $value;
			}
		}

		if( !\admin_tools::SaveConfig() ){
			//these two lines are fairly useless when the ReturnHeader() is used
			$config = $configBefore;
			message($langmessage['OOPS'].' (1)');
		}else{

			$this->UpdateAddon($addon);

			message($langmessage['SAVED']);

		}

		$this->ReturnHeader();
	}

	public function UpdateAddon($addon){
		if( !function_exists('OnTextChange') ){
			return;
		}

		\gpPlugin::SetDataFolder($addon);

		OnTextChange();

		\gpPlugin::ClearDataFolder();
	}

	public function AddonText(){
		global $langmessage,$config;

		$addon = \gp_edit::CleanArg($_REQUEST['addon']);
		$texts = $this->GetAddonTexts($addon);

		//not set up correctly
		if( $texts === false ){
			$this->EditText();
			return;
		}


		echo '<div class="inline_box" style="text-align:right">';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		echo '<input type="hidden" name="cmd" value="saveaddontext" />';
		echo '<input type="hidden" name="return" value="" />'; //will be populated by javascript
		echo '<input type="hidden" name="addon" value="'.htmlspecialchars($addon).'" />'; //will be populated by javascript


		$this->AddonTextFields($texts);
		echo ' <input type="submit" name="aaa" value="'.$langmessage['save'].'" class="gpsubmit" />';
		echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="admin_box_close gpcancel" />';


		echo '</form>';
		echo '</div>';

	}

	public function AddonTextFields($array){
		global $langmessage,$config;
		echo '<table class="bordered">';
			echo '<tr>';
			echo '<th>';
			echo $langmessage['default'];
			echo '</th>';
			echo '<th>';
			echo '</th>';
			echo '</tr>';

		$key =& $_GET['key'];
		foreach($array as $text){

			$default = $value = $text;
			if( isset($langmessage[$text]) ){
				$default = $value = $langmessage[$text];
			}
			if( isset($config['customlang'][$text]) ){
				$value = $config['customlang'][$text];
			}

			$style = '';
			if( $text == $key ){
				$style = ' style="background-color:#f5f5f5"';
			}

			echo '<tr'.$style.'>';
			echo '<td>';
			echo $text;
			echo '</td>';
			echo '<td>';
			echo '<input type="text" name="values['.htmlspecialchars($text).']" value="'.$value.'" class="gpinput"/>'; //value has already been escaped with htmlspecialchars()
			echo '</td>';
			echo '</tr>';

		}
		echo '</table>';
	}





	public function EditText(){
		global $config, $langmessage,$page;

		if( !isset($_GET['key']) ){
			message($langmessage['OOPS'].' (0)');
			return;
		}

		$default = $value = $key = $_GET['key'];
		if( isset($langmessage[$key]) ){
			$default = $value = $langmessage[$key];

		}
		if( isset($config['customlang'][$key]) ){
			$value = $config['customlang'][$key];
		}


		echo '<div class="inline_box">';
		echo '<form action="'.\common::GetUrl('Admin_Theme_Content').'" method="post">';
		echo '<input type="hidden" name="cmd" value="savetext" />';
		echo '<input type="hidden" name="key" value="'.htmlspecialchars($key).'" />';
		echo '<input type="hidden" name="return" value="" />'; //will be populated by javascript

		echo '<table class="bordered">';
			echo '<tr>';
			echo '<th>';
			echo $langmessage['default'];
			echo '</th>';
			echo '<th>';
			echo '</th>';
			echo '</tr>';
			echo '<tr>';
			echo '<td>';
			echo $default;
			echo '</td>';
			echo '<td>';
			//$value is already escaped using htmlspecialchars()
			echo '<input type="text" name="value" value="'.$value.'" class="gpinput"/>';
			echo '<p>';
			echo ' <input type="submit" name="aaa" value="'.$langmessage['save'].'" class="gpsubmit"/>';
			echo ' <input type="submit" name="cmd" value="'.$langmessage['cancel'].'" class="admin_box_close gpcancel" />';
			echo '</p>';
			echo '</td>';
			echo '</tr>';
		echo '</table>';

		echo '</form>';
		echo '</div>';
	}



	public function SaveText(){
		global $config, $langmessage,$page;

		if( !isset($_POST['key']) ){
			message($langmessage['OOPS'].' (0)');
			return;
		}
		if( !isset($_POST['value']) ){
			message($langmessage['OOPS'].' (1)');
			return;
		}

		$default = $key = $_POST['key'];
		if( isset($langmessage[$key]) ){
			$default = $langmessage[$key];
		}

		$config['customlang'][$key] = $value = htmlspecialchars($_POST['value']);
		if( ($value === $default) || (htmlspecialchars($default) == $value) ){
			unset($config['customlang'][$key]);
		}

		if( \admin_tools::SaveConfig() ){
			message($langmessage['SAVED']);
		}else{
			message($langmessage['OOPS'].' (s1)');
		}
		$this->ReturnHeader();

	}

	public function SetLayoutArray(){
		global $gp_menu, $gp_titles, $gp_index, $config;


		$titleThemes = array();
		$customThemes = array();
		$customThemeLevel = 0;
		$max_level = 5;


		foreach($gp_menu as $id => $info){

			$level = $info['level'];

			//reset theme inheritance
			$max_level = max($max_level,$level);
			for( $i = $level; $i <= $max_level; $i++){
				if( isset($customThemes[$i]) ){
					$customThemes[$i] = false;
				}
			}

			if( !empty($gp_titles[$id]['gpLayout']) ){
				$titleThemes[$id] = $gp_titles[$id]['gpLayout'];
			}else{

				$parent_layout = false;
				$temp_level = $level;
				while( $temp_level >= 0 ){
					if( isset($customThemes[$temp_level]) && ($customThemes[$temp_level] !== false) ){
						$titleThemes[$id] = $parent_layout = $customThemes[$temp_level];
						break;
					}
					$temp_level--;
				}

				if( $parent_layout === false ){
					$titleThemes[$id] = $config['gpLayout'];
				}
			}

			$customThemes[$level] = $titleThemes[$id];
		}


		foreach($gp_index as $title => $id){
			$titleInfo = $gp_titles[$id];

			if( isset($titleThemes[$id]) ){
				continue;
			}

			if( !empty($titleInfo['gpLayout']) ){
				$titleThemes[$id] = $titleInfo['gpLayout'];
			}else{
				$titleThemes[$id] = $config['gpLayout'];
			}

		}


		$this->LayoutArray = $titleThemes;

	}


	/**
	 * Delete a remote theme
	 *
	 */
	public function DeleteTheme(){
		global $langmessage, $dataDir, $gpLayouts, $config;

		$config_before = $config;
		$gpLayoutsBefore = $gpLayouts;
		$theme_folder_name =& $_POST['folder'];
		$theme_folder = $dataDir.'/data/_themes/'.$theme_folder_name;

		if( empty($theme_folder_name) || !ctype_alnum($theme_folder_name) ){
			message($langmessage['OOPS'].' (Invalid Request)');
			return false;
		}

		$order = false;
		if( isset($config['themes'][$theme_folder_name]['order']) ){
			$order = $config['themes'][$theme_folder_name]['order'];
		}

		if( !$this->CanDeleteTheme($theme_folder_name,$message) ){
			message($message);
			return false;
		}

		//remove layouts
		$rm_addon = false;
		foreach($gpLayouts as $layout_id => $layout_info){

			if( !isset($layout_info['is_addon']) || !$layout_info['is_addon'] ){
				continue;
			}

			$layout_folder = dirname($layout_info['theme']);
			if( $layout_folder != $theme_folder_name ){
				continue;
			}

			if( array_key_exists('addon_key',$layout_info) ){
				$rm_addon = $layout_info['addon_key'];
			}

			$this->RmLayoutPrep($layout_id);
			unset($gpLayouts[$layout_id]);
		}


		//remove from settings
		unset($config['themes'][$theme_folder_name]);

		if( $rm_addon ){

			includeFile('admin/admin_addon_installer.php');
			$installer = new admin_addon_installer();
			if( !$installer->Uninstall($rm_addon) ){
				$gpLayouts = $gpLayoutsBefore;
			}
			$installer->OutputMessages();

		}else{

			if( !\admin_tools::SaveAllConfig() ){
				$config = $config_before;
				$gpLayouts = $gpLayoutsBefore;
				message($langmessage['OOPS'].' (s1)');
				return false;
			}

			message($langmessage['SAVED']);
			if( $order ){
				$img_path = \common::IdUrl('ci');
				\common::IdReq($img_path);
			}

		}


		//delete the folder if it hasn't already been deleted by admin_addon_installer
		$dir = $dataDir.'/data/_themes/'.$theme_folder_name;
		if( file_exists($dir) ){
			\gpFiles::RmAll($dir);
		}

	}



	/**
	 * Remote a layout
	 *
	 */
	public function DeleteLayoutConfirmed(){
		global $gpLayouts, $langmessage, $gp_titles;

		$layout =& $_POST['layout'];
		if( !isset($gpLayouts[$layout]) ){
			message($langmessage['OOPS'].' (Layout not set)');
			return false;
		}

		//remove from $gp_titles
		$this->RmLayout($layout);
	}

	/**
	 * Remove a layout from $gp_titles and $gpLayouts
	 *
	 */
	public function RmLayout($layout){
		global $gp_titles, $gpLayouts, $langmessage;

		$gpLayoutsBefore = $gpLayouts;

		$this->RmLayoutPrep($layout);


		//determine if code in /data/_theme should be removed
		$layout_info = $gpLayouts[$layout];
		$rm_addon = false;
		if( isset($layout_info['addon_key']) ){
			$rm_addon = $gpLayouts[$layout]['addon_key'];

			//don't remove if there are other layouts using the same code
			foreach($gpLayouts as $layout_id => $info){
				if( $layout_id == $layout ){
					continue;
				}
				if( !array_key_exists('addon_key',$info) ){
					continue;
				}
				if( $info['addon_key'] == $rm_addon ){
					$rm_addon = false;
				}
			}
		}

		unset($gpLayouts[$layout]);

		//delete and save
		if( $rm_addon ){
			includeFile('admin/admin_addon_installer.php');
			$installer = new admin_addon_installer();
			$installer->rm_folders = false;
			if( !$installer->Uninstall($rm_addon) ){
				$gpLayouts = $gpLayoutsBefore;
			}
			$installer->OutputMessages();

		}elseif( !\admin_tools::SavePagesPHP() ){
			$gpLayouts = $gpLayoutsBefore;
			message($langmessage['OOPS'].' (s1)');
			return false;
		}else{
			message($langmessage['SAVED']);
		}

		//remove custom css
		$this->RemoveCSS($layout);
	}

	public function RmLayoutPrep($layout){
		global $gp_titles;

		//remove from $gp_titles
		foreach($gp_titles as $title => $titleInfo){
			if( isset($titleThemes[$title]) ){
				continue;
			}
			if( empty($titleInfo['gpLayout']) ){
				continue;
			}

			if( $titleInfo['gpLayout'] == $layout ){
				unset($gp_titles[$title]['gpLayout']);
			}
		}
	}


	public function CanDeleteTheme($folder,&$message){
		global $gpLayouts, $config, $langmessage;

		foreach($gpLayouts as $layout_id => $layout){

			if( !isset($layout['is_addon']) || !$layout['is_addon'] ){
				continue;
			}
			$layout_folder = dirname($layout['theme']);
			if( $layout_folder == $folder ){
				if( $config['gpLayout'] == $layout_id ){
					$message = $langmessage['delete_default_layout'];
					return false;
				}
			}
		}
		return true;
	}


	public function LayoutUrl($layout,&$query=''){
		$url = 'Admin_Theme_Content';
		if( $this->layout_request ){
			$url = 'Admin_Theme_Content/Edit/'.rawurlencode($layout);
		}else{
			$query .= '&layout='.rawurlencode($layout);
		}
		return $url;
	}

	public function LayoutLink($layout,$label,$query,$attr){
		$url = $this->LayoutUrl($layout,$query);
		return \common::Link($url,$label,$query,$attr);
	}

}
