<?php
namespace DTS\eBaySDK\Types;

use \DTS\eBaySDK\Types;
use \DTS\eBaySDK\Exceptions;

class BaseType
{
    protected static $properties = [];
    protected static $xmlNamespaces = [];

    private $values = [];

    public function __construct(array $values = [])
    {
        if (!array_key_exists(__CLASS__, self::$properties)) {
            self::$properties[__CLASS__] = [];
        }

        $this->setValues(__CLASS__, $values);
    }

    public function __get($name)
    {
        return $this->get(get_class($this), $name);
    }

    public function __set($name, $value)
    {
        $this->set(get_class($this), $name, $value);
    }

    public function __isset($name)
    {
        return $this->isPropertySet(get_class($this), $name);
    }

    public function __unset($name)
    {
        $this->unSetProperty(get_class($this), $name);
    }

    public function toXml($elementName, $rootElement = false)
    {
        return sprintf('%s<%s%s%s>%s</%s>', 
            $rootElement ? '<?xml version="1.0" encoding="UTF-8"?>' : '',
            $elementName, 
            $this->attributesToXml(),
            array_key_exists(get_class($this), self::$xmlNamespaces) ? sprintf(' xmlns="%s"', self::$xmlNamespaces[get_class($this)]) : '', 
            $this->propertiesToXml(), 
            $elementName
        );
    }

    protected function setValues($class, array $values = [])
    {
        foreach ($values as $property => $value) {
            $this->set($class, $property, $value);
        }
    }

    private function get($class, $name)
    {
        self::ensurePropertyExists($class, $name);

        return $this->getValue($class, $name);
    }

    private function set($class, $name, $value)
    {
        self::ensurePropertyExists($class, $name);
        self::ensurePropertyType($class, $name, $value);

        $this->setValue($class, $name, $value);
    }

    private function isPropertySet($class, $name)
    {
        self::ensurePropertyExists($class, $name);

        return array_key_exists($name, $this->values);
    }

    private function unSetProperty($class, $name)
    {
        self::ensurePropertyExists($class, $name);

        unset($this->values[$name]);
    }

    private function getValue($class, $name)
    {
        $info = self::propertyInfo($class, $name);

        if ($info['unbound'] && !array_key_exists($name, $this->values)) {
            $this->values[$name] = new Types\UnboundType($class, $name, $info['type']);
        }

        return array_key_exists($name, $this->values) ? $this->values[$name] : null;
    }

    private function setValue($class, $name, $value)
    {
        $info = self::propertyInfo($class, $name);

        if (!$info['unbound']) {
            $this->values[$name] = $value;
        } else {
            $actualType = self::getActualType($value);
            if ('array' !== $actualType) {
                throw new Exceptions\InvalidPropertyTypeException(get_class($this), $name, 'DTS\eBaySDK\Types\UnboundType', $actualType);
            } else {
                $this->values[$name] = new Types\UnboundType(get_class($this), $name, $info['type']);
                foreach ($value as $item) {
                    $this->values[$name][] = $item;
                }
            }
        }
    }

    private function attributesToXml() {
        $attributes = [];

        foreach (self::$properties[get_class($this)] as $name => $info) {
            if(!$info['attribute']) {
                continue;
            }

            if (!array_key_exists($name, $this->values)) {
                continue;
            }

            $attributes[] = self::attributeToXml($info['attributeName'], $this->values[$name]); 
        }

        return join('', $attributes);
    }

    private function propertiesToXml() {
        $properties = [];

        foreach (self::$properties[get_class($this)] as $name => $info) {
            if($info['attribute']) {
                continue;
            }

            if (!array_key_exists($name, $this->values)) {
                continue;
            }

            $value = $this->values[$name];

            if(!array_key_exists('elementName', $info) && !array_key_exists('attributeName', $info)) {
                $properties[] = self::encodeValueXml($value);
            }
            else {
                if ($info['unbound']) {
                    foreach($value as $property) {
                        $properties[] = self::propertyToXml($info['elementName'], $property); 
                    }
                } else {
                    $properties[] = self::propertyToXml($info['elementName'], $value); 
                }
            }
        }

        return join('', $properties);
    }

    private static function ensurePropertyExists($class, $name)
    {
        if (!array_key_exists($name, self::$properties[$class])) {
            throw new Exceptions\UnknownPropertyException(get_called_class(), $name);
        }
    }

    private static function ensurePropertyType($class, $name, $value)
    {
        $info = self::propertyInfo($class, $name);

        $expectedType = $info['type'];
        $actualType = self::getActualType($value);

        if ($expectedType !== $actualType && 'array' !== $actualType) {
            throw new Exceptions\InvalidPropertyTypeException(get_called_class(), $name, $expectedType, $actualType);
        }
    }

    private static function getActualType($value)
    {
        $actualType = gettype($value);

        if ('object' === $actualType) {
            $actualType = get_class($value);
        }

        return $actualType;
    }

    private static function propertyInfo($class, $name)
    {
        return self::$properties[$class][$name];
    }

    protected static function getParentValues(array $properties = [], array $values = [])
    {
      return [
          array_diff_key($values, $properties),
          array_intersect_key($values, $properties)
      ];
    }

    private static function attributeToXml($name, $value)
    {
        return sprintf(' %s="%s"', $name, self::encodeValueXml($value));
    }

    private static function propertyToXml($name, $value)
    {
        if (is_subclass_of($value, '\DTS\eBaySDK\Types\BaseType', false)) {
            return $value->toXml($name);
        } else {
            return sprintf('<%s>%s</%s>', $name, self::encodeValueXml($value), $name);
        }
    }

    private static function encodeValueXml($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d\TG:i:s.000\Z');
        }
        else if (is_bool($value)){
            return $value ? 'true' : 'false';
        } else {
            return $value;
        }
    }
}
