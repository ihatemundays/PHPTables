<?php
namespace MatrixTables;

const TYPE_TABLE = 1;
const TYPE_SECTION = 2;
const TYPE_CELL = 3;
const TYPE_ROW = 4;
const TYPE_COLUMN = 5;

const TABLE_FULL = 1;
const TABLE_COLLAPSED = 2;

const SECTION_HEADER = 1;
const SECTION_BODY = 2;
const SECTION_FOOTER = 3;

const RENDER = 0;	
const SKIP = false;	

class Amorphous
{
	protected $_values = array();
	protected $_callbacks = array();
	
	function __get($property)
	{
		if (isset($this->_values[$property]))
		{
			return $this->_values[$property];
		}
		elseif (isset($this->_callbacks[$property]) && is_callable($this->_callbacks[$property]))
		{
			return ($this->_values[$property] = $this->_callbacks[$property]($this));
		}
		
		return null;
	}
	
	function __set($property, $value)
	{
		$this->_values[$property] = $value;
	}	
};

class Column extends Amorphous 
{ 
	public $table;
	public $section;
	
	public $index;
	public $rows;	

	function __construct($table, $section, $column, $rows)
	{
		$this->table = $table;
		$this->section = $section;
		
		$this->index = $column;		
		$this->rows = $rows;		

		$this->_callbacks = $section->callbacks(TYPE_COLUMN);			
	}
};

class Row extends Amorphous 
{ 
	public $table;
	public $section;
	
	public $index;
	public $columns;
	
	function __construct($table, $section, $row, $columns)
	{
		$this->table = $table;
		$this->section = $section;
				
		$this->index = $row;
		$this->columns = $columns;		

		$this->_callbacks = $section->callbacks(TYPE_ROW);							
	}
};

class Cell extends Amorphous 
{ 
	public $table;
	public $section;
	
	public $column;
	public $row;
	
	public $rows = 1;
	public $columns = 1;
	
	function __construct($table, $section, $column, $row)
	{
		$this->table = $table;
		$this->section = $section;
		
		$this->column = $column;
		$this->row = $row;
		
		$this->_callbacks = $section->callbacks(TYPE_CELL);					
	}

	public function expand($columns = 1, $rows = 1)
	{
		$this->columns = ($columns <= 0 ? 1 : (int)$columns);
		$this->rows = ($rows <= 0 ? 1 : (int)$rows);
	}
};

class Section extends Amorphous
{
	public $table;

	protected $_sectionTag = 'tbody';
	protected $_cellTag = 'td';

	protected $_columns = array();
	protected $_rows = array();
	protected $_cells = array();
	
	protected $_columnCount = 0;	
	protected $_rowCount = 0;
	
	protected $_tableCallbacks = array();
	protected $_columnCallbacks = array();
	protected $_rowCallbacks = array();
	protected $_cellCallbacks = array();
	
	protected $_tableAttributes = array();
	
	protected $_renderMap = array();		

	function __construct($table, &$callbacks, &$attributes, $columns, $rows)
	{
		$this->table = $table;
		$this->_tableCallbacks = &$callbacks;
		$this->_tableAttributes = &$attributes;
	
		$this->_columnCount = ($columns <= 0 ? 1 : (int)$columns);
		$this->_rowCount = ($rows <= 0 ? 1 : (int)$rows);		
		
		for ($a = 0; $a <= $this->_columnCount; ++$a)
		{
			$this->_renderMap[$a] = array();
			
			for ($b = 0; $b <= $this->_rowCount; ++$b)
			{
				$this->_renderMap[$a][$b] = array(RENDER => null);
			}
		}		
	}
	
	protected function _build($type)
	{
		switch ($type)
		{
			case TYPE_COLUMN:
				list($ignore, $column) = func_get_args();
				
				if (!isset($this->_columns[$column]))
				{
					$this->_columns[$column] = new Column($this->table, $this, $column, $this->_rowCount);
				}				
				
				return $this->_columns[$column];
			case TYPE_ROW:
				list($ignore, $row) = func_get_args();
				
				if (!isset($this->_rows[$row]))
				{
					$this->_rows[$row] = new Row($this->table, $this, $row, $this->_columnCount);
				}				

				return $this->_rows[$row];
			case TYPE_CELL: // Cell considers rows and columns.
				list($ignore, $column, $row) = func_get_args();
				
				$_column = $this->_build(TYPE_COLUMN, $column);
				$_row = $this->_build(TYPE_ROW, $row);				
				
				if (!isset($this->_cells[$column][$row]))
				{
					$this->_cells[$column][$row] = new Cell($this->table, $this, $_column, $_row);
				}				
				
				return $this->_cells[$column][$row];
		}
	}			
	
	public function row($row)
	{
		return $this->_build(TYPE_ROW, $row);
	}

	public function column($column)
	{
		return $this->_build(TYPE_COLUMN, $column);
	}
	
	public function cell($column, $row)
	{
		return $this->_build(TYPE_CELL, $column, $row);
	}

	public function callbacks($type)
	{
		switch ($type)
		{
			case TYPE_TABLE:
				return $this->_tableCallbacks;
			case TYPE_SECTION:
				return $this->_callbacks;
			case TYPE_COLUMN:
				return $this->_columnCallbacks;
			case TYPE_ROW:
				return $this->_rowCallbacks;
			case TYPE_CELL:
				return $this->_cellCallbacks;
		}
		
		return array(); // Return an empty array.
	}
	
	public function callback($type, $property, $callback)
	{
		if (is_callable($callback))
		{
			switch ($type)
			{
				case TYPE_TABLE:
					$this->_tableCallbacks[$property] = $callback;
					break;			
				case TYPE_SECTION:
					$this->_callbacks[$property] = $callback;
					break;			
				case TYPE_COLUMN:
					$this->_columnCallbacks[$property] = $callback;
					break;
				case TYPE_ROW:
					$this->_rowCallbacks[$property] = $callback;
					break;
				case TYPE_CELL:
					$this->_cellCallbacks[$property] = $callback;
					break;
			}
		}
	}
	
	protected function _parseMapDimension($type, $selection)
	{
		$selected = array();		
		
		$max = 0;
	
		if ($type == TYPE_COLUMN)
		{
			$max = $this->_columnCount - 1;
		}
		elseif ($type == TYPE_ROW)
		{
			$max = $this->_rowCount - 1;
		}
		
		// List. Reapply.
		$selections = explode(';', $selection);		
		
		foreach ($selections as $selection)
		{
			$tmp = array();
			
			if ($selection == '*') // All.			
			{
				$tmp = range(0, $max);
			}
			elseif ($selection == 'first') // First.
			{
				$tmp = array(0);
			}
			elseif ($selection == 'last') // Last.
			{
				$tmp = array($max);
			}
			elseif ($selection == 'odd') // Odd.
			{
				$tmp = range(0, $max, 2);
			}
			elseif ($selection == 'even') // Even.
			{
				if ($max != 0)
				{
					$tmp = range(1, $max, 2);
				}
			}
			elseif (preg_match('/0|[1-9][0-9]*-0|[1-9][0-9]*/', $selection)) // Range.
			{
				list($start, $stop) = explode('-', $selection);
				
				if ($start > $max) { $start = $max; }
				if ($stop < $start) { $stop = $start; }
				
				$tmp = range($start, $stop);
			}
			elseif (preg_match('/0|[1-9][0-9]*/', $selection)) // Index.
			{
				if ($selection >= 0 && $selection <= $max) { $tmp = array($selection); }
			}
			
			$selected = array_merge($selected, $tmp);
		}		
		
		return array_unique($selected);
	}
	
	protected function _assignRenderMapRender(&$location, $callback)
	{
		if (is_callable($callback))
		{
			if (!is_array(@$location))
			{
				$location = array();
			}
		
			$location[] = $callback;
		}
	}
	
	protected function _assignRenderMapAttributes(&$location, $attributes)
	{
		foreach ($attributes as $attribute => $callback)
		{
			if (is_callable($callback))
			{
				if (!is_array(@$location[$attribute]))
				{
					$location[$attribute] = array();
				}
		
				$location[$attribute][] = $callback;
			}
		}
	}	
	
	public function map($selection, $callback, $attributes = array())
	{
		list($x, $y) = explode(',', $selection);
		
		$x = $this->_parseMapDimension(TYPE_COLUMN, $x);
		$y = $this->_parseMapDimension(TYPE_ROW, $y);
		
		$appliedRowAttributes = false;
		
		foreach ($x as $xOffset)
		{
			foreach ($y as $yOffset)
			{
				$this->_assignRenderMapRender($this->_renderMap[$xOffset][$yOffset][RENDER], $callback);
				
				// Assign attributes.
				if (isset($attributes[TYPE_CELL]) && is_array($attributes[TYPE_CELL]))
				{
					$this->_assignRenderMapAttributes($this->_renderMap[$xOffset][$yOffset], $attributes[TYPE_CELL]); // All other index values are properties of the cell.
				}
				
				// Assign attributes.
				if (!$appliedRowAttributes && isset($attributes[TYPE_ROW]) && is_array($attributes[TYPE_ROW]))
				{
					$this->_assignRenderMapAttributes($this->_renderMap[$this->_columnCount][$yOffset], $attributes[TYPE_ROW]);
				}
			}
			
			// Make it this far, you already went through all rows. Don't do again.
			$appliedRowAttributes = true;
			
			// Assign attributes.
			if (isset($attributes[TYPE_COLUMN]) && is_array($attributes[TYPE_COLUMN]))
			{
				$this->_assignRenderMapAttributes($this->_renderMap[$xOffset][$this->_rowCount], $attributes[TYPE_COLUMN]);
			}
		}
		
		// Assign attributes.
		if (isset($attributes[TYPE_SECTION]) && is_array($attributes[TYPE_SECTION]))
		{
			$this->_assignRenderMapAttributes($this->_renderMap[$this->_columnCount][$this->_rowCount], $attributes[TYPE_SECTION]);
		}
		
		// Assign attributes.
		if (isset($attributes[TYPE_TABLE]) && is_array($attributes[TYPE_TABLE]))
		{
			$this->_assignRenderMapAttributes($this->_tableAttributes, $attributes[TYPE_TABLE]);
		}
	}
	
	protected function _processAttributes($element, $attributes, $previousAttributes = array())
	{
		$rtn = $previousAttributes;
		
		if (is_array($attributes))
		{
			foreach ($attributes as $attribute => $callbacks)
			{
				if (is_array($callbacks))
				{
					foreach ($callbacks as $callback)
					{
						$rtn[$attribute] = $callback($element, @$rtn[$attribute]);
					}
				}
			}
		}
		
		return $rtn;
	}
	
	protected function _renderAttributes()
	{
		$attributes = array();
		$parameters = func_get_args();
		
		$rtn = '';
		
		foreach ($parameters as $parameter)
		{
			$attributes = array_merge($attributes, $parameter);
		}
		
		foreach ($attributes as $attribute => $value)
		{
			$rtn .= ' ' . htmlentities($attribute) . '="' . htmlentities($value) . '"';			
		}
		
		return $rtn;
	}
	
	public function _render($element, $renders)
	{
		$rtn = '';
		
		foreach ($renders as $render)
		{
			$rtn = $render($element, $rtn);
		}
		
		return $rtn;
	}	
	
	public function render()
	{	
		echo "\t" . '<' . $this->_sectionTag . $this->_renderAttributes($this->_processAttributes($this, $this->_renderMap[$this->_columnCount][$this->_rowCount])) . '>' . PHP_EOL;
	
		for ($a = 0; $a < $this->_rowCount; ++$a)
		{
			echo "\t\t" . '<tr' . $this->_renderAttributes($this->_processAttributes($this->row($a), $this->_renderMap[$this->_columnCount][$a])) . '>' . PHP_EOL;
		
			for ($b = 0; $b < $this->_columnCount; ++$b)
			{
				$cell = $this->_build(TYPE_CELL, $b, $a); // X x Y
								
				if ($this->_renderMap[$b][$a][RENDER] !== SKIP)
				{
					$render = ($this->_renderMap[$b][$a][RENDER] ? $this->_render($cell, $this->_renderMap[$b][$a][RENDER]) : '&nsbp;');
				
					if ($render === SKIP) { continue; }
					
					echo "\t\t\t" . '<' . $this->_cellTag . ' colspan="' . htmlentities($cell->columns) . '" rowspan="' . htmlentities($cell->rows) . '"' . 
						$this->_renderAttributes(
							$this->_processAttributes($this->column($b), $this->_renderMap[$b][$this->_rowCount]),
							$this->_processAttributes($cell, array_slice($this->_renderMap[$b][$a], 1))
						) .
						'>';
					echo $render;
					echo '</' . $this->_cellTag . '>' . PHP_EOL;
					
					// Map to the cell expansion.
					for ($c = $a, $d = min($this->_rowCount, $c + $cell->rows); $c < $d; ++$c)
					{
						for ($e = $b, $f = min($this->_columnCount, $e + $cell->columns); $e < $f; ++$e)
						{						
							if ($c == $a && $e == $b) { continue; }
							
							$childCell = $this->_build(TYPE_CELL, $e, $c);
							
							if (is_array($this->_renderMap[$e][$c][RENDER]))
							{
								$this->_render($childCell, $this->_renderMap[$e][$c][RENDER]);
							}
							
							$this->_renderMap[$e][$c][RENDER] = SKIP;
						}
					}					
				}
			}
			
			echo "\t\t" . '</tr>' . PHP_EOL;			
		}
		
		echo "\t" . '</' . $this->_sectionTag . '>' . PHP_EOL;
		
	}	
};

class HeaderSection extends Section
{
	protected $_sectionTag = 'thead';
	protected $_cellTag = 'th';
}

class BodySection extends Section { };

class FooterSection extends Section
{
	protected $_sectionTag = 'tfoot';
	protected $_cellTag = 'th';
};

class Table extends Section { 
	protected $_attributes = array();
};

class CollapsedTable extends Table
{
	function __construct($columns, $rows)
	{		
		parent::__construct($this, $this->_callbacks, $this->_attributes, $columns, $rows);
	}
	
	public function render()
	{
		echo '<table' . $this->_renderAttributes($this->_processAttributes($this, $this->_attributes)) . '>' . PHP_EOL;
		
		for ($a = 0; $a < $this->_rowCount; ++$a)
		{
			echo "\t" . '<tr' . $this->_renderAttributes($this->_processAttributes($this->row($a), $this->_renderMap[$this->_columnCount][$a])) . '>' . PHP_EOL;
		
			for ($b = 0; $b < $this->_columnCount; ++$b)
			{
				$cell = $this->_build(TYPE_CELL, $b, $a); // X x Y
								
				if ($this->_renderMap[$b][$a][RENDER] !== SKIP)
				{
					$render = ($this->_renderMap[$b][$a][RENDER] ? $this->_render($cell, $this->_renderMap[$b][$a][RENDER]) : '&nsbp;');
				
					if ($render === SKIP) { continue; }
					
					echo "\t\t" . '<' . $this->_cellTag . ' colspan="' . htmlentities($cell->columns) . '" rowspan="' . htmlentities($cell->rows) . '"' . 
						$this->_renderAttributes(
							$this->_processAttributes($this->column($b), $this->_renderMap[$b][$this->_rowCount]),
							$this->_processAttributes($cell, array_slice($this->_renderMap[$b][$a], 1))
						) .
						'>';
					echo $render;
					echo '</td>' . PHP_EOL;
					
					// Map to the cell expansion.
					for ($c = $a, $d = min($this->_rowCount, $c + $cell->rows); $c < $d; ++$c)
					{
						for ($e = $b, $f = min($this->_columnCount, $e + $cell->columns); $e < $f; ++$e)
						{						
							if ($c == $a && $e == $b) { continue; }
							
							$childCell = $this->_build(TYPE_CELL, $e, $c);
							
							if (is_array($this->_renderMap[$e][$c][RENDER]))
							{
								$this->_render($childCell, $this->_renderMap[$e][$c][RENDER]);
							}
							
							$this->_renderMap[$e][$c][RENDER] = SKIP;
						}
					}					
				}
			}
			
			echo "\t" . '</tr>' . PHP_EOL;			
		}
		
		echo '</table>' . PHP_EOL;
	}
};

class FullTable extends Table
{ 	
	private $_header = null;
	private $_body = null;
	private $_footer = null;
	
	function __construct()
	{
		$this->table = $this;
		$this->_tableCallbacks = $this->_callbacks;
		$this->_tableAttributes = $this->_attributes;
	}
	
	private function _setSection($section, $arguments)
	{
		list($columns, $rows) = $arguments;
				
		switch ($section)
		{
			case SECTION_HEADER:
				return new HeaderSection($this, $this->_callbacks, $this->_attributes, $columns, $rows);
			case SECTION_BODY:
				return new BodySection($this, $this->_callbacks, $this->_attributes, $columns, $rows);
			case SECTION_FOOTER:
				return new FooterSection($this, $this->_callbacks, $this->_attributes, $columns, $rows);
		}
		
		return null;
	}
	
	public function header()
	{
		if (($arguments = func_get_args()))
		{
			$this->_header = $this->_setSection(SECTION_HEADER, $arguments);
		}
		
		return $this->_header;
	}
	
	public function body()
	{
		if (($arguments = func_get_args()))
		{
			$this->_body = $this->_setSection(SECTION_BODY, $arguments);
		}
		
		return $this->_body;
	}
	
	public function footer()
	{
		if (($arguments = func_get_args()))
		{
			$this->_footer = $this->_setSection(SECTION_FOOTER, $arguments);
		}
		
		return $this->_footer;
	}
	
	public function render()
	{
		echo '<table' . $this->_renderAttributes($this->_processAttributes($this, $this->_attributes)) . '>' . PHP_EOL;
		
		if ($this->_header)
		{
			$this->_header->render();
		}
		
		if ($this->_body)
		{
			$this->_body->render();
		}
		
		if ($this->_footer)
		{
			$this->_footer->render();
		}
		
		echo '</table>' . PHP_EOL;
	}
};

class TableFactory
{
	static function build($type)
	{
		switch ($type)
		{
			case TABLE_FULL:
				return new FullTable();
			case TABLE_COLLAPSED:
				list($tmp, $rows, $columns) = func_get_args();
				
				return new CollapsedTable($rows, $columns);
		}
	}
};