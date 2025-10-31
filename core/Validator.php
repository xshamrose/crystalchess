<?php

/**
 * Form Validation Class
 * Crystal Chess Tournament Booking Platform
 */

class Validator
{
    private $errors = [];
    private $data = [];

    /**
     * Constructor
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Validate required field
     */
    public function required($field, $message = null)
    {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? ucfirst($field) . ' is required';
        }
        return $this;
    }

    /**
     * Validate email
     */
    public function email($field, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = $message ?? 'Invalid email format';
            }
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function min($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be at least $length characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function max($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must not exceed $length characters";
        }
        return $this;
    }

    /**
     * Validate exact length
     */
    public function length($field, $length, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) !== $length) {
            $this->errors[$field] = $message ?? ucfirst($field) . " must be exactly $length characters";
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric($field, $message = null)
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be a number';
        }
        return $this;
    }

    /**
     * Validate integer value
     */
    public function integer($field, $message = null)
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' must be an integer';
        }
        return $this;
    }

    /**
     * Validate minimum value
     */
    public function minValue($field, $min, $message = null)
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            if ($this->data[$field] < $min) {
                $this->errors[$field] = $message ?? ucfirst($field) . " must be at least $min";
            }
        }
        return $this;
    }

    /**
     * Validate maximum value
     */
    public function maxValue($field, $max, $message = null)
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            if ($this->data[$field] > $max) {
                $this->errors[$field] = $message ?? ucfirst($field) . " must not exceed $max";
            }
        }
        return $this;
    }

    /**
     * Validate phone number
     */
    public function phone($field, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9]/', '', $this->data[$field]);
            if (strlen($phone) < 10) {
                $this->errors[$field] = $message ?? 'Invalid phone number';
            }
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function date($field, $format = 'Y-m-d', $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field] = $message ?? 'Invalid date format';
            }
        }
        return $this;
    }

    /**
     * Validate date is in future
     */
    public function futureDate($field, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $date = strtotime($this->data[$field]);
            if ($date <= time()) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' must be a future date';
            }
        }
        return $this;
    }

    /**
     * Validate date is in past
     */
    public function pastDate($field, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $date = strtotime($this->data[$field]);
            if ($date >= time()) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' must be a past date';
            }
        }
        return $this;
    }

   
    public function validate($rules)
    {
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                $params = explode(':', $rule);
                $method = array_shift($params);

                if (method_exists($this, $method)) {
                    $this->$method($field, ...$params);
                } else {
                    $this->addError($field, "Unknown validation rule: $method");
                }
            }
        }

        return $this;
    }

    /**
     * Validate URL
     */
    public function url($field, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->errors[$field] = $message ?? 'Invalid URL format';
            }
        }
        return $this;
    }

    /**
     * Validate field matches another field
     */
    public function match($field, $matchField, $message = null)
    {
        if (isset($this->data[$field]) && isset($this->data[$matchField])) {
            if ($this->data[$field] !== $this->data[$matchField]) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' must match ' . ucfirst($matchField);
            }
        }
        return $this;
    }

    /**
     * Validate field is unique in database
     */
    public function unique($field, $table, $column = null, $excludeId = null, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $column = $column ?? $field;
            $db = new Database();

            $sql = "SELECT COUNT(*) FROM $table WHERE $column = :value";

            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
            }

            $db->query($sql);
            $db->bind(':value', $this->data[$field]);

            if ($excludeId) {
                $db->bind(':exclude_id', $excludeId);
            }

            $count = $db->fetchColumn();

            if ($count > 0) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' already exists';
            }
        }
        return $this;
    }

    /**
     * Validate field exists in database
     */
    public function exists($field, $table, $column = null, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $column = $column ?? $field;
            $db = new Database();

            $db->query("SELECT COUNT(*) FROM $table WHERE $column = :value");
            $db->bind(':value', $this->data[$field]);
            $count = $db->fetchColumn();

            if ($count === 0) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' does not exist';
            }
        }
        return $this;
    }

    /**
     * Validate field is in array of allowed values
     */
    public function in($field, $allowedValues, $message = null)
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowedValues)) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' has an invalid value';
        }
        return $this;
    }

    /**
     * Validate regex pattern
     */
    public function regex($field, $pattern, $message = null)
    {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = $message ?? ucfirst($field) . ' format is invalid';
        }
        return $this;
    }

    /**
     * Validate file upload
     */
    public function file($field, $options = [], $message = null)
    {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES[$field];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errors[$field] = 'File upload failed';
                return $this;
            }

            if (isset($options['max_size']) && $file['size'] > $options['max_size']) {
                $maxMB = $options['max_size'] / 1048576;
                $this->errors[$field] = $message ?? "File size must not exceed {$maxMB}MB";
                return $this;
            }

            if (isset($options['allowed_types'])) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $options['allowed_types'])) {
                    $this->errors[$field] = $message ?? 'Invalid file type';
                    return $this;
                }
            }
        }
        return $this;
    }

    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message = null)
    {
        if (isset($this->data[$field])) {
            if (!call_user_func($callback, $this->data[$field])) {
                $this->errors[$field] = $message ?? ucfirst($field) . ' is invalid';
            }
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails()
    {
        return !empty($this->errors);
    }

    /**
     * Get all errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function firstError()
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    public function error($field)
    {
        return $this->errors[$field] ?? null;
    }

    public function addError($field, $message)
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public static function make($data)
    {
        return new self($data);
    }

    /**
     * ✅ Aliases for backward compatibility
     */
    public function minLength($field, $length, $label = null)
    {
        return $this->min($field, $length, $label);
    }

    public function maxLength($field, $length, $label = null)
    {
        return $this->max($field, $length, $label);
    }

    /**
     * ✅ Password strength validation
     * Requires at least 8 chars, one uppercase, one lowercase, and one number
     */
    public function password($field, $message = null)
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $password = $this->data[$field];
            $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';
            if (!preg_match($pattern, $password)) {
                $this->errors[$field] = $message ??
                    ucfirst($field) . ' must contain at least 8 characters, including uppercase, lowercase, and a number';
            }
        }
        return $this;
    }
}
