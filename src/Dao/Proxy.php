<?php
/**
 * Created by PhpStorm.
 * User: netbrain
 * Date: 10/30/14
 * Time: 8:30 PM
 */

namespace SimphpleOrm\Dao;


interface Proxy {
    public function isInitialized();
    public function initialize();
} 