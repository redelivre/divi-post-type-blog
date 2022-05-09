<?php

class DPTB {
	
	public static $modules_slugs = array();
	
	public function __construct() {
		add_action('et_builder_ready', array($this, 'et_builder_ready'), 12);
	}

	function et_builder_ready()
	{
		require_once dirname(__FILE__).'/renders.php';
		$modules = array_filter(glob(dirname(__FILE__).'/*'), 'is_dir');
		foreach ($modules as $module)
		{
			$filename = $module.DIRECTORY_SEPARATOR.basename($module).'.php';
			if(file_exists($filename))
			{
				require_once $filename;
			}
		}
		$this->add_modules_filters();
	}
	
	// Add filters to builder elements
	function add_modules_filters() {
		if (isset($GLOBALS['shortcode_tags'])) {
			foreach($GLOBALS['shortcode_tags'] as $slug => $module){
				if (in_array($slug, self::$modules_slugs) && is_array($module) && array_key_exists(0, $module)) {
					$obj = $module[0];
					if ($obj instanceof ET_Builder_Element) { //check if is divi element
						if(property_exists($obj, 'whitelisted_fields') && is_array($obj->whitelisted_fields)) {
							$obj->whitelisted_fields = apply_filters("et_builder_module_whitelisted_fields_".$slug, $obj->whitelisted_fields);
						}
						if(isset($obj->fields_unprocessed)) {
							//var_dump($slug);
							//print_r($obj->fields_unprocessed);
							$obj->fields_unprocessed = apply_filters("et_builder_module_fields_unprocessed_".$slug, $obj->fields_unprocessed);
						}
						if(isset($obj->fields_defaults)) {
							$obj->fields_defaults = apply_filters("et_builder_module_fields_defaults_".$slug, $obj->fields_defaults);
						}
						$GLOBALS['shortcode_tags'][$slug][0] = $obj;
					}
				}
			}
		}
	}
}
new DPTB();
