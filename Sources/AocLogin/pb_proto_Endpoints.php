<?php
include_once 'protocolbuf/message/pb_message.php';

class ProtoEndpoint extends PBMessage
{
    public $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;
    //var $wired_type = 0;
    public $value = "ProtoEndpoint";

    public function __construct($reader = NULL)
    {
        parent::__construct($reader);
        $this->fields["1"] = "PBFixedInt";
        $this->values["1"] = "";
        $this->fields["2"] = "PBInt";
        $this->values["2"] = "";
        $this->fields["3"] = "PBInt";
        $this->values["3"] = "";
    }

    public function type()
    {
        return $this->_get_value("1");
    }

    public function set_type($value)
    {
        return $this->_set_value("1", $value);
    }

    public function instance()
    {
        return $this->_get_value("2");
    }

    public function set_instance($value)
    {
        return $this->_set_value("2", $value);
    }

    public function token()
    {
        return $this->_get_value("3");
    }

    public function set_token($value)
    {
        return $this->_set_value("3", $value);
    }
}

class RpcHeader extends PBMessage
{
    //var $wired_type = 0;
    public $wired_type = PBMessage::WIRED_LENGTH_DELIMITED;

    public function __construct($reader = NULL)
    {
        parent::__construct($reader);
        $this->fields["1"] = "ProtoEndpoint";
        $this->values["1"] = "";
        $this->fields["2"] = "ProtoEndpoint";
        $this->values["2"] = "";
        $this->fields["3"] = "ProtoEndpoint";
        $this->values["3"] = array();
        $this->fields["4"] = "PBInt";
        $this->values["4"] = "";
    }

    public function caller_id()
    {
        return $this->_get_value("1");
    }

    public function set_caller_id($value)
    {
        return $this->_set_value("1", $value);
    }

    public function endpoint_id()
    {
        return $this->_get_value("2");
    }

    public function set_endpoint_id($value)
    {
        return $this->_set_value("2", $value);
    }

    public function full_routing_context($offset)
    {
        return $this->_get_arr_value("3", $offset);
    }

    public function add_full_routing_context()
    {
        return $this->_add_arr_value("3");
    }

    public function set_full_routing_context($index, $value)
    {
        $this->_set_arr_value("3", $index, $value);
    }

    public function remove_last_full_routing_context()
    {
        $this->_remove_last_arr_value("3");
    }

    public function full_routing_context_size()
    {
        return $this->_get_arr_size("3");
    }

    public function method_instance()
    {
        return $this->_get_value("4");
    }

    public function set_method_instance($value)
    {
        return $this->_set_value("4", $value);
    }
}
