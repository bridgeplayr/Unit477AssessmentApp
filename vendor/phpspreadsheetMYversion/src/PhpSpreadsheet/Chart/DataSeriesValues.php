<?php

namespace PhpOffice\PhpSpreadsheet\Chart;

use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Calculation\Functions;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataSeriesValues
{
    const DATASERIES_TYPE_STRING = 'String';
    const DATASERIES_TYPE_NUMBER = 'Number';

    private static $dataTypeValues = [
        self::DATASERIES_TYPE_STRING,
        self::DATASERIES_TYPE_NUMBER,
    ];

		// added RG

		/**
		 * trendLine: similar to Axis plus addtional properties 'type', 
		 * 'showRSqr', 'showEq', 'order' and 'period'
		 *
     * @var mixed[]
		 *
		 * for special-effect properties like 'style', 'shadow', 'glow', and 'soft edges' 
		 * use get/setTrendLinexxxxxProperties
     */
		private $trendLine = [
				'type' 		 => TRENDLINE_TYPE_LINEAR,
				'showRSqr' => FALSE,
				'showEq' 	 => FALSE,
				'order'  	 => 2, // for type=poly
				'period' 	 => 2, // for type=movingAvg

				'colortype' => self::EXCEL_COLOR_TYPE_ARGB,
        'value' => null,
				'alpha' => 0,

				'width' => '15875',
        'compound' => self::LINE_STYLE_COMPOUND_SIMPLE,
        'dash' => self::LINE_STYLE_DASH_SOLID,
        'cap' => self::LINE_STYLE_CAP_FLAT,
        'join' => self::LINE_STYLE_JOIN_BEVEL,
        'arrow' => [
            'head' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_5,
            ],
            'end' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_8,
            ],
        ],
		];
		
		private $trendlineStyleProperties = [
        'width' => '9525',
        'compound' => self::LINE_STYLE_COMPOUND_SIMPLE,
        'dash' => self::LINE_STYLE_DASH_SOLID,
        'cap' => self::LINE_STYLE_CAP_FLAT,
        'join' => self::LINE_STYLE_JOIN_BEVEL,
        'arrow' => [
            'head' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_5,
            ],
            'end' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_8,
            ],
        ],
    ];

    /**
     * Shadow Properties.
     *
     * @var mixed[]
     */
    private $trendLineShadowProperties = [
        'presets' => self::SHADOW_PRESETS_NOSHADOW,
        'effect' => null,
        'color' => [
            'type' => self::EXCEL_COLOR_TYPE_STANDARD,
            'value' => 'black',
            'alpha' => 40,
        ],
        'size' => [
            'sx' => null,
            'sy' => null,
            'kx' => null,
        ],
        'blur' => null,
        'direction' => null,
        'distance' => null,
        'algn' => null,
        'rotWithShape' => null,
    ];

    /**
     * Glow Properties.
     *
     * @var mixed[]
     */
    private $trendLineGlowProperties = [
        'size' => null,
        'color' => [
            'type' => self::EXCEL_COLOR_TYPE_STANDARD,
            'value' => 'black',
            'alpha' => 40,
        ],
    ];

    /**
     * Trendline Soft Edge Properties.
     *
     * @var mixed[]
     */
    private $trendLineSoftEdges = [
        'size' => null,
    ];

		/**
		 * scatterLine: similar to Axis object, plus addtional properties 'connected' 
		 * and 'smooth'
		 *
     * @var mixed
		 * use get/setScatterLinexxxxxProperties & 
		 * other special-effect properties like 'style', 'shadow', 'glow', and 'soft edges' 
     */

		private $scatterLine = [
				'connected'	=> TRUE,
				'smooth'		=> FALSE,

				'colortype' => self::EXCEL_COLOR_TYPE_ARGB,
        'value' => null,
				'alpha' => 0,

				'width' => '15875',
        'compound' => self::LINE_STYLE_COMPOUND_SIMPLE,
        'dash' => self::LINE_STYLE_DASH_SOLID,
        'cap' => self::LINE_STYLE_CAP_FLAT,
        'join' => self::LINE_STYLE_JOIN_BEVEL,
        'arrow' => [
            'head' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_5,
            ],
            'end' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_8,
            ],
        ],
		] ; 

		private $scatterlineStyleProperties = [
        'width' => '9525',
        'compound' => self::LINE_STYLE_COMPOUND_SIMPLE,
        'dash' => self::LINE_STYLE_DASH_SOLID,
        'cap' => self::LINE_STYLE_CAP_FLAT,
        'join' => self::LINE_STYLE_JOIN_BEVEL,
        'arrow' => [
            'head' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_5,
            ],
            'end' => [
                'type' => self::LINE_STYLE_ARROW_TYPE_NOARROW,
                'size' => self::LINE_STYLE_ARROW_SIZE_8,
            ],
        ],
    ];

    /**
     * Shadow Properties.
     *
     * @var mixed[]
     */
    private $scatterLineShadowProperties = [
        'presets' => self::SHADOW_PRESETS_NOSHADOW,
        'effect' => null,
        'color' => [
            'type' => self::EXCEL_COLOR_TYPE_STANDARD,
            'value' => 'black',
            'alpha' => 40,
        ],
        'size' => [
            'sx' => null,
            'sy' => null,
            'kx' => null,
        ],
        'blur' => null,
        'direction' => null,
        'distance' => null,
        'algn' => null,
        'rotWithShape' => null,
    ];

    /**
     * Glow Properties.
     *
     * @var mixed[]
     */
    private $scatterLineGlowProperties = [
        'size' => null,
        'color' => [
            'type' => self::EXCEL_COLOR_TYPE_STANDARD,
            'value' => 'black',
            'alpha' => 40,
        ],
    ];

    /**
     * ScatterLine Soft Edge Properties.
     *
     * @var mixed[]
     */
    private $scatterLineSoftEdges = [
        'size' => null,
    ];

    /**
     * Series Data Type.
     *
     * @var string
     */
    private $dataType;

    /**
     * Series Data Source.
     *
     * @var string
     */
    private $dataSource;

    /**
     * Format Code.
     *
     * @var string
     */
    private $formatCode;

    /**
     * Series Point Marker.
     *
     * @var string
     */
    private $pointMarker;

		/* added RG as per suggestions from Github user GajowyJ */
		/**
     * Series Point Size.
     *
     * @var int
     */
    private $pointSize = 4 ;

    /**
     * Point Count (The number of datapoints in the dataseries).
     *
     * @var int
     */
    private $pointCount = 0;

    /**
     * Data Values.
     *
     * @var mixed[]
     */
    private $dataValues = [];

    /**
     * Fill and/or Border marker color (can be array with string or 
     * 6 char hex value colors if dataseries have custom colors).
     *
     * @var string|string[]|int|int[]
     */
    private $fillColor;

    /**
     * Line Width.
     *
     * @var int
     */
    private $lineWidth = 12700;

    /**
     * Create a new DataSeriesValues object.
     *
     * @param string $dataType
     * @param string $dataSource
     * @param null|mixed $formatCode
     * @param int $pointCount
     * @param mixed $dataValues
     * @param null|mixed $marker
     * @param null|string|string[] $fillColor
     */
    public function __construct($dataType = self::DATASERIES_TYPE_NUMBER, $dataSource = null, $formatCode = null, $pointCount = 0, $dataValues = [], $marker = null, $fillColor = null)
    {
        $this->setDataType($dataType);
        $this->dataSource = $dataSource;
        $this->formatCode = $formatCode;
        $this->pointCount = $pointCount;
        $this->dataValues = $dataValues;
        $this->pointMarker = $marker;
        $this->fillColor = $fillColor;
    }

    /**
     * Get Series Data Type.
     *
     * @return string
     */
    public function getDataType()
    {
        return $this->dataType;
    }

    /**
     * Set Series Data Type.
     *
     * @param string $dataType Datatype of this data series
     *                                Typical values are:
     *                                    DataSeriesValues::DATASERIES_TYPE_STRING
     *                                        Normally used for axis point values
     *                                    DataSeriesValues::DATASERIES_TYPE_NUMBER
     *                                        Normally used for chart data values
     *
     * @return $this
     */
    public function setDataType($dataType)
    {
        if (!in_array($dataType, self::$dataTypeValues)) {
            throw new Exception('Invalid datatype for chart data series values');
        }
        $this->dataType = $dataType;

        return $this;
    }

    /**
     * Get Series Data Source (formula).
     *
     * @return string
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * Set Series Data Source (formula).
     *
     * @param string $dataSource
     *
     * @return $this
     */
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;

        return $this;
    }

		/* added RG as per suggestions from Github user GajowyJ */
    /**
     * Get Point size.
     *
     * @return int
     */
    public function getPointSize()
    {
        return $this->pointSize;
    }

		/* added RG as per suggestions from Github user GajowyJ */
    /**
     * Set Point size.
     *
     * @param int $size
     *
     * @return $this
     */
    public function setPointSize($size)
    {
        $this->pointSize = $size;

        return $this;
    }

    /**
     * Get Point Marker.
     *
     * @return string
     */
    public function getPointMarker()
    {
        return $this->pointMarker;
    }

    /**
     * Set Point Marker.
     *
     * @param string $marker
     *
     * @return $this
     */
    public function setPointMarker($marker)
    {
        $this->pointMarker = $marker;

        return $this;
    }

    /**
     * Get Series Format Code.
     *
     * @return string
     */
    public function getFormatCode()
    {
        return $this->formatCode;
    }

    /**
     * Set Series Format Code.
     *
     * @param string $formatCode
     *
     * @return $this
     */
    public function setFormatCode($formatCode)
    {
        $this->formatCode = $formatCode;

        return $this;
    }

    /**
     * Get Series Point Count.
     *
     * @return int
     */
    public function getPointCount()
    {
        return $this->pointCount;
    }

    /**
     * Get fill color.
     *
     * @return string|string[] HEX color or array with HEX colors
     */
    public function getFillColor()
    {
        return $this->fillColor;
    }

    /**
     * Set fill color for series.
     *
     * @param string|string[] $color HEX color or array with HEX colors
     *
     * @return   DataSeriesValues
     */
    public function setFillColor($color)
    {
        if (is_array($color)) {
            foreach ($color as $colorValue) {
                $this->validateColor($colorValue);
            }
        } else {
            $this->validateColor($color);
        }
        $this->fillColor = $color;

        return $this;
    }

    /**
     * Method for validating hex color.
     *
     * @param string $color value for color
     *
     * @return bool true if validation was successful
     */
    private function validateColor($color)
    {
        if (!preg_match('/^[a-f0-9]{6}$/i', $color)) {
            throw new Exception(sprintf('Invalid hex color for chart series (color: "%s")', $color));
        }

        return true;
    }

    /**
     * Get line width for series.
     *
     * @return int
     */
    public function getLineWidth()
    {
        return $this->lineWidth;
    }

    /**
     * Set line width for the series.
     *
     * @param int $width
     *
     * @return $this
     */
    public function setLineWidth($width)
    {
        $minWidth = 12700;
        $this->lineWidth = max($minWidth, $width);

        return $this;
    }

    /**
     * Identify if the Data Series is a multi-level or a simple series.
     *
     * @return null|bool
     */
    public function isMultiLevelSeries()
    {
        if (count($this->dataValues) > 0) {
            return is_array(array_values($this->dataValues)[0]);
        }

        return null;
    }

    /**
     * Return the level count of a multi-level Data Series.
     *
     * @return int
     */
    public function multiLevelCount()
    {
        $levelCount = 0;
        foreach ($this->dataValues as $dataValueSet) {
            $levelCount = max($levelCount, count($dataValueSet));
        }

        return $levelCount;
    }

    /**
     * Get Series Data Values.
     *
     * @return mixed[]
     */
    public function getDataValues()
    {
        return $this->dataValues;
    }

    /**
     * Get the first Series Data value.
     *
     * @return mixed
     */
    public function getDataValue()
    {
        $count = count($this->dataValues);
        if ($count == 0) {
            return null;
        } elseif ($count == 1) {
            return $this->dataValues[0];
        }

        return $this->dataValues;
    }

    /**
     * Set Series Data Values.
     *
     * @param array $dataValues
     *
     * @return $this
     */
    public function setDataValues($dataValues)
    {
        $this->dataValues = Functions::flattenArray($dataValues);
        $this->pointCount = count($dataValues);

        return $this;
    }

    public function refresh(Worksheet $worksheet, $flatten = true): void
    {
        if ($this->dataSource !== null) {
            $calcEngine = Calculation::getInstance($worksheet->getParent());
            $newDataValues = Calculation::unwrapResult(
                $calcEngine->_calculateFormulaValue(
                    '=' . $this->dataSource,
                    null,
                    $worksheet->getCell('A1')
                )
            );
            if ($flatten) {
                $this->dataValues = Functions::flattenArray($newDataValues);
                foreach ($this->dataValues as &$dataValue) {
                    if (is_string($dataValue) && !empty($dataValue) && $dataValue[0] == '#') {
                        $dataValue = 0.0;
                    }
                }
                unset($dataValue);
            } else {
                [$worksheet, $cellRange] = Worksheet::extractSheetTitle($this->dataSource, true);
                $dimensions = Coordinate::rangeDimension(str_replace('$', '', $cellRange));
                if (($dimensions[0] == 1) || ($dimensions[1] == 1)) {
                    $this->dataValues = Functions::flattenArray($newDataValues);
                } else {
                    $newArray = array_values(array_shift($newDataValues));
                    foreach ($newArray as $i => $newDataSet) {
                        $newArray[$i] = [$newDataSet];
                    }

                    foreach ($newDataValues as $newDataSet) {
                        $i = 0;
                        foreach ($newDataSet as $newDataVal) {
                            array_unshift($newArray[$i++], $newDataVal);
                        }
                    }
                    $this->dataValues = $newArray;
                }
            }
            $this->pointCount = count($this->dataValues);
        }
    }

		/**
     * Get TrendLine property 
     *
     * @return $this
     */
    public function getTrendLineProperty($property)
    {
				return $this->trendLine[$property];
    }

    /**
     * Set TrendLine property 
     *
     * @return $this
     */
    public function setTrendLineProperty($property, $value): self
    {
				$this->trendLine[$property] = $value;

				return $this;
		}

		/**
     * Get/Set scatterLine Property.
     *
     * @mixed 
     */
    public function getScatterLineProperty($property)
    {
        return $this->scatterLine[$property];
    }

    public function setScatterLineProperty($property, $value): self
    {
        $this->trendLine[$property] = $value;

        return $this;
    }

		/**
     * Set trendLine Style Properties.
     *
     * @param float $lineWidth
     * @param string $compoundType
     * @param string $dashType
     * @param string $capType
     * @param string $joinType
     * @param string $headArrowType
     * @param string $headArrowSize
     * @param string $endArrowType
     * @param string $endArrowSize
     */
    public function setTrendLineStyleProperties($lineWidth = null, $compoundType = null, $dashType = null, $capType = null, $joinType = null, $headArrowType = null, $headArrowSize = null, $endArrowType = null, $endArrowSize = null): void
    {
        ($lineWidth !== null) ? $this->trendlineStyleProperties['width'] = $this->getExcelPointsWidth((float) $lineWidth) : null;
        ($compoundType !== null) ? $this->trendlineStyleProperties['compound'] = (string) $compoundType : null;
        ($dashType !== null) ? $this->trendlineStyleProperties['dash'] = (string) $dashType : null;
        ($capType !== null) ? $this->trendlineStyleProperties['cap'] = (string) $capType : null;
        ($joinType !== null) ? $this->trendlineStyleProperties['join'] = (string) $joinType : null;
        ($headArrowType !== null) ? $this->trendlineStyleProperties['arrow']['head']['type'] = (string) $headArrowType : null;
        ($headArrowSize !== null) ? $this->trendlineStyleProperties['arrow']['head']['size'] = (string) $headArrowSize : null;
        ($endArrowType !== null) ? $this->trendlineStyleProperties['arrow']['end']['type'] = (string) $endArrowType : null;
        ($endArrowSize !== null) ? $this->trendlineStyleProperties['arrow']['end']['size'] = (string) $endArrowSize : null;
    }

    /**
     * Get trendLine Style Property.
     *
     * @param array|string $elements
     *
     * @return string
     */
    public function getTrendLineStyleProperty($elements)
    {
        return $this->getArrayElementsValue($this->trendlineStyleProperties, $elements);
    }

    /**
     * Get trendLine Style Arrow Excel Width.
     *
     * @param string $arrow
     *
     * @return string
     */
    public function getTrendLineStyleArrowWidth($arrow)
    {
        return $this->getLineStyleArrowSize($this->trendlineStyleProperties['arrow'][$arrow]['size'], 'w');
    }

    /**
     * Get trendLine Style Arrow Excel Length.
     *
     * @param string $arrow
     *
     * @return string
     */
    public function getTrendLineStyleArrowLength($arrow)
    {
        return $this->getLineStyleArrowSize($this->trendlineStyleProperties['arrow'][$arrow]['size'], 'len');
    }

    /**
     * Set trendLine Shadow Properties.
     *
     * @param int $shadowPresets
     * @param string $colorValue
     * @param string $colorType
     * @param string $colorAlpha
     * @param float $blur
     * @param int $angle
     * @param float $distance
     */
    public function setTrendLineShadowProperties($shadowPresets, $colorValue = null, $colorType = null, $colorAlpha = null, $blur = null, $angle = null, $distance = null): void
    {
        $this->setTrendLineShadowPresetsProperties((int) $shadowPresets)
            ->setTrendLineShadowColor(
                $colorValue ?? 			 $this->trendLineShadowProperties['color']['value'],
                $colorAlpha ?? (int) $this->trendLineShadowProperties['color']['alpha'],
                $colorType ?? 			 $this->trendLineShadowProperties['color']['type']
            )
            ->setTrendLineShadowBlur($blur)
            ->setTrendLineShadowAngle($angle)
            ->setTrendLineShadowDistance($distance);
    }

    /**
     * Set trendLine Shadow Color.
     *
     * @param int $presets
     *
     * @return $this
     */
    private function setTrendLineShadowPresetsProperties($presets)
    {
        $this->trendLineShadowProperties['presets'] = $presets;
        $this->setTrendLineShadowPropertiesMapValues($this->getTrendLineShadowPresetsMap($presets));

        return $this;
    }

    /**
     * Set trendLine Shadow Properties from Mapped Values.
     *
     * @param mixed $reference
     *
     * @return $this
     */
    private function setTrendLineShadowPropertiesMapValues(array $propertiesMap, &$reference = null)
    {
        $base_reference = $reference;
        foreach ($propertiesMap as $property_key => $property_val) {
            if (is_array($property_val)) {
                if ($reference === null) {
                    $reference = &$this->trendLineShadowProperties[$property_key];
                } else {
                    $reference = &$reference[$property_key];
                }
                $this->setTrendLineShadowPropertiesMapValues($property_val, $reference);
            } else {
                if ($base_reference === null) {
                    $this->trendLineShadowProperties[$property_key] = $property_val;
                } else {
                    $reference[$property_key] = $property_val;
                }
            }
        }

        return $this;
    }

    /**
     * Set trendLine Shadow Color.
     *
     * @param string $color
     * @param int $alpha
     * //@param string $alphaType
     * @param string $colorType
     *
     * @return $this
     */
    // private function setTrendLineShadowColor($color, $alpha, $alphaType)
    private function setTrendLineShadowColor($color, $alpha, $colorType)
    {
        //$this->trendLineShadowProperties['color'] = $this->setColorProperties($color, $alpha, $alphaType);
        $this->trendLineShadowProperties['color'] = $this->setColorProperties($color, $alpha, $colorType);

        return $this;
    }

    /**
     * Set trendLine Shadow Blur.
     *
     * @param float $blur
     *
     * @return $this
     */
    private function setTrendLineShadowBlur($blur)
    {
        if ($blur !== null) {
            $this->trendLineShadowProperties['blur'] = (string) $this->getExcelPointsWidth($blur);
        }

        return $this;
    }

    /**
     * Set trendLine Shadow Angle.
     *
     * @param int $angle
     *
     * @return $this
     */
    private function setTrendLineShadowAngle($angle)
    {
        if ($angle !== null) {
            $this->trendLineShadowProperties['direction'] = (string) $this->getExcelPointsAngle($angle);
        }

        return $this;
    }

    /**
     * Set trendLine Shadow Distance.
     *
     * @param float $distance
     *
     * @return $this
     */
    private function setTrendLineShadowDistance($distance)
    {
        if ($distance !== null) {
            $this->trendLineShadowProperties['distance'] = (string) $this->getExcelPointsWidth($distance);
        }

        return $this;
    }

    /**
     * Get trendLine Shadow Property.
     *
     * @param string|string[] $elements
     *
     * @return null|array|int|string
     */
    public function getTrendLineShadowProperty($elements)
    {
        return $this->getArrayElementsValue($this->trendLineShadowProperties, $elements);
    }

    /**
     * Set trendLine Glow Properties.
     *
     * @param float $size
     * @param string $colorValue
     * @param int $colorAlpha
     * @param string $colorType
     */
    public function setTrendLineGlowProperties($size, $colorValue = null, $colorAlpha = null, $colorType = null): void
    {
        $this->setTrendLineGlowSize($size)
            ->setTrendLineGlowColor(
                $colorValue ?? 			 $this->trendLineGlowProperties['color']['value'],
                $colorAlpha ?? (int) $this->trendLineGlowProperties['color']['alpha'],
                $colorType ?? 			 $this->trendLineGlowProperties['color']['type']
            );
    }

    /**
     * Get trendLine Glow Property.
     *
     * @param array|string $property
     *
     * @return string
     */
    public function getTrendLineGlowProperty($property)
    {
        return $this->getArrayElementsValue($this->trendLineGlowProperties, $property);
    }

    /**
     * Set trendLine Glow Size.
     *
     * @param float $size
     *
     * @return $this
     */
    private function setTrendLineGlowSize($size)
    {
        if ($size !== null) {
            $this->trendLineGlowProperties['size'] = $this->getExcelPointsWidth($size);
        }

        return $this;
    }

    /**
     * Set trendLine Glow Color.
     *
     * @param string $color
     * @param int $alpha
     * @param string $colorType
     *
     * @return $this
     */
    private function setTrendLineGlowColor($color, $alpha, $colorType)
    {
        $this->trendLineGlowProperties['color'] = $this->setColorProperties($color, $alpha, $colorType);

        return $this;
    }

    /**
     * Set trendLine Soft Edges Size.
     *
     * @param float $size
     */
    public function setTrendLineSoftEdges($size): void
    {
        if ($size !== null) {
            $trendLineSoftEdges['size'] = (string) $this->getExcelPointsWidth($size);
        }
    }

    /**
     * Get trendLine Soft Edges Size.
     *
     * @return string
     */
    public function getTrendLineSoftEdgesSize()
    {
        return $this->trendLineSoftEdges['size'];
    }
--------------------------------------
		/**
     * Set scatterLine Style Properties.
     *
     * @param float $lineWidth
     * @param string $compoundType
     * @param string $dashType
     * @param string $capType
     * @param string $joinType
     * @param string $headArrowType
     * @param string $headArrowSize
     * @param string $endArrowType
     * @param string $endArrowSize
     */
    public function setScatterLineStyleProperties($lineWidth = null, $compoundType = null, $dashType = null, $capType = null, $joinType = null, $headArrowType = null, $headArrowSize = null, $endArrowType = null, $endArrowSize = null): void
    {
        ($lineWidth !== null) ? $this->scatterlineStyleProperties['width'] = $this->getExcelPointsWidth((float) $lineWidth) : null;
        ($compoundType !== null) ? $this->scatterlineStyleProperties['compound'] = (string) $compoundType : null;
        ($dashType !== null) ? $this->scatterlineStyleProperties['dash'] = (string) $dashType : null;
        ($capType !== null) ? $this->scatterlineStyleProperties['cap'] = (string) $capType : null;
        ($joinType !== null) ? $this->scatterlineStyleProperties['join'] = (string) $joinType : null;
        ($headArrowType !== null) ? $this->scatterlineStyleProperties['arrow']['head']['type'] = (string) $headArrowType : null;
        ($headArrowSize !== null) ? $this->scatterlineStyleProperties['arrow']['head']['size'] = (string) $headArrowSize : null;
        ($endArrowType !== null) ? $this->scatterlineStyleProperties['arrow']['end']['type'] = (string) $endArrowType : null;
        ($endArrowSize !== null) ? $this->scatterlineStyleProperties['arrow']['end']['size'] = (string) $endArrowSize : null;
    }

    /**
     * Get scatterLine Style Property.
     *
     * @param array|string $elements
     *
     * @return string
     */
    public function getScatterLineStyleProperty($elements)
    {
        return $this->getArrayElementsValue($this->scatterlineStyleProperties, $elements);
    }

    /**
     * Get scatterLine Style Arrow Excel Width.
     *
     * @param string $arrow
     *
     * @return string
     */
    public function getScatterLineStyleArrowWidth($arrow)
    {
        return $this->getLineStyleArrowSize($this->scatterlineStyleProperties['arrow'][$arrow]['size'], 'w');
    }

    /**
     * Get scatterLine Style Arrow Excel Length.
     *
     * @param string $arrow
     *
     * @return string
     */
    public function getScatterLineStyleArrowLength($arrow)
    {
        return $this->getLineStyleArrowSize($this->scatterlineStyleProperties['arrow'][$arrow]['size'], 'len');
    }

    /**
     * Set scatterLineShadow Properties.
     *
     * @param int $shadowPresets
     * @param string $colorValue
     * @param string $colorType
     * @param string $colorAlpha
     * @param float $blur
     * @param int $angle
     * @param float $distance
     */
    public function setScatterLineShadowProperties($shadowPresets, $colorValue = null, $colorType = null, $colorAlpha = null, $blur = null, $angle = null, $distance = null): void
    {
        $this->setScatterLineShadowPresetsProperties((int) $shadowPresets)
            ->setScatterLineShadowColor(
                $colorValue ?? 			 $this->scatterLineShadowProperties['color']['value'],
                $colorAlpha ?? (int) $this->scatterLineShadowProperties['color']['alpha'],
                $colorType ?? 			 $this->scatterLineShadowProperties['color']['type']
            )
            ->setScatterLineShadowBlur($blur)
            ->setScatterLineShadowAngle($angle)
            ->setScatterLineShadowDistance($distance);
    }

    /**
     * Set scatterLineShadow Color.
     *
     * @param int $presets
     *
     * @return $this
     */
    private function setScatterLineShadowPresetsProperties($presets)
    {
        $this->scatterLineShadowProperties['presets'] = $presets;
        $this->setScatterLineShadowPropertiesMapValues($this->getScatterLineShadowPresetsMap($presets));

        return $this;
    }

    /**
     * Set scatterLineShadow Properties from Mapped Values.
     *
     * @param mixed $reference
     *
     * @return $this
     */
    private function setScatterLineShadowPropertiesMapValues(array $propertiesMap, &$reference = null)
    {
        $base_reference = $reference;
        foreach ($propertiesMap as $property_key => $property_val) {
            if (is_array($property_val)) {
                if ($reference === null) {
                    $reference = &$this->scatterLineShadowProperties[$property_key];
                } else {
                    $reference = &$reference[$property_key];
                }
                $this->setScatterLineShadowPropertiesMapValues($property_val, $reference);
            } else {
                if ($base_reference === null) {
                    $this->scatterLineShadowProperties[$property_key] = $property_val;
                } else {
                    $reference[$property_key] = $property_val;
                }
            }
        }

        return $this;
    }

    /**
     * Set scatterLineShadow Color.
     *
     * @param string $color
     * @param int $alpha
     * //@param string $alphaType
     * @param string $colorType
     *
     * @return $this
     */
    // private function setScatterLineShadowColor($color, $alpha, $alphaType)
    private function setScatterLineShadowColor($color, $alpha, $colorType)
    {
        // $this->scatterLineShadowProperties['color'] = $this->setColorProperties($color, $alpha, $alphaType);
        $this->scatterLineShadowProperties['color'] = $this->setColorProperties($color, $alpha, $colorType);

        return $this;
    }

    /**
     * Set scatterLineShadow Blur.
     *
     * @param float $blur
     *
     * @return $this
     */
    private function setScatterLineShadowBlur($blur)
    {
        if ($blur !== null) {
            $this->scatterLineShadowProperties['blur'] = (string) $this->getExcelPointsWidth($blur);
        }

        return $this;
    }

    /**
     * Set scatterLineShadow Angle.
     *
     * @param int $angle
     *
     * @return $this
     */
    private function setScatterLineShadowAngle($angle)
    {
        if ($angle !== null) {
            $this->scatterLineShadowProperties['direction'] = (string) $this->getExcelPointsAngle($angle);
        }

        return $this;
    }

    /**
     * Set scatterLineShadow Distance.
     *
     * @param float $distance
     *
     * @return $this
     */
    private function setScatterLineShadowDistance($distance)
    {
        if ($distance !== null) {
            $this->scatterLineShadowProperties['distance'] = (string) $this->getExcelPointsWidth($distance);
        }

        return $this;
    }

    /**
     * Get scatterLineShadow Property.
     *
     * @param string|string[] $elements
     *
     * @return null|array|int|string
     */
    public function getScatterLineShadowProperty($elements)
    {
        return $this->getArrayElementsValue($this->scatterLineShadowProperties, $elements);
    }

    /**
     * Set scatterLineGlow Properties.
     *
     * @param float $size
     * @param string $colorValue
     * @param int $colorAlpha
     * @param string $colorType
     */
    public function setScatterLineGlowProperties($size, $colorValue = null, $colorAlpha = null, $colorType = null): void
    {
        $this->setScatterLineGlowSize($size)
            ->setScatterLineGlowColor(
                $colorValue ?? 			 $this->scatterLineGlowProperties['color']['value'],
                $colorAlpha ?? (int) $this->scatterLineGlowProperties['color']['alpha'],
                $colorType ?? 			 $this->scatterLineGlowProperties['color']['type']
            );
    }

    /**
     * Get scatterLineGlow Property.
     *
     * @param array|string $property
     *
     * @return string
     */
    public function getScatterLineGlowProperty($property)
    {
        return $this->getArrayElementsValue($this->scatterLineGlowProperties, $property);
    }

    /**
     * Set scatterLineGlow Size.
     *
     * @param float $size
     *
     * @return $this
     */
    private function setScatterLineGlowSize($size)
    {
        if ($size !== null) {
            $this->scatterLineGlowProperties['size'] = $this->getExcelPointsWidth($size);
        }

        return $this;
    }

    /**
     * Set scatterLineGlow Color.
     *
     * @param string $color
     * @param int $alpha
     * @param string $colorType
     *
     * @return $this
     */
    private function setScatterLineGlowColor($color, $alpha, $colorType)
    {
        $this->scatterLineGlowProperties['color'] = $this->setColorProperties($color, $alpha, $colorType);

        return $this;
    }

    /**
     * Set scatterLine Soft Edges Size.
     *
     * @param float $size
     */
    public function setScatterLineSoftEdges($size): void
    {
        if ($size !== null) {
            $scatterLineSoftEdges['size'] = (string) $this->getExcelPointsWidth($size);
        }
    }

    /**
     * Get scatterLine Soft Edges Size.
     *
     * @return string
     */
    public function getScatterLineSoftEdgesSize()
    {
        return $this->scatterLineSoftEdges['size'];
    }
}
