<?php

namespace Jpswade\LaravelDatabaseTools\Services;

use DateInterval;
use DateTime;
use Exception;
use PDO;

/**
 * Fixes the "no such function" error by giving SQLite MySQL compatibility by creating the missing function using PDO
 * for SQLite using PHP functions.
 *
 * @see https://raw.githubusercontent.com/aaemnnosttv/wp-sqlite-integration/master/functions.php
 */
class SqliteService
{
    /**
     * Array of public functions, SQL function => PHP function
     */
    private const FUNCTIONS = [
        'month' => 'month',
        'year' => 'year',
        'day' => 'day',
        'unix_timestamp' => 'unix_timestamp',
        'now' => 'now',
        'char_length' => 'char_length',
        'md5' => 'md5',
        'curdate' => 'curdate',
        'rand' => 'rand',
        'substring' => 'substring',
        'dayofmonth' => 'day',
        'second' => 'second',
        'minute' => 'minute',
        'hour' => 'hour',
        'date_format' => 'dateformat',
        'from_unixtime' => 'from_unixtime',
        'date_add' => 'date_add',
        'date_sub' => 'date_sub',
        'adddate' => 'date_add',
        'subdate' => 'date_sub',
        'localtime' => 'now',
        'localtimestamp' => 'now',
        'isnull' => 'isnull',
        'if' => '_if',
        'regexpp' => 'regexp',
        'concat' => 'concat',
        'field' => 'field',
        'log' => 'log',
        'least' => 'least',
        'greatest' => 'greatest',
        'get_lock' => 'get_lock',
        'release_lock' => 'release_lock',
        'ucase' => 'ucase',
        'lcase' => 'lcase',
        'inet_ntoa' => 'inet_ntoa',
        'inet_aton' => 'inet_aton',
        'datediff' => 'datediff',
        'locate' => 'locate',
        'utc_date' => 'utc_date',
        'utc_time' => 'utc_time',
        'utc_timestamp' => 'utc_timestamp',
        'version' => 'version'
    ];

    const MYSQL_COMPATIBILITY_VERSION = '5.6';
    const MYSQL_PHP_DATE_FORMATS = ['%a' => 'D', '%b' => 'M', '%c' => 'n', '%D' => 'jS', '%d' => 'd', '%e' => 'j', '%H' => 'H', '%h' => 'h', '%I' => 'h', '%i' => 'i', '%j' => 'z', '%k' => 'G', '%l' => 'g', '%M' => 'F', '%m' => 'm', '%p' => 'A', '%r' => 'h:i:s A', '%S' => 's', '%s' => 's', '%T' => self::MYSQL_TIME_FORMAT, '%U' => 'W', '%u' => 'W', '%V' => 'W', '%v' => 'W', '%W' => 'l', '%w' => 'w', '%X' => 'Y', '%x' => 'o', '%Y' => 'Y', '%y' => 'y',];
    const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';
    const MYSQL_DATE_FORMAT = 'Y-m-d';
    const MYSQL_TIME_FORMAT = 'H:i:s';

    public function __construct(PDO $pdo)
    {
        foreach (self::FUNCTIONS as $functionName => $function) {
            $pdo->sqliteCreateFunction($functionName, [$this, $function]);
        }
    }

    public function month(string $field): string
    {
        $t = strtotime($field);
        return date('n', $t);
    }

    public function year(string $field): string
    {
        $t = strtotime($field);
        return date('Y', $t);
    }

    public function day($field): string
    {
        $t = strtotime($field);
        return date('j', $t);
    }

    /**
     * Method to return the unix timestamp.
     *
     * Used without an argument, it returns PHP time() function (total seconds passed
     * from '1970-01-01 00:00:00' GMT). Used with the argument, it changes the value
     * to the timestamp.
     *
     * @param ?string $field representing the date formatted as '0000-00-00 00:00:00'.
     * @return int
     */
    public function unix_timestamp(?string $field = null): ?int
    {
        return $field === null ? time() : strtotime($field);
    }

    /**
     * Method to emulate MySQL SECOND() function.
     *
     * @param string representing the time formatted as '00:00:00'.
     * @return int of unsigned integer
     */
    public function second(string $field): int
    {
        $time = strtotime($field);
        return (int)date('s', $time);
    }

    /**
     * Method to emulate MySQL MINUTE() function.
     *
     * @param string $field representing the time formatted as '00:00:00'.
     * @return int of unsigned integer
     */
    public function minute(string $field): int
    {
        $t = strtotime($field);
        return (int)date('i', $t);
    }

    /**
     * Method to emulate MySQL HOUR() function.
     *
     * @param string representing the time formatted as '00:00:00'.
     * @return int
     */
    public function hour($time): int
    {
        list($hours, $minutes, $seconds) = explode(':', $time);
        return (int)$hours;
    }

    /**
     * Method to emulate MySQL FROM_UNIXTIME() function.
     *
     * @param int $field of unix timestamp
     * @param ?string $format to indicate the way of formatting(optional)
     * @return string formatted as '0000-00-00 00:00:00'.
     */
    public function from_unixtime(int $field, ?string $format = null): string
    {
        $date = date(self::MYSQL_DATETIME_FORMAT, $field);
        return $format === null ? $date : $this->dateformat($date, $format);
    }

    /**
     * Method to emulate MySQL DATEFORMAT() function.
     */
    public function dateformat(string $date, string $format): string
    {
        $mysql_php_date_formats = self::MYSQL_PHP_DATE_FORMATS;
        $t = strtotime($date);
        $format = strtr($format, $mysql_php_date_formats);
        return date($format, $t);
    }

    /**
     * Method to emulate MySQL CHAR_LENGTH() function.
     */
    public function char_length(string $field): int
    {
        return strlen($field);
    }

    /**
     * Method to emulate MySQL MD5() function.
     */
    public function md5(string $field): string
    {
        return md5($field);
    }

    /**
     * Method to emulate MySQL RAND() function.
     *
     * SQLite does have a random generator, but it is called RANDOM() and returns random
     * number between -9223372036854775808 and +9223372036854775807. So we substitute it
     * with PHP random generator.
     *
     * This function uses mt_rand() which is four times faster than rand() and returns
     * the random number between 0 and 1.
     */
    public function rand(): int
    {
        return mt_rand(0, 1);
    }

    /**
     * Method to emulate MySQL SUBSTRING() function.
     *
     * This function rewrites the function name to SQLite compatible substr(),
     * which can manipulate UTF-8 characters.
     */
    public function substring(string $text, int $pos, int $len = null): string
    {
        return "substr($text, $pos, $len)";
    }

    /**
     * Method to emulate MySQL DATE_ADD() function.
     *
     * This function adds the time value of $interval expression to $date.
     * $interval is a single quoted strings rewritten by SQLiteQueryDriver::rewrite_query().
     * It is calculated in the private function deriveInterval().
     *
     * @throws Exception
     */
    public function date_add(string $date, string $interval): string
    {
        $interval = $this->deriveInterval($interval);
        switch (strtolower($date)) {
            case 'curdate()':
                $objDate = new Datetime($this->curdate());
                $objDate->add(new DateInterval($interval));
                return $objDate->format(self::MYSQL_DATE_FORMAT);
            case 'now()':
                $objDate = new Datetime($this->now());
                $objDate->add(new DateInterval($interval));
                return $objDate->format(self::MYSQL_DATETIME_FORMAT);
            default:
                $objDate = new Datetime($date);
                $objDate->add(new DateInterval($interval));
                return $objDate->format(self::MYSQL_DATETIME_FORMAT);
        }
    }

    /**
     * Method to calculate the interval time between two dates value.
     * @param string $interval white space separated expression.
     * @return string representing the time to add or subtract.
     */
    private function deriveInterval(string $interval): ?string
    {
        $interval = trim(substr(trim($interval), 8));
        $parts = explode(' ', $interval);
        foreach ($parts as $part) {
            if (!empty($part)) {
                $_parts[] = $part;
            }
        }
        $type = strtolower(end($_parts));
        switch ($type) {
            case 'second':
                $unit = 'S';
                return 'PT' . $_parts[0] . $unit;
            case 'minute':
                $unit = 'M';
                return 'PT' . $_parts[0] . $unit;
            case 'hour':
                $unit = 'H';
                return 'PT' . $_parts[0] . $unit;
            case 'day':
                $unit = 'D';
                return 'P' . $_parts[0] . $unit;
            case 'week':
                $unit = 'W';
                return 'P' . $_parts[0] . $unit;
            case 'month':
                $unit = 'M';
                return 'P' . $_parts[0] . $unit;
            case 'year':
                $unit = 'Y';
                return 'P' . $_parts[0] . $unit;
            case 'minute_second':
                list($minutes, $seconds) = explode(':', $_parts[0]);
                return 'PT' . $minutes . 'M' . $seconds . 'S';
            case 'hour_second':
                list($hours, $minutes, $seconds) = explode(':', $_parts[0]);
                return 'PT' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';
            case 'hour_minute':
                list($hours, $minutes) = explode(':', $_parts[0]);
                return 'PT' . $hours . 'H' . $minutes . 'M';
            case 'day_second':
                $days = intval($_parts[0]);
                list($hours, $minutes, $seconds) = explode(':', $_parts[1]);
                return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M' . $seconds . 'S';
            case 'day_minute':
                $days = intval($_parts[0]);
                list($hours, $minutes) = explode(':', $parts[1]);
                return 'P' . $days . 'D' . 'T' . $hours . 'H' . $minutes . 'M';
            case 'day_hour':
                $days = intval($_parts[0]);
                $hours = intval($_parts[1]);
                return 'P' . $days . 'D' . 'T' . $hours . 'H';
            case 'year_month':
                list($years, $months) = explode('-', $_parts[0]);
                return 'P' . $years . 'Y' . $months . 'M';
        }
        return null;
    }

    /**
     * Method to emulate MySQL CURDATE() function.
     *
     * @return string representing current time formatted as '0000-00-00'.
     */
    public function curdate(): string
    {
        return date(self::MYSQL_DATE_FORMAT);
    }

    /**
     * Method to emulate MySQL NOW() function.
     *
     * @return string representing current time formatted as '0000-00-00 00:00:00'.
     */
    public function now(): string
    {
        return date(self::MYSQL_DATETIME_FORMAT);
    }

    /**
     * Method to emulate MySQL DATE_SUB() function.
     *
     * This function subtracts the time value of $interval expression from $date.
     * $interval is a single quoted strings rewritten by SQLiteQueryDriver::rewrite_query().
     * It is calculated in the private function deriveInterval().
     *
     * @param string $date representing the start date.
     * @param string $interval representing the expression of the time to subtract.
     * @return string date formatted as '0000-00-00 00:00:00'.
     * @throws Exception
     */
    public function date_sub(string $date, string $interval): string
    {
        $interval = $this->deriveInterval($interval);
        switch (strtolower($date)) {
            case 'curdate()':
                $objDate = new Datetime($this->curdate());
                $objDate->sub(new DateInterval($interval));
                return $objDate->format(self::MYSQL_DATE_FORMAT);
            case 'now()':
                $objDate = new Datetime($this->now());
                $objDate->sub(new DateInterval($interval));
                return $objDate->format(self::MYSQL_DATETIME_FORMAT);
            default:
                $objDate = new Datetime($date);
                $objDate->sub(new DateInterval($interval));
                return $objDate->format(self::MYSQL_DATETIME_FORMAT);
        }
    }

    /**
     * Method to emulate MySQL DATE() function.
     *
     * @param string $date formatted as unix time.
     * @return string formatted as '0000-00-00'.
     */
    public function date(string $date): string
    {
        return date(self::MYSQL_DATE_FORMAT, strtotime($date));
    }

    /**
     * Method to emulate MySQL ISNULL() function.
     *
     * This function returns true if the argument is null, and true if not.
     *
     * @param mixed $field
     * @return boolean
     */
    public function isnull($field): bool
    {
        return $field === null;
    }

    /**
     * Method to emulate MySQL IF() function.
     *
     * As 'IF' is a reserved word for PHP, function name must be changed.
     *
     * @param mixed $expression the statement to be evaluated as true or false.
     * @param mixed $true statement or value returned if $expression is true.
     * @param mixed $false statement or value returned if $expression is false.
     * @return mixed
     */
    public function _if($expression, $true, $false)
    {
        return ($expression == true) ? $true : $false;
    }

    /**
     * Method to emulate MySQL REGEXP() function.
     *
     * @param string $field haystack
     * @param string $pattern regular expression to match.
     * @return integer 1 if matched, 0 if not matched.
     */
    public function regexp(string $field, string $pattern, $delimiter = '/'): int
    {
        $pattern = str_replace($delimiter, '\\' . $delimiter, $pattern);
        $pattern = $delimiter . $pattern . $delimiter . 'i';
        return preg_match($pattern, $field);
    }

    /**
     * Method to emulate MySQL CONCAT() function.
     *
     * SQLite does have CONCAT() function, but it has a different syntax from MySQL.
     * So this function must be manipulated here.
     */
    public function concat(...$input): string
    {
        return implode('', $input);
    }

    /**
     * Method to emulate MySQL FIELD() function.
     *
     * Gets the list argument and compares the first item to all the others.
     * If the same value is found, it returns the position of that value. If not, it
     * returns 0.
     */
    public function field(): ?int
    {
        $numArgs = func_num_args();
        if ($numArgs < 2 or func_get_arg(0) === null) {
            return 0;
        } else {
            $arg_list = func_get_args();
        }
        $searchString = array_shift($arg_list);
        for ($i = 0; $i < $numArgs - 1; $i++) {
            if ($searchString === strtolower($arg_list[$i])) {
                return $i + 1;
            }
        }
        return 0;
    }

    /**
     * Method to emulate MySQL LOG() function.
     *
     * Used with one argument, it returns the natural logarithm of X.
     * <code>
     * LOG(X)
     * </code>
     * Used with two arguments, it returns the natural logarithm of X base B.
     * <code>
     * LOG(B, X)
     * </code>
     * In this case, it returns the value of log(X) / log(B).
     *
     * Used without an argument, it returns false. This returned value will be
     * rewritten to 0, because SQLite doesn't understand true/false value.
     *
     * @param int representing the base of the logarithm, which is optional.
     * @param double value to turn into logarithm.
     * @return double|null
     */
    public function log()
    {
        $numArgs = func_num_args();
        if ($numArgs == 1) {
            $arg1 = func_get_arg(0);
            return log($arg1);
        } else if ($numArgs == 2) {
            $arg1 = func_get_arg(0);
            $arg2 = func_get_arg(1);
            return log($arg1) / log($arg2);
        }
        return null;
    }

    /**
     * Method to emulate MySQL LEAST() function.
     *
     * This function rewrites the function name to SQLite compatible function name.
     */
    public function least(): string
    {
        $arg_list = func_get_args();
        $list = implode(',', $arg_list);
        return "min($list)";
    }

    /**
     * Method to emulate MySQL GREATEST() function.
     *
     * This function rewrites the function name to SQLite compatible function name.
     */
    public function greatest(): string
    {
        $arg_list = func_get_args();
        $list = implode(',', $arg_list);
        return "max($list)";
    }

    /**
     * Method to dummy out MySQL GET_LOCK() function.
     *
     * This function is meaningless in SQLite, so we do nothing.
     */
    public function get_lock(string $name, int $timeout): string
    {
        return '1=1';
    }

    /**
     * Method to dummy out MySQL RELEASE_LOCK() function.
     *
     * This function is meaningless in SQLite, so we do nothing.
     */
    public function release_lock(string $name): string
    {
        return '1=1';
    }

    /**
     * Method to emulate MySQL UCASE() function.
     *
     * This is MySQL alias for upper() function. This function rewrites it
     * to SQLite compatible name upper().
     */
    public function ucase(string $string): string
    {
        return "upper($string)";
    }

    /**
     * Method to emulate MySQL LCASE() function.
     *
     *
     * This is MySQL alias for lower() function. This function rewrites it
     * to SQLite compatible name lower().
     *
     * @param string
     * @return string SQLite compatible function name.
     */
    public function lcase($string)
    {
        return "lower($string)";
    }

    /**
     * Method to emulate MySQL INET_NTOA() function.
     *
     * This function gets 4 or 8 bytes int and turn it into the network address.
     */
    public function inet_ntoa($num): string
    {
        return long2ip($num);
    }

    /**
     * Method to emulate MySQL INET_ATON() function.
     *
     * This function gets the network address and turns it into integer.
     */
    public function inet_aton(string $address): int
    {
        $int_data = ip2long($address);
        return sprintf('%u', $int_data);
    }

    /**
     * Method to emulate MySQL DATEDIFF() function.
     *
     * This function compares two dates value and returns the difference.
     * @throws Exception
     */
    public function datediff(string $start, string $end): string
    {
        $start_date = new DateTime($start);
        $end_date = new DateTime($end);
        $interval = $end_date->diff($start_date, false);
        return $interval->format('%r%a');
    }

    /**
     * Method to emulate MySQL LOCATE() function.
     *
     * This function returns the position if $substr is found in $str. If not,
     * it returns 0. If mbstring extension is loaded, mb_strpos() function is
     * used.
     */
    public function locate(string $substr, string $str, int $pos = 0): int
    {
        if (extension_loaded('mbstring')) {
            if (($val = mb_strpos($str, $substr, $pos)) !== false) {
                return $val + 1;
            }
            return 0;
        }
        if (($val = strpos($str, $substr, $pos)) !== false) {
            return $val + 1;
        }
        return 0;
    }

    /**
     * Method to return GMT date in the string format.
     */
    public function utc_date(): string
    {
        return gmdate(self::MYSQL_DATE_FORMAT, time());
    }

    /**
     * Method to return GMT time in the string format.
     */
    public function utc_time(): string
    {
        return gmdate(self::MYSQL_TIME_FORMAT, time());
    }

    /**
     * Method to return GMT time stamp in the string format.
     */
    public function utc_timestamp(): string
    {
        return gmdate(self::MYSQL_DATETIME_FORMAT, time());
    }

    /**
     * Method to return MySQL version.
     *
     * This function only returns the current newest version number of MySQL,
     * because it is meaningless for SQLite database.
     */
    public function version(): string
    {
        return self::MYSQL_COMPATIBILITY_VERSION;
    }
}
