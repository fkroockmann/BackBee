<?php

namespace BackBuilder\Cache\DAO;

/**
 * Entity for DAO stored cache data
 *
 * @category    BackBuilder
 * @package     BackBuilder\Cache\DAO
 * @copyright   Lp digital system
 * @author      c.rouillon
 * @Entity
 * @Table(name="cache")
 */
class Entity
{

    /**
     * The cache id
     * @var string
     * @Id @Column(type="string", name="uid")
     */
    protected $_uid;

    /**
     * A tag associated to the cache
     * @var string
     * @Column(type="string", name="tag")
     */
    protected $_tag;

    /**
     * The data stored
     * @string
     * @Column(type="string", name="data")
     */
    protected $_data;

    /**
     * The expire date time for the stored data
     * @var \DateTime
     * @Column(type="datetime", name="expire")
     */
    protected $_expire;

    /**
     * The creation date time
     * @var \DateTime
     * @Column(type="datetime", name="created")
     */
    protected $_created;

    /**
     * Class constructor
     * @param string $uid Optional, the cache id
     */
    public function __construct($uid = null)
    {
        $this->_uid = $uid;
        $this->_created = new \DateTime();
    }

    /**
     * Sets the cache id
     * @param string $uid
     * @return \BackBuilder\Cache\DAO\Entity
     */
    public function setUid($uid)
    {
        $this->_uid = $uid;
        return $this;
    }

    /**
     * Sets the data to store
     * @param string $data
     * @return \BackBuilder\Cache\DAO\Entity
     */
    public function setData($data)
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * Sets the expire date time
     * @param \DateTime $expire
     * @return \BackBuilder\Cache\DAO\Entity
     */
    public function setExpire(\DateTime $expire = null)
    {
        $this->_expire = ($expire) ? $expire : null;
        return $this;
    }

    /**
     * Set the associated tag
     * @param string $tag
     * @return \BackBuilder\Cache\DAO\Entity
     */
    public function setTag($tag = NULL)
    {
        $this->_tag = $tag;
        return $this;
    }

    /**
     * Returns the cache id
     * @return string
     */
    public function getId()
    {
        return $this->_uid;
    }
    
    /**
     * Returns the stored data
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Returns the data time expiration
     * @return \DateTime
     */
    public function getExpire()
    {
        return $this->_expire;
    }

}