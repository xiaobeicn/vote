<?php

class Vote {

    private static $_instance = null;
    private $_redis = null;
    private $_sessionid = '';
    private $_lockTime = 86400; // 24h
    private $_pre = 'website:vote:';
    public $_module = '';
    public $_member = '';

    /**
     * eg. Web_Vote::getInstance()->setModule('m')->setSessionid($sid)->setMember('id')->up();
     */
    private function __construct() {
        $this->_redis = (new Redis())->connect('127.0.0.1', 6379)->select(6);
        $this->_sessionid = session_id();
    }

    public static function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function setModule($module) {
        $this->_module = strval($module);
        return $this;
    }

    public function getModule() {
        if (!$this->_module) {
            throw new Exception('module missing, pelese use setModule method.');
        }
        return $this->_module;
    }

    public function setMember($member) {
        $this->_member = strval($member);
        return $this;
    }

    public function getMember() {
        if (!$this->_member) {
            throw new Exception('member missing, pelese use setMember method.');
        }
        return $this->_member;
    }

    public function up() {
        $res = $this->_vote();
        $count = $this->getVoteCount();
        if ($res) {
            return ['code' => 0, 'data' => $count, 'msg' => 'up success'];
        }
        return ['code' => 1, 'data' => $count, 'msg' => 'up fail'];
    }

    public function down() {
        $res = $this->_vote('down');
        $count = $this->getVoteCount();
        if ($res) {
            return ['code' => 0, 'data' => $count, 'msg' => 'down success'];
        }
        return ['code' => 1, 'data' => $count, 'msg' => 'down fail'];
    }

    private function _vote($operation = 'up') {
        $increment = $operation == 'down' ? -1 : 1;
        $lock = $this->_redis->get($this->_getLockKey());
        if ($operation != $lock) {
            $this->_redis->zIncrBy($this->_getZsetKey(), $increment, $this->getMember());
            $this->_redis->setEx($this->_getLockKey(), $this->_lockTime, $operation);
            $logArr = [
                'sessionid' => $this->_sessionid,
                'module' => $this->getModule(),
                'member'    => $this->getMember(),
                'useragent' => $_SERVER['HTTP_USER_AGENT'],
                'referer' => $_SERVER['HTTP_REFERER'],
                'serveraddr' => $_SERVER['SERVER_ADDR'],
                'servername' => $_SERVER['SERVER_NAME'],
                'host' => $_SERVER['HTTP_HOST'],
                'uri' => $_SERVER['REQUEST_URI'],
                'query' => $_SERVER['QUERY_STRING'],
                // 'ip' => getIp(),
                'time' => time()
            ];
            // 记录mongo日志
            
            return true;
        }
        return false;
    }

    public function setSessionid($sid) {
        if ($sid) {
            $this->_sessionid = $sid; // 需要安全过滤
        }
        return $this;
    }
    
    public function getVoteCount() {
        $score = $this->_redis->zScore($this->_getZsetKey(), $this->getMember());
        return ($score !== FALSE && $score > 0) ? $score : '0';
    }
    
    public function getVoteStatus($operation = 'up') {
        $lock = $this->_redis->get($this->_getLockKey());
        return ($operation == $lock) ? TRUE : FALSE;
    }

    private function _getLockKey() {
        return $this->_getZsetKey() . $this->getMember() . $this->_sessionid;
    }

    private function _getZsetKey() {
        return $this->_pre . $this->getModule();
    }

}
