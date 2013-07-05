<?php
/**
 * ZnZend
 *
 * @author Zion Ng <zion@intzone.com>
 * @link   http://github.com/zionsg/ZnZend for canonical source repository
 */

namespace ZnZend\Db;

use DateTime;
use Zend\Form\Annotation;
use Zend\Stdlib\ArraySerializableInterface;
use ZnZend\Db\Exception;

/**
 * Base class for entities corresponding to rows in database tables
 *
 * Methods from EntityInterface are implemented for defaults and should
 * be overwritten by concrete classes if required.
 *
 * Getters are preferred over public properties as the latter would likely be named
 * after the actual database columns, which the user should not know about.
 * Also, additional validation/other stuff can be added to the setter/getter without having
 * to get everyone in the world to convert their code from $entity->foo = $x; to $entity->setFoo($x);
 *
 * @Annotation\Name("entity")
 * @Annotation\Hydrator("Zend\Stdlib\Hydrator\ArraySerializable")
 */
abstract class AbstractEntity implements ArraySerializableInterface, EntityInterface
{
    /**
     * NOTE: 5 things to do for each entity property: protected, Annotation, getter, setter, $_mapGettersColumns
     *
     * $id is for example only, and fulfils the 5 things above - getter is getId() and setter is setId().
     * Internal variables should be prefixed with an underscore, eg. $_mapGettersColumns, to differentiate
     * between them and entity properties.
     *
     * @Annotation\Exclude()
     * @var int
     */
    protected $id;

    /**
     * Array mapping getters to columns - to be set by extending class
     *
     * Various parts of this class assume that for a getter getX() or isX(),
     * the corresponding setter will be setX().
     *
     * @Annotation\Exclude()
     * @example array('getId' => 'person_id', 'getFullName' => "CONCAT(person_firstname, ' ', person_lastname)")
     * @var array
     */
    protected static $_mapGettersColumns = array(
        // The mappings below are for the getters defined in EntityInterface
        // and are provided for easy copying when coding extending classes
        'getId'          => 'id',
        'getName'        => 'name',
        'getDescription' => 'description',
        'getThumbnail'   => 'thumbnail',
        'getPriority'    => 'priority',
        'getCreated'     => 'created',
        'getCreator'     => 'creator',
        'getUpdated'     => 'updated',
        'getUpdator'     => 'updator',
        'isHidden'       => 'ishidden',
        'isDeleted'      => 'isdeleted',
    );

    /**
     * Constructor
     *
     * @param array $data Optional array to populate entity
     */
    public function __construct(array $data = array())
    {
        $this->exchangeArray($data);
    }

    /**
     * Value when entity is treated as a string
     *
     * This is vital if a getter such as getCreator() returns an EntityInterface (instead of string)
     * and it is used in log text or in a view script. Should default to getName().
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * Defined by ArraySerializableInterface; Set entity properties from array
     *
     * This uses $_mapGettersColumns - a column must be mapped and have a setter
     * for the corresponding key in $data to be set. In general, for getX() or isX(),
     * the corresponding setter is assumed to be setX().
     * Extending classes should override this if this is not desired.
     *
     * This method is used by \Zend\Stdlib\Hydrator\ArraySerializable::hydrate()
     * typically in forms to populate an object.
     *
     * @param  array $data
     * @return void
     */
    public function exchangeArray(array $data)
    {
        $map = array_flip(self::mapGettersColumns());
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $map)) {
                continue;
            }
            $getter = $map[$key];
            $setter = ('get' === substr($getter, 0, 3))
                    ? substr_replace($getter, 'set', 0, 3)  // getX() becomes setX()
                    : substr_replace($getter, 'set', 0, 2); // isX() becomes setX()
            if (is_callable(array($this, $setter))) {
                $this->$setter($value);
            }
        }
    }

    /**
     * Defined by ArraySerializableInterface; Get entity properties as an array
     *
     * This uses $_mapGettersColumns and calls all the getters to populate the array.
     * All values are cast to string for use in forms and database calls.
     * If the value is DateTime, $value->format('c') is used to return the ISO 8601 timestamp.
     * If the value is an object, $value->__toString() must be defined.
     * Extending classes should override this if this is not desired.
     *
     * This method is used by \Zend\Stdlib\Hydrator\ArraySerializable::extract()
     * typically in forms to extract values from an object.
     *
     * @return array
     */
    public function getArrayCopy()
    {
        $result = array();
        $map = self::mapGettersColumns();
        foreach ($map as $getter => $column) {
            if (!property_exists($this, $column)) {
                continue; // in case the column is an SQL expression
            }
            $value = $this->$getter();
            if ($value instanceof DateTime) {
                $value = $value->format('c');
            }
            $result[$column] = (string) $value;
        }

        return $result;
    }

    /**
     * Defined by EntityInterface; Map getters to column names in table
     *
     * @example array('getId' => 'person_id', 'getFullName' => "CONCAT(person_firstname, ' ', person_lastname)")
     * @return  array
     */
    public static function mapGettersColumns()
    {
        $caller = get_called_class();
        return $caller::$_mapGettersColumns;
    }

    /**
     * Generic internal getter for entity properties
     *
     * @param  null|string $property Optional property to retrieve. If not specified,
     *                               $_mapGettersColumns is checked for the name of the calling
     *                               function to get the mapped property.
     * @param  null|mixed  $default  Optional default value if key or property does not exist
     * @return mixed
     * @internal E_USER_NOTICE is triggered if property does not exist
     */
    protected function get($property = null, $default = null)
    {
        if (null === $property) {
            $trace = debug_backtrace();
            $callerFunction = $trace[1]['function'];
            $map = self::mapGettersColumns();
            if (array_key_exists($callerFunction, $map)) {
                $property = $map[$callerFunction];
            }
        }

        if (property_exists($this, $property)) {
            return $this->$property;
        }

        if (empty($trace)) {
            $trace = debug_backtrace();
        }
        trigger_error(
            sprintf(
                'Undefined property: %s::%s in %s on line %s',
                $trace[1]['class'] . '()',
                $property,
                $trace[0]['file'],
                $trace[0]['line']
            ),
            E_USER_NOTICE
        );

        return $default;
    }

    /**
     * Generic internal setter for entity properties
     *
     * @param  null|mixed  $value    Value to set
     * @param  null|string $type     Optional data type to cast to, if any. No casting is done
     *                               if value is null. If $type is lowercase, cast
     *                               to primitive type, eg. (string) $value, else cast to object,
     *                               eg. new DateTime($value).
     * @param  null|string $property Optional property to set $value to. If not specified,
     *                               $_mapGettersColumns is checked for the corresponding getter
     *                               of the calling function to get the mapped property.
     *                               In general, for setX(), the corresponding getter is either
     *                               getX() or isX().
     * @throws Exception\InvalidArgumentException Property does not exist
     * @return AbstractEntity For fluent interface
     */
    protected function set($value, $type = null, $property = null)
    {
        // Check if property exists
        if (null === $property) {
            $trace = debug_backtrace();
            $callerFunction = $trace[1]['function'];
            $map = self::mapGettersColumns();

            $getFunc = substr_replace($callerFunction, 'get', 0, 3);
            $isFunc = substr_replace($callerFunction, 'is', 0, 3);
            if (array_key_exists($getFunc, $map)) {
                $property = $map[$getFunc];
            } elseif (array_key_exists($isFunc, $map)) {
                $property = $map[$isFunc];
            }
        }

        if (!property_exists($this, $property)) {
            throw new Exception\InvalidArgumentException("Property \"{$property}\" does not exist.");
        }

        // Cast to specified type before setting - skip if value is null or no type specified
        if ($value !== null && $type !== null) {
            if ($type == strtolower($type)) { // primitive type
                settype($value, $type);
            } else { // object
                $value = new $type($value);
            }
        }

        $this->$property = $value;
        return $this;
    }

    /**
     * Defined by EntityInterface; Get record id
     *
     * @return null|int
     */
    public function getId()
    {
        // Alternative: $this->get('id') where 'id' is the column name
        return (int) $this->get();
    }

    /**
     * Defined by EntityInterface; Set record id
     *
     * @param  null|int $value
     * @return AbstractEntity
     */
    public function setId($value)
    {
        return $this->set($value, 'int');
    }

    /**
     * Defined by EntityInterface; Get name
     *
     * @return null|string
     */
    public function getName()
    {
        return $this->get();
    }

    /**
     * Defined by EntityInterface; Set name
     *
     * @param  null|string $value
     * @return AbstractEntity
     */
    public function setName($value)
    {
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Get description
     *
     * @return null|string
     */
    public function getDescription()
    {
        return $this->get();
    }

    /**
     * Defined by EntityInterface; Set description
     *
     * @param  null|string $value
     * @return AbstractEntity
     */
    public function setDescription($value)
    {
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Get filename of thumbnail image for entity
     *
     * @return null|string
     */
    public function getThumbnail()
    {
        return $this->get();
    }

    /**
     * Defined by EntityInterface; Set filename of thumbnail image for entity
     *
     * @param  null|string $value
     * @return AbstractEntity
     */
    public function setThumbnail($value)
    {
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Get priority
     *
     * When listing entities, smaller numbers typically come first.
     *
     * @return null|int
     */
    public function getPriority()
    {
        return (int) $this->get();
    }

    /**
     * Set priority
     *
     * When listing entities, smaller numbers typically come first.
     *
     * @param  null|int $value
     * @return AbstractEntity
     */
    public function setPriority($value)
    {
        return $this->set($value, 'int');
    }

    /**
     * Defined by EntityInterface; Get timestamp when entity was created
     *
     * Return null if value is default DATETIME value of '0000-00-00 00:00:00' in SQL.
     *
     * @return null|DateTime
     */
    public function getCreated()
    {
        $timestamp = $this->get();
        if (false === strtotime($timestamp)) {
            return null;
        }

        return new DateTime($timestamp);
    }

    /**
     * Defined by EntityInterface; Set timestamp when entity was created
     *
     * Set to null if value is default DATETIME value of '0000-00-00 00:00:00' in SQL.
     *
     * @param  null|string|DateTime $value String must be parsable by DateTime
     * @return AbstractEntity
     */
    public function setCreated($value)
    {
        $value = (false === strtotime($value)) ? null : new DateTime($value);
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Get user who created the entity
     *
     * A simple string can be returned (eg. userid) or preferrably, an object
     * which implements EntityInterface.
     *
     * @return null|string|EntityInterface
     */
    public function getCreator()
    {
        return $this->get();
    }

    /**
     * Defined by EntityInterface; Set user who created the entity
     *
     * @param  null|string|EntityInterface $value
     * @return AbstractEntity
     */
    public function setCreator($value)
    {
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Get timestamp when entity was last updated
     *
     * Return null if value is default DATETIME value of '0000-00-00 00:00:00' in SQL.
     *
     * @return null|DateTime
     */
    public function getUpdated()
    {
        $timestamp = $this->get();
        if (false === strtotime($timestamp)) {
            return null;
        }

        return new DateTime($timestamp);
    }

    /**
     * Defined by EntityInterface; Set timestamp when entity was last updated
     *
     * Set to null if value is default DATETIME value of '0000-00-00 00:00:00' in SQL.
     *
     * @param  null|string|DateTime $value String must be parsable by DateTime
     * @return AbstractEntity
     */
    public function setUpdated($value)
    {
        $value = (false === strtotime($value)) ? null : new DateTime($value);
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Get user who last updated the entity
     *
     * A simple string can be returned (eg. userid) or preferrably, an object
     * which implements EntityInterface.
     *
     * @return null|string|EntityInterface
     */
    public function getUpdator()
    {
        return $this->get();
    }

    /**
     * Defined by EntityInterface; Set user who last updated the entity
     *
     * @param  null|string|EntityInterface $value
     * @return AbstractEntity
     */
    public function setUpdator($value)
    {
        return $this->set($value);
    }

    /**
     * Defined by EntityInterface; Check whether entity is marked as hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return (bool) $this->get();
    }

    /**
     * Defined by EntityInterface; Set hidden status of entity
     *
     * @param  bool $value
     * @return AbstractEntity
     */
    public function setHidden($value)
    {
        return $this->set($value, 'bool');
    }

    /**
     * Defined by EntityInterface; Check whether entity is marked as deleted
     *
     * Ideally, no records should ever be deleted from the database and
     * should have a field to mark it as deleted instead.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return (bool) $this->get();
    }

    /**
     * Defined by EntityInterface; Set deleted status of entity
     *
     * @param  bool $value
     * @return AbstractEntity
     */
    public function setDeleted($value)
    {
        return $this->set($value, 'bool');
    }
}
