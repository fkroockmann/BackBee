<?php

namespace BackBuilder\Site;

use BackBuilder\Util\Numeric,
    BackBuilder\Services\Local\IJson,
    BackBuilder\Site\Site,
    BackBuilder\Exception\InvalidArgumentException,
    BackBuilder\Security\Acl\Domain\AObjectIdentifiable;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * A website layout
 *
 * If the layout is not associated to a website, it is proposed as layout template
 * to webmasters
 * 
 * The stored data is a serialized standard object. The object must have the
 * folowing structure :
 * 
 * layout: {
 *   templateLayouts: [      // Array of final dropable zones
 *     zone1: {
 *       id:                 // unique identifier of the zone
 *       defaultContainer:   // default AClassContent drop at creation
 *       target:             // array of accepted AClassContent dropable
 *       gridClassPrefix:    // prefix of responsive CSS classes
 *       gridSize:           // size of this zone for responsive CSS
 *     },
 *     ...
 *   ]
 * }
 * 
 * @category    BackBuilder
 * @package     BackBuilder\Site
 * @copyright   Lp digital system
 * @author      c.rouillon <rouillon.charles@gmail.com>
 * @Entity(repositoryClass="BackBuilder\Site\Repository\LayoutRepository")
 * @Table(name="layout")
 * @HasLifecycleCallbacks
 */
class Layout extends AObjectIdentifiable implements IJson
{

    /**
     * The unique identifier.
     * @var string
     * @Id @Column(type="string", name="uid")
     */
    protected $_uid;

    /**
     * The label of this layout.
     * @var string
     * @Column(type="string", name="label", nullable=false)
     */
    protected $_label;

    /**
     * The file name of the layout.
     * @var string
     * @Column(type="string", name="path", nullable=false)
     */
    protected $_path;

    /**
     * The seralized data.
     * @var string
     * @Column(type="text", name="data", nullable=false)
     */
    protected $_data;

    /**
     * The creation datetime
     * @var \DateTime
     * @Column(type="datetime", name="created", nullable=false)
     */
    protected $_created;

    /**
     * The last modification datetime
     * @var \DateTime
     * @Column(type="datetime", name="modified", nullable=false)
     */
    protected $_modified;

    /**
     * The optional path to the layout icon
     * @var string
     * @Column(type="string", name="picpath", nullable=true)
     */
    protected $_picpath;

    /**
     * Optional owner site.
     * @var \BackBuilder\Site\Site
     * @ManyToOne(targetEntity="BackBuilder\Site\Site", inversedBy="_layouts")
     * @JoinColumn(name="site_uid", referencedColumnName="uid")
     */
    protected $_site;

    /**
     * Store pages using this layout.
     * var \Doctrine\Common\Collections\ArrayCollection
     * @OneToMany(targetEntity="BackBuilder\NestedNode\Page", mappedBy="_layout")
     */
    protected $_pages;

    /**
     * The DOM document corresponding to the data
     * @var \DOMDocument
     */
    protected $_domdocument;

    /**
     * Is the layout datas are valid ?
     * @var Boolean
     */
    protected $_isValid;

    /**
     * The final DOM zones on layout.
     * @var array
     */
    protected $_zones;

    /**
     * Class constructor.
     * @param string $uid The unique identifier of the layout
     * @param array $options Initial options for the layout:
     *                         - label      the default label
     *                         - path       the path to the template file
     */
    public function __construct($uid = NULL, $options = NULL)
    {
        $this->_uid = (is_null($uid)) ? md5(uniqid('', TRUE)) : $uid;
        $this->_created = new \DateTime();
        $this->_modified = new \DateTime();

        $this->_pages = new ArrayCollection();

        if (true === is_array($options)) {
            if (true === array_key_exists('label', $options)) {
                $this->setLabel($options['label']);
            }
            if (true === array_key_exists('path', $options)) {
                $this->setPath($options['path']);
            }
        }
    }

    /**
     * Returns the unique identifier.
     * @return string
     */
    public function getUid()
    {
        return $this->_uid;
    }

    /**
     * Returns the label
     * @return string
     */
    public function getLabel()
    {
        return $this->_label;
    }

    /**
     * Returns the file name of the layout.
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Returns the serialized data of the layout.
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Returns the unserialzed object for the layout.
     * @return \StdClass
     */
    public function getDataObject()
    {
        return json_decode($this->getData());
    }

    /**
     * Returns the path to the layout icon if defined, NULL otherwise
     * @return string|NULL
     */
    public function getPicPath()
    {
        return $this->_picpath;
    }

    /**
     * Returns the owner site if defined, NULL otherwise
     * @return \BackBuilder\Site\Site|NULL
     */
    public function getSite()
    {
        return $this->_site;
    }

    /**
     * Return the final zones (ie with contentset) for the layout.
     * @return array|NULL Returns an array of zones or NULL is the layout datas
     *                    are invalid.
     */
    public function getZones()
    {
        if (null === $this->_zones) {
            if (true === $this->isValid()) {
                $this->_zones = array();
                $zonesWithChild = array();

                $zones = $this->getDataObject()->templateLayouts;
                foreach ($zones as $zone) {
                    $zonesWithChild[] = substr($zone->target, 1);
                }

                foreach ($zones as $zone) {
                    if (false === in_array($zone->id, $zonesWithChild)) {
                        if (false === property_exists($zone, 'mainZone')) {
                            $zone->mainZone = false;
                        }

                        if (false === property_exists($zone, 'defaultClassContent')) {
                            $zone->defaultClassContent = null;
                        }

                        $zone->options = $this->_getZoneOptions($zone);

                        array_push($this->_zones, $zone);
                    }
                }
            }
        }

        return $this->_zones;
    }

    /**
     * Returns the zone at the index $index
     * @param int $index
     * @return \StdClass|null
     * @throws InvalidArgumentException
     */
    public function getZone($index)
    {
        if (false === Numeric::isPositiveInteger($index, false)) {
            throw new InvalidArgumentException('Invalid integer value.');
        }

        if (null !== $zones = $this->getZones()) {
            if ($index < count($zones)) {
                return $zones[$index];
            }
        }

        return null;
    }

    /**
     * Generates and returns a DOM document according to the unserialized data object
     * @return \DOMDocument|NULL Returns a DOM document or NULL is the layout datas
     *                           are invalid.
     */
    public function getDomDocument()
    {
        if (null === $this->_domdocument) {
            if (true === $this->isValid()) {
                $mainLayoutRow = new \DOMDocument('1.0', 'UTF-8');
                $mainNode = $mainLayoutRow->createElement('div');
                $mainNode->setAttribute('class', 'row');

                $clearNode = $mainLayoutRow->createElement('div');
                $clearNode->setAttribute('class', 'clear');

                $mainId = '';
                $zones = array();
                foreach ($this->getDataObject()->templateLayouts as $zone) {
                    $mainId = $zone->defaultContainer;
                    $class = $zone->gridClassPrefix . $zone->gridSize;

                    if (true === property_exists($zone, 'alphaClass')) {
                        $class .= ' ' . $zone->alphaClass;
                    }

                    if (true === property_exists($zone, 'omegaClass')) {
                        $class .= ' ' . $zone->omegaClass;
                    }

                    if (true === property_exists($zone, 'typeClass')) {
                        $class .= ' ' . $zone->typeClass;
                    }

                    $zoneNode = $mainLayoutRow->createElement('div');
                    $zoneNode->setAttribute('class', trim($class));
                    $zones['#' . $zone->id] = $zoneNode;

                    $parentNode = isset($zones[$zone->target]) ? $zones[$zone->target] : $mainNode;
                    $parentNode->appendChild($zoneNode);
                    if (true === property_exists($zone, 'clearAfter')
                            && 1 == $zone->clearAfter) {
                        $parentNode->appendChild(clone $clearNode);
                    }
                }

                $mainNode->setAttribute('id', substr($mainId, 1));
                $mainLayoutRow->appendChild($mainNode);

                $this->_domdocument = $mainLayoutRow;
            }
        }

        return $this->_domdocument;
    }

    /**
     * Checks for a valid structure of the unserialized data object.
     * @return Boolean Returns TRUE if the data object is valid, FALSE otherwise
     */
    public function isValid()
    {
        if (null === $this->_isValid) {
            $this->_isValid = false;

            if (null !== $data_object = $this->getDataObject()) {
                if (true === property_exists($data_object, 'templateLayouts')
                        && true === is_array($data_object->templateLayouts)
                        && 0 < count($data_object->templateLayouts)) {

                    $this->_isValid = true;

                    foreach ($data_object->templateLayouts as $zone) {
                        if (false === property_exists($zone, 'id')
                                || false === property_exists($zone, 'defaultContainer')
                                || false === property_exists($zone, 'target')
                                || false === property_exists($zone, 'gridClassPrefix')
                                || false === property_exists($zone, 'gridSize')) {
                            $this->_isValid = false;
                            break;
                        }
                    }
                }
            }
        }

        return $this->_isValid;
    }

    /**
     * Sets the label.
     * @param string $label
     * @return \BackBuilder\Site\Layout
     */
    public function setLabel($label)
    {
        $this->_label = $label;
        return $this;
    }

    /**
     * Set the filename of the layout
     * @param string $path
     * @return \BackBuilder\Site\Layout
     */
    public function setPath($path)
    {
        $this->_path = $path;
        return $this;
    }

    /**
     * Sets the data associated to the layout.
     * None validity checks are performed at this step.
     * @param mixed $data
     * @return \BackBuilder\Site\Layout
     */
    public function setData($data)
    {
        if (true === is_object($data)) {
            return $this->setDataObject($data);
        }

        $this->_picpath = null;
        $this->_isValid = null;
        $this->_domdocument = null;
        $this->_zones = null;

        $this->_data = $data;

        return $this;
    }

    /**
     * Sets the data associated to the layout.
     * None validity checks are performed at this step.
     * @param mixed $data
     * @return \BackBuilder\Site\Layout
     */
    public function setDataObject($data)
    {
        if (true === is_object($data)) {
            $data = json_encode($data);
        }

        return $this->setData($data);
    }

    /**
     * Sets the path to the layout icon.
     * @param string $picpath
     * @return \BackBuilder\Site\Layout
     */
    public function setPicPath($picpath)
    {
        $this->_picpath = $picpath;
        return $this;
    }

    /**
     * Associates this layout to a website.
     * @param \BackBuilder\Site\Site $site
     * @return \BackBuilder\Site\Layout
     */
    public function setSite(Site $site)
    {
        $this->_site = $site;
        return $this;
    }

    /**
     * @see BackBuilder\Services\Local\IJson::__toJson()
     */
    public function __toJson()
    {
        $result = new \stdClass();
        $result->templateLayouts = $this->getDataObject()->templateLayouts;
        $result->templateTitle = $this->getLabel();
        $result->path = $this->getPath();
        $result->picpath = $this->getPicPath();
        $result->uid = $this->getUid();
        $result->site = ($this->getSite() !== null) ? $this->getSite()->__toJson() : null;

        return $result;
    }

    /**
     * Returns a contentset options according to the layout zone
     * @param \StdClass $zone
     * @return array
     */
    private function _getZoneOptions(\StdClass $zone)
    {
        $options = array(
            'parameters' => array(
                'class' => array(
                    'type' => 'scalar',
                    'options' => array('default' => 'row')
                )
            )
        );

        if (true === property_exists($zone, 'accept')
                && true === is_array($zone->accept)
                && 0 < count($zone->accept)
                && $zone->accept[0] != '') {

            $options['accept'] = $zone->accept;

            $func = function (&$item, $key) {
                        $item = ('' == $item) ? null : 'BackBuilder\ClassContent\\' . $item;
                    };

            array_walk($options['accept'], $func);
        }

        if (true === property_exists($zone, 'maxentry') && 0 < $zone->maxentry) {
            $options['maxentry'] = $zone->maxentry;
        }

        return $options;
    }

}