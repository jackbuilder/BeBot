<?php
/**
 * Reads string input
 */
class PBInputStringReader extends PBInputReader
{
    public $length = 0;

    public function __construct($string)
    {
        parent::__construct();
        $this->string = $string;
        $this->length = strlen($string);
    }

    /**
     * get the next
     *
     * @param boolean $is_string - if set to true only one byte is read
     */
    public function next($is_string = FALSE)
    {
        $package = '';
        while (TRUE) {
            if ($this->pointer >= $this->length) {
                return FALSE;
            }

            $string = '';
            $string = $this->string[$this->pointer];
            $this->pointer++;

            if ($is_string == TRUE) {
                return ord($string);
            }

            $value = decbin(ord($string));

            if ($value >= 10000000 && $is_string == FALSE) {
                // now fill to eight with 00
                $package .= $value;
            } else {
                // now fill to length of eight with 0
                $value = substr('00000000', 0, 8 - strlen($value) % 8) . $value;
                $ret = $this->base128->get_value($package . $value);

                return $ret;
            }
        }
    }
}
