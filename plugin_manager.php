<?php
require_once('plugins/eZ/Base/src/base.php');

//TODO - eZ components are loaded from plugin Manager - can we make it only plugin dependent?
// Required method to be able to use the eZ Components
function __autoload( $className ) {
        ezcBase::autoload( $className );
}

class PluginManager {
	
	private $plugins = array();
	private $pluginSequence = array();
	private $logger;
	
	private $settings;
	private $pluginSettings = null;

	function PluginManager() {
        $this->logger =& LoggerManager::getLogger('PluginManager');
	}
	
	function camelize($fileName) {
		$className = str_replace(".plg", "", $fileName);
		$className = str_replace(" ", "", ucwords(str_replace("_", " ", $className)));
		return $className;
	}
	
    public function register($pluginName, $pluginFolder, iPlugin &$plugin, $pluginFolderSettings) {
		$pluginSettingName = strtolower($pluginName);
		$this->logger->debug("Registering Plugin $pluginName from $pluginFolder and loading settings from ($pluginSettingName)");
        $this->plugins[$pluginName] = array("folder" => $pluginFolder, "plugin" => & $plugin);
		$defaultSettings = (array_key_exists('default', $this->settings) ? $this->settings['default'] : array());
		$pluginSettings = (array_key_exists($pluginSettingName, $this->settings) ? $this->settings[$pluginSettingName] : array());
		$folderSettings = (array_key_exists($pluginSettingName, $pluginFolderSettings) ? $pluginFolderSettings[$pluginSettingName] : $pluginFolderSettings);
		$pluginSettings = array_merge($defaultSettings, $pluginSettings);
		$pluginSettings = array_merge($pluginSettings, $folderSettings);
        $plugin->onLoad($pluginSettings);
    }
	
	public function sequencePlugins() {
		foreach ($this->plugins as $name => $plugin) {
		    $order = isset($this->pluginSettings[$name.'.order']) ? $this->pluginSettings[$name.'.order'] : 0;
			$this->pluginSequence[$order] = $name;
		}
		ksort($this->pluginSequence);
		$this->logger->debug("Plugins Sequence ".print_r($this->pluginSequence, true));
	}
	
	public function loadPlugins($fromFolder, &$settings) {
	    
		$this->logger->debug("Loading Plugins from $fromFolder");
		$this->settings = $settings;
		$this->pluginSettings = $settings['plugins'];
		$this->loadPluginsFromFolder($fromFolder);
		$this->sequencePlugins();
	}
	
	public function loadPluginsFromFolder($fromFolder) {
	    
		if ($handle = @opendir($fromFolder)) {
			$pluginFolderSettings = array();
			if (is_file($fromFolder."settings.ini")) {
				$pluginFolderSettings = parse_ini_file($fromFolder.'settings.ini', true);
			}
            while (false !== ($file = readdir($handle))) {
                if (is_file($fromFolder . $file) && strpos($file, ".plg") !== false) {
					$className = $this->camelize($file);
					if (isset($this->pluginSettings[$className.'.enabled']) && $this->pluginSettings[$className.'.enabled']) {
						$this->logger->debug("Loading Plugin $className from $fromFolder");
						if (isset($this->plugins[$className])) {
							$this->logger->debug("Couldn't load Plugin $className from $fromFolder as it is already loaded from ".$this->plugins[$className]['folder']);
						} else {
						    try {
								require($fromFolder. $file);
								if (class_exists($className)) {
									$class = new $className;
								} else {
									$this->logger->debug("Plugin $className not defined in file $fromFolder.$file");
								}
							} catch(Exception $ex) {
								$this->logger->debug($e);
							}
							
							if (!($class instanceof iPlugin)) {
								$this->logger->debug("Couldn't load Plugin $className from $fromFolder as it doesn't implement iPlugin interface");
								unset($class);
							} else {
								$this->register($className, $fromFolder, new $className, $pluginFolderSettings);
							}
						}
					} else {
						$this->logger->debug("Plugin $className disabled");
					}
                }
                else if ((is_dir($fromFolder . $file)) && ($file != '.') && ($file != '..')) {
                    $this->loadPluginsFromFolder($fromFolder . $file . '/');
                }
            }
            closedir($handle);
        } 
	}

    public function executePlugins(&$data) {
		$this->logger->debug("Executing Plugins");
		foreach($this->pluginSequence as $order => $pluginName) {
			if (isset($this->plugins[$pluginName])) {
				$plugin = $this->plugins[$pluginName];
				$this->logger->debug("Executing Plugin ".$pluginName);
				$plugin['plugin']->handleEvent($data);
			}
		}
	}
	
    public function shutdown() {
		$this->logger->debug("Shutting down Plugins");
		foreach ($this->plugins as $name => $plugin) {
		    $plugin['plugin']->onUnload();
			unset($this->plugins[$name]);
		}
		unset($this->plugins);
		unset($this->pluginSequence);
		unset($this->pluginSettings);
		unset($this->logger);
    }
}
?>
