<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\ClassDefinition\Data;

use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Geo\AbstractGeo;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Normalizer\NormalizerInterface;
use Pimcore\Tool\Serialize;

class Geopolygon extends AbstractGeo implements ResourcePersistenceAwareInterface, QueryResourcePersistenceAwareInterface, EqualComparisonInterface, VarExporterInterface, NormalizerInterface
{
    use Extension\ColumnType;
    use Extension\QueryColumnType;

    /**
     * Static type of this element
     *
     * @var string
     */
    public $fieldtype = 'geopolygon';

    /**
     * Type for the column to query
     *
     * @var string
     */
    public $queryColumnType = 'longtext';

    /**
     * Type for the column
     *
     * @var string
     */
    public $columnType = 'longtext';

    /**
     * @see ResourcePersistenceAwareInterface::getDataForResource
     *
     * @param string $data
     * @param null|DataObject\Concrete $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataForResource($data, $object = null, $params = [])
    {
        return Serialize::serialize($data);
    }

    /**
     * Checks if data is valid for current data field
     *
     * @param mixed $data
     * @param bool $omitMandatoryCheck
     *
     * @throws \Exception
     */
    public function checkValidity($data, $omitMandatoryCheck = false)
    {
        $isEmpty = true;

        if ($data) {
            $valid = true;

            if (!is_array($data)) {
                $valid = false;
            } else {
                foreach ($data as $point) {
                    if (!$point instanceof DataObject\Data\GeoCoordinates) {
                        $valid = false;
                        break;
                    }
                }
            }

            if (!$valid) {
                throw new ValidationException('Expected an array of Geopoint');
            }

            $isEmpty = false;
        }

        if (!$omitMandatoryCheck && $this->getMandatory() && $isEmpty) {
            throw new ValidationException('Empty mandatory field [ ' . $this->getName() . ' ]');
        }
    }

    /**
     * @see ResourcePersistenceAwareInterface::getDataFromResource
     *
     * @param string $data
     * @param null|DataObject\Concrete $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataFromResource($data, $object = null, $params = [])
    {
        return Serialize::unserialize($data);
    }

    /**
     * @see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     *
     * @param string $data
     * @param null|DataObject\Concrete $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataForQueryResource($data, $object = null, $params = [])
    {
        return $this->getDataForResource($data, $object, $params);
    }

    /**
     * @see Data::getDataForEditmode
     *
     * @param string $data
     * @param null|DataObject\Concrete $object
     * @param mixed $params
     *
     * @return array|null
     */
    public function getDataForEditmode($data, $object = null, $params = [])
    {
        if (!empty($data)) {
            if (is_array($data)) {
                $points = [];
                foreach ($data as $point) {
                    $points[] = [
                        'latitude' => $point->getLatitude(),
                        'longitude' => $point->getLongitude(),
                    ];
                }

                return $points;
            }
        }

        return null;
    }

    /**
     * @see Data::getDataFromEditmode
     *
     * @param string $data
     * @param null|DataObject\Concrete $object
     * @param mixed $params
     *
     * @return DataObject\Data\GeoCoordinates[]|null
     */
    public function getDataFromEditmode($data, $object = null, $params = [])
    {
        if (is_array($data)) {
            $points = [];
            foreach ($data as $point) {
                $points[] = new DataObject\Data\GeoCoordinates($point['latitude'], $point['longitude']);
            }

            return $points;
        }

        return null;
    }

    /**
     * @see Data::getVersionPreview
     *
     * @param DataObject\Data\GeoCoordinates[]|null $data
     * @param null|DataObject\Concrete $object
     * @param mixed $params
     *
     * @return string
     */
    public function getVersionPreview($data, $object = null, $params = [])
    {
        return $this->getDiffVersionPreview($data, $object, $params);
    }

    /**
     * converts object data to a simple string value or CSV Export
     *
     * @internal
     *
     * @param DataObject\Concrete $object
     * @param array $params
     *
     * @return string
     */
    public function getForCsvExport($object, $params = [])
    {
        $data = $this->getDataFromObjectParam($object, $params);
        if (!empty($data)) {
            $dataArray = $this->getDataForEditmode($data, $object, $params);
            $rows = [];
            if (is_array($dataArray)) {
                foreach ($dataArray as $point) {
                    $rows[] = implode(';', $point);
                }

                return implode('|', $rows);
            }
        }

        return '';
    }

    /**
     * @param DataObject\Concrete|DataObject\Objectbrick\Data\AbstractData|DataObject\Fieldcollection\Data\AbstractData $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDataForSearchIndex($object, $params = [])
    {
        return '';
    }

    /** True if change is allowed in edit mode.
     * @param DataObject\Concrete $object
     * @param mixed $params
     *
     * @return bool
     */
    public function isDiffChangeAllowed($object, $params = [])
    {
        return true;
    }

    /** Generates a pretty version preview (similar to getVersionPreview) can be either html or
     * a image URL. See the https://github.com/pimcore/object-merger bundle documentation for details
     *
     * @param array|null $data
     * @param DataObject\Concrete|null $object
     * @param mixed $params
     *
     * @return string
     */
    public function getDiffVersionPreview($data, $object = null, $params = [])
    {
        $line = [];

        if (is_array($data)) {
            foreach ($data as $point) {
                $line[] = $point->getLatitude() . ',' . $point->getLongitude();
            }
        }

        return implode(' ', $line);
    }

    /** Encode value for packing it into a single column.
     *
     * @deprecated marshal is deprecated and will be removed in Pimcore 10. Use normalize instead.
     *
     * @param mixed $value
     * @param DataObject\Concrete $object
     * @param mixed $params
     *
     * @return mixed
     */
    public function marshal($value, $object = null, $params = [])
    {
        if ($value) {
            $value = Serialize::unserialize($value);
            $result = [];
            if (is_array($value)) {
                /** @var DataObject\Data\GeoCoordinates $point */
                foreach ($value as $point) {
                    $result[] = [
                            $point->getLatitude(),
                            $point->getLongitude(),
                        ];
                }
            }

            return [
                'value' => json_encode($result),
            ];
        }
    }

    /** See marshal
     *
     * @deprecated unmarshal is deprecated and will be removed in Pimcore 10. Use denormalize instead.
     *
     * @param mixed $value
     * @param DataObject\Concrete $object
     * @param mixed $params
     *
     * @return mixed|null
     */
    public function unmarshal($value, $object = null, $params = [])
    {
        if (isset($value['value'])) {
            $value = json_decode($value['value']);
            $result = [];
            if (is_array($value)) {
                foreach ($value as $point) {
                    $result[] = new DataObject\Data\GeoCoordinates($point[0], $point[1]);
                }
            }

            return $result;
        }

        return null;
    }

    /**
     *
     * @param DataObject\Data\GeoCoordinates[]|null $oldValue
     * @param DataObject\Data\GeoCoordinates[]|null $newValue
     *
     * @return bool
     */
    public function isEqual($oldValue, $newValue): bool
    {
        if ($oldValue === null && $newValue === null) {
            return true;
        }

        if (!is_array($oldValue) || !is_array($newValue)
        || count($oldValue) != count($newValue)) {
            return false;
        }

        $fd = new Geopoint();

        $oldValue = array_values($oldValue);
        $newValue = array_values($newValue);

        foreach ($oldValue as $p => $point) {
            if (!$fd->isEqual($point, $newValue[$p])) {
                return false;
            }
        }

        return true;
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '?array';
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return '?array';
    }

    public function getPhpdocInputType(): ?string
    {
        return 'array|null';
    }

    public function getPhpdocReturnType(): ?string
    {
        return 'array|null';
    }

    /**
     * { @inheritdoc }
     */
    public function normalize($value, $params = [])
    {
        if (is_array($value)) {
            $points = [];
            $fd = new Geopoint();
            foreach ($value as $p) {
                $points[] = $fd->normalize($p);
            }

            return $points;

        }
        return null;
    }

    /**
     * { @inheritdoc }
     */
    public function denormalize($value, $params = [])
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $point) {
                $result[] = new DataObject\Data\GeoCoordinates($point['latitude'], $point['longitude']);
            }
            return $result;
        }
        return null;

    }
}
