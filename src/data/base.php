<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

class HSEY_USE_DATA_BASE
{
    protected $KEY = '';
    protected $DATA = [];
    public function __construct()
    {
        $this->init();
    }
    public function init()
    {
        $this->onLoad();
    }

    private function onLoad()
    {
        $this->DATA = get_option($this->KEY, []);
    }
    private function onSave()
    {
        update_option($this->KEY, $this->DATA);
    }
    public function get()
    {
        return $this->DATA;
    }
    public function setField($key, $value)
    {
        $this->DATA[$key] = $value;
        $this->onSave();
    }
    public function set($DATA)
    {
        $this->DATA = $DATA;
        $this->onSave();
    }
    public function add($DATA)
    {
        $this->DATA = array_merge(
            $this->DATA,
            $DATA
        );
        $this->onSave();
    }
}
