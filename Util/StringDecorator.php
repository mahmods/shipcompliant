<?php
/**
 * Created by PhpStorm.
 * User: fred
 * Date: 7/21/14
 * Time: 5:54 PM
 */

namespace H2\ShipCompliant\Util;

/**
 * A utility wrapper class for basic string handling.
 * Loosely based on java's built in string class
 *
 * Usage:
 *
 * $myString = new StringUtil('The quick brown fox jumped over the lazy dog');
 *
 * echo $myString;  // prints 'The quick brown fox jumped over the lazy dog'
 *
 * echo $myString->length(); // prints 44
 *
 * $myString->contains('quick'); // returns true
 *
 * $myString->startsWith('The'); // returns true
 *
 * $myString->endsWith('The'); // returns false
 *
 */
class StringDecorator {

    private $str;


    /**
     * Constructor initializes class with initial string data
     *
     * @param string $str
     *
     */
    public function __construct($str = "")
    {
        $this->str = $str;
    }


    /**
     * Override base __toString() function
     * @return string contents of internal string data
     */
    public function __toString()
    {
        return $this->str;
    }

    /**
     * Get the length of the internal string
     * @return int of string
     */
    public function length()
    {
        return strlen($this->str);
    }

    /**
     * Equality match string
     *
     * @param mixed $instr string to match
     *
     * @return string
     */
    public function equals($instr)
    {
        return ($this->str === $instr);
    }

    /**
     * Compare internal string to see if it contains $instr
     *
     * @param mixed $instr
     *
     * @return boolean true if internal string contains $instr
     */
    public function contains($instr)
    {
        $pos = strpos($this->str, $instr);
        if ($pos !== false)
        {
            return true;
        }
        return false;
    }

    /**
     * Check to see if internal string starts with $instr
     *
     * @param mixed $instr
     *
     * @return boolean true for match, false for no match
     */
    public function startsWith($instr)
    {
        $pos = strpos($this->str, $instr);
        if ($pos == 0)
        {
            return true;
        }
        return false;
    }

    /**
     * Check to see if internal string ends with $instr
     *
     * @param mixed $instr
     *
     * @return boolean true for match, false for no match
     */
    function endsWith($instr)
    {
        // Get the length of the end string
        $len = strlen($instr);
        // Look at the end of $this->str for the substring the size of $instr
        $tmp = substr($this->str, strlen($this->str) - $len);
        // If it matches, it does end with $instr
        return $tmp == $instr;
    }

    /**
     * Convert internal string to wordpress style "slug"
     * @return string slug
     */
    public function toSlug()
    {
        $slug = str_replace(array('(', ')', '%', '#', '/', '\\n'), '', $this->str);
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', "-", $slug);
        return $slug;
    }

    /**
     * Convert internal string to character array
     * @return array character array
     */
    public function toArray()
    {
        $charArray = array();
        $len       = strlen($this->str);
        for ($i = 0; $i < $len; $i++)
        {
            $charArray[$i] = $this->str[$i];
        }
        return $charArray;
    }

    public function isPostalCode()
    {
        return preg_match("/([0-9]{5}|[0-9]{5}\\-[0-9]{4})/u", $this->str);
    }

    public function splitZipPlusFour()
    {

        if ($this->isPostalCode() && $this->contains('-'))
        {
            $tokens = preg_split('/-/', $this->str);
            return array(
                'zip' => $tokens[0],
                'plus_four' => $tokens[1]
            );
        }
        return array('zip' => $this->str, 'plus_four' => null);
    }

    public function underscoreToCamel() {
        // strip underscores
        $str = str_replace('_', ' ', $this->str);
        // capitalize words
        $str = ucwords($str);
        // trim spaces
        $str = trim(str_replace(' ', '', $str));


        return $str;
    }

    public function underscoreToTitle() {
             // strip underscores
        $str = str_replace('_', ' ', $this->str);
        // capitalize words
        $str = ucwords($str);
        // trim spaces
        $str = trim($str);

        return $str;
    }

}
