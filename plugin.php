<?php
interface iPlugin {
    public function onLoad(&$context);
    public function onUnload();
	public function handleEvent(&$messageDetails);
}

abstract class BasePlugin implements iPlugin {

    public function onLoad(&$context) {
	}
	
    public function onUnload() {
	}
	
	public function handleEvent(&$messageDetails) {
	       $this->handleMessage($messageDetails);
	}
	
	abstract function handleMessage(&$messageDetails);
}

?>